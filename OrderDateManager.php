<?php

namespace REDQ_RnB;

/**
 * Keep T-Rent's first and last rental day visible in cart, checkout,
 * order details and WooCommerce emails.
 *
 * This class only reads RnB rental dates and adds display metadata. It does
 * not change prices, totals, payment status, deposits or availability.
 */
class OrderDateManager
{
    const FIRST_DAY_KEY = 'Første leiedag';
    const LAST_DAY_KEY  = 'Siste leiedag';

    public function __construct()
    {
        add_filter('rnb_format_rental_item_data', [$this, 'add_rental_date_rows'], 50, 4);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_dates'], 30, 4);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'restore_missing_order_dates'], 30, 2);
    }

    /**
     * Replace RnB's optional date rows with stable T-Rent date rows.
     * The existing pickup_datetime/return_datetime slots are reused, so the
     * information is not duplicated when RnB's own date display is enabled.
     */
    public function add_rental_date_rows($results, $product_id, $data, $settings)
    {
        if (!is_array($results) || !is_array($data)) {
            return $results;
        }

        $dates = $this->extract_dates_from_data($data);

        if ($dates['first'] !== '') {
            $results['pickup_datetime'] = $this->date_row(
                self::FIRST_DAY_KEY,
                $dates['first'],
                'pickup_datetime'
            );
        }

        if ($dates['last'] !== '') {
            $results['return_datetime'] = $this->date_row(
                self::LAST_DAY_KEY,
                $dates['last'],
                'return_datetime'
            );
        }

        return $results;
    }

    /**
     * Defensive checkout fallback. Normally CartHandler has already saved the
     * rows produced above; this only fills them when another plugin removed or
     * bypassed RnB's prepared order-item data.
     */
    public function save_order_dates($item, $cart_item_key, $values, $order)
    {
        if (!$this->is_rental_checkout_item($values)) {
            return;
        }

        $dates = $this->extract_dates_from_data($values['rental_data']);

        if ($dates['first'] !== '' && $item->get_meta(self::FIRST_DAY_KEY, true) === '') {
            $item->add_meta_data(self::FIRST_DAY_KEY, $dates['first'], true);
        }

        if ($dates['last'] !== '' && $item->get_meta(self::LAST_DAY_KEY, true) === '') {
            $item->add_meta_data(self::LAST_DAY_KEY, $dates['last'], true);
        }
    }

    /**
     * Older orders can already contain the dates only in RnB's hidden data.
     * Add display-only rows when the visible metadata is missing. This also
     * makes the dates appear if an existing order email is sent again.
     */
    public function restore_missing_order_dates($formatted_meta, $item)
    {
        if (!is_array($formatted_meta) || !$this->is_rental_order_item($item)) {
            return $formatted_meta;
        }

        $has_first = false;
        $has_last  = false;

        foreach ($formatted_meta as $meta) {
            $key = isset($meta->key) ? (string) $meta->key : '';
            $display_key = isset($meta->display_key) ? wp_strip_all_tags((string) $meta->display_key) : '';

            if ($key === self::FIRST_DAY_KEY || $display_key === self::FIRST_DAY_KEY) {
                $has_first = true;
            }

            if ($key === self::LAST_DAY_KEY || $display_key === self::LAST_DAY_KEY) {
                $has_last = true;
            }
        }

        if ($has_first && $has_last) {
            return $formatted_meta;
        }

        $dates = $this->extract_dates_from_order_item($item);

        if (!$has_first && $dates['first'] !== '') {
            $formatted_meta['trent_first_rental_day'] = $this->formatted_meta(
                self::FIRST_DAY_KEY,
                $dates['first']
            );
        }

        if (!$has_last && $dates['last'] !== '') {
            $formatted_meta['trent_last_rental_day'] = $this->formatted_meta(
                self::LAST_DAY_KEY,
                $dates['last']
            );
        }

        return $formatted_meta;
    }

    private function is_rental_checkout_item($values)
    {
        if (empty($values['rental_data']) || !is_array($values['rental_data'])) {
            return false;
        }

        if (isset($values['data']) && is_object($values['data']) && method_exists($values['data'], 'get_type')) {
            return $values['data']->get_type() === 'redq_rental';
        }

        return false;
    }

    private function is_rental_order_item($item)
    {
        if (!is_object($item) || !method_exists($item, 'get_product')) {
            return false;
        }

        $product = $item->get_product();
        if ($product && method_exists($product, 'get_type') && $product->get_type() === 'redq_rental') {
            return true;
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $meta_key) {
            if (is_array($item->get_meta($meta_key, true))) {
                return true;
            }
        }

        return false;
    }

    private function extract_dates_from_order_item($item)
    {
        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $meta_key) {
            $rental_data = $item->get_meta($meta_key, true);
            if (is_array($rental_data)) {
                $dates = $this->extract_dates_from_data($rental_data);
                if ($dates['first'] !== '' || $dates['last'] !== '') {
                    return $dates;
                }
            }
        }

        $first = $this->first_item_meta($item, [
            '_pickup_hidden_datetime',
            'pickup_hidden_datetime',
            'pickup_date',
        ]);
        $last = $this->first_item_meta($item, [
            '_return_hidden_datetime',
            'return_hidden_datetime',
            'dropoff_date',
            'return_date',
        ]);

        return [
            'first' => $this->format_date($first),
            'last'  => $this->format_date($last),
        ];
    }

    private function extract_dates_from_data($data)
    {
        $posted_data = !empty($data['posted_data']) && is_array($data['posted_data'])
            ? $data['posted_data']
            : [];

        $first = $this->first_non_empty([
            isset($data['pickup_date']) ? $data['pickup_date'] : '',
            isset($posted_data['pickup_date']) ? $posted_data['pickup_date'] : '',
        ]);

        $last = $this->first_non_empty([
            isset($data['dropoff_date']) ? $data['dropoff_date'] : '',
            isset($data['return_date']) ? $data['return_date'] : '',
            isset($posted_data['dropoff_date']) ? $posted_data['dropoff_date'] : '',
            isset($posted_data['return_date']) ? $posted_data['return_date'] : '',
        ]);

        return [
            'first' => $this->format_date($first),
            'last'  => $this->format_date($last),
        ];
    }

    private function first_item_meta($item, $keys)
    {
        foreach ($keys as $key) {
            $value = $item->get_meta($key, true);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function first_non_empty($values)
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private function format_date($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = explode('|', $value, 2)[0];

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            $timestamp = strtotime($matches[1] . ' 12:00:00');
        } else {
            $timestamp = strtotime($value);
        }

        if ($timestamp === false) {
            return '';
        }

        return date_i18n('d.m.Y', $timestamp);
    }

    private function date_row($key, $value, $meta_key)
    {
        return [
            'meta_key' => $meta_key,
            'type'     => 'single',
            'summary'  => false,
            'key'      => $key,
            'data'     => [
                'name' => $value,
            ],
        ];
    }

    private function formatted_meta($key, $value)
    {
        return (object) [
            'key'           => $key,
            'value'         => $value,
            'display_key'   => $key,
            'display_value' => $value,
        ];
    }
}

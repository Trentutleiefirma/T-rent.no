<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App_Rental
{
    public static function active_order_statuses()
    {
        if (!function_exists('wc_get_order_statuses')) {
            return [];
        }

        $excluded = [
            'wc-cancelled',
            'wc-refunded',
            'wc-failed',
            'wc-pending',
            'wc-checkout-draft',
            'wc-rnb-fake-order',
        ];

        return array_values(array_diff(array_keys(wc_get_order_statuses()), $excluded));
    }

    public static function is_rental_item($item)
    {
        if (!is_object($item) || !method_exists($item, 'get_product')) {
            return false;
        }

        $product = $item->get_product();
        if ($product && method_exists($product, 'get_type') && $product->get_type() === 'redq_rental') {
            return true;
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            if (is_array($item->get_meta($key, true))) {
                return true;
            }
        }

        return false;
    }

    public static function inventory_id_from_item($item)
    {
        foreach (['booking_inventory', '_booking_inventory'] as $key) {
            $value = $item->get_meta($key, true);
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (!is_array($data)) {
                continue;
            }

            $posted = isset($data['posted_data']) && is_array($data['posted_data']) ? $data['posted_data'] : [];
            foreach ([$data, $posted] as $source) {
                foreach (['booking_inventory', 'inventory_id'] as $field) {
                    if (isset($source[$field]) && is_numeric($source[$field]) && (int) $source[$field] > 0) {
                        return (int) $source[$field];
                    }
                }
            }
        }

        $product_id = method_exists($item, 'get_product_id') ? (int) $item->get_product_id() : 0;
        if ($product_id > 0 && function_exists('rnb_get_product_inventory_id')) {
            $ids = rnb_get_product_inventory_id($product_id);
            if (is_array($ids) && !empty($ids)) {
                return (int) reset($ids);
            }
        }

        return 0;
    }

    public static function rental_period_from_item($item)
    {
        $data_sources = [];

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (!is_array($data)) {
                continue;
            }

            $data_sources[] = $data;
            if (isset($data['posted_data']) && is_array($data['posted_data'])) {
                $data_sources[] = $data['posted_data'];
            }
        }

        $start_date = '';
        $start_time = '';
        $return_date = '';
        $return_time = '';

        foreach ($data_sources as $data) {
            if ($start_date === '' && !empty($data['pickup_date'])) {
                $start_date = $data['pickup_date'];
            }
            if ($start_time === '' && !empty($data['pickup_time'])) {
                $start_time = $data['pickup_time'];
            }
            if ($return_date === '') {
                if (!empty($data['dropoff_date'])) {
                    $return_date = $data['dropoff_date'];
                } elseif (!empty($data['return_date'])) {
                    $return_date = $data['return_date'];
                }
            }
            if ($return_time === '') {
                if (!empty($data['dropoff_time'])) {
                    $return_time = $data['dropoff_time'];
                } elseif (!empty($data['return_time'])) {
                    $return_time = $data['return_time'];
                }
            }
        }

        if ($start_date === '') {
            $hidden = self::first_item_meta($item, ['_pickup_hidden_datetime', 'pickup_hidden_datetime']);
            list($start_date, $start_time) = self::split_hidden_datetime($hidden, $start_time);
        }

        if ($return_date === '') {
            $hidden = self::first_item_meta($item, ['_return_hidden_datetime', 'return_hidden_datetime']);
            list($return_date, $return_time) = self::split_hidden_datetime($hidden, $return_time);
        }

        if ($start_date === '') {
            $start_date = self::first_item_meta($item, ['Første leiedag', 'pickup_date']);
        }

        if ($return_date === '') {
            $return_date = self::first_item_meta($item, ['Siste leiedag', 'dropoff_date', 'return_date']);
        }

        return [
            'start_ts'    => self::parse_datetime($start_date, $start_time, '08:00'),
            'return_ts'   => self::parse_datetime($return_date, $return_time, '20:00'),
            'start_date'  => $start_date,
            'start_time'  => $start_time,
            'return_date' => $return_date,
            'return_time' => $return_time,
        ];
    }

    public static function format_timestamp($timestamp, $with_time = true)
    {
        if (!$timestamp) {
            return '';
        }

        return wp_date($with_time ? 'd.m.Y H:i' : 'd.m.Y', (int) $timestamp, wp_timezone());
    }

    private static function first_item_meta($item, $keys)
    {
        foreach ($keys as $key) {
            $value = $item->get_meta($key, true);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private static function split_hidden_datetime($value, $fallback_time)
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return ['', $fallback_time];
        }

        $parts = explode('|', trim((string) $value), 2);
        $date = $parts[0];
        $time = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : $fallback_time;

        return [$date, $time];
    }

    private static function parse_datetime($date, $time, $default_time)
    {
        if (!is_scalar($date) || trim((string) $date) === '') {
            return 0;
        }

        $date = trim((string) $date);

        if (strpos($date, '|') !== false) {
            list($date_part, $pipe_time) = array_pad(explode('|', $date, 2), 2, '');
            $date = trim($date_part);
            if (trim((string) $time) === '' && trim($pipe_time) !== '') {
                $time = trim($pipe_time);
            }
        }

        $time = is_scalar($time) && trim((string) $time) !== '' ? trim((string) $time) : $default_time;
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $value = $date . ' ' . $time;

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i:s',
            'Y/m/d H:i',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            if ($parsed instanceof DateTimeImmutable && $parsed->format($format) === $value) {
                return $parsed->getTimestamp();
            }
        }

        try {
            return (new DateTimeImmutable($value, $timezone))->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    }
}

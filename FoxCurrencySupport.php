<?php

namespace REDQ_RnB\Integration;

use REDQ_RnB\Traits\Rental_Data_Trait;
use WC_Order;

/**
 * FoxCurrencySupport
 *
 * @version 5.0.0
 * @since 1.0.0
 */
class FoxCurrencySupport
{
    use Rental_Data_Trait;
    /**
     * Init class
     */
    public function __construct()
    {
        if (!class_exists('WOOCS')) {
            return;
        }

        add_filter('rnb_price_args', [$this, 'convert_archive_price'], 10, 3);
        add_filter('rnb_show_product_pricing_info', [$this, 'convert_product_pricing_info'], 10, 1);

        add_filter('rnb_payable_pickup_location', [$this, 'convert_location_price'], 10, 3);
        add_filter('rnb_payable_return_location', [$this, 'convert_location_price'], 10, 3);
        add_filter('rnb_payable_resources', [$this, 'convert_resource_price'], 10, 3);
        add_filter('rnb_payable_security_deposite', [$this, 'convert_deposit_price'], 10, 3);
        add_filter('rnb_payable_category', [$this, 'convert_category_price'], 10, 3);
        add_filter('rnb_payable_person', [$this, 'convert_person_price'], 10, 3);
        add_filter('rnb_prepare_payment_due_data', [$this, 'convert_payment_due_data'], 10, 3);
        add_filter('rnb_prepare_instant_pay_amount', [$this, 'convert_instant_pay_amount'], 10, 3);

        add_filter('rnb_instant_pay_total', [$this, 'convert_instant_pay_total'], 10, 2);


        add_filter('rnb_rental_cost_details', [$this, 'convert_cost_details'], 10, 4);
        add_filter('rnb_format_rental_item_data', [$this, 'convert_item_data'], 10, 3);
        add_filter('rnb_price_breakdown', [$this, 'rnb_currency_cart_meta'], 10, 1);
        add_filter('rnb_cart_deposit', [$this, 'cart_deposit'], 10, 1);
        add_filter('rnb_order_deposit', [$this, 'order_deposit'], 10, 1);

        add_filter('rnb_item_price_breakdown', [$this, 'item_deposit'], 10, 2);
        add_action('woocommerce_before_order_item_line_item_html', [$this, 'update_line_item'], 10, 3);
    }

    public function convert_archive_price($price, $product_id, $inventory_id)
    {
        global $WOOCS;

        if (isset($price['price_limit']['min'])) {
            $price['price_limit']['min'] = $WOOCS->woocs_exchange_value($price['price_limit']['min']);
        }

        if (isset($price['price_limit']['max'])) {
            $price['price_limit']['max'] = $WOOCS->woocs_exchange_value($price['price_limit']['max']);
        }

        return $price;
    }

    public function convert_product_pricing_info($pricing)
    {
        global $WOOCS;

        if (isset($pricing['perkilo_price'])) {
            $pricing['perkilo_price'] = $WOOCS->woocs_exchange_value($pricing['perkilo_price']);
        }

        if ($pricing['pricing_type'] === 'general_pricing') {
            $pricing['general_pricing'] = $WOOCS->woocs_exchange_value($pricing['general_pricing']);
        }

        if ($pricing['pricing_type'] === 'daily_pricing') {
            foreach ($pricing['daily_pricing'] as $key => $price) {
                $pricing['daily_pricing'][$key] = $WOOCS->woocs_exchange_value($price);
            }
        }

        if ($pricing['pricing_type'] === 'monthly_pricing') {
            foreach ($pricing['monthly_pricing'] as $key => $price) {
                $pricing['monthly_pricing'][$key] = $WOOCS->woocs_exchange_value($price);
            }
        }

        if ($pricing['pricing_type'] === 'days_range') {
            foreach ($pricing['days_range'] as $key => $range) {
                $range['range_cost'] =  $WOOCS->woocs_exchange_value($range['range_cost']);
                $pricing['days_range'][$key] = $range;
            }
        }

        if ($pricing['hourly_pricing_type'] === 'hourly_general') {
            $pricing['hourly_general'] = $WOOCS->woocs_exchange_value($pricing['hourly_general']);
        }

        if ($pricing['hourly_pricing_type'] === 'hourly_range') {
            foreach ($pricing['hourly_range'] as $key => $range) {
                $range['range_cost'] =  $WOOCS->woocs_exchange_value($range['range_cost']);
                $pricing['hourly_range'][$key] = $range;
            }
        }

        return $pricing;
    }

    public function convert_location_price($locations, $inventory_id, $taxonomy)
    {
        global $WOOCS;

        if (empty($locations)) {
            return $locations;
        }

        $results = [];

        foreach ($locations as $location) {
            $location['cost'] = $WOOCS->woocs_exchange_value($location['cost']);
            $results[] = $location;
        }

        return $results;
    }

    public function convert_resource_price($resources, $inventory_id, $taxonomy)
    {
        global $WOOCS;

        if (empty($resources)) {
            return $resources;
        }

        $results = [];

        foreach ($resources as $resource) {
            $resource['resource_cost'] = $WOOCS->woocs_exchange_value($resource['resource_cost']);
            $resource['resource_hourly_cost'] = $WOOCS->woocs_exchange_value($resource['resource_hourly_cost']);
            $results[] = $resource;
        }

        return $results;
    }

    public function convert_deposit_price($deposits, $inventory_id, $taxonomy)
    {
        global $WOOCS;

        if (empty($deposits)) {
            return $deposits;
        }

        $results = [];

        foreach ($deposits as $deposit) {
            $deposit['security_deposite_cost'] = $WOOCS->woocs_exchange_value($deposit['security_deposite_cost']);
            $deposit['security_deposite_hourly_cost'] = $WOOCS->woocs_exchange_value($deposit['security_deposite_hourly_cost']);
            $results[] = $deposit;
        }

        return $results;
    }

    public function convert_category_price($categories, $inventory_id, $taxonomy)
    {
        global $WOOCS;

        if (empty($categories)) {
            return $categories;
        }

        $results = [];

        foreach ($categories as $category) {
            $category['cost'] = $WOOCS->woocs_exchange_value($category['cost']);
            $category['hourlycost'] = $WOOCS->woocs_exchange_value($category['hourlycost']);
            $results[] = $category;
        }

        return $results;
    }

    public function convert_person_price($people, $inventory_id, $taxonomy)
    {
        global $WOOCS;

        if (empty($people)) {
            return $people;
        }

        $adults = isset($people['adults']) ? $people['adults'] : [];
        if (!empty($adults)) {
            $adults_results = [];
            foreach ($adults as $key => $adult) {
                $adult['person_cost'] = $adult['person_cost'] ? $WOOCS->woocs_exchange_value($adult['person_cost']) : 0;
                $adult['person_hourly_cost'] = $adult['person_cost'] ? $WOOCS->woocs_exchange_value($adult['person_hourly_cost']) : 0;
                $adults_results[] = $adult;
            }
            $people['adults'] = $adults_results;
        }

        $children = isset($people['childs']) ? $people['childs'] : [];
        if (!empty($children)) {
            $children_results = [];
            foreach ($children as $key => $child) {
                $child['person_cost'] = $child['person_cost'] ? $WOOCS->woocs_exchange_value($child['person_cost']) : 0;
                $child['person_hourly_cost'] = $child['person_cost'] ? $WOOCS->woocs_exchange_value($child['person_hourly_cost']) : 0;
                $children_results[] = $child;
            }
            $people['childs'] = $children_results;
        }

        return $people;
    }

    public function convert_payment_due_data($due, $data, $settings)
    {
        global $WOOCS;

        if (empty($due)) {
            return $due;
        }

        if (!(defined('DOING_AJAX') && DOING_AJAX)) {
            return $due;
        }

        if (isset($due['total'])) {
            $due['total'] = $WOOCS->woocs_exchange_value($due['total']);
        }

        if (isset($due['data']) && isset($due['data']['cost'])) {
            $due['data']['cost'] = $WOOCS->woocs_exchange_value($due['data']['cost']);
        }

        return $due;
    }


    public function convert_instant_pay_amount($due, $data, $settings)
    {
        global $WOOCS;

        if (empty($due)) {
            return $due;
        }

        // if (!(defined('DOING_AJAX') && DOING_AJAX)) {
        //     return $due;
        // }

        if (isset($due['instance_payment_type']) && $due['instance_payment_type'] === 'fixed') {
            $due['total'] = $WOOCS->woocs_exchange_value($due['total']);
            if (isset($due['data']) && isset($due['data']['cost'])) {
                $due['data']['cost'] = $WOOCS->woocs_exchange_value($due['data']['cost']);
            }
        }


        return $due;
    }

    public function convert_instant_pay_total($total, $type)
    {
        global $WOOCS;

        if (empty($total)) {
            return $total;
        }

        // if (!(defined('DOING_AJAX') && DOING_AJAX)) {
        //     return $total;
        // }

        if ($type === 'fixed') {
            $total = $WOOCS->woocs_exchange_value($total);
            return $total;
        }

        return $total;
    }


    public function convert_get_location_data($location, $term_id, $taxonomy)
    {
        global $WOOCS;

        if (isset($location[2]) && !empty($location[2])) {
            $location[2] = $WOOCS->woocs_exchange_value($location[2]);
        }

        return $location;
    }

    public function convert_get_resource_data($resource, $term_id, $taxonomy)
    {
        global $WOOCS;

        if (isset($resource[2]) && !empty($resource[1])) {
            $resource[1] = $WOOCS->woocs_exchange_value($resource[1]);
        }

        if (isset($resource[3]) && !empty($resource[3])) {
            $resource[3] = $WOOCS->woocs_exchange_value($resource[3]);
        }

        return $resource;
    }

    public function convert_get_category_data($category, $term_id, $taxonomy)
    {
        global $WOOCS;

        if (isset($category[1]) && !empty($category[1])) {
            $category[1] = $WOOCS->woocs_exchange_value($category[1]);
        }

        if (isset($category[3]) && !empty($category[3])) {
            $category[3] = $WOOCS->woocs_exchange_value($category[3]);
        }

        return $category;
    }

    public function convert_get_person_data($person, $term_id, $taxonomy)
    {
        global $WOOCS;

        if (isset($person[1]) && !empty($person[1])) {
            $person[1] = $WOOCS->woocs_exchange_value($person[1]);
        }

        if (isset($person[3]) && !empty($person[3])) {
            $person[3] = $WOOCS->woocs_exchange_value($person[3]);
        }

        return $person;
    }

    public function convert_get_deposit_data($deposit, $term_id, $taxonomy)
    {
        global $WOOCS;

        if (isset($deposit[1]) && !empty($deposit[1])) {
            $deposit[1] = $WOOCS->woocs_exchange_value($deposit[1]);
        }

        if (isset($deposit[3]) && !empty($deposit[3])) {
            $deposit[3] = $WOOCS->woocs_exchange_value($deposit[3]);
        }

        return $deposit;
    }

    public function convert_cost_details($prices, $productId, $inventoryId, $args)
    {
        global $WOOCS;

        if ($args['add_cart']) {
            return $prices;
        }

        if (isset($prices['price_breakdown']['extras_breakdown'])) {
            $extras_prices = [];
            $extras_breakdown = $prices['price_breakdown']['extras_breakdown'];
            foreach ($extras_breakdown as $key => $price) {
                if (is_array($price)) {
                    foreach ($price as $k => $p) {
                        $extras_prices[$key][$k] = $p ? $WOOCS->woocs_exchange_value($p) : 0;
                    }
                }
                if (!is_array($price)) {
                    $extras_prices[$key] = $WOOCS->woocs_exchange_value($price);
                }
            }
            $prices['price_breakdown']['extras_breakdown'] = $extras_prices;
        }

        if (isset($prices['price_breakdown']['duration_total'])) {
            $prices['price_breakdown']['duration_total'] = $WOOCS->woocs_exchange_value($prices['price_breakdown']['duration_total']);
        }

        if (isset($prices['price_breakdown']['discount_total'])) {
            $prices['price_breakdown']['discount_total'] = $WOOCS->woocs_exchange_value($prices['price_breakdown']['discount_total']);
        }

        if (isset($prices['price_breakdown']['extras_total'])) {
            $prices['price_breakdown']['extras_total'] = $WOOCS->woocs_exchange_value($prices['price_breakdown']['extras_total']);
        }

        if (isset($prices['price_breakdown']['deposit_total'])) {
            $prices['price_breakdown']['deposit_total'] = $WOOCS->woocs_exchange_value($prices['price_breakdown']['deposit_total']);
        }

        if (isset($prices['price_breakdown']['total'])) {
            $prices['price_breakdown']['total'] = $WOOCS->woocs_exchange_value($prices['price_breakdown']['total']);
        }

        if (isset($prices['price_breakdown']['deposit_free_total'])) {
            $prices['price_breakdown']['deposit_free_total'] = $WOOCS->woocs_exchange_value($prices['price_breakdown']['deposit_free_total']);
        }


        return $prices;
    }

    public function convert_item_data($args, $product_id, $data)
    {
        global $WOOCS;

        foreach ($args as $key => $arg) {

            if (empty($arg)) {
                continue;
            }

            if ($key === 'instant_pay_amount') {
                continue;
            }

            $data_args = $arg['data'];


            // if (isset($arg['total'])) {
            //     $price = $WOOCS->woocs_exchange_value($arg['total']);
            //     $args[$key]['total'] = $price;
            // }

            if ($arg['type'] === 'single' && isset($data_args['cost'])) {
                $price = $WOOCS->woocs_exchange_value($data_args['cost']);
                $args[$key]['data']['cost'] = $price;
            }

            if ($arg['type'] === 'multiple') {
                foreach ($data_args as $data_key => $data_value) {
                    if (isset($data_value['cost'])) {
                        $price = $WOOCS->woocs_exchange_value($data_value['cost']);
                        $args[$key]['data'][$data_key]['cost'] = $price;
                    }
                }
            }
        }

        return $args;
    }

    public function cart_deposit($deposit)
    {
        global $WOOCS;

        if (empty($deposit)) {
            return $deposit;
        }

        return $WOOCS->woocs_exchange_value($deposit);
    }

    public function order_deposit($deposit)
    {
        global $WOOCS;

        if (empty($deposit)) {
            return $deposit;
        }

        return $WOOCS->woocs_exchange_value($deposit);
    }

    public function item_deposit($price_breakdown,  $order_id = null)
    {
        global $WOOCS;

        $storage           = new \WOOCS_STORAGE(get_option('woocs_storage', 'transient'));
        $default_currency  = $storage->get_val('woocs_default_currency');
        $currencies        = $WOOCS->get_currencies();

        if (empty($order_id)) {
            $selected_currency = $storage->get_val('woocs_current_currency');
            $selected_currency = $selected_currency ? $selected_currency : $default_currency;
        } else {
            $selected_currency    = get_post_meta($order_id, '_order_currency', true);
        }

        $rate    = isset($currencies[$selected_currency]['rate']) ? $currencies[$selected_currency]['rate'] : '';
        $deposit = $price_breakdown['deposit_total'];

        if (isset($price_breakdown['_currency']) && !empty($price_breakdown['_currency'])) {
            $currency          = $price_breakdown['_currency'];

            // $deposit = $WOOCS->back_convert($deposit, $currency['rate']);
            // if ($selected_currency !== $default_currency) {
            //     $deposit = floatval($deposit) * floatval($rate);
            // }

            $price_breakdown['deposit_total'] = $deposit;
        } else {
            if ($order_id != null) {
                $price_breakdown['deposit_total'] = $WOOCS->woocs_exchange_value($deposit);
            }
        }

        return $price_breakdown;
    }

    /**
     * Add Currency meta while adding item to cart
     */
    public function rnb_currency_cart_meta($prices)
    {
        global $WOOCS;

        $storage          = new \WOOCS_STORAGE(get_option('woocs_storage', 'transient'));
        $currencies       = $WOOCS->get_currencies();
        $current_currency = $WOOCS->current_currency;
        $rate             = $currencies[$current_currency]['rate'];

        $prices['_currency'] = [
            'type' => $current_currency,
            'rate' => $rate
        ];

        return $prices;
    }

    public function update_line_item($item_id, $item, $order)
    {
        $item_data = $item->get_data();
        $product_id = $item_data['product_id'];

        $product_type = wc_get_product($product_id)->get_type();
        if ($product_type !== 'redq_rental') {
            return;
        }

        $quantity = $item_data['quantity'];
        $rental_data = $item->get_meta('rnb_hidden_order_meta');
        $rental_item = $this->format_rental_item_data($product_id, $rental_data, $quantity);

        $meta_data = apply_filters('rnb_prepared_order_item_data', $rental_item, $product_id, $item);
        if (empty($meta_data)) {
            return false;
        }

        foreach ($meta_data as $key => $data) {
            if (empty($data)) {
                continue;
            }

            $value = $data['type'] === 'single' ? $this->format_single_item_value($data['data']) :  $this->format_multiple_item_value($data['data']);
            if (empty($value)) {
                continue;
            }

            $item->update_meta_data(
                $data['key'],
                $value,
            );
        }
    }
}

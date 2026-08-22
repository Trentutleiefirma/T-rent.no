<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;
use WC_Order;
use WP_Term;

trait Legacy_Trait
{
    /**
     * Prepare legacy data from order item
     *
     * @return array
     */
    public function prepare_legacy_item_data()
    {
        $results = [];

        $args = [
            'post_type'      => 'shop_order',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ];

        $orders = get_posts($args);

        if (empty($orders)) {
            return $results;
        }

        foreach ($orders as $order) {

            if ($order->post_status === 'wc-rnb-fake-order') {
                continue;
            }

            $order_id = $order->ID;
            $order    = new WC_Order($order_id);

            $items = $order->get_items();

            if (!count($items)) {
                continue;
            }

            foreach ($items as $item) {

                $item_data = $item->get_data();
                $product_id = $item_data['product_id'];

                if (!is_rental_product($product_id)) {
                    continue;
                }

                $item_details = [];
                $item_id      = $item_data['id'];

                $item_details['product_id'] = $item_data['product_id'];

                $item_meta_data = $item->get_formatted_meta_data('');

                foreach ($item_meta_data as $item_meta) {
                    $item_details[$item_meta->key] = $item_meta->value;
                }

                $results[$order_id][$item_id] = $item_details;
            }
        }

        return $results;
    }

    public function prepare_item($item)
    {
        $results = [];

        foreach ($item as $item_id => $item_data) {

            $product_id = $item_data['product_id'];
            $inventory_id = isset($item_data['booking_inventory']) ? $item_data['booking_inventory'] : '';

            $results[$item_id] = [
                'add-to-cart'       => $product_id,
                'booking_inventory' => $inventory_id
            ];

            $labels = redq_rental_get_settings($product_id, 'labels', ['pickup_location', 'return_location', 'pickup_date', 'return_date', 'resources', 'categories', 'person', 'deposites', 'inventory']);
            $labels = $labels['labels'];

            foreach ($item_data as $key => $value) {

                if (in_array($key, ['_pickup_hidden_datetime', 'pickup_hidden_datetime'])) {
                    $pickup_datetime = explode('|', $value);
                    $results[$item_id]['pickup_date'] = $pickup_datetime[0];
                    $results[$item_id]['pickup_time'] = isset($pickup_datetime[1]) ? $pickup_datetime[1] : '';
                }

                if (in_array($key, ['_return_hidden_datetime', 'return_hidden_datetime'])) {
                    $return_datetime = explode('|', $value);
                    $results[$item_id]['dropoff_date'] = $return_datetime[0];
                    $results[$item_id]['dropoff_time'] = isset($return_datetime[1]) ? $return_datetime[1] : '';
                }

                if ($key === $labels['pickup_location']) {
                    $results[$item_id]['pickup_location'] = $this->convert_location_attribute($value, 'pickup_location');
                }

                if ($key === $labels['return_location']) {
                    $results[$item_id]['dropoff_location'] = $this->convert_location_attribute($value, 'dropoff_location');
                }

                if ($key === $labels['adults']) {
                    $results[$item_id]['adults'] = $this->convert_single_attribute_term($value, 'person');
                }

                if ($key === $labels['childs']) {
                    $results[$item_id]['childs'] = $this->convert_single_attribute_term($value, 'person');
                }

                if ($key === $labels['resource']) {
                    $results[$item_id]['extras'] = $this->convert_multiple_attributes_terms($value, 'resource');
                }

                if ($key === $labels['categories']) {
                    $categories = $this->convert_category_attributes($value, 'rnb_categories');

                    $results[$item_id]['categories'] = $categories['categories'];
                    $results[$item_id]['cat_quantity'] = $categories['cat_quantity'];
                }

                if ($key === $labels['deposite']) {
                    $results[$item_id]['security_deposites'] = $this->convert_multiple_attributes_terms($value, 'deposite');
                }
            }
        }

        return $results;
    }

    public function convert_single_attribute_term($attr, $taxonomy, $post_type = 'inventory')
    {
        $term_id = '';
        $name = wp_trim_words($attr);
        $term = get_term_by('name', $name, $taxonomy, $post_type);

        if (empty($term)) {
            return $term_id;
        }

        if ($term->term_id) {
            return $term->term_id;
        }

        return $term_id;
    }

    public function convert_multiple_attributes_terms($attrs, $taxonomy, $post_type = 'inventory')
    {
        $results = [];

        $split_attrs = explode('<br>', $attrs);

        if (empty($split_attrs)) {
            return $results;
        }

        foreach ($split_attrs as $attr) {

            if (empty(trim($attr))) {
                continue;
            }

            $split_again = explode('(', $attr);
            $first       = wp_trim_words($split_again[0]);
            $term        = get_term_by('name', $first, $taxonomy, $post_type);

            if (empty($term)) {
                continue;
            }

            if ($term->term_id) {
                $results[] = $term->term_id;
            }
        }

        return $results;
    }

    public function convert_location_attribute($attr, $taxonomy = 'pickup_location')
    {
        $locationTerm = [];
        $termsObject = get_terms($taxonomy);

        if (!count($termsObject)) {
            return null;
        }

        foreach ($termsObject as $term) {
            if (in_array($attr, [$term->description, $term->name])) {
                $locationTerm = $term;
            }
        }

        if (empty($locationTerm)) {
            return null;
        }

        return $locationTerm->term_id;
    }

    public function convert_category_attributes($attrs, $taxonomy, $post_type = 'inventory')
    {
        $results = [];

        $split_attrs = explode('<br>', $attrs);

        if (empty($split_attrs)) {
            return $results;
        }

        foreach ($split_attrs as $attr) {

            if (empty(trim($attr))) {
                continue;
            }

            $split_again = explode('(', $attr);
            $first       = wp_trim_words($split_again[0]);
            $first_again = explode('×', $first);

            $name = $first_again[0];
            $qty = $first_again[1];

            $term        = get_term_by('name', $name, $taxonomy, $post_type);
            if (empty($term)) {
                continue;
            }
            if ($term->term_id) {
                $results['categories'][] = $term->term_id;
                $results['cat_quantity'][] = $qty;
            }
        }

        return $results;
    }
}

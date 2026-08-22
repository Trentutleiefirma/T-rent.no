<?php

namespace REDQ_RnB;

use REDQ_RnB\Traits\Form_Trait;
use REDQ_RnB\Traits\Data_Trait;
use REDQ_RnB\Traits\Cost_Trait;

abstract class Booking_Manager
{
    use Form_Trait, Data_Trait, Cost_Trait;

    public function prepare_form_data($post_form, $add_cart = false)
    {
        $product_id = isset($post_form['add-to-cart']) ? $post_form['add-to-cart'] : '';
        $inventory_id = isset($post_form['booking_inventory']) ? $post_form['booking_inventory'] : '';
        $quote_id = isset($post_form['quote_id']) ? $post_form['quote_id'] : '';
        $quantity = isset($post_form['inventory_quantity']) ? $post_form['inventory_quantity'] : 1;

        $conditions = redq_rental_get_settings($product_id, 'conditions');
        $conditions = $conditions['conditions'];

        $available_qty   = $this->has_inventory_by_date($product_id, $post_form);
        $rental_duration = $this->calculate_rental_duration($product_id, $post_form);

        $pickup_location = isset($post_form['pickup_location']) ? $post_form['pickup_location'] : null;
        $return_location = isset($post_form['dropoff_location']) ? $post_form['dropoff_location'] : null;
        $map_distance = isset($post_form['total_distance']) ? $post_form['total_distance'] : null;
        $categories = isset($post_form['categories']) ? $post_form['categories'] : [];
        $categories_qty = isset($post_form['cat_quantity']) ? $post_form['cat_quantity'] : [];
        $resources = isset($post_form['extras']) ? $post_form['extras'] : [];
        $adult =  isset($post_form['additional_adults_info']) ? $post_form['additional_adults_info'] : null;
        $child =  isset($post_form['additional_childs_info']) ? $post_form['additional_childs_info'] : null;
        $deposits = isset($post_form['security_deposites']) ? $post_form['security_deposites'] : [];

        $args = [
            'pickup_location' => get_pickup_location_data($pickup_location, 'pickup_location', $conditions['booking_layout']),
            'return_location' => get_dropoff_location_data($return_location, 'dropoff_location', $conditions['booking_layout']),
            'distance_cost'   => get_map_distance_cost($inventory_id, $map_distance),
            'categories'      => get_category_data($categories, 'rnb_categories'),
            'resources'       => get_resource_data($resources, 'resource'),
            'adult'           => get_person_data($adult, 'person'),
            'child'           => get_person_data($child, 'person'),
            'deposits'        => get_deposit_data($deposits, 'deposite'),
            'add_cart'        => $add_cart
        ];

        $args = apply_filters('rnb_calculate_rental_cost_args', $args, $post_form, $inventory_id, $product_id);
        $price_breakdown = $this->calculate_rental_cost($product_id, $inventory_id, $rental_duration, $args, $quantity);

        //Make ready posted data
        return apply_filters('rnb_prepared_form_data', [
            'date_multiply'              => $this->get_date_unit($rental_duration),
            'booking_inventory'          => $post_form['booking_inventory'],
            'inventory_quantity'         => get_post_meta($inventory_id, 'quantity', true),
            'available_quantity'         => $available_qty,
            'quantity'                   => $quantity,
            'quote_id'                   => $quote_id,
            'pickup_date'                => $post_form['pickup_date'],
            'pickup_time'                => $post_form['pickup_time'],
            'dropoff_date'               => $post_form['dropoff_date'],
            'dropoff_time'               => $post_form['dropoff_time'],
            'pickup_location'            => $this->format_location($args['pickup_location']),
            'return_location'            => $this->format_location($args['return_location']),
            'location_cost'              => $args['distance_cost'],
            'payable_cat'                => $this->format_category($args['categories']),
            'payable_resource'           => $this->format_resource($args['resources']),
            'payable_security_deposites' => $this->format_deposit($args['deposits']),
            'adults_info'                => $this->format_person($args['adult']),
            'childs_info'                => $this->format_person($args['child']),
            'rental_days_and_costs'      => $price_breakdown,
            'item_added_at'              => date("Y-m-d H: i: s"),
        ], $post_form);
    }
}

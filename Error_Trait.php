<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;

/**
 * Error Handle Trait
 */
trait Error_Trait
{
    public function send_ajax_failure_response()
    {
        if (is_ajax()) {
            if (!isset(WC()->session->reload_checkout)) {
                ob_start();
                wc_print_notices();
                $messages = ob_get_clean();
            }

            $response = array(
                'result'   => 'failure',
                'messages' => isset($messages) ? $messages : '',
                'refresh'  => isset(WC()->session->refresh_totals),
                'reload'   => isset(WC()->session->reload_checkout),
            );

            unset(WC()->session->refresh_totals, WC()->session->reload_checkout);
            wp_send_json($response);
        }
    }

    public function handle_form($args = [], $checkout = false)
    {
        $errors = [];

        $product_id = isset($args['product_id']) ? $args['product_id'] : null;
        $inventory_id = isset($args['inventory_id']) ? $args['inventory_id'] : null;

        if (empty($product_id)) {
            $errors[] = esc_html__('Sorry! No product found.', 'redq-rental');
        }

        if (!isset($inventory_id) || empty($inventory_id)) {
            $errors[] = esc_html__('Sorry! No inventory found.', 'redq-rental');
        }

        if (!isset($args['pickup_date']) || empty($args['pickup_date'])) {
            $errors[] = esc_html__('Sorry! pickup date is required', 'redq-rental');
        }

        if (count($errors)) {
            return $errors;
        }

        $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];
        $labels = redq_rental_get_settings($product_id, 'labels', ['notice'])['labels'];

        $return_date = isset($args['return_date']) && !empty($args['return_date'])
            ? $args['return_date']
            : $args['pickup_date'];
        $booking_quantity = isset($args['inventory_quantity']) ? $args['inventory_quantity'] : 1;

        /*
         * T-Rent uses calendar days, not clock hours.
         * Validate the dates only. This deliberately does not compare pickup
         * and return times, so a same-day booking such as 23/08 -> 23/08 is
         * valid. 24/08 -> 25/08 remains two rental days.
         */
        $pickup_date = Carbon::createFromFormat('Y-m-d', $args['pickup_date'])->startOfDay();
        $return_date_obj = Carbon::createFromFormat('Y-m-d', $return_date)->startOfDay();
        $current_date = Carbon::now()->startOfDay();

        // Normalize same-day bookings before date validation and duration checks.
        // handle_form() is also called again during checkout, so this must run here.
        if ($pickup_date->isSameDay($return_date_obj)) {
            $args['pickup_date'] = $pickup_date->toDateString();
            $args['return_date'] = $pickup_date->toDateString();
            $args['dropoff_date'] = $pickup_date->toDateString();
            $args['actual_hours'] = 24;
            $args['days'] = 1;
            $args['flat_hours'] = 24;
            $return_date = $args['return_date'];
            $return_date_obj = $pickup_date->copy();
        }

        if ($pickup_date->lessThan($current_date) || $pickup_date->greaterThan($return_date_obj)) {
            $errors[] = $labels['invalid_range_notice'];
        }

        $holidays = redq_rental_handle_holidays($product_id);
        $is_holiday = $this->check_dates_against_holidays(
            array_merge($args, ['return_date' => $return_date]),
            $holidays,
            $conditions
        );
        if ($is_holiday) {
            $errors[] = esc_html__('Sorry! pickup or return is not possible in holidays', 'redq-rental');
        }

        $duration = $this->calculate_rental_duration($product_id, $args);

        $max_days = $conditions['max_book_days'];
        $min_days = $conditions['min_book_days'];

        if (empty($duration['flat_hours'])) {
            $errors[] = sprintf(esc_html__('Sorry! booking duration can\'t be %s', 'redq-rental'), $duration['flat_hours']);
        }
        if ($max_days && $duration['days'] > $max_days) {
            $errors[] = $labels['max_day_notice'];
        }

        if ($min_days && $duration['days'] < $min_days) {
            $errors[] = $labels['min_day_notice'];
        }

        $max_hours = $conditions['max_book_hours'];
        $min_hours = $conditions['min_book_hours'];

        if ($max_hours && $duration['actual_hours'] > $max_hours) {
            $errors[] = sprintf(esc_html__('Sorry, the booking duration cannot exceed %s hours', 'redq-rental'), $max_hours);
        }

        if ($min_hours && $duration['actual_hours'] < $min_hours) {
            $errors[] = sprintf(esc_html__('Sorry, the booking duration must be at least %s hours', 'redq-rental'), $min_hours);
        }

        if ($booking_quantity < 1) {
            $errors[] = $labels['quantity_notice'];
        }

        $available_qty = $this->has_inventory_by_date($product_id, $args);

        if ($checkout && $available_qty < $booking_quantity) {
            $errors[] = $labels['quantity_notice'];
        }

        if (!$checkout) {
            $cart_qty = $this->check_product_quantity_in_cart($product_id, $args);
            if (($booking_quantity + $cart_qty) > $available_qty) {
                $errors[] = $labels['quantity_notice'];
            }
        }

        $categories = isset($args['categories']) ? $args['categories'] : null;
        $has_category_errors = $this->category_validation($categories);
        if (!empty($has_category_errors)) {
            foreach ($has_category_errors as $cat_error) {
                $errors[] = $cat_error;
            }
        }

        if (isset($args['order_type']) && $args['order_type'] !== 'extend_order') {
            $form_deposits = isset($args['security_deposites']) ? $args['security_deposites'] : null;
            $has_deposit_errors = $this->deposit_validation($inventory_id, $form_deposits);
            if (!empty($has_deposit_errors)) {
                $errors[] = $has_deposit_errors;
            }
        }

        return $errors;
    }

    /**
     * Normalize the rental data stored in the cart before RnB validates checkout.
     *
     * Quote checkout and ordinary add-to-cart do not always store identical key
     * names. Product-page validation can therefore pass while checkout later
     * receives a missing or stale return_date and reports an invalid range.
     */
    private function normalize_checkout_rental_data($cart_item)
    {
        $product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
        $rental_data = isset($cart_item['rental_data']) && is_array($cart_item['rental_data'])
            ? $cart_item['rental_data']
            : [];

        $posted_data = isset($rental_data['posted_data']) && is_array($rental_data['posted_data'])
            ? $rental_data['posted_data']
            : $rental_data;

        if (!$product_id || empty($posted_data)) {
            return $posted_data;
        }

        // rearrange_form_data() requires the original form key names.
        $posted_data['add-to-cart'] = !empty($posted_data['add-to-cart'])
            ? $posted_data['add-to-cart']
            : $product_id;

        if (empty($posted_data['booking_inventory'])) {
            if (!empty($posted_data['inventory_id'])) {
                $posted_data['booking_inventory'] = $posted_data['inventory_id'];
            } elseif (!empty($rental_data['booking_inventory'])) {
                $posted_data['booking_inventory'] = $rental_data['booking_inventory'];
            }
        }

        if (empty($posted_data['dropoff_date']) && !empty($posted_data['return_date'])) {
            $posted_data['dropoff_date'] = $posted_data['return_date'];
        }
        if (empty($posted_data['return_date']) && !empty($posted_data['dropoff_date'])) {
            $posted_data['return_date'] = $posted_data['dropoff_date'];
        }
        if (empty($posted_data['return_date']) && !empty($posted_data['pickup_date'])) {
            $posted_data['return_date'] = $posted_data['pickup_date'];
            $posted_data['dropoff_date'] = $posted_data['pickup_date'];
        }

        if (empty($posted_data['dropoff_time']) && !empty($posted_data['return_time'])) {
            $posted_data['dropoff_time'] = $posted_data['return_time'];
        }
        if (empty($posted_data['return_time']) && !empty($posted_data['dropoff_time'])) {
            $posted_data['return_time'] = $posted_data['dropoff_time'];
        }

        $normalized = $this->rearrange_form_data($posted_data);
        if (is_array($normalized)) {
            $posted_data = $normalized;
        }

        // Same calendar date is always one paid rental day. Keep the real clock
        // times for availability/overlap checks, but never let checkout turn the
        // date range or duration back into zero.
        if (!empty($posted_data['pickup_date'])) {
            $return_date = !empty($posted_data['return_date'])
                ? $posted_data['return_date']
                : $posted_data['pickup_date'];

            $pickup = Carbon::parse($posted_data['pickup_date'])->startOfDay();
            $return = Carbon::parse($return_date)->startOfDay();

            if ($pickup->isSameDay($return)) {
                $same_day = $pickup->toDateString();
                $posted_data['pickup_date'] = $same_day;
                $posted_data['return_date'] = $same_day;
                $posted_data['dropoff_date'] = $same_day;
                $posted_data['actual_hours'] = 24;
                $posted_data['days'] = 1;
                $posted_data['flat_hours'] = 24;
            }
        }

        return $posted_data;
    }

    public function handle_checkout_items($cart_items)
    {
        $results = [];
        $errors = [];

        foreach ($cart_items as $cart_item) {
            $product_id = isset($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
            $product = $product_id ? wc_get_product($product_id) : false;
            $product_type = $product ? $product->get_type() : '';

            if ($product_type !== 'redq_rental') {
                continue;
            }

            $posted_data = $this->normalize_checkout_rental_data($cart_item);
            $errors[$product_id] = $this->handle_form($posted_data, true);
        }

        foreach ($errors as $key => $error) {
            if (!empty($error)) {
                $results[] = __('Error For ', 'redq-rental') . '<strong>"' . get_the_title($key) . '"<strong>  ';
                $results[] = implode(',', $error);
            }
        }

        return $results;
    }

    public function handle_uid_errors()
    {
        $errors = [];
        $uid = get_option('rnb_uid');
        $uid_message = base64_decode(get_option('rnb_uid_error_message'));

        if (empty($uid)) {
            $errors[] = $uid_message;
        }

        $info = get_option(base64_encode($uid));
        if (empty($info)) {
            $errors[] = $uid_message;
        }

        if (isset($info['active']) && empty($info['active'])) {
            $errors[] = $uid_message;
        }

        if (count($errors)) {
            $errors = array_unique($errors);
        }

        return apply_filters('rnb_uid_errors', $errors);
    }

    public function category_validation($categories)
    {
        $results = [];

        if (empty($categories) || !is_array($categories)) {
            return $results;
        }

        foreach ($categories as $category) {
            $split = explode('|', $category);
            $term_id = isset($split[0]) ? $split[0] : 0;
            $qty = isset($split[1]) ? $split[1] : 0;
            $term_qty = get_term_meta($term_id, 'inventory_rnb_cat_qty', true);
            if ($qty > $term_qty) {
                $results[] = esc_html__('Sorry! max category quantity exceed', 'redq-rental');
            }
        }

        return $results;
    }

    public function deposit_validation($inventory_id, $form_deposits)
    {
        $results = [];
        $required_sd = [];
        $deposits = get_the_terms($inventory_id, 'deposite');

        if (empty($deposits) || !is_array($deposits)) {
            return $results;
        }

        foreach ($deposits as $deposit) {
            $is_clickable = get_term_meta($deposit->term_id, 'inventory_sd_price_clickable_term_meta', true);
            if ($is_clickable === 'no') {
                $required_sd[] = $deposit->term_id;
            }
        }

        if (empty($required_sd)) {
            return $results;
        }

        if (empty($form_deposits)) {
            return esc_html__('Sorry! deposit is required', 'redq-rental');
        }

        $has_required = array_diff($required_sd, array_intersect($required_sd, $form_deposits));
        if (count($has_required)) {
            return esc_html__('Sorry! non-clickable deposit is required', 'redq-rental');
        }

        return $results;
    }

    public function check_cart_has_rental_items($cart_items)
    {
        foreach ($cart_items as $item) {
            $product_id = $item['product_id'];
            $product_type = wc_get_product($product_id)->get_type();
            if ($product_type === 'redq_rental') {
                return true;
            }
        }

        return false;
    }

    public function check_dates_against_holidays($booking_details, $holidays, $conditions)
    {
        $holiday_format = $conditions['date_format'];
        $pickup_date = Carbon::createFromFormat('Y-m-d', $booking_details['pickup_date']);
        $return_date = Carbon::createFromFormat('Y-m-d', $booking_details['return_date']);

        $holidays_formatted = array_map(function ($date) use ($holiday_format) {
            return Carbon::createFromFormat($holiday_format, $date);
        }, $holidays);

        foreach ($holidays_formatted as $holiday) {
            if ($pickup_date->isSameDay($holiday) || $return_date->isSameDay($holiday)) {
                return true;
            }
        }

        return false;
    }

    public function prepare_validate_fields($product_id)
    {
        $messages = rnb_get_translated_strings();
        $validations = redq_rental_get_settings($product_id, 'validations')['validations'];
        $fields = [];

        if ($validations['pickup_location'] === 'open') {
            $fields[] = [
                'selector' => "select[name='pickup_location']",
                'message' => $messages['pickup_loc_required'],
                'titleTag' => 'h5',
            ];
        }

        if ($validations['return_location'] === 'open') {
            $fields[] = [
                'selector' => "select[name='dropoff_location']",
                'message' => $messages['dropoff_loc_required'],
                'titleTag' => 'h5',
            ];
        }

        if ($validations['person'] === 'open') {
            $fields[] = [
                'selector' => "select[name='additional_adults_info']",
                'message' => $messages['adult_required'],
                'titleTag' => 'h5',
            ];
        }

        if ($validations['pickup_time'] === 'open') {
            $fields[] = [
                'selector' => "input[name='pickup_time']",
                'message' => $messages['pickup_time_required'],
                'titleTag' => 'h5',
            ];
        }

        if ($validations['return_time'] === 'open') {
            $fields[] = [
                'selector' => "input[name='dropoff_time']",
                'message' => $messages['dropoff_time_required'],
                'titleTag' => 'h5',
            ];
        }

        if (isset($validations['resource']) && $validations['resource'] === 'open') {
            $fields[] = [
                'selector' => "input[name='extras[]']",
                'message' => $messages['resource_required'],
                'checkboxGroup' => true,
                'titleTag' => 'h5',
            ];
        }

        if (isset($validations['category']) && $validations['category'] === 'open') {
            $fields[] = [
                'selector' => "input[name='categories[]']",
                'message' => $messages['resource_required'],
                'checkboxGroup' => true,
                'titleTag' => 'h5',
            ];
        }

        if (isset($validations['deposit']) && $validations['deposit'] === 'open') {
            $fields[] = [
                'selector' => "input[name='security_deposites[]']",
                'message' => $messages['deposit_required'],
                'checkboxGroup' => true,
                'titleTag' => 'h5',
            ];
        }

        return apply_filters('rnb_validate_fields', $fields, $validations, $messages, $product_id);
    }
}
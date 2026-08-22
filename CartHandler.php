<?php

namespace REDQ_RnB;

use Automattic\WooCommerce\Admin\Overrides\Order;
use REDQ_RnB\Booking_Manager;
use REDQ_RnB\Traits\Form_Trait;
use REDQ_RnB\Traits\Data_Trait;
use REDQ_RnB\Traits\Cost_Trait;
use REDQ_RnB\Traits\Error_Trait;
use REDQ_RnB\Traits\Rental_Data_Trait;
use WC_Order;

/**
 * Handle cart page
 *
 * @version 5.0.0
 * @since 1.0.0
 */
class CartHandler extends Booking_Manager
{
    use Form_Trait, Data_Trait, Cost_Trait, Error_Trait, Rental_Data_Trait;

    public function __construct()
    {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'rnb_add_to_cart_validation'], 10, 1);
        add_filter('woocommerce_add_cart_item_data', [$this, 'rnb_add_cart_item_data'], 20, 2);
        add_filter('woocommerce_add_cart_item', [$this, 'rnb_add_cart_item'], 20, 1);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'rnb_get_cart_item_from_session'], 20, 2);
        add_filter('woocommerce_get_item_data', [$this, 'rnb_get_item_data'], 20, 2);
        add_filter('woocommerce_cart_item_quantity', [$this, 'rnb_cart_item_quantity'], 20, 2);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_deposit_total_as_fee'], 20, 1);
        add_action('woocommerce_checkout_process', [$this, 'rnb_validate_checkout_process'], 20, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'create_order_line_item'], 10, 4);
        add_action('woocommerce_new_order_item', [$this, 'rnb_order_item_meta'], 20, 3);
        add_action('woocommerce_thankyou', [$this, 'rnb_order_thankyou'], 10, 1);
        add_action('rnb_thankyou', [$this, 'rnb_thankyou'], 20, 1);
        add_action('woocommerce_order_status_failed', [$this, 'rnb_handle_failed_order'], 10, 2);
        add_action('wp_head', [$this, 'rnb_handle_after_order_event']);
    }

    /**
     * Server validation before add to cart
     *
     * @param boolean $valid
     * @return boolean
     */
    public function rnb_add_to_cart_validation($valid)
    {
        if (isset($_POST['order_type']) && $_POST['order_type'] === 'extend_order') {
            return true;
        }

        $product_id = isset($_POST['add-to-cart']) ? $_POST['add-to-cart'] : '';

        if (!is_rental_product($product_id)) {
            return $valid;
        }

        $inventory_id = isset($_POST['booking_inventory']) ? $_POST['booking_inventory'] : '';

        if (empty($product_id) || empty($inventory_id)) {
            wc_add_notice(sprintf(__('Sorry! product or inventory is not found', 'redq-rental')), 'error');
            return false;
        }

        $_POST = $this->rearrange_form_data($_POST);
        $has_errors = $this->handle_form($_POST);

        if ($has_errors && !empty($has_errors)) {
            wc_add_notice(sprintf(__('%s', 'redq-rental'), implode(',', $has_errors)), 'error');
            return false;
        }

        return $valid;
    }

    /**
     * Insert posted data into cart item meta
     *
     * @param $cart_item_meta
     * @param string $product_id , array $cart_item_meta
     * @return array
     */
    public function rnb_add_cart_item_data($cart_item_meta, $product_id)
    {
        $product_type = wc_get_product($product_id)->get_type();
        $order_type = isset($_POST['order_type']) ? $_POST['order_type'] : 'new_order';

        if ($product_type !== 'redq_rental' || $order_type !== 'new_order') {
            return $cart_item_meta;
        }

        if (isset($cart_item_meta['rental_data']['quote_id'])) {
            return $cart_item_meta;
        }

        $_POST = $this->rearrange_form_data($_POST);
        $posted_data = $this->prepare_form_data($_POST, true);

        $posted_data['posted_data'] = $_POST;
        $cart_item_meta['rental_data'] = $posted_data;

        return apply_filters('rnb_cart_item_meta', $cart_item_meta);
    }

    /**
     * Add cart item meta
     *
     * @param array $cart_item
     * @return array
     */
    public function rnb_add_cart_item($cart_item)
    {
        $product_id   = $cart_item['data']->get_id();
        $product_type = wc_get_product($product_id)->get_type();

        if (isset($cart_item['rental_data']['quote_id']) && !empty($cart_item['rental_data']['quote_id']) && $product_type === 'redq_rental') {
            $cart_item['data']->set_price($cart_item['rental_data']['rental_days_and_costs']['cost']);
        } else {
            if (isset($cart_item['rental_data']['rental_days_and_costs']['cost']) && $product_type === 'redq_rental') {
                $cart_item['data']->set_price($cart_item['rental_data']['rental_days_and_costs']['cost']);
            }

            // revert
            if (isset($cart_item['quantity']) && $product_type === 'redq_rental') {
                $cart_item['quantity'] = isset($cart_item['rental_data']['quantity']) ? $cart_item['rental_data']['quantity'] : 1;
            }
        }

        // die();

        return $cart_item;
    }

    /**
     * Get item data from session
     *
     * @param array $cart_item
     * @param $values
     * @return array
     */
    public function rnb_get_cart_item_from_session($cart_item, $values)
    {
        if (!empty($values['rental_data'])) {
            $cart_item = $this->rnb_add_cart_item($cart_item);
        }
        return $cart_item;
    }

    /**
     * Show cart item data in cart and checkout page
     *
     * array $item_data
     * array $cart_item
     * @return array
     */
    public function rnb_get_item_data($item_data, $cart_item)
    {
        $product_id = $cart_item['data']->get_id();
        $product_type = wc_get_product($product_id)->get_type();

        if ($product_type !== 'redq_rental' || empty($cart_item['rental_data'])) {
            return $item_data;
        }

        $quantity = intval($cart_item['quantity']);
        $item = $this->format_rental_item_data($product_id, $cart_item['rental_data'], $quantity);

        $meta_data = apply_filters('rnb_prepared_cart_item_data', $item, $product_id, $cart_item);
        if (empty($meta_data)) {
            return $item_data;
        }

        $ignored_keys = apply_filters('rnb_ignored_item_meta_keys', ['pay_during_booking'], $meta_data);

        foreach ($meta_data as $key => $data) {
            if (empty($data)) {
                continue;
            }

            if (in_array($key, $ignored_keys)) {
                continue;
            }

            $value = $data['type'] === 'single' ? $this->format_single_item_value($data['data']) :  $this->format_multiple_item_value($data['data']);
            if (empty($value)) {
                continue;
            }

            $item_data[] = [
                'key'    => $data['key'],
                'value'   => $value
            ];
        }
        return $item_data;
    }

    /**
     * Set quantity always 1
     *
     * @param $product_quantity
     * @param array $cart_item_key , int $product_quantity
     * @return int
     */
    public function rnb_cart_item_quantity($quantity, $cart_item_key)
    {
        global $woocommerce;
        $cart_details = $woocommerce->cart->cart_contents;

        foreach ($cart_details as $key => $detail) {
            if ($key !== $cart_item_key) {
                continue;
            }

            $product_id = $detail['product_id'];
            $product_type = wc_get_product($product_id)->get_type();
            if ($product_type === 'redq_rental') {
                return $detail['quantity'] ? $detail['quantity'] : 1;
            }

            return $quantity;
        }

        return $quantity;
    }

    public function add_deposit_total_as_fee($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $deposit_total = 0;
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['rental_data']['rental_days_and_costs']['price_breakdown']['deposit_total'])) {
                $deposit = $cart_item['rental_data']['rental_days_and_costs']['price_breakdown']['deposit_total'];
                if(isset($cart_item['rental_data']['quantity'])){
                    $deposit = $deposit * $cart_item['rental_data']['quantity'];
                }
                $deposit_total += $deposit;
            }
        }

        if ($deposit_total > 0) {
            $cart->add_fee(__('Deposit', 'redq-rental'), $deposit_total, false);
        }
    }

    /**
     * Checking Processed Data
     *
     * @return void
     */
    public function rnb_validate_checkout_process()
    {
        $cart_items = WC()->cart->get_cart();

        if (empty($cart_items)) {
            $this->send_ajax_failure_response();
        }

        $has_rental_item = $this->check_cart_has_rental_items($cart_items);
        if (!$has_rental_item) {
            return;
        }

        $uid_errors = $this->handle_uid_errors();
        if (count($uid_errors)) {
            wc_add_notice(sprintf(__('%s', 'redq-rental'), implode(', ', $uid_errors)), 'error');
            $this->send_ajax_failure_response();
        }

        $has_errors = $this->handle_checkout_items($cart_items);
        if (count($has_errors)) {
            wc_add_notice(sprintf(__('%s', 'redq-rental'), implode(', ', $has_errors)), 'error');
            $this->send_ajax_failure_response();
        }
    }


    /**
     * create_order_line_item function
     *
     * @param object $item
     * @param string $cart_item_key
     * @param array $values
     * @param object $order
     * @return void | boolean
     */
    public function create_order_line_item($item, $cart_item_key, $values, $order)
    {
        $product_id = $values['data']->get_id();
        $product_type = wc_get_product($product_id)->get_type();

        if ($product_type !== 'redq_rental' || !isset($values['rental_data'])) {
            return;
        }

        $quantity = intval($values['quantity']);
        $rental_item = $this->format_rental_item_data($product_id, $values['rental_data'], $quantity);

        $meta_data = apply_filters('rnb_prepared_order_item_data', $rental_item, $product_id, $values);
        if (empty($meta_data)) {
            return false;
        }

        $ignored_keys = apply_filters('rnb_ignored_item_meta_keys', ['pay_during_booking'], $meta_data);

        foreach ($meta_data as $key => $data) {
            if (empty($data)) {
                continue;
            }

            if (in_array($key, $ignored_keys)) {
                continue;
            }

            $value = $data['type'] === 'single' ? $this->format_single_item_value($data['data']) :  $this->format_multiple_item_value($data['data']);
            if (empty($value)) {
                continue;
            }

            $item->add_meta_data(
                $data['key'],
                $value,
                true
            );
        }

        $hidden_keys = $this->format_hidden_rental_item_data($product_id, $values['rental_data']);
        foreach ($hidden_keys as $key => $data) {
            $item->add_meta_data(
                $data['key'],
                $data['value'],
                true
            );
        }
    }

    /**
     * rnb_order_item_meta function
     *
     * @param int $item_id
     * @param object $item
     * @param int $order_id
     * @return boolean | void
     */
    public function rnb_order_item_meta($item_id, $item, $order_id)
    {
        $item_data = $item->get_data();
        if (!isset($item_data['product_id']) || empty($item_data['product_id'])) {
            return;
        }

        $product_id = $item_data['product_id'];
        $product_type = wc_get_product($product_id)->get_type();
        if ($product_type !== 'redq_rental' || !isset($item->legacy_values['rental_data'])) {
            return;
        }

        $rental_data = $item->legacy_values['rental_data'];
        if (empty($rental_data)) {
            return;
        }

        $conditionals = redq_rental_get_settings($product_id, 'conditions')['conditions'];
        $inventory_id = $rental_data['booking_inventory'];
        $quantity = isset($rental_data['quantity']) ? $rental_data['quantity'] : 1;
        $time_interval = !empty($conditionals['time_interval']) ? (int) $conditionals['time_interval'] : 30;

        if (isset($rental_data['quote_id'])) {
            update_post_meta($rental_data['quote_id'], '_rnb_rfq_order_id', $order_id);
            update_post_meta($rental_data['quote_id'], '_rnb_rfq_item_id', $item_id);
        }

        // Start inventory post meta update from here
        $booked_dates_ara = isset($rental_data['rental_days_and_costs']['booked_dates']['saved']) ? $rental_data['rental_days_and_costs']['booked_dates']['saved'] : array();

        $pickup_datetime = '';
        $return_datetime = '';

        if (isset($rental_data['pickup_date']) && !empty($rental_data['pickup_date'])) {
            $date = date_create($rental_data['pickup_date']);
            $pickup_datetime = date_format($date, "Y-m-d");
        }

        if (isset($rental_data['pickup_time']) && !empty($rental_data['pickup_time'])) {
            $pickup_datetime .= ' ' . $rental_data['pickup_time'];
        } else {
            $pickup_datetime .= ' ' . rnb_time_subtraction(0); // ' 00:00';
        }

        if (isset($rental_data['dropoff_date']) && !empty($rental_data['dropoff_date'])) {
            $date = date_create($rental_data['dropoff_date']);
            $return_datetime = date_format($date, "Y-m-d");
        }

        if (isset($rental_data['dropoff_time']) && !empty($rental_data['dropoff_time'])) {
            $return_datetime .= ' ' . $rental_data['dropoff_time'];
        } else {
            $return_datetime .= ' ' . rnb_time_subtraction($time_interval);
        }

        $booked_dates_ara = array(
            'pickup_datetime' => $pickup_datetime,
            'return_datetime' => $return_datetime,
            'inventory_id'    => $inventory_id,
            'product_id'      => $product_id,
            'quantity'        => get_post_meta($inventory_id, 'quantity', true),
        );

        $order = wc_get_order($order_id);
        if (!in_array($order->get_status(), ['cancelled', 'failed'])) {
            rnb_process_rental_order_data($product_id, $order_id, $item_id, $inventory_id, $booked_dates_ara, $quantity);
        }
    }
public function rnb_order_thankyou($order_id){
    global $wpdb;
    $tablename = $wpdb->prefix . 'rnb_availability';
    $wpdb->update(
        $tablename,
        array(
            'updated_at' => current_time('mysql'),
            'delete_status' => 0,
        ),
        array(
            'order_id' => $order_id,
        )
    );
    
}
    /**
     * Thank you
     */
    public function rnb_thankyou($order_id)
    {
        $order = new WC_Order($order_id);
        $items = $order->get_items();
       
    
        foreach ($items as $item) {
            foreach ($item['item_meta'] as $key => $value) {
                if ($key === 'Quote Request') {
                    wp_update_post(array(
                        'ID'          => $value[0],
                        'post_status' => 'quote-completed'
                    ));
                }
            }
        }
    }

    /**
     * Handle Failed order
     *
     * @param int $order_id
     * @param object $order
     * @return void
     */
    public function rnb_handle_failed_order($order_id, $order)
    {
        if (!empty($order) && !in_array($order->get_status(), ['cancelled', 'failed'])) {
            return;
        }

        $items = $order->get_items();

        foreach ($items as $key => $item) {
            $item_data = $item->get_data();
            $args = array(
                'order_id'   => $order_id,
                'item_id'    => $item_data['id'],
                'product_id' => $item_data['product_id'],
            );

            rnb_booking_dates_update($args);
        }
    }

    /**
     * Handle after order event
     *
     * @return void
     */
    public function rnb_handle_after_order_event()
    {
        global $wp;

        if (!is_wc_endpoint_url('order-received')) {
            return;
        }

        $order_id = absint($wp->query_vars['order-received']);
        $order    = wc_get_order($order_id);

        if (empty($order)) {
            return;
        }

        if (!in_array($order->get_status(), ['cancelled', 'failed'])) {
            return;
        }

        $items = $order->get_items();

        foreach ($items as $key => $item) {
            $item_data = $item->get_data();

            $args = array(
                'order_id'   => $order_id,
                'item_id'    => $item_data['id'],
                'product_id' => $item_data['product_id'],
            );

            rnb_booking_dates_update($args);
        }
    }
}

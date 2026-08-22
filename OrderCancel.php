<?php

namespace REDQ_RnB;


/**
 * Order Cancel 
 */
class OrderCancel
{

    public function __construct()
    {
        add_filter( 'woocommerce_my_account_my_orders_actions', [$this, 'add_custom_button_to_orders_list'], 10, 2 );
        add_action( 'init', [$this, 'rnb_custom_wc_register_post_statuses'] );
        add_filter( 'wc_order_statuses', [$this, 'rnb_custom_wc_add_order_statuses'] );
        add_action('wp_ajax_rnb_update_order_statue', [$this, 'rnb_update_order_statue']);
        add_action('wp_ajax_nopriv_rnb_update_order_statue', [$this, 'rnb_update_order_statue']);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'remove_my_account_orders_cancel_button'], 10, 2);
    }
    /**
     * add a custom button for cancel order 
     */
    public  function add_custom_button_to_orders_list( $actions, $order ) {
        /**
         * If not enable cancel order  return
         */
        $is_enable = get_option('rnb_enable_cancel_order_button', 'no');
        if($is_enable == 'no'){
            return $actions;
        }
         /**
         * If empty order id return 
         */
        $order_id = $order->get_id();
        if($order_id == ''){
            return $actions;
        }
        $product_id = [];
        $product_type = '';
        $order = wc_get_order( $order_id );

        // Get order items
        $order_items = $order->get_items();
        $order_status = $order->get_status();
        foreach ( $order_items as $item_id => $item ) {
            // Get the product ID for the item
             $product_id = $item->get_product_id();
             $product = $item->get_product();
            if( $product->is_type('redq_rental') ){
                $product_type = 'redq_rental';
            } 
             $product_ids[] = $product_id;
        }
        $enable_order_type = get_option('rnb_when_cancel_order_button_show', 'pending, on-hold, processing'); 
        $enable_order_type_list = [];
        if('' != $enable_order_type){
            $enable_order_type_list = explode(', ', $enable_order_type);
        }
        /**
         * if current order status not in the list return 
         */
        if(!in_array($order_status, $enable_order_type_list)){
            return $actions;
        }
        /**
         * if product type not redq_retal return  
         */
        if($product_type == 'redq_rental'){
            global $wp;
            $url = add_query_arg( [
                $wp->query_vars,
                'order_id' =>  $order_id,
                'product_id' => join(',', $product_ids)
            ], home_url( $wp->request ) );

            $actions['rnb_cancel_order'] = array(
                'url'    => esc_url($url), 
                'name'   => esc_html__( 'Cancel Order', 'redq-rental' ),
                'action' => "rnb_cancel_order",
            );
        }
        return $actions;
    }
    /**
     * Register a custom order status
     * hooked with init function 
     */
    function rnb_custom_wc_register_post_statuses() {
        register_post_status( 'wc-cancel-request', array(
            'label'                     => _x( 'Cancel Request', 'WooCommerce Order status', 'redq-rental' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Approved (%s)', 'Approved (%s)', 'redq-rental' )
        ) );
    }
    /**
     * add custom order status 
     * @return string
     */
    function rnb_custom_wc_add_order_statuses( $order_statuses ) {
        $order_statuses['wc-cancel-request'] = _x( 'Cancel Request', 'WooCommerce Order status', 'redq-rental' );
        return $order_statuses;
    }
    /**
     * Update order status with ajax 
     */
    public function rnb_update_order_statue() {
        check_ajax_referer( 'rnb-cancel-ajax-nonce', 'nonce' );
        $cancel_customer_note = sanitize_textarea_field($_REQUEST['cancelReason']);
        $cancel_action = isset($_REQUEST['cancelReasonRequired']) ? sanitize_textarea_field($_REQUEST['cancelReasonRequired']): '';
        if($cancel_customer_note == '' && $cancel_action == 'yes'){
            wp_send_json_success([
                'html' => esc_html__('Cancel reason is required', 'redq-rental'),
                'class' => 'danger'
            ]);
        }
        $cancel_reason = esc_html__('Cancel Order Reason : ', 'redq-rental');
        $cancel_reason .= sanitize_textarea_field($_REQUEST['cancelReason']);
        $url = sanitize_text_field($_REQUEST['orderData']);
        $html = '';
        $html_class = '';
        // Parse the URL
        $url_components = parse_url($url);
        // Parse the query string
        parse_str($url_components['query'], $query);
        $product_all = explode(',', $query['product_id']);
        $product_id = '';
        if(count($product_all) == 1){
            $product_id = $product_all[0];
        }
        // Get the order ID
        $order_id = $query['order_id'];
        // Get an instance of WC_Order object by order ID
        $order = wc_get_order( $order_id );
        $pickup_time = [];
        foreach ( $order->get_items() as $item_id => $item ) {
            // Get item meta
            $item_meta = $item->get_meta_data();
        
            // Output item meta
            foreach ( $item_meta as $meta ) {
                $pickup_time[] = $item->get_meta('_pickup_hidden_datetime');
            }
        }
        $order_time  = str_replace('|', ' ', $pickup_time[0]);
        $timezone_string = get_option('timezone_string');

        // Set the timezone
        if (!$timezone_string) {
            $timezone_string = 'UTC'; // Default to UTC if no timezone is set
        }
        $order_timezone = new \DateTimeZone($timezone_string);

        // Create DateTime object for the order time
        $order_time = new \DateTime($order_time, $order_timezone);
        $order_time_string = $order_time->getTimestamp();

        // Get options for cancel request type
        $cancel_request_type_by = get_option('rnb_cancel_order_before_by', 'day');
        $before_hour = get_option('rnb_cancel_order_before_hour', '');
        $before_day = get_option('rnb_cancel_order_before_day', '');

        // Get current time in the same timezone
        $current_time = new \DateTime('now', $order_timezone);
        $current_timestamp = $current_time->getTimestamp();

        // Check if the current time is less than the cancel time
        $time_difference = $order_time_string - $current_timestamp;

        $days = floor($time_difference / (60 * 60 * 24));
        $hours = floor(($time_difference % (60 * 60 * 24)) / (60 * 60));

        if($cancel_request_type_by == 'day'){
            if($days > 0 && $days >= $before_day){
                $cancel_request_type = get_option('rnb_cancel_order_request_type', 'cancel-request');
                $order->update_status( $cancel_request_type ); 
                $order->add_order_note( $cancel_reason );
                $html = esc_html__('Order Cancel Request Received', 'redq-rental');
                $html_class = 'success';
            }else{
                //$html= sprintf('You can cancel order before %s days', esc_html($before_day));
                $html = sprintf( _n( 'You can cancel order before %s day', 'You can cancel order before %s days', $before_day, 'redq-rental' ), number_format_i18n( $before_day ) );
                $html_class = 'danger';
            }
        }
        if($cancel_request_type_by == 'hour'){
            if($hours > 0 && $hours >= $before_hour){
                $html = esc_html__('Order Cancel Request Received', 'redq-rental');
                $cancel_request_type = get_option('rnb_cancel_order_request_type', 'cancel-request');
                $order->update_status( $cancel_request_type ); 
                $order->add_order_note( $cancel_reason );
                $html_class = 'success';
            }else{
                // $html = sprintf('You can cancel order before %s hours', esc_html($before_hour));
                $html = sprintf( _n( 'You can cancel order before %s hour', 'You can cancel order before %s hours', $before_hour, 'redq-rental' ), number_format_i18n( $before_hour ) );
                $html_class = 'danger';
            }
        }
        wp_send_json_success([
            'html' => $html,
            'class' => $html_class
        ]);
        wp_die();
    }
    /**
     * remove default cancel button 
     */
    function remove_my_account_orders_cancel_button( $actions, $order ){
        $order_id = $order->get_id();
        if($order_id == ''){
            return $actions;
        }
        $product_type = '';
        $order = wc_get_order( $order_id );
        // Get order items
        $order_items = $order->get_items();
        foreach ( $order_items as $item_id => $item ) {
            // Get the product ID for the item
                $product = $item->get_product();
            if( $product->is_type('redq_rental') ){
                $product_type = 'redq_rental';
            } 
        }
        if($product_type !== 'redq_rental'){
            return $actions;
        }
        unset($actions['cancel']);
        return $actions;
    }
}
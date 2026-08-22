<?php

/**
 * RNB Global Accessable Functions
 *
 * Functions for order specific things.
 *
 * @author        RedQTeam
 * @category    Core
 * @package    RNB Global Accessable Functions
 * @version     3.0.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Check for terms
 *
 * @param int $post_id
 * @param string $taxonomy
 * @return boolean
 */
function rnb_has_term($post_id, $taxonomy)
{
    $terms = get_the_terms($post_id, $taxonomy);

    if (is_wp_error($terms) || empty($terms)) {
        return false;
    }

    return true;
}

/**
 * Get ajax url
 *
 * @return string
 */
function rnb_get_ajax_url()
{
    return admin_url('admin-ajax.php');
}


/**
 * rnb_get_product_price
 *
 * @param mixed $product_id
 *
 * @param string $inventory_id
 * @return void|array
 */
function rnb_get_product_price($product_id, $inventory_id = '')
{
    $product = wc_get_product($product_id);

    if (empty($product) || $product->get_type() !== 'redq_rental') {
        return;
    }

    $labels     = rnb_get_settings($product_id, 'labels', ['unit_price']);
    $conditions = rnb_get_settings($product_id, 'conditions', ['show_price_type']);

    $price      = 0;
    $prefix     = '';
    $suffix     = $labels['unit_price'];
    $show_price = $conditions['show_price_type'];

    if (empty($inventory_id)) {
        $inventory = rnb_get_product_inventory_id($product_id);
        $inventory_id = !empty($inventory) ? $inventory[0] : null;
    }

    $pricing_data = redq_rental_get_pricing_data($inventory_id, $product_id);

    $day_pricing_type = isset($pricing_data['pricing_type']) ? $pricing_data['pricing_type'] : 'general_pricing';
    $hourly_pricing_type = isset($pricing_data['hourly_pricing_type']) ? $pricing_data['hourly_pricing_type'] : 'hourly_pricing';
    $pricing_type = $show_price === 'daily' ? $day_pricing_type : $hourly_pricing_type;

    switch ($pricing_type) {
        case 'general_pricing':
            $price = $pricing_data['general_pricing'];
            break;
        case 'daily_pricing':
            $daily_pricing = $pricing_data['daily_pricing'];
            $today = strtolower(date('l'));
            $price = isset($daily_pricing[$today]) ? $daily_pricing[$today] : 0;
            $prefix = esc_html__('From ', 'redq-rental');
            break;
        case 'monthly_pricing':
            $monthly_pricing = $pricing_data['monthly_pricing'];
            $current_month = strtolower(date('F'));
            $price = isset($monthly_pricing[$current_month]) ? $monthly_pricing[$current_month] : 0;
            $prefix = esc_html__('From ', 'redq-rental');
            break;
        case 'days_range':
            $day_ranges_cost = $pricing_data['days_range'];
            $price = isset($day_ranges_cost[0]) ? $day_ranges_cost[0]['range_cost'] : 0;
            $prefix = isset($day_ranges_cost[0]['cost_applicable']) && $day_ranges_cost[0]['cost_applicable'] === 'fixed' ? '' : esc_html__('From ', 'redq-rental');
            break;
        case 'hourly_general':
            $price = $pricing_data['hourly_general'];
            break;
        case 'hourly_range':
            $hourly_ranges_cost = $pricing_data['hourly_range'];
            $price = isset($hourly_ranges_cost[0]) ? $hourly_ranges_cost[0]['range_cost'] : 0;
            $prefix = isset($hourly_ranges_cost[0]['cost_applicable']) && $hourly_ranges_cost[0]['cost_applicable'] === 'fixed' ? '' : esc_html__('From ', 'redq-rental');
            break;
        case 'flat_hours':
            if ($hourly_pricing_type === 'hourly_general') {
                $price = $pricing_data['hourly_general'];
            } else {
                $hourly_ranges_cost = $pricing_data['hourly_range'];
                $price = isset($hourly_ranges_cost[0]) ? $hourly_ranges_cost[0]['range_cost'] : 0;
                $prefix = esc_html__('From ', 'redq-rental');
            }
            break;
    }

    $results = [
        'price_limit' => $pricing_data['price_limit'],
        'show_range'  => apply_filters('rnb_show_price_range', true),
        'price'       => $price,
        'prefix'      => $prefix,
        'suffix'      => $suffix,
    ];

    return apply_filters('rnb_price_args', $results, $product_id, $inventory_id);
}

/**
 * Get inventories price
 *
 * @param int $inventory_id
 * @param int $product_id
 * @return array
 */
function get_inventory_price($inventory_id, $product_id)
{
    $conditions = redq_rental_get_settings($product_id, 'conditions');
    $conditions = $conditions['conditions'];

    $show_price = $conditions['show_price_type'];
    $pricing_data = redq_rental_get_pricing_data($inventory_id, $product_id);

    $day_pricing_type = isset($pricing_data['pricing_type']) ? $pricing_data['pricing_type'] : 'general_pricing';
    $hourly_pricing_type = isset($pricing_data['hourly_pricing_type']) ? $pricing_data['hourly_pricing_type'] : 'hourly_pricing';
    $pricing_type = $show_price === 'daily' ? $day_pricing_type : $hourly_pricing_type;

    $price = 0;
    $prefix = '';

    switch ($pricing_type) {
        case 'general_pricing':
            $price = $pricing_data['general_pricing'];
            break;
        case 'daily_pricing':
            $daily_pricing = $pricing_data['daily_pricing'];
            $today = strtolower(date('l'));
            $price = isset($daily_pricing[$today]) ? $daily_pricing[$today] : 0;
            $prefix = esc_html__('From ', 'redq-rental');
            break;
        case 'monthly_pricing':
            $monthly_pricing = $pricing_data['monthly_pricing'];
            $current_month = strtolower(date('F'));
            $price = isset($monthly_pricing[$current_month]) ? $monthly_pricing[$current_month] : 0;
            $prefix = esc_html__('From ', 'redq-rental');
            break;
        case 'days_range':
            $day_ranges_cost = $pricing_data['days_range'];
            $price = isset($day_ranges_cost[0]) ? $day_ranges_cost[0]['range_cost'] : 0;
            $prefix = isset($day_ranges_cost[0]['cost_applicable']) && $day_ranges_cost[0]['cost_applicable'] === 'fixed' ? '' : esc_html__('From ', 'redq-rental');
            break;
        case 'hourly_general':
            $price = $pricing_data['hourly_general'];
            break;
        case 'hourly_range':
            $hourly_ranges_cost = $pricing_data['hourly_range'];
            $price = isset($hourly_ranges_cost[0]) ? $hourly_ranges_cost[0]['range_cost'] : 0;
            $prefix = isset($hourly_ranges_cost[0]['cost_applicable']) && $hourly_ranges_cost[0]['cost_applicable'] === 'fixed' ? '' : esc_html__('From ', 'redq-rental');
            break;
        case 'flat_hours':
            if ($hourly_pricing_type === 'hourly_general') {
                $price = $pricing_data['hourly_general'];
            } else {
                $hourly_ranges_cost = $pricing_data['hourly_range'];
                $price = isset($hourly_ranges_cost[0]) ? $hourly_ranges_cost[0]['range_cost'] : 0;
                $prefix = esc_html__('From ', 'redq-rental');
            }
            break;
    }

    return [
        'price'  => $price,
        'prefix' => $prefix
    ];
}

/**
 * Convert To Output Format
 *
 * @param $date
 * @param string $output_format
 * @return false|string
 * @since 1.0.0
 */
function convert_to_output_format($date, $output_format = 'Y-m-d')
{
    $input_date = strtotime($date);
    return date($output_format, $input_date);
}


/**
 * Manage all Block Dates that are selected during blocking
 *
 * @param $tax_indentifier
 * @param $taxonomoy
 * @param $unique_model_name
 * @return array
 * @since 3.0.4
 */

function rental_iventory_selected_attributes($tax_indentifier, $taxonomoy, $unique_model_name)
{
    $selected_terms = [];

    if (isset($tax_indentifier) && !empty($tax_indentifier)) {
        foreach ($tax_indentifier as $taxi_key => $taxi_value) {
            if ($taxi_value['title'] === $unique_model_name) {
                $args = [
                    'orderby' => 'name',
                    'order' => 'ASC',
                    'fields' => 'all',
                ];
                if (taxonomy_exists('pickup_location')) {
                    $terms_per_post = wp_get_post_terms($taxi_value['inventory_id'], $taxonomoy, $args);
                }

                if (isset($terms_per_post) && is_array($terms_per_post)) {
                    foreach ($terms_per_post as $term_key => $term_value) {
                        $selected_terms[] = $term_value->slug;
                    }
                }
            }
        }

        return $selected_terms;
    }

    return $selected_terms;
}

if (!function_exists('rental_meta_data')) :

    /**
     * Enqueue css and js files
     *
     * @param string $key key name that will be checked
     * @param string $fallback default value
     * @return string
     * @since 3.0.5
     * @access public
     * @version 3.0.5
     */
    function rental_meta_data($product_id, $key, $fallback, $sub_key = '')
    {
        $product_id = isset($product_id) && !empty($product_id) ? $product_id : get_the_ID();
        $meta_value = get_post_meta($product_id, $key, true);
        if (!empty($sub_key)) {
            if (isset($meta_value) && !empty($meta_value)) {
                return $meta_value;
            }
        } elseif (isset($meta_value) && !empty($meta_value)) {
            return $meta_value;
        }
        return $fallback;
    }

endif;

if (!function_exists('rental_extract_meta_data')) :

    /**
     * Split options
     *
     *
     *
     * Call this function in following ways
     *
     * 1.  extract(
     *      rental_extract_meta_data(
     *          array(
     *            'Your_variable_name' = array ('default_value', 'main_ara_key', 'main_ara_sub_key'),
     *         )
     *      )
     *     );
     *
     *
     *
     * @version 3.0.5
     * @since 3.0.5
     * @var Array $helper
     * @access public
     */
    function rental_extract_meta_data($helper)
    {
        foreach ($helper as $option_key => $option_fallback) {
            if (is_array($option_fallback)) {
                $product_id = $option_fallback[0];
                $fallback = $option_fallback[1];
                $main_key = array_key_exists(2, $option_fallback) ? $option_fallback[2] : '';
                $sub_key = array_key_exists(3, $option_fallback) ? $option_fallback[3] : '';
                $helper[$option_key] = rental_meta_data($product_id, $main_key, $fallback, $sub_key);
            } else {
                $helper[$option_key] = rental_meta_data($option_key, $option_fallback, '');
            }
        }

        return $helper;
    }

endif;

if (!function_exists('rental_product_in_cart')) :
    /**
     * @version 3.0.5
     * @since 3.0.5
     * @var Array $helper
     * @access public
     */
    function rental_product_in_cart($product_id)
    {
        global $woocommerce;
        $cart_dates = [];

        return $cart_dates;
    }

endif;


if (!function_exists('redq_rental_staring_block_days')) :

    /**
     * Retrieve all block dates starting from current day
     *
     * @param null
     * @since 3.0.9
     * @version 3.0.9
     *
     */
    function redq_rental_staring_block_days($product_id)
    {
        $conditional_data = redq_rental_get_settings($product_id, 'conditions');
        $conditional_data = $conditional_data['conditions'];
        $output_format = $conditional_data['date_format'];
        $rental_block = $conditional_data['blockable'];
        $weekend = $conditional_data['weekends'];
        $no_of_days = $conditional_data['pre_block_days'];

        $standard_format = 'Y-m-d';
        $starting_block_dates = [];
        $today = strtotime(date($standard_format));
        $numeric_today = date('w', $today);
        $count = 0;

        while ($count < $no_of_days) {
            $day = strtotime('+' . $count . ' day', $today);
            $count_day = date('w', $day);
            if (in_array($count_day, $weekend, true)) {
                $no_of_days++;
                $count++;
                continue;
            }
            $starting_block_dates[] = date($output_format, $day);
            $count++;
        }

        return $starting_block_dates;
    }

endif;


if (!function_exists('rnb_process_rental_order_data')) :

    /**
     * Process rental order data
     *
     * @param null
     * @return all block post booking block days
     * @since 3.0.9
     * @version 3.0.9
     *
     */
    function rnb_process_rental_order_data($product_id, $order_id, $item_id, $inventory_id, $booked_dates_ara, $quantity, $block_by = 'FRONTEND_ORDER')
    {
        if (!count($booked_dates_ara)) {
            return;
        }

        global $wpdb;

        //Handle pre block and post block dates
        $conditional_data = redq_rental_get_settings($product_id, 'conditions');
        $conditional_data = $conditional_data['conditions'];

        if (!empty($conditional_data['before_block_days'])) {
            $booked_dates_ara['pickup_datetime'] = date('Y-m-d H:i:s', strtotime('-' . $conditional_data['before_block_days'] . ' day', strtotime($booked_dates_ara['pickup_datetime'])));
        }

        if (!empty($conditional_data['post_block_days'])) {
            $booked_dates_ara['return_datetime'] = date('Y-m-d H:i:s', strtotime('+' . $conditional_data['post_block_days'] . ' day', strtotime($booked_dates_ara['return_datetime'])));
        }
        // pre post block hour calculation 
        if (isset($conditional_data['before_block_hours']) && !empty($conditional_data['before_block_hours'])) {
            $booked_dates_ara['pickup_datetime'] = date('Y-m-d H:i:s', strtotime('-' . $conditional_data['before_block_hours'] . ' hour', strtotime($booked_dates_ara['pickup_datetime'])));
        }
        if (!empty($conditional_data['post_block_hours'])) {
            $booked_dates_ara['return_datetime'] = date('Y-m-d H:i:s', strtotime('+' . $conditional_data['post_block_hours'] . ' hour', strtotime($booked_dates_ara['return_datetime'])));
        }
        //End

        $booked_infos = [
            'pickup_date'  => $booked_dates_ara['pickup_datetime'],
            'return_date'  => $booked_dates_ara['return_datetime'],
            'inventory_id' => $inventory_id,
            'block_by'     => $block_by
        ];
        if(isset($booked_dates_ara['delete_status'])){
            $booked_infos['delete_status'] =  $booked_dates_ara['delete_status'];
        }

        //Inventories reference
        if (isset($inventory_id) && !empty($inventory_id)) {
            update_post_meta($order_id, 'order_item_' . $item_id . '_inventory_ref', $inventory_id);
        }

        if (isset($booked_infos) && !empty($booked_infos)) {
            rnb_save_dates_on_database($product_id, $inventory_id, $order_id, $item_id, $booked_infos);
        }
    }

endif;

if (!function_exists('rnb_save_dates_on_database')) :
    /**
     * rnb_save_dates_on_database
     *
     * @param mixed $product_id
     * @param mixed $order_id
     * @param mixed $item_id
     * @param mixed $block_dates_times
     *
     * @return void
     */
    function rnb_save_dates_on_database($product_id, $inventory_id, $order_id, $item_id, $booked_infos)
    {
        if (isset($booked_infos) && !empty($booked_infos)) {
            $length = sizeof($booked_infos);
            if (!class_exists('SitePress')) {
                $args = [
                    'pickup_datetime' => $booked_infos['pickup_date'],
                    'return_datetime' => $booked_infos['return_date'],
                    'product_id'      => $product_id,
                    'inventory_id'    => $inventory_id,
                    'order_id'        => $order_id,
                    'item_id'         => $item_id,
                    'block_by'        => $booked_infos['block_by']
                ];
                if(isset($booked_infos['delete_status'])){
                    $args['delete_status'] = $booked_infos['delete_status'];
                }
                rnb_booking_dates_insert($args);
            } else {
                $languages = apply_filters('wpml_active_languages', null, 'orderby=id&order=desc');
                $translated_posts = [];

                if (isset($languages) && sizeof($languages) !== 0) {
                    foreach ($languages as $lang_key => $lang_value) {
                        $language_code = $lang_value['language_code'];
                        $p_id = icl_object_id($product_id, 'product', false, $language_code);
                        $i_id = icl_object_id($inventory_id, 'inventory', false, $language_code);
                        $translated_posts[$language_code]['product_id'] = $p_id;
                        $translated_posts[$language_code]['inventory_id'] = $i_id;
                    }
                }

                if (!empty($translated_posts)) {
                    foreach ($translated_posts as $lang_key => $translated_post) {
                        $args = [
                            'pickup_datetime' => $booked_infos['pickup_date'],
                            'return_datetime' => $booked_infos['return_date'],
                            'product_id'      => $translated_post['product_id'],
                            'inventory_id'    => $translated_post['inventory_id'],
                            'order_id'        => $order_id,
                            'item_id'         => $item_id,
                            'lang'            => $lang_key,
                            'block_by'        => $booked_infos['block_by']
                        ];
                        if(isset($booked_infos['delete_status'])){
                            $args['delete_status'] = $booked_infos['delete_status'];
                        }
                        rnb_booking_dates_insert($args);
                    }
                }
            }
        }
    }
endif;

if (!function_exists('redq_rental_get_settings')) :

    /**
     * Retrieve all global and local settings
     *
     * @param null
     * @return array block post booking block days
     * @since 4.0.5
     * @version 4.0.5
     *
     */
    function redq_rental_get_settings($product_id, $type = 'labels', $required_values = ['pickup_date'])
    {
        $settings_data = [];

        extract(rental_extract_meta_data([
            'display_settings'     => [$product_id, 'global', 'rnb_settings_for_display'],
            'labels_settings'      => [$product_id, 'global', 'rnb_settings_for_labels'],
            'conditions_settings'  => [$product_id, 'global', 'rnb_settings_for_conditions'],
            'validations_settings' => [$product_id, 'global', 'rnb_settings_for_validations'],
        ]));

        switch ($type) {
            case 'general':

                $settings_data['general'] = [
                    'lang_domain'          => get_option('rnb_lang_domain'),
                    'months'               => get_option('rnb_lang_months'),
                    'weekdays'             => get_option('rnb_lang_weekdays'),
                    'instance_pay_type'    => get_option('rnb_instance_payment_type'),
                    'instance_pay'         => get_option('rnb_instance_payment'),
                    'total_days'           => get_option('rnb_total_days_label', esc_html__('Total Days', 'redq-rental')),
                    'total_hours'          => get_option('rnb_total_hours_label', esc_html__('Total Hours', 'redq-rental')),
                    'payment_due'          => get_option('rnb_payment_due_label', esc_html__('Payment Due', 'redq-rental')),
                    'attribute_tab'        => get_option('rnb_attribute_tab', esc_html__('Attributes', 'redq-rental')),
                    'feature_tab'          => get_option('rnb_feature_tab', esc_html__('Features', 'redq-rental')),
                    'rfq_user_pass'        => get_option('rnb_enable_rfq_without_user_pass', 'yes'),
                    'day_of_week_start'    => (int) get_option('rnb_day_of_week_start', 1) - 1,
                    'holidays'             => get_option('rnb_holidays', []),
                    'admin_email'          => get_option('rnb_rfq_admin_profile_email'),
                    'admin_name'           => get_option('rnb_rfq_admin_profile_name'),
                    'pickup_location_cost' => get_option('rnb_pickup_location_cost', esc_html__('Pickup Location Cost', 'redq-rental')),
                    'return_location_cost' => get_option('rnb_return_location_cost', esc_html__('Return Location Cost', 'redq-rental')),
                    'distance_cost'        => get_option('rnb_distance_cost', esc_html__('Distance Cost', 'redq-rental')),
                    'duration_cost'        => get_option('rnb_duration_cost', esc_html__('Duration Cost', 'redq-rental')),
                    'discount_amount'      => get_option('rnb_discount_amount', esc_html__('Discount amount', 'redq-rental')),
                    'resource_cost'        => get_option('rnb_resource_cost', esc_html__('Resource Cost', 'redq-rental')),
                    'category_cost'        => get_option('rnb_category_cost', esc_html__('Category Cost', 'redq-rental')),
                    'adult_cost'           => get_option('rnb_adult_cost', esc_html__('Adult Cost', 'redq-rental')),
                    'child_cost'           => get_option('rnb_child_cost', esc_html__('Child Cost', 'redq-rental')),
                    'deposit_amount'       => get_option('rnb_deposit_amount', esc_html__('Deposit', 'redq-rental')),
                    'instant_pay_amount'   => get_option('rnb_instant_pay_amount', esc_html__('Instant Pay', 'redq-rental')),
                    'initial_amount'       => get_option('rnb_initial_value_amount', esc_html__('Initial Amount', 'redq-rental')),
                    'total_instant_pay'    => get_option('rnb_total_instant_pay_amount', esc_html__('Total Instant Pay', 'redq-rental')),
                    'payment_due_amount'   => get_option('rnb_payment_due_amount', esc_html__('Payment Due', 'redq-rental')),
                    'quote_total_amount'   => get_option('rnb_quote_total_amount', esc_html__('Quote total', 'redq-rental')),

                    'subtotal_amount'      => get_option('rnb_subtotal', esc_html__('Subtotal', 'redq-rental')),
                    'grand_total_amount'   => get_option('rnb_grand_total', esc_html__('Grand Total', 'redq-rental')),

                    'gdpr_enable'          => get_option('rnb_rfq_gdpr_enable', 'no'),
                    'gdpr_text'            => get_option('rnb_rfq_gdpr_text', esc_html__('Please accept to consent to your data being stored in line with the guidelines set out in our ', 'redq-rental')),
                    'gdpr_page'            => get_option('rnb_rfq_gdpr_page', false),
                ];

                break;
            case 'display':
                if ($display_settings === 'local') {
                    extract(rental_extract_meta_data([
                        'pickup_date'      => [$product_id, 'open', 'redq_rental_local_show_pickup_date'],
                        'pickup_time'      => [$product_id, 'open', 'redq_rental_local_show_pickup_time'],
                        'return_date'      => [$product_id, 'open', 'redq_rental_local_show_dropoff_date'],
                        'return_time'      => [$product_id, 'open', 'redq_rental_local_show_dropoff_time'],
                        'quantity'         => [$product_id, 'open', 'rnb_enable_quantity'],
                        'flip_box'         => [$product_id, 'open', 'redq_rental_local_show_pricing_flip_box'],
                        'discount'         => [$product_id, 'open', 'redq_rental_local_show_price_discount_on_days'],
                        'instance_payment' => [$product_id, 'open', 'redq_rental_local_show_price_instance_payment'],
                        'rfq'              => [$product_id, 'open', 'redq_rental_local_show_request_quote'],
                        'book_now'         => [$product_id, 'open', 'redq_rental_local_show_book_now'],
                    ]));
                    $settings_data['display']['pickup_date'] = $pickup_date;
                    $settings_data['display']['pickup_time'] = $pickup_time;
                    $settings_data['display']['return_date'] = $return_date;
                    $settings_data['display']['return_time'] = $return_time;
                    $settings_data['display']['quantity'] = $quantity;
                    $settings_data['display']['flip_box'] = $flip_box;
                    $settings_data['display']['instance_payment'] = $instance_payment;
                    $settings_data['display']['discount'] = $discount;
                    $settings_data['display']['rfq'] = $rfq;
                    $settings_data['display']['book_now'] = $book_now;
                } else {
                    $options_data = [];
                    $options_data['pickup_date'] = get_option('rnb_show_pickup_date', 'yes');
                    $options_data['pickup_time'] = get_option('rnb_show_pickup_time', 'yes');
                    $options_data['return_date'] = get_option('rnb_show_dropoff_date', 'yes');
                    $options_data['return_time'] = get_option('rnb_show_dropoff_time', 'yes');
                    $options_data['quantity'] = get_option('rnb_enable_quantity', 'yes');
                    $options_data['flip_box'] = get_option('rnb_enable_price_flipbox', 'yes');
                    $options_data['discount'] = get_option('rnb_enable_price_discount', 'closed');
                    $options_data['instance_payment'] = get_option('rnb_enable_instance_payment', 'closed');
                    $options_data['rfq'] = get_option('rnb_enable_rfq_btn', 'closed');
                    $options_data['book_now'] = get_option('rnb_enable_book_now_btn', 'yes');

                    foreach ($options_data as $key => $value) {
                        if (!empty($value) && $value === 'yes') {
                            $settings_data['display'][$key] = 'open';
                        } else {
                            $settings_data['display'][$key] = 'closed';
                        }
                    }
                }
                break;

            case 'conditions':
                if ($conditions_settings === 'local') {
                    extract(rental_extract_meta_data([
                        'blockable'             => [$product_id, 'yes', 'redq_block_general_dates'],
                        'date_format'           => [$product_id, 'm/d/Y', 'redq_calendar_date_format'],
                        'euro_format'           => [$product_id, 'no', 'redq_choose_european_date_format'],
                        'max_time_late'         => [$product_id, '0', 'redq_max_time_late'],
                        'pay_extra_hours'       => [$product_id, 'no', 'rnb_pay_extra_hours'],
                        'single_day_booking'    => [$product_id, 'open', 'redq_rental_local_enable_single_day_time_based_booking'],
                        'include_trailing_date' => [$product_id, 'open', 'rnb_include_trailing_date'],
                        'max_book_days'         => [$product_id, '', 'redq_max_rental_days'],
                        'min_book_days'         => [$product_id, '', 'redq_min_rental_days'],
                        'max_book_hours'        => [$product_id, '', 'redq_max_rental_hours'],
                        'min_book_hours'        => [$product_id, '', 'redq_min_rental_hours'],
                        'pre_block_days'        => [$product_id, '', 'redq_rental_starting_block_dates'],
                        'before_block_days'     => [$product_id, 0, 'redq_rental_before_booking_block_dates'],
                        'post_block_days'       => [$product_id, 0, 'redq_rental_post_booking_block_dates'],
                        'time_interval'         => [$product_id, 30, 'redq_time_interval'],
                        'show_price_type'       => [$product_id, 'daily', 'rnb_show_price_type'],
                        'weekends'              => [$product_id, [], 'redq_rental_off_days'],
                        'allowed_times'         => [$product_id, '', 'redq_allowed_times'],
                        'booking_layout'        => [$product_id, 'layout_one', 'rnb_booking_layout'],
                        'time_format'           => [$product_id, '24-hours', 'redq_calendar_time_format'],
                    ]));
                    $settings_data['conditions']['blockable'] = $blockable;
                    $settings_data['conditions']['date_format'] = $date_format;
                    $settings_data['conditions']['euro_format'] = $euro_format;
                    $settings_data['conditions']['time_format'] = $time_format;
                    $settings_data['conditions']['max_time_late'] = (int) $max_time_late;
                    $settings_data['conditions']['pay_extra_hours'] = $pay_extra_hours;
                    $settings_data['conditions']['single_day_booking'] = $single_day_booking;
                    $settings_data['conditions']['include_trailing_date'] = $include_trailing_date;
                    $settings_data['conditions']['max_book_days'] = (int) $max_book_days;
                    $settings_data['conditions']['min_book_days'] = (int) $min_book_days;
                    $settings_data['conditions']['max_book_hours'] = (int) $max_book_hours;
                    $settings_data['conditions']['min_book_hours'] = (int) $min_book_hours;
                    $settings_data['conditions']['pre_block_days'] = (int) $pre_block_days;
                    $settings_data['conditions']['before_block_days'] = (int) $before_block_days;
                    $settings_data['conditions']['post_block_days'] = (int) $post_block_days;
                    $settings_data['conditions']['before_block_hours'] = isset($before_block_hours) ? (int) $before_block_hours : 0;
                    $settings_data['conditions']['post_block_hours'] = isset($post_block_hours) ? (int) $post_block_hours : 0;
                    $settings_data['conditions']['time_interval'] = !empty($time_interval) ? (int) $time_interval : 30;
                    $settings_data['conditions']['show_price_type'] = $show_price_type;
                    $settings_data['conditions']['weekends'] = rnb_format_weekend($weekends);

                    $settings_data['conditions']['allowed_times'] = !empty($allowed_times) ? rnb_format_allow_times($allowed_times) : [];
                    $settings_data['conditions']['booking_layout'] = $booking_layout;
                } else {
                    $settings_data['conditions']['blockable'] = get_option('rnb_block_rental_days', 'yes');
                    $settings_data['conditions']['date_format'] = get_option('rnb_choose_date_format', 'm/d/Y');
                    $settings_data['conditions']['time_format'] = get_option('rnb_choose_time_format', '24-hours');
                    $settings_data['conditions']['euro_format'] = $settings_data['conditions']['date_format'] === 'd/m/Y' ? 'yes' : 'no';
                    $settings_data['conditions']['max_time_late'] = get_option('rnb_max_time_late', 0);
                    $settings_data['conditions']['pay_extra_hours'] = get_option('rnb_pay_extra_hours', 'no');
                    $single_day_booking = get_option('rnb_single_day_booking', 'yes');
                    $settings_data['conditions']['single_day_booking'] = $single_day_booking === 'yes' ? 'open' : 'closed';
                    $include_trailing_date = get_option('rnb_include_trailing_date', 'yes');
                    $settings_data['conditions']['include_trailing_date'] = $include_trailing_date === 'yes' ? 'open' : 'closed';
                    $settings_data['conditions']['max_book_days'] = get_option('rnb_max_book_day');
                    $settings_data['conditions']['min_book_days'] = get_option('rnb_min_book_day');
                    $settings_data['conditions']['max_book_hours'] = get_option('rnb_max_book_hours');
                    $settings_data['conditions']['min_book_hours'] = get_option('rnb_min_book_hours');
                    $settings_data['conditions']['pre_block_days'] = get_option('rnb_staring_block_days', 0);
                    $settings_data['conditions']['before_block_days'] = get_option('rnb_before_block_days', 0);
                    $settings_data['conditions']['post_block_days'] = get_option('rnb_post_block_days');
                    $settings_data['conditions']['before_block_hours'] = get_option('rnb_before_block_hours', 0);
                    $settings_data['conditions']['post_block_hours'] = get_option('rnb_post_block_hours');
                    $settings_data['conditions']['time_interval'] = get_option('rnb_time_intervals') ? get_option('rnb_time_intervals') : 30;
                    $settings_data['conditions']['show_price_type'] = get_option('rnb_show_price_type', 'daily');
                    $settings_data['conditions']['booking_layout'] = get_option('rnb_booking_layout', 'layout_one');

                    //subtraction 1 to adjust weekday
                    $saved_weekends = get_option('rnb_weekends');
                    $weekends = [];
                    if (isset($saved_weekends) && !empty($saved_weekends)) {
                        foreach ($saved_weekends as $wkey => $wvalue) {
                            $weekends[] = ($wvalue - 1);
                        }
                    }
                    $settings_data['conditions']['weekends'] = rnb_format_weekend($weekends);
                    $allowed_times = get_option('rnb_allowed_times');
                    $settings_data['conditions']['allowed_times'] = !empty($allowed_times) ? rnb_format_allow_times($allowed_times) : [];
                }
                break;

            case 'validations':
                if ($validations_settings === 'local') {
                    extract(rental_extract_meta_data([
                        'pickup_location' => [$product_id, 'closed', 'redq_rental_local_required_pickup_location'],
                        'return_location' => [$product_id, 'closed', 'redq_rental_local_required_return_location'],
                        'person' => [$product_id, 'closed', 'redq_rental_local_required_person'],
                        'pickup_time' => [$product_id, 'closed', 'redq_rental_required_local_pickup_time'],
                        'return_time' => [$product_id, 'closed', 'redq_rental_required_local_return_time'],
                    ]));
                    $settings_data['validations']['pickup_location'] = $pickup_location;
                    $settings_data['validations']['return_location'] = $return_location;
                    $settings_data['validations']['person'] = $person;
                    $settings_data['validations']['pickup_time'] = $pickup_time;
                    $settings_data['validations']['return_time'] = $return_time;

                    extract(rental_extract_meta_data([
                        'fri_min' => [$product_id, '00:00', 'redq_rental_fri_min_time'],
                        'fri_max' => [$product_id, '24:00', 'redq_rental_fri_max_time'],
                        'sat_min' => [$product_id, '00:00', 'redq_rental_sat_min_time'],
                        'sat_max' => [$product_id, '24:00', 'redq_rental_sat_max_time'],
                        'sun_min' => [$product_id, '00:00', 'redq_rental_sun_min_time'],
                        'sun_max' => [$product_id, '24:00', 'redq_rental_sun_max_time'],
                        'mon_min' => [$product_id, '00:00', 'redq_rental_mon_min_time'],
                        'mon_max' => [$product_id, '24:00', 'redq_rental_mon_max_time'],
                        'tue_min' => [$product_id, '00:00', 'redq_rental_thu_min_time'],
                        'tue_max' => [$product_id, '24:00', 'redq_rental_thu_max_time'],
                        'wed_min' => [$product_id, '00:00', 'redq_rental_wed_min_time'],
                        'wed_max' => [$product_id, '24:00', 'redq_rental_wed_max_time'],
                        'thu_min' => [$product_id, '00:00', 'redq_rental_thur_min_time'],
                        'thu_max' => [$product_id, '24:00', 'redq_rental_thur_max_time'],
                    ]));
                } else {
                    $options_data = [];
                    $options_data['pickup_location'] = get_option('rnb_required_pickup_loc', 'yes');
                    $options_data['return_location'] = get_option('rnb_required_dropoff_loc', 'yes');
                    $options_data['pickup_time'] = get_option('rnb_required_pickup_time', 'yes');
                    $options_data['return_time'] = get_option('rnb_required_dropoff_time', 'yes');
                    $options_data['person'] = get_option('rnb_required_person', 'yes');
                    foreach ($options_data as $key => $value) {
                        if (!empty($value) && $value === 'yes') {
                            $settings_data['validations'][$key] = 'open';
                        } else {
                            $settings_data['validations'][$key] = 'closed';
                        }
                    }

                    $default             = [];
                    $default['fri_min']  = '00:00';
                    $default['fri_max']  = '24:00';
                    $default['sat_min']  = '00:00';
                    $default['sat_max']  = '24:00';
                    $default['sun_min']  = '00:00';
                    $default['sun_max']  = '24:00';
                    $default['mon_min']  = '00:00';
                    $default['mon_max']  = '24:00';
                    $default['thu_min']  = '00:00';
                    $default['thu_max']  = '24:00';
                    $default['wed_min']  = '00:00';
                    $default['wed_max']  = '24:00';
                    $default['thur_min'] = '00:00';
                    $default['thur_max'] = '24:00';

                    $data = get_option('rnb_opening_closing');
                    $data = $data ? $data : [];
                    $data = wp_parse_args($data, $default);
                    extract($data);
                }

                $time_avail = [];
                $time_avail['fri']['min'] = $fri_min;
                $time_avail['fri']['max'] = $fri_max;
                $time_avail['sat']['min'] = $sat_min;
                $time_avail['sat']['max'] = $sat_max;
                $time_avail['sun']['min'] = $sun_min;
                $time_avail['sun']['max'] = $sun_max;
                $time_avail['mon']['min'] = $mon_min;
                $time_avail['mon']['max'] = $mon_max;
                $time_avail['tue']['min'] = $thu_min;
                $time_avail['tue']['max'] = $thu_max;
                $time_avail['wed']['min'] = $wed_min;
                $time_avail['wed']['max'] = $wed_max;
                $time_avail['thu']['min'] = $thur_min;
                $time_avail['thu']['max'] = $thur_max;

                $settings_data['validations']['openning_closing'] = $time_avail;

                break;

            case 'layout_two':

                if (isset($required_values) && !empty($required_values)) :
                    foreach ($required_values as $key => $value) {
                        $data = [];

                        if ($value === 'inventory') {
                            $data['inventory_top_heading'] = get_option('rnb_inventory_top_heading');
                            $data['inventory_top_desc'] = get_option('rnb_inventory_top_desc');
                            $data['inventory_inner_heading'] = get_option('rnb_inventory_inner_heading');
                            $data['inventory_inner_desc'] = get_option('rnb_inventory_inner_desc');
                        }

                        if ($value === 'datetime') {
                            $data['date_top_heading'] = get_option('rnb_date_top_heading');
                            $data['date_top_desc'] = get_option('rnb_date_top_desc');
                            $data['date_inner_heading'] = get_option('rnb_date_inner_heading');
                            $data['date_inner_desc'] = get_option('rnb_date_inner_desc');
                        }

                        if ($value === 'location') {
                            $data['location_top_heading'] = get_option('rnb_location_top_heading');
                            $data['location_top_desc'] = get_option('rnb_location_top_desc');
                            $data['location_inner_heading'] = get_option('rnb_location_inner_heading');
                            $data['location_inner_desc'] = get_option('rnb_location_inner_desc');
                        }

                        if ($value === 'resource') {
                            $data['resource_top_heading'] = get_option('rnb_resource_top_heading');
                            $data['resource_top_desc'] = get_option('rnb_resource_top_desc');
                            $data['resource_inner_heading'] = get_option('rnb_resource_inner_heading');
                            $data['resource_inner_desc'] = get_option('rnb_resource_inner_desc');
                        }

                        if ($value === 'person') {
                            $data['person_top_heading'] = get_option('rnb_person_top_heading');
                            $data['person_top_desc'] = get_option('rnb_person_top_desc');
                            $data['person_inner_heading'] = get_option('rnb_person_inner_heading');
                            $data['person_inner_desc'] = get_option('rnb_person_inner_desc');
                        }

                        if ($value === 'deposit') {
                            $data['deposit_top_heading'] = get_option('rnb_deposit_top_heading');
                            $data['deposit_top_desc'] = get_option('rnb_deposit_top_desc');
                            $data['deposit_inner_heading'] = get_option('rnb_deposit_inner_heading');
                            $data['deposit_inner_desc'] = get_option('rnb_deposit_inner_desc');
                        }

                        if ($value === 'summary') {
                            $data['summary_top_heading'] = get_option('rnb_summary_top_heading');
                            $data['summary_top_desc'] = get_option('rnb_summary_top_desc');
                            $data['summary_inner_heading'] = get_option('rnb_summary_inner_heading');
                            $data['summary_inner_desc'] = get_option('rnb_summary_inner_desc');
                        }

                        $settings_data[$value] = $data;
                    }

                endif;

                break;

            default:
                if ($labels_settings === 'local') {
                    $builds_fields = [];
                    if (isset($required_values) && !empty($required_values)) {
                        foreach ($required_values as $key => $value) {
                            if ($value === 'pickup_location') {
                                $builds_fields['pickup_location'] = [$product_id, esc_html__('Pickup Locations', 'redq-rental'), 'redq_pickup_location_heading_title'];
                                $builds_fields['pickup_loc_placeholder'] = [$product_id, esc_html__('Pickup Locations', 'redq-rental'), 'redq_pickup_loc_placeholder'];

                                extract(rental_extract_meta_data($builds_fields));

                                $settings_data['labels']['pickup_location'] = $pickup_location;
                                $settings_data['labels']['pickup_loc_placeholder'] = $pickup_loc_placeholder;
                            }
                            if ($value === 'return_location') {
                                $builds_fields['return_location'] = [$product_id, esc_html__('Return Locations', 'redq-rental'), 'redq_dropoff_location_heading_title'];
                                $builds_fields['return_loc_placeholder'] = [$product_id, esc_html__('Return Locations', 'redq-rental'), 'redq_return_loc_placeholder'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['return_location'] = $return_location;
                                $settings_data['labels']['return_loc_placeholder'] = $return_loc_placeholder;
                            }
                            if ($value === 'pickup_date') {
                                $builds_fields['pickup_datetime'] = [$product_id, esc_html__('Pickup Date&Time', 'redq-rental'), 'redq_pickup_date_heading_title'];
                                $builds_fields['pickup_date'] = [$product_id, esc_html__('Date', 'redq-rental'), 'redq_pickup_date_placeholder'];
                                $builds_fields['pickup_time'] = [$product_id, esc_html__('Time', 'redq-rental'), 'redq_pickup_time_placeholder'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['pickup_datetime'] = $pickup_datetime;
                                $settings_data['labels']['pickup_date'] = $pickup_date;
                                $settings_data['labels']['pickup_time'] = $pickup_time;
                            }
                            if ($value === 'return_date') {
                                $builds_fields['return_datetime'] = [$product_id, esc_html__('Return Date&Time', 'redq-rental'), 'redq_dropoff_date_heading_title'];
                                $builds_fields['return_date'] = [$product_id, esc_html__('Date', 'redq-rental'), 'redq_dropoff_date_placeholder'];
                                $builds_fields['return_time'] = [$product_id, esc_html__('Time', 'redq-rental'), 'redq_dropoff_time_placeholder'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['return_datetime'] = $return_datetime;
                                $settings_data['labels']['return_date'] = $return_date;
                                $settings_data['labels']['return_time'] = $return_time;
                            }
                            if ($value === 'person') {
                                $builds_fields['adults'] = [$product_id, esc_html__('Adults', 'redq-rental'), 'redq_adults_heading_title'];
                                $builds_fields['adults_placeholder'] = [$product_id, esc_html__('Choose Adults', 'redq-rental'), 'redq_adults_placeholder'];
                                $builds_fields['childs'] = [$product_id, esc_html__('Childs', 'redq-rental'), 'redq_childs_heading_title'];
                                $builds_fields['childs_placeholder'] = [$product_id, esc_html__('Choose Childs', 'redq-rental'), 'redq_childs_placeholder'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['adults'] = $adults;
                                $settings_data['labels']['adults_placeholder'] = $adults_placeholder;
                                $settings_data['labels']['childs'] = $childs;
                                $settings_data['labels']['childs_placeholder'] = $childs_placeholder;
                            }

                            if ($value === 'quantity') {
                                $builds_fields['quantity'] = [$product_id, esc_html__('Quantity', 'redq-rental'), 'rnb_quantity_label'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['quantity'] = $quantity;
                            }

                            if ($value === 'resources') {
                                $builds_fields['resource'] = [$product_id, esc_html__('Resources', 'redq-rental'), 'redq_resources_heading_title'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['resource'] = $resource;
                            }
                            if ($value === 'categories') {
                                $builds_fields['category'] = [$product_id, esc_html__('Categories', 'redq-rental'), 'redq_rnb_cat_heading'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['categories'] = $category;
                            }
                            if ($value === 'deposites') {
                                $builds_fields['deposite'] = [$product_id, esc_html__('Deposite', 'redq-rental'), 'redq_security_deposite_heading_title'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['deposite'] = $deposite;
                            }
                            if ($value === 'inventory') {
                                $builds_fields['inventory'] = [$product_id, esc_html__('Inventory', 'redq-rental'), 'rnb_inventory_title'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['inventory'] = $inventory;
                            }
                            if ($value === 'booking_info') {
                                $builds_fields['discount'] = [$product_id, esc_html__('Discounts', 'redq-rental'), 'redq_discount_text_title'];
                                $builds_fields['instance_pay'] = [$product_id, esc_html__('Instance Pay', 'redq-rental'), 'redq_instance_pay_text_title'];
                                $builds_fields['total_cost'] = [$product_id, esc_html__('Total Cost', 'redq-rental'), 'redq_total_cost_text_title'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['discount'] = $discount;
                                $settings_data['labels']['instance_pay'] = $instance_pay;
                                $settings_data['labels']['total_cost'] = $total_cost;
                            }
                            if ($value === 'price_info') {
                                $builds_fields['flipbox'] = [$product_id, esc_html__('Show Pricing', 'redq-rental'), 'redq_show_pricing_flipbox_text'];
                                $builds_fields['flipbox_info'] = [$product_id, esc_html__('Pricing Info', 'redq-rental'), 'redq_flip_pricing_plan_text'];
                                $builds_fields['unit_price'] = [$product_id, esc_html__('/Per Day', 'redq-rental'), 'rnb_unit_price'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['flipbox'] = $flipbox;
                                $settings_data['labels']['flipbox_info'] = $flipbox_info;
                                $settings_data['labels']['unit_price'] = $unit_price;
                            }
                            if ($value === 'buttons') {
                                $builds_fields['book_now'] = [$product_id, esc_html__('Book Now', 'redq-rental'), 'redq_book_now_button_text'];
                                $builds_fields['rfq'] = [$product_id, esc_html__('Request For Quote', 'redq-rental'), 'redq_rfq_button_text'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['book_now'] = $book_now;
                                $settings_data['labels']['rfq'] = $rfq;
                            }

                            if ($value === 'notice') {
                                $builds_fields['invalid_range_notice'] = [$product_id, esc_html__('Invalid Date Range', 'redq-rental'), 'rnb_invalid_date_range_notice'];
                                $builds_fields['max_day_notice'] = [$product_id, esc_html__('Max Rental Days ', 'redq-rental'), 'rnb_max_day_notice'];
                                $builds_fields['min_day_notice'] = [$product_id, esc_html__('Min Rental Days', 'redq-rental'), 'rnb_min_day_notice'];
                                $builds_fields['max_hour_notice'] = [$product_id, esc_html__('Max Rental Hours ', 'redq-rental'), 'rnb_max_hour_notice'];
                                $builds_fields['min_hour_notice'] = [$product_id, esc_html__('Min Rental Hours', 'redq-rental'), 'rnb_min_hour_notice'];
                                $builds_fields['quantity_notice'] = [$product_id, esc_html__('Quantity is not available', 'redq-rental'), 'rnb_quantity_notice'];

                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['invalid_range_notice'] = $invalid_range_notice;
                                $settings_data['labels']['max_day_notice'] = $max_day_notice;
                                $settings_data['labels']['min_day_notice'] = $min_day_notice;
                                $settings_data['labels']['max_hour_notice'] = $max_hour_notice;
                                $settings_data['labels']['min_hour_notice'] = $min_hour_notice;
                                $settings_data['labels']['quantity_notice'] = $quantity_notice;
                            }

                            if ($value === 'rfq_form') {
                                $builds_fields['username'] = [$product_id, esc_html__('Username', 'redq-rental'), 'redq_username_placeholder'];
                                $builds_fields['password'] = [$product_id, esc_html__('Password', 'redq-rental'), 'redq_password_placeholder'];
                                $builds_fields['first_name'] = [$product_id, esc_html__('First Name', 'redq-rental'), 'redq_first_name_placeholder'];
                                $builds_fields['last_name'] = [$product_id, esc_html__('Last Name', 'redq-rental'), 'redq_last_name_placeholder'];
                                $builds_fields['email'] = [$product_id, esc_html__('Email', 'redq-rental'), 'redq_email_placeholder'];
                                $builds_fields['phone'] = [$product_id, esc_html__('Phone', 'redq-rental'), 'redq_phone_placeholder'];
                                $builds_fields['message'] = [$product_id, esc_html__('Message', 'redq-rental'), 'redq_message_placeholder'];

                                $builds_fields['username_title'] = [$product_id, esc_html__('Username', 'redq-rental'), 'redq_username_title'];
                                $builds_fields['password_title'] = [$product_id, esc_html__('Password', 'redq-rental'), 'redq_password_title'];
                                $builds_fields['first_name_title'] = [$product_id, esc_html__('First Name', 'redq-rental'), 'redq_first_name_title'];
                                $builds_fields['last_name_title'] = [$product_id, esc_html__('Last Name', 'redq-rental'), 'redq_last_name_title'];
                                $builds_fields['email_title'] = [$product_id, esc_html__('Email', 'redq-rental'), 'redq_email_title'];
                                $builds_fields['phone_title'] = [$product_id, esc_html__('Phone', 'redq-rental'), 'redq_phone_title'];
                                $builds_fields['message_title'] = [$product_id, esc_html__('Message', 'redq-rental'), 'redq_message_title'];

                                $builds_fields['submit_button'] = [$product_id, esc_html__('Submit', 'redq-rental'), 'redq_submit_button_text'];
                                extract(rental_extract_meta_data($builds_fields));
                                $settings_data['labels']['username'] = $username;
                                $settings_data['labels']['password'] = $password;
                                $settings_data['labels']['first_name'] = $first_name;
                                $settings_data['labels']['last_name'] = $last_name;
                                $settings_data['labels']['phone'] = $phone;
                                $settings_data['labels']['email'] = $email;
                                $settings_data['labels']['message'] = $message;

                                $settings_data['labels']['username_title'] = $username_title;
                                $settings_data['labels']['password_title'] = $password_title;
                                $settings_data['labels']['first_name_title'] = $first_name_title;
                                $settings_data['labels']['last_name_title'] = $last_name_title;
                                $settings_data['labels']['phone_title'] = $phone_title;
                                $settings_data['labels']['email_title'] = $email_title;
                                $settings_data['labels']['message_title'] = $message_title;

                                $settings_data['labels']['submit_button'] = $submit_button;
                            }
                        }
                    }
                } else {
                    if (isset($required_values) && !empty($required_values)) {
                        foreach ($required_values as $key => $value) {
                            if ($value === 'pickup_location') {
                                $settings_data['labels']['pickup_location'] = get_option('rnb_pickup_location_title', esc_html__('Pickup Location', 'redq-rental'));
                                $settings_data['labels']['pickup_loc_placeholder'] = get_option('rnb_pickup_location_placeholder', esc_html__('Pickup Location', 'redq-rental'));
                            }
                            if ($value === 'return_location') {
                                $settings_data['labels']['return_location'] = get_option('rnb_dropoff_location_title', esc_html__('Dropoff Location', 'redq-rental'));
                                $settings_data['labels']['return_loc_placeholder'] = get_option('rnb_dropoff_location_placeholder', esc_html__('Dropoff Location', 'redq-rental'));
                            }
                            if ($value === 'pickup_date') {
                                $settings_data['labels']['pickup_datetime'] = get_option('rnb_pickup_datetime_title', esc_html__('Pickup Date & Time', 'redq-rental'));
                                $settings_data['labels']['pickup_date'] = get_option('rnb_pickup_date_placeholder', esc_html__('Date', 'redq-rental'));
                                $settings_data['labels']['pickup_time'] = get_option('rnb_pickup_time_placeholder', esc_html__('Time', 'redq-rental'));
                            }
                            if ($value === 'return_date') {
                                $settings_data['labels']['return_datetime'] = get_option('rnb_dropoff_datetime_title', esc_html__('Return Date & Time', 'redq-rental'));
                                $settings_data['labels']['return_date'] = get_option('rnb_dropoff_date_placeholder', esc_html__('Date', 'redq-rental'));
                                $settings_data['labels']['return_time'] = get_option('rnb_dropoff_time_placeholder', esc_html__('Time', 'redq-rental'));
                            }
                            if ($value === 'person') {
                                $settings_data['labels']['adults'] = get_option('rnb_adults_title', esc_html__('Adults', 'redq-rental'));
                                $settings_data['labels']['adults_placeholder'] = get_option('rnb_adults_placeholder', esc_html__('Adults', 'redq-rental'));
                                $settings_data['labels']['childs'] = get_option('rnb_childs_title', esc_html__('Childs', 'redq-rental'));
                                $settings_data['labels']['childs_placeholder'] = get_option('rnb_childs_placeholder', esc_html__('Childs', 'redq-rental'));
                            }
                            if ($value === 'quantity') {
                                $settings_data['labels']['quantity'] = get_option('rnb_quantity_title', esc_html__('Quantity', 'redq-rental'));
                            }
                            if ($value === 'resources') {
                                $settings_data['labels']['resource'] = get_option('rnb_resources_title', esc_html__('Resource', 'redq-rental'));
                            }
                            if ($value === 'categories') {
                                $settings_data['labels']['categories'] = get_option('rnb_categories_title', esc_html__('Category', 'redq-rental'));
                            }
                            if ($value === 'deposites') {
                                $settings_data['labels']['deposite'] = get_option('rnb_deposit_title', esc_html__('Deposit', 'redq-rental'));
                            }
                            if ($value === 'inventory') {
                                $settings_data['labels']['inventory'] = get_option('rnb_inventory_title', esc_html__('Choose Inventory', 'redq-rental'));
                            }
                            if ($value === 'booking_info') {
                                $settings_data['labels']['discount'] = get_option('rnb_discount_text', esc_html__('Discount', 'redq-rental'));
                                $settings_data['labels']['instance_pay'] = get_option('rnb_instance_pay_text', esc_html__('Instance Pay', 'redq-rental'));
                                $settings_data['labels']['total_cost'] = get_option('rnb_total_cost_text', esc_html__('Total Cost', 'redq-rental'));
                            }
                            if ($value === 'price_info') {
                                $settings_data['labels']['flipbox'] = get_option('rnb_pricing_flipbox_title') ? get_option('rnb_pricing_flipbox_title') : esc_html__('Show Pricing', 'redq-rental');
                                $settings_data['labels']['flipbox_info'] = get_option('rnb_pricing_flipbox_info_title') ? get_option('rnb_pricing_flipbox_info_title') : esc_html__('Pricing Info', 'redq-rental');
                                $settings_data['labels']['unit_price'] = get_option('rnb_unit_price') ? get_option('rnb_unit_price') : esc_html__('/Per Day', 'redq-rental');
                            }
                            if ($value === 'buttons') {
                                $settings_data['labels']['book_now'] = get_option('rnb_book_now_text') ? get_option('rnb_book_now_text') : esc_html__('Book Now', 'redq-rental');
                                $settings_data['labels']['rfq'] = get_option('rnb_rfq_text') ? get_option('rnb_rfq_text') : esc_html__('Request For Quote', 'redq-rental');
                            }

                            if ($value === 'notice') {
                                $settings_data['labels']['invalid_range_notice'] = get_option('rnb_invalid_date_range_notice') ? get_option('rnb_invalid_date_range_notice') : esc_html__('Sorry! date range is not valid', 'redq-rental');
                                $settings_data['labels']['max_day_notice'] = get_option('rnb_max_day_notice') ? get_option('rnb_max_day_notice') : esc_html__('Sorry! Max booking days exceed', 'redq-rental');
                                $settings_data['labels']['min_day_notice'] = get_option('rnb_min_day_notice') ? get_option('rnb_min_day_notice') : esc_html__('Please choose min booking days', 'redq-rental');
                                $settings_data['labels']['max_hour_notice'] = get_option('rnb_max_hour_notice') ? get_option('rnb_max_hour_notice') : esc_html__('Sorry! Max booking hours exceed', 'redq-rental');
                                $settings_data['labels']['min_hour_notice'] = get_option('rnb_min_hour_notice') ? get_option('rnb_min_hour_notice') : esc_html__('Please choose min booking hours', 'redq-rental');
                                $settings_data['labels']['quantity_notice'] = get_option('rnb_quantity_notice') ? get_option('rnb_quantity_notice') : esc_html__('Sorry! quantity is not available', 'redq-rental');
                            }

                            if ($value === 'rfq_form') {
                                $settings_data['labels']['username'] = get_option('rnb_username_placeholder', esc_html__('Username', 'redq-rental'));
                                $settings_data['labels']['password'] = get_option('rnb_password_placeholder', esc_html__('Password', 'redq-rental'));
                                $settings_data['labels']['first_name'] = get_option('rnb_first_name_placeholder', esc_html__('First Name', 'redq-rental'));
                                $settings_data['labels']['last_name'] = get_option('rnb_last_name_placeholder', esc_html__('Last Name', 'redq-rental'));
                                $settings_data['labels']['email'] = get_option('rnb_email_placeholder', esc_html__('Email', 'redq-rental'));
                                $settings_data['labels']['phone'] = get_option('rnb_phone_placeholder', esc_html__('Phone', 'redq-rental'));
                                $settings_data['labels']['message'] = get_option('rnb_message_placeholder', esc_html__('Message', 'redq-rental'));

                                $settings_data['labels']['username_title'] = get_option('rnb_username_title', esc_html__('Username', 'redq-rental'));
                                $settings_data['labels']['password_title'] = get_option('rnb_password_title', esc_html__('Password', 'redq-rental'));
                                $settings_data['labels']['first_name_title'] = get_option('rnb_first_name_title', esc_html__('First Name', 'redq-rental'));
                                $settings_data['labels']['last_name_title'] = get_option('rnb_last_name_title', esc_html__('Last Name', 'redq-rental'));
                                $settings_data['labels']['email_title'] = get_option('rnb_email_title', esc_html__('Email', 'redq-rental'));
                                $settings_data['labels']['phone_title'] = get_option('rnb_phone_title', esc_html__('Phone', 'redq-rental'));
                                $settings_data['labels']['message_title'] = get_option('rnb_message_title', esc_html__('Message', 'redq-rental'));

                                $settings_data['labels']['submit_button'] = get_option('rnb_submit_button_text', esc_html__('Submit', 'redq-rental'));
                            }
                        }
                    }
                }
                break;
        }

        return $settings_data;
    }

endif;



if (!function_exists('redq_rental_get_pricing_data')) :


    /**
     * Pricing 
     *
     * @param int $inventory_id
     * @param int $product_id
     * @return array
     */
    function redq_rental_get_pricing_data($inventory_id, $product_id = null)
    {
        $pricing_data = [];

        extract(rental_extract_meta_data([
            'pricing_type'        => [$inventory_id, 'general_pricing', 'pricing_type'],
            'hourly_pricing_type' => [$inventory_id, 'hourly_price', 'hourly_pricing_type'],
            'price_discount'      => [$product_id, [], 'redq_price_discount_cost'],
            'perkilo_price'       => [$inventory_id, 0, 'perkilo_price'],
            'distance_unit_type'  => [$inventory_id, 'kilometer', 'distance_unit_type'],
        ]));

        $pricing_data['pricing_type'] = $pricing_type;
        $pricing_data['hourly_pricing_type'] = $hourly_pricing_type;
        $pricing_data['distance_unit_type'] = $distance_unit_type;
        $pricing_data['perkilo_price'] = $perkilo_price;

        switch ($pricing_type) {
            case 'daily_pricing':
                extract(rental_extract_meta_data([
                    'daily_pricing' => [$inventory_id, '', 'redq_daily_pricing'],
                ]));
                $pricing_data[$pricing_type] = $daily_pricing;
                break;
            case 'monthly_pricing':
                extract(rental_extract_meta_data([
                    'monthly_pricing' => [$inventory_id, '', 'redq_monthly_pricing'],
                ]));
                $pricing_data[$pricing_type] = $monthly_pricing;
                break;
            case 'days_range':
                extract(rental_extract_meta_data([
                    'days_range' => [$inventory_id, '', 'redq_day_ranges_cost'],
                ]));
                $pricing_data[$pricing_type] = $days_range;
                break;
            default:
                extract(rental_extract_meta_data([
                    'general_price' => [$inventory_id, '', 'general_price'],
                ]));
                $pricing_data[$pricing_type] = $general_price;
                break;
        }

        switch ($hourly_pricing_type) {
            case 'hourly_range':
                extract(rental_extract_meta_data([
                    'hourly_range' => [$inventory_id, '', 'redq_hourly_ranges_cost'],
                ]));
                $pricing_data[$hourly_pricing_type] = $hourly_range;
                break;
            default:
                extract(rental_extract_meta_data([
                    'hourly_price' => [$inventory_id, '', 'hourly_price'],
                ]));
                $pricing_data[$hourly_pricing_type] = $hourly_price;
                break;
        }

        $pricing_data['price_discount'] = $price_discount;
        $pricing_data['price_limit'] = redq_rental_calculate_price_limit($pricing_data, $inventory_id, $product_id);

        return apply_filters('rnb_product_pricing_info', $pricing_data, $inventory_id, $product_id);
    }

endif;

if (!function_exists('rnb_get_inventory_quantity')) {
    /**
     * Get quantity by inventory
     *
     * @param int $inventory_id
     * @param int $product_id
     * @return int
     */
    function rnb_get_inventory_quantity($inventory_id, $product_id = null)
    {
        $data = rental_extract_meta_data([
            'quantity' => [$inventory_id, 1, 'quantity'],
        ]);

        extract($data);

        return $quantity;
    }
}

if (!function_exists('redq_rental_calculate_price_limit')) :
    /**
     * redq_rental_calculate_price_limit
     *
     * @param mixed $pricing_data
     * @param mixed $inventory_id
     * @param mixed $product_id
     *
     * @return array
     */
    function redq_rental_calculate_price_limit($pricing_data, $inventory_id, $product_id)
    {
        $conditions = redq_rental_get_settings($product_id, 'conditions');
        $conditions = $conditions['conditions'];
        $show_price = $conditions['show_price_type'];

        $pricing = is_array($pricing_data[$pricing_data['pricing_type']]) ? array_values($pricing_data[$pricing_data['pricing_type']]) : [$pricing_data[$pricing_data['pricing_type']]];

        if ($pricing_data['pricing_type'] === 'days_range') {
            $pricing = is_array($pricing_data[$pricing_data['pricing_type']]) ? array_column($pricing_data[$pricing_data['pricing_type']], 'range_cost') : [];
        }

        if ($pricing_data['pricing_type'] === 'flat_hours' || $show_price === 'hourly') {
            $pricing = is_array($pricing_data[$pricing_data['hourly_pricing_type']]) ? array_values($pricing_data[$pricing_data['hourly_pricing_type']]) : [$pricing_data[$pricing_data['hourly_pricing_type']]];
            if ($pricing_data['hourly_pricing_type'] === 'hourly_range' && !empty($pricing_data['hourly_range'])) {
                $pricing = array_column($pricing_data[$pricing_data['hourly_pricing_type']], 'range_cost');
            }
        }

        $range = [
            'min' => is_array($pricing) && !empty($pricing) ? min($pricing) : 0,
            'max' => is_array($pricing) && !empty($pricing) ? max($pricing) : 0
        ];

        return apply_filters('rnb_unit_price_limit', $range, $inventory_id, $product_id);
    }
endif;

if (!function_exists('redq_rental_day_names')) :

    /**
     * day name
     * @return array
     */
    function redq_rental_day_names()
    {
        return [
            'friday'    => esc_html__('Friday', 'redq-rental'),
            'saturday'  => esc_html__('Saturday', 'redq-rental'),
            'sunday'    => esc_html__('Sunday', 'redq-rental'),
            'monday'    => esc_html__('Monday', 'redq-rental'),
            'tuesday'   => esc_html__('Tuesday', 'redq-rental'),
            'wednesday' => esc_html__('Wednesday', 'redq-rental'),
            'thursday'  => esc_html__('Thursday', 'redq-rental'),
        ];
    }

endif;

if (!function_exists('redq_rental_month_names')) :

    /**
     * Month name
     * @return array
     */
    function redq_rental_month_names()
    {
        return [
            'january'   => esc_html__('January', 'redq-rental'),
            'february'  => esc_html__('February', 'redq-rental'),
            'march'     => esc_html__('March', 'redq-rental'),
            'april'     => esc_html__('April', 'redq-rental'),
            'may'       => esc_html__('May', 'redq-rental'),
            'june'      => esc_html__('June', 'redq-rental'),
            'july'      => esc_html__('July', 'redq-rental'),
            'august'    => esc_html__('August', 'redq-rental'),
            'september' => esc_html__('September', 'redq-rental'),
            'october'   => esc_html__('October', 'redq-rental'),
            'november'  => esc_html__('November', 'redq-rental'),
            'december'  => esc_html__('December', 'redq-rental'),
        ];
    }

endif;


if (!function_exists('redq_rental_handle_holidays')) :
    /**
     * Get holidays
     *
     * @param int $product_id
     * @return array
     */
    function redq_rental_handle_holidays($product_id)
{
    $holidays   = [];
    $general    = rnb_get_settings($product_id, 'general', ['holidays']);
    $conditions = rnb_get_settings($product_id, 'conditions', ['date_format']);

    $output_format = $conditions['date_format'] ? $conditions['date_format'] : 'd/m/Y';
    $input_format = 'd/m/Y'; // Assuming this is the input format in 'holidays'. Adjust if necessary.

    $holiday_ranges = $general['holidays'];
    if (empty($holiday_ranges)) {
        return $holidays;
    }

    $ranges = explode(',', $holiday_ranges);

    foreach ($ranges as $range) {
        $parts = explode('to', $range);

        if (count($parts) !== 2) {
            // Log an error or take other actions if the format is incorrect
            error_log("Invalid date range format: $range");
            continue; // Skip this range if format is invalid
        }

        list($start_date, $end_date) = $parts;

        // Trim and parse the dates using Carbon
        $start_date = trim($start_date);
        $end_date = trim($end_date);

        try {
            $start_date = Carbon::createFromFormat($input_format, $start_date);
            $end_date = Carbon::createFromFormat($input_format, $end_date);
        } catch (Exception $e) {
            // Log or handle error if provided dates are in incorrect format
            error_log("Invalid date format: Start: $start_date, End: $end_date");
            continue; // Skip to next range if current range is invalid
        }

        $period = CarbonPeriod::create($start_date, $end_date);

        foreach ($period as $date) {
            $holidays[] = $date->format($output_format);
        }
    }

    return $holidays;
}
endif;


if (!function_exists('redq_rental_global_color_control')) :

    function redq_rental_global_color_control()
    {
        return get_option('rnb_overall_color_display_option');
    }
endif;

if (!function_exists('rnb_format_allow_times')) :
    /**
     * Retrieve allow times
     *
     * @return array
     * @version 6.0.4
     * @since 6.0.4
     */
    function rnb_format_allow_times($allowed_times)
    {
        $formatted_times = [];
        $time_ara = explode(',', $allowed_times);
        if (isset($time_ara) && !empty($time_ara)) {
            foreach ($time_ara as $key => $value) {
                $formatted_times[] = trim($value);
            }
            $formatted_times = array_filter($formatted_times, static function ($value) {
                return $value !== '';
            });
        }
        return $formatted_times;
    }
endif;

function rnb_custom_date_insert($place_holders, $values)
{
    global $wpdb;

    $tablename = $wpdb->prefix . 'rnb_availability';

    $query = "INSERT INTO $tablename (`block_by`, `pickup_datetime`, `return_datetime`, `rental_duration`, `inventory_id`, `product_id`, `order_id`, `item_id`) VALUES ";
    $query .= implode(', ', $place_holders);
    $sql = $wpdb->prepare("$query ", $values);

    if ($wpdb->query($sql)) {
        return true;
    }

    return false;
}

/**
 * rnb_booking_dates_insert
 *
 * @param array $args
 * @return void
 */
function rnb_booking_dates_insert($args = [])
{
    $defaults = [
        'pickup_datetime' => '',
        'return_datetime' => '',
        'rental_duration' => '',
        'product_id'      => '',
        'inventory_id'    => '',
        'order_id'        => '',
        'item_id'         => '',
        'created_at'      => current_time('mysql'),
        'updated_at'      => current_time('mysql'),
        'block_by'        => 'FRONTEND_ORDER',
        'delete_status'   => 1
    ];

    if (!isset($args['pickup_datetime']) && !isset($args['return_datetime'])) {
        return;
    }

    global $wpdb;
    $pickup_datetime = isset($args['pickup_datetime']) ? $args['pickup_datetime'] : $args['return_datetime'];
    $return_datetime = isset($args['return_datetime']) ? $args['return_datetime'] : $args['pickup_datetime'];

    $formatted_args = [
        'pickup_datetime' => date('Y-m-d H:i', strtotime($pickup_datetime)),
        'return_datetime' => date('Y-m-d H:i', strtotime($return_datetime)),
        'rental_duration' => rnb_calculate_date_difference($pickup_datetime, $return_datetime),
    ];

    $args = wp_parse_args($formatted_args, $args);
    $rental_data = wp_parse_args($args, $defaults);

    $tablename = $wpdb->prefix . 'rnb_availability';
    $wpdb->insert($tablename, $rental_data, ['%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']);
}

/**
 * rnb_booking_dates_update
 *
 * @param array $args
 * @return void
 */
function rnb_booking_dates_update($args = [])
{
    global $wpdb;
    $defaults = [
        'product_id' => '',
        'order_id' => '',
        'item_id' => '',
    ];

    $where = wp_parse_args($args, $defaults);

    $updated_data = [
        'updated_at' => current_time('mysql'),
        'delete_status' => 1,
    ];

    $tablename = $wpdb->prefix . 'rnb_availability';
    $wpdb->update($tablename, $updated_data, $where, ['%s', '%d'], ['%d', '%d', '%d']);
}

/**
 * rnb_calculate_date_difference
 *
 * @param datetime $date_1
 * @param datetime $date_2
 * @param string $format
 *
 * @return string
 */
// function rnb_calculate_date_difference($date_1 , $date_2 , $format = '%dD:%hH:%iM' ) {
function rnb_calculate_date_difference($date_1, $date_2, $format = '%yY:%mM:%dD:%hH:%iM')
{
    $datetime1 = date_create($date_1);
    $datetime2 = date_create($date_2);
    $interval = date_diff($datetime1, $datetime2);
    return $interval->format($format);
}

function rnb_format_date_time($date_time)
{
    $edt = explode('T', $date_time);
    $date = $edt[0];
    $time = isset($edt[1]) ? $edt[1] : '';
    $result = strtotime($date . ' ' . $time);
    return date('Y-m-d\TH:i', $result);
}

/**
 * Helper method to check is gdpr enable or not
 *
 * @param $product_id
 *
 * @return bool
 */
function is_gdpr_enable($product_id)
{
    $general = redq_rental_get_settings($product_id, 'general');
    $gdpr_enable = $general['general']['gdpr_enable'];
    $gdpr_page = $general['general']['gdpr_page'];

    return $gdpr_enable === 'yes' && $gdpr_page;
}

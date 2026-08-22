<?php

/**
 * Get other templates (e.g. product attributes) passing attributes and including the file.
 *
 * @access public
 * @param string $template_name
 * @param array $args (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 */
function rnb_get_template($template_name, $args = [], $template_path = '', $default_path = '')
{
    if (!empty($args) && is_array($args)) {
        extract($args);
    }

    $located = rnb_locate_template($template_name, $template_path, $default_path);

    if (!file_exists($located)) {
        _doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $located), '2.1');
        return;
    }

    // Allow 3rd party plugin filter template file from their plugin.
    $located = apply_filters('rnb_get_template', $located, $template_name, $args, $template_path, $default_path);

    do_action('woocommerce_before_template_part', $template_name, $template_path, $located, $args);

    include $located;

    do_action('woocommerce_after_template_part', $template_name, $template_path, $located, $args);
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *        yourtheme        /    $template_path    /    $template_name
 *        yourtheme        /    $template_name
 *        $default_path    /    $template_name
 *
 * @access public
 * @param string $template_name
 * @param string $template_path (default: '')
 * @param string $default_path (default: '')
 * @return string
 */
function rnb_locate_template($template_name, $template_path = '', $default_path = '')
{
    if (!$template_path) {
        $template_path = WC()->template_path();
    }

    if (!$default_path) {
        $default_path = trailingslashit(RNB_PATH) . 'templates/';
    }

    // Look within passed path within the theme - this is priority.
    $template = locate_template(
        [
            trailingslashit($template_path) . $template_name,
            $template_name
        ]
    );

    // Get default template/
    if (!$template || WC_TEMPLATE_DEBUG_MODE) {
        $template = $default_path . $template_name;
    }

    // Return what we found.
    return apply_filters('woocommerce_locate_template', $template, $template_name, $template_path);
}

function rnb_inventory_availability_check($product_id, $inventory_id, $render = 'BLOCKED_DATES_ONLY')
{
    global $wpdb;
    $inventory_id = (int) $inventory_id;

    $booking_data = $wpdb->get_results(
        "select *,
        (select sum(meta_value) from {$wpdb->prefix}woocommerce_order_itemmeta where order_item_id=wr.item_id and meta_key='_qty') as booked,
        (select meta_value from {$wpdb->prefix}postmeta where post_id=wr.inventory_id and meta_key='quantity') as quantity
        from {$wpdb->prefix}rnb_availability as wr where inventory_id='" . $inventory_id . "' AND delete_status='0' AND block_by <>'CUSTOM'",
        ARRAY_A
    );

    if (empty($booking_data)) {
        return [];
    }

    $conditional_data = redq_rental_get_settings($product_id, 'conditions');
    $conditional_data = $conditional_data['conditions'];

    $validation_data = redq_rental_get_settings($product_id, 'validations');
    $validation_data = $validation_data['validations'];
    $opening_closing = $validation_data['openning_closing'];

    $time_interval = (!empty($conditional_data['time_interval'])) ? $conditional_data['time_interval'] : 5;
    $time_format = $conditional_data['time_format'];
    $date_format = $conditional_data['date_format'];

    $filtered_booking_data = array_filter($booking_data, function ($value, $index) use ($inventory_id) {
        return $value['inventory_id'] == $inventory_id && new DateTime() < new DateTime($value['return_datetime']);
    }, ARRAY_FILTER_USE_BOTH);

    $sloted_data = [];

    $first_order = reset($filtered_booking_data);
    $total_inventory = isset($first_order['quantity']) ? $first_order['quantity'] : null;

    if ($total_inventory == null) {
        return [];
    }

    $skip_order = ['wc-checkout-draft', 'wc-failed', 'auto-draft'];
    foreach ($filtered_booking_data as $key => $data) {

        $order_status = get_post_status($data['order_id']);
        if (!$order_status || in_array($order_status, $skip_order)) {
            continue;
        }

        $single_slotted_data = [];
        $begin = new DateTime($data['pickup_datetime']);
        $begin = rnb_adjust_extend_order_minute($begin, $data['item_id'], 1);
        $end = new DateTime($data['return_datetime']);
        // $end = $end->modify('+1 hour');
        $end_interval = $time_interval - 1;
        $end = $end->modify("+{$end_interval} minutes");

        // $interval = new DateInterval('PT1H');
        $interval = new DateInterval("PT{$time_interval}M");
        $daterange = new DatePeriod($begin, $interval, $end);

        foreach ($daterange as $date) {
            // $processed_booked_slot = array('datetime' => $date->format('Y:m:d H:m:s'), 'booked' => $data['booked']);
            if (isset($sloted_data[$date->format("{$date_format} H:i")])) {
                $sloted_data[$date->format("{$date_format} H:i")] += $data['booked'];
            } else {
                $sloted_data[$date->format("{$date_format} H:i")] = $data['booked'];
            }
        }
    }

    $final_booked_date = array_filter($sloted_data, function ($value, $index) use ($total_inventory) {
        return $value >= $total_inventory;
    }, ARRAY_FILTER_USE_BOTH);

    $formatted_blocked_date = [];
    foreach ($final_booked_date as $date => $value) {
        $datetime = explode(' ', $date);
        // if (isset($formatted_blocked_date[$datetime[0]])) {
        $formatted_blocked_date[$datetime[0]][] = $datetime[1];
        // }
    }

    $blocked_date = [];

    if (!empty($conditional_data['allowed_times'])) {
        $time_frame = $conditional_data['allowed_times'];
    } else {
        $time_frame = rnb_get_hours_range($lower = 0, $upper = 86400, $step = $time_interval * 60, $format = 'H:i');
    }

    $allowed_datetime = [];

    if ($time_format == '12-hours') {
        foreach ($time_frame as $key => $time) {
            $time_frame[$key] = date('H:i', strtotime($time));
        }
    }

    foreach ($formatted_blocked_date as $key => $blocked) {
        if (count($blocked) === count($time_frame)) {
            $blocked_date[] = $key;
        } else {
            $allowed_datetime[$key] = array_values(array_diff($time_frame, $blocked));
        }
    }

    foreach ($allowed_datetime as $key => $value) {
        if (empty($value)) {
            $blocked_date[] = $key;
        }
    }

    if ('BLOCKED_DATES_ONLY' == $render) {
        return $blocked_date;
    }

    //Checked for allowed times validation settings
    $validated_datetime = [];
    foreach ($allowed_datetime as $key => $times) {
        $allowed_times = [];
        $day_name      = strtolower(date('D', strtotime($key)));
        $range         = isset($opening_closing[$day_name]) ? $opening_closing[$day_name] : [];

        if (count($range)) {
            foreach ($times as $time_key => $time) {
                if ($time > $range['max'] || $time < $range['min']) {
                    continue;
                }
                $allowed_times[] = $time;
            }
            $validated_datetime[$key] = $allowed_times;
        }

        if (!count($range)) {
            $validated_datetime[$key] = $times;
        }
    }
    //End


    if ($time_format == '12-hours') {
        foreach ($validated_datetime as $key => $datetime) {
            foreach ($datetime as $k => $time) {
                $validated_datetime[$key][$k] = date('g:i a', strtotime($time));
            }
        }
    }

    return $validated_datetime;
}

function rnb_adjust_extend_order_minute($date, $item_id, $min = 1)
{
    $items = wc_get_order_item_meta($item_id, 'rnb_hidden_order_meta', true);
    if (empty($items)) {
        return $date;
    }

    $posted_data = $items['posted_data'];
    if (!isset($posted_data['order_type'])) {
        return $date;
    }

    $order_type = $posted_data['order_type'];
    if ($order_type !== 'extend_order') {
        return $date;
    }

    return rnb_adjust_minute($date, $min = 1);
}


function rnb_adjust_minute($date, $min = 1)
{
    $arg = "PT" . $min . "M";
    $interval = new DateInterval($arg);
    $interval->invert = 1;
    $date->add($interval);

    return $date;
}

function rnb_inventory_quantity_availability_check($frontend)
{
    global $wpdb;

    $inventory_id = intval($frontend['inventory_id']);
    $product_id = intval($frontend['product_id']);

    $filtered_booking_data = $wpdb->get_results(
        "select *,
        (select sum(meta_value) from {$wpdb->prefix}woocommerce_order_itemmeta where order_item_id= wr.item_id and meta_key='_qty') as booked,
        (select meta_value from {$wpdb->prefix}postmeta where post_id= wr.inventory_id and meta_key='quantity') as quantity
        from {$wpdb->prefix}rnb_availability as wr where inventory_id='" . $inventory_id . "' AND delete_status='0'",
        ARRAY_A
    );

    if (empty($filtered_booking_data)) {
        return $frontend['quantity'];
    }

    $conditional_data = redq_rental_get_settings($product_id, 'conditions');
    $conditional_data = $conditional_data['conditions'];
    // $time_interval = (!empty($conditional_data['time_interval'])) ? $conditional_data['time_interval'] : 5;
    $time_interval = 1;

    //For pre & post block dates
    // $frontend['pickup_datetime'] = date("Y-m-d H:i:s", strtotime('-' . $conditional_data['before_block_days'] . ' day', strtotime($frontend['pickup_datetime'])));
    // $frontend['return_datetime'] = date("Y-m-d H:i:s", strtotime('+' . $conditional_data['post_block_days'] . ' day', strtotime($frontend['return_datetime'])));
    //End

    $sloted_data = [];
    $first_order = reset($filtered_booking_data);

    $total_inventory = isset($first_order['quantity']) ? $first_order['quantity'] : null;

    if ($total_inventory == null) {
        return [];
    }

    foreach ($filtered_booking_data as $key => $data) {
        $single_slotted_data = [];
        $begin = new DateTime($data['pickup_datetime']);
        $end = new DateTime($data['return_datetime']);
        // $end = $end->modify('+1 hour');
        $end_interval = $time_interval - 1;
        $end = $end->modify("+{$end_interval} minutes");

        // $interval = new DateInterval('PT1H');
        $interval = new DateInterval("PT{$time_interval}M");
        $daterange = new DatePeriod($begin, $interval, $end);

        foreach ($daterange as $date) {
            $booked_qty = $data['block_by'] === 'CUSTOM' ? $data['quantity'] : $data['booked'];
            if (isset($sloted_data[$date->format('Y:m:d H:i')])) {
                $sloted_data[$date->format('Y:m:d H:i')] += $booked_qty;
            } else {
                $sloted_data[$date->format('Y:m:d H:i')] = $booked_qty;
            }
        }
    }

    $to = new DateTime($frontend['pickup_datetime']);
    $from = new DateTime($frontend['return_datetime']);
    // $from = $from->modify('+1 hour');
    $end_interval = $time_interval - 1;
    $from = $from->modify("+{$end_interval} minutes");

    // $interval = new DateInterval('PT1H');
    $interval = new DateInterval("PT{$time_interval}M");
    $daterange = new DatePeriod($to, $interval, $from);

    $requested_data_slot = [];
    foreach ($daterange as $date) {
        $requested_data_slot[] = $date->format('Y:m:d H:i');
    }

    $final_availability_slot = [];
    foreach ($requested_data_slot as $key => $single_slot) {
        if (isset($sloted_data[$single_slot])) {
            $final_availability_slot[] = $total_inventory - $sloted_data[$single_slot];
        } else {
            $final_availability_slot[] = $total_inventory;
        }
    }

    return count($final_availability_slot) ? min($final_availability_slot) : null;
}

function rnb_get_hours_range($start = 0, $end = 86400, $step = 3600, $format = 'H:i a')
{
    $times = [];
    foreach (range($start, $end, $step) as $timestamp) {
        $hour_mins = gmdate('H:i', $timestamp);
        if (!empty($format)) {
            $times[$hour_mins] = gmdate($format, $timestamp);
        } else {
            $times[$hour_mins] = $hour_mins;
        }
    }
    return $times;
}

function array_key_exists_recursive($key, $array)
{
    if (array_key_exists($key, $array)) {
        return true;
    }
    foreach ($array as $k => $value) {
        if (is_array($value) && array_key_exists_recursive($key, $value)) {
            return true;
        }
    }
    return false;
}

function rnb_get_product_inventory_id($product_id)
{
    global $wpdb;
    $pivot_table = $wpdb->prefix . 'rnb_inventory_product';

    if ($wpdb->get_var("SHOW TABLES LIKE '$pivot_table'") == $pivot_table) {
        $results = $wpdb->get_results($wpdb->prepare("SELECT inventory FROM $pivot_table WHERE product = %d", $product_id), ARRAY_N);

        $inventory_id = [];
        foreach ($results as $r) {
            foreach ($r as $i) {
                $inventory_id[] = $i;
            }
        }

        return $inventory_id;
    }
    return [];
}

/**
 * get woocommerce currency info
 *
 * @return array
 */
function rnb_get_woocommerce_currency_info()
{
    return [
        'symbol'   => get_woocommerce_currency_symbol(),
        'currency' => get_woocommerce_currency(),
        'thousand' => wc_get_price_thousand_separator(),
        'decimal'  => wc_get_price_decimal_separator(),
        'number'   => wc_get_price_decimals(),
        'position' => get_option('woocommerce_currency_pos'),
    ];
}

/**
 * Get translated strings
 *
 * @return array
 */
function rnb_get_translated_strings()
{
    return [
        'invalid_range_notice'     => __('Invalid Date Range', 'redq-rental'),
        'max_day_notice'           => __('Max Rental Days', 'redq-rental'),
        'min_day_notice'           => __('Min Rental Days', 'redq-rental'),
        'quantity_notice'          => __('Quantity is not available', 'redq-rental'),
        'quote_user_name'          => __('User name field is required', 'redq-rental'),
        'quote_password'           => __('Password field is required', 'redq-rental'),
        'quote_first_name'         => __('First name field is required', 'redq-rental'),
        'quote_last_name'          => __('Last name field is required', 'redq-rental'),
        'quote_email'              => __('Quote email is required', 'redq-rental'),
        'quote_phone'              => __('Phone is required', 'redq-rental'),
        'quote_message'            => __('Message is required', 'redq-rental'),
        'positive_days'            => __('No of days must be greater than 1 day', 'redq-rental'),
        'positive_hours'           => __('Total hours must be greater than 0 hours', 'redq-rental'),
        'pickup_loc_required'      => __('Pickup location is required', 'redq-rental'),
        'dropoff_loc_required'     => __('Drop-off location is required', 'redq-rental'),
        'pickup_time_required'     => __('Pickup time is required', 'redq-rental'),
        'dropoff_time_required'    => __('Drop-off time is required', 'redq-rental'),
        'adult_required'           => __('Adults is required', 'redq-rental'),
        'child_required'           => __('Child is required', 'redq-rental'),
        'resource_required'           => __('At least one resource is required', 'redq-rental'),
        'category_required'           => __('At least one category is required', 'redq-rental'),
        'deposit_required'           => __('Deposit is required', 'redq-rental'),
        'email_validation_message' => __('Email Must be valid & required!', 'redq-rental'),
        'phone_validation_message' => __('Phone number must be valid & required!', 'redq-rental'),
        'accept_gdpr_message'      => __('Without accept GDPR, RFQ can’t be operational', 'redq-rental'),
        // Model layout string
        'cancel'                   => __('Cancel', 'redq-rental'),
        'current'                  => __('current step:', 'redq-rental'),
        'pagination'               => __('Pagination', 'redq-rental'),
        'finish'                   => __('Finish Process', 'redq-rental'),
        'next'                     => __('Next', 'redq-rental'),
        'previous'                 => __('Previous', 'redq-rental'),
        'loading'                  => __('Loading ...', 'redq-rental'),
    ];
}

function rnb_get_localize_info($product_id)
{
    $general_data = redq_rental_get_settings($product_id, 'general');
    return [
        'domain'   => $general_data['general']['lang_domain'],
        'months'   => $general_data['general']['months'],
        'weekdays' => $general_data['general']['weekdays']
    ];
}

/**
 * get combined settings data
 *
 * @param int $product_id
 * @return array
 */
function rnb_get_combined_settings_data($product_id)
{
    $rnb_data = [];

    $general_data = redq_rental_get_settings($product_id, 'general');
    $labels = redq_rental_get_settings($product_id, 'labels', ['pickup_location', 'return_location', 'pickup_date', 'return_date', 'resources', 'categories', 'person', 'deposites']);
    $layout_two_labels = redq_rental_get_settings($product_id, 'layout_two', ['inventory', 'datetime', 'location', 'resource', 'person', 'deposit', 'resources']);
    $displays = redq_rental_get_settings($product_id, 'display');
    $conditions = redq_rental_get_settings($product_id, 'conditions');
    $validations = redq_rental_get_settings($product_id, 'validations');

    $rnb_data['settings']['general'] = $general_data['general'];
    $rnb_data['settings']['labels'] = $labels['labels'];
    $rnb_data['settings']['displays'] = $displays['display'];
    $rnb_data['settings']['conditions'] = $conditions['conditions'];
    $rnb_data['settings']['validations'] = $validations['validations'];
    $rnb_data['settings']['layout_two_labels'] = $layout_two_labels;

    return $rnb_data;
}

// /**
//  * rnb_date_between
//  *
//  * @param  mixed $date
//  * @param  mixed $from
//  * @param  mixed $to
//  *
//  * @return boolean
//  */
// function rnb_date_between($StartDate1, $EndDate1, $StartDate2, $EndDate2)
// {
//     $result = false;

//     if (($StartDate1 <= $EndDate2) && ($StartDate2 <= $EndDate1)) {
//         $result = true;
//     }

//     return $result;
// }

function rnb_get_rental_orders_by_dates($start_date, $end_date)
{
    global $wpdb;

    $ids = [];

    if (empty($start_date) || empty($end_date)) {
        return $ids;
    }

    $posts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT posts.ID FROM {$wpdb->prefix}rnb_availability as ra 
            INNER JOIN {$wpdb->prefix}posts AS posts ON ra.order_id = posts.ID
            WHERE ra.pickup_datetime >= %s AND ra.pickup_datetime <= %s",
            $start_date,
            $end_date
        )
    );

    if (empty($posts)) {
        return $ids;
    }

    foreach ($posts as $post) {
        $ids[] = $post->ID;
    }

    return $ids;
}

function rnb_get_products_by_inventory($inventory_id)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'rnb_inventory_product';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (empty($table_exists)) {
        return [
            'success' => false,
            'message' => __('Sorry! rnb_inventory_product table doesn\'t exist', 'redq-rental')
        ];
    }

    $query = $wpdb->prepare("
            SELECT product
            FROM {$wpdb->prefix}rnb_inventory_product
            WHERE inventory = %d
        ", $inventory_id);

    $product_ids = $wpdb->get_col($query);
    if (empty($product_ids)) {
        return [
            'success' => false,
            'message' => __('Sorry! No products found for this inventory', 'redq-rental')
        ];
    }

    return [
        'success' => true,
        'product_ids' => $product_ids
    ];
}

function rnb_get_inventory_by_product($product_id)
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'rnb_inventory_product';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (empty($table_exists)) {
        return [
            'success' => false,
            'message' => __('Sorry! rnb_inventory_product table doesn\'t exist', 'redq-rental')
        ];
    }

    $query = $wpdb->prepare("
            SELECT inventory
            FROM {$wpdb->prefix}rnb_inventory_product
            WHERE product = %d
        ", $product_id);

    $inventories = $wpdb->get_col($query);
    if (empty($inventories)) {
        return [
            'success' => false,
            'message' => __('Sorry! No inventory found for this product', 'redq-rental')
        ];
    }

    return [
        'success' => true,
        'inventories' => $inventories
    ];
}

// function rnb_check_if_terms_exist($term_ids, $single = false)
// {
//     global $wpdb;

//     if (!is_array($term_ids)) {
//         $term_ids = array($term_ids);
//     }

//     $term_ids = array_map('absint', $term_ids);
//     $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));

//     $query = $wpdb->prepare("SELECT term_id FROM {$wpdb->terms} WHERE term_id IN ($placeholders)", $term_ids);

//     $existing_terms = $wpdb->get_col($query);
//     $missing_terms = array_diff($term_ids, $existing_terms);

//     if ($single && count($existing_terms)) {
//         return reset($existing_terms);
//     }

//     return $existing_terms;
// }

function rnb_check_if_terms_exist($term_ids, $single = false, $taxonomy = '')
{
    global $wpdb;

    if (!is_array($term_ids)) {
        $term_ids = array($term_ids);
    }

    $term_ids = array_map('absint', $term_ids);
    $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));

    // Modify the query to join with term_taxonomy table
    $taxonomy_query = '';
    if (!empty($taxonomy)) {
        $taxonomy_query = $wpdb->prepare(" AND tt.taxonomy = %s", $taxonomy);
    }

    $query = $wpdb->prepare("SELECT t.term_id FROM {$wpdb->terms} AS t 
                             INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id 
                             WHERE t.term_id IN ($placeholders) {$taxonomy_query}", $term_ids);

    $existing_terms = $wpdb->get_col($query);
    $missing_terms = array_diff($term_ids, $existing_terms);

    if ($single && count($existing_terms)) {
        return reset($existing_terms);
    }

    return $existing_terms;
}


function rnb_has_order_items($order_id)
{
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT COUNT(order_item_id) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d",
        $order_id
    );

    $count = $wpdb->get_var($query);

    return $count > 0;
}


function rnb_check_order_item_meta_exists($order_id, $meta_key)
{
    global $wpdb;

    // Get the count of all 'line item' type items in the order
    $items_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(order_item_id) FROM {$wpdb->prefix}woocommerce_order_items 
             WHERE order_id = %d AND order_item_type = 'line_item'",
            $order_id
        )
    );

    // Get the count of 'line item' type items that have the specified meta key
    $meta_count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT woim.order_item_id) FROM {$wpdb->prefix}woocommerce_order_itemmeta as woim
            INNER JOIN {$wpdb->prefix}woocommerce_order_items as woi ON woim.order_item_id = woi.order_item_id
            WHERE woi.order_id = %d AND woi.order_item_type = 'line_item' AND woim.meta_key = %s",
            $order_id,
            $meta_key
        )
    );

    // Compare the counts
    return $meta_count == $items_count;
}
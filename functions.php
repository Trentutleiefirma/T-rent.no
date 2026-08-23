<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * is_rental_product
 *
 * @param mixed $product_id
 *
 * @return boolean
 */
function is_rental_product($product_id)
{
    if (is_shop()) {
        return false;
    }

    $is_product = wc_get_product($product_id);
    $product_type = $is_product ? $is_product->get_type() : '';

    return $product_type && $product_type === 'redq_rental' ? true : false;
}

/**
 * Convert Euro To Standard
 *
 * @param $date
 * @param $euro_format
 * @return false|string
 * @since 1.0.0
 */
function rnb_generalized_date_format($date, $euro_format)
{
    $formatted_date = $euro_format === 'no' ? $date : strtotime(str_replace('/', '.', $date));
    return (new Carbon($formatted_date))->toDateString();
}

function rnb_get_duration($start, $end)
{
    $defaults = [
        'duration' => 0,
        'days'     => 0,
        'hours'    => 0,
    ];

    if (empty($start) || empty($end)) {
        return $defaults;
    }

    // T-Rent uses calendar days. Resolve the calendar dates before converting
    // elapsed minutes to hours so a same-day booking can never become 0 hours.
    $start_date = $start->copy()->startOfDay();
    $end_date   = $end->copy()->startOfDay();

    if ($start_date->isSameDay($end_date)) {
        return [
            'duration' => 24,
            'days'     => 1,
            'hours'    => 0,
        ];
    }

    $mins      = $start->floatDiffInRealMinutes($end);
    $durations = $mins / 60;

    // T-Rent counts calendar dates inclusively: 23->24 = 2 days,
    // 23->25 = 3 days. Clock-time duration remains the actual elapsed hours.
    $day  = $start_date->diffInDays($end_date) + 1;
    $hour = (int) ceil($mins % (24 * 60) / 60);

    if ($hour >= 24) {
        $hour = $hour - 24;
    }

    return wp_parse_args([
        'duration' => $durations,
        'days'     => $day,
        'hours'    => $hour
    ], $defaults);
}

/**
 * Default inventory id
 *
 * @return int
 */
function rnb_get_default_inventory_id($product_id = null)
{
    if (empty($product_id)) {
        $product_id = get_the_ID();
    }

    $inventory_id = '';
    $inventory_ids = rnb_get_product_inventory_id($product_id);

    if (!empty($inventory_ids) && is_array($inventory_ids)) {
        $inventory_id = $inventory_ids[0];
    }

    return $inventory_id;
}

/**
 * item meta key to hold all data
 *
 * @return string
 */
function rnb_oder_item_data_key()
{
    return apply_filters('rnb_order_item_data_key', 'rnb_hidden_order_meta');
}

function rnb_format_oc_time($args, $conditions)
{
    $oc_times = $args['openning_closing'];

    if (empty($oc_times)) {
        return $args;
    }

    $formatted   = [];
    $day_to_dow = rnb_get_day_of_dow();
    $timeFormat = $conditions['time_format'] === '24-hours' ? 'H:i' : 'h:ia';

    foreach ($oc_times as $key => $oc_time) {
        $today = Carbon::now()->toDateString();
        $min = (new Carbon($today . $oc_time['min']))->format($timeFormat);
        $max_time =  $oc_time['max'] == '24:00' ? '23:59' : $oc_time['max'];
        $max = (new Carbon($today .  $max_time))->format($timeFormat);
        $new_key = $day_to_dow[$key];
        $formatted[$new_key] = [
            'min' => $min,
            'max' => $max,
        ];
    }

    $args['openning_closing'] = $formatted;

    return $args;
}

/**
 * Mapping date format
 *
 * @return array
 */
function rnb_map_date_format()
{
    return [
        'm/d/Y' => 'mm/dd/yy',
        'd/m/Y' => 'dd/mm/yy',
        'Y/m/d' => 'yy/mm/dd'
    ];
}

/**
 * Get day of week
 *
 * @return array
 */
function rnb_get_day_of_dow()
{
    return [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];
}

/**
 * Default pickup time
 *
 * @return string
 */
function rnb_get_default_pickup_time($product_id, $form_data = [])
{
    global $post;

    if (empty($product_id)) {
        $product_id = $post->ID;
    }

    $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];
    $display = redq_rental_get_settings($product_id, 'display')['display'];

    $offset = (float) get_option('gmt_offset');
    $today = (new Carbon())->addHours($offset);

    $current_date = $today->toDateString();
    $pickup_date = (new Carbon($form_data['pickup_date']))->toDateString();

    if ($current_date !== $pickup_date) {
        return '00:00';
    }

    $slots = [];
    $interval = isset($conditions['time_interval']) && $conditions['time_interval'] ? $conditions['time_interval'] : 30;

    for ($i = 1; $i <= 60; $i++) {
        if ($i % $interval === 0) {
            $slots[]  = $i;
        }
    }

    $hour = $today->hour;

    foreach ($slots as $key => $slot) {
        $min = $slot;
        if ($slot === 60) {
            $hour = $hour + 1;
            $min = 0;
        }
        $custom = "$current_date $hour:$min";
        $customObject = new Carbon($custom);

        if ($customObject->greaterThan($today)) {
            return $customObject->format('H:i');
        }
    }

    return (Carbon::now())->format('H:i');
}

/**
 * Default return time
 *
 * @return string
 */
function rnb_get_default_return_time($product_id, $form_data = [])
{
    global $post;

    if (empty($product_id)) {
        $product_id = $post->ID;
    }

    $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];
    $display = redq_rental_get_settings($product_id, 'display')['display'];

    $offset = (float) get_option('gmt_offset');
    $today = (new Carbon())->addHours($offset);

    $current_date = $today->toDateString();
    $pickup_date = (new Carbon($form_data['pickup_date']))->toDateString();
    $return_date = (new Carbon($form_data['return_date']))->toDateString();

    if ($current_date === $return_date) {
        return '23:59:59';
    }

    if ($pickup_date === $return_date) {
        return '23:59:59';
    }

    if ($pickup_date !== $return_date && $conditions['include_trailing_date'] === 'closed') {
        return '00:00';
    }

    if ($conditions['include_trailing_date'] !== 'closed') {
        return '23:59:59';
    }

    return $today->format('H:i:s');
}

/**
 * Parse weekend into init value
 * 
 * @param  array $weekend
 * @return array
 */
function rnb_format_weekend($weekends)
{
    if (empty($weekends)) {
        return [];
    }

    $results = [];

    foreach ($weekends as $weekend) {
        $results[] = intval($weekend);
    }

    return $results;
}

/**
 * Get times slots
 *
 * @param integer $interval
 * @param string $format
 * @return array
 */
function rnb_get_time_slots($interval = 30, $format = 'H:i')
{
    $period = new CarbonPeriod('00:00', '' . $interval . ' minutes', '24:00');
    $slots  = [];

    foreach ($period as $item) {
        array_push($slots, $item->format($format));
    }

    return $slots;
}

/**
 * Check dates overlapping
 *
 * @param array $args1
 * @param array $args2
 * @return boolean
 */
function rnb_check_dates_overlap($args1, $args2)
{
    $pickup_date = $args1['pickup_date'];
    $pickup_time = $args1['pickup_time'];
    $return_date = $args1['return_date'];
    $return_time = $args1['return_time'];

    $pickup_datetime = $pickup_date . ' ' . $pickup_time;
    $return_datetime = $return_date . ' ' . $return_time;

    $pickup_date_2 = $args2['pickup_date'];
    $pickup_time_2 = $args2['pickup_time'];
    $return_date_2 = $args2['dropoff_date'];
    $return_time_2 = $args2['dropoff_time'];

    $pickup_datetime_2 = $pickup_date_2 . ' ' . $pickup_time_2;
    $return_datetime_2 = $return_date_2 . ' ' . $return_time_2;

    $period = CarbonPeriod::create($pickup_datetime, $return_datetime);
    $period_2 = CarbonPeriod::create($pickup_datetime_2, $return_datetime_2);

    return $period->overlaps($period_2);
}


// add_filter('woocommerce_product_class', 'load_custom_product_class', 10, 2);
function load_custom_product_class($classname, $product_type)
{
    if ($product_type === 'redq_rental') {
        $classname = 'WC_Product_Redq_rental';
    }

    return $classname;
}

if (!function_exists('is_view_quote_page')) {

    /**
     * Is_view_quote_page - Returns true when on the view order page.
     *
     * @return bool
     */
    function is_view_quote_page()
    {
        global $wp;

        $page_id = wc_get_page_id('myaccount');

        return ($page_id && is_page($page_id) && isset($wp->query_vars['view-quote']));
    }
}

if (!function_exists('is_rfq_page')) {

    /**
     * Is_rfq_page - Returns true when on the view order page.
     *
     * @return bool
     */
    function is_rfq_page()
    {
        global $wp;

        $page_id = wc_get_page_id('myaccount');

        return ($page_id && is_page($page_id) && isset($wp->query_vars['view-quote'])) || ($page_id && is_page($page_id) && isset($wp->query_vars['request-quote']));
    }
}

/**
 * Retrieve Instance payment type
 */
function rnb_get_instance_payment_type()
{
    $instance_payment_type = get_option('rnb_instance_payment_type');
    $instance_payment_type = empty($instance_payment_type) ? 'percent' : $instance_payment_type;
    return $instance_payment_type;
}

function rnb_convert_dates_in_common_format($dates)
{
    if (empty($dates)) {
        return [];
    }

    $formatted_dates = array_map(function ($date) {
        return Carbon::parse($date)->format('Y-m-d');
    }, $dates);

    return $formatted_dates;
}

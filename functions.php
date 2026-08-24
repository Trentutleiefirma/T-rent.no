<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;

function is_rental_product($product_id)
{
    if (is_shop()) {
        return false;
    }

    $is_product = wc_get_product($product_id);
    $product_type = $is_product ? $is_product->get_type() : '';

    return $product_type && $product_type === 'redq_rental' ? true : false;
}

function rnb_generalized_date_format($date, $euro_format)
{
    $formatted_date = $euro_format === 'no' ? $date : strtotime(str_replace('/', '.', $date));
    return (new Carbon($formatted_date))->toDateString();
}

/**
 * T-Rent rents by calendar day, not elapsed hours.
 * Same date = 1 rental day. Each additional calendar date adds one rental day.
 * Exception: pickup between 19:00 and 21:00 on the previous calendar date
 * is treated as free evening-before pickup when the booking spans exactly one date boundary.
 */
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

    $start_date = $start->copy()->startOfDay();
    $end_date   = $end->copy()->startOfDay();

    if ($end_date->lt($start_date)) {
        return $defaults;
    }

    $calendar_diff = (int) $start_date->diffInDays($end_date);
    $days = $calendar_diff + 1;

    // Free evening-before pickup applies only to pickup time, only for one date boundary.
    $pickup_time = $start->format('H:i');
    if (
        $calendar_diff === 1 &&
        $pickup_time >= '19:00' &&
        $pickup_time <= '21:00'
    ) {
        $days = 1;
    }

    // RnB expects duration internally in hours.
    $duration = 24 * $days;

    return wp_parse_args([
        'duration' => $duration,
        'days'     => $days,
        'hours'    => 0,
    ], $defaults);
}

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

    $formatted = [];
    $day_to_dow = rnb_get_day_of_dow();
    $timeFormat = $conditions['time_format'] === '24-hours' ? 'H:i' : 'h:ia';

    foreach ($oc_times as $key => $oc_time) {
        $today = Carbon::now()->toDateString();
        $min = (new Carbon($today . $oc_time['min']))->format($timeFormat);
        $max_time = $oc_time['max'] == '24:00' ? '23:59' : $oc_time['max'];
        $max = (new Carbon($today . $max_time))->format($timeFormat);
        $new_key = $day_to_dow[$key];
        $formatted[$new_key] = [
            'min' => $min,
            'max' => $max,
        ];
    }

    $args['openning_closing'] = $formatted;
    return $args;
}

function rnb_map_date_format()
{
    return [
        'm/d/Y' => 'mm/dd/yy',
        'd/m/Y' => 'dd/mm/yy',
        'Y/m/d' => 'yy/mm/dd'
    ];
}

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
 * T-Rent default pickup: opening time for the selected day.
 */
function rnb_get_default_pickup_time($product_id, $form_data = [])
{
    $pickup_date = !empty($form_data['pickup_date']) ? Carbon::parse($form_data['pickup_date']) : Carbon::now();
    $day = (int) $pickup_date->dayOfWeek;

    // Weekdays 08:00, weekends 09:00.
    return in_array($day, [Carbon::SATURDAY, Carbon::SUNDAY], true) ? '09:00' : '08:00';
}

/**
 * T-Rent default return: 20:00 every day.
 */
function rnb_get_default_return_time($product_id, $form_data = [])
{
    return '20:00';
}

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

function rnb_get_time_slots($interval = 30, $format = 'H:i')
{
    $period = new CarbonPeriod('00:00', '' . $interval . ' minutes', '24:00');
    $slots = [];

    foreach ($period as $item) {
        array_push($slots, $item->format($format));
    }

    return $slots;
}

/**
 * Availability is checked using actual pickup/dropoff datetimes.
 * Exact handover is not considered an overlap.
 */
function rnb_check_dates_overlap($args1, $args2)
{
    $pickup_datetime = Carbon::parse($args1['pickup_date'] . ' ' . ($args1['pickup_time'] ?? '00:00'));
    $return_datetime = Carbon::parse($args1['return_date'] . ' ' . ($args1['return_time'] ?? '23:59:59'));

    $pickup_datetime_2 = Carbon::parse($args2['pickup_date'] . ' ' . ($args2['pickup_time'] ?? '00:00'));
    $return_datetime_2 = Carbon::parse($args2['dropoff_date'] . ' ' . ($args2['dropoff_time'] ?? '23:59:59'));

    if ($return_datetime->lte($pickup_datetime) || $return_datetime_2->lte($pickup_datetime_2)) {
        return false;
    }

    return $pickup_datetime->lt($return_datetime_2) && $pickup_datetime_2->lt($return_datetime);
}

function load_custom_product_class($classname, $product_type)
{
    if ($product_type === 'redq_rental') {
        $classname = 'WC_Product_Redq_rental';
    }

    return $classname;
}

if (!function_exists('is_view_quote_page')) {
    function is_view_quote_page()
    {
        global $wp;
        $page_id = wc_get_page_id('myaccount');
        return ($page_id && is_page($page_id) && isset($wp->query_vars['view-quote']));
    }
}

if (!function_exists('is_rfq_page')) {
    function is_rfq_page()
    {
        global $wp;
        $page_id = wc_get_page_id('myaccount');
        return ($page_id && is_page($page_id) && isset($wp->query_vars['view-quote'])) || ($page_id && is_page($page_id) && isset($wp->query_vars['request-quote']));
    }
}

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

    return array_map(function ($date) {
        return Carbon::parse($date)->format('Y-m-d');
    }, $dates);
}

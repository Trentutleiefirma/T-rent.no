<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Handle rental data
 */
trait Period_Trait
{
    /**
     * Get blocks dates by product and inventory
     *
     * @param int $product_id
     * @param int $inventory_id
     * @return array
     */
    public function get_periods($product_id, $inventory_id)
    {
        if (empty($product_id) || empty($inventory_id)) {
            return [];
        }

        $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];

        $cart_dates          = rental_product_in_cart($product_id);
        $starting_block_days = redq_rental_staring_block_days($product_id);
        $holidays            = redq_rental_handle_holidays($product_id);
        $buffer_dates        = array_merge($starting_block_days, $cart_dates, $holidays);
        $availability        = rnb_inventory_availability_check($product_id, $inventory_id);

        $allowed_datetime = rnb_inventory_availability_check($product_id, $inventory_id, 'ALLOWED_DATETIMES_ONLY');

        $custom_dates = $this->handle_custom_block_dates($product_id, $inventory_id, $conditions);
        $availability = count($custom_dates['dates']) ? array_merge($availability, $custom_dates['dates']) : $availability;
        $allowed_datetime = count($custom_dates['time_slots']) ? array_merge($allowed_datetime, $custom_dates['time_slots']) : $allowed_datetime;

        return [
            'availability'     => $availability,
            'allowed_datetime' => $allowed_datetime,
            'buffer_dates'     => $buffer_dates,
        ];
    }

    /**
     * Handle custom block dates
     *
     * @param int $product_id
     * @param int $inventory_id
     * @param array $conditions
     * @return array
     */
    public function handle_custom_block_dates($product_id, $inventory_id, $conditions = [])
    {
        global $wpdb;

        if (empty($product_id) || empty($inventory_id)) {
            return [];
        }

        $ranges = $wpdb->get_results(
            "select * from {$wpdb->prefix}rnb_availability where product_id='" . $product_id . "' AND inventory_id='" . $inventory_id . "' AND block_by='CUSTOM' AND delete_status='0'",
            ARRAY_A
        );

        $dates      = [];
        $time_slots = [];

        $interval    = (int) $conditions['time_interval'];
        $date_format = $conditions['date_format'];
        $time_format = $conditions['time_format'] === '24-hours' ? 'H:i' : 'h:i a';

        foreach ($ranges as $key => $range) {
            $start  = new Carbon($range['pickup_datetime']);
            $return = new Carbon($range['return_datetime']);

            $start_date  = $start->format('Y-m-d');
            $start_time  = $start->format('H:i');

            $return_date = $return->format('Y-m-d');
            $return_time = $return->format('H:i');

            $period = CarbonPeriod::create($start_date, $return_date);
            foreach ($period as $date) {
                $dates[] = $date->format($date_format);
            }

            $period = new CarbonPeriod('00:00', '' . $interval . ' minutes', $start_time);
            foreach ($period as $item) {
                $time_slots[$start->format($date_format)][] = $item->format($time_format);
            }

            $period = new CarbonPeriod($return_time, '' . $interval . ' minutes', '23:59');
            foreach ($period as $item) {
                $time_slots[$return->format($date_format)][] = $item->format($time_format);
            }
        }

        $dates = array_diff($dates, array_keys($time_slots));
        foreach ($time_slots as $date => $slot) {
            if (count($slot) <= 1) {
                $dates[] = $date;
                unset($time_slots[$date]);
            }
        }

        return [
            'dates'      => count($dates) ? array_values($dates) : [],
            'time_slots' => $time_slots,
        ];
    }

    /**
     * Find disabled times
     *
     * @param array $args
     * @return array
     */
    public function rnb_get_disable_times($allow_times, $time_format, $time_interval)
    {
        $results = [];

        if (empty($allow_times)) {
            return $results;
        }

        $time_format   = $time_format === '24-hours' ? 'H:i' : 'g:i a';
        $time_format2  = $time_format === '24-hours' ? 'H:i' : 'h:i a';
        $timeSlots    = rnb_get_time_slots($time_interval, $time_format);

        $allow_slots = [];
        $ara = [];

        foreach ($allow_times as $key => $time) {
            $today = Carbon::now()->toDateString();
            $allow_slots[] = (new Carbon($today . ' ' . $time))->format($time_format);
        }

        $times = array_values(array_diff($timeSlots, $allow_slots));

        foreach ($times as $key => $time) {
            $formatted = Carbon::now()->toDateString();
            $dateTime = new Carbon($formatted . $time);
            $newDateTime = $dateTime->addMinute();
            $ara[] = [
                (new Carbon($formatted . $time))->format($time_format2),
                $newDateTime->format($time_format2)
            ];
        }
        return $ara;
    }
}
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

        /*
         * T-Rent uses a separate visual status list for the storefront calendar.
         * RnB's normal "availability" remains untouched because it also contains
         * time-slot logic. Confirmed rental days and full manual CUSTOM blocks
         * are only used to paint dates red.
         */
        $confirmed_booking_dates = $this->get_confirmed_booking_status_dates(
            $product_id,
            $inventory_id,
            $conditions
        );

        $status_dates = array_values(
            array_unique(
                array_merge(
                    $confirmed_booking_dates,
                    !empty($custom_dates['dates']) ? $custom_dates['dates'] : []
                )
            )
        );

        return [
            'availability'     => $availability,
            'allowed_datetime' => $allowed_datetime,
            'buffer_dates'     => $buffer_dates,
            'status_dates'     => $status_dates,
        ];
    }

    /**
     * T-Rent: dates that belong to confirmed WooCommerce rental bookings.
     *
     * RnB already calculates the actual charged/booked calendar dates and stores
     * them in rnb_hidden_order_meta. Using those dates avoids guessing from
     * pickup/dropoff clock times and preserves T-Rent's evening-before logic.
     *
     * @param int   $product_id
     * @param int   $inventory_id
     * @param array $conditions
     * @return array
     */
    public function get_confirmed_booking_status_dates($product_id, $inventory_id, $conditions = [])
    {
        global $wpdb;

        $product_id   = absint($product_id);
        $inventory_id = absint($inventory_id);

        if (!$product_id || !$inventory_id) {
            return [];
        }

        $date_format = !empty($conditions['date_format'])
            ? $conditions['date_format']
            : 'm/d/Y';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT order_id, item_id
                 FROM {$wpdb->prefix}rnb_availability
                 WHERE product_id = %d
                   AND inventory_id = %d
                   AND block_by <> 'CUSTOM'
                   AND delete_status = 0",
                $product_id,
                $inventory_id
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $confirmed_statuses = apply_filters(
            'trent_rnb_confirmed_calendar_statuses',
            [
                'processing',
                'completed',
                'partially-paid',
            ],
            $product_id,
            $inventory_id
        );

        $dates = [];

        foreach ($rows as $row) {
            $order_id = !empty($row['order_id']) ? absint($row['order_id']) : 0;
            $item_id  = !empty($row['item_id']) ? absint($row['item_id']) : 0;

            if (!$order_id || !$item_id) {
                continue;
            }

            $order = wc_get_order($order_id);
            if (!$order || !in_array($order->get_status(), $confirmed_statuses, true)) {
                continue;
            }

            $rental_data = wc_get_order_item_meta($item_id, 'rnb_hidden_order_meta', true);

            if (!is_array($rental_data) || empty($rental_data)) {
                $rental_data = wc_get_order_item_meta($item_id, '_rnb_hidden_order_meta', true);
            }

            $saved_dates = [];

            if (
                is_array($rental_data) &&
                !empty($rental_data['rental_days_and_costs']['booked_dates']['saved']) &&
                is_array($rental_data['rental_days_and_costs']['booked_dates']['saved'])
            ) {
                $saved_dates = $rental_data['rental_days_and_costs']['booked_dates']['saved'];
            }

            foreach ($saved_dates as $saved_date) {
                try {
                    $date = new Carbon($saved_date);
                    $dates[] = $date->format($date_format);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return array_values(array_unique($dates));
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
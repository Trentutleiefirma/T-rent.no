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
     * T-Rent: dates touched by confirmed WooCommerce rental bookings.
     *
     * Pricing dates can differ from occupied dates when equipment is picked up
     * free of charge the evening before. The availability table records the
     * actual occupied interval and is the source for the red calendar status.
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
                "SELECT DISTINCT order_id, pickup_datetime, return_datetime
                 FROM {$wpdb->prefix}rnb_availability
                 WHERE product_id = %d
                   AND inventory_id = %d
                   AND block_by <> 'CUSTOM'
                   AND delete_status = 0
                   AND return_datetime > %s",
                $product_id,
                $inventory_id,
                wp_date('Y-m-d 00:00:00')
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
        $confirmed_orders = [];

        foreach ($rows as $row) {
            $order_id = !empty($row['order_id']) ? absint($row['order_id']) : 0;

            if (!$order_id) {
                continue;
            }

            if (!array_key_exists($order_id, $confirmed_orders)) {
                $order = wc_get_order($order_id);
                $confirmed_orders[$order_id] = $order && in_array($order->get_status(), $confirmed_statuses, true);
            }

            if (!$confirmed_orders[$order_id]) {
                continue;
            }

            try {
                $pickup = new Carbon($row['pickup_datetime']);
                $return = new Carbon($row['return_datetime']);
                if ($return->lte($pickup)) {
                    continue;
                }

                $first_day = $pickup->copy()->startOfDay();
                $last_day = $return->copy()->startOfDay();
                // A return at midnight frees the equipment for that new day.
                if ($return->format('H:i:s') === '00:00:00') {
                    $last_day->subDay();
                }

                if ($last_day->lt($first_day)) {
                    continue;
                }

                foreach (CarbonPeriod::create($first_day, '1 day', $last_day) as $day) {
                    $dates[] = $day->format($date_format);
                }
            } catch (\Exception $e) {
                continue;
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

<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Handle rental data
 */
trait Data_Trait
{
    /**
     * Rearrange form posted data
     *
     * @param array $formData
     * @return array
     */
    public function rearrange_form_data($formData)
    {
        if (!isset($formData['add-to-cart']) || !isset($formData['booking_inventory']) || !isset($formData['pickup_date'])) {
            return false;
        }

        $product_id = $formData['add-to-cart'];
        $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];

        $formData['product_id'] = $product_id;
        $formData['inventory_id'] = $formData['booking_inventory'];

        if (isset($formData['pickup_date'])) {
            $formData['pickup_date'] = rnb_generalized_date_format($formData['pickup_date'],  $conditions['euro_format']);
            $formData['pickup_date'] = (new Carbon($formData['pickup_date']))->toDateString();
        }

        if (isset($formData['dropoff_date'])) {
            $formData['dropoff_date'] = rnb_generalized_date_format($formData['dropoff_date'],  $conditions['euro_format']);
            $formData['dropoff_date'] = (new Carbon($formData['dropoff_date']))->toDateString();
            $formData['return_date'] = (new Carbon($formData['dropoff_date']))->toDateString();
        }

        if (isset($formData['return_date'])) {
            $formData['return_date'] = rnb_generalized_date_format($formData['return_date'],  $conditions['euro_format']);
            $formData['return_date'] = (new Carbon($formData['return_date']))->toDateString();
            $formData['dropoff_date'] = (new Carbon($formData['return_date']))->toDateString();
        }

        if (!isset($formData['dropoff_date']) || empty($formData['dropoff_date'])) {
            $formData['dropoff_date'] = $formData['pickup_date'];
            $formData['return_date'] = $formData['pickup_date'];
        }

        if (isset($formData['post_type']) && $formData['post_type'] === 'shop_order') {

            if (!isset($formData['pickup_time']) || empty($formData['pickup_time'])) {
                $formData['pickup_time'] = rnb_get_default_pickup_time($product_id, $formData);
            }

            if (!isset($formData['return_time']) || empty($formData['return_time'])) {
                $formData['return_time'] = rnb_get_default_return_time($product_id, $formData);
            }

            $formData['dropoff_time']  = $formData['return_time'];
        } else {

            // if (($formData['pickup_date'] === $formData['return_date']) && $conditions['single_day_booking'] === 'open') {
            //     $formData['pickup_time']  =  rnb_get_default_pickup_time($product_id, $formData);
            //     $formData['dropoff_time'] = rnb_get_default_return_time($product_id, $formData);
            //     $formData['return_time']  = rnb_get_default_return_time($product_id, $formData);
            // }

            if (!isset($formData['pickup_time']) || empty($formData['pickup_time'])) {
                $formData['pickup_time'] = rnb_get_default_pickup_time($product_id, $formData);
            }

            if (!isset($formData['dropoff_time']) || empty($formData['dropoff_time'])) {
                $formData['dropoff_time'] = isset($formData['return_time']) ? $formData['return_time'] : rnb_get_default_return_time($product_id, $formData);
                $formData['return_time'] = isset($formData['return_time']) ? $formData['return_time'] : rnb_get_default_return_time($product_id, $formData);
            }

            if (isset($formData['dropoff_time'])) {
                $formData['return_time'] = $formData['dropoff_time'];
            }

            if (isset($formData['return_time'])) {
                $formData['dropoff_time'] = $formData['return_time'];
            }
        }

        return $formData;
    }



    /**
     * Format blocked dates
     *
     * @param array $dates
     * @param array $options
     * @return array
     */
    public function rnb_format_blocked_date($dates, $options)
    {
        $results = [];

        if (empty($dates)) {
            return $results;
        }

        $dateFormat = $options['settings']['conditions']['date_format'];

        foreach ($dates as $key => $date) {
            $date_obj  = Carbon::createFromFormat($dateFormat, $date);
            $results[] = $date_obj->toDateString();
        }

        return $results;
    }

    /**
     * Format blocked datetime
     *
     * @param array $dates
     * @param array $options
     * @return array
     */
    public function rnb_format_blocked_datetime($dates, $options)
    {
        $results = [];

        if (empty($dates)) {
            return $results;
        }

        $conditions = $options['settings']['conditions'];
        $validations = $options['settings']['validations'];
        $ocTimes = $validations['openning_closing'];

        $dateFormat   = $conditions['date_format'];
        $timeInterval = $conditions['time_interval'];
        $timeFormat   = $conditions['time_format'] === '24-hours' ? 'H:i' : 'g:i a';
        $timeFormat2   = $conditions['time_format'] === '24-hours' ? 'H:i' : 'h:ia';
        $timeSlots    = rnb_get_time_slots($timeInterval, $timeFormat);

        foreach ($dates as $date => $times) {

            $date_obj  = Carbon::createFromFormat($dateFormat, $date);
            $validateOcTimes = $ocTimes[$date_obj->dayOfWeek];

            $formatted = $date_obj->toDateString();
            $times     = array_values(array_diff($timeSlots, $times));
            $ara       = [];

            foreach ($times as $key => $time) {

                $minDateTime = new Carbon($formatted . $validateOcTimes['min']);
                $maxDateTime = new Carbon($formatted . $validateOcTimes['max']);
                $dateTime    = new Carbon($formatted . $time);

                if (!($dateTime->greaterThanOrEqualTo($minDateTime) &&  $dateTime->lessThanOrEqualTo($maxDateTime))) {
                    continue;
                }

                $newDateTime = $dateTime->addMinute();
                $ara[] = [
                    (new Carbon($formatted . $time))->format($timeFormat2),
                    $newDateTime->format($timeFormat2)
                ];
            }

            $results[$formatted] = $ara;
        }

        return $results;
    }

    /**
     * Get inventory by dates
     *
     * @param int $product_id
     * @param array $args
     * @return array
     */
    public function has_inventory_by_date($product_id, $args)
    {
        $pickupPeriod = new Carbon($args['pickup_date'] . $args['pickup_time']);
        $returnPeriod = new Carbon($args['return_date'] . $args['return_time']);

        $duration = rnb_get_duration($pickupPeriod, $returnPeriod);

        if (empty($duration)) {
            return false;
        }

        $inventory_id = $args['booking_inventory'];
        $inventoryArgs = [
            'pickup_datetime' => $pickupPeriod->format('Y-m-d H:i'),
            'return_datetime' => $returnPeriod->format('Y-m-d H:i'),
            'product_id'      => $product_id,
            'inventory_id'    => $inventory_id,
            'quantity'        => get_post_meta($inventory_id, 'quantity', true)
        ];
        $quantity = rnb_inventory_quantity_availability_check($inventoryArgs);

        return apply_filters('rnb_inventory_quantity_by_date', $quantity, $product_id, $args);
    }

    /**
     * Calculate rental duration in details
     *
     * @param int $product_id
     * @param array $args
     * @return array
     */
    public function calculate_rental_duration($product_id, $args)
    {
        $bookedDates = [];
        $count       = 0;

        $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];

        $max_hours_late      = floatval($conditions['max_time_late']);
        $hasSingleDayBooking = $conditions['single_day_booking'];
        $output_format = $conditions['date_format'];

        $pickupPeriod = new Carbon($args['pickup_date'] . $args['pickup_time']);
        $returnPeriod = new Carbon($args['return_date'] . $args['return_time']);
        $duration = rnb_get_duration($pickupPeriod, $returnPeriod);

        $actual_hours = $duration['duration'];
        $totalHours   = ceil($duration['duration']);
        $days         = $duration['days'];
        $hours        = ceil($duration['hours']);

        if (empty($days) && $hasSingleDayBooking === 'open') {
            $totalHours = 24;
            $days       = 1;
            $hours      = 0;
        }

        if ($conditions['pay_extra_hours'] !== 'yes' && $hours > $max_hours_late && $totalHours >= 24) {
            $days += 1;
            $hours = 0;
        }
        if ($days && $hours >= $max_hours_late) {
            $hours = $hours - $max_hours_late;
        }

        // if ($days && $hours > $max_hours_late) {
        //     if($totalHours >= 24){
        //         $days += 1;
        //         $hours = 0;
        //     }else{
        //         $hours -= $max_hours_late;
        //     }
        // }

        while ($count < $days) {
            $start = new Carbon($args['pickup_date'] . $args['pickup_time']);
            $date =  ($start)->addDay($count);
            $bookedDates['formatted'][] = $date->format($output_format);
            $bookedDates['saved'][]     = $date->format('Y-m-d');
            $bookedDates['iso'][]       = strtotime($date);
            $count++;
        }

        return [
            'pickup_period' => $pickupPeriod,
            'return_period' => $returnPeriod,
            'actual_hours'  => $actual_hours,
            'flat_hours'    => $totalHours,
            'days'          => $days,
            'hours'         => $hours,
            'booked_dates'  => $bookedDates
        ];
    }

    /**
     * Get date unit
     *
     * @param array $rental_duration
     * @return string
     */
    public function get_date_unit($rental_duration)
    {
        $date_multiply = 'per_hour';

        if ($rental_duration['days']) {
            $date_multiply = 'per_day';
        }

        return $date_multiply;
    }

    /**
     * Check product quantity in cart
     *
     * @param int $product_id
     * @param array $args
     * @return int
     */
    public function check_product_quantity_in_cart($product_id, $args)
    {
        global $woocommerce;

        $quantity    = 0;
        $cart_items  = $woocommerce->cart->get_cart();

        if (empty($cart_items)) {
            return $quantity;
        }

        foreach ($cart_items as $item) {

            if (empty($item['product_id'])) {
                continue;
            }

            $product_type = wc_get_product($item['product_id'])->get_type();
            if ($product_type !== 'redq_rental') {
                continue;
            }

            $rental_data   = $item['rental_data'];
            if ($args['booking_inventory'] != $rental_data['booking_inventory']) {
                continue;
            }

            $overlap = rnb_check_dates_overlap($args, $rental_data);

            if (empty($overlap)) {
                continue;
            }

            $quantity += $item['quantity'];
        }

        return $quantity;
    }

    /**
     * Get total deposit of a order
     *
     * @param int $order_id
     * @return number
     */
    public function get_deposit_by_order($order_id)
    {
        if (empty($order_id)) {
            return false;
        }

        $order   = wc_get_order($order_id);

        if (empty($order)) {
            return false;
        }

        $items   = $order->get_items();
        $deposit = 0;

        if (isset($items) && empty($items)) {
            return;
        }

        foreach ($items as $item_id => $item) {
            $price_breakdown = wc_get_order_item_meta($item_id, 'rnb_price_breakdown', true);

            if (empty($price_breakdown)) {
                continue;
            }

            if (!isset($price_breakdown['deposit_total'])) {
                continue;
            }

            $price_breakdown = apply_filters('rnb_item_price_breakdown', $price_breakdown, $order_id);
            $deposit_amount = isset($price_breakdown['deposit_total']) ? floatval($price_breakdown['deposit_total']) * $item['quantity'] : 0;
            $deposit += $deposit_amount;
        }

        $deposit = apply_filters('rnb_order_deposit', $deposit);

        return $deposit;
    }
}

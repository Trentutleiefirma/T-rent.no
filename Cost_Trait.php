<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;

/**
 * Cost calculation
 */
trait Cost_Trait
{
    /**
     * Calculate rental cost
     *
     * @param int $productId
     * @param int $inventoryId
     * @param array $durations
     * @param array $args
     * @return array
     */
    public function calculate_rental_cost($productId, $inventoryId, $durations, $args = [], $quantity = '')
    {
        $pricing    = redq_rental_get_pricing_data($inventoryId, $productId);
        $displays   = redq_rental_get_settings($productId, 'display')['display'];
        $conditions = redq_rental_get_settings($productId, 'conditions')['conditions'];

        $options = [
            'conditions' => $conditions
        ];

        $prices = [];

        switch ($pricing['pricing_type']) {
            case 'flat_hours':
                $prices = $this->calculate_flat_hour_price($durations, $pricing, $options);
                break;
            case 'general_pricing':
                $prices = $this->calculate_general_price($durations, $pricing, $options);
                break;
            case 'daily_pricing':
                $prices = $this->calculate_daily_price($durations, $pricing, $options);
                break;
            case 'monthly_pricing':
                $prices = $this->calculate_monthly_price($durations, $pricing, $options);
                break;
            case 'days_range':
                $prices = $this->calculate_day_range_price($durations, $pricing, $options);
                break;
            default:
                break;
        }

        $prices = apply_filters('rnb_rental_duration_costs', $prices, $inventoryId, $productId, $durations, $pricing, $args);
        $extrasPrices = $this->calculate_extras_price($durations, $args);

        $costByDays  = isset($prices['costByDays']) ? floatval($prices['costByDays']) : 0;
        $costByHours = isset($prices['costByHours']) ? floatval($prices['costByHours']) : 0;

        $durationTotal = $costByDays + $costByHours;
        $durationTotal = apply_filters('rnb_duration_total', $durationTotal, $durations, $productId, $inventoryId, $_POST, $prices, $pricing);

        $hasDiscount = $this->has_discount($pricing['price_discount'], $durations);
        $discount    = $this->calculate_discount($durationTotal, $hasDiscount, $displays);
        $discount_total = $discount * (int ) $quantity;
        $grandTotal = ($durationTotal - $discount) + $extrasPrices['nonrefundable_total'] + $extrasPrices['refundable_total'];
        $depositFreeTotal = $grandTotal - $extrasPrices['refundable_total'];
        
        $priceBreakdown = [
            'duration_breakdown' => [
                'hourly' => $costByHours,
                'daily'  => $costByDays,
            ],
            'extras_breakdown'   => $extrasPrices,
            'duration_total'     => $durationTotal,
            'discount_total'     => $discount_total,
            'extras_total'       => $extrasPrices['nonrefundable_total'],
            'deposit_total'      => $extrasPrices['refundable_total'],
            'total'              => $grandTotal,
            'deposit_free_total' => $depositFreeTotal
        ];

        $priceBreakdown = apply_filters('rnb_price_breakdown', $priceBreakdown, $durations, $productId, $inventoryId);

        $cost = $priceBreakdown['deposit_free_total'];
        $instance_payment = $this->handle_instant_payment($priceBreakdown, $displays);
        $instance_payment = apply_filters('rnb_instance_payment', $instance_payment,  $priceBreakdown, $displays);
        $instance_payment_total = $instance_payment * (int) $quantity;
        $due_payment      = $cost - $instance_payment;
        $line_total       = $priceBreakdown['total'] - $due_payment;

        return  apply_filters('rnb_rental_cost_details', [
            'pricing_type'    => $pricing['pricing_type'],
            'flat_hours'      => $durations['flat_hours'],
            'days'            => $durations['days'],
            'hours'           => $durations['hours'],
            'booked_dates'    => $durations['booked_dates'],
            'price_breakdown' => $priceBreakdown,
            'cost'            => $instance_payment,
            'instant_pay'     => $instance_payment_total,
            'due_payment'     => $due_payment,
            'line_total'      => $line_total
        ], $productId, $inventoryId, $args);
    }

    public function handle_instant_payment($prices, $displays)
    {
        $amount = $prices['deposit_free_total'];
        if ($displays['instance_payment'] === 'closed') {
            return $amount;
        }

        $total = intval(get_option('rnb_instance_payment'));
        if (empty($total)) {
            return 0;
        }

        $type = rnb_get_instance_payment_type();
        if ($type === 'fixed') {
            return floatval($total);
        }

        $cost = $prices['deposit_free_total'];
        $amount = ($cost * $total) / 100;

        return $amount;
    }

    /**
     * Flat hour price calculation
     *
     * @param array $durations
     * @param array $pricing
     * @param array $args
     * @return array
     */
    public function calculate_flat_hour_price($durations, $pricing, $args = [])
    {
        $costByHours  = 0;
        $costByDays   = 0;

        $hours = $durations['flat_hours'];
        $costByHours = $this->calculate_hourly_price($hours, $pricing);

        return apply_filters('rnb_flat_hour_duration_prices', [
            'costByHours' => $costByHours,
            'costByDays'  => $costByDays
        ], $pricing, $args);
    }

    /**
     * General price calculation
     *
     * @param array $durations
     * @param array $pricing
     * @param array $options
     * @return array
     */
    public function calculate_general_price($durations, $pricing, $options = [])
    {
        $generalPrice = (float) $pricing['general_pricing'];
        $costByHours  = 0;
        $costByDays   = 0;

        $days  = $durations['days'];
        $hours = ceil($durations['hours']);

        $conditions   = $options['conditions'];
        $payExtraHour = $conditions['pay_extra_hours'];

        if ($days === 0) {
            $costByHours = $this->calculate_hourly_price($hours, $pricing);
        }

        if ($days > 0) {
            $costByDays  = $days * $generalPrice;

            if ($payExtraHour === 'yes' && $hours > 0) {
                $costByHours = $this->calculate_hourly_price($hours, $pricing);
            }
        }

        return apply_filters('rnb_general_duration_prices', [
            'costByHours' => $costByHours,
            'costByDays'  => $costByDays
        ], $pricing, $options);
    }

    /**
     * Daily price calculation
     *
     * @param array $durations
     * @param array $pricing
     * @param array $options
     * @return array
     */
    public function calculate_daily_price($durations, $pricing, $options = [])
    {
        $dailyPricing = $pricing['daily_pricing'];
        $costByHours  = 0;
        $costByDays   = 0;

        $conditions   = $options['conditions'];
        $payExtraHour = $conditions['pay_extra_hours'];

        $days  = $durations['days'];
        $hours = ceil($durations['hours']);
        $dates = $durations['booked_dates']['saved'];

        if ($days === 0) {
            $costByHours = $this->calculate_hourly_price($hours, $pricing);
        }

        if ($days > 0) {
            foreach ($dates as $key => $date) {
                $day = strtolower(Carbon::parse($date)->format('l'));
                $costByDays += isset($dailyPricing[$day]) ? $dailyPricing[$day] : 0;
            }

            if ($payExtraHour === 'yes' && $hours > 0) {
                $costByHours = $this->calculate_hourly_price($hours, $pricing);
            }
        }

        return apply_filters('rnb_daily_duration_prices', [
            'costByHours' => $costByHours,
            'costByDays'  => $costByDays
        ], $pricing, $options);
    }

    /**
     * Monthly price calculation
     *
     * @param array $durations
     * @param array $pricing
     * @param array $options
     * @return array
     */
    public function calculate_monthly_price($durations, $pricing, $options = [])
    {
        $monthlyPricing = $pricing['monthly_pricing'];
        $costByHours  = 0;
        $costByDays   = 0;

        $conditions   = $options['conditions'];
        $payExtraHour = $conditions['pay_extra_hours'];

        $days  = $durations['days'];
        $hours = ceil($durations['hours']);
        $dates = $durations['booked_dates']['saved'];

        if ($days === 0) {
            $costByHours = $this->calculate_hourly_price($hours, $pricing);
        }

        if ($days > 0) {

            foreach ($dates as $key => $date) {
                $month = strtolower(Carbon::parse($date)->format('F'));
                $costByDays += isset($monthlyPricing[$month]) ? $monthlyPricing[$month] : 0;
            }

            if ($payExtraHour === 'yes' && $hours > 0) {
                $costByHours = $this->calculate_hourly_price($hours, $pricing);
            }
        }

        return apply_filters('rnb_monthly_duration_prices', [
            'costByHours' => $costByHours,
            'costByDays'  => $costByDays
        ], $pricing, $options);
    }

    /**
     * Day range price calculation
     *
     * @param array $durations
     * @param array $pricing
     * @param array $options
     * @return array
     */
    public function calculate_day_range_price($durations, $pricing, $options = [])
    {
        $dayRangePricing = $pricing['days_range'];
        $costByHours  = 0;
        $costByDays   = 0;

        $conditions   = $options['conditions'];
        $payExtraHour = $conditions['pay_extra_hours'];

        $days  = $durations['days'];
        $hours = ceil($durations['hours']);
        $dates = $durations['booked_dates']['saved'];

        if ($days === 0) {
            $costByHours = $this->calculate_hourly_price($hours, $pricing);
        }

        if ($days > 0) {
            $priceRange = $this->get_price_range($dayRangePricing, $durations);
            $costByDays += $priceRange['cost_applicable'] === 'fixed' ? $priceRange['range_cost'] : $priceRange['range_cost'] * $days;

            if ($payExtraHour === 'yes' && $hours > 0) {
                $costByHours = $this->calculate_hourly_price($hours, $pricing);
            }
        }

        return apply_filters('rnb_day_range_duration_prices', [
            'costByHours' => $costByHours,
            'costByDays'  => $costByDays
        ], $pricing, $options);
    }

    /**
     * Hourly price calculation
     *
     * @param array $hours
     * @param array $pricing
     * @return array
     */
    public function calculate_hourly_price($hours, $pricing)
    {
        $cost = 0;

        if ($pricing['hourly_pricing_type'] === 'hourly_general') {
            $cost = (int) $hours * (float) $pricing['hourly_general'];
        }

        if ($pricing['hourly_pricing_type'] === 'hourly_range') {

            $priceRanges = isset($pricing['hourly_range']) ? $pricing['hourly_range'] : null;
            $range = $this->has_hour_range($hours, $priceRanges);

            if (empty($range)) {
                return $cost;
            }

            if ($range['cost_applicable'] === 'fixed') {
                $cost = (float) $range['range_cost'];
            } else {
                $cost = (float) $range['range_cost'] * (int) $hours;
            }
        }

        return apply_filters('rnb_hourly_prices', $cost, $pricing);
    }

    /**
     * Check price range
     *
     * @param array $priceRanges
     * @param array $durations
     * @return array|boolean
     */
    public function get_price_range($priceRanges, $durations)
    {
        $days  = $durations['days'];
        foreach ($priceRanges as $key => $priceRange) {
            if ($priceRange['min_days'] <= $days && $priceRange['max_days'] >= $days) {
                return $priceRange;
            }
        }

        return false;
    }

    /**
     * Check for discount
     *
     * @param array $discounts
     * @param array $durations
     * @return array|boolean
     */
    public function has_discount($discounts, $durations)
    {
        $scheme = 0;
        $days   = intval($durations['days']);

        if (!empty($discounts) && is_array($discounts) && (count($discounts) > 0)) {
            foreach ($discounts as $key => $discount) {
                if (intval($discount['min_days']) <= $days && intval($discount['max_days']) >= $days) {
                    return $discount;
                }
            }
        }

        return $scheme;
    }

    /**
     * Check for hour range
     *
     * @param int $hour
     * @param array $ranges
     * @return array|boolean
     */
    public function has_hour_range($hour, $ranges)
    {
        $range = [];

        if (!empty($ranges) && is_array($ranges) && (count($ranges) > 0)) {
            foreach ($ranges as $key => $range) {
                if (intval($range['min_hours']) <= $hour && intval($range['max_hours']) >= $hour) {
                    return $range;
                }
            }
        }

        return $range;
    }

    /**
     * Discount calculation
     *
     * @param float $durationTotal
     * @param [type] $hasDiscount
     * @return float
     */
    public function calculate_discount($durationTotal, $hasDiscount, $displays)
    {
        $discount = 0;
        if($displays['discount'] == 'closed'){
            return $discount;
        }
        if (empty($hasDiscount)) {
            return $discount;
        }

        if ($hasDiscount['discount_type'] === 'percentage') {
            $discount = ($durationTotal * $hasDiscount['discount_amount']) / 100;
        }

        if ($hasDiscount['discount_type'] !== 'percentage') {
            $discount = $hasDiscount['discount_amount'];
        }

        return $discount;
    }

    /**
     * Extras price calculation
     *
     * @param array $durations
     * @param array $args
     * @return array
     */
    public function calculate_extras_price($durations, $args)
    {
        $results = [];
        $days    = (int) $durations['days'];
        $hours   =  ceil($durations['hours']);

        //Location cost
        $pickupLocation = $this->format_location($args['pickup_location']);
        $locationTotal = 0;
        if (!empty($pickupLocation)) {
            $locationTotal += $pickupLocation['cost'];
            $results['details_breakdown']['pickup_location_cost'] = $pickupLocation['cost'];
        }

        $returnLocation = $this->format_location($args['return_location']);
        if (!empty($returnLocation)) {
            $locationTotal += $returnLocation['cost'];
            $results['details_breakdown']['return_location_cost'] = $returnLocation['cost'];
        }

        if (isset($args['distance_cost']) && !empty($args['distance_cost'])) {
            $locationTotal += $args['distance_cost'];
            $results['details_breakdown']['kilometer_cost'] = $args['distance_cost'];
        }

        $results['non_refundable']['location_total'] = $locationTotal;

        //Categories Cost
        $categoryTotal = 0;
        $categories     = $this->format_category($args['categories']);

        foreach ($categories as $key => $category) {
            if ($category['multiply'] === 'per_day') {
                $categoryTotal += $days * $category['cost'] * $category['quantity'];
                $categoryTotal += $hours * $category['hourly_cost'] * $category['quantity'];
            } else {
                $categoryTotal += $category['cost'] * $category['quantity'];;
            }
        }

        $results['details_breakdown']['category_cost'] = $categoryTotal;
        $results['non_refundable']['category_total'] = $categoryTotal;

        //Resource Cost
        $resourceTotal = 0;
        $resources     = $this->format_resource($args['resources']);

        foreach ($resources as $key => $resource) {
            if ($resource['multiply'] === 'per_day') {
                $resourceTotal += $days * $resource['cost'];
                $resourceTotal += $hours * $resource['hourly_cost'];
            } else {
                $resourceTotal += $resource['cost'];
            }
        }

        $results['details_breakdown']['resource_cost'] = $resourceTotal;
        $results['non_refundable']['resource_total'] = $resourceTotal;

        //Person cost
        $adultTotal = 0;
        $adult     = $this->format_person($args['adult']);
        if (!empty($adult)) {
            if ($adult['multiply'] === 'per_day') {
                $adultTotal += $days * $adult['cost'];
                $adultTotal += $hours * $adult['hourly_cost'];
            } else {
                $adultTotal += $adult['cost'];
            }
        }
        $results['details_breakdown']['adult_cost'] = $adultTotal;
        $results['non_refundable']['adult_total'] = $adultTotal;

        $childTotal = 0;
        $child = $this->format_person($args['child']);
        if (!empty($child)) {
            if ($child['multiply'] === 'per_day') {
                $childTotal += $days * $child['cost'];
                $childTotal += $hours * $child['hourly_cost'];
            } else {
                $childTotal += $child['cost'];
            }
        }
        $results['details_breakdown']['child_cost'] = $childTotal;
        $results['non_refundable']['child_total'] = $childTotal;

        //Deposit Cost
        $depositTotal = 0;
        $deposits     = $this->format_deposit($args['deposits']);

        foreach ($deposits as $key => $deposit) {
            if ($deposit['multiply'] === 'per_day') {
                $depositTotal +=  $days *  $deposit['cost'];
                $depositTotal +=  $hours *  $deposit['hourly_cost'];
            } else {
                $depositTotal +=  $deposit['cost'];
            }
        }

        $results['details_breakdown']['deposit_cost'] = $depositTotal;
        $results['refundable']['deposit_total'] = $depositTotal;

        $results['nonrefundable_total'] = isset($results['non_refundable']) ? array_sum(array_values($results['non_refundable'])) : 0;
        $results['refundable_total'] = isset($results['refundable']) ? array_sum(array_values($results['refundable'])) : 0;

        return apply_filters('rnb_extras_price', $results, $durations, $args);
    }

    /**
     * Format location
     *
     * @param string $location
     * @return array
     */
    public function format_location($location)
    {
        if (empty($location)) {
            return [];
        }

        $data = explode('|', $location);

        return [
            'name'   => $data[0],
            'address' => $data[1],
            'cost'    => isset($data[2]) ? (float) $data[2] : 0,
        ];
    }

    /**
     * Format category
     *
     * @param array $categories
     * @return array
     */
    public function format_category($categories)
    {
        $results = [];

        if (empty($categories)) {
            return $results;
        }

        foreach ($categories as $key => $category) {
            $data = explode('|', $category);

            $results[$key] = [
                'name'        => $data[0],
                'cost'        => (float) $data[1],
                'multiply'    => $data[2],
                'hourly_cost' => (float) $data[3],
                'quantity'    => isset($data[4]) ? $data[4] : 1
            ];
        }

        return $results;
    }

    /**
     * Format resource
     *
     * @param array $resources
     * @return array
     */
    public function format_resource($resources)
    {
        $results = [];

        if (empty($resources)) {
            return $results;
        }

        foreach ($resources as $key => $resource) {
            $data = explode('|', $resource);

            $results[$key] = [
                'name'        => $data[0],
                'cost'        => (float) $data[1],
                'multiply'    => $data[2],
                'hourly_cost' => (float) $data[3],
            ];
        }

        return $results;
    }

    /**
     * Format person
     *
     * @param string $person
     * @return array
     */
    public function format_person($person)
    {
        if (empty($person)) {
            return [];
        }

        $data = explode('|', $person);

        return [
            'name'       => $data[0],
            'cost'        => (float) $data[1],
            'multiply'    => $data[2],
            'hourly_cost' => (float) $data[3],
        ];
    }

    /**
     * Format deposit
     *
     * @param array $deposits
     * @return array
     */
    public function format_deposit($deposits)
    {
        $results = [];

        if (empty($deposits)) {
            return $results;
        }

        foreach ($deposits as $key => $resource) {
            $data = explode('|', $resource);

            $results[$key] = [
                'name'        => $data[0],
                'cost'        => (float) $data[1],
                'multiply'    => $data[2],
                'hourly_cost' => (float) $data[3],
            ];
        }

        return $results;
    }
}

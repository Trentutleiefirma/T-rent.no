<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

trait Rental_Data_Trait
{

    public $instant_pay = null;

    public function get_rental_data_by_order_id($order_id)
    {
        $results = [];

        if (empty($order_id)) {
            return $results;
        }

        $order = wc_get_order($order_id);

        $results['order_details'] = $order->get_data();
        $results['order_details']['customer_name'] = rnb_customer_name($order);

        $pickup_period = '';
        $return_period = '';
        $duration      = '';
        $deposit       = 0;
        $discount      = 0;
        $extras        = 0;
        $total         = 0;
        $deposit_fee_total = 0;

        foreach ($order->get_items() as $item_id => $item) {

            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();

            $results['item_details'][$item_id] = [
                'item_data'      => $item->get_data(),
                'formatted_data' => $item->get_all_formatted_meta_data(),
            ];

            $rental_data = $item->get_meta('rnb_hidden_order_meta', true);
            if (empty($rental_data)) {
                continue;
            }
            $rental_meta = $this->format_rental_item_data($product_id, $rental_data, $quantity);



            $results['item_details'][$item_id]['rental_meta'] = $rental_meta;
            $results['item_details'][$item_id]['rental_data'] = $rental_data;

            $pickup_period .= isset($rental_meta['pickup_datetime']['data']) ? $rental_meta['pickup_datetime']['data']['name'] : '';
            $return_period .=  isset($rental_meta['return_datetime']['data']) ? $rental_meta['return_datetime']['data']['name'] : '';
            $duration .= isset($rental_meta['duration']['data']) ? $rental_meta['duration']['data']['name'] : '';

            $rdc = $rental_data['rental_days_and_costs'];

            $price_breakdown = $rdc['price_breakdown'];

            $deposit += $price_breakdown['deposit_total'];
            $discount += $price_breakdown['discount_total'];
            $extras += $price_breakdown['extras_total'];
            $total += $price_breakdown['total'];
            $deposit_fee_total += $price_breakdown['deposit_free_total'];
        }

        $results['order_details']['pickup_period'] = $pickup_period;
        $results['order_details']['return_period'] = $return_period;
        $results['order_details']['duration'] = $duration;
        $results['order_details']['deposit'] = $deposit;
        $results['order_details']['extras'] = $extras;
        $results['order_details']['total'] = $total;
        $results['order_details']['deposit_fee_total'] = $deposit_fee_total;

        return $results;
    }

    public function format_rental_item_data($product_id, $data, $quantity)
    {
        $results = [];

        if (!isset($data['product_id'])) {
            $data['product_id'] = $product_id;
        }

        $general = redq_rental_get_settings($product_id, 'general')['general'];
        $labels = redq_rental_get_settings($product_id, 'labels', ['pickup_location', 'return_location', 'pickup_date', 'return_date', 'resources', 'categories', 'person', 'deposites', 'inventory'])['labels'];
        $displays = redq_rental_get_settings($product_id, 'display')['display'];
        $conditions = redq_rental_get_settings($product_id, 'conditions')['conditions'];

        $settings = [
            'general'    => $general,
            'labels'     => $labels,
            'displays'   => $displays,
            'conditions' => $conditions,
        ];

        $results['inventory']          = $this->prepare_inventory_data($data, $settings);
        $results['pickup_datetime']    = $this->prepare_pickup_datetime_data($data, $settings);
        $results['return_datetime']    = $this->prepare_return_datetime_data($data, $settings);
        $results['quote_data']         = $this->prepare_quote($data, $settings);
        $results['total_hour']         = $this->prepare_total_hour($data, $settings);
        $results['duration']           = $this->prepare_duration_data($data, $settings, $quantity);
        $results['discount_total']     = $this->prepare_discount_total_data($data, $settings);
        $results['pickup_location']    = $this->prepare_pickup_location_data($data, $settings, $quantity);
        $results['return_location']    = $this->prepare_return_location_data($data, $settings, $quantity);
        $results['category']           = $this->prepare_category_data($data, $settings, $quantity);
        $results['resource']           = $this->prepare_resource_data($data, $settings, $quantity);
        $results['adult']              = $this->prepare_adult_data($data, $settings, $quantity);
        $results['child']              = $this->prepare_child_data($data, $settings, $quantity);
        $results['extras_total']       = $this->prepare_extras_total_data($data, $settings);
        $results['deposit_free_total'] = $this->prepare_deposit_free_total_data($data, $settings, $quantity);
        $results['instant_pay_amount'] = $this->prepare_instant_pay_amount($data, $settings, $quantity);
        // $results['pay_during_booking'] = $this->prepare_pay_during_booking($data, $settings, $quantity);
        $results['deposit']            = $this->prepare_deposit_data($data, $settings, $quantity);
        $results['total']              = $this->prepare_total_data($data, $settings, $quantity);
        $results['due_payment']        = $this->prepare_payment_due_data($data, $settings, $quantity);

        foreach ($results as $key => $result) {
            if (empty($result)) {
                continue;
            }
            $result['meta_key'] = $key;
            $results[$key] = $result;
        }

        return apply_filters('rnb_format_rental_item_data', $results, $product_id, $data, $settings);
    }

    public function prepare_pay_during_booking($data, $settings, $quantity)
    {
        if ($this->instant_pay) {
            $instant_pay    = $this->instant_pay;
            $price_summary  = $this->get_price_summary_from_rental_data($data);
            $general        = $settings['general'];
            $key            = $general['initial_amount'] ? $general['initial_amount'] : esc_html__('Initial Value', 'redq-rental');
            $deposit_free_total = isset($price_summary['deposit_free_total']) ? $price_summary['deposit_free_total'] : 0;
            $deposit_free_total = (float) $deposit_free_total;
            $initial_amount = ($instant_pay * $deposit_free_total) / 100;

            $instance_payment_type = rnb_get_instance_payment_type();

            $results = [
                'key'          => $key,
                'summary'      => ('percent' == $instance_payment_type) ? true : false,
                'summary_key'  => $key,
                'type'         => 'single',
                'total'        => $initial_amount,
                'data'         => [
                    'name' => $key,
                    'cost' => $initial_amount
                ],
            ];

            return apply_filters('rnb_prepare_pay_during_booking', $results, $data, $settings);
        }

        return [];
    }

    public function prepare_instant_pay_amount($data, $settings, $quantity)
    {
        $display = rnb_get_settings($data['product_id'], 'display', ['instance_payment']);
        if ($display['instance_payment'] !== 'open') {
            return [];
        }

        $general = rnb_get_settings($data['product_id'], 'general', ['instance_pay_type', 'instance_pay', 'instant_pay_amount']);
        $type = $general['instance_pay_type'];
        $key = $general['instant_pay_amount'];
        if ('percent' == $type) {
            $key = sprintf('%s [ %s%% ]', $general['instant_pay_amount'], $general['instance_pay']);
        }        
        $amount = $data['rental_days_and_costs']['instant_pay'];
        $this->instant_pay = (float) $amount;

        $results = [
            'settings_key' => ('percent' == $type) ? 'instant_pay_amount' : '',
            'instance_payment_type' => $type,
            'key'          => $key,
            'summary'      => true,
            'summary_key'  => $key,
            'type'         => 'single',
            'total'        => $amount,
            'data'         => [
                'name' => $key,
                'cost' => $amount,
            ],
        ];

        return apply_filters('rnb_prepare_instant_pay_amount', $results, $data, $settings);
    }

    public function format_hidden_rental_item_data($product_id, $data)
    {
        $results = [];
        $displays = redq_rental_get_settings($product_id, 'display')['display'];

        $results['_booking_inventory'] = [
            'key' => 'booking_inventory',
            'value' => $data['booking_inventory'],
        ];

        $results['_rnb_price_breakdown'] = [
            'key' => 'rnb_price_breakdown',
            'value' => $data['rental_days_and_costs']['price_breakdown'],
        ];

        $hidden_key = function_exists('rnb_oder_item_data_key') ? rnb_oder_item_data_key() :  'rnb_hidden_order_meta';
        $results['_' . $hidden_key] = [
            'key' => $hidden_key,
            'value' => $data,
        ];

        if (isset($data['pickup_date']) && $displays['pickup_date'] === 'open') {
            if (isset($data['pickup_time']) && $displays['pickup_time'] !== 'closed') {
                $ptime = $data['pickup_time'];
            } else {
                $ptime = '00:00';
            }
            $results['_pickup_hidden_datetime'] = [
                'key' => '_pickup_hidden_datetime',
                'value' =>  $data['pickup_date'] . '|' . $ptime,
            ];
        }

        if ((isset($data['dropoff_date']) && $displays['return_date'] === 'open') || (isset($data['dropoff_time']) && $displays['return_time'] === 'open')) {
            if (isset($data['dropoff_time']) && $displays['return_time'] !== 'closed') {
                $rtime = $data['dropoff_time'];
            } else {
                $rtime = '23:00';
            }
            $results['_return_hidden_datetime'] = [
                'key' => '_return_hidden_datetime',
                'value' =>  $data['dropoff_date'] . '|' . $rtime,
            ];
        }

        if ($data['rental_days_and_costs']['days'] > 0 && $data['rental_days_and_costs']['pricing_type'] !== 'flat_hours') {
            $results['_return_hidden_days'] = [
                'key' => '_return_hidden_days',
                'value' =>   $data['rental_days_and_costs']['days'],
            ];
        }

        return apply_filters('rnb_format_hidden_rental_item_data', $results, $product_id, $data);
    }

    public function prepare_inventory_data($data, $settings)
    {
        if (!(isset($data['booking_inventory']) && !empty($data['booking_inventory']))) {
            return [];
        }

        $labels = $settings['labels'];

        $results = [
            'type' => 'single',
            'summary' => false,
            'key'    => $labels['inventory'],
            'data'   => [
                'name' => get_the_title($data['booking_inventory'])
            ],
        ];

        return apply_filters('rnb_prepare_inventory_data', $results, $data, $settings);
    }

    public function prepare_pickup_datetime_data($data, $settings)
    {
        $displays = $settings['displays'];
        if (!isset($data['pickup_date']) && $displays['pickup_date'] === 'open') {
            return [];
        }

        $conditions = $settings['conditions'];
        $labels = $settings['labels'];

        $value = convert_to_output_format($data['pickup_date'], $conditions['date_format']);
        if (isset($data['pickup_time']) && $displays['pickup_time'] !== 'closed') {
            $value .= ' ' . esc_html__('at', 'redq-rental') . ' ' . $data['pickup_time'];
        }

        $results = [
            'type' => 'single',
            'summary' => false,
            'key'   => $labels['pickup_datetime'],
            'data' =>   [
                'name' => $value
            ],
        ];

        return apply_filters('rnb_prepare_pickup_datetime_data', $results, $data, $settings);
    }

    public function prepare_return_datetime_data($data, $settings)
    {
        $displays = $settings['displays'];
        if (!((isset($data['dropoff_date']) && $displays['return_date'] === 'open') || (isset($data['dropoff_time']) && $displays['return_time'] === 'open'))) {
            return [];
        }

        $conditions = $settings['conditions'];
        $labels = $settings['labels'];

        $value = convert_to_output_format($data['dropoff_date'], $conditions['date_format']);
        if (isset($data['dropoff_time']) && $displays['return_time'] !== 'closed') {
            $value .= ' ' . esc_html__('at', 'redq-rental') . ' ' . $data['dropoff_time'];
        }

        $results = [
            'type'    => 'single',
            'summary' => false,
            'key'     => $labels['return_datetime'],
            'data' =>   [
                'name' => $value
            ],
        ];

        return apply_filters('rnb_prepare_return_datetime_data', $results, $data, $settings);
    }

    public function prepare_quote($data, $settings)
    {
        if (!isset($data['quote_id']) && !empty($data['quote_id'])) {
            return [];
        }

        $results = [
            'type' => 'single',
            'summary' => false,
            'key'   =>  __('Quote Request', 'redq-rental'),
            'data' =>   [
                'name' => $data['quote_id']
            ],
        ];

        return apply_filters('rnb_prepare_quote_data', $results, $data, $settings);
    }

    public function prepare_total_hour($data, $settings)
    {
        $general = $settings['general'];

        if ($data['rental_days_and_costs']['pricing_type'] === 'flat_hours') {
            $results = [
                'type' => 'single',
                'summary' => false,
                'key'   => $general['total_hours'] ? $general['total_hours'] : esc_html__('Total Hours', 'redq-rental'),
                'data' =>   [
                    'name' => $data['rental_days_and_costs']['flat_hours']
                ],
            ];
            return apply_filters('rnb_prepare_total_hours_data', $results, $data, $settings);
        }

        if ($data['rental_days_and_costs']['days'] <= 0 && $data['rental_days_and_costs']['pricing_type'] !== 'flat_hours') {
            $results = [
                'type' => 'single',
                'summary' => false,
                'key'  => $general['total_hours'] ? $general['total_hours'] : esc_html__('Total Hours', 'redq-rental'),
                'data' => [
                    'name' => $data['rental_days_and_costs']['hours']
                ],
            ];
            return apply_filters('rnb_prepare_total_hours_data', $results, $data, $settings);
        }

        return [];
    }

    public function prepare_duration_data($data, $settings, $quantity = 1)
    {
        $duration      = '';
        $general       = $settings['general'];
        $breakdown     = $data['rental_days_and_costs'];
        $price_summary = $this->get_price_summary_from_rental_data($data);

        if ($breakdown['days']) {
            $duration .= sprintf(
                _n('%s day ', '%s days ', $breakdown['days'], 'redq-rental'),
                $breakdown['days']
            );
        }
        
        if ($breakdown['hours']) {
            $duration .= sprintf(
                _n('%s hour ', '%s hours ', $breakdown['hours'], 'redq-rental'),
                $breakdown['hours']
            );
        }

        $results = [
            'type' => 'single',
            'summary' => true,
            'key' => $general['total_days'] ? $general['total_days'] : esc_html__('Total Days', 'redq-rental'),
            'summary_key' =>  $general['duration_cost'] . ' [ ' . $duration . ']',
            'total' => $price_summary['duration_total'] * $quantity,
            'data' => [
                'name' => $duration,
                'cost' => $price_summary['duration_total'],
            ]
        ];

        return apply_filters('rnb_prepare_duration_data', $results, $data, $settings);
    }

    public function prepare_pickup_location_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['pickup_location']) || empty($data['pickup_location'])) {
            return [];
        }

        $price_summary = $this->get_price_summary_from_rental_data($data);
        $labels = $settings['labels'];
        $general = $settings['general'];
        $details = $data['pickup_location']['address'];
        $cost = isset($data['pickup_location']) && !empty($data['pickup_location']['cost']) ? $data['pickup_location']['cost'] : 0;
        $total = isset($price_summary['pickup_location_cost']) ? $price_summary['pickup_location_cost'] : 0;

        $results = [
            'type' => 'single',
            'summary' => true,
            'key'  => $labels['pickup_location'],
            'summary_key' => $general['pickup_location_cost'],
            'total' => $total * $quantity,
            'data' => [
                'name' => $details,
                'cost' => $cost,
            ],
        ];

        return apply_filters('rnb_prepare_pickup_location_data', $results, $data, $settings);
    }

    public function prepare_return_location_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['return_location']) || empty($data['return_location'])) {
            return [];
        }

        $price_summary = $this->get_price_summary_from_rental_data($data);
        $labels = $settings['labels'];
        $general = $settings['general'];
        $details = $data['return_location']['address'];
        $cost = isset($data['return_location']) && !empty($data['return_location']['cost']) ? $data['return_location']['cost'] : 0;
        $total = isset($price_summary['return_location_cost']) ? $price_summary['return_location_cost'] : 0;

        $results = [
            'type' => 'single',
            'summary' => true,
            'key'  => $labels['return_location'],
            'summary_key' => $general['return_location_cost'],
            'total' => $total * $quantity,
            'data' => [
                'name' => $details,
                'cost' => $cost,
            ],
        ];

        return apply_filters('rnb_prepare_return_location_data', $results, $data, $settings);
    }

    public function prepare_category_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['payable_cat']) || empty($data['payable_cat'])) {
            return [];
        }

        $labels = $settings['labels'];
        $general = $settings['general'];
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['category_cost']) ? $price_summary['category_cost'] : 0;

        $results = [
            'type'        => 'multiple',
            'summary'     => true,
            'key'         => $labels['categories'],
            'summary_key' => $general['category_cost'],
            'total'       => $total * $quantity,
            'data'        => $data['payable_cat'],
        ];

        return apply_filters('rnb_prepare_category_data', $results, $data, $settings);
    }

    public function prepare_resource_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['payable_resource']) || empty($data['payable_resource'])) {
            return [];
        }

        $labels = $settings['labels'];
        $general = $settings['general'];
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['resource_cost']) ? $price_summary['resource_cost'] : 0;

        $results = [
            'type'        => 'multiple',
            'summary'     => true,
            'key'         => $labels['resource'],
            'summary_key' => $general['resource_cost'],
            'total'       => $total * $quantity,
            'data'        => $data['payable_resource'],
        ];

        return apply_filters('rnb_prepare_resource_data', $results, $data, $settings);
    }

    public function prepare_deposit_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['payable_security_deposites']) || empty($data['payable_security_deposites'])) {
            return [];
        }

        $labels = $settings['labels'];
        $general = $settings['general'];
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['deposit_total']) ? $price_summary['deposit_total'] : 0;

        $results = [
            'type'        => 'multiple',
            'summary'     => true,
            'key'         => $labels['deposite'],
            'summary_key' => $general['deposit_amount'],
            'total'       => $total * $quantity,
            'data'        => $data['payable_security_deposites'],
        ];

        return apply_filters('rnb_prepare_deposit_data', $results, $data, $settings);
    }

    public function prepare_adult_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['adults_info']) || empty($data['adults_info'])) {
            return [];
        }

        $labels = $settings['labels'];
        $general = $settings['general'];
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['adult_cost']) ? $price_summary['adult_cost'] : 0;

        $results = [
            'type'        => 'single',
            'summary'     => true,
            'key'         => $labels['adults'],
            'summary_key' => $general['adult_cost'],
            'total'       => $total * $quantity,
            'data'        => $data['adults_info'],
        ];

        return apply_filters('rnb_prepare_adult_data', $results, $data, $settings);
    }

    public function prepare_child_data($data, $settings, $quantity = 1)
    {
        if (!isset($data['childs_info']) || empty($data['childs_info'])) {
            return [];
        }

        $labels = $settings['labels'];
        $general = $settings['general'];
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['child_cost']) ? $price_summary['child_cost'] : 0;

        $results = [
            'type'        => 'single',
            'summary'     => true,
            'key'         => $labels['childs'],
            'summary_key' => $general['child_cost'],
            'total'       => $total * $quantity,
            'data'        => $data['childs_info'],
        ];

        return apply_filters('rnb_prepare_child_data', $results, $data, $settings);
    }

    public function prepare_payment_due_data($data, $settings, $quantity)
    {
        if (!empty($data['rental_days_and_costs']['due_payment'])) {
            $due = $data['rental_days_and_costs']['due_payment'];
            $general = $settings['general'];
            $key   = $general['payment_due'] ? $general['payment_due'] : esc_html__('Due Payment', 'redq-rental');
            $total = $due * $quantity;
            $results = [
                'key'         => $key,
                'summary'     => true,
                'summary_key' => $key,
                'type'        => 'single',
                'total'       => $total,
                'data'        => [
                    'name' => $key,
                    'cost' => $total,
                ],
            ];

            return apply_filters('rnb_prepare_payment_due_data', $results, $data, $settings);
        }

        return [];
    }

    public function prepare_discount_total_data($data, $settings)
    {
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['discount_total']) ? $price_summary['discount_total'] : 0;
        $general = $settings['general'];
        $key = $general['discount_amount'] ? $general['discount_amount'] : '';

        $results = [
            'type' => 'single',
            'summary' => true,
            'key' => $key,
            'summary_key' => $key,
            'total' => $total,
            'data'   => [
                'name' => $key,
                'cost' => $total,
            ],
        ];

        return apply_filters('rnb_prepare_discount_total_data', $results, $data, $settings);
    }

    public function prepare_extras_total_data($data, $settings)
    {
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['extras_total']) ? $price_summary['extras_total'] : 0;
        $general = $settings['general'];
        $key = esc_html__('Extras Total', 'redq-rental');

        $results = [
            'type' => 'single',
            'summary' => true,
            'key' => $key,
            'summary_key' => $key,
            'total' => $total,
            'data'   => [
                'name' => $key,
                'cost' => $total,
            ],
        ];

        return apply_filters('rnb_prepare_extras_total_data', $results, $data, $settings);
    }

    public function prepare_deposit_free_total_data($data, $settings, $quantity = 1)
    {
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['deposit_free_total']) ? $price_summary['deposit_free_total'] : 0;
        $general = $settings['general'];
        $key = $general['subtotal_amount'] ? $general['subtotal_amount'] : esc_html__('Subtotal Total', 'redq-rental');

        $results = [
            'type'        => 'single',
            'summary'     => true,
            'key'         => $key,
            'summary_key' => $key,
            'total'       => $total * $quantity,
            'data'        => [
                'name' => $key,
                'cost' => $total,
            ],
        ];

        return apply_filters('rnb_prepare_deposit_free_total_data', $results, $data, $settings);
    }

    public function prepare_total_data($data, $settings, $quantity = 1)
    {
        $price_summary = $this->get_price_summary_from_rental_data($data);
        $total = isset($price_summary['total']) ? $price_summary['total'] : 0;
        $general = $settings['general'];
        $key = $general['grand_total_amount'] ? $general['grand_total_amount'] : esc_html__('Total', 'redq-rental');

        $results = [
            'type'        => 'single',
            'summary'     => true,
            'key'         => $key,
            'summary_key' => $key,
            'total'       => $total * $quantity,
            'data'   => [
                'name' => $key,
                'cost' => $total,
            ],
        ];

        return apply_filters('rnb_prepare_total_data', $results, $data, $settings);
    }

    public function format_single_item_value($data)
    {
        $value = '';

        if (isset($data['name']) && !empty($data['name'])) {
            $value .= $data['name'];
        }

        if (isset($data['cost']) && $data['cost'] > 0) {
            if (is_numeric($data['cost'])) {
                $value .= !isset($data['multiply']) ? ' (' . wc_price($data['cost']) . ')' : ' (' . wc_price($data['cost']) . '-' .   $this->unit_mapping($data['multiply']) . ')';
            } else {
                $value .= ' (' . $data['cost'] . ')';
            }
        }

        return $value;
    }

    public function format_multiple_item_value($data)
    {
        $value = '';

        foreach ($data as $key => $item) {
            $value .= $item['name'];
            if (isset($item['cost']) && $item['cost'] > 0) {
                if (isset($item['quantity']) && $item['quantity'] > 1) {
                    $value .= ' × ' . $item['quantity'];
                }
                $value .= ' ( ' . wc_price($item['cost']) . '-' .   $this->unit_mapping($item['multiply']) . ')';
            }

            if ($key < count($data) - 1) {
                $value .= ', </br>';
            }
        }

        return $value;
    }

    public function unit_mapping($key)
    {
        $map = [
            'per_day'  => __('Per Day', 'redq-rental'),
            'one_time' => __('One Time', 'redq-rental'),
            'per_hour' => __('Per Hour', 'redq-rental'),
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        return null;
    }

    public function get_price_summary_from_rental_data($data)
    {
        $summary = [];
        $prices = $data['rental_days_and_costs']['price_breakdown'];

        $summary = $prices['extras_breakdown']['details_breakdown'];
        $summary['duration_total'] = $prices['duration_total'];
        $summary['discount_total'] = $prices['discount_total'];
        $summary['extras_total'] = $prices['extras_total'];
        $summary['deposit_total'] = $prices['deposit_total'];
        $summary['deposit_free_total'] = $prices['deposit_free_total'];
        $summary['total'] = $prices['total'];

        return $summary;
    }
}

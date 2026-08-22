<?php
function rnb_get_settings($product_id, $type, $posted_keys = [])
{
    $results      = [];
    $is_local     = get_post_meta($product_id, "rnb_settings_for_{$type}", true) === 'local';
    $settings_map = rnb_get_settings_map($type);

    foreach ($settings_map as $common_key => $keys) {
        if (count($posted_keys) && !in_array($common_key, $posted_keys)) {
            continue;
        }

        $local_key = $keys['local'];
        $global_key = $keys['global'];
        $mapping = isset($keys['mapping']) ? $keys['mapping'] : [];
        $default_value = isset($keys['default']) ? $keys['default'] : '';

        if ($is_local && !empty($local_key)) {
            $value = get_post_meta($product_id, $local_key, true);
            $value = (!empty($value)) ? $value : $default_value;
        } else if (!empty($global_key)) {
            $value = get_option($global_key);
            $value = ($value !== false) ? $value : $default_value;
        } else {
            $value = $default_value;
        }

        // Apply mapping if exists and value matches a mapping key
        if (!empty($mapping) && array_key_exists($value, $mapping)) {
            $value = $mapping[$value];
        }

        $results[$common_key] = $value;
    }

    return $results;
}

/**
 * Returns the settings map based on the type.
 *
 * @param string $type Type of the settings.
 * @return array Associative array of settings keys.
 */
function rnb_get_settings_map($type)
{
    switch ($type) {
        case 'general':
            return rnb_get_general_keys();
        case 'display':
            return rnb_get_display_keys();
        case 'conditions':
            return rnb_get_conditional_keys();
        case 'validations':
            return rnb_get_validation_keys();
        case 'layout_two':
            return rnb_get_layout_two_keys();
        default:
            return rnb_get_labels_keys();
    }
}

function rnb_get_general_keys()
{
    $settings_map = [
        'instance_pay_type' => [
            'local'   => '',
            'global'  => 'rnb_instance_payment_type',
            'default' => 'percent'
        ],
        'instance_pay' => [
            'local'   => '',
            'global'  => 'rnb_instance_payment',
            'default' => 100
        ],
        'instant_pay_amount' => [
            'local'   => '',
            'global'  => 'rnb_instant_pay_amount',
            'default' => esc_html__('Instant Pay', 'redq-rental')
        ],
        'total_days' => [
            'local'   => '',
            'global'  => 'rnb_total_days_label',
            'default' => esc_html__('Total Days', 'redq-rental')
        ],
        'total_hours' => [
            'local'   => '',
            'global'  => 'rnb_total_hours_label',
            'default' => esc_html__('Total Hours', 'redq-rental')
        ],
        'attribute_tab' => [
            'local'   => '',
            'global'  => 'rnb_attribute_tab',
            'default' => esc_html__('Attributes', 'redq-rental')
        ],
        'feature_tab' => [
            'local'   => '',
            'global'  => 'rnb_feature_tab',
            'default' => esc_html__('Features', 'redq-rental')
        ],
        'pickup_location_cost' => [
            'local'   => '',
            'global'  => 'rnb_pickup_location_cost',
            'default' => esc_html__('Pickup Location Cost', 'redq-rental')
        ],
        'return_location_cost' => [
            'local'   => '',
            'global'  => 'rnb_return_location_cost',
            'default' => esc_html__('Return Location Cost', 'redq-rental')
        ],
        'distance_cost' => [
            'local'   => '',
            'global'  => 'rnb_distance_cost',
            'default' => esc_html__('Distance Cost', 'redq-rental')
        ],
        'duration_cost' => [
            'local'   => '',
            'global'  => 'rnb_duration_cost',
            'default' => esc_html__('Duration Cost', 'redq-rental')
        ],
        'discount_amount' => [
            'local'   => '',
            'global'  => 'rnb_discount_amount',
            'default' => esc_html__('Discount Amount', 'redq-rental')
        ],
        'resource_cost' => [
            'local'   => '',
            'global'  => 'rnb_resource_cost',
            'default' => esc_html__('Resource Cost', 'redq-rental')
        ],
        'category_cost' => [
            'local'   => '',
            'global'  => 'rnb_category_cost',
            'default' => esc_html__('Category Cost', 'redq-rental')
        ],
        'adult_cost' => [
            'local'   => '',
            'global'  => 'rnb_adult_cost',
            'default' => esc_html__('Adult Cost', 'redq-rental')
        ],
        'child_cost' => [
            'local'   => '',
            'global'  => 'rnb_child_cost',
            'default' => esc_html__('Child Cost', 'redq-rental')
        ],
        'deposit_amount' => [
            'local'   => '',
            'global'  => 'rnb_deposit_amount',
            'default' => esc_html__('Deposit', 'redq-rental')
        ],
        'payment_due_amount' => [
            'local'   => '',
            'global'  => 'rnb_payment_due_amount',
            'default' => esc_html__('Payment Due', 'redq-rental')
        ],
        'quote_total_amount' => [
            'local'   => '',
            'global'  => 'rnb_quote_total_amount',
            'default' => esc_html__('Quote total', 'redq-rental')
        ],
        'subtotal_amount' => [
            'local'   => '',
            'global'  => 'rnb_subtotal',
            'default' => esc_html__('Subtotal', 'redq-rental')
        ],
        'grand_total_amount' => [
            'local'   => '',
            'global'  => 'rnb_grand_total',
            'default' => esc_html__('Grand Total', 'redq-rental')
        ],
        'gdpr_enable' => [
            'local'   => '',
            'global'  => 'rnb_rfq_gdpr_enable',
            'default' => 'no'
        ],
        'gdpr_text' => [
            'local'   => '',
            'global'  => 'rnb_rfq_gdpr_text',
            'default' => esc_html__('Please accept to consent to your data being stored in line with the guidelines set out in our', 'redq-rental')
        ],
        'gdpr_page' => [
            'local'   => '',
            'global'  => 'rnb_rfq_gdpr_page',
            'default' => false,
        ],
        'holidays' => [
            'local'   => '',
            'global'  => 'rnb_holidays',
            'default' => []
        ]
    ];

    return $settings_map;
}


function rnb_get_display_keys()
{
    $settings_map = [
        'pickup_date' => [
            'local'   => 'redq_rental_local_show_pickup_date',
            'global'  => 'rnb_show_pickup_date',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ], 
        'pickup_time' => [
            'local'   => 'redq_rental_local_show_pickup_time',
            'global'  => 'rnb_show_pickup_time',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'return_date' => [
            'local'   => 'redq_rental_local_show_dropoff_date',
            'global'  => 'rnb_show_dropoff_date',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ], 
        'return_time' => [
            'local'   => 'redq_rental_local_show_dropoff_time',
            'global'  => 'rnb_show_dropoff_time',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'quantity' => [
            'local'   => 'rnb_enable_quantity',
            'global'  => 'rnb_enable_quantity',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'flip_box' => [
            'local'   => 'redq_rental_local_show_pricing_flip_box',
            'global'  => 'rnb_enable_price_flipbox',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'discount' => [
            'local'   => 'redq_rental_local_show_price_discount_on_days',
            'global'  => 'rnb_enable_price_discount',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'closed'
        ],
        'instance_payment' => [
            'local'   => 'redq_rental_local_show_price_instance_payment',
            'global'  => 'rnb_enable_instance_payment',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'closed'
        ],
        'rfq' => [
            'local'   => 'redq_rental_local_show_request_quote',
            'global'  => 'rnb_enable_rfq_btn',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'closed'
        ],
        'book_now' => [
            'local'   => 'redq_rental_local_show_request_quote',
            'global'  => 'rnb_enable_book_now_btn',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
    ];

    return $settings_map;
}


function rnb_get_conditional_keys()
{
    $settings_map = [
        'blockable' => [
            'local'   => 'redq_block_general_dates',
            'global'  => 'rnb_block_rental_days',
            'default' => 'yes'
        ],
        'date_format' => [
            'local'  => 'redq_calendar_date_format',
            'global' => 'rnb_choose_date_format',
            'default' => 'm/d/Y'
        ],
        'euro_format' => [
            'local'   => 'redq_choose_european_date_format',
             'global'  => '',
            'default' => 'no'
        ],
        'max_time_late' => [
            'local'   => 'redq_max_time_late',
            'global'  => 'rnb_max_time_late',
            'default' => '0'
        ],
        'pay_extra_hours' => [
            'local'   => 'rnb_pay_extra_hours',
            'global'  => 'rnb_pay_extra_hours',
            'default' => 'no'
        ],
        'single_day_booking' => [
            'local'   => 'redq_rental_local_enable_single_day_time_based_booking',
            'global'  => 'rnb_single_day_booking',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'include_trailing_date' => [
            'local'   => 'rnb_include_trailing_date',
            'global'  => 'rnb_include_trailing_date',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'max_book_days' => [
            'local'   => 'redq_max_rental_days',
            'global'  => 'rnb_max_book_day',
            'default' => ''
        ],
        'min_book_days' => [
            'local'   => 'redq_min_rental_days',
            'global'  => 'rnb_min_book_day',
            'default' => ''
        ],
        'pre_block_days' => [
            'local'   => 'redq_rental_starting_block_dates',
            'global'  => 'rnb_staring_block_days',
            'default' => '0'
        ],
        'before_block_days' => [
            'local'   => 'redq_rental_before_booking_block_dates',
            'global'  => 'rnb_before_block_days',
            'default' => '0'
        ],
        'post_block_days' => [
            'local'   => 'redq_rental_post_booking_block_dates',
            'global'  => 'rnb_post_block_days',
            'default' => ''
        ],
        'time_interval' => [
            'local'   => 'redq_time_interval',
            'global'  => 'rnb_time_intervals',
            'default' => '0'
        ],
        'show_price_type' => [
            'local'   => 'rnb_show_price_type',
            'global'  => 'rnb_show_price_type',
            'default' => 'daily'
        ],
        'weekends' => [
            'local'   => 'redq_rental_off_days',
            'global'  => 'rnb_weekends',
            'default' => []
        ],
        'allowed_times' => [
            'local'   => 'redq_allowed_times',
            'global'  => 'rnb_allowed_times',
            'default' => []
        ],
        'booking_layout' => [
            'local'   => 'rnb_booking_layout',
            'global'  => 'rnb_booking_layout',
            'default' => 'layout_one'
        ],
        'time_format' => [
            'local'   => 'redq_calendar_time_format',
            'global'  => 'rnb_choose_time_format',
            'default' => '24-hours'
        ]
    ];

    return $settings_map;
}


function rnb_get_validation_keys()
{
       $settings_map = [
        'pickup_location' => [
            'local'   => 'redq_rental_local_required_pickup_location',
            'global'  => 'rnb_required_pickup_loc',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'no'
        ],
        'return_location' => [
            'local'   => 'redq_rental_local_required_return_location',
            'global'  => 'rnb_required_dropoff_loc',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'no'
        ],
        'person' => [
            'local'   => 'redq_rental_local_required_person',
            'global'  => 'rnb_required_person',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'no'
        ],
        'pickup_time' => [
            'local'   => 'redq_rental_required_local_pickup_time',
            'global'  => 'rnb_required_pickup_time',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ],
        'return_time' => [
            'local'   => 'redq_rental_required_local_return_time',
            'global'  => 'rnb_required_dropoff_time',
            'mapping' => ['no' => 'closed', 'yes' => 'open'],
            'default' => 'yes'
        ]
    ];

    return $settings_map;
}


function rnb_get_layout_two_keys()
{
    $settings_map = [
        'inventory_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_inventory_top_heading',
            'default' => []
        ],
        'inventory_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_inventory_top_desc',
            'default' => []
        ],
        'inventory_inner_heading' => [
            'local'   => '',
            'global'  => 'rnb_inventory_inner_heading',
            'default' => []
        ],
        'inventory_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_inventory_inner_desc',
            'default' => []
        ],
        'date_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_date_top_heading',
            'default' => []
        ],
        'date_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_date_top_desc',
            'default' => []
        ],
        'date_inner_heading' => [
            'local'   => '',
            'global'  => 'rnb_date_inner_heading',
            'default' => []
        ],
        'date_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_date_inner_desc',
            'default' => []
        ],
        'location_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_location_top_heading',
            'default' => []
        ],
        'location_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_location_top_desc',
            'default' => []
        ],
        'location_inner_heading' => [
            'local'   => '',
            'global'  => 'location_inner_heading',
            'default' => []
        ],
        'location_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_location_inner_desc',
            'default' => []
        ],
        'resource_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_resource_top_heading',
            'default' => []
        ],
        'resource_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_resource_top_desc',
            'default' => []
        ],
        'resource_inner_heading' => [
            'local'   => '',
            'global'  => 'rnb_resource_inner_heading',
            'default' => []
        ],
        'resource_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_resource_inner_desc',
            'default' => []
        ],
        'person_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_person_top_heading',
            'default' => []
        ],
        'person_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_person_top_desc',
            'default' => []
        ],
        'person_inner_heading' => [
            'local'   => '',
            'global'  => 'rnb_person_inner_desc',
            'default' => []
        ],
        'person_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_person_inner_desc',
            'default' => []
        ],
        'deposit_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_deposit_top_heading',
            'default' => []
        ],
        'deposit_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_deposit_top_desc',
            'default' => []
        ],
        'deposit_inner_heading' => [
            'local'   => '',
            'global'  => 'rnb_deposit_inner_heading',
            'default' => []
        ],
        'deposit_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_deposit_inner_desc',
            'default' => []
        ],
        'summary_top_heading' => [
            'local'   => '',
            'global'  => 'rnb_summary_top_heading',
            'default' => []
        ],
        'summary_top_desc' => [
            'local'   => '',
            'global'  => 'rnb_summary_top_desc',
            'default' => []
        ],
        'summary_inner_heading' => [
            'local'   => '',
            'global'  => 'rnb_summary_inner_heading',
            'default' => []
        ],
        'summary_inner_desc' => [
            'local'   => '',
            'global'  => 'rnb_summary_inner_desc',
            'default' => []
        ],
    ];

    return $settings_map;
}

function rnb_get_labels_keys()
{
    $settings_map = [
        'pickup_location' => [
            'local'   => 'redq_pickup_location_heading_title',
            'global'  => 'rnb_pickup_location_title',
            'default' => esc_html__('Pickup Locations', 'redq-rental')
        ],
        'pickup_loc_placeholder' => [
            'local'   => 'redq_pickup_loc_placeholder',
            'global'  => 'rnb_pickup_location_placeholder',
            'default' => esc_html__('Pickup Locations', 'redq-rental')
        ],
        'return_location' => [
            'local'   => 'redq_dropoff_location_heading_title',
            'global'  => 'rnb_dropoff_location_title',
            'default' => esc_html__('Return Locations', 'redq-rental')
        ],
        'return_loc_placeholder' => [
            'local'   => 'redq_return_loc_placeholder',
            'global'  => 'rnb_dropoff_location_placeholder',
            'default' => esc_html__('Return Locations', 'redq-rental')
        ],
        'pickup_datetime' => [
            'local'   => 'redq_pickup_date_heading_title',
            'global'  => 'rnb_pickup_datetime_title',
            'default' => esc_html__('Pickup Date&Time', 'redq-rental')
        ],
        'pickup_date' => [
            'local'   => 'redq_pickup_date_placeholder',
            'global'  => 'rnb_pickup_date_placeholder',
            'default' => esc_html__('Date', 'redq-rental')
        ],
        'pickup_time' => [
            'local'   => 'redq_pickup_time_placeholder',
            'global'  => 'rnb_pickup_time_placeholder',
            'default' => esc_html__('Time', 'redq-rental')
        ],
        'return_datetime' => [
            'local'   => 'redq_dropoff_date_heading_title',
            'global'  => 'rnb_dropoff_datetime_title',
            'default' => esc_html__('Return Date&Time', 'redq-rental')
        ],
        'return_date' => [
            'local'   => 'redq_dropoff_date_placeholder',
            'global'  => 'rnb_dropoff_date_placeholder',
            'default' => esc_html__('Date', 'redq-rental')
        ],
        'return_time' => [
            'local'   => 'redq_dropoff_time_placeholder',
            'global'  => 'rnb_dropoff_time_placeholder',
            'default' => esc_html__('Time', 'redq-rental')
        ],
        'adults' => [
            'local'   => 'redq_adults_heading_title',
            'global'  => 'rnb_adults_title',
            'default' => esc_html__('Adults', 'redq-rental')
        ],
        'adults_placeholder' => [
            'local'   => 'redq_adults_placeholder',
            'global'  => 'rnb_adults_placeholder',
            'default' => esc_html__('Adults', 'redq-rental')
        ],
        'childs' => [
            'local'   => 'redq_childs_heading_title',
            'global'  => 'rnb_childs_title',
            'default' => esc_html__('Childs', 'redq-rental')
        ],
        'childs_placeholder' => [
            'local'   => 'redq_childs_placeholder',
            'global'  => 'rnb_childs_placeholder',
            'default' => esc_html__('Choose Childs', 'redq-rental')
        ],
        'quantity' => [
            'local'   => 'rnb_quantity_label',
            'global'  => 'rnb_quantity_title',
            'default' => esc_html__('Quantity', 'redq-rental')
        ],
        'resource' => [
            'local'   => 'redq_resources_heading_title',
            'global'  => 'rnb_resources_title',
            'default' => esc_html__('Resources', 'redq-rental')
        ],
        'category' => [
            'local'   => 'redq_rnb_cat_heading',
            'global'  => 'rnb_categories_title',
            'default' => esc_html__('Categories', 'redq-rental')
        ],
        'deposite' => [
            'local'   => 'redq_security_deposite_heading_title',
            'global'  => 'rnb_deposit_title',
            'default' => esc_html__('Deposite', 'redq-rental')
        ],
        'inventory' => [
            'local'   => 'rnb_inventory_title',
            'global'  => 'rnb_inventory_title',
            'default' => esc_html__('Inventory', 'redq-rental')
        ],
        'discount' => [
            'local'   => 'redq_discount_text_title',
            'global'  => 'rnb_discount_text',
            'default' => esc_html__('Discounts', 'redq-rental')
        ],
        'instance_pay' => [
            'local'   => 'redq_instance_pay_text_title',
            'global'  => 'rnb_instance_pay_text',
            'default' => esc_html__('Instance Pay', 'redq-rental')
        ],
        'total_cost' => [
            'local'   => 'redq_total_cost_text_title',
            'global'  => 'rnb_total_cost_text',
            'default' => esc_html__('Total Cost', 'redq-rental')
        ],
        'flipbox' => [
            'local'   => 'redq_show_pricing_flipbox_text',
            'global'  => 'rnb_pricing_flipbox_title',
            'default' => esc_html__('Show Pricing', 'redq-rental')
        ],
        'flipbox_info' => [
            'local'   => 'redq_flip_pricing_plan_text',
            'global'  => 'rnb_pricing_flipbox_info_title',
            'default' => esc_html__('Pricing Info', 'redq-rental')
        ],
        'unit_price' => [
            'local'   => 'rnb_unit_price',
            'global'  => 'rnb_unit_price',
            'default' => esc_html__('/Per Day', 'redq-rental')
        ],
        'book_now' => [
            'local'   => 'redq_book_now_button_text',
            'global'  => 'rnb_book_now_text',
            'default' => esc_html__('Book Now', 'redq-rental')
        ],
        'rfq' => [
            'local'   => 'redq_rfq_button_text',
            'global'  => 'rnb_rfq_text',
            'default' => esc_html__('Request For Quote', 'redq-rental')
        ],
        'invalid_range_notice' => [
            'local'   => 'rnb_invalid_date_range_notice',
            'global'  => 'rnb_invalid_date_range_notice',
            'default' => esc_html__('Invalid Date Range', 'redq-rental')
        ],
        'max_day_notice' => [
            'local'   => 'rnb_max_day_notice',
            'global'  => 'rnb_max_day_notice',
            'default' => esc_html__('Max Rental Days', 'redq-rental')
        ],
        'min_day_notice' => [
            'local'   => 'rnb_min_day_notice',
            'global'  => 'rnb_min_day_notice',
            'default' => esc_html__('Min Rental Days', 'redq-rental')
        ],
        'quantity_notice' => [
            'local'   => 'rnb_quantity_notice',
            'global'  => 'rnb_quantity_notice',
            'default' => esc_html__('Quantity is not available', 'redq-rental')
        ],
        'username' => [
            'local'   => 'redq_username_placeholder',
            'global'  => 'rnb_username_placeholder',
            'default' => esc_html__('Username', 'redq-rental')
        ],
        'password' => [
            'local'   => 'redq_password_placeholder',
            'global'  => 'rnb_password_placeholder',
            'default' => esc_html__('Password', 'redq-rental')
        ],
        'first_name' => [
            'local'   => 'redq_first_name_placeholder',
            'global'  => 'rnb_first_name_placeholder',
            'default' => esc_html__('First Name', 'redq-rental')
        ],
        'last_name' => [
            'local'   => 'redq_last_name_placeholder',
            'global'  => 'rnb_last_name_placeholder',
            'default' => esc_html__('Last Name', 'redq-rental')
        ],
        'email' => [
            'local'   => 'redq_email_placeholder',
            'global'  => 'rnb_email_placeholder',
            'default' => esc_html__('Email', 'redq-rental')
        ],
        'phone' => [
            'local'   => 'redq_phone_placeholder',
            'global'  => 'rnb_phone_placeholder',
            'default' => esc_html__('Phone', 'redq-rental')
        ],
        'message' => [
            'local'   => 'redq_message_placeholder',
            'global'  => 'rnb_message_placeholder',
            'default' => esc_html__('Message', 'redq-rental')
        ],
        'username_title' => [
            'local'   => 'redq_username_title',
            'global'  => 'rnb_username_title',
            'default' => esc_html__('Username', 'redq-rental')
        ],
        'password_title' => [
            'local'   => 'redq_password_title',
            'global'  => 'rnb_password_title',
            'default' => esc_html__('Password', 'redq-rental')
        ],
        'first_name_title' => [
            'local'   => 'redq_first_name_title',
            'global'  => 'rnb_first_name_title',
            'default' => esc_html__('First Name', 'redq-rental')
        ],
        'last_name_title' => [
            'local'   => 'redq_last_name_title',
            'global'  => 'rnb_last_name_title',
            'default' => esc_html__('Last Name', 'redq-rental')
        ],
        'email_title' => [
            'local'   => 'redq_email_title',
            'global'  => 'rnb_email_title',
            'default' => esc_html__('Email', 'redq-rental')
        ],
        'phone_title' => [
            'local'   => 'redq_phone_title',
            'global'  => 'rnb_phone_title',
            'default' => esc_html__('Phone', 'redq-rental')
        ],
        'message_title' => [
            'local'   => 'redq_message_title',
            'global'  => 'rnb_message_title',
            'default' => esc_html__('Message', 'redq-rental')
        ],
        'submit_button' => [
            'local'   => 'redq_submit_button_text',
            'global'  => 'rnb_submit_button_text',
            'default' => esc_html__('Submit', 'redq-rental')
        ]
    ];

    return $settings_map;
}

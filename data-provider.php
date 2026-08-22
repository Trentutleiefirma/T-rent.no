<?php

/**
 * rnb_get_inventory_taxonomies
 *
 * @return array
 */
function rnb_get_inventory_taxonomies()
{
    $taxonomy_args = [
        [
            'taxonomy' => 'rnb_categories',
            'label' => __('RnB Categories', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'resource',
            'label' => __('Resources', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'person',
            'label' => __('Person', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'deposite',
            'label' => __('Deposit', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'attributes',
            'label' => __('Attributes', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'features',
            'label' => __('Features', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'pickup_location',
            'label' => __('Pickup Location', 'redq-rental'),
            'post_type' => 'inventory'
        ],
        [
            'taxonomy' => 'dropoff_location',
            'label' => __('Dropoff Location', 'redq-rental'),
            'post_type' => 'inventory'
        ],
    ];
    return apply_filters('rnb_register_inventory_taxonomy', $taxonomy_args);
}

/**
 * rnb_term_meta_data_provider
 *
 * @return array
 */
function rnb_term_meta_data_provider()
{
    //Rnb Categories Term Meta args
    $args[] = [
        'taxonomy' => 'rnb_categories',
        'args' => [
            'title'       => __('Quantity', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_rnb_cat_qty',
            'column_name' => __('Qty', 'redq-rental'),
            'placeholder' => '',
            'required'    => false,
        ]
    ];
    $args[] = [
        'taxonomy' => 'rnb_categories',
        'args' => [
            'title'       => __('Choose Payable or Not', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_rnb_cat_payable_or_not',
            'column_name' => __('Pay', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key'   => 'yes',
                    'value' => esc_html__('Yes', 'redq-rental')
                ],
                '1' => [
                    'key'   => 'no',
                    'value' => esc_html__('No', 'redq-rental')
                ],
            ],
        ]
    ];
    $args[] = [
        'taxonomy' => 'rnb_categories',
        'args' => [
            'title'       => __('Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_rnb_cat_cost_termmeta',
            'column_name' => __('Cost', 'redq-rental'),
            'placeholder' => '',
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];
    $args[] = [
        'taxonomy' => 'rnb_categories',
        'args' => [
            'title'       => __('Price Applicable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_rnb_cat_price_applicable_term_meta',
            'column_name' => __('Applied', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'one_time',
                    'value' => esc_html__('One Time', 'redq-rental')
                ],
                '1' => [
                    'key' => 'per_day',
                    'value' => esc_html__('Per Day', 'redq-rental')
                ],
            ],
        ]
    ];
    $args[] = [
        'taxonomy' => 'rnb_categories',
        'args' => [
            'title'       => __('Hourly Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_rnb_cat_hourly_cost_termmeta',
            'column_name' => __('H.Cost', 'redq-rental'),
            'placeholder' => '',
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];
    $args[] = [
        'taxonomy' => 'rnb_categories',
        'args' => [
            'title'       => __('Clickable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_rnb_cat_clickable_term_meta',
            'column_name' => __('Clickable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'yes',
                    'value' => esc_html__('Yes', 'redq-rental')
                ],
                '1' => [
                    'key' => 'no',
                    'value' => esc_html__('No', 'redq-rental')
                ],
            ],
        ]
    ];

    //Resource Term Meta args

    $args[] = [
        'taxonomy' => 'resource',
        'args' => [
            'title'       => __('Price Applicable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_price_applicable_term_meta',
            'column_name' => __('Applicable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'one_time',
                    'value' => 'One Time'
                ],
                '1' => [
                    'key' => 'per_day',
                    'value' => 'Per Day'
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'resource',
        'args' => [
            'title'       => __('Resource Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_resource_cost_termmeta',
            'column_name' => __('R.Cost', 'redq-rental'),
            'placeholder' => __('Resource Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'resource',
        'args' => [
            'title'       => __('Hourly Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_hourly_cost_termmeta',
            'column_name' => __('H.Cost', 'redq-rental'),
            'placeholder' => __('Hourly Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'resource',
        'args' => [
            'title'       => __('Clickable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'rnb_resource_clickable',
            'column_name' => __('Clickable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'yes',
                    'value' => __('Yes', 'redq-rental')
                ],
                '1' => [
                    'key' => 'no',
                    'value' => __('No', 'redq-rental')
                ],
            ],
        ]
    ];

    //Person Term Meta args
    $args[] = [
        'taxonomy' => 'person',
        'args' => [
            'title'       => __('Choose payable or not', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_person_payable_or_not',
            'column_name' => __('Payable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'yes',
                    'value' => esc_html__('Yes', 'redq-rental')
                ],
                '1' => [
                    'key' => 'no',
                    'value' => esc_html__('No', 'redq-rental')
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'person',
        'args' => [
            'title'       => __('Choose Person Type', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_person_type',
            'column_name' => __('P.Type', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'none',
                    'value' => esc_html__('None', 'redq-rental')
                ],
                '1' => [
                    'key' => 'adult',
                    'value' => esc_html__('Adult', 'redq-rental')
                ],
                '2' => [
                    'key' => 'child',
                    'value' => esc_html__('Child', 'redq-rental')
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'person',
        'args' => [
            'title'       => __('Person Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_person_cost_termmeta',
            'column_name' => __('P.Cost', 'redq-rental'),
            'placeholder' => __('Person Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'person',
        'args' => [
            'title'       => __('Price Applicable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_person_price_applicable_term_meta',
            'column_name' => __('Applicable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'one_time',
                    'value' => 'One Time'
                ],
                '1' => [
                    'key' => 'per_day',
                    'value' => 'Per Day'
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'person',
        'args' => [
            'title'       => __('Hourly Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_peroson_hourly_cost_termmeta',
            'column_name' => __('H.Cost', 'redq-rental'),
            'placeholder' => __('Hourly Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    //Deposit Term Meta args
    $args[] = [
        'taxonomy' => 'deposite',
        'args' => [
            'title'       => __('Security Deposite Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_sd_cost_termmeta',
            'column_name' => __('S.D.Cost', 'redq-rental'),
            'placeholder' => __('Security Deposite Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'deposite',
        'args' => [
            'title'       => __('Price Applicable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_sd_price_applicable_term_meta',
            'column_name' => __('Applicable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'one_time',
                    'value' => 'One Time'
                ],
                '1' => [
                    'key' => 'per_day',
                    'value' => 'Per Day'
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'deposite',
        'args' => [
            'title'       => __('Hourly Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_sd_hourly_cost_termmeta',
            'column_name' => __('H.Cost', 'redq-rental'),
            'placeholder' => __('Hourly Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'deposite',
        'args' => [
            'title'       => __('Security Deposite Clickable', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_sd_price_clickable_term_meta',
            'column_name' => __('Clickable', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key' => 'yes',
                    'value' => 'Yes'
                ],
                '1' => [
                    'key' => 'no',
                    'value' => 'No'
                ],
            ],
        ]
    ];

    //Pickup Location Term Meta args
    $args[] = [
        'taxonomy' => 'pickup_location',
        'args' => [
            'title'       => __('Pickup Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_pickup_cost_termmeta',
            'column_name' => __('Cost', 'redq-rental'),
            'placeholder' => __('Pickup Location Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'pickup_location',
        'args' => [
            'title'       => __('Default Selected ?', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_pickup_location_selected_term_meta',
            'column_name' => __('selected', 'redq-rental'),
            'options'     => [
                '1' => [
                    'key' => 'no',
                    'value' => 'No'
                ],
                '0' => [
                    'key' => 'yes',
                    'value' => 'Yes'
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'pickup_location',
        'args' => [
            'title'       => __('Latitude', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_pickup_location_lat',
            'column_name' => __('Latitude', 'redq-rental'),
            'placeholder' => __('Latitude', 'redq-rental'),
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'pickup_location',
        'args' => [
            'title'       => __('Longitude', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_pickup_location_lng',
            'column_name' => __('Longitude', 'redq-rental'),
            'placeholder' => __('Longitude', 'redq-rental'),
            'required'    => false,
        ]
    ];

    //Dropoff Location Term Meta args
    $args[] = [
        'taxonomy' => 'dropoff_location',
        'args' => [
            'title'       => __('Dropoff Cost', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_dropoff_cost_termmeta',
            'column_name' => __('Cost', 'redq-rental'),
            'placeholder' => __('Dropoff Location Cost', 'redq-rental'),
            'text_type'   => 'price',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'dropoff_location',
        'args' => [
            'title'       => __('Default Selected ?', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_dropoff_location_selected_term_meta',
            'column_name' => __('selected', 'redq-rental'),
            'options'     => [
                '1' => [
                    'key'   => 'no',
                    'value' => 'No'
                ],
                '0' => [
                    'key'   => 'yes',
                    'value' => 'Yes'
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'dropoff_location',
        'args' => [
            'title'       => __('Latitude', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_dropoff_location_lat',
            'column_name' => __('Latitude', 'redq-rental'),
            'placeholder' => __('Latitude', 'redq-rental'),
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'dropoff_location',
        'args' => [
            'title'       => __('Longitude', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_dropoff_location_lng',
            'column_name' => __('Longitude', 'redq-rental'),
            'placeholder' => __('Longitude', 'redq-rental'),
            'required'    => false,
        ]
    ];

    //Attributes Term Meta args
    $args[] = [
        'taxonomy' => 'attributes',
        'args' => [
            'title'       => __('Attribute Name', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_attribute_name',
            'column_name' => __('A.Name', 'redq-rental'),
            'placeholder' => '',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'attributes',
        'args' => [
            'title'       => __('Attribute Value', 'redq-rental'),
            'type'        => 'text',
            'id'          => 'inventory_attribute_value',
            'column_name' => __('A.Value', 'redq-rental'),
            'placeholder' => '',
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'attributes',
        'args' => [
            'title'       => __('Choose Image/Icon', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'choose_attribute_icon',
            'column_name' => __('I.Type', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key'   => 'icon',
                    'value' => 'Icon'
                ],
                '1' => [
                    'key'   => 'image',
                    'value' => 'Image'
                ],
            ],
        ]
    ];

    $args[] = [
        'taxonomy' => 'attributes',
        'args' => [
            'title'       => __('Attribute Icon', 'redq-rental'),
            'type'        => 'text',
            'text_type'   => 'icon',
            'id'          => 'inventory_attribute_icon',
            'column_name' => __('Icon', 'redq-rental'),
            'placeholder' => __('Font-awesome icon Ex. fa fa-car', 'redq-rental'),
            'required'    => false,
        ]
    ];

    $args[] = [
        'taxonomy' => 'attributes',
        'args' => [
            'title'       => __('Attribute Image', 'redq-rental'),
            'type'        => 'image',
            'id'          => 'attributes_image_icon',
            'column_name' => __('Image', 'redq-rental'),
        ]
    ];

    $args[] = [
        'taxonomy' => 'attributes',
        'args'     => [
            'title'       => __('Highlighted Or Not', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_attribute_highlighted',
            'column_name' => __('Highlighted', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key'   => 'yes',
                    'value' => 'Yes'
                ],
                '1' => [
                    'key'   => 'no',
                    'value' => 'No'
                ],
            ],
        ]
    ];

    //Feature term meta args
    $args[] = [
        'taxonomy' => 'features',
        'args' => [
            'title'       => __('Image', 'redq-rental'),
            'type'        => 'image',
            'id'          => 'feature_image_icon',
            'column_name' => __('Image', 'redq-rental'),
        ]
    ];

    $args[] = [
        'taxonomy' => 'features',
        'args' => [
            'title'       => __('Highlighted Or Not', 'redq-rental'),
            'type'        => 'select',
            'id'          => 'inventory_feature_highlighted',
            'column_name' => __('Highlighted', 'redq-rental'),
            'options'     => [
                '0' => [
                    'key'   => 'yes',
                    'value' => 'Yes'
                ],
                '1' => [
                    'key'   => 'no',
                    'value' => 'No'
                ],
            ],
        ]
    ];

    //Car Company term meta args
    $args[] = [
        'taxonomy' => 'car_company',
        'args'     => [
            'title'       => __('Car Company Image', 'redq-rental'),
            'type'        => 'image',
            'id'          => 'product_car_company_icon',
            'column_name' => __('Image', 'redq-rental'),
        ]
    ];

    return apply_filters('rnb_inventory_term_meta_args', $args);
}

/**
 * rnb_format_prices
 *
 * @param mixed $price_breakdown
 *
 * @return array
 */
function rnb_format_prices($breakdown, $quantity = 1, $product_id = null, $distance_unit_type = null)
{
    $formatted_prices = apply_filters('rnb_formatted_prices_data', [], $_POST);
    $prices           = $breakdown['price_breakdown'];
    $instant_pay      = $breakdown['instant_pay'];

    $duration_total = isset($prices['duration_total']) && $prices['duration_total'] ? $prices['duration_total'] : 0;
    $discount_total = isset($prices['discount_total']) && $prices['discount_total'] ? $prices['discount_total'] : 0;
    $deposit_free_total_get = isset($prices['deposit_free_total']) && $prices['deposit_free_total'] ? $prices['deposit_free_total'] : 0;
    $deposit_free_total = apply_filters('rnb_deposit_free_total', $deposit_free_total_get, $_POST);

    $deposit_total = isset($prices['deposit_total']) && $prices['deposit_total'] ? $prices['deposit_total'] : 0;
    $get_total = isset($prices['total']) && $prices['total'] ? $prices['total'] : 0;
    $total = apply_filters('rnb_total_price', $get_total, $_POST);

    $details_breakdown = rnb_rearrange_details_breakdown($prices);
    $general_settings = redq_rental_get_settings($product_id, 'general')['general'];

    if (isset($details_breakdown['pickup_location_cost']) && $details_breakdown['pickup_location_cost'] && $general_settings['pickup_location_cost']) {
        $data = [
            'text' => $general_settings['pickup_location_cost'],
            'amount' => $details_breakdown['pickup_location_cost'],
            'cost' => wc_price($details_breakdown['pickup_location_cost'] * $quantity)
        ];
        $formatted_prices['pickup_location_cost'] = $data;
    }

    if (isset($details_breakdown['return_location_cost']) && $details_breakdown['return_location_cost'] && $general_settings['return_location_cost']) {
        $data = [
            'text' => $general_settings['return_location_cost'],
            'amount' => $details_breakdown['return_location_cost'],
            'cost' => wc_price($details_breakdown['return_location_cost'] * $quantity)
        ];
        $formatted_prices['return_location_cost'] = $data;
    }

    if (isset($details_breakdown['kilometer_cost']) && $details_breakdown['kilometer_cost']) {
        $data = [
            'text' => $general_settings['distance_cost'],
            'amount' => $details_breakdown['kilometer_cost'],
            'cost' => wc_price($details_breakdown['kilometer_cost'] * $quantity)
        ];
        $formatted_prices['kilometer_cost'] = $data;
    }

    if ($duration_total && $general_settings['duration_cost']) {

        $duration_text =  $general_settings['duration_cost'] . ' [ ';
        if ($breakdown['days']) {
            $duration_text .= $breakdown['days'] > 1 ? $breakdown['days'] . __(' days ', 'redq-rental') : $breakdown['days'] . __(' day ', 'redq-rental');
        }
        if ($breakdown['hours']) {
            $duration_text .= $breakdown['hours'] > 1 ? $breakdown['hours'] . __(' hours ', 'redq-rental') : $breakdown['hours'] . __(' hour ', 'redq-rental');
        }
        $duration_text .= ' ]';

        $data = [
            'text' => $duration_text,
            'amount' => $duration_total,
            'cost' => wc_price($duration_total * $quantity)
        ];
        $formatted_prices['duration_cost'] = $data;
    }

    if ($discount_total && $general_settings['discount_amount']) {
        $data = [
            'text'   => $general_settings['discount_amount'],
            'amount' => $discount_total,
            'cost'   => wc_price(-$discount_total * $quantity)
        ];
        $formatted_prices['discount_cost'] = $data;
    }

    if (isset($details_breakdown['resource_cost']) && $details_breakdown['resource_cost'] && $general_settings['resource_cost']) {
        $data = [
            'text'   => $general_settings['resource_cost'],
            'amount' => $details_breakdown['resource_cost'],
            'cost'   => wc_price($details_breakdown['resource_cost'] * $quantity)
        ];
        $formatted_prices['resource_cost'] = $data;
    }

    if (isset($details_breakdown['category_cost']) && $details_breakdown['category_cost'] && $general_settings['category_cost']) {
        $data = [
            'text' => $general_settings['category_cost'],
            'amount' => $details_breakdown['category_cost'],
            'cost' => wc_price($details_breakdown['category_cost'] * $quantity)
        ];
        $formatted_prices['category_cost'] = $data;
    }

    if (isset($details_breakdown['adult_cost']) && $details_breakdown['adult_cost'] && $general_settings['adult_cost']) {
        $data = [
            'text' => $general_settings['adult_cost'],
            'amount' => $details_breakdown['adult_cost'],
            'cost' => wc_price($details_breakdown['adult_cost'] * $quantity)
        ];
        $formatted_prices['adult_cost'] = $data;
    }

    if (isset($details_breakdown['child_cost']) && $details_breakdown['child_cost'] && $general_settings['child_cost']) {
        $data = [
            'text' => $general_settings['child_cost'],
            'amount' => $details_breakdown['child_cost'],
            'cost' => wc_price($details_breakdown['child_cost'] * $quantity)
        ];
        $formatted_prices['child_cost'] = $data;
    }

    if ($deposit_free_total && $general_settings['subtotal_amount']) {
        $data = [
            'text' =>  $general_settings['subtotal_amount'],
            'amount' => $deposit_free_total,
            'cost' => wc_price($deposit_free_total * $quantity)
        ];
        $formatted_prices['deposit_free_total'] = $data;
    }

    if ($instant_pay === 100) {
        if ($deposit_total && $general_settings['deposit_amount']) {
            $data = [
                'text' =>  $general_settings['deposit_amount'],
                'amount' => $deposit_total,
                'cost' => wc_price($deposit_total * $quantity),
            ];
            $formatted_prices['deposit'] = $data;
        }

        if ($total && $general_settings['grand_total_amount']) {
            $data = [
                'text' =>  $general_settings['grand_total_amount'],
                'amount' => $total,
                'cost' => wc_price($total * $quantity)
            ];
            $formatted_prices['grand_total'] = $data;
        }
    }

    if ($instant_pay !== 100) {

        if ($general_settings['instant_pay_amount']) {
            $data = [
                'text' => $general_settings['instant_pay_amount'],
                'amount' => $instant_pay,
                'cost' => $instant_pay . '%'
            ];
            $formatted_prices['instant_pay'] = $data;
        }

        // if ($general_settings['initial_amount']) {
        //     $data = [
        //         'text' => $general_settings['initial_amount'],
        //         'amount' => $breakdown['cost'],
        //         'cost' => wc_price($breakdown['cost'] * $quantity),
        //     ];
        //     $formatted_prices['pay_during_booking'] = $data;
        // }

        if ($deposit_total && $general_settings['deposit_amount']) {
            $data = [
                'text' =>  $general_settings['deposit_amount'],
                'amount' => $deposit_total,
                'cost' => wc_price($deposit_total * $quantity),
            ];
            $formatted_prices['deposit'] = $data;
        }

        if ($general_settings['total_instant_pay']) {
            $data = [
                'text' => $general_settings['total_instant_pay'],
                'amount' => $breakdown['cost'] + $deposit_total,
                'cost' => wc_price(($breakdown['cost'] + $deposit_total) * $quantity),
            ];
            $formatted_prices['total_instant_pay'] = $data;
        }

        if ($general_settings['payment_due_amount']) {
            $data = [
                'text' =>  $general_settings['payment_due_amount'],
                'amount' => $breakdown['due_payment'],
                'cost' => wc_price($breakdown['due_payment'] * $quantity),
            ];
            $formatted_prices['due_payment'] = $data;
        }
    }

    if ($total && $general_settings['quote_total_amount']) {
        $data = [
            'text' => $general_settings['quote_total_amount'],
            'amount' => $total,
            'cost' => $total * $quantity
        ];
        $formatted_prices['quote_total'] = $data;
    }

    return apply_filters('rnb_formatted_prices', $formatted_prices, $breakdown, $product_id, $quantity);
}

/**
 * rnb_rearrange_details_breakdown
 *
 * @param array $prices
 *
 * @return array
 */
function rnb_rearrange_details_breakdown($prices)
{
    $breakdown = [];

    $day_based_breakdown = isset($prices['extras_breakdown']['details_breakdown']) ? $prices['extras_breakdown']['details_breakdown'] : [];
    $hour_based_breakdown = isset($prices['extras_hour_breakdown']['details_breakdown']) ? $prices['extras_hour_breakdown']['details_breakdown'] : [];

    if (!empty($day_based_breakdown) && !empty($hour_based_breakdown)) {
        foreach ($day_based_breakdown as $key => $value) {
            $amount = isset($hour_based_breakdown[$key]) ? $hour_based_breakdown[$key] : 0;
            $breakdown[$key] = $value + $amount;
        }
    }

    if (!empty($day_based_breakdown) && empty($hour_based_breakdown)) {
        $breakdown = $day_based_breakdown;
    }

    if (empty($day_based_breakdown) && !empty($hour_based_breakdown)) {
        $breakdown = $hour_based_breakdown;
    }

    return $breakdown;
}

/**
 * get_pickup_location_data
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return string
 */
function get_pickup_location_data($term_id, $taxonomy, $layout = 'layout_one')
{
    if (!$term_id) {
        return;
    }

    if ($layout === 'layout_two') {
        $result = [
            $term_id,
            $term_id,
            0
        ];
        return implode('|', $result);
    }

    $term = get_term_by('id', $term_id, $taxonomy);
    if (empty($term)) {
        return null;
    }

    $cost = get_term_meta($term_id, 'inventory_pickup_cost_termmeta', true);
    $cost = $cost ? (float) $cost : 0;

    $result = apply_filters('rnb_get_pickup_location_data', [
        $term->name,
        $term->description ? $term->description : $term->name,
        $cost
    ], $term_id, $taxonomy);

    return implode('|', $result);
}

/**
 * get_dropoff_location_data
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return string
 */
function get_dropoff_location_data($term_id, $taxonomy, $layout = 'layout_one')
{
    if (!$term_id) {
        return;
    }

    if ($layout === 'layout_two') {
        $result = [
            $term_id,
            $term_id,
            0
        ];
        return implode('|', $result);
    }

    $term = get_term_by('id', $term_id, $taxonomy);

    $cost = get_term_meta($term_id, 'inventory_dropoff_cost_termmeta', true);
    $cost = $cost ? (float) $cost : 0;

    $result = apply_filters('rnb_get_return_location_data', [
        $term->name,
        $term->description ? $term->description : $term->name,
        $cost
    ], $term_id, $taxonomy);

    return implode('|', $result);
}

/**
 * Undocumented function
 *
 * @param [type] $distance
 * @return void
 */
function get_map_distance_cost($inventory_id, $distance_details)
{
    if (empty($distance_details)) {
        return 0;
    }

    $unit          = get_post_meta($inventory_id, 'distance_unit_type', true);
    $per_kilo_cost = get_post_meta($inventory_id, 'perkilo_price', true);
    $per_kilo_cost = is_numeric($per_kilo_cost) ? $per_kilo_cost : 0;

    $distance = explode('|', $distance_details);
    $total_kilos = $distance[0] ? $distance[0] : '';

    $cost = floatval($per_kilo_cost) * $total_kilos;

    if (!empty($unit) && $unit === 'mile') {
        $cost = $cost * 0.621;
    }

    return $cost;
}

/**
 * get_resource_data
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return array
 */
function get_resource_data($term_ids, $taxonomy)
{
    $results = [];

    if (!count($term_ids)) {
        return $results;
    }

    foreach ($term_ids as $key => $term_id) {

        if (empty($term_id)) {
            continue;
        }

        $term = get_term_by('id', $term_id, $taxonomy);

        $cost        = get_term_meta($term_id, 'inventory_resource_cost_termmeta', true);
        $applicable  = get_term_meta($term_id, 'inventory_price_applicable_term_meta', true);
        $hourly_cost = get_term_meta($term_id, 'inventory_hourly_cost_termmeta', true);

        $cost = $cost ? (float) $cost : 0;
        $hourly_cost = $hourly_cost ? (float) $hourly_cost : 0;

        $data = apply_filters('rnb_get_resource_dataa', [
            $term->name,
            $cost,
            $applicable,
            $hourly_cost
        ], $term_ids, $taxonomy);

        $results[] = implode('|', $data);
    }

    return $results;
}

/**
 * get_category_data
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return array
 */
function get_category_data($categories, $taxonomy)
{
    $results = [];

    if (!count($categories)) {
        return $results;
    }

    foreach ($categories as $key => $category) {

        $ids = explode('|', $category);
        $term_id = $ids[0];
        $qty = isset($ids[1]) ? (int) $ids[1] : 1;

        $term = get_term_by('id', $term_id, $taxonomy);

        $cost        = get_term_meta($term_id, 'inventory_rnb_cat_cost_termmeta', true);
        $applicable  = get_term_meta($term_id, 'inventory_rnb_cat_price_applicable_term_meta', true);
        $hourly_cost = get_term_meta($term_id, 'inventory_rnb_cat_hourly_cost_termmeta', true);

        $cost = $cost ? (float) $cost : 0;
        $hourly_cost = $hourly_cost ? (float) $hourly_cost : 0;
        $qty = $qty ? (int) $qty : 1;

        $data = apply_filters('rnb_get_category_data', [
            $term->name,
            $cost,
            $applicable,
            $hourly_cost,
            $qty
        ], $categories, $taxonomy);

        $results[] = implode('|', $data);
    }

    return $results;
}

/**
 * get_person_data
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return null | string
 */
function get_person_data($term_id, $taxonomy)
{
    if (!$term_id) {
        return null;
    }

    $term = get_term_by('id', $term_id, $taxonomy);
    $cost = get_term_meta($term_id, 'inventory_person_cost_termmeta', true);
    $applicable = get_term_meta($term_id, 'inventory_person_price_applicable_term_meta', true);
    $hourly_cost = get_term_meta($term_id, 'inventory_peroson_hourly_cost_termmeta', true);
    $cost = $cost ? (float) $cost : 0;
    $hourly_cost = $hourly_cost ? (float) $hourly_cost : 0;

    $result = apply_filters('rnb_get_person_data', [
        $term->name,
        $cost,
        $applicable,
        $hourly_cost
    ], $term_id, $taxonomy);

    return implode('|', $result);
}

/**
 * get_deposit_data
 *
 * @param int $term_id
 * @param string $taxonomy
 * @return array
 */
function get_deposit_data($term_ids, $taxonomy)
{
    $results = [];

    if (!count($term_ids)) {
        return $results;
    }

    foreach ($term_ids as $key => $term_id) {
        $term = get_term_by('id', $term_id, $taxonomy);

        $cost = get_term_meta($term_id, 'inventory_sd_cost_termmeta', true);
        $applicable = get_term_meta($term_id, 'inventory_sd_price_applicable_term_meta', true);
        $hourly_cost = get_term_meta($term_id, 'inventory_sd_hourly_cost_termmeta', true);

        $cost = $cost ? (float) $cost : 0;
        $hourly_cost = $hourly_cost ? (float) $hourly_cost : 0;

        $data = apply_filters('rnb_get_deposit_data', [
            $term->name,
            $cost,
            $applicable,
            $hourly_cost
        ], $term_ids, $taxonomy);

        $results[] = implode('|', $data);
    }

    return $results;
}

/**
 * Admin profile for RFQ
 *
 * @return array
 */
function rnb_get_quote_admin_profile()
{
    $profile = [
        'name'  => get_option('rnb_rfq_admin_profile_name'),
        'email' => get_option('rnb_rfq_admin_profile_email'),
    ];

    // If the user isn't set the custom name, then use sitename
    if (empty(trim($profile['name']))) {
        $profile['name'] = htmlspecialchars_decode(get_option('blogname'));
    }

    // If the user isn't set the custom email, then use wp-admin email
    if (empty(trim($profile['email']))) {
        $profile['email'] = get_option('admin_email');
    }

    return $profile;
}

/**
 * Helper function for get RFQ author email and name
 *
 * @param $post_id
 *
 * @return mixed
 */
function rnb_get_quote_customer_email($post_id)
{
    // Get the order_quote_meta content
    $order_quote_meta = json_decode(get_post_meta($post_id, 'order_quote_meta', true), true);


    // Get the forms array
    $forms = array_column($order_quote_meta, 'forms')[0];

    // Extract the email from that forms and return it
    return $forms['quote_email'];
}


/**
 * Renders the order number, customer name and provides a preview link.
 *
 * @param WC_Order $order The order object for the current row.
 *
 * @return string
 */
function rnb_customer_name($order)
{
    $buyer = '';
    if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
        /* translators: 1: first name 2: last name */
        $buyer = trim(sprintf(_x('%1$s %2$s', 'full name', 'redq-rental'), $order->get_billing_first_name(), $order->get_billing_last_name()));
    } elseif ($order->get_billing_company()) {
        $buyer = trim($order->get_billing_company());
    } elseif ($order->get_customer_id()) {
        $user  = get_user_by('id', $order->get_customer_id());
        $buyer = ucwords($user->display_name);
    }

    /**
     * Filter buyer name in list table orders.
     *
     * @since 3.7.0
     *
     * @param string   $buyer Buyer name.
     * @param WC_Order $order Order data.
     */
    $buyer = apply_filters('woocommerce_admin_order_buyer_name', $buyer, $order);
    return $buyer;
}

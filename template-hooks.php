<?php

/**
 * RnB Template Hooks
 *
 * Action/filter hooks used for RnB functions/templates.
 *
 * @author        RedQTeam
 * @category    Core
 * @package    RnB/Templates
 * @version     2.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Content Wrappers.
 *
 * @see woocommerce_output_content_wrapper()
 */
add_action('rnb_before_add_to_cart_form', 'rnb_price_flip_box', 10);
add_action('rnb_before_add_to_cart_form', 'rnb_validation_notice', 15);

/**
 * Content Wrappers.
 *
 * @see woocommerce_output_content_wrapper()
 */
function rnb_template_hooks()
{
    $priorities = apply_filters('rnb_main_content_priority', [
        'select_inventory'   => 5,
        'pickup_locations'   => 10,
        'return_locations'   => 15,
        'pickup_datetimes'   => 20,
        'return_datetimes'   => 25,
        'quantity'           => 28,
        'payable_resources'  => 50,
        'payable_categories' => 40,
        'payable_persons'    => 50,
        'payable_deposits'   => 60,
    ]);

    add_action('rnb_main_rental_content', 'rnb_select_inventory', $priorities['select_inventory']);
    add_action('rnb_main_rental_content', 'rnb_pickup_locations', $priorities['pickup_locations']);
    add_action('rnb_main_rental_content', 'rnb_return_locations', $priorities['return_locations']);
    add_action('rnb_main_rental_content', 'rnb_pickup_datetimes', $priorities['pickup_datetimes']);
    add_action('rnb_main_rental_content', 'rnb_return_datetimes', $priorities['return_datetimes']);
    add_action('rnb_main_rental_content', 'rnb_quantity', $priorities['quantity']);
    add_action('rnb_main_rental_content', 'rnb_payable_resources', $priorities['payable_resources']);
    add_action('rnb_main_rental_content', 'rnb_payable_categories', $priorities['payable_categories']);
    add_action('rnb_main_rental_content', 'rnb_payable_persons', $priorities['payable_persons']);
    add_action('rnb_main_rental_content', 'rnb_payable_deposits', $priorities['payable_deposits']);
}


/**
 * Content Wrappers.
 *
 * @see rnb_modal_booking_func()
 */
add_action('rnb_modal_booking', 'rnb_modal_booking_func', 10);

/**
 * Content Wrappers.
 *
 * @see woocommerce_output_content_wrapper()
 */

$rnb_booking_layout = get_post_meta(get_the_ID(), 'rnb_booking_layout', true);
if ($rnb_booking_layout === 'layout_two') {
    add_action('woocommerce_before_add_to_cart_button', 'rnb_booking_summary_two', 10);
} else {
    add_action('woocommerce_before_add_to_cart_button', 'rnb_booking_summary', 10);
}

/**
 * Content Wrappers.
 *
 * @see woocommerce_output_content_wrapper()
 */
add_action('rnb_plain_booking_button', 'rnb_direct_booking', 10);
add_action('rnb_plain_booking_button', 'rnb_request_quote', 20);

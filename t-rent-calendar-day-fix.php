<?php
/**
 * T-Rent calendar-day compatibility fix.
 *
 * Rental dates are calendar days. A same-day booking (23/08 -> 23/08)
 * must be valid even though RnB internally creates default times.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check whether the submitted rental is a same-calendar-day booking.
 */
function trent_is_same_day_rental_request(): bool
{
    if (empty($_POST['pickup_date'])) {
        return false;
    }

    $pickup = sanitize_text_field(wp_unslash($_POST['pickup_date']));
    $return = !empty($_POST['return_date'])
        ? sanitize_text_field(wp_unslash($_POST['return_date']))
        : (!empty($_POST['dropoff_date']) ? sanitize_text_field(wp_unslash($_POST['dropoff_date'])) : $pickup);

    $pickup = function_exists('rnb_generalized_date_format')
        ? rnb_generalized_date_format($pickup, true)
        : date('Y-m-d', strtotime(str_replace('/', '.', $pickup)));
    $return = function_exists('rnb_generalized_date_format')
        ? rnb_generalized_date_format($return, true)
        : date('Y-m-d', strtotime(str_replace('/', '.', $return)));

    return $pickup === $return;
}

/**
 * RnB's internal duration check can return zero for equal datetimes.
 * For a calendar-day rental, make the inventory check span one minute so
 * RnB considers the request a real interval. This does not change pricing;
 * pricing remains based on the selected calendar day.
 */
add_filter('rnb_inventory_quantity_by_date', function ($quantity, $product_id, $args) {
    if (!is_array($args) || empty($args['pickup_date']) || empty($args['return_date'])) {
        return $quantity;
    }

    if ($args['pickup_date'] !== $args['return_date']) {
        return $quantity;
    }

    $pickup_time = !empty($args['pickup_time']) ? $args['pickup_time'] : '00:00';
    $return_time = !empty($args['return_time']) ? $args['return_time'] : $pickup_time;

    $pickup = new DateTime($args['pickup_date'] . ' ' . $pickup_time);
    $return = new DateTime($args['return_date'] . ' ' . $return_time);

    if ($return <= $pickup) {
        $return = clone $pickup;
        $return->modify('+1 minute');
    }

    $inventory_id = !empty($args['booking_inventory']) ? (int) $args['booking_inventory'] : 0;
    if (!$inventory_id) {
        return $quantity;
    }

    $inventory_args = [
        'pickup_datetime' => $pickup->format('Y-m-d H:i'),
        'return_datetime' => $return->format('Y-m-d H:i'),
        'product_id'      => $product_id,
        'inventory_id'    => $inventory_id,
        'quantity'        => get_post_meta($inventory_id, 'quantity', true),
    ];

    $fixed_quantity = rnb_inventory_quantity_availability_check($inventory_args);

    return $fixed_quantity;
}, 20, 3);

/**
 * If RnB rejected only the generic invalid-date notice for a same-day
 * calendar booking, remove that notice and allow the request through.
 * Other validation errors (inventory, deposit, required fields, etc.) remain.
 */
add_filter('woocommerce_add_to_cart_validation', function ($valid) {
    if ($valid || !trent_is_same_day_rental_request()) {
        return $valid;
    }

    $product_id = !empty($_POST['add-to-cart']) ? absint($_POST['add-to-cart']) : 0;
    if (!$product_id) {
        return $valid;
    }

    $labels = redq_rental_get_settings($product_id, 'labels', ['notice'])['labels'] ?? [];
    $invalid_notice = $labels['invalid_range_notice'] ?? '';

    if ($invalid_notice === '') {
        return $valid;
    }

    $notices = wc_get_notices('error');
    $kept = [];
    $removed = false;

    foreach ($notices as $notice) {
        $text = isset($notice['notice']) ? wp_strip_all_tags($notice['notice']) : '';
        if (trim($text) === trim(wp_strip_all_tags($invalid_notice))) {
            $removed = true;
            continue;
        }
        $kept[] = $notice;
    }

    if (!$removed) {
        return $valid;
    }

    wc_set_notices(['error' => $kept]);

    return empty($kept);
}, 20, 1);

/**
 * Same protection during checkout validation.
 */
add_action('woocommerce_checkout_process', function () {
    $cart = WC()->cart;
    if (!$cart) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (empty($cart_item['rental_data']['posted_data'])) {
            continue;
        }

        $data = $cart_item['rental_data']['posted_data'];
        $pickup = $data['pickup_date'] ?? '';
        $return = $data['return_date'] ?? ($data['dropoff_date'] ?? $pickup);

        if ($pickup !== $return) {
            continue;
        }

        $product_id = !empty($cart_item['product_id']) ? absint($cart_item['product_id']) : 0;
        if (!$product_id) {
            continue;
        }

        $labels = redq_rental_get_settings($product_id, 'labels', ['notice'])['labels'] ?? [];
        $invalid_notice = $labels['invalid_range_notice'] ?? '';
        if (!$invalid_notice) {
            continue;
        }

        $notices = wc_get_notices('error');
        $kept = [];
        foreach ($notices as $notice) {
            $text = isset($notice['notice']) ? wp_strip_all_tags($notice['notice']) : '';
            if (trim($text) !== trim(wp_strip_all_tags($invalid_notice))) {
                $kept[] = $notice;
            }
        }
        wc_set_notices(['error' => $kept]);
    }
}, 30);

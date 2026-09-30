<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App_Bookings
{
    const REST_NAMESPACE = 't-rent-app/v1';

    public static function register()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/bookings', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'rest_bookings'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);
    }

    public static function rest_bookings()
    {
        if (!function_exists('wc_get_orders')) {
            return new WP_Error('trent_wc_missing', 'WooCommerce er ikke tilgjengelig.', ['status' => 500]);
        }

        $statuses = TRent_Admin_App_Rental::active_order_statuses();
        if (empty($statuses)) {
            return rest_ensure_response(['bookings' => []]);
        }

        $orders = wc_get_orders([
            'limit' => 150,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => $statuses,
            'return' => 'objects',
        ]);

        $now = time();
        $bookings = [];

        foreach ($orders as $order) {
            if (!$order || !method_exists($order, 'get_items')) {
                continue;
            }

            $order_date = $order->get_date_created();
            $order_date_ts = $order_date ? $order_date->getTimestamp() : 0;

            foreach ($order->get_items('line_item') as $item_id => $item) {
                if (!TRent_Admin_App_Rental::is_rental_item($item)) {
                    continue;
                }

                $period = TRent_Admin_App_Rental::rental_period_from_item($item);
                if (empty($period['start_ts']) || empty($period['return_ts'])) {
                    continue;
                }

                if ($period['start_ts'] <= $now && $period['return_ts'] > $now) {
                    $phase = 'active';
                    $phase_label = 'Ute nå';
                    $sort_group = 0;
                    $sort_ts = $period['return_ts'];
                } elseif ($period['start_ts'] > $now) {
                    $phase = 'upcoming';
                    $phase_label = 'Kommende';
                    $sort_group = 1;
                    $sort_ts = $period['start_ts'];
                } else {
                    $phase = 'completed';
                    $phase_label = 'Avsluttet';
                    $sort_group = 2;
                    $sort_ts = -$period['return_ts'];
                }

                $first = trim((string) $order->get_billing_first_name());
                $last = trim((string) $order->get_billing_last_name());
                $customer = trim($first . ' ' . $last);
                if ($customer === '') {
                    $customer = trim((string) $order->get_formatted_billing_full_name());
                }
                if ($customer === '') {
                    $customer = 'Ukjent kunde';
                }

                $bookings[] = [
                    'order_id' => (int) $order->get_id(),
                    'order_number' => (string) $order->get_order_number(),
                    'item_id' => (int) $item_id,
                    'product_id' => (int) $item->get_product_id(),
                    'product_name' => (string) $item->get_name(),
                    'inventory_id' => TRent_Admin_App_Rental::inventory_id_from_item($item),
                    'status' => (string) $order->get_status(),
                    'status_label' => wc_get_order_status_name($order->get_status()),
                    'phase' => $phase,
                    'phase_label' => $phase_label,
                    'booking_created_ts' => $order_date_ts,
                    'booking_created' => $order_date_ts ? TRent_Admin_App_Rental::format_timestamp($order_date_ts, true) : '',
                    'pickup_ts' => (int) $period['start_ts'],
                    'return_ts' => (int) $period['return_ts'],
                    'pickup' => TRent_Admin_App_Rental::format_timestamp($period['start_ts'], true),
                    'return' => TRent_Admin_App_Rental::format_timestamp($period['return_ts'], true),
                    'customer_name' => $customer,
                    'phone' => (string) $order->get_billing_phone(),
                    'email' => (string) $order->get_billing_email(),
                    'total' => (string) $order->get_total(),
                    'currency' => (string) $order->get_currency(),
                    'payment_method' => (string) $order->get_payment_method_title(),
                    '_sort_group' => $sort_group,
                    '_sort_ts' => $sort_ts,
                ];
            }
        }

        usort($bookings, function ($a, $b) {
            if ($a['_sort_group'] !== $b['_sort_group']) {
                return $a['_sort_group'] - $b['_sort_group'];
            }

            if ($a['_sort_group'] === 2) {
                return $a['_sort_ts'] <=> $b['_sort_ts'];
            }

            return $a['_sort_ts'] <=> $b['_sort_ts'];
        });

        foreach ($bookings as &$booking) {
            unset($booking['_sort_group'], $booking['_sort_ts']);
        }
        unset($booking);

        return rest_ensure_response(['bookings' => $bookings]);
    }
}

TRent_Admin_App_Bookings::register();

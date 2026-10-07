<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App_Equipment
{
    const REST_NAMESPACE = 't-rent-app/v1';

    const STATUS_META = '_t_rent_equipment_status';
    const CHECKED_AT_META = '_t_rent_equipment_checked_at';
    const CHECKED_BY_META = '_t_rent_equipment_checked_by';
    const CHECKED_NOTE_META = '_t_rent_equipment_checked_note';
    const CHECKED_THROUGH_META = '_t_rent_equipment_checked_through';
    const SERVICE_AT_META = '_t_rent_equipment_service_at';
    const SERVICE_BY_META = '_t_rent_equipment_service_by';
    const SERVICE_NOTE_META = '_t_rent_equipment_service_note';
    const LOG_META = '_t_rent_equipment_control_log';

    public static function register()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/equipment', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'rest_equipment'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/equipment/(?P<id>\\d+)/status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_update_status'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);
    }

    public static function rest_equipment()
    {
        return rest_ensure_response(self::payload());
    }

    public static function rest_update_status(WP_REST_Request $request)
    {
        $inventory_id = absint($request['id']);
        if (!$inventory_id || get_post_type($inventory_id) !== 'inventory') {
            return new WP_Error('trent_inventory_missing', 'Utstyret ble ikke funnet.', ['status' => 404]);
        }

        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];
        $status = isset($data['status']) ? sanitize_key($data['status']) : '';
        $note = isset($data['note']) ? sanitize_text_field($data['note']) : '';

        if (!in_array($status, ['ready', 'pending', 'service', 'maintenance'], true)) {
            return new WP_Error('trent_invalid_equipment_status', 'Ugyldig kontrollstatus.', ['status' => 400]);
        }

        $user = wp_get_current_user();
        $actor = ($user && $user->exists()) ? $user->display_name : 'T-Rent App';
        $now_mysql = wp_date('c');
        $covered_return = (int) get_post_meta($inventory_id, self::CHECKED_THROUGH_META, true);

        if ($status === 'ready') {
            $schedule = self::bookings_by_inventory();
            if (!empty($schedule[$inventory_id]['latest_started'])) {
                $covered_return = max(
                    $covered_return,
                    (int) $schedule[$inventory_id]['latest_started']['return_ts']
                );
            } else {
                $covered_return = max($covered_return, time());
            }

            update_post_meta($inventory_id, self::STATUS_META, 'ready');
            update_post_meta($inventory_id, self::CHECKED_AT_META, $now_mysql);
            update_post_meta($inventory_id, self::CHECKED_BY_META, $actor);
            update_post_meta($inventory_id, self::CHECKED_NOTE_META, $note);
            update_post_meta($inventory_id, self::CHECKED_THROUGH_META, $covered_return);
            $message = get_the_title($inventory_id) . ' er kontrollert og klar.';
        } elseif ($status === 'service') {
            update_post_meta($inventory_id, self::STATUS_META, 'service');
            update_post_meta($inventory_id, self::SERVICE_AT_META, $now_mysql);
            update_post_meta($inventory_id, self::SERVICE_BY_META, $actor);
            update_post_meta($inventory_id, self::SERVICE_NOTE_META, $note);
            $message = get_the_title($inventory_id) . ' er satt til service.';
        } elseif ($status === 'maintenance') {
            update_post_meta($inventory_id, self::STATUS_META, 'maintenance');
            update_post_meta($inventory_id, self::CHECKED_BY_META, $actor);
            update_post_meta($inventory_id, self::CHECKED_NOTE_META, $note);
            $message = get_the_title($inventory_id) . ' er satt til ikke klar.';
        } else {
            update_post_meta($inventory_id, self::STATUS_META, 'pending');
            update_post_meta($inventory_id, self::CHECKED_BY_META, $actor);
            update_post_meta($inventory_id, self::CHECKED_NOTE_META, $note);
            $message = get_the_title($inventory_id) . ' er satt til kontroll.';
        }

        self::append_log($inventory_id, [
            'status' => $status,
            'at' => $now_mysql,
            'by' => $actor,
            'note' => $note,
            'covered_return' => $covered_return,
        ]);

        $payload = self::payload();
        $payload['message'] = $message;

        return rest_ensure_response($payload);
    }

    private static function payload()
    {
        $rows = self::equipment_rows();
        $counts = [
            'pending' => 0,
            'service' => 0,
            'maintenance' => 0,
            'out' => 0,
            'ready' => 0,
        ];

        foreach ($rows as $row) {
            if (isset($counts[$row['display_status']])) {
                $counts[$row['display_status']]++;
            }
        }

        return [
            'rows' => $rows,
            'counts' => $counts,
        ];
    }

    private static function equipment_rows()
    {
        $inventories = get_posts([
            'post_type' => 'inventory',
            'post_status' => ['publish', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (empty($inventories)) {
            return [];
        }

        $bookings = self::bookings_by_inventory();
        $rows = [];

        foreach ($inventories as $inventory) {
            $inventory_id = (int) $inventory->ID;
            $stored_status = (string) get_post_meta($inventory_id, self::STATUS_META, true);
            $checked_at = (string) get_post_meta($inventory_id, self::CHECKED_AT_META, true);
            $checked_through = (int) get_post_meta($inventory_id, self::CHECKED_THROUGH_META, true);
            $service_at = (string) get_post_meta($inventory_id, self::SERVICE_AT_META, true);
            $service_by = (string) get_post_meta($inventory_id, self::SERVICE_BY_META, true);
            $service_note = (string) get_post_meta($inventory_id, self::SERVICE_NOTE_META, true);

            if ($service_at === '') {
                $log = get_post_meta($inventory_id, self::LOG_META, true);
                if (is_array($log)) {
                    foreach ($log as $entry) {
                        if (!is_array($entry) || ($entry['status'] ?? '') !== 'service') {
                            continue;
                        }
                        $service_at = isset($entry['at']) ? (string) $entry['at'] : '';
                        $service_by = isset($entry['by']) ? (string) $entry['by'] : '';
                        $service_note = isset($entry['note']) ? (string) $entry['note'] : '';
                        break;
                    }
                }
            }

            $schedule = isset($bookings[$inventory_id]) ? $bookings[$inventory_id] : self::empty_schedule();

            if ($stored_status === 'maintenance') {
                $display_status = 'maintenance';
                $reason = 'Registrert som ikke klar for utleie.';
            } elseif ($stored_status === 'service') {
                $display_status = 'service';
                $reason = 'Registrert til service.';
            } elseif ($stored_status === 'pending') {
                $display_status = 'pending';
                $reason = 'Manuelt satt til kontroll.';
            } elseif (!empty($schedule['last_returned']) && (int) $schedule['last_returned']['return_ts'] > $checked_through) {
                $display_status = 'pending';
                $reason = 'Ny retur etter siste kontroll.';
            } elseif (!empty($schedule['active_booking'])) {
                $display_status = 'out';
                $reason = 'Ute på leie nå.';
            } elseif ($stored_status === 'ready' && $checked_at !== '') {
                $display_status = 'ready';
                $reason = '';
            } else {
                $display_status = 'pending';
                $reason = 'Ikke grunnkontrollert i systemet ennå.';
            }

            $rows[] = [
                'inventory_id' => $inventory_id,
                'name' => self::equipment_name($inventory),
                'quantity' => max(1, (int) get_post_meta($inventory_id, 'quantity', true)),
                'display_status' => $display_status,
                'status_label' => self::status_label($display_status),
                'reason' => $reason,
                'checked_at' => self::format_datetime($checked_at),
                'checked_by' => (string) get_post_meta($inventory_id, self::CHECKED_BY_META, true),
                'checked_note' => (string) get_post_meta($inventory_id, self::CHECKED_NOTE_META, true),
                'service_at' => self::format_datetime($service_at),
                'service_by' => $service_by,
                'service_note' => $service_note,
                'last_returned' => self::format_booking($schedule['last_returned']),
                'active_booking' => self::format_booking($schedule['active_booking']),
                'next_booking' => self::format_booking($schedule['next_booking']),
                '_sort_return' => !empty($schedule['last_returned']) ? (int) $schedule['last_returned']['return_ts'] : 0,
            ];
        }

        $priority = ['pending' => 0, 'service' => 1, 'maintenance' => 2, 'out' => 3, 'ready' => 4];

        usort($rows, function ($a, $b) use ($priority) {
            $ap = isset($priority[$a['display_status']]) ? $priority[$a['display_status']] : 9;
            $bp = isset($priority[$b['display_status']]) ? $priority[$b['display_status']] : 9;

            if ($ap !== $bp) {
                return $ap - $bp;
            }

            if ($a['_sort_return'] !== $b['_sort_return']) {
                return $b['_sort_return'] - $a['_sort_return'];
            }

            return strcasecmp($a['name'], $b['name']);
        });

        foreach ($rows as &$row) {
            unset($row['_sort_return']);
        }
        unset($row);

        return $rows;
    }

    private static function bookings_by_inventory()
    {
        $results = [];
        $statuses = TRent_Admin_App_Rental::active_order_statuses();

        if (empty($statuses) || !function_exists('wc_get_orders')) {
            return $results;
        }

        $orders = wc_get_orders([
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => $statuses,
            'return' => 'objects',
        ]);

        $now = time();

        foreach ($orders as $order) {
            if (!$order || !method_exists($order, 'get_items')) {
                continue;
            }

            foreach ($order->get_items('line_item') as $item_id => $item) {
                if (!TRent_Admin_App_Rental::is_rental_item($item)) {
                    continue;
                }

                $inventory_id = TRent_Admin_App_Rental::inventory_id_from_item($item);
                if ($inventory_id <= 0 || get_post_type($inventory_id) !== 'inventory') {
                    continue;
                }

                $period = TRent_Admin_App_Rental::rental_period_from_item($item);
                if (empty($period['start_ts']) || empty($period['return_ts'])) {
                    continue;
                }

                if (!isset($results[$inventory_id])) {
                    $results[$inventory_id] = self::empty_schedule();
                }

                $booking = [
                    'order_id' => (int) $order->get_id(),
                    'item_id' => (int) $item_id,
                    'start_ts' => (int) $period['start_ts'],
                    'return_ts' => (int) $period['return_ts'],
                ];

                if ($booking['return_ts'] <= $now) {
                    if (
                        empty($results[$inventory_id]['last_returned']) ||
                        $booking['return_ts'] > $results[$inventory_id]['last_returned']['return_ts']
                    ) {
                        $results[$inventory_id]['last_returned'] = $booking;
                    }
                }

                if ($booking['start_ts'] <= $now) {
                    if (
                        empty($results[$inventory_id]['latest_started']) ||
                        $booking['start_ts'] > $results[$inventory_id]['latest_started']['start_ts']
                    ) {
                        $results[$inventory_id]['latest_started'] = $booking;
                    }
                }

                if ($booking['start_ts'] <= $now && $booking['return_ts'] > $now) {
                    if (
                        empty($results[$inventory_id]['active_booking']) ||
                        $booking['return_ts'] < $results[$inventory_id]['active_booking']['return_ts']
                    ) {
                        $results[$inventory_id]['active_booking'] = $booking;
                    }
                }

                if ($booking['start_ts'] > $now) {
                    if (
                        empty($results[$inventory_id]['next_booking']) ||
                        $booking['start_ts'] < $results[$inventory_id]['next_booking']['start_ts']
                    ) {
                        $results[$inventory_id]['next_booking'] = $booking;
                    }
                }
            }
        }

        return $results;
    }

    private static function empty_schedule()
    {
        return [
            'last_returned' => null,
            'latest_started' => null,
            'active_booking' => null,
            'next_booking' => null,
        ];
    }

    private static function format_booking($booking)
    {
        if (empty($booking)) {
            return null;
        }

        return [
            'order_id' => (int) $booking['order_id'],
            'start' => TRent_Admin_App_Rental::format_timestamp($booking['start_ts'], true),
            'return' => TRent_Admin_App_Rental::format_timestamp($booking['return_ts'], true),
        ];
    }

    private static function append_log($inventory_id, $entry)
    {
        $log = get_post_meta($inventory_id, self::LOG_META, true);
        if (!is_array($log)) {
            $log = [];
        }

        array_unshift($log, $entry);
        update_post_meta($inventory_id, self::LOG_META, array_slice($log, 0, 50));
    }

    private static function equipment_name($inventory)
    {
        global $wpdb;

        $inventory_name = trim(wp_strip_all_tags(get_the_title($inventory)));
        $table = $wpdb->prefix . 'rnb_inventory_product';
        $product_names = [];

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;

        if ($exists) {
            $product_ids = $wpdb->get_col(
                $wpdb->prepare("SELECT product FROM {$table} WHERE inventory = %d", (int) $inventory->ID)
            );

            foreach ((array) $product_ids as $product_id) {
                $name = trim(wp_strip_all_tags(get_the_title((int) $product_id)));
                if ($name !== '') {
                    $product_names[] = $name;
                }
            }
        }

        $product_names = array_values(array_unique($product_names));

        if (empty($product_names)) {
            return $inventory_name !== '' ? $inventory_name : 'Utstyr #' . (int) $inventory->ID;
        }

        $product_label = implode(', ', $product_names);
        if ($inventory_name === '' || strcasecmp($inventory_name, $product_label) === 0) {
            return $product_label;
        }

        return $product_label . ' – ' . $inventory_name;
    }

    private static function status_label($status)
    {
        $labels = [
            'pending' => 'Må kontrolleres',
            'service' => 'Service',
            'maintenance' => 'Ikke klar',
            'out' => 'Ute på leie',
            'ready' => 'Kontrollert og klar',
        ];

        return isset($labels[$status]) ? $labels[$status] : 'Ukjent';
    }

    private static function format_datetime($datetime)
    {
        if ($datetime === '') {
            return '';
        }

        try {
            $parsed = new DateTimeImmutable($datetime);
            return wp_date('d.m.Y H:i', $parsed->getTimestamp(), wp_timezone());
        } catch (Exception $e) {
            return $datetime;
        }
    }
}

TRent_Admin_App_Equipment::register();

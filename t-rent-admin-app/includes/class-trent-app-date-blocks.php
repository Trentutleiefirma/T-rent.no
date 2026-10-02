<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App_Date_Blocks
{
    const REST_NAMESPACE = 't-rent-app/v1';

    public static function register()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/blocks', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'rest_blocks'],
                'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'rest_add_block'],
                'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/blocks/remove', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_remove_block'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);
    }

    private static function availability_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'rnb_availability';
    }

    private static function mapping_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'rnb_inventory_product';
    }

    private static function table_exists($table)
    {
        global $wpdb;
        $like = $wpdb->esc_like($table);
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like)) === $table;
    }

    private static function parse_date($value)
    {
        $value = sanitize_text_field((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            ($errors !== false && (!empty($errors['warning_count']) || !empty($errors['error_count']))) ||
            $date->format('Y-m-d') !== $value
        ) {
            return false;
        }

        return $date;
    }

    private static function rental_products()
    {
        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => ['redq_rental'],
                ],
            ],
        ]);

        return array_map(function ($id) {
            return [
                'id' => (int) $id,
                'name' => get_the_title($id),
            ];
        }, $ids);
    }

    private static function get_product_inventories($product_id)
    {
        global $wpdb;

        $product_id = absint($product_id);
        if (!$product_id) {
            return [];
        }

        $inventory_ids = [];
        $mapping_table = self::mapping_table();

        if (self::table_exists($mapping_table)) {
            $inventory_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT inventory FROM {$mapping_table} WHERE product = %d ORDER BY inventory ASC",
                    $product_id
                )
            );
        }

        if (empty($inventory_ids)) {
            $meta = get_post_meta($product_id, '_redq_product_inventory', true);
            if (is_array($meta)) {
                $inventory_ids = $meta;
            } elseif (!empty($meta)) {
                $inventory_ids = [$meta];
            }
        }

        if (empty($inventory_ids) && function_exists('rnb_get_default_inventory_id')) {
            $default = rnb_get_default_inventory_id($product_id);
            if ($default) {
                $inventory_ids = [$default];
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', (array) $inventory_ids))));
    }

    private static function is_rental_product($product_id)
    {
        return (
            $product_id > 0 &&
            get_post_type($product_id) === 'product' &&
            has_term('redq_rental', 'product_type', $product_id)
        );
    }

    private static function purge_product_cache($product_id)
    {
        clean_post_cache($product_id);

        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }

        do_action('litespeed_purge_post', $product_id);
    }

    private static function active_blocks()
    {
        global $wpdb;

        $table = self::availability_table();
        if (!self::table_exists($table)) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    product_id,
                    pickup_datetime,
                    return_datetime,
                    GROUP_CONCAT(id ORDER BY id ASC) AS block_ids,
                    COUNT(*) AS inventory_count
                 FROM {$table}
                 WHERE block_by = 'CUSTOM'
                   AND delete_status = 0
                   AND return_datetime > %s
                 GROUP BY product_id, pickup_datetime, return_datetime
                 ORDER BY pickup_datetime ASC, product_id ASC",
                current_time('mysql')
            ),
            ARRAY_A
        );

        $result = [];

        foreach ($rows as $row) {
            try {
                $from = new DateTimeImmutable($row['pickup_datetime'], wp_timezone());
                $to = new DateTimeImmutable($row['return_datetime'], wp_timezone());
                if ($to > $from && $to->format('H:i:s') === '00:00:00') {
                    $to = $to->modify('-1 day');
                }
                $from_display = wp_date('d.m.Y', $from->getTimestamp(), wp_timezone());
                $to_display = wp_date('d.m.Y', $to->getTimestamp(), wp_timezone());
            } catch (Exception $e) {
                $from_display = $row['pickup_datetime'];
                $to_display = $row['return_datetime'];
            }

            $ids = array_values(array_filter(array_map('absint', explode(',', (string) $row['block_ids']))));

            $result[] = [
                'product_id' => (int) $row['product_id'],
                'product_name' => get_the_title((int) $row['product_id']),
                'from' => $from_display,
                'to' => $to_display,
                'ids' => $ids,
                'inventory_count' => (int) $row['inventory_count'],
            ];
        }

        return $result;
    }

    public static function rest_blocks()
    {
        return rest_ensure_response([
            'products' => self::rental_products(),
            'blocks' => self::active_blocks(),
        ]);
    }

    public static function rest_add_block(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];

        $product_id = isset($data['product_id']) ? absint($data['product_id']) : 0;
        $from = self::parse_date($data['from_date'] ?? '');
        $to = self::parse_date($data['to_date'] ?? '');

        if (!self::is_rental_product($product_id)) {
            return new WP_Error('trent_invalid_product', 'Velg et gyldig utleieprodukt.', ['status' => 400]);
        }

        if (!$from || !$to || $to < $from) {
            return new WP_Error('trent_invalid_date', 'Kontroller fra- og til-dato.', ['status' => 400]);
        }

        $table = self::availability_table();
        if (!self::table_exists($table)) {
            return new WP_Error('trent_rnb_missing', 'RnB availability-tabellen ble ikke funnet.', ['status' => 500]);
        }

        $inventory_ids = self::get_product_inventories($product_id);
        if (empty($inventory_ids)) {
            return new WP_Error('trent_no_inventory', 'Produktet har ingen tilkoblet RnB-inventory.', ['status' => 400]);
        }

        $pickup_datetime = $from->format('Y-m-d 00:00:00');
        $return_boundary = $to->modify('+1 day');
        $return_datetime = $return_boundary->format('Y-m-d 00:00:00');
        $rental_duration = (string) max(1, (int) $from->diff($return_boundary)->days);

        global $wpdb;

        $created_ids = [];
        $now = current_time('mysql');

        foreach ($inventory_ids as $inventory_id) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$table}
                     WHERE product_id = %d
                       AND inventory_id = %d
                       AND block_by = 'CUSTOM'
                       AND delete_status = 0
                       AND pickup_datetime = %s
                       AND return_datetime = %s
                     LIMIT 1",
                    $product_id,
                    $inventory_id,
                    $pickup_datetime,
                    $return_datetime
                )
            );

            if ($existing) {
                continue;
            }

            $inserted = $wpdb->insert(
                $table,
                [
                    'pickup_datetime' => $pickup_datetime,
                    'return_datetime' => $return_datetime,
                    'rental_duration' => $rental_duration,
                    'product_id' => $product_id,
                    'inventory_id' => $inventory_id,
                    'order_id' => null,
                    'item_id' => null,
                    'lang' => get_locale(),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'block_by' => 'CUSTOM',
                    'delete_status' => 0,
                ],
                ['%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d']
            );

            if ($inserted === false) {
                foreach ($created_ids as $id) {
                    $wpdb->delete($table, ['id' => $id, 'block_by' => 'CUSTOM'], ['%d', '%s']);
                }

                return new WP_Error('trent_block_failed', 'Blokkeringen kunne ikke lagres.', ['status' => 500]);
            }

            $created_ids[] = absint($wpdb->insert_id);
        }

        if (empty($created_ids)) {
            return new WP_Error('trent_block_exists', 'Denne perioden er allerede manuelt blokkert.', ['status' => 409]);
        }

        self::purge_product_cache($product_id);

        return rest_ensure_response([
            'message' => 'Datoen er blokkert.',
            'blocks' => self::active_blocks(),
        ]);
    }

    public static function rest_remove_block(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        $ids = isset($data['ids']) && is_array($data['ids'])
            ? array_values(array_unique(array_filter(array_map('absint', $data['ids']))))
            : [];

        if (empty($ids)) {
            return new WP_Error('trent_invalid_block', 'Blokkeringen kunne ikke identifiseres.', ['status' => 400]);
        }

        $table = self::availability_table();
        if (!self::table_exists($table)) {
            return new WP_Error('trent_rnb_missing', 'RnB availability-tabellen ble ikke funnet.', ['status' => 500]);
        }

        global $wpdb;

        $product_ids = [];
        foreach ($ids as $id) {
            $product_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT product_id FROM {$table} WHERE id = %d AND block_by = 'CUSTOM' LIMIT 1",
                    $id
                )
            );

            if ($product_id) {
                $product_ids[] = absint($product_id);
            }
        }

        $removed = 0;
        $now = current_time('mysql');

        foreach ($ids as $id) {
            $updated = $wpdb->update(
                $table,
                ['delete_status' => 1, 'updated_at' => $now],
                ['id' => $id, 'block_by' => 'CUSTOM'],
                ['%d', '%s'],
                ['%d', '%s']
            );

            if ($updated) {
                $removed++;
            }
        }

        foreach (array_unique($product_ids) as $product_id) {
            self::purge_product_cache($product_id);
        }

        if ($removed === 0) {
            return new WP_Error('trent_invalid_block', 'Blokkeringen kunne ikke fjernes.', ['status' => 400]);
        }

        return rest_ensure_response([
            'message' => 'Blokkeringen er fjernet.',
            'blocks' => self::active_blocks(),
        ]);
    }
}

TRent_Admin_App_Date_Blocks::register();

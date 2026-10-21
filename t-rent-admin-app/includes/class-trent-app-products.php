<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce/RnB product administration for T-Rent App.
 */
final class TRent_Admin_App_Products
{
    public static function register_rest_routes($namespace, $permission_callback)
    {
        register_rest_route($namespace, '/products', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'rest_products'],
                'permission_callback' => $permission_callback,
                'args' => [
                    'search' => [
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default' => '',
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'rest_create_product'],
                'permission_callback' => $permission_callback,
            ],
        ]);

        register_rest_route($namespace, '/product/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'rest_product'],
                'permission_callback' => $permission_callback,
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [__CLASS__, 'rest_update_product'],
                'permission_callback' => $permission_callback,
            ],
        ]);

        register_rest_route($namespace, '/product-options', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'rest_product_options'],
            'permission_callback' => $permission_callback,
        ]);

        register_rest_route($namespace, '/media', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_upload_media'],
            'permission_callback' => $permission_callback,
        ]);
    }

    public static function rest_products(WP_REST_Request $request)
    {
        if (!function_exists('wc_get_product')) {
            return new WP_Error('trent_wc_missing', 'WooCommerce er ikke tilgjengelig.', ['status' => 500]);
        }

        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => 100,
            's' => $request->get_param('search'),
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        $products = [];
        foreach ($query->posts as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $products[] = self::format_product($product);
            }
        }

        return rest_ensure_response(['products' => $products]);
    }

    public static function rest_product_options()
    {
        $categories = [];
        $category_terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (!is_wp_error($category_terms)) {
            foreach ($category_terms as $term) {
                $categories[] = [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                    'parent' => (int) $term->parent,
                ];
            }
        }

        $tags = [];
        $tag_terms = get_terms([
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => 250,
        ]);

        if (!is_wp_error($tag_terms)) {
            foreach ($tag_terms as $term) {
                $tags[] = [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                ];
            }
        }

        $inventories = [];
        $inventory_posts = get_posts([
            'post_type' => 'inventory',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
        ]);

        foreach ($inventory_posts as $inventory) {
            $inventories[] = [
                'id' => (int) $inventory->ID,
                'name' => get_the_title($inventory),
                'base_price' => (string) get_post_meta($inventory->ID, 'general_price', true),
                'quantity' => max(1, (int) get_post_meta($inventory->ID, 'quantity', true)),
            ];
        }

        return rest_ensure_response([
            'categories' => $categories,
            'tags' => $tags,
            'inventories' => $inventories,
        ]);
    }

    public static function rest_upload_media(WP_REST_Request $request)
    {
        if (!current_user_can('upload_files')) {
            return new WP_Error('trent_upload_forbidden', 'Du har ikke tilgang til å laste opp bilder.', ['status' => 403]);
        }

        $files = $request->get_file_params();
        if (empty($files['file']) || empty($files['file']['tmp_name'])) {
            return new WP_Error('trent_image_missing', 'Velg et bilde som skal lastes opp.', ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            return new WP_Error(
                'trent_image_upload_failed',
                $attachment_id->get_error_message(),
                ['status' => 400]
            );
        }

        $mime = (string) get_post_mime_type($attachment_id);
        if (strpos($mime, 'image/') !== 0) {
            wp_delete_attachment($attachment_id, true);
            return new WP_Error('trent_image_type', 'Filen må være et bilde.', ['status' => 400]);
        }

        return rest_ensure_response(self::format_media($attachment_id));
    }

    public static function rest_create_product(WP_REST_Request $request)
    {
        if (!function_exists('wc_get_product')) {
            return new WP_Error('trent_wc_missing', 'WooCommerce er ikke tilgjengelig.', ['status' => 500]);
        }

        $data = self::request_data($request);
        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        if ($name === '') {
            return new WP_Error('trent_name_required', 'Produktnavn kan ikke være tomt.', ['status' => 400]);
        }

        $type = isset($data['type']) ? sanitize_key($data['type']) : 'redq_rental';
        if (!in_array($type, ['redq_rental', 'simple'], true)) {
            return new WP_Error('trent_invalid_product_type', 'Ugyldig produkttype.', ['status' => 400]);
        }

        $status = self::validate_status($data['status'] ?? 'draft');
        if (is_wp_error($status)) {
            return $status;
        }

        $validation = self::validate_product_payload($data, $type);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $postarr = [
            'post_type' => 'product',
            'post_status' => $status,
            'post_title' => $name,
            'post_content' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'post_excerpt' => isset($data['short_description']) ? wp_kses_post($data['short_description']) : '',
        ];

        if (!empty($data['slug'])) {
            $postarr['post_name'] = sanitize_title($data['slug']);
        }

        $product_id = wp_insert_post($postarr, true);
        if (is_wp_error($product_id)) {
            return new WP_Error('trent_product_create_failed', 'Kunne ikke opprette produktet.', ['status' => 500]);
        }

        $term_result = wp_set_object_terms($product_id, $type, 'product_type', false);
        if (is_wp_error($term_result)) {
            wp_delete_post($product_id, true);
            return new WP_Error('trent_product_type_failed', 'Kunne ikke sette produkttype.', ['status' => 500]);
        }

        clean_post_cache($product_id);
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_delete_post($product_id, true);
            return new WP_Error('trent_product_create_failed', 'WooCommerce kunne ikke laste det nye produktet.', ['status' => 500]);
        }

        $saved = self::save_product_data($product_id, $data, $product, true);
        if (is_wp_error($saved)) {
            wp_delete_post($product_id, true);
            return $saved;
        }

        self::clear_product_cache($product_id);
        $fresh = wc_get_product($product_id);

        return rest_ensure_response(self::format_product($fresh ?: $product));
    }

    public static function rest_product(WP_REST_Request $request)
    {
        $product = wc_get_product((int) $request['id']);
        if (!$product) {
            return new WP_Error('trent_product_missing', 'Produktet finnes ikke.', ['status' => 404]);
        }

        return rest_ensure_response(self::format_product($product));
    }

    public static function rest_update_product(WP_REST_Request $request)
    {
        $product_id = (int) $request['id'];
        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error('trent_product_missing', 'Produktet finnes ikke.', ['status' => 404]);
        }

        if (!current_user_can('edit_post', $product_id)) {
            return new WP_Error('trent_product_forbidden', 'Du kan ikke redigere dette produktet.', ['status' => 403]);
        }

        $data = self::request_data($request);
        $validation = self::validate_product_payload($data, $product->get_type());
        if (is_wp_error($validation)) {
            return $validation;
        }

        $saved = self::save_product_data($product_id, $data, $product, false);
        if (is_wp_error($saved)) {
            return $saved;
        }

        self::clear_product_cache($product_id);
        $fresh = wc_get_product($product_id);

        return rest_ensure_response(self::format_product($fresh ?: $product));
    }

    private static function request_data(WP_REST_Request $request)
    {
        $data = $request->get_json_params();
        return is_array($data) ? $data : [];
    }

    private static function validate_status($status)
    {
        $status = sanitize_key($status);
        if (!in_array($status, ['publish', 'draft', 'private'], true)) {
            return new WP_Error('trent_invalid_status', 'Ugyldig produktstatus.', ['status' => 400]);
        }

        if ($status === 'publish' && !current_user_can('publish_products')) {
            return new WP_Error('trent_publish_forbidden', 'Du kan ikke publisere produkter.', ['status' => 403]);
        }

        return $status;
    }

    private static function validate_product_payload($data, $type)
    {
        if (array_key_exists('name', $data) && sanitize_text_field($data['name']) === '') {
            return new WP_Error('trent_name_required', 'Produktnavn kan ikke være tomt.', ['status' => 400]);
        }

        if (array_key_exists('status', $data)) {
            $status = self::validate_status($data['status']);
            if (is_wp_error($status)) {
                return $status;
            }
        }

        if ($type === 'simple' && array_key_exists('regular_price', $data)) {
            $price = self::decimal_value($data['regular_price'], true);
            if (is_wp_error($price)) {
                return $price;
            }
        }

        if ($type === 'redq_rental' && isset($data['rental']) && is_array($data['rental'])) {
            $base_price = self::decimal_value($data['rental']['base_price'] ?? '', false);
            if (is_wp_error($base_price) || (float) $base_price <= 0) {
                return new WP_Error('trent_invalid_rental_price', 'Grunnpris per dag må være større enn 0.', ['status' => 400]);
            }

            $inventory_id = absint($data['rental']['inventory_id'] ?? 0);
            $create_inventory = !empty($data['rental']['create_inventory']);
            if (!$create_inventory && $inventory_id > 0 && get_post_type($inventory_id) !== 'inventory') {
                return new WP_Error('trent_inventory_missing', 'Valgt RnB-utstyr/lager finnes ikke.', ['status' => 400]);
            }

            $tiers = isset($data['rental']['tiers']) && is_array($data['rental']['tiers'])
                ? $data['rental']['tiers']
                : [];

            foreach ($tiers as $tier) {
                if (!is_array($tier)) {
                    continue;
                }

                $daily_raw = trim((string) ($tier['daily_price'] ?? ''));
                if ($daily_raw === '') {
                    continue;
                }

                $daily_price = self::decimal_value($daily_raw, false);
                if (is_wp_error($daily_price) || (float) $daily_price <= 0) {
                    return new WP_Error('trent_invalid_tier_price', 'Et prisnivå har ugyldig dagpris.', ['status' => 400]);
                }
                if ((float) $daily_price > (float) $base_price) {
                    return new WP_Error('trent_tier_above_base', 'Dagpris i et prisnivå kan ikke være høyere enn grunnprisen.', ['status' => 400]);
                }

                $min_days = absint($tier['min_days'] ?? 0);
                $max_days_raw = trim((string) ($tier['max_days'] ?? ''));
                $max_days = $max_days_raw === '' ? 9999 : absint($max_days_raw);
                if ($min_days < 1 || $max_days < $min_days) {
                    return new WP_Error('trent_invalid_tier_days', 'Kontroller fra/til dager i prisnivåene.', ['status' => 400]);
                }
            }
        }

        if (array_key_exists('deposit_amount', $data)) {
            $amount = self::decimal_value($data['deposit_amount'], true);
            if (is_wp_error($amount)) {
                return new WP_Error('trent_invalid_deposit_amount', 'Ugyldig depositumbeløp.', ['status' => 400]);
            }
        }

        return true;
    }

    private static function save_product_data($product_id, $data, $product, $creating)
    {
        $post_update = ['ID' => $product_id];

        if (array_key_exists('name', $data)) {
            $post_update['post_title'] = sanitize_text_field($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $post_update['post_content'] = wp_kses_post($data['description']);
        }
        if (array_key_exists('short_description', $data)) {
            $post_update['post_excerpt'] = wp_kses_post($data['short_description']);
        }
        if (array_key_exists('slug', $data)) {
            $slug = sanitize_title($data['slug']);
            if ($slug !== '') {
                $post_update['post_name'] = $slug;
            }
        }
        if (array_key_exists('status', $data)) {
            $status = self::validate_status($data['status']);
            if (is_wp_error($status)) {
                return $status;
            }
            $post_update['post_status'] = $status;
        }

        if (count($post_update) > 1) {
            $updated = wp_update_post($post_update, true);
            if (is_wp_error($updated)) {
                return new WP_Error('trent_product_update_failed', 'Kunne ikke lagre produktteksten.', ['status' => 500]);
            }
        }

        clean_post_cache($product_id);
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('trent_product_missing', 'WooCommerce kunne ikke laste produktet etter lagring.', ['status' => 500]);
        }

        try {
            if (array_key_exists('sku', $data)) {
                $product->set_sku(sanitize_text_field($data['sku']));
            }

            if (array_key_exists('stock_status', $data)) {
                $stock_status = sanitize_key($data['stock_status']);
                if (!in_array($stock_status, ['instock', 'outofstock', 'onbackorder'], true)) {
                    $stock_status = 'instock';
                }
                $product->set_stock_status($stock_status);
            }

            if ($product->get_type() === 'simple' && array_key_exists('regular_price', $data)) {
                $regular_price = self::decimal_value($data['regular_price'], true);
                if (is_wp_error($regular_price)) {
                    return $regular_price;
                }
                $product->set_regular_price($regular_price);
                $product->set_price($regular_price);
            }

            $product->save();
        } catch (Throwable $e) {
            return new WP_Error('trent_product_save_failed', $e->getMessage() ?: 'Kunne ikke lagre WooCommerce-data.', ['status' => 400]);
        }

        if (array_key_exists('category_ids', $data)) {
            $category_ids = self::id_list($data['category_ids']);
            $result = wp_set_object_terms($product_id, $category_ids, 'product_cat', false);
            if (is_wp_error($result)) {
                return new WP_Error('trent_categories_failed', 'Kunne ikke lagre produktkategorier.', ['status' => 500]);
            }
        }

        if (array_key_exists('tag_names', $data)) {
            $tag_names = self::text_list($data['tag_names']);
            $result = wp_set_object_terms($product_id, $tag_names, 'product_tag', false);
            if (is_wp_error($result)) {
                return new WP_Error('trent_tags_failed', 'Kunne ikke lagre produktetiketter.', ['status' => 500]);
            }
        }

        if (array_key_exists('featured_media', $data)) {
            $featured_id = absint($data['featured_media']);
            if ($featured_id > 0) {
                if (!wp_attachment_is_image($featured_id)) {
                    return new WP_Error('trent_featured_image_invalid', 'Hovedbildet er ikke et gyldig bilde.', ['status' => 400]);
                }
                set_post_thumbnail($product_id, $featured_id);
            } else {
                delete_post_thumbnail($product_id);
            }
        }

        if (array_key_exists('gallery_ids', $data)) {
            $gallery_ids = [];
            foreach (self::id_list($data['gallery_ids']) as $attachment_id) {
                if (wp_attachment_is_image($attachment_id)) {
                    $gallery_ids[] = $attachment_id;
                }
            }
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }

        if (array_key_exists('deposit_enabled', $data)) {
            update_post_meta(
                $product_id,
                '_awcdp_deposit_enabled',
                rest_sanitize_boolean($data['deposit_enabled']) ? 'yes' : 'no'
            );
        }

        if (array_key_exists('deposit_type', $data)) {
            $deposit_type = sanitize_key($data['deposit_type']);
            if (!in_array($deposit_type, ['fixed', 'percent'], true)) {
                return new WP_Error('trent_invalid_deposit_type', 'Ugyldig depositumtype.', ['status' => 400]);
            }
            update_post_meta($product_id, '_awcdp_deposit_type', $deposit_type);
        }

        if (array_key_exists('deposit_amount', $data)) {
            $deposit_amount = self::decimal_value($data['deposit_amount'], true);
            if (is_wp_error($deposit_amount)) {
                return $deposit_amount;
            }
            update_post_meta($product_id, '_awcdp_deposits_deposit_amount', $deposit_amount);
        }

        if ($product->get_type() === 'redq_rental' && isset($data['rental']) && is_array($data['rental'])) {
            $rental_saved = self::save_rental_data($product_id, $data['rental'], $creating);
            if (is_wp_error($rental_saved)) {
                return $rental_saved;
            }
        }

        return true;
    }

    private static function save_rental_data($product_id, $rental, $creating)
    {
        $base_price = self::decimal_value($rental['base_price'] ?? '', false);
        if (is_wp_error($base_price) || (float) $base_price <= 0) {
            return new WP_Error('trent_invalid_rental_price', 'Legg inn en gyldig grunnpris per dag.', ['status' => 400]);
        }

        $quantity = max(1, absint($rental['quantity'] ?? 1));
        $inventory_id = absint($rental['inventory_id'] ?? 0);
        $create_inventory = !empty($rental['create_inventory']) || $inventory_id === 0;
        $created_inventory = 0;

        if ($create_inventory) {
            $inventory_name = isset($rental['inventory_name'])
                ? sanitize_text_field($rental['inventory_name'])
                : '';

            if ($inventory_name === '') {
                $inventory_name = get_the_title($product_id);
            }

            $inventory_id = wp_insert_post([
                'post_type' => 'inventory',
                'post_status' => 'publish',
                'post_title' => $inventory_name,
            ], true);

            if (is_wp_error($inventory_id)) {
                return new WP_Error('trent_inventory_create_failed', 'Kunne ikke opprette RnB-utstyr/lager.', ['status' => 500]);
            }
            $created_inventory = (int) $inventory_id;
        }

        if ($inventory_id <= 0 || get_post_type($inventory_id) !== 'inventory') {
            if ($created_inventory) {
                wp_delete_post($created_inventory, true);
            }
            return new WP_Error('trent_inventory_missing', 'Valgt RnB-utstyr/lager finnes ikke.', ['status' => 400]);
        }

        update_post_meta($inventory_id, 'quantity', $quantity);
        update_post_meta($inventory_id, 'pricing_type', 'general_pricing');
        update_post_meta($inventory_id, 'general_price', $base_price);
        if (get_post_meta($inventory_id, 'hourly_pricing_type', true) === '') {
            update_post_meta($inventory_id, 'hourly_pricing_type', 'hourly_pricing');
        }

        $linked = self::link_inventory($product_id, $inventory_id);
        if (is_wp_error($linked)) {
            if ($created_inventory) {
                wp_delete_post($created_inventory, true);
            }
            return $linked;
        }

        update_post_meta($product_id, '_regular_price', $base_price);
        update_post_meta($product_id, '_price', $base_price);

        $tiers = isset($rental['tiers']) && is_array($rental['tiers']) ? $rental['tiers'] : [];
        $discounts = [];

        foreach ($tiers as $tier) {
            if (!is_array($tier)) {
                continue;
            }

            $min_days = absint($tier['min_days'] ?? 0);
            $max_raw = trim((string) ($tier['max_days'] ?? ''));
            $max_days = $max_raw === '' ? 9999 : absint($max_raw);
            if ($min_days < 1 || $max_days < $min_days) {
                continue;
            }

            $daily_raw = trim((string) ($tier['daily_price'] ?? ''));
            if ($daily_raw !== '') {
                $daily_price = self::decimal_value($daily_raw, false);
                if (is_wp_error($daily_price) || (float) $daily_price <= 0 || (float) $daily_price > (float) $base_price) {
                    return new WP_Error('trent_invalid_tier_price', 'Kontroller dagprisene i prisnivåene.', ['status' => 400]);
                }

                $discount_percent = 100 - (((float) $daily_price / (float) $base_price) * 100);
                if ($discount_percent <= 0.000001) {
                    continue;
                }

                $discounts[] = [
                    'min_days' => $min_days,
                    'max_days' => $max_days,
                    'discount_type' => 'percentage',
                    'discount_amount' => round($discount_percent, 6),
                ];
                continue;
            }

            // Preserve an existing RnB discount if the daily-price field was not changed.
            $raw_type = sanitize_key($tier['discount_type'] ?? '');
            $raw_amount = self::decimal_value($tier['discount_amount'] ?? '', true);
            if (
                in_array($raw_type, ['percentage', 'fixed'], true) &&
                !is_wp_error($raw_amount) &&
                (float) $raw_amount > 0
            ) {
                $discounts[] = [
                    'min_days' => $min_days,
                    'max_days' => $max_days,
                    'discount_type' => $raw_type,
                    'discount_amount' => (float) $raw_amount,
                ];
            }
        }

        update_post_meta($product_id, 'redq_price_discount_cost', $discounts);
        update_post_meta($product_id, 'rnb_show_price_type', 'daily');
        update_post_meta($product_id, 'redq_rental_local_show_price_discount_on_days', 'open');
        update_post_meta(
            $product_id,
            'redq_rental_local_show_request_quote',
            !empty($rental['request_quote']) ? 'open' : 'closed'
        );
        update_post_meta(
            $product_id,
            'redq_rental_local_show_book_now',
            !empty($rental['book_now']) ? 'open' : 'closed'
        );

        if ($creating) {
            foreach ([
                'rnb_settings_for_display',
                'rnb_settings_for_labels',
                'rnb_settings_for_conditions',
                'rnb_settings_for_validations',
            ] as $key) {
                update_post_meta($product_id, $key, 'global');
            }

            update_post_meta($product_id, 'redq_rental_local_show_pickup_date', 'open');
            update_post_meta($product_id, 'redq_rental_local_show_pickup_time', 'closed');
            update_post_meta($product_id, 'redq_rental_local_show_dropoff_date', 'open');
            update_post_meta($product_id, 'redq_rental_local_show_dropoff_time', 'closed');
            update_post_meta($product_id, 'rnb_include_trailing_date', 'open');
        }

        return true;
    }

    private static function link_inventory($product_id, $inventory_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rnb_inventory_product';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists !== $table) {
            return new WP_Error('trent_rnb_table_missing', 'RnB lagerkobling er ikke tilgjengelig.', ['status' => 500]);
        }

        $wpdb->delete($table, ['product' => $product_id], ['%d']);
        $inserted = $wpdb->insert(
            $table,
            ['inventory' => $inventory_id, 'product' => $product_id],
            ['%d', '%d']
        );

        if ($inserted === false) {
            return new WP_Error('trent_inventory_link_failed', 'Kunne ikke koble RnB-utstyr/lager til produktet.', ['status' => 500]);
        }

        update_post_meta($product_id, '_redq_product_inventory', [(string) $inventory_id]);

        return true;
    }

    private static function decimal_value($value, $allow_empty)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $allow_empty ? '' : new WP_Error('trent_number_required', 'Tallverdi mangler.', ['status' => 400]);
        }

        $decimal = wc_format_decimal($raw);
        if ($decimal === '' || !is_numeric($decimal) || (float) $decimal < 0) {
            return new WP_Error('trent_invalid_number', 'Ugyldig tallverdi.', ['status' => 400]);
        }

        return $decimal;
    }

    private static function id_list($values)
    {
        if (!is_array($values)) {
            return [];
        }

        $ids = array_map('absint', $values);
        return array_values(array_unique(array_filter($ids)));
    }

    private static function text_list($values)
    {
        if (is_string($values)) {
            $values = explode(',', $values);
        }
        if (!is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            $value = sanitize_text_field($value);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }

    private static function current_inventory_ids($product_id)
    {
        if (function_exists('rnb_get_product_inventory_id')) {
            $ids = rnb_get_product_inventory_id($product_id);
            if (is_array($ids)) {
                return self::id_list($ids);
            }
        }

        $meta = get_post_meta($product_id, '_redq_product_inventory', true);
        return self::id_list(is_array($meta) ? $meta : []);
    }

    private static function format_product($product)
    {
        $id = $product->get_id();
        $type = $product->get_type();
        $post = get_post($id);

        $type_labels = [
            'redq_rental' => 'RnB utleie',
            'simple' => 'Enkelt produkt',
            'variable' => 'Variabelt produkt',
            'grouped' => 'Gruppert produkt',
            'external' => 'Eksternt produkt',
        ];

        $status_labels = [
            'publish' => 'Publisert',
            'draft' => 'Kladd',
            'private' => 'Privat',
            'pending' => 'Venter',
        ];

        $category_ids = wp_get_post_terms($id, 'product_cat', ['fields' => 'ids']);
        $category_ids = is_wp_error($category_ids) ? [] : array_map('intval', $category_ids);

        $tag_names = wp_get_post_terms($id, 'product_tag', ['fields' => 'names']);
        $tag_names = is_wp_error($tag_names) ? [] : array_values($tag_names);

        $featured_id = (int) $product->get_image_id();
        $gallery_ids = method_exists($product, 'get_gallery_image_ids')
            ? array_map('intval', $product->get_gallery_image_ids())
            : self::id_list(explode(',', (string) get_post_meta($id, '_product_image_gallery', true)));

        $inventory_ids = $type === 'redq_rental' ? self::current_inventory_ids($id) : [];
        $inventory_id = !empty($inventory_ids) ? (int) reset($inventory_ids) : 0;
        $base_price = $inventory_id ? (string) get_post_meta($inventory_id, 'general_price', true) : '';
        $quantity = $inventory_id ? max(1, (int) get_post_meta($inventory_id, 'quantity', true)) : 1;

        $discounts = get_post_meta($id, 'redq_price_discount_cost', true);
        $discounts = is_array($discounts) ? $discounts : [];
        $tiers = [];

        foreach ($discounts as $discount) {
            if (!is_array($discount)) {
                continue;
            }

            $discount_type = sanitize_key($discount['discount_type'] ?? 'percentage');
            $discount_amount = (float) ($discount['discount_amount'] ?? 0);
            $daily_price = '';

            if ($discount_type === 'percentage' && (float) $base_price > 0) {
                $daily_price = wc_format_decimal(
                    (float) $base_price * (1 - ($discount_amount / 100)),
                    wc_get_price_decimals()
                );
            }

            $max_days = absint($discount['max_days'] ?? 0);
            $tiers[] = [
                'min_days' => absint($discount['min_days'] ?? 0),
                'max_days' => $max_days >= 9999 ? '' : $max_days,
                'daily_price' => $daily_price,
                'discount_type' => $discount_type,
                'discount_amount' => $discount_amount,
            ];
        }

        return [
            'id' => $id,
            'name' => $product->get_name(),
            'slug' => $post ? $post->post_name : '',
            'description' => $post ? $post->post_content : '',
            'short_description' => $post ? $post->post_excerpt : '',
            'status' => $product->get_status(),
            'status_label' => $status_labels[$product->get_status()] ?? $product->get_status(),
            'type' => $type,
            'type_label' => $type_labels[$type] ?? $type,
            'sku' => $product->get_sku(),
            'regular_price' => $product->get_regular_price(),
            'price' => $product->get_price(),
            'stock_status' => $product->get_stock_status(),
            'image' => $featured_id ? esc_url_raw(wp_get_attachment_image_url($featured_id, 'thumbnail')) : '',
            'featured' => $featured_id ? self::format_media($featured_id) : null,
            'gallery' => array_values(array_filter(array_map([__CLASS__, 'format_media'], $gallery_ids))),
            'category_ids' => $category_ids,
            'tag_names' => $tag_names,
            'permalink' => esc_url_raw(get_permalink($id)),
            'admin_edit_url' => esc_url_raw(admin_url('post.php?post=' . $id . '&action=edit')),
            'deposit' => [
                'enabled' => get_post_meta($id, '_awcdp_deposit_enabled', true) === 'yes',
                'type' => get_post_meta($id, '_awcdp_deposit_type', true) ?: 'fixed',
                'amount' => (string) get_post_meta($id, '_awcdp_deposits_deposit_amount', true),
            ],
            'rental' => [
                'inventory_ids' => $inventory_ids,
                'inventory_id' => $inventory_id,
                'inventory_name' => $inventory_id ? get_the_title($inventory_id) : '',
                'base_price' => $base_price,
                'quantity' => $quantity,
                'tiers' => $tiers,
                'request_quote' => get_post_meta($id, 'redq_rental_local_show_request_quote', true) !== 'closed',
                'book_now' => get_post_meta($id, 'redq_rental_local_show_book_now', true) === 'open',
            ],
        ];
    }

    public static function format_media($attachment_id)
    {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0 || !wp_attachment_is_image($attachment_id)) {
            return null;
        }

        $url = wp_get_attachment_image_url($attachment_id, 'large');
        $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');

        return [
            'id' => $attachment_id,
            'url' => $url ? esc_url_raw($url) : '',
            'thumb' => $thumb ? esc_url_raw($thumb) : ($url ? esc_url_raw($url) : ''),
            'alt' => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        ];
    }

    private static function clear_product_cache($product_id)
    {
        clean_post_cache($product_id);
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
    }
}

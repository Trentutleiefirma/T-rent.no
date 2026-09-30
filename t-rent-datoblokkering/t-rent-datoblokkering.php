<?php
/**
 * Plugin Name: T-Rent Datoblokkering
 * Description: Enkel manuell blokkering av utleiedatoer for T-Rent. Oppretter RnB CUSTOM-blokkeringer uten at klokkeslett må angis.
 * Version: 1.0.0
 * Author: T-Rent
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Date_Blocker
{
    const PAGE_SLUG = 't-rent-datoblokkering';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_bar_menu', [__CLASS__, 'register_admin_bar_link'], 90);
        add_action('admin_post_trent_add_date_block', [__CLASS__, 'handle_add_block']);
        add_action('admin_post_trent_remove_date_block', [__CLASS__, 'handle_remove_block']);
    }

    public static function register_menu()
    {
        add_menu_page(
            'Blokker dato',
            'Blokker dato',
            'manage_woocommerce',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-calendar-alt',
            56.5
        );
    }

    public static function register_admin_bar_link($wp_admin_bar)
    {
        if (!is_user_logged_in() || !current_user_can('manage_woocommerce')) {
            return;
        }

        $wp_admin_bar->add_node([
            'id'    => 'trent-date-blocker',
            'title' => 'Blokker dato',
            'href'  => admin_url('admin.php?page=' . self::PAGE_SLUG),
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
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));

        return $found === $table;
    }

    private static function redirect_with_status($status, $count = 0)
    {
        $url = add_query_arg(
            [
                'page'               => self::PAGE_SLUG,
                'trent_block_status' => sanitize_key($status),
                'trent_block_count'  => absint($count),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    private static function parse_date($value)
    {
        $value = sanitize_text_field(wp_unslash($value));
        $timezone = wp_timezone();

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
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

    private static function get_rental_products()
    {
        return get_posts([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => ['redq_rental'],
                ],
            ],
        ]);
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
            $default_inventory = rnb_get_default_inventory_id($product_id);
            if ($default_inventory) {
                $inventory_ids = [$default_inventory];
            }
        }

        $inventory_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', (array) $inventory_ids)
                )
            )
        );

        return $inventory_ids;
    }

    private static function is_rental_product($product_id)
    {
        $product_id = absint($product_id);

        return (
            $product_id > 0 &&
            get_post_type($product_id) === 'product' &&
            has_term('redq_rental', 'product_type', $product_id)
        );
    }

    public static function handle_add_block()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Du har ikke tilgang til å blokkere datoer.');
        }

        check_admin_referer('trent_add_date_block');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $from_value = isset($_POST['from_date']) ? $_POST['from_date'] : '';
        $to_value   = isset($_POST['to_date']) ? $_POST['to_date'] : '';

        if (!self::is_rental_product($product_id)) {
            self::redirect_with_status('invalid_product');
        }

        $from = self::parse_date($from_value);
        $to   = self::parse_date($to_value);

        if (!$from || !$to || $to < $from) {
            self::redirect_with_status('invalid_date');
        }

        $table = self::availability_table();
        if (!self::table_exists($table)) {
            self::redirect_with_status('rnb_missing');
        }

        $inventory_ids = self::get_product_inventories($product_id);
        if (empty($inventory_ids)) {
            self::redirect_with_status('no_inventory');
        }

        /*
         * RnB's CUSTOM-period logic treats the return moment as the point at
         * which availability starts again. For an inclusive date selection we
         * therefore store 00:00 on the day after the selected end date.
         *
         * Example: 2026-10-05 to 2026-10-05 becomes
         * 2026-10-05 00:00:00 -> 2026-10-06 00:00:00.
         */
        $pickup_datetime = $from->format('Y-m-d 00:00:00');
        $return_boundary = $to->modify('+1 day');
        $return_datetime = $return_boundary->format('Y-m-d 00:00:00');
        $rental_duration = (string) max(1, (int) $from->diff($return_boundary)->days);

        global $wpdb;

        $created = 0;
        $created_ids = [];
        $failed = false;
        $now = current_time('mysql');

        foreach ($inventory_ids as $inventory_id) {
            $existing_id = $wpdb->get_var(
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

            if ($existing_id) {
                continue;
            }

            $inserted = $wpdb->insert(
                $table,
                [
                    'pickup_datetime' => $pickup_datetime,
                    'return_datetime' => $return_datetime,
                    'rental_duration' => $rental_duration,
                    'product_id'      => $product_id,
                    'inventory_id'    => $inventory_id,
                    'order_id'        => null,
                    'item_id'         => null,
                    'lang'            => get_locale(),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                    'block_by'        => 'CUSTOM',
                    'delete_status'   => 0,
                ],
                [
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%d',
                ]
            );

            if ($inserted === false) {
                $failed = true;
                break;
            }

            $created++;
            $created_ids[] = absint($wpdb->insert_id);
        }

        if ($failed) {
            foreach ($created_ids as $created_id) {
                if ($created_id) {
                    $wpdb->delete(
                        $table,
                        [
                            'id'       => $created_id,
                            'block_by' => 'CUSTOM',
                        ],
                        [
                            '%d',
                            '%s',
                        ]
                    );
                }
            }

            self::redirect_with_status('database_error');
        }

        if ($created === 0) {
            self::redirect_with_status('already_exists');
        }

        self::redirect_with_status('added', $created);
    }

    public static function handle_remove_block()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Du har ikke tilgang til å fjerne blokkeringer.');
        }

        check_admin_referer('trent_remove_date_block');

        $raw_ids = isset($_POST['block_ids'])
            ? sanitize_text_field(wp_unslash($_POST['block_ids']))
            : '';

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', explode(',', $raw_ids))
                )
            )
        );

        if (empty($ids)) {
            self::redirect_with_status('invalid_block');
        }

        $table = self::availability_table();
        if (!self::table_exists($table)) {
            self::redirect_with_status('rnb_missing');
        }

        global $wpdb;

        $removed = 0;
        $now = current_time('mysql');

        foreach ($ids as $id) {
            $updated = $wpdb->update(
                $table,
                [
                    'delete_status' => 1,
                    'updated_at'    => $now,
                ],
                [
                    'id'       => $id,
                    'block_by' => 'CUSTOM',
                ],
                [
                    '%d',
                    '%s',
                ],
                [
                    '%d',
                    '%s',
                ]
            );

            if ($updated) {
                $removed++;
            }
        }

        self::redirect_with_status($removed > 0 ? 'removed' : 'invalid_block', $removed);
    }

    private static function get_active_blocks()
    {
        global $wpdb;

        $table = self::availability_table();
        if (!self::table_exists($table)) {
            return [];
        }

        $now = current_time('mysql');

        return $wpdb->get_results(
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
                $now
            ),
            ARRAY_A
        );
    }

    private static function display_date($mysql_datetime)
    {
        try {
            $date = new DateTimeImmutable($mysql_datetime, wp_timezone());
            return wp_date('d.m.Y', $date->getTimestamp(), wp_timezone());
        } catch (Exception $e) {
            return $mysql_datetime;
        }
    }

    private static function display_inclusive_end_date($pickup_datetime, $return_datetime)
    {
        try {
            $pickup = new DateTimeImmutable($pickup_datetime, wp_timezone());
            $return = new DateTimeImmutable($return_datetime, wp_timezone());

            if (
                $return > $pickup &&
                $return->format('H:i:s') === '00:00:00'
            ) {
                $return = $return->modify('-1 day');
            }

            return wp_date('d.m.Y', $return->getTimestamp(), wp_timezone());
        } catch (Exception $e) {
            return $return_datetime;
        }
    }

    private static function render_status_notice()
    {
        $status = isset($_GET['trent_block_status'])
            ? sanitize_key(wp_unslash($_GET['trent_block_status']))
            : '';

        if (!$status) {
            return;
        }

        $messages = [
            'added'          => ['success', 'Datoen er blokkert. Den blir rød og kan ikke bookes.'],
            'removed'        => ['success', 'Blokkeringen er fjernet. Datoen er tilgjengelig igjen dersom ingen booking blokkerer den.'],
            'already_exists' => ['warning', 'Denne datoen er allerede manuelt blokkert.'],
            'invalid_product'=> ['error', 'Velg et gyldig utleieprodukt.'],
            'invalid_date'   => ['error', 'Kontroller fra- og til-dato. Til-dato kan ikke være før fra-dato.'],
            'no_inventory'   => ['error', 'Produktet har ingen tilkoblet RnB-inventory og kan derfor ikke blokkeres.'],
            'rnb_missing'    => ['error', 'RnB-tabellen ble ikke funnet. Kontroller at WooCommerce Booking & Rental System er aktivert.'],
            'invalid_block'  => ['error', 'Blokkeringen kunne ikke fjernes.'],
            'database_error' => ['error', 'Blokkeringen kunne ikke lagres. Ingen delvis blokkering ble beholdt.'],
        ];

        if (!isset($messages[$status])) {
            return;
        }

        [$type, $message] = $messages[$status];

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $rnb_ready = defined('RNB_VERSION') && self::table_exists(self::availability_table());
        $products = self::get_rental_products();
        $blocks = self::get_active_blocks();
        $today = wp_date('Y-m-d');
        ?>
        <div class="wrap trent-date-blocker">
            <h1>Blokker dato</h1>

            <?php self::render_status_notice(); ?>

            <?php if (!$rnb_ready) : ?>
                <div class="notice notice-error">
                    <p><strong>RnB er ikke tilgjengelig.</strong> Aktiver WooCommerce Booking &amp; Rental System før du bruker denne siden.</p>
                </div>
            <?php endif; ?>

            <div class="trent-block-card">
                <h2>Legg inn manuell blokkering</h2>
                <p class="description">
                    Velg produkt og dato. Du trenger ikke angi klokkeslett.
                    Samme dato i begge felt blokkerer én hel dag.
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="trent_add_date_block">
                    <?php wp_nonce_field('trent_add_date_block'); ?>

                    <div class="trent-block-grid">
                        <div class="trent-field trent-product-field">
                            <label for="trent-product-id">Produkt</label>
                            <select id="trent-product-id" name="product_id" required <?php disabled(!$rnb_ready); ?>>
                                <option value="">Velg produkt</option>
                                <?php foreach ($products as $product_id) : ?>
                                    <option value="<?php echo esc_attr($product_id); ?>">
                                        <?php echo esc_html(get_the_title($product_id)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="trent-field">
                            <label for="trent-from-date">Fra og med</label>
                            <input
                                type="date"
                                id="trent-from-date"
                                name="from_date"
                                value="<?php echo esc_attr($today); ?>"
                                required
                                <?php disabled(!$rnb_ready); ?>
                            >
                        </div>

                        <div class="trent-field">
                            <label for="trent-to-date">Til og med</label>
                            <input
                                type="date"
                                id="trent-to-date"
                                name="to_date"
                                value="<?php echo esc_attr($today); ?>"
                                required
                                <?php disabled(!$rnb_ready); ?>
                            >
                        </div>

                        <div class="trent-field trent-submit-field">
                            <button type="submit" class="button button-primary button-large" <?php disabled(!$rnb_ready); ?>>
                                Blokker
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="trent-block-card">
                <h2>Aktive manuelle blokkeringer</h2>

                <?php if (empty($blocks)) : ?>
                    <p>Ingen aktive manuelle blokkeringer.</p>
                <?php else : ?>
                    <div class="trent-table-wrap">
                        <table class="widefat striped trent-block-table">
                            <thead>
                                <tr>
                                    <th>Produkt</th>
                                    <th>Fra</th>
                                    <th>Til</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blocks as $block) : ?>
                                    <?php
                                    $product_id = absint($block['product_id']);
                                    $title = $product_id ? get_the_title($product_id) : '';
                                    if (!$title) {
                                        $title = $product_id ? 'Produkt #' . $product_id : 'Ukjent produkt';
                                    }
                                    ?>
                                    <tr>
                                        <td data-label="Produkt">
                                            <strong><?php echo esc_html($title); ?></strong>
                                        </td>
                                        <td data-label="Fra">
                                            <?php echo esc_html(self::display_date($block['pickup_datetime'])); ?>
                                        </td>
                                        <td data-label="Til">
                                            <?php
                                            echo esc_html(
                                                self::display_inclusive_end_date(
                                                    $block['pickup_datetime'],
                                                    $block['return_datetime']
                                                )
                                            );
                                            ?>
                                        </td>
                                        <td class="trent-remove-cell">
                                            <form
                                                method="post"
                                                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                                onsubmit="return confirm('Fjerne denne blokkeringen?');"
                                            >
                                                <input type="hidden" name="action" value="trent_remove_date_block">
                                                <input
                                                    type="hidden"
                                                    name="block_ids"
                                                    value="<?php echo esc_attr($block['block_ids']); ?>"
                                                >
                                                <?php wp_nonce_field('trent_remove_date_block'); ?>
                                                <button type="submit" class="button">
                                                    Fjern
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .trent-date-blocker {
                max-width: 1050px;
            }

            .trent-block-card {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 10px;
                padding: 20px;
                margin: 18px 0;
                box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
            }

            .trent-block-card h2 {
                margin-top: 0;
            }

            .trent-block-grid {
                display: grid;
                grid-template-columns: minmax(260px, 2fr) minmax(160px, 1fr) minmax(160px, 1fr) auto;
                gap: 14px;
                align-items: end;
                margin-top: 18px;
            }

            .trent-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }

            .trent-field select,
            .trent-field input[type="date"] {
                width: 100%;
                min-height: 42px;
            }

            .trent-submit-field .button {
                min-height: 42px;
                padding-left: 22px;
                padding-right: 22px;
            }

            .trent-remove-cell {
                width: 100px;
                text-align: right;
            }

            @media (max-width: 850px) {
                .trent-block-grid {
                    grid-template-columns: 1fr 1fr;
                }

                .trent-product-field,
                .trent-submit-field {
                    grid-column: 1 / -1;
                }

                .trent-submit-field .button {
                    width: 100%;
                }
            }

            @media (max-width: 600px) {
                .trent-block-card {
                    padding: 14px;
                }

                .trent-block-grid {
                    grid-template-columns: 1fr;
                }

                .trent-product-field,
                .trent-submit-field {
                    grid-column: auto;
                }

                .trent-block-table,
                .trent-block-table tbody,
                .trent-block-table tr,
                .trent-block-table td {
                    display: block;
                    width: 100%;
                }

                .trent-block-table thead {
                    display: none;
                }

                .trent-block-table tr {
                    padding: 10px 0;
                    border-bottom: 1px solid #dcdcde;
                }

                .trent-block-table td {
                    box-sizing: border-box;
                    border: 0;
                    padding: 7px 10px;
                }

                .trent-block-table td::before {
                    content: attr(data-label);
                    display: block;
                    color: #646970;
                    font-size: 12px;
                    font-weight: 600;
                    margin-bottom: 2px;
                }

                .trent-remove-cell {
                    text-align: left;
                }

                .trent-remove-cell .button {
                    width: 100%;
                }
            }
        </style>

        <script>
            (function () {
                var from = document.getElementById('trent-from-date');
                var to = document.getElementById('trent-to-date');

                if (!from || !to) {
                    return;
                }

                function syncEndDate() {
                    to.min = from.value;

                    if (!to.value || to.value < from.value) {
                        to.value = from.value;
                    }
                }

                from.addEventListener('change', syncEndDate);
                syncEndDate();
            }());
        </script>
        <?php
    }
}

TRent_Date_Blocker::init();

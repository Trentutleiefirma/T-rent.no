<?php
/**
 * Plugin Name: T-Rent Admin App
 * Description: Mobilvennlig front-end app for sikker administrasjon av T-Rent WooCommerce uten wp-admin.
 * Version: 0.2.0
 * Author: T-Rent
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App
{
    const VERSION = '0.2.0';
    const QUERY_VAR = 'trent_app';
    const REST_NAMESPACE = 't-rent-app/v1';

    public static function init()
    {
        add_action('init', [__CLASS__, 'register_route']);
        add_filter('query_vars', [__CLASS__, 'register_query_var']);
        add_action('template_redirect', [__CLASS__, 'render_app']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar']);
    }

    public static function activate()
    {
        self::register_route();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    public static function register_route()
    {
        add_rewrite_rule('^t-rent-app/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top');
    }

    public static function register_query_var($vars)
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public static function hide_admin_bar($show)
    {
        return ((int) get_query_var(self::QUERY_VAR) === 1) ? false : $show;
    }

    private static function can_manage()
    {
        return is_user_logged_in() && (
            current_user_can('manage_woocommerce') ||
            current_user_can('edit_products')
        );
    }

    public static function rest_permission()
    {
        return self::can_manage();
    }

    public static function render_app()
    {
        if ((int) get_query_var(self::QUERY_VAR) !== 1) {
            return;
        }

        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        if (!self::can_manage()) {
            status_header(403);
            wp_die('Du har ikke tilgang til T-Rent Admin App.', 'Ingen tilgang', ['response' => 403]);
        }

        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        $config = [
            'restBase' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'logoutUrl' => esc_url_raw(wp_logout_url(home_url('/t-rent-app/'))),
            'today' => wp_date('Y-m-d'),
        ];

        $user = wp_get_current_user();
        $base = plugin_dir_url(__FILE__);
        ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>T-Rent App</title>
    <link rel="stylesheet" href="<?php echo esc_url($base . 'assets/app.css?ver=' . self::VERSION); ?>">
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div>
            <div class="brand">T-RENT APP</div>
            <div class="sub">Booking, utstyr og WooCommerce</div>
        </div>
        <div class="top-actions">
            <span class="sub"><?php echo esc_html($user->display_name); ?></span>
            <a class="btn secondary" href="<?php echo esc_url($config['logoutUrl']); ?>">Logg ut</a>
        </div>
    </header>

    <nav class="app-nav" aria-label="T-Rent App">
        <button class="nav-btn active" data-view="bookings" type="button">Bookinger</button>
        <button class="nav-btn" data-view="blocks" type="button">Blokker dato</button>
        <button class="nav-btn" data-view="equipment" type="button">Utstyr</button>
        <button class="nav-btn" data-view="products" type="button">Produkter</button>
    </nav>

    <div id="notice" class="notice"></div>

    <section id="view-bookings" class="view active">
        <div class="card section-toolbar">
            <div>
                <div class="section-title compact">Bookinger</div>
                <div class="hint">Leiedato, dato bookingen ble lagt inn og kundens kontaktinformasjon.</div>
            </div>
            <div class="toolbar-actions">
                <select id="bookingFilter" class="small-select">
                    <option value="open">Aktive + kommende</option>
                    <option value="all">Alle</option>
                    <option value="active">Ute nå</option>
                    <option value="upcoming">Kommende</option>
                    <option value="completed">Avsluttet</option>
                </select>
                <button id="bookingRefresh" class="btn secondary" type="button">Oppdater</button>
            </div>
        </div>
        <div id="bookingList"><div class="card empty">Laster bookinger ...</div></div>
    </section>

    <section id="view-blocks" class="view">
        <div class="card block-form-card">
            <div class="section-title">Blokker dato</div>
            <div class="hint">Samme funksjon som i «Utleie system». Blokkeringen lagres i RnB og gjør datoen utilgjengelig.</div>
            <form id="blockForm" class="block-form">
                <div class="field">
                    <label for="blockProduct">Produkt</label>
                    <select id="blockProduct" required>
                        <option value="">Laster produkter ...</option>
                    </select>
                </div>
                <div class="field">
                    <label for="blockFrom">Fra og med</label>
                    <input id="blockFrom" type="date" required>
                </div>
                <div class="field">
                    <label for="blockTo">Til og med</label>
                    <input id="blockTo" type="date" required>
                </div>
                <div class="field button-field">
                    <label>&nbsp;</label>
                    <button class="btn" type="submit">Blokker dato</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="list-head row-head">
                <span>Aktive manuelle blokkeringer</span>
                <button id="blockRefresh" class="btn secondary small-btn" type="button">Oppdater</button>
            </div>
            <div id="blockList"><div class="empty">Laster blokkeringer ...</div></div>
        </div>
    </section>

    <section id="view-equipment" class="view">
        <div id="equipmentCounts" class="status-grid"></div>
        <div id="equipmentList"><div class="card empty">Laster utstyr ...</div></div>
    </section>

    <section id="view-products" class="view">
        <div class="card toolbar">
            <input id="search" class="search" type="search" placeholder="Søk etter produkt ..." autocomplete="off">
            <button id="refresh" class="btn secondary" type="button">Oppdater</button>
        </div>

        <div class="grid">
            <section class="card list">
                <div class="list-head">Produkter</div>
                <div id="productList"><div class="empty">Laster produkter ...</div></div>
            </section>

            <section class="card editor" id="editor">
                <div class="empty">Velg et produkt for å redigere.</div>
            </section>
        </div>
    </section>
</div>

<script>window.TRentApp = <?php echo wp_json_encode($config); ?>;</script>
<script src="<?php echo esc_url($base . 'assets/app.js?ver=' . self::VERSION); ?>"></script>
</body>
</html>
        <?php
        exit;
    }

    public static function register_rest_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/products', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'rest_products'],
            'permission_callback' => [__CLASS__, 'rest_permission'],
            'args' => [
                'search' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => '',
                ],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/product/(?P<id>\\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'rest_product'],
                'permission_callback' => [__CLASS__, 'rest_permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [__CLASS__, 'rest_update_product'],
                'permission_callback' => [__CLASS__, 'rest_permission'],
            ],
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
            'posts_per_page' => 60,
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

        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];

        if (array_key_exists('name', $data)) {
            $name = sanitize_text_field($data['name']);
            if ($name === '') {
                return new WP_Error('trent_name_required', 'Produktnavn kan ikke være tomt.', ['status' => 400]);
            }
            $product->set_name($name);
        }

        if (array_key_exists('status', $data)) {
            $status = sanitize_key($data['status']);
            if (!in_array($status, ['publish', 'draft', 'private'], true)) {
                return new WP_Error('trent_invalid_status', 'Ugyldig produktstatus.', ['status' => 400]);
            }
            $product->set_status($status);
        }

        $validated_price = null;
        if (array_key_exists('regular_price', $data) && $data['regular_price'] !== null) {
            if ($product->get_type() === 'redq_rental') {
                return new WP_Error('trent_rental_price_locked', 'RnB-leiepris er låst i denne versjonen av appen.', ['status' => 400]);
            }

            $raw_price = trim((string) $data['regular_price']);
            if ($raw_price === '') {
                $validated_price = '';
            } else {
                $validated_price = wc_format_decimal($raw_price);
                if ($validated_price === '' || (float) $validated_price < 0) {
                    return new WP_Error('trent_invalid_price', 'Ugyldig grunnpris.', ['status' => 400]);
                }
            }
        }

        $deposit_type = null;
        if (array_key_exists('deposit_type', $data)) {
            $deposit_type = sanitize_key($data['deposit_type']);
            if (!in_array($deposit_type, ['fixed', 'percent'], true)) {
                return new WP_Error('trent_invalid_deposit_type', 'Ugyldig depositumtype.', ['status' => 400]);
            }
        }

        $deposit_amount = null;
        if (array_key_exists('deposit_amount', $data)) {
            $raw_amount = trim((string) $data['deposit_amount']);
            if ($raw_amount === '') {
                $deposit_amount = '';
            } else {
                $deposit_amount = wc_format_decimal($raw_amount);
                if ($deposit_amount === '' || (float) $deposit_amount < 0) {
                    return new WP_Error('trent_invalid_deposit_amount', 'Ugyldig depositumbeløp.', ['status' => 400]);
                }
            }
        }

        if ($validated_price !== null) {
            $product->set_regular_price($validated_price);
            $product->set_price($validated_price);
        }

        try {
            $product->save();
        } catch (Throwable $e) {
            return new WP_Error('trent_save_failed', 'Kunne ikke lagre WooCommerce-produktet.', ['status' => 500]);
        }

        if (array_key_exists('deposit_enabled', $data)) {
            update_post_meta(
                $product_id,
                '_awcdp_deposit_enabled',
                rest_sanitize_boolean($data['deposit_enabled']) ? 'yes' : 'no'
            );
        }

        if ($deposit_type !== null) {
            update_post_meta($product_id, '_awcdp_deposit_type', $deposit_type);
        }

        if ($deposit_amount !== null) {
            update_post_meta($product_id, '_awcdp_deposits_deposit_amount', $deposit_amount);
        }

        clean_post_cache($product_id);
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }

        $fresh = wc_get_product($product_id);
        return rest_ensure_response(self::format_product($fresh ?: $product));
    }

    private static function format_product($product)
    {
        $id = $product->get_id();
        $type = $product->get_type();
        $image = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');

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

        return [
            'id' => $id,
            'name' => $product->get_name(),
            'status' => $product->get_status(),
            'status_label' => $status_labels[$product->get_status()] ?? $product->get_status(),
            'type' => $type,
            'type_label' => $type_labels[$type] ?? $type,
            'sku' => $product->get_sku(),
            'regular_price' => $product->get_regular_price(),
            'price' => $product->get_price(),
            'image' => $image ? esc_url_raw($image) : '',
            'permalink' => esc_url_raw(get_permalink($id)),
            'deposit' => [
                'enabled' => get_post_meta($id, '_awcdp_deposit_enabled', true) === 'yes',
                'type' => get_post_meta($id, '_awcdp_deposit_type', true) ?: 'fixed',
                'amount' => (string) get_post_meta($id, '_awcdp_deposits_deposit_amount', true),
            ],
        ];
    }
}

require_once __DIR__ . '/includes/class-trent-app-rental.php';
require_once __DIR__ . '/includes/class-trent-app-bookings.php';
require_once __DIR__ . '/includes/class-trent-app-date-blocks.php';
require_once __DIR__ . '/includes/class-trent-app-equipment.php';

TRent_Admin_App::init();

register_activation_hook(__FILE__, ['TRent_Admin_App', 'activate']);
register_deactivation_hook(__FILE__, ['TRent_Admin_App', 'deactivate']);

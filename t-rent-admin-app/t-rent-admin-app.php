<?php
/**
 * Plugin Name: T-Rent Admin App
 * Description: Mobilvennlig front-end app for sikker administrasjon av T-Rent WooCommerce uten wp-admin.
 * Version: 0.6.0
 * Author: T-Rent
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App
{
    const VERSION = '0.6.0';
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

    private static function pwa_app_path()
    {
        $path = wp_make_link_relative(home_url('/t-rent-app/'));
        return $path !== '' ? $path : '/t-rent-app/';
    }

    private static function render_pwa_manifest()
    {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow', true);

        $app_path = self::pwa_app_path();
        // Reuse WordPress' configured Site Icon instead of shipping separate binary icon files.
        // WordPress generates the standard icon sizes from the Site Icon attachment.
        $icon_192_url = esc_url_raw(get_site_icon_url(192));
        $icon_512_url = esc_url_raw(get_site_icon_url(512));

        echo wp_json_encode([
            'id' => $app_path,
            'name' => 'T-RENT APP',
            'short_name' => 'T-RENT',
            'description' => 'Administrasjon av bookinger, utstyr og produkter for T-Rent.',
            'start_url' => $app_path . '?source=pwa',
            'scope' => $app_path,
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#f4f6f8',
            'theme_color' => '#111827',
            'prefer_related_applications' => false,
            'icons' => [
                [
                    'src' => $icon_192_url,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon_512_url,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function render_pwa_service_worker()
    {
        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . self::pwa_app_path());
        header('X-Robots-Tag: noindex, nofollow', true);

        echo "self.addEventListener('install',function(){self.skipWaiting();});\n";
        echo "self.addEventListener('activate',function(event){event.waitUntil(self.clients.claim());});\n";
        echo "self.addEventListener('fetch',function(event){if(event.request.method!=='GET'){return;}event.respondWith(fetch(event.request));});\n";
        exit;
    }

    public static function render_app()
    {
        if ((int) get_query_var(self::QUERY_VAR) !== 1) {
            return;
        }

        $pwa_asset = isset($_GET['trent_pwa']) ? sanitize_key(wp_unslash($_GET['trent_pwa'])) : '';
        if ($pwa_asset === 'manifest') {
            self::render_pwa_manifest();
        }
        if ($pwa_asset === 'sw') {
            self::render_pwa_service_worker();
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
            'serviceWorkerUrl' => esc_url_raw(home_url('/t-rent-app/?trent_pwa=sw')),
            'appScope' => self::pwa_app_path(),
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
    <meta name="theme-color" content="#111827">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="T-RENT APP">
    <title>T-Rent App</title>
    <link rel="manifest" href="<?php echo esc_url(home_url('/t-rent-app/?trent_pwa=manifest')); ?>">
    <link rel="icon" type="image/png" href="<?php echo esc_url(get_site_icon_url(192)); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(get_site_icon_url(180)); ?>">
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
            <button id="installApp" class="btn secondary" type="button" hidden>Installer app</button>
            <a class="btn secondary" href="<?php echo esc_url($config['logoutUrl']); ?>">Logg ut</a>
        </div>
    </header>

    <nav class="app-nav" aria-label="T-Rent App">
        <button class="nav-btn active" data-view="bookings" type="button">Bookinger</button>
        <button class="nav-btn" data-view="quotes" type="button">Forespørsler</button>
        <button class="nav-btn" data-view="blocks" type="button">Blokker dato</button>
        <button class="nav-btn" data-view="equipment" type="button">Utstyrskontroll</button>
        <button class="nav-btn" data-view="products" type="button">Produkter</button>
    </nav>

    <div class="card global-search">
        <input id="globalSearch" class="search" type="search" placeholder="Søk i bookinger ..." autocomplete="off">
        <button id="globalSearchClear" class="btn secondary" type="button">Tøm</button>
    </div>

    <div id="notice" class="notice"></div>

    <section id="view-bookings" class="view active">
        <div class="card section-toolbar">
            <div>
                <div class="section-title compact">Bookinger</div>
                <div class="hint">Leiedato, dato bookingen ble lagt inn og kundens kontaktinformasjon.</div>
            </div>
            <div class="toolbar-actions">
                <select id="bookingFilter" class="small-select">
                    <option value="open">Pågående + pause + kommende</option>
                    <option value="all">Alle</option>
                    <option value="ongoing">Pågående</option>
                    <option value="paused">På pause</option>
                    <option value="upcoming">Kommende</option>
                    <option value="completed">Fullført</option>
                </select>
                <button id="bookingRefresh" class="btn secondary" type="button">Oppdater</button>
            </div>
        </div>
        <div id="bookingList"><div class="card empty">Laster bookinger ...</div></div>
    </section>

    <section id="view-quotes" class="view">
        <div class="card section-toolbar">
            <div>
                <div class="section-title compact">Forespørsler</div>
                <div class="hint">Godkjenn eller avslå RnB-forespørsler. Interne kommentarer lagres i T-Rent App.</div>
            </div>
            <div class="toolbar-actions">
                <select id="quoteFilter" class="small-select">
                    <option value="open">Aktuelle forespørsler</option>
                    <option value="all">Alle</option>
                    <option value="quote-pending">Venter på svar</option>
                    <option value="quote-processing">Behandles</option>
                    <option value="quote-on-hold">På vent</option>
                    <option value="quote-accepted">Godkjent</option>
                    <option value="quote-cancelled">Avslått</option>
                    <option value="quote-completed">Fullført</option>
                </select>
                <button id="quoteRefresh" class="btn secondary" type="button">Oppdater</button>
            </div>
        </div>
        <div id="quoteList"><div class="card empty">Laster forespørsler ...</div></div>
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
        <div class="card toolbar product-toolbar">
            <div class="hint">Trykk på et produkt for å redigere, eller opprett et nytt produkt.</div>
            <div class="toolbar-actions">
                <button id="newProduct" class="btn" type="button">Nytt produkt</button>
                <button id="refresh" class="btn secondary" type="button">Oppdater</button>
            </div>
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
        TRent_Admin_App_Products::register_rest_routes(
            self::REST_NAMESPACE,
            [__CLASS__, 'rest_permission']
        );
    }

}

require_once __DIR__ . '/includes/class-trent-app-products.php';
require_once __DIR__ . '/includes/class-trent-app-rental.php';
require_once __DIR__ . '/includes/class-trent-app-bookings.php';
require_once __DIR__ . '/includes/class-trent-app-quotes.php';
require_once __DIR__ . '/includes/class-trent-app-date-blocks.php';
require_once __DIR__ . '/includes/class-trent-app-equipment.php';

TRent_Admin_App::init();

register_activation_hook(__FILE__, ['TRent_Admin_App', 'activate']);
register_deactivation_hook(__FILE__, ['TRent_Admin_App', 'deactivate']);

<?php
/**
 * Plugin Name: T-Rent Bestillingskontroll
 * Description: Fornyer sikkerhetskoder i RnB-skjemaer og viser en begrenset, anonym feillogg.
 * Version: 1.0.0
 * Author: T-Rent
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: t-rent-booking-health
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Booking_Health
{
    const VERSION = '1.0.0';
    const OPTION = 'trent_booking_health_events';
    const REFRESH = '_trent_booking_refresh';

    public static function init()
    {
        add_action('template_redirect', [__CLASS__, 'refresh_headers'], 0);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 100);
        add_action('wp_print_footer_scripts', [__CLASS__, 'repair_tracks_config'], 5);
        add_action('wp_verify_nonce_failed', [__CLASS__, 'record_nonce_failure'], 10, 4);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
    }

    public static function is_rental_page()
    {
        if (!function_exists('is_product') || !is_product() || !function_exists('wc_get_product')) {
            return false;
        }
        $product = wc_get_product(get_queried_object_id());
        return $product && $product->is_type('redq_rental');
    }

    public static function refresh_id()
    {
        $id = isset($_GET[self::REFRESH]) && is_scalar($_GET[self::REFRESH])
            ? (string) wp_unslash($_GET[self::REFRESH]) : '';
        return preg_match('/^[a-zA-Z0-9-]{12,80}$/D', $id) ? $id : '';
    }

    public static function refresh_headers()
    {
        if (self::refresh_id() === '' || !self::is_rental_page()) {
            return;
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }

    public static function enqueue()
    {
        if (!self::is_rental_page()) {
            return;
        }
        wp_enqueue_script('trent-booking-health', plugins_url('assets/booking-health.js', __FILE__), ['jquery'], self::VERSION, true);
        wp_localize_script('trent-booking-health', 'TRentBookingHealth', [
            'version' => self::VERSION,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'productUrl' => get_permalink(get_queried_object_id()),
            'generatedAt' => time(),
            'refreshId' => self::refresh_id(),
            'maxAgeMs' => 5 * MINUTE_IN_SECONDS * 1000,
        ]);
    }

    // WooPayments 11.2.1's standalone tracking bundle dereferences this object.
    // Asset optimizers can remove the other bundle that normally supplies it.
    public static function repair_tracks_config()
    {
        if (!self::is_rental_page() || !wp_script_is('wcpay-frontend-tracks', 'enqueued')) {
            return;
        }
        $scripts = wp_scripts();
        $registered = isset($scripts->registered['wcpay-frontend-tracks']) ? $scripts->registered['wcpay-frontend-tracks'] : null;
        if (!$registered || !is_string($registered->src)) {
            return;
        }
        $src = strtok($registered->src, '?');
        $url = trailingslashit(dirname($src));
        wp_add_inline_script('wcpay-frontend-tracks', 'window.wcpayAssets = window.wcpayAssets || ' . wp_json_encode(['url' => $url]) . ';', 'before');
    }

    public static function record_nonce_failure($nonce, $nonce_action, $user, $token)
    {
        static $recorded = false;
        if ($recorded || !in_array($nonce_action, ['rnb_ajax_nonce', 'rnb_rfq_nonce'], true)) {
            return;
        }
        $action = isset($_POST['action']) && is_scalar($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
        if (!in_array($action, ['rnb_calculate_inventory_data', 'rnb_get_inventory_data', 'redq_request_for_a_quote', 'rnb_quote_booking_data'], true)) {
            return;
        }
        $id = isset($_POST['product_id']) ? $_POST['product_id'] : 0;
        if (isset($_POST['form']) && is_array($_POST['form']) && isset($_POST['form']['add-to-cart'])) {
            $id = $_POST['form']['add-to-cart'];
        }
        $id = is_scalar($id) ? absint($id) : 0;
        $events = get_option(self::OPTION, []);
        $events = is_array($events) ? $events : [];
        $cutoff = time() - 14 * DAY_IN_SECONDS;
        $events = array_values(array_filter($events, static function ($event) use ($cutoff) {
            return is_array($event) && isset($event['at']) && (int) $event['at'] >= $cutoff;
        }));
        $events[] = ['at' => time(), 'action' => $action, 'product_id' => $id];
        update_option(self::OPTION, array_slice($events, -50), false);
        $recorded = true;
    }

    public static function admin_menu()
    {
        add_submenu_page('woocommerce', 'Bestillingskontroll', 'Bestillingskontroll', 'manage_woocommerce', 'trent-booking-health', [__CLASS__, 'admin_page']);
    }

    public static function admin_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        echo '<div class="wrap"><h1>T-Rent Bestillingskontroll</h1>';
        echo '<p>Registrerte avvisninger av sikkerhetskoder i RnB. En avvisning kan ha blitt rettet automatisk etterpå. Loggen inneholder tidspunkt, handling og produkt-ID.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Tidspunkt</th><th>Handling</th><th>Produkt-ID</th></tr></thead><tbody>';
        $events = get_option(self::OPTION, []);
        $events = is_array($events) ? $events : [];
        $shown = 0;
        foreach (array_reverse($events) as $event) {
            if (!is_array($event) || empty($event['at']) || (int) $event['at'] < time() - 14 * DAY_IN_SECONDS) {
                continue;
            }
            echo '<tr><td>' . esc_html(wp_date('d.m.Y H:i:s', (int) $event['at'])) . '</td><td>' . esc_html(isset($event['action']) ? $event['action'] : '') . '</td><td>' . (int) (isset($event['product_id']) ? $event['product_id'] : 0) . '</td></tr>';
            $shown++;
        }
        if (!$shown) {
            echo '<tr><td colspan="3">Ingen registrerte avvisninger de siste 14 dagene.</td></tr>';
        }
        echo '</tbody></table><p>Maksimalt 50 hendelser. Navn, e-post, IP-adresse, datoønsker og sikkerhetskoder lagres ikke.</p></div>';
    }

    public static function activate()
    {
        // Cached HTML must be rebuilt once so that the new script is included.
        do_action('cache_enabler_clear_complete_cache');
    }
}

TRent_Booking_Health::init();
register_activation_hook(__FILE__, ['TRent_Booking_Health', 'activate']);

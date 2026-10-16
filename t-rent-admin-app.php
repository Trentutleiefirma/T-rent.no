<?php
/**
 * Plugin Name: T-Rent Admin App
 * Description: Utstyrskontroll og manuell datoblokkering for T-Rents RnB-utleie.
 * Version: 2.2.0
 * Author: T-Rent
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: t-rent-admin-app
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('T_RENT_ADMIN_APP_VERSION')) {
    define('T_RENT_ADMIN_APP_VERSION', '2.2.0');
}
if (!defined('T_RENT_ADMIN_APP_FILE')) {
    define('T_RENT_ADMIN_APP_FILE', __FILE__);
}

require_once __DIR__ . '/includes/class-equipment-control.php';
require_once __DIR__ . '/includes/class-date-blocker.php';

add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p><strong>T-Rent Admin App krever WooCommerce.</strong> Aktiver WooCommerce før tillegget tas i bruk.</p></div>';
        });

        return;
    }

    if (!defined('RNB_VERSION')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-warning"><p><strong>T-Rent Admin App krever WooCommerce Booking &amp; Rental System (RnB).</strong> Utstyrskontroll og datoblokkering blir ikke operative før RnB er aktivert.</p></div>';
        });

        return;
    }

    new \TRent_Drift\Equipment_Control();
    \TRent_Drift\Date_Blocker::init();
}, 30);

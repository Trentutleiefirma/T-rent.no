<?php

/**
 * Plugin Name: WooCommerce Booking & Rental System
 * Plugin URI: https://codecanyon.net/item/rnb-woocommerce-rental-booking-system/14835145?ref=redqteam
 * Description: RnB – WooCommerce Booking & Rental is a user friendly woocommerce booking plugin built as woocommerce extension. This powerful woocommerce plugin allows you to sell your time or date based bookings. It creates a new product type to your WooCommerce site. Perfect for those wanting to offer rental , booking , or real estate agencies or services.
 * Version: 18.0.3
 * Author: RedQ Team
 * Author URI: https://redq.io/
 * License: http : //www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: redq-rental
 * Domain Path: /languages
 * WC requires at least: 8.0.0
 * WC tested up to: latest
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

$active_plugins = apply_filters('rnb_active_plugins', get_option('active_plugins'));
$required_plugins = ['woocommerce/woocommerce.php'];

if (count(array_intersect($required_plugins, $active_plugins)) !== count($required_plugins)) {
    add_action('admin_notices', 'rnb_notice');
    function rnb_notice()
    {
        $woocommerce_link = '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>';
        echo '<div class="error"><p><strong>' . sprintf(esc_html__('RnB requires WooCommerce to be installed and active. You can download %s from here.', 'redq-rental'), $woocommerce_link) . '</strong></p></div>';
    }
    return;
}

final class RedQ_Rental_And_Bookings
{
    /**
     * Plugin data from get_plugins()
     *
     * @since 1.0
     * @var object
     */
    public $plugin_data;

    /**
     * Plugin version
     */
    public $plugin_version = '18.0.3';

    /**
     * Includes to load
     *
     * @since 1.0
     * @var array
     */
    public $includes;

    /**
     * Init class
     *
     * @return null
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->define_constants();

        register_activation_hook(__FILE__, [&$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'rnb_flush_rewrite_rule']);
        register_activation_hook(__FILE__, [$this, 'rnb_flush_rewrite_rule']);

        $quote_menu = get_option('rnb_enable_rft_endpoint', 'yes');
        if ($quote_menu == 'yes') {
            add_action('init', [$this, 'rfq_endpoints']);
        }

        add_action('plugins_loaded', [$this, 'init_plugin'], 1);
        add_action('init', [$this, 'text_domain']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'action_links'], 1);
        add_filter('plugin_row_meta',  [$this, 'row_meta'], 10, 2);
    }

    /**
     * action_links
     *
     * @param array $links
     *
     * @return array
     */
    public function action_links($links)
    {
        $links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=rnb_settings') . '" target="_blank">' . __('Settings', 'inspect') . '</a>';
        $links[] = '<a href="https://rnb-doc.vercel.app/" target="_blank">' . __('Docs', 'redq-rental') . '</a>';
        $links[] = '<a href="https://redqsupport.ticksy.com/" target="_blank">' . __('Support', 'redq-rental') . '</a>';
        $links[] = '<a href="admin.php?page=rnb_addons" target="_blank">' . __('Add-ons', 'redq-rental') . '</a>';

        return $links;
    }

    /**
     * Row meta
     *
     * @param array $links
     * @param string $file
     * @return array
     */
    public function row_meta($links, $file)
    {
        if ('woocommerce-rental-and-booking/redq-rental-and-bookings.php' !== $file) {
            return $links;
        }

        $row_meta[] = '<a href="https://1.envato.market/NKzXeP" target="_blank">' . __('Compatible Theme By RedQ', 'redq-rental') . '</a>';
        $row_meta[] = '<a href="https://youtu.be/IPWetPsOHzk?list=PLUT1MYLrVpA_d_fzbuIDwFANBvo1ckApI" target="_blank">' . __('Tutorials', 'redq-rental') . '</a>';

        return array_merge($links, $row_meta);
    }

    /**
     * RFQ Endpoint
     *
     * @return null
     * @since 3.0.0
     */
    public static function rfq_endpoints()
    {
        add_rewrite_endpoint('request-quote', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('view-quote', EP_ALL);
    }

    /**
     * Initialize singleton instance
     *
     * @return \RedQ_Rental_And_Bookings
     */
    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Plugin constant define
     *
     * @return null
     * @since 1.0.0
     */
    public function define_constants()
    {
        define('RNB_VERSION', $this->plugin_version);
        define('RNB_FILE', __FILE__);
        define('RNB_PATH', __DIR__);
        define('RNB_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(RNB_FILE)));
        define('RNB_URL', plugins_url('', RNB_FILE));
        define('RNB_ASSETS', RNB_URL . '/assets');
        define('RNB_INC_DIR', 'includes');
        define('RNB_LANG_DIR', 'languages');
        define('RNB_ROOT_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
        define('RNB_DIR', dirname(plugin_basename(RNB_FILE))); // plugin's directory
        define('RNB_ASSETS_DIR', 'assets'); // assets directory
        define('RNB_PACKAGE_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/');
        define('RNB_UID_KEY', '14835145');
        define('RNB_UID_URL', 'https://rnb.redq.io/');
    }

    /**
     * Plugin information
     *
     * @return void
     */
    public function activate()
    {
        $installer = new REDQ_RnB\Installer();
        $installer->run();
    }

    /**
     * flush rewrite rules
     *
     * @return void
     */
    public function rnb_flush_rewrite_rule()
    {
        RedQ_Rental_And_Bookings::rfq_endpoints();
    }

    /**
     * Load plugin files
     *
     * @return void
     */
    public function init_plugin()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        /**
         * compatible plugin with latest version
         * add custom order table 
         * custom_order_tables
         */
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        $tax_display = get_option('rnb_enable_enable_tax_on_single_product', 'no');

        new REDQ_RnB\Init();
        if ($tax_display == 'yes') {
            new REDQ_RnB\TaxDisplay();
        }
        new REDQ_RnB\Hook();
        new REDQ_RnB\TemplateHook();
        new REDQ_RnB\Integration\FoxCurrencySupport();
        new REDQ_RnB\Assets();
        new REDQ_RnB\Ajax();
        new REDQ_RnB\CartHandler();
        new REDQ_RnB\Order();
        new REDQ_RnB\RequestForQuote();
        new REDQ_RnB\ColorControl();
        new REDQ_RnB\Admin\Generator();
        new REDQ_RnB\OrderCancel();
        if (is_admin()) {
            new REDQ_RnB\Admin\AdminPage();
            new REDQ_RnB\Admin\MetaBoxes();
            new REDQ_RnB\Admin\SaveMeta();
            new REDQ_RnB\Integration\FullCalendarIntegration();
            new REDQ_RnB\Admin\Orders_List_Table_Ajax();
        } else {
            new REDQ_RnB\Tabs();
            new REDQ_RnB\Integration\BeThemeSupport();
        }


        require_once trailingslashit(RNB_PATH) . RNB_INC_DIR . '/class-redq-product-redq_rental.php';
        require_once trailingslashit(RNB_PATH) . 'DepositManager.php';
        new REDQ_RnB\DepositManager();
    }

    /**
     * Plugin text-domain
     *
     * @return null
     * @since 1.0.0
     */
    public function text_domain()
    {
        load_plugin_textdomain('redq-rental', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

/**
 * Initialize main plugin
 *
 * @return \RedQ_Rental_And_Bookings
 */
function redq_rnb()
{
    return RedQ_Rental_And_Bookings::init();
}
redq_rnb();

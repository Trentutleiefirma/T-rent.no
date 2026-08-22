<?php

namespace REDQ_RnB;

use REDQ_RnB\Traits\Assets_Trait;
use REDQ_RnB\Traits\Data_Trait;
use REDQ_RnB\Traits\Period_Trait;
use REDQ_RnB\Traits\Error_Trait;

class Assets
{
    use Assets_Trait, Data_Trait, Period_Trait, Error_Trait;

    /**
     * Init class
     */
    public function __construct()
    {
        add_filter('woocommerce_screen_ids', [$this, 'rnb_screen_ids']);
        add_action('wp_enqueue_scripts', [$this, 'register_front_assets']);
        add_action('admin_enqueue_scripts', [$this, 'register_admin_assets']);
    }

    public function rnb_screen_ids($screen_ids)
    {
        $screen_ids[] = 'rnb_page_calendar';
        $screen_ids[] = 'toplevel_page_rnb_addons';
        $screen_ids[] = 'edit-request_quote';
        $screen_ids[] = 'edit-inventory';
        $screen_ids[] = 'inventory';
        $screen_ids[] = 'edit-resource';
        $screen_ids[] = 'edit-rnb_categories';
        $screen_ids[] = 'edit-resource';
        $screen_ids[] = 'edit-person';
        $screen_ids[] = 'edit-deposite';
        $screen_ids[] = 'edit-attributes';
        $screen_ids[] = 'edit-features';
        $screen_ids[] = 'edit-pickup_location';
        $screen_ids[] = 'edit-dropoff_location';
        $screen_ids[] = 'rnb_page_addons';
        $screen_ids[] = 'toplevel_page_rnb_dashboard';

        return $screen_ids;
    }

    /**
     * Register front assets
     */
    public function register_front_assets()
    {
        $product_id = '';
        $inventory_id = '';
        if (is_product() && is_rental_product(get_the_ID())) {
            $product_id = get_the_ID();
            $inventory_id = rnb_get_default_inventory_id();
        }

        $scripts = $this->get_front_scripts();
        $styles  = $this->get_front_styles();

        foreach ($scripts as $handle => $script) {
            $deps    = isset($script['deps']) ? $script['deps'] : false;
            $version = isset($script['version']) ? $script['version'] : RNB_VERSION;

            wp_register_script($handle, $script['src'], $deps, $version, true);

            if ($product_id && isset($script['scope']) && in_array('general', $script['scope'])) {
                wp_enqueue_script($handle);
            }

            if (is_rfq_page() && isset($script['scope']) && in_array('rfq', $script['scope'])) {
                wp_enqueue_script($handle);
            }
        }

        foreach ($styles as $handle => $style) {
            $deps    = isset($style['deps']) ? $style['deps'] : false;
            $version = isset($style['version']) ? $style['version'] : RNB_VERSION;

            wp_register_style($handle, $style['src'], $deps, $version);

            if ($product_id && isset($script['scope']) && in_array('general', $script['scope'])) {
                wp_enqueue_style($handle);
            }

            if (is_rfq_page() && isset($script['scope']) && in_array('rfq', $script['scope'])) {
                wp_enqueue_style($handle);
            }
        } 
        if(function_exists('is_account_page') && is_account_page()){
            wp_enqueue_style('rnb-cancel-order');
            wp_enqueue_script('rnb-cancel-order');
            $cancel_before_by   = get_option('rnb_cancel_order_before_by');
            $cancel_before_time = $cancel_before_by == 'day' ? get_option('rnb_cancel_order_before_day') : get_option('rnb_cancel_order_before_hour');
            wp_localize_script('rnb-cancel-order', 'rnb_cancel_order_obj', [
                'ajax_url'                 => admin_url( 'admin-ajax.php' ),
                'nonce'                    => wp_create_nonce('rnb-cancel-ajax-nonce'),
                'customer_note'            => get_option('rnb_cancel_order_customer_note'),
                'cancel_reason_require'    => get_option('rnb_cancel_order_button_required'),
                'order_cancel_before'      => $cancel_before_by,
                'order_cancel_before_time' => $cancel_before_time,

            ]);
        }    
        //Enable or Disable google map
        if ($product_id) {
            $this->rnb_handle_google_map($product_id);
            $this->localize_scripts($product_id, $inventory_id);
        }
    }

    /**
     * Handle google map scripts
     *
     * @param int $product_id
     * @return void
     */
    public function rnb_handle_google_map($product_id)
    {
        if (!is_product()) {
            return;
        }

        $gmap_enable = get_option('rnb_enable_gmap');
        $map_key     = get_option('rnb_gmap_api_key');
        $conditions  = redq_rental_get_settings($product_id, 'conditions');
        if ($gmap_enable === 'yes' && $map_key && isset($conditions['conditions']['booking_layout']) && $conditions['conditions']['booking_layout'] !== 'layout_one') {
            $markers = [
                'pickup'      => RNB_ROOT_URL . '/assets/img/marker-pickup.png',
                'destination' => RNB_ROOT_URL . '/assets/img/marker-destination.png'
            ];

            wp_register_script('google-map-api', '//maps.googleapis.com/maps/api/js?key=' . $map_key . '&libraries=places,geometry&language=en-US', true, false);
            wp_enqueue_script('google-map-api');

            wp_register_script('rnb-map', RNB_ROOT_URL . '/assets/js/rnb-map.js', ['jquery'], true);
            wp_enqueue_script('rnb-map');

            wp_localize_script('rnb-map', 'RNB_MAP', [
                'markers'       => $markers,
                'pickup_title'  => esc_html__('Pickup Point', 'redq-rental'),
                'dropoff_title' => esc_html__('DropOff Point', 'redq-rental'),
            ]);
        }
    }

    /**
     * Localize scripts
     *
     * @param int $product_id
     * @param int $inventory_id
     * @return void
     */
    public function localize_scripts($product_id, $inventory_id)
    {
        wp_localize_script('front-end-scripts', 'MODEL_DATA', [
            'translated_strings' => rnb_get_translated_strings()
        ]);

        $settings_data      = rnb_get_combined_settings_data($product_id);
        $woocommerce_info   = rnb_get_woocommerce_currency_info();
        $translated_strings = rnb_get_translated_strings();
        $localize_info      = rnb_get_localize_info($product_id);
        $conditions         = redq_rental_get_settings($product_id, 'conditions')['conditions'];

        $periods = $this->get_periods($product_id, $inventory_id);
        $pickup_locations = rnb_arrange_pickup_location_data($product_id, $inventory_id, $conditions);
        $return_locations = rnb_arrange_return_location_data($product_id, $inventory_id, $conditions);
        $deposits         = rnb_arrange_security_deposit_data($product_id, $inventory_id, $conditions);
        $adult_data       = rnb_arrange_adult_data($product_id, $inventory_id, $conditions);
        $child_data       = rnb_arrange_child_data($product_id, $inventory_id, $conditions);
        $resources        = rnb_arrange_resource_data($product_id, $inventory_id, $conditions);
        $categories       = rnb_arrange_category_data($product_id, $inventory_id, $conditions);

        $validate_fields = $this->prepare_validate_fields($product_id);


        wp_localize_script('front-end-scripts', 'CALENDAR_DATA', [
            'availability'       => isset($periods['availability']) ? $periods['availability'] : [],
            'calendar_props'     => $settings_data,
            'validate_fields' => $validate_fields,
            'block_dates'        => isset($periods['availability']) ? $periods['availability'] : [],
            'woocommerce_info'   => $woocommerce_info,
            'allowed_datetime'   => isset($periods['allowed_datetime']) ? $periods['allowed_datetime'] : [],
            'localize_info'      => $localize_info,
            'translated_strings' => $translated_strings,
            'buffer_days'        => isset($periods['buffer_dates']) ? $periods['buffer_dates'] : [],
            'quantity'           => get_post_meta($inventory_id, 'quantity', true),
            'ajax_url'           => rnb_get_ajax_url(),
            'nonce' => wp_create_nonce('rnb_ajax_nonce'),
            'pick_up_locations'  => $pickup_locations,
            'return_locations'   => $return_locations,
            'resources'          => $resources,
            'categories'         => $categories,
            'adults'             => $adult_data,
            'childs'             => $child_data,
            'deposits'           => $deposits,
        ]);

        wp_localize_script('rnb-rfq', 'RFQ_DATA', [
            'ajax_url'           => rnb_get_ajax_url(),
            'translated_strings' => $translated_strings,
            'enable_gdpr'        => is_gdpr_enable($product_id),
            'nonce'              => wp_create_nonce('rnb_rfq_nonce'),
        ]);

        $block_future_date = get_option('rnb_calendar_block_future_dates', 365);
        wp_localize_script('rnb-calendar', 'RNB_URL_DATA', [
            'date'              => rnb_check_url_dates(),
            'url_data'          => rnb_normalize_params(),
            'block_future_date' => !empty($block_future_date) ? intval($block_future_date) : 365,
        ]);
    }

    /**
     * Register admin assets
     */
    public function register_admin_assets()
    {
        $screen = get_current_screen();
        $screen_id = $screen ? $screen->id : '';
        $current_post_type = isset($screen->post_type) ? $screen->post_type : '';
        $post_type_list = ['request_quote', 'inventory'];
        $scripts = $this->get_admin_scripts();
        $styles  = $this->get_admin_styles();
        // doc link provider script 
        $docs = $this->get_doc_details();
        if (in_array($current_post_type, $post_type_list)) {
            $rnb_doc = $scripts['rnb-doc'];
            $deps    = isset($rnb_doc['deps']) ? $rnb_doc['deps'] : false;
            $version = isset($rnb_doc['version']) ? $rnb_doc['version'] : RNB_VERSION;
            wp_register_script('rnb-doc', $rnb_doc['src'], $deps, $version, true);
            wp_enqueue_script('rnb-doc');

            wp_localize_script('rnb-doc', 'rnb_docs', [
                'docs' => $docs,
                'button_name' => esc_html__('View Docs', 'redq-rental')
            ]);
        }
        // quote style 
        if ($current_post_type === 'request_quote') {
            $rnb_quote = $styles['rnb-quote'];
            $deps    = isset($rnb_quote['deps']) ? $rnb_quote['deps'] : false;
            $version = isset($rnb_quote['version']) ? $rnb_quote['version'] : RNB_VERSION;
            wp_register_style('rnb-quote', $rnb_quote['src'], $deps, $version);
            wp_enqueue_style('rnb-quote');
        }
        // order admin list 
        if ($screen_id == 'rnb_page_rnb-order') {
            $rnb_doc = $scripts['admin-order-list'];
            $deps    = isset($rnb_doc['admin-order-list']) ? $rnb_doc['admin-order-list'] : false;
            $version = isset($rnb_doc['admin-order-list']) ? $rnb_doc['admin-order-list'] : RNB_VERSION;
            wp_register_script('admin-order-list', $rnb_doc['src'], $deps, $version, true);
            wp_enqueue_script('admin-order-list');
            wp_localize_script('admin-order-list', 'rnb_order_list', [
                'ajax_url' => admin_url('admin-ajax.php'),
                '_nonce'   => wp_create_nonce('_rnb_order_nonce')
            ]);
        }


        if (in_array($screen_id, ['woocommerce_page_wc-orders', 'shop_order'])) {
            $order_js = $scripts['rnb-order'];
            $deps    = isset($order_js['deps']) ? $order_js['deps'] : false;
            $version = isset($order_js['version']) ? $order_js['version'] : RNB_VERSION;
            wp_register_script('rnb-order', $order_js['src'], $deps, $version, true);
            wp_enqueue_script('rnb-order');
            $params = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('rnb_admin_nonce'),
            ];
            wp_localize_script('rnb-order', 'rnb_order_data', $params);
        }

        if (!(in_array($screen_id, wc_get_screen_ids(), true) && $screen_id !== 'shop_coupon' && $screen_id !== 'shop_order')) {
            return;
        }

        foreach ($scripts as $handle => $script) {

            if ($handle === 'rnb-order'  || $handle === 'rnb-doc') {
                continue;
            }

            $deps    = isset($script['deps']) ? $script['deps'] : false;
            $version = isset($script['version']) ? $script['version'] : RNB_VERSION;

            wp_register_script($handle, $script['src'], $deps, $version, true);
            wp_enqueue_script($handle);
        }

        foreach ($styles as $handle => $style) {
            $deps    = isset($style['deps']) ? $style['deps'] : false;
            $version = isset($style['version']) ? $style['version'] : RNB_VERSION;

            wp_register_style($handle, $style['src'], $deps, $version);
            wp_enqueue_style($handle);
        }

        $this->localize_admin_scripts($screen_id);

        wp_enqueue_media();
    }

    /**
     * Localize scripts
     *
     * @param int $product_id
     * @param int $inventory_id
     * @return void
     */
    public function localize_admin_scripts($screen_id)
    {
        global $woocommerce, $wpdb;

        $post_id = get_the_ID();

        $params = [
            'plugin_url'     => $woocommerce->plugin_url(),
            'ajax_url'       => admin_url('admin-ajax.php'),
            'calendar_image' => $woocommerce->plugin_url() . '/assets/images/calendar.png',
        ];

        $products_by_inventory = $wpdb->get_results($wpdb->prepare("SELECT product FROM {$wpdb->prefix}rnb_inventory_product WHERE inventory = %d", $post_id));

        if (isset($post_id) && !empty($post_id)) {
            $post_type = get_post_type($post_id);
            $post_id = isset($post_type) && $post_type === 'inventory' && count($products_by_inventory) ? $products_by_inventory[0]->product : '';
            $conditions = redq_rental_get_settings($post_id, 'conditions');
            $admin_data = $conditions['conditions'];
            $params['calendar_data'] = $admin_data;
        }

        //Prepare inventory price to localize
        $prices = [];

        if ($screen_id === 'product') {
            $args = array(
                'post_type'      => 'Inventory',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'post_status'    => 'publish',
                'fields'         => 'ids'
            );

            $inventories = get_posts($args);

            foreach ($inventories as $key => $inventory_id) {
                $price = get_inventory_price($inventory_id, $post_id);
                $prices[$inventory_id] = isset($price['price']) ? $price['price'] : 0;
            }
        }


        $params['inventory_prices'] = $prices;

        wp_localize_script('rnb-admin', 'RNB_ADMIN_DATA', $params);
    }
}

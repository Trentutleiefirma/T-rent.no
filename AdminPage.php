<?php

namespace REDQ_RnB\Admin;

use REDQ_RnB\Traits\Data_Trait;
use Automattic\WooCommerce\Utilities\NumberUtil;

use WC_ORDER;

if (!defined('ABSPATH')) {
    exit;
}

class AdminPage
{
    use Data_Trait;

    /**
     * Init class
     */
    function __construct()
    {
        add_action('admin_menu', [$this, 'rnb_admin_menu']);
        add_filter('parent_file', [$this, 'mbe_set_current_menu']);
        add_action('woocommerce_before_order_itemmeta', [$this, 'rnb_before_order_itemmeta'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'rnb_order_status_changed'], 10, 3);
        add_action('trashed_post', [$this, 'rnb_trashed_orders'], 10, 1);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'rnb_hidden_order_meta']);
        add_action('woocommerce_after_order_itemmeta', [$this, 'rnb_order_item_action_buttons'], 10, 3);
        add_action('woocommerce_order_item_add_action_buttons', [$this, 'rnb_refund_deposit'], 10, 1);
        // add_action('woocommerce_order_after_calculate_totals', [$this, 'adjust_deposit_with_order_total'], 10, 2);
        add_action('wp_trash_post', [$this, 'delete_related_entries_on_post_delete']);
        add_action('wp_ajax_rnb_uid', [$this, 'handle_rnb_uid']);
        add_filter('admin_body_class', [$this, 'rnb_order_list_body_class']);
        add_action('init', [$this, 'quote_mark_as_read']);
    }

    /**
     * rnb_admin_menu
     *
     * @version 1.0.0
     * @since 2.0.4
     */
    public function rnb_admin_menu()
    {
        $rnb_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg width="1000" height="1000" viewBox="0 0 1000 1000" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M304.054 77.7565C259.636 163.769 262.652 287.846 327.547 386.89C299.287 398.174 272.073 411.92 246.22 427.97C231.97 436.828 218.127 446.393 204.69 456.663C160.524 490.29 121.98 530.719 90.4982 576.439C81.9397 588.635 74.0444 601.195 66.8125 614.118V244.84C66.8041 212.862 73.0954 181.195 85.327 151.649C97.5587 122.103 115.491 95.2553 138.1 72.6405C160.709 50.0256 187.552 32.086 217.095 19.8466C246.638 7.60718 278.302 1.30762 310.281 1.30762H366.638C340.583 22.0551 319.248 48.1168 304.054 77.7565Z" fill="#A7AAAD"/>
        <path d="M908.242 261.917C908.242 274.434 907.664 287.079 906.445 299.467C894.891 450.696 737.821 532.152 611.176 579.716C579.082 590.82 544.356 601.668 510.015 602.567C482.991 601.347 452.245 598.587 425.542 603.722C438.038 566.826 457.376 532.616 482.542 502.882C530.427 450.375 602.254 420.784 621.19 351.46C653.99 264.613 591.149 216.215 506.42 222.569C481.13 223.468 452.887 220.002 427.789 224.752C375.218 234.765 343.252 296.9 365.783 345.876C266.803 185.404 359.364 -19.9999 548.785 1.56753C688.46 2.08104 840.651 29.6179 893.928 167.881C904.08 198.168 908.922 229.981 908.242 261.917Z" fill="#A7AAAD"/>
        <path d="M522.6 399.467C475.296 430.053 435.843 471.328 407.422 519.963C379.001 568.598 362.409 623.232 358.983 679.458C358.597 682.825 358.404 686.211 358.405 689.6V775.549C357.604 813.706 341.885 850.03 314.616 876.732C287.347 903.435 250.701 918.39 212.536 918.39C174.371 918.39 137.724 903.435 110.456 876.732C83.1874 850.03 67.4675 813.706 66.667 775.549V741.721C94.0092 647.741 149.716 564.503 226.168 503.389C302.62 442.276 396.085 406.271 493.779 400.301C503.279 399.788 513.036 399.467 522.407 399.467H522.6Z" fill="#A7AAAD"/>
        <path d="M902.82 671.578V751.684C902.829 771.282 898.803 790.689 890.973 808.796C883.144 826.902 871.664 843.353 857.19 857.208C842.716 871.063 825.533 882.05 806.622 889.541C787.711 897.031 767.443 900.878 746.978 900.862C713.669 900.869 681.683 888.375 657.881 866.06C634.079 843.745 620.356 813.387 619.657 781.495C619.688 780.947 619.688 780.398 619.657 779.849L617.811 715.411C617.811 710.9 617.81 706.571 617.301 702.365C616.837 682.941 611.971 663.846 603.041 646.4C600.171 641.415 596.756 636.735 592.855 632.44C675.956 609.474 754.032 572.297 823.308 522.705C841.108 532.491 856.933 545.251 870.035 560.381C884.677 578.548 894.608 605.372 899.828 640.853C901.038 650.363 902.056 660.666 902.82 671.578Z" fill="#A7AAAD"/>
        <path d="M472.039 900.827C512.231 888.836 548.796 867.785 578.788 839.372C608.779 810.959 620.964 775.006 627.586 731.962C628.517 729.679 633.556 717.783 634.209 715.407L663.674 670.019C671.623 643.435 690.032 621.114 714.952 607.844C739.873 594.573 769.321 591.41 796.982 599.032C824.644 606.653 848.316 624.453 862.922 648.615C877.529 672.777 885.853 668.409 895.786 728.651V768.384C875.92 839.374 863.871 874.339 773.275 900.828C718.183 916.937 565.048 921.323 493.089 905.998C486.104 904.46 478.971 902.737 472.179 900.865L472.039 900.827Z" fill="#A7AAAD"/>
        </svg>');


        $parent_slug = 'rnb_dashboard';
        add_menu_page(__('RnB', 'redq-rental'), __('RnB', 'redq-rental'), 'publish_posts', $parent_slug,  [$this, 'rnb_dashboard_page'], $rnb_icon, 56);

        $submenus = apply_filters('rnb_admin_submenu', [
            'license' => [
                'page_title' => __('Activate License', 'redq-rental'),
                'menu_title' => __('Activate License', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => $parent_slug,
                'callback'   => '',
                'position'   => null,
            ],
            'inventory' => [
                'page_title' => __('All Inventories', 'redq-rental'),
                'menu_title' => __('All Inventories', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit.php?post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'add_inventory' => [
                'page_title' => __('Add New Inventory', 'redq-rental'),
                'menu_title' => __('Add New Inventory', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'post-new.php?post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'order' => [
                'page_title' => __('RnB Orders', 'redq-rental'),
                'menu_title' => __('RnB Orders', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'rnb-order',
                'callback'   => [$this, 'redq_rental_order'],
                'position'   => null,
            ],
            'quote' => [
                'page_title' => __('Quotes', 'redq-rental'),
                'menu_title' => __('Quotes '.$this->get_quotes_count(), 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit.php?post_type=request_quote',
                'callback'   => '',
                'position'   => null,
            ],
            'calendar' => [
                'page_title' => __('RnB Calendar', 'redq-rental'),
                'menu_title' => __('RnB Calendar', 'redq-rental'),
                'capability' => 'publish_posts',
                'menu_slug'  => 'calendar',
                'callback'   => [$this, 'redq_rental_admin_main_menu_options'],
                'position'   => null,
            ],
            'resource' => [
                'page_title' => __('Resources', 'redq-rental'),
                'menu_title' => __('Resources', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=resource&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'category' => [
                'page_title' => __('Categories', 'redq-rental'),
                'menu_title' => __('Categories', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=rnb_categories&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'person' => [
                'page_title' => __('Person', 'redq-rental'),
                'menu_title' => __('Person', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=person&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'deposit' => [
                'page_title' => __('Deposits', 'redq-rental'),
                'menu_title' => __('Deposits', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=deposite&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'pickup_location' => [
                'page_title' => __('Pickup Locations', 'redq-rental'),
                'menu_title' => __('Pickup Locations', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=pickup_location&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'return_location' => [
                'page_title' => __('Return Locations', 'redq-rental'),
                'menu_title' => __('Return Locations', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=dropoff_location&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'feature' => [
                'page_title' => __('Features', 'redq-rental'),
                'menu_title' => __('Features', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=features&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
            'attribute' => [
                'page_title' => __('Attributes', 'redq-rental'),
                'menu_title' => __('Attributes', 'redq-rental'),
                'capability' => 'manage_options',
                'menu_slug'  => 'edit-tags.php?taxonomy=attributes&post_type=inventory',
                'callback'   => '',
                'position'   => null,
            ],
        ], $parent_slug);

        foreach ($submenus as $key => $menu) {
            add_submenu_page($parent_slug, $menu['page_title'], $menu['menu_title'], $menu['capability'], $menu['menu_slug'], $menu['callback'], $menu['position']);
        }

        add_submenu_page($parent_slug, __('Settings', 'redq-rental'), __('Settings', 'redq-rental'), 'manage_options', 'admin.php?page=wc-settings&tab=rnb_settings');
        add_submenu_page(
            $parent_slug,
            __('Docs', 'redq-rental'),
            __('Docs', 'redq-rental'),
            'manage_options',
            'rnb-docs',
            [$this, 'docs_callback']
        );
        add_submenu_page(
            $parent_slug,
            __('Support Forum', 'redq-rental'),
            __('Support Forum', 'redq-rental'),
            'manage_options',
            'support-forum',
            [$this, 'support_forum_callback']
        );
        add_submenu_page(
            'rnb_dashboard',
            __('Extensions', 'redq-rental'),
            __('Extensions', 'redq-rental'),
            'publish_posts',
            'rnb_addons',
            [$this, 'redq_rental_admin_addons_page']
        );
    }

    public function mbe_set_current_menu($parent_file)
    {
        global $submenu_file, $current_screen, $pagenow;

        if ($current_screen->post_type == 'inventory') {

            if ($pagenow == 'post.php') {
                $submenu_file = 'edit.php?post_type=' . $current_screen->post_type;
            }

            if ($pagenow == 'edit-tags.php') {
                $submenu_file = 'edit-tags.php?taxonomy=' . $current_screen->taxonomy . '&post_type=' . $current_screen->post_type;
            }

            $parent_file = 'rnb_dashboard';
        }

        return $parent_file;
    }

    /**
     * rnb_dashboard_page
     *
     * @version 1.0.0
     * @since 2.0.4
     */
    public function rnb_dashboard_page()
    {
        if (!current_user_can('publish_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'redq-rental'));
        }

        $uid_code     = get_option('rnb_uid', '');
        $activation_status = get_option('rnb_uid_status', false);
        $masked_length     = max(strlen($uid_code) - 16, 0);
        $masked_code       = substr($uid_code, 0, 6) . str_repeat('*', $masked_length) . substr($uid_code, -6);

        $template = apply_filters('rnb_dashboard_template', __DIR__ . '/views/rnb-dashboard.php');
        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * loader
     *
     * @return void
     */
    public function loader()
    {
        echo '<span class="loader"></span>';
    }

    public function docs_callback()
    {
        wp_redirect('https://rnb-doc.vercel.app/');
        exit;
    }

    public function support_forum_callback()
    {
        wp_redirect('https://redqsupport.ticksy.com/');
        exit;
    }

    /**
     * redq_rental_admin_main_menu_options
     *
     * @version 1.0.0
     * @since 2.0.4
     */
    public function redq_rental_admin_main_menu_options()
    {
        if (!current_user_can('publish_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'redq-rental'));
        }

        include_once 'views/admin-menu-page-full-calender.php';
    }
    /**
     * rnb order
     *
     * @version 1.0.0
     * @since 2.0.4
     */
    public function redq_rental_order()
    {
        if (!current_user_can('publish_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'redq-rental'));
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        require_once ABSPATH . 'wp-admin/includes/template.php';
        $orders_list_table = new Orders_List_Table();

        // Prepare the items to be displayed
        $orders_list_table->prepare_items();

        // Display the table
        $orders_list_table->display();
        // order table ajax action    
    }


    public function redq_rental_admin_addons_page()
    {
        if (!current_user_can('publish_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'redq-rental'));
        }

        include_once 'views/admin-rnb-addons.php';
    }


    /**
     * rnb_admin_menu
     *
     * @version 1.0.0
     * @since 2.0.4
     */
    public function rnb_hidden_order_meta($args)
    {
        $args[] = '_return_hidden_datetime';
        $args[] = '_pickup_hidden_datetime';
        $args[] = '_return_hidden_days';
        $args[] = 'redq_google_cal_sync_id';
        $args[] = 'booking_inventory';
        return $args;
    }


    /**
     * rnb_before_order_itemmeta
     *
     * @param string $item_id
     * @param object $item
     * @param object $product
     *
     * @return void
     */
    public function rnb_before_order_itemmeta($item_id, $item, $product)
    {
        if (isset($product) && !empty($product)) :
            $product_id = $product->get_id();
            if ($item->get_type() === 'line_item') {
                $order_id = get_the_ID();

                $is_translated = apply_filters('wpml_element_has_translations', NULL, $product_id, 'product');
                if (in_array('sitepress-multilingual-cms/sitepress.php', apply_filters('active_plugins', get_option('active_plugins'))) && function_exists('icl_object_id') && $is_translated) {
                    $order_lang = get_post_meta($order_id, 'rnb_place_order_in_lang', true);
                    $inventory_id = get_post_meta($order_id, 'order_item_' . $item_id . '_' . $order_lang . '_inventory_ref', true);
                } else {
                    $inventory_id = get_post_meta($order_id, 'order_item_' . $item_id . '_inventory_ref', true);
                }

                if (!empty($inventory_id)) {
                    echo '<div class="rnb-inventory-ref"> <span class="title"> ' . esc_html__('Inventory Reference', 'redq-rental') . ' : </span>';
                    echo '<a target="_blank" href="' . get_edit_post_link($inventory_id) . '">' . get_the_title($inventory_id) . '</a>';
                    echo '</div>';
                }
            }
        endif;
    }


    /**
     * rnb_order_status_changed
     *
     * @param string $order_id
     * @param string $old_status
     * @param string $new_status
     *
     * @return void
     */
    public function rnb_order_status_changed($order_id, $old_status, $new_status)
    {
        $order = new WC_Order($order_id);
        $items = $order->get_items();

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {

            if ($item->get_type() !== 'line_item') {
                continue;
            }

            $item_data    = $item->get_data();
            $item_id      = $item_data['id'];
            $product_id   = $item_data['product_id'];
            $product      = wc_get_product($product_id);
            $product_type = $product ? $product->get_type() : '';

            if ($product_type !== 'redq_rental') {
                continue;
            }

            if ($new_status === 'cancelled' && $old_status !== 'cancelled') {
                $args = [
                    'product_id' => $product_id,
                    'order_id'   => $order_id,
                    'item_id'    => $item_id
                ];
                rnb_booking_dates_update($args);
            }
        }
    }


    /**
     * Delete posts
     *
     * @version 1.0.0
     * @since 2.0.4
     */
    public function rnb_trashed_orders($order_id)
    {
        $post_type = get_post_type($order_id);
        if ($post_type !== 'shop_order') {
            return;
        }

        $order = new WC_Order($order_id);
        $items = $order->get_items();

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {

            if ($item->get_type() !== 'line_item') {
                continue;
            }

            $item_data    = $item->get_data();
            $item_id      = $item_data['id'];
            $product_id   = $item_data['product_id'];
            $product      = wc_get_product($product_id);
            $product_type = $product ? $product->get_type() : '';

            if ($product_type !== 'redq_rental') {
                continue;
            }

            $args = [
                'product_id' => $product_id,
                'order_id'   => $order_id,
                'item_id'    => $item_id
            ];

            rnb_booking_dates_update($args);
        }
    }


    /**
     * Check array key from multi-dimentional array
     *
     * @version 3.0.9
     * @since 2.0.4
     */
    public function get_multidimentional_array_index($products, $field, $value)
    {
        foreach ($products as $key => $product) {
            if ($product->$field === $value)
                return $key;
        }
        return false;
    }


    /**
     * Sync delete order and available dates
     *
     * @version 3.0.9
     * @since 2.0.4
     */
    public function rnb_sync_order_dates($order_id, $item_id, $product_id, $booked_dates_ara)
    {

        $reset_buffer_days = array();

        $is_translated = apply_filters('wpml_element_has_translations', NULL, $product_id, 'product');
        if (in_array('sitepress-multilingual-cms/sitepress.php', apply_filters('active_plugins', get_option('active_plugins'))) && function_exists('icl_object_id') && $is_translated) {
            /**
             * Disabled booking dates for all laguages for a certain product
             *
             * This will work only if WPML is installed
             */
            $languages = apply_filters('wpml_active_languages', NULL, 'orderby=id&order=desc');
            $translated_posts = array();

            if (isset($languages) && sizeof($languages) !== 0) {
                foreach ($languages as $lang_key => $lang_value) {
                    $id = icl_object_id($product_id, 'product', false, $lang_value['language_code']);

                    $block_dates_times = get_post_meta($id, 'redq_block_dates_and_times', true);
                    $block_datetimes = array();
                    $deleted_block_dates_times = array();
                    $copied_block_dates_times = $block_dates_times;
                    $flag = 0;

                    $inventory_refs = get_post_meta($order_id, 'order_item_' . $item_id . '_' . $lang_key . '_inventory_ref', true);
                    foreach ($copied_block_dates_times as $key => $value) {

                        if (in_array($key, $inventory_refs)) {
                            $updated_ara = array();
                            $updated_only_block_dates_ara = array();
                            $deleted_ara = array();
                            $deleted_only_block_dates_ara = array();

                            foreach ($value['block_dates'] as $bkey => $bvalue) {
                                if (in_array($bvalue['from'], $booked_dates_ara)) {
                                    $deleted_ara[] = $bvalue;
                                    $flag = 1;
                                    continue;
                                }
                                $updated_ara[] = $bvalue;
                            }

                            foreach ($value['only_block_dates'] as $obkey => $obvalue) {
                                if (in_array($obvalue, $booked_dates_ara)) {
                                    $deleted_only_block_dates_ara[] = $obvalue;
                                    continue;
                                }
                                $updated_only_block_dates_ara[] = $obvalue;
                            }

                            update_post_meta($key, 'redq_rental_availability', $updated_ara);

                            $block_dates_times[$key]['block_dates'] = $updated_ara;
                            $block_dates_times[$key]['only_block_dates'] = $updated_only_block_dates_ara;

                            $deleted_block_dates_times[$key]['deleted_block_dates'] = $deleted_ara;
                            $deleted_block_dates_times[$key]['deleted_only_block_dates'] = $deleted_only_block_dates_ara;
                        }
                        //if( $flag === 1 )break;
                    }
                    update_post_meta($id, 'redq_block_dates_and_times', $block_dates_times);
                    update_post_meta($id, 'redq_deleted_block_dates_and_times', $deleted_block_dates_times);
                }
                update_post_meta($order_id, 'order_item_' . $item_id . '_extra_pre_buffer_dates', $reset_buffer_days);
                update_post_meta($order_id, 'order_item_' . $item_id . '_extra_buffer_dates', $reset_buffer_days);
            }
        } else {

            $block_dates_times = get_post_meta($product_id, 'redq_block_dates_and_times', true);
            $block_datetimes = array();
            $deleted_block_dates_times = array();
            $copied_block_dates_times = $block_dates_times;
            $flag = 0;

            $inventory_refs = get_post_meta($order_id, 'order_item_' . $item_id . '_inventory_ref', true);
            $inventory_refs = is_array($inventory_refs) ? $inventory_refs : array();

            foreach ($copied_block_dates_times as $key => $value) {
                if (in_array($key, $inventory_refs)) {
                    $updated_ara = array();
                    $updated_only_block_dates_ara = array();
                    $deleted_ara = array();
                    $deleted_only_block_dates_ara = array();

                    foreach ($value['block_dates'] as $bkey => $bvalue) {
                        if (in_array($bvalue['from'], $booked_dates_ara)) {
                            $deleted_ara[] = $bvalue;
                            $flag = 1;
                            continue;
                        }
                        $updated_ara[] = $bvalue;
                    }

                    foreach ($value['only_block_dates'] as $obkey => $obvalue) {
                        if (in_array($obvalue, $booked_dates_ara)) {
                            $deleted_only_block_dates_ara[] = $obvalue;
                            continue;
                        }
                        $updated_only_block_dates_ara[] = $obvalue;
                    }

                    update_post_meta($key, 'redq_rental_availability', $updated_ara);

                    $block_dates_times[$key]['block_dates'] = $updated_ara;
                    $block_dates_times[$key]['only_block_dates'] = $updated_only_block_dates_ara;

                    $deleted_block_dates_times[$key]['deleted_block_dates'] = $deleted_ara;
                    $deleted_block_dates_times[$key]['deleted_only_block_dates'] = $deleted_only_block_dates_ara;
                }

                //if( $flag === 1 )break;
            }

            update_post_meta($product_id, 'redq_block_dates_and_times', $block_dates_times);
            update_post_meta($product_id, 'redq_deleted_block_dates_and_times', $deleted_block_dates_times);

            update_post_meta($order_id, 'order_item_' . $item_id . '_extra_pre_buffer_dates', $reset_buffer_days);
            update_post_meta($order_id, 'order_item_' . $item_id . '_extra_buffer_dates', $reset_buffer_days);
        }
    }

    /**
     * Clear rental dates
     *
     * @param int $item_id
     * @param object $item
     * @param object $product
     * @return void
     */
    public function rnb_order_item_action_buttons($item_id, $item, $product)
    {
        if ($item->get_type() !== 'line_item') {
            return;
        }

        if (!empty($product) && $product->get_type() !== 'redq_rental') {
            return;
        }

        $order_id   = $item->get_data()['order_id'];
        $product_id = $item->get_data()['product_id'];

        echo '<a href="#" id="rnb-clear-dates" class="rnb-clear-dates" data-order_id="' . $order_id . '"  data-item_id="' . $item_id . '" data-product_id="' . $product_id . '"> ' . esc_html__('Clear Dates', 'redq-rental') . '</a>';
    }

    /**
     * Add refund deposit button
     *
     * @param object $order
     * @return void
     */
    public function rnb_refund_deposit($order)
    {
        $order_id = $order->get_id();
        $deposit  = $this->get_deposit_by_order($order_id);

        if (empty($deposit)) {
            return;
        }

        echo '<button data-order_id="' . $order_id . '" type="button" class="button refund-deposit">' . esc_html__('Refund Deposit', 'redq-rental') . '</button>';
    }

    /**
     * Adjust deposit with order total in admin order details page
     * 
     *
     * @param [type] $and_taxes
     * @param object $order
     * @return void
     */
    public function adjust_deposit_with_order_total($and_taxes, $order)
    {
        $items = $order->get_items();
        if (empty($items)) {
            return;
        }

        $deposit = 0;

        foreach ($items as $item_id => $item) {

            $item_data = $item->get_data();
            $product_id = $item_data['product_id'];

            if (empty($product_id)) {
                continue;
            }

            $product_type = wc_get_product($product_id)->get_type();

            if ($product_type === 'redq_rental') {
                $price_breakdown = wc_get_order_item_meta($item_id, 'rnb_price_breakdown', true);
                $deposit_amount = isset($price_breakdown['deposit_total']) ? floatval($price_breakdown['deposit_total']) * $item['quantity'] : 0;
                $deposit += $deposit_amount;
            }
        }

        $order->set_total(NumberUtil::round($deposit + $order->get_total(), wc_get_price_decimals()));
        $order->save();
    }

    public function delete_related_entries_on_post_delete($post_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rnb_inventory_product';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (empty($table_exists)) {
            return false;
        }

        if (get_post_type($post_id) === 'product' || get_post_type($post_id) === 'inventory') {
            $wpdb->delete($table_name, array('product' => $post_id), array('%d'));
            $wpdb->delete($table_name, array('inventory' => $post_id), array('%d'));
        }
    }

    /**
     * Handle uid
     *
     * @return void
     */
    public function handle_rnb_uid()
    {
        if (!check_ajax_referer('rnb-uid-security', 'security', false)) {
            wp_send_json_error([
                'message' => __('Nonce verification failed.', 'redq-rental'),
                'code'    => 403,
                'type'    => 'forbidden',
            ]);
        }

        $uid_key = '';
        if (isset($_POST['uid_key'])) {
            $uid_key = sanitize_text_field($_POST['uid_key']);
        }

        $deactivate = false;
        if (isset($_POST['deactivate']) && !empty($_POST['deactivate'])) {
            $deactivate = true;
            $uid_key    = get_option('rnb_uid', '');
        }

        if (empty($uid_key)) {
            wp_send_json_error([
                'message' => __('Purchase code is required', 'redq-rental'),
                'code'    => 404,
                'type'    => 'not_found',
            ]);
        }

        if (!preg_match("/^([a-f0-9]{8})-(([a-f0-9]{4})-){3}([a-f0-9]{12})$/i", $uid_key)) {
            wp_send_json_error([
                'message' => __('Purchase code is invalid, try with valid one.', 'redq-rental'),
                'code'    => 400,
                'type'    => 'bad_request',
            ]);
        }

        if (empty(RNB_UID_URL)) {
            wp_send_json_error([
                'message' => __('Rest URL is not defined.', 'redq-rental'),
                'code'    => 404,
                'type'    => 'api_request_failed',
            ]);
        }

        $rest_url =  add_query_arg(array(
            'home_url'      => site_url(),
            'deactivate'    => $deactivate,
            'item_id'       => sanitize_text_field(RNB_UID_KEY),
            'purchase_code' => sanitize_text_field($uid_key),
        ), RNB_UID_URL . '/wp-json/redqteam/v1/elv');

        $response = wp_remote_get($rest_url, []);
        if (is_wp_error($response) || (200 !== wp_remote_retrieve_response_code($response))) {
            wp_send_json_error([
                'message' => __('Something went wrong with API request.', 'redq-rental'),
                'code'    => 404,
                'type'    => 'api_request_failed',
            ]);
        }

        $response_body = wp_remote_retrieve_body($response);
        $result        = json_decode($response_body, true);

        if (('valid' != $result['item']) || intval(RNB_UID_KEY) !== intval($result['product_id'])) {
            wp_send_json_error([
                'message' => __('Purchase code is invalid, try with valid one.', 'redq-rental'),
                'code'    => 404,
                'type'    => 'activation_failed',
            ]);
        }

        if (isset($result['deactivated']) && !empty($result['deactivated'])) {
            update_option('rnb_uid_status', false);
            update_option('rnb_uid', '');
            update_option(base64_encode($_POST['uid_key']), $result);
            wp_send_json_success([
                'message'  => __('License deactivated successfully.', 'turbo'),
                'status'   => __('Inactive', 'turbo'),
                'btn_text' => __('Activate', 'turbo'),
                'code'     => 200,
                'type'     => 'deactivation_success',
            ]);
        }

        update_option('rnb_uid_status', true);
        update_option('rnb_uid', $uid_key);
        update_option(base64_encode($uid_key), $result);

        wp_send_json_success([
            'success'  => true,
            'status'   => __('Active', 'redq-rental'),
            'btn_text' => __('Deactivate', 'redq-rental'),
            'code'     => 200,
            'type'     => 'activation_success',
        ]);
    }
    /**
     * Add a body class for rnb admin order list 
     * @return string 
     */
    public function rnb_order_list_body_class($classes)
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'rnb-order') {
            $classes .= ' post-type-shop_order';
        }
        return $classes;
    }
    public function get_quotes_count(){
        global $wpdb;

        // Meta key and value
        $meta_key = 'rnb_quote_need_view';
        $meta_value = true;
        
        // Custom post type
        $post_type = 'request_quote';
        
        // SQL query to count posts with specific meta data
        $sql = $wpdb->prepare("
            SELECT COUNT(p.ID) as count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND pm.meta_key = %s
            AND pm.meta_value = %s
        ", $post_type, $meta_key, $meta_value);
        
        // Execute the query
        $count = $wpdb->get_var($sql);
        $html = '';
        if($count > 0 ){
            $html =  sprintf('<span class="awaiting-mod update-plugins count-%s"><span class="processing-count">%s</span></span>', $count, $count);
        }
        // Output the count
       return  $html;
    }
    public function quote_mark_as_read () {
        if(isset($_REQUEST['post'])){
            $post_type = get_post_type($_REQUEST['post']);
            if($post_type == 'request_quote'){
                update_post_meta($_REQUEST['post'], 'rnb_quote_need_view', false);
            }
        }
    }
}

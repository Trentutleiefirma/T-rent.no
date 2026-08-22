<?php

namespace REDQ_RnB\Admin;

use WC_Settings_Page, WC_Admin_Settings;

if (!defined('ABSPATH')) {
    exit;  // Exit if accessed directly
}

/**
 * WooCommerce Product Settings
 *
 * @author   Redqteam
 * @category Admin
 * @package  WooCommerce/Admin
 * @version  2.4.0
 */
if (!class_exists('GlobalSettings', false)) :

    /**
     * GlobalSettings.
     */
    class GlobalSettings extends WC_Settings_Page
    {
        /**
         * Constructor.
         */
        public function __construct()
        {
            $this->id    = 'rnb_settings';
            $this->label = __('RnB Settings', 'redq-rental');

            add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_page'), 20);
            add_action('woocommerce_settings_' . $this->id, array($this, 'output'));
            add_action('woocommerce_settings_save_' . $this->id, array($this, 'save'));
            add_filter('woocommerce_admin_settings_sanitize_option', array($this, 'set_default_values'), 10, 2);
            add_action('woocommerce_sections_' . $this->id, array($this, 'output_sections'));
            add_filter('woocommerce_admin_field_rnb_opening_closing', [$this, 'rnb_opening_closing_output_field']);
        }

        /**
         * Get sections.
         *
         * @return array
         */
        public function get_sections()
        {
            $sections = array(
                ''             => __('General', 'redq-rental'),
                'display'      => __('Display', 'redq-rental'),
                'labels'       => __('Labels', 'redq-rental'),
                'conditions'   => __('Conditions', 'redq-rental'),
                'validations'  => __('Validations', 'redq-rental'),
                'layout_two'   => __('Layout 2 (Extra Settings)', 'redq-rental'),
                'order_cancel' => __('Order Cancel', 'redq-rental')
            );

            return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
        }

        /**
         * Output the settings.
         */
        public function output()
        {
            global $current_section;
            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::output_fields($settings);
        }

        /**
         * Save settings.
         */
        public function save()
        {
            global $current_section;
            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::save_fields($settings);
        }

        /**
         * Set default values based on conditions
         *
         * @param string $value
         * @param array $option
         * @return string
         */
        public function set_default_values($value, $option)
        {
            if ($option['id'] === 'rnb_instance_payment' && $value === '') {
                $value = 100;
            }

            return $value;
        }

        /**
         * Get settings array.
         *
         * @param string $current_section
         *
         * @return array
         */
        public function get_settings($current_section = '')
        {
            if ('display' == $current_section) {
                $settings = apply_filters('woocommerce_display_settings', array(

                    array(
                        'title' => __('Show or Hide DateTime Fields', 'redq-rental'),
                        'type'  => 'title',
                        'desc'  => '',
                        'id'    => 'rnb_datetime_options',
                    ),

                    array(
                        'title'           => __('Enable DateTime Fields', 'redq-rental'),
                        'desc'            => __('Enable DateTime Fields', 'redq-rental'),
                        'id'              => 'rnb_enable_datetime_fields',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => 'start',
                        'show_if_checked' => 'option',
                    ),

                    array(
                        'desc'            => __('Show Pickup Date', 'redq-rental'),
                        'id'              => 'rnb_show_pickup_date',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Show Pickup Time', 'redq-rental'),
                        'id'              => 'rnb_show_pickup_time',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Show Drop-off Date', 'redq-rental'),
                        'id'              => 'rnb_show_dropoff_date',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Show Drop-off Time', 'redq-rental'),
                        'id'              => 'rnb_show_dropoff_time',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'title'   => __('Enable Quantity', 'redq-rental'),
                        'desc'    => __('Enable Quantity Field', 'redq-rental'),
                        'id'      => 'rnb_enable_quantity',
                        'default' => 'yes',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'image_options',
                    ),

                    array(
                        'title' => __('Display Button Options', 'redq-rental'),
                        'type'  => 'title',
                        'desc'  => '',
                        'id'    => 'rnb_button_options',
                    ),

                    array(
                        'title'   => __('Button Options', 'redq-rental'),
                        'desc'    => __('Show Book Now Button', 'redq-rental'),
                        'id'      => 'rnb_enable_book_now_btn',
                        'default' => 'yes',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'desc'    => __('Show Request For Quote Button', 'redq-rental'),
                        'id'      => 'rnb_enable_rfq_btn',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'image_options',
                    ),

                    array(
                        'title' => __('Others Options', 'redq-rental'),
                        'type'  => 'title',
                        'desc'  => '',
                        'id'    => 'rnb_others_options',
                    ),

                    array(
                        'title'           => __('Show Tax', 'redq-rental'),
                        'desc'            => __('Show Tax On Single Product Page', 'redq-rental'),
                        'id'              => 'rnb_enable_enable_tax_on_single_product',
                        'default'         => 'no',
                        'type'            => 'checkbox',
                    ),
                    array(
                        'title'           => __('Show Price FlipBox', 'redq-rental'),
                        'desc'            => __('Show Price FlipBox', 'redq-rental'),
                        'id'              => 'rnb_enable_price_flipbox',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                    ),

                    array(
                        'title'   => __('Show Price Discount', 'redq-rental'),
                        'desc'    => __('Show Price Discount', 'redq-rental'),
                        'id'      => 'rnb_enable_price_discount',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'title'   => __('Show Instance Payment', 'redq-rental'),
                        'desc'    => __('Show Instance Payment', 'redq-rental'),
                        'id'      => 'rnb_enable_instance_payment',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'image_options',
                    ),

                ));
            } elseif ('labels' == $current_section) {
                $settings = apply_filters('rnb_labels_settings', array(

                    array(
                        'title' => __('Location Labels', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_location_start',
                    ),
                    array(
                        'title'       => __('Pickup Location Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Pickup location title in front-end', 'redq-rental'),
                        'id'          => 'rnb_pickup_location_title',
                        'placeholder' => __('Pickup Location', 'redq-rental'),
                        'default'     => __('Pickup Location', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Pickup Location Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Pickup location placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_pickup_location_placeholder',
                        'placeholder' => __('Choose Pickup Location', 'redq-rental'),
                        'default'     => __('Pickup Location', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Return Location Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Return Location title in front-end', 'redq-rental'),
                        'id'          => 'rnb_dropoff_location_title',
                        'placeholder' => __('Return Location', 'redq-rental'),
                        'default'     => __('Return Location', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Return Location Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Return Location placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_dropoff_location_placeholder',
                        'placeholder' => __('Return Location', 'redq-rental'),
                        'default'     => __('Return Location', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_location_end',
                    ),


                    array(
                        'title' => __('Duration Labels', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_duration_start',
                    ),

                    array(
                        'title'       => __('Pickup DateTime Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Pickup Date title in front-end', 'redq-rental'),
                        'id'          => 'rnb_pickup_datetime_title',
                        'placeholder' => __('Pickup Date & Time', 'redq-rental'),
                        'default'     => __('Pickup Date & Time', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Pickup Date Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Pickup Date Placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_pickup_date_placeholder',
                        'placeholder' => __('Pickup Date', 'redq-rental'),
                        'default'     => __('Pickup Date', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Pickup Time Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Pickup Time Placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_pickup_time_placeholder',
                        'placeholder' => __('Pickup Time', 'redq-rental'),
                        'default'     => __('Pickup Time', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Return DateTime Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as return Date title in front-end', 'redq-rental'),
                        'id'          => 'rnb_dropoff_datetime_title',
                        'placeholder' => __('Return Date & Time', 'redq-rental'),
                        'default'     => __('Return Date & Time', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Drop-off Date Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Drop-off Date Placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_dropoff_date_placeholder',
                        'placeholder' => __('Drop-off Date', 'redq-rental'),
                        'default'     => __('Drop-off Date', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Drop-off Time Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Drop-off Time Placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_dropoff_time_placeholder',
                        'placeholder' => __('Drop-off Time', 'redq-rental'),
                        'default'     => __('Drop-off Time', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_duration_end',
                    ),

                    array(
                        'title' => __('Extras Labels', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_extras_start',
                    ),

                    array(
                        'title'       => __('Resources Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as resources title in front-end', 'redq-rental'),
                        'id'          => 'rnb_resources_title',
                        'placeholder' => __('Resources', 'redq-rental'),
                        'default'     => __('Resources', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Categories Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Categories title in front-end', 'redq-rental'),
                        'id'          => 'rnb_categories_title',
                        'placeholder' => __('Categories', 'redq-rental'),
                        'default'     => __('Categories', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Deposit Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Deposit title in front-end', 'redq-rental'),
                        'id'          => 'rnb_deposit_title',
                        'placeholder' => __('Deposit', 'redq-rental'),
                        'default'     => __('Deposit', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Adults Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Adults title in front-end', 'redq-rental'),
                        'id'          => 'rnb_adults_title',
                        'placeholder' => __('Adults', 'redq-rental'),
                        'default'     => __('Adults', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Adults Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Adults Placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_adults_placeholder',
                        'placeholder' => __('Choose Adults', 'redq-rental'),
                        'default'     => __('Choose Adults', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Childs Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Childs title in front-end', 'redq-rental'),
                        'id'          => 'rnb_childs_title',
                        'placeholder' => __('Childs', 'redq-rental'),
                        'default'     => __('Childs', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Childs Placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Childs Placeholder in front-end', 'redq-rental'),
                        'id'          => 'rnb_childs_placeholder',
                        'placeholder' => __('Choose Childs', 'redq-rental'),
                        'default'     => __('Choose Childs', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_extras_end',
                    ),

                    array(
                        'title' => __('Others Labels', 'redq-rental'),
                        'type'  => 'title',
                        'desc'  => '',
                        'id'    => 'rnb_others_label_start',
                    ),

                    array(
                        'title'       => __('Choose Inventory Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as inventory title in front-end', 'redq-rental'),
                        'id'          => 'rnb_inventory_title',
                        'placeholder' => __('Choose Inventory', 'redq-rental'),
                        'default'     => __('Choose Inventory', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Pricing Info Title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as pricing title in front-end', 'redq-rental'),
                        'id'          => 'rnb_pricing_flipbox_info_title',
                        'placeholder' => __('Pricing Info', 'redq-rental'),
                        'default'     => __('Pricing Info', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Unit Price Title', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_unit_price',
                        'placeholder' => __('Per Day', 'redq-rental'),
                        'default'     => __('/Day', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'title'       => __('Quantity Title', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_quantity_title',
                        'placeholder' => __('Quantity', 'redq-rental'),
                        'default'     => __('Quantity', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('Book Now Button Text', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Book Now text in front-end', 'redq-rental'),
                        'id'          => 'rnb_book_now_text',
                        'placeholder' => __('Book Now', 'redq-rental'),
                        'default'     => __('Book Now', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_others_label_end',
                    ),

                    array(
                        'title' => __('Customize Notice', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_notice_section_start',
                    ),
                    array(
                        'name'        => __('Invalid Date Range Notice', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_invalid_date_range_notice',
                        'placeholder' => __('Invalid date range', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'        => __('Max Day Notice', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_max_day_notice',
                        'placeholder' => __('Enter max day exceed notice', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'        => __('Min Day Notice', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_min_day_notice',
                        'placeholder' => __('Enter min day notice', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'        => __('Invalid Quantity', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_quantity_notice',
                        'placeholder' => __('Enter Quantity notice', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'        => __('Max Hour Notice', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_max_hour_notice',
                        'placeholder' => __('Enter max hour exceed notice', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'        => __('Min Hour Notice', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_min_hour_notice',
                        'placeholder' => __('Enter min hour notice', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    // array(
                    //     'title' => __('Discount Text', 'redq-rental'),
                    //     'type' => 'text',
                    //     'desc' => __('This text will show as discount text in front-end', 'redq-rental'),
                    //     'id'   => 'rnb_discount_text',
                    //     'placeholder' => __('Total Discount', 'redq-rental'),
                    //     'default'  => __('Total Discount', 'redq-rental'),
                    //     'css'      => 'width: 250px;',
                    //     'autoload' => false,
                    //     'desc_tip' => true,
                    //     'class'    => 'manage_stock_field',
                    // ),
                    // array(
                    //     'title' => __('Total Cost Text', 'redq-rental'),
                    //     'type' => 'text',
                    //     'desc' => __('This text will show as Total Cost text in front-end', 'redq-rental'),
                    //     'id'   => 'rnb_total_cost_text',
                    //     'placeholder' => __('Total Cost', 'redq-rental'),
                    //     'default'  => __('Total Cost', 'redq-rental'),
                    //     'css'      => 'width: 250px;',
                    //     'autoload' => false,
                    //     'desc_tip' => true,
                    //     'class'    => 'manage_stock_field',
                    // ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_notice_section_end',
                    ),

                    array(
                        'title' => __('Customize Request For Quote', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_rfq_section_start',
                    ),
                    array(
                        'title'       => __('Request For Quote Button Text', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show as Request For Quote text in front-end', 'redq-rental'),
                        'id'          => 'rnb_rfq_text',
                        'placeholder' => __('Request For Quote', 'redq-rental'),
                        'default'     => __('Request For Quote', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ username title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_username_title',
                        'placeholder' => __('Username', 'redq-rental'),
                        'default'     => __('Username', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ username placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_username_placeholder',
                        'placeholder' => __('Username', 'redq-rental'),
                        'default'     => __('Username', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ password title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_password_title',
                        'placeholder' => __('password', 'redq-rental'),
                        'default'     => __('password', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ password placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_password_placeholder',
                        'placeholder' => __('password', 'redq-rental'),
                        'default'     => __('password', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ first name title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_first_name_title',
                        'placeholder' => __('First name', 'redq-rental'),
                        'default'     => __('First name', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ first name placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_first_name_placeholder',
                        'placeholder' => __('First name', 'redq-rental'),
                        'default'     => __('First name', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ last name title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_last_name_title',
                        'placeholder' => __('Last name', 'redq-rental'),
                        'default'     => __('Last name', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ last name placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_last_name_placeholder',
                        'placeholder' => __('Last name', 'redq-rental'),
                        'default'     => __('Last name', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ email title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_email_title',
                        'placeholder' => __('Email', 'redq-rental'),
                        'default'     => __('Email', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ email placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_email_placeholder',
                        'placeholder' => __('Email', 'redq-rental'),
                        'default'     => __('Email', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ phone title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_phone_title',
                        'placeholder' => __('Phone', 'redq-rental'),
                        'default'     => __('Phone', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ phone placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_phone_placeholder',
                        'placeholder' => __('Phone', 'redq-rental'),
                        'default'     => __('Phone', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ message title', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_message_title',
                        'placeholder' => __('Message', 'redq-rental'),
                        'default'     => __('Message', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ message placeholder', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_message_placeholder',
                        'placeholder' => __('Message', 'redq-rental'),
                        'default'     => __('Message', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'title'       => __('RFQ submit button text', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('This text will show in the request for quote form', 'redq-rental'),
                        'id'          => 'rnb_submit_button_text',
                        'placeholder' => __('Submit', 'redq-rental'),
                        'default'     => __('Submit', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_rfq_section_end',
                    ),

                ));
            } elseif ('conditions' == $current_section) {
                $settings = apply_filters('rnb_global_conditions_settings', array(
                    array(
                        'title' => __('Choose Different Conditions Logic For Products', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_conditionals_options',
                    ),

                    array(
                        'title'    => __('Date Format', 'redq-rental'),
                        'desc'     => __('Date will display in this format all place in rental product', 'redq-rental'),
                        'id'       => 'rnb_choose_date_format',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:150px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'm/d/Y' => __('m/d/Y', 'redq-rental'),
                            'd/m/Y' => __('d/m/Y', 'redq-rental'),
                            'Y/m/d' => __('Y/m/d', 'redq-rental'),
                        ),
                        'autoload' => false,
                        'default'  => 'm/d/Y',
                    ),

                    array(
                        'title'    => __('Time Format', 'redq-rental'),
                        'desc'     => __('This will be applicable in the time picker field in product page', 'redq-rental'),
                        'id'       => 'rnb_choose_time_format',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:150px;',
                        'desc_tip' => true,
                        'options'  => array(
                            '24-hours' => __('24 Hours', 'redq-rental'),
                            '12-hours' => __('12 Hours', 'redq-rental'),
                        ),
                        'autoload' => true,
                        'default'  => '24-hours',
                    ),

                    array(
                        'title'             => __('Max Hour Late', 'redq-rental'),
                        'desc'              => __('Another day will count if customer returns by exceeding this no. of hour. Suppose you set the hour as 2. Now if a customer place an order from  10/10/2018 at 10:00 to 12/10/2018 at 12:00 he will be charged for 2 days ( although here is 50 hours means 2days and 2 hours). Now if he returns after 12/10/2018 at 12:00 then he will be charged for 3days ', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_max_time_late',
                        'default'           => '0',
                        'css'               => 'width: 50px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),

                    array(
                        'title'    => __('Pay Extra Hours', 'redq-rental'),
                        'desc'     => __('If you make this option as yes then customer has to pay for extra hours in hourly pricing plan.', 'redq-rental'),
                        'id'       => 'rnb_pay_extra_hours',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:150px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'yes' => __('Yes', 'redq-rental'),
                            'no'  => __('No', 'redq-rental'),
                        ),
                        'default'  => 'yes',
                        'autoload' => false,
                    ),

                    array(
                        'title'    => __('Single Day Booking', 'redq-rental'),
                        'desc'     => __('Checked : If pickup and return date are same then it counts as 1-day. Also select this for single date. FYI : Set max time late as at least 0 for this. UnChecked : If pickup and return date are same then it counts as 0-day. Also select this for single date. ', 'redq-rental'),
                        'id'       => 'rnb_single_day_booking',
                        'desc_tip' => true,
                        'default'  => 'yes',
                        'type'     => 'checkbox',
                    ),
                    array(
                        'title'    => __('Include Trailing Date', 'redq-rental'),
                        'desc'     => __('Checked : if pickup & return time are disabled then pickup date and return date will count as individual date. Also make sure that you set pay extra hour option as no. UnChecked : if pickup & return time are disabled then pickup date and return date will count as previous manner means without 24-hours separate date will not count', 'redq-rental'),
                        'id'       => 'rnb_include_trailing_date',
                        'desc_tip' => true,
                        'default'  => 'yes',
                        'type'     => 'checkbox',
                    ),
                    array(
                        'title'             => __('Max Booking Days', 'redq-rental'),
                        'desc'              => __('No. of days that customer must have to select during placing an order otherwise he will not be allowed to place an order', 'redq-rental'),
                        'placeholder'       => __('E.g. - 6', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_max_book_day',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),
                    array(
                        'title'             => __('Min Booking Days', 'redq-rental'),
                        'desc'              => __('No. of days that customer must have to select during placing an order otherwise he will not be allowed to place an order', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_min_book_day',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),
                    array(
                        'title'             => __('Max Booking Hours', 'redq-rental'),
                        'desc'              => __('No. of hours that customer must have to select during placing an order otherwise he will not be allowed to place an order', 'redq-rental'),
                        'placeholder'       => __('E.g. - 6', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_max_book_hours',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),
                    array(
                        'title'             => __('Min Booking Hours', 'redq-rental'),
                        'desc'              => __('No. of hours that customer must have to select during placing an order otherwise he will not be allowed to place an order', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_min_book_hours',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),
                    array(
                        'title'             => __('Initially blocked dates in calendar', 'redq-rental'),
                        'desc'              => __('If you set the value as 2, When someone open the calendar in product page if today is 10/10/2018 then customer will see the initially bookable date as 12/10/2018', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_staring_block_days',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),

                    array(
                        'title'             => __('Pre Booking Block Days', 'redq-rental'),
                        'desc'              => __('Selected no. of days will be blocked automatically after a booking order and customer will not be charged for extra these days. Suppose you set the value 2. Now if any customer books date from 10/10/18 to 12/10/18 then after completing the order 08/10/18 to 10/10/18 date will be disabled in calendar for this order. Although customer will not be charged for these extra 2 days', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_before_block_days',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),

                    array(
                        'title'             => __('Post Booking Block Days', 'redq-rental'),
                        'desc'              => __('Selected no. of days will be blocked automatically after a booking and customer will not be charged for extra these days. Suppose you set the value 2. Now if any customer books date from 10/10/18 to 12/10/18 then after completing the order 10/10/18 to 14/10/18 date will be disabled in calendar for this order. Although customer will not be charged for these extra 2 days', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_post_block_days',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),
                    array(
                        'title'             => __('Pre Booking Block Hours', 'redq-rental'),
                        'desc'              => __('Selected no. of hours will be blocked automatically after a booking order and customer will not be charged for extra these hours. Suppose you set the value 2. Now if any customer books date from 10am to 12pm then after completing the order 8am to 10pm date will be disabled in calendar for this order. Although customer will not be charged for these extra 2 hours', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_before_block_hours',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),

                    array(
                        'title'             => __('Post Booking Block Hours', 'redq-rental'),
                        'desc'              => __('Selected no. of hours will be blocked automatically after a booking and customer will not be charged for extra these hours. Suppose you set the value 2. Now if any customer books date from 8am to 10pm then after completing the order 10am to 12pm date will be disabled in calendar for this order. Although customer will not be charged for these extra 2 hours', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'type'              => 'number',
                        'id'                => 'rnb_post_block_hours',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0'
                        ),
                    ),
                    array(
                        'title'             => __('Time Intervals (Mins)', 'redq-rental'),
                        'desc'              => __('Time Interval in mins E.g. - 20. Time interval will not work if you use allowed times options', 'redq-rental'),
                        'placeholder'       => __('E.g. - 20', 'redq-rental'),
                        'type'              => 'number',
                        'default'           => 30,
                        'id'                => 'rnb_time_intervals',
                        'css'               => 'width: 90px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0',
                            'max'  => '60'
                        ),
                    ),

                    array(
                        'name'              => __('Allowed Times (Comma, separated)', 'redq-rental'),
                        'type'              => 'textarea',
                        'desc'              => __('Enter allowed time in comma separated format like 10:00, 12:00 (For 24 hour time format) or 10:00 am, 11:00 am (For 12 hour time format. Use space before am or pm) ', 'redq-rental'),
                        'id'                => 'rnb_allowed_times',
                        'placeholder'       => __('Enter allowed time in comma separated format like 10:00, 12:00 (For 24 hour date format) or 10:00 am, 11:00 am (For 12 hour date format. Use space before am or pm) ', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),

                    array(
                        'title'    => __('Show Product Price', 'redq-rental'),
                        'desc'     => __('If you set daily pricing then per day price will show as product price, if you set hourly pricing then hour price will show as product price', 'redq-rental'),
                        'id'       => 'rnb_show_price_type',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:150px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'daily'  => __('Daily Pricing', 'redq-rental'),
                            'hourly' => __('Hourly Pricing', 'redq-rental'),
                        ),
                        'autoload' => false,
                    ),

                    array(
                        'title'    => __('Select Weekends', 'redq-rental'),
                        'desc'     => __('Choose Weekends', 'redq-rental'),
                        'id'       => 'rnb_weekends',
                        'type'     => 'multiselect',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:300px;',
                        'desc_tip' => true,
                        'options'  => array(
                            1 => esc_html__('Sunday', 'redq-rental'),
                            2 => esc_html__('Monday', 'redq-rental'),
                            3 => esc_html__('Tuesday', 'redq-rental'),
                            4 => esc_html__('Wednesday', 'redq-rental'),
                            5 => esc_html__('Thursday', 'redq-rental'),
                            6 => esc_html__('Friday', 'redq-rental'),
                            7 => esc_html__('Saturday', 'redq-rental'),
                        ),
                    ),

                    array(
                        'title'    => __('Choose Layout', 'redq-rental'),
                        'desc'     => __('Choose your booking page layout. Either it will be normal view or modal view', 'redq-rental'),
                        'id'       => 'rnb_booking_layout',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:150px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'layout_one' => __('Normal Layout', 'redq-rental'),
                            'layout_two' => __('Modal Layout', 'redq-rental'),
                        ),
                        'autoload' => false,
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'digital_download_options',
                    ),






                ));
            } elseif ('validations' == $current_section) {
                $settings = apply_filters('rnb_validations_settings', array(

                    array(
                        'title' => __('Enable or Disable Required Fields', 'redq-rental'),
                        'type'  => 'title',
                        'desc'  => '',
                        'id'    => 'rnb_datetime_options',
                    ),

                    array(
                        'title'           => __('Required Fields', 'redq-rental'),
                        'desc'            => __('Choose Value Must Required Fields', 'redq-rental'),
                        'id'              => 'rnb_required_fields',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => 'start',
                        'show_if_checked' => 'option',
                    ),

                    array(
                        'desc'            => __('Required Pickup Location [Not applicable for modal layout]', 'redq-rental'),
                        'id'              => 'rnb_required_pickup_loc',
                        'default'         => 'no',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Required Drop-off Location [Not applicable for modal layout]', 'redq-rental'),
                        'id'              => 'rnb_required_dropoff_loc',
                        'default'         => 'no',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Required Pickup Time', 'redq-rental'),
                        'id'              => 'rnb_required_pickup_time',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Required Drop-off Time', 'redq-rental'),
                        'id'              => 'rnb_required_dropoff_time',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'desc'            => __('Required Adult', 'redq-rental'),
                        'id'              => 'rnb_required_person',
                        'default'         => 'no',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),

                    array(
                        'title'   => __('Daily Basis Opening & Closing Time', 'redq-rental'),
                        'desc'    => __('Enter time in the following format. 10:00 or 23:30 or 24:00 etc (For 24-hour time format) and 10:00 am or 1:30 pm or 2:00 pm etc (For 12-hour time format. Space before am or pm is important)', 'redq-rental'),
                        'id'      => 'rnb_opening_closing',
                        'type'    => 'rnb_opening_closing',
                        'default' => ''
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'digital_download_options',
                    ),

                ));
            } elseif ('layout_two' == $current_section) {
                $settings = apply_filters('rnb_validations_settings', array(

                    array(
                        'title' => __('Extra Fields For Layout Two', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_layout_two_fields',
                    ),

                    array(
                        'title'    => __('RnB background color', 'redq-rental'),
                        'desc'     => sprintf(__('The main RnB background color. Default %s.', 'redq-rental'), '<code>#b07aa4</code>'),
                        'id'       => 'rnb_overall_color_display_option',
                        'type'     => 'color',
                        'css'      => 'width:6em;',
                        'default'  => '#b07aa4',
                        'autoload' => false,
                        'desc_tip' => true,
                    ),

                    array(
                        'name'        => __('Inventory Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_inventory_top_heading',
                        'placeholder' => __('Inventory Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Inventory Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_inventory_top_desc',
                        'placeholder'       => __('Inventory top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),

                    array(
                        'name'        => __('Inventory Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_inventory_inner_heading',
                        'placeholder' => __('Inventory Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Inventory Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_inventory_inner_desc',
                        'placeholder'       => __('Inventory inner description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Date Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_date_top_heading',
                        'placeholder' => __('Date Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Date Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_date_top_desc',
                        'placeholder'       => __('Date top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Date Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_date_inner_heading',
                        'placeholder' => __('Date Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Date Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_date_inner_desc',
                        'placeholder'       => __('Date Inner Description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Location Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_location_top_heading',
                        'placeholder' => __('Location Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Location Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_location_top_desc',
                        'placeholder'       => __('Location top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),

                    array(
                        'name'        => __('Location Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_location_inner_heading',
                        'placeholder' => __('Location Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Location Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_location_inner_desc',
                        'placeholder'       => __('Location Inner Description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Resource Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_resource_top_heading',
                        'placeholder' => __('Resource Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Resource Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_resource_top_desc',
                        'placeholder'       => __('Resource top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Resource Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_resource_inner_heading',
                        'placeholder' => __('Resource Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Resource Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_resource_inner_desc',
                        'placeholder'       => __('Resource Inner Description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Person Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_person_top_heading',
                        'placeholder' => __('Person Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Person Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_person_top_desc',
                        'placeholder'       => __('Person top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Person Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_person_inner_heading',
                        'placeholder' => __('Person Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Person Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_person_inner_desc',
                        'placeholder'       => __('Person Inner Description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Deposit Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_deposit_top_heading',
                        'placeholder' => __('Deposit Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Deposit Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_deposit_top_desc',
                        'placeholder'       => __('Deposit top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),


                    array(
                        'name'        => __('Deposit Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_deposit_inner_heading',
                        'placeholder' => __('Deposit Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Deposit Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_deposit_inner_desc',
                        'placeholder'       => __('Deposit Inner Description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),

                    array(
                        'name'        => __('Summary Top Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_summary_top_heading',
                        'placeholder' => __('Summary Top Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Summary Top Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_summary_top_desc',
                        'placeholder'       => __('Summary top description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),

                    array(
                        'name'        => __('Summary Inner Heading', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_summary_inner_heading',
                        'placeholder' => __('Summary Inner Heading', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'              => __('Summary Inner Description', 'redq-rental'),
                        'type'              => 'textarea',
                        'id'                => 'rnb_summary_inner_desc',
                        'placeholder'       => __('Summary Inner Description', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '3',
                        ),
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'digital_download_options',
                    ),

                ));
            } elseif ('order_cancel' == $current_section) {
                $settings = apply_filters('rnb_products_order_management', array(

                    array(
                        'title' => __('Order Cancel Button', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_product_calendar_personalize',
                    ),

                    array(
                        'title'   => __('Enable Order Cancel Button?', 'redq-rental'),
                        'desc'    => __('Enable order cancel button on my account page on order list', 'redq-rental'),
                        'id'      => 'rnb_enable_cancel_order_button',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),
                    array(
                        'name'        => __('In Which Order Type show button', 'redq-rental'),
                        'type'        => 'textarea',
                        'desc'        => __('In which order type cancel button display, by default, seperated by comma', 'redq-rental'),
                        'id'          => 'rnb_when_cancel_order_button_show',
                        'placeholder' => __(' E.g - pending, on-hold, processing', 'redq-rental'),
                        'css'         => 'min-width:250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                        'default'     => 'pending, on-hold, processing'
                    ),
                    array(
                        'title'   => __('Make cancellation reason input required.', 'redq-rental'),
                        'desc'    => __('Make cancellation reason input required.', 'redq-rental'),
                        'id'      => 'rnb_cancel_order_button_required',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),
                    array(
                        'name'        => __('Customer note ', 'redq-rental'),
                        'type'        => 'textarea',
                        'desc'        => __('Customer note will appear in cancellation request popup', 'redq-rental'),
                        'id'          => 'rnb_cancel_order_customer_note',
                        'css'         => 'min-width:250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                        'default'     => ''
                    ),
                    array(
                        'name'     => __('Cancel Type', 'redq-rental'),
                        'desc'     => __('Please chose cancel type, direct cancel Or cancel request', 'redq-rental'),
                        'id'       => 'rnb_cancel_order_request_type',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:250px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'cancelled'      => esc_html__('Cancelled', 'redq-rental'),
                            'wc-cancel-request' => esc_html__('Cancel request', 'redq-rental'),
                        ),
                        'default' => 'wc-cancel-request',
                    ),
                    array(
                        'name'     => __('Cancel Before By', 'redq-rental'),
                        'desc'     => __('When customer can place cancel Request', 'redq-rental'),
                        'id'       => 'rnb_cancel_order_before_by',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select multiple-true',
                        'multiple' => true,
                        'css'      => 'min-width:250px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'day'      => esc_html__('Day', 'redq-rental'),
                            'hour' => esc_html__('Hour', 'redq-rental'),
                        ),
                        'default' => 'day',
                    ),
                    array(
                        'name'        => __('Day', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('If Cancel Before By Day this value will work', 'redq-rental'),
                        'id'          => 'rnb_cancel_order_before_day',
                        'placeholder' => __('Day E.g - 1', 'redq-rental'),
                        'css'         => 'min-width:250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'name'        => __('Hour', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('If Cancel Before By Hour this value will work', 'redq-rental'),
                        'id'          => 'rnb_cancel_order_before_hour',
                        'placeholder' => __('Hour E.g - 1', 'redq-rental'),
                        'css'         => 'min-width:250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_rfq_gdpr',
                    ),

                ));
            } else {
                $settings = apply_filters('rnb_products_general_settings', array(

                    array(
                        'title' => __('Make Product Calendar In Your Language', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_product_calendar_personalize',
                    ),

                    array(
                        'name'        => __('Language Domain', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter your language domain. E.g. - de', 'redq-rental'),
                        'id'          => 'rnb_lang_domain',
                        'placeholder' => __('Language Domain E.g - fr', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'     => __('RnB Calendar Data Type', 'redq-rental'),
                        'desc'     => __('Please choose rest api if your order data size is big', 'redq-rental'),
                        'id'       => 'rnb_calendar_data_type',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:250px;',
                        'desc_tip' => true,
                        'options'  => array(
                            'pre_fetched' => esc_html__('Pre Fetched', 'redq-rental'),
                            'rest_api'    => esc_html__('Rest API', 'redq-rental'),
                        ),
                        'default' => 'pre_fetched'
                    ),

                    array(
                        'name'     => __('RnB Calendar Active For', 'redq-rental'),
                        'desc'     => __('Enter the number of days for you\'d like the calendar remain active from today date.', 'redq-rental'),
                        'id'       => 'rnb_calendar_block_future_dates',
                        'type'     => 'number',
                        'css'      => 'min-width:250px;',
                        'desc_tip' => true,
                        'default'  => 365
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_instastant_payment_options',
                    ),

                    array(
                        'title' => __('Configure Instance Payments', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_instastant_payment_section_title',
                    ),

                    array(
                        'title'       => __('Instance Pay Type', 'redq-rental'),
                        'desc'        => __('Select the payment type first. If selected payment type is "percentage" then you need to insert a "percentage" value in the below field. On the other hand you need to insert a fixed amount. This works like a partial payment of the total amount of checkout.', 'redq-rental'),
                        'placeholder' => __('E.g. - 2', 'redq-rental'),
                        'id'          => 'rnb_instance_payment_type',
                        'css'         => 'width: 80px;',
                        'type'        => 'select',
                        'class'       => 'wc-enhanced-select',
                        'desc_tip'    => true,
                        'options'     => array(
                            'percent' => esc_html__('Percentage Amount', 'redq-rental'),
                            'fixed'   => esc_html__('Fixed Amount', 'redq-rental')
                        ),
                        'default' => 'percent'
                    ),
                    array(
                        'title'             => __('Pay During Booking', 'redq-rental'),
                        'type'              => 'number',
                        'desc'              => __('You must have to pay this amount during booking (this could be a fixed or percentage amount of total, based on above selection). If "percentage" payment type is selected and this field is empty then default pay amount value is 100% . For the fixed type the default amount is 0.', 'redq-rental'),
                        'placeholder'       => __('E.g. - 2', 'redq-rental'),
                        'id'                => 'rnb_instance_payment',
                        'css'               => 'width: 80px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min'  => '0',
                        ),
                        'default' => 100
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_rfq_options',
                    ),


                    array(
                        'title' => __('Miscellaneous Sections', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_miscellaneous_section',
                    ),

                    // array(
                    //     'title'           => __('Show Price Info At Top in Price Flip box', 'redq-rental'),
                    //     'desc'            => __('Show Price Info At Top in Price Flip box', 'redq-rental'),
                    //     'id'              => 'rnb_flipbox_price_top_info',
                    //     'default'         => 'yes',
                    //     'type'            => 'checkbox',
                    // ),

                    array(
                        'name'        => __('Shop Page Button Label', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter Your Shop Page Button Label. E.g. - Read More', 'redq-rental'),
                        'id'          => 'rnb_shop_page_button',
                        'placeholder' => __('Read More', 'redq-rental'),
                        'default'     => __('Read More', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'              => __('Holidays', 'redq-rental'),
                        'type'              => 'textarea',
                        'desc'              => __('Enter holidays in this format E.g.: 2018-07-18 to 2018-07-26,2018-08-18 to 2018-08-20', 'redq-rental'),
                        'id'                => 'rnb_holidays',
                        'placeholder'       => __('Enter holidays in this format E.g.: 2018-07-18 to 2018-07-26, 2018-08-18 to 2018-08-20', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'class'             => 'manage_stock_field',
                        'custom_attributes' => array(
                            'rows' => '5',
                        ),
                    ),

                    array(
                        'title'    => __('Select day of week start', 'redq-rental'),
                        'desc'     => __('Choose day of week start', 'redq-rental'),
                        'id'       => 'rnb_day_of_week_start',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:250px;',
                        'desc_tip' => true,
                        'options'  => array(
                            1 => esc_html__('Sunday', 'redq-rental'),
                            2 => esc_html__('Monday', 'redq-rental'),
                            3 => esc_html__('Tuesday', 'redq-rental'),
                            4 => esc_html__('Wednesday', 'redq-rental'),
                            5 => esc_html__('Thursday', 'redq-rental'),
                            6 => esc_html__('Friday', 'redq-rental'),
                            7 => esc_html__('Saturday', 'redq-rental'),
                        ),
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_miscellaneous_section',
                    ),

                    array(
                        'title' => __('Request For Quote', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_rfq_personalize',
                    ),

                    array(
                        'title'   => __('Enable RFQ Without Username & Password', 'redq-rental'),
                        'desc'    => __('Enable RFQ Without Username & Password', 'redq-rental'),
                        'id'      => 'rnb_enable_rfq_without_user_pass',
                        'default' => 'yes',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'title'   => __('Enable RFQ on Account Page', 'redq-rental'),
                        'desc'    => __('Enable Request For Quote Endpoint In My Account Page', 'redq-rental'),
                        'id'      => 'rnb_enable_rft_endpoint',
                        'default' => 'yes',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_general_section_end',
                    ),


                    array(
                        'title' => __('Google Map Configuration', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_gmap_section',
                    ),

                    array(
                        'title'   => __('Enable Google Map For Location', 'redq-rental'),
                        'id'      => 'rnb_enable_gmap',
                        'default' => 'yes',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'name'        => __('Google Map Api Key', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter google map API Key', 'redq-rental'),
                        'id'          => 'rnb_gmap_api_key',
                        'placeholder' => __('Google map api key', 'redq-rental'),
                        'css'         => 'width: 550px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_general_section_end',
                    ),

                    array(
                        'title' => __('Universal Labels', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_universal_label',
                    ),

                    array(
                        'name'        => __('Attributes Tab Label', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter the text label E.g. - Attributes Tab', 'redq-rental'),
                        'id'          => 'rnb_attribute_tab',
                        'default'     => __('Attributes', 'redq-rental'),
                        'placeholder' => __('Attributes Tab', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Features Tab Label', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter the text label E.g. - Features Tab', 'redq-rental'),
                        'id'          => 'rnb_feature_tab',
                        'default'     => __('Features', 'redq-rental'),
                        'placeholder' => __('Features Tab', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Total Days Label (cart,checkout,order pages)', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter the text label E.g. - Total Days', 'redq-rental'),
                        'id'          => 'rnb_total_days_label',
                        'default'     => __('Total Days', 'redq-rental'),
                        'placeholder' => __('Total Days', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Total Hours Label (cart,checkout,order pages)', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter the text label E.g. - Total Hours', 'redq-rental'),
                        'id'          => 'rnb_total_hours_label',
                        'default'     => __('Total Hours', 'redq-rental'),
                        'placeholder' => __('Total Hours', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Payment Due Label (cart,checkout,order pages)', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Enter the text label E.g. - Payment Due', 'redq-rental'),
                        'id'          => 'rnb_payment_due_label',
                        'default'     => __('Payment Due', 'redq-rental'),
                        'placeholder' => __('Payment Due', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_universal_label_end',
                    ),

                    array(
                        'title' => __('Personalize Price Breakdown', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_price_breakdown_start',
                    ),

                    array(
                        'name'        => __('Pickup Location Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_pickup_location_cost',
                        'default'     => __('Pickup Location Cost', 'redq-rental'),
                        'placeholder' => __('Pickup Location Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Return Location Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_return_location_cost',
                        'default'     => __('Return Location Cost', 'redq-rental'),
                        'placeholder' => __('Return Location Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Distance Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_distance_cost',
                        'default'     => __('Distance Cost', 'redq-rental'),
                        'placeholder' => __('Distance Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Duration Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_duration_cost',
                        'default'     => __('Duration Cost', 'redq-rental'),
                        'placeholder' => __('Duration Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Discount Amount', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_discount_amount',
                        'default'     => __('Discount Amount', 'redq-rental'),
                        'placeholder' => __('Discount Amount', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Resource Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_resource_cost',
                        'default'     => __('Resource Cost', 'redq-rental'),
                        'placeholder' => __('Resource Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Category Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_category_cost',
                        'default'     => __('Category Cost', 'redq-rental'),
                        'placeholder' => __('Category Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Adult Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_adult_cost',
                        'default'     => __('Adult Cost', 'redq-rental'),
                        'placeholder' => __('Adult Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Child Cost', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_child_cost',
                        'default'     => __('Child Cost', 'redq-rental'),
                        'placeholder' => __('Child Cost', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Deposit Amount', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_deposit_amount',
                        'default'     => __('Deposit Amount', 'redq-rental'),
                        'placeholder' => __('Deposit Amount', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => true,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Instant Pay', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_instant_pay_amount',
                        'default'     => __('Instant Pay Amount', 'redq-rental'),
                        'placeholder' => __('Instant Pay Amount', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => true,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    // array(
                    //     'name'        => __('Initial Value', 'redq-rental'),
                    //     'type'        => 'text',
                    //     'id'          => 'rnb_initial_value_amount',
                    //     'default'     => __('Initial Value', 'redq-rental'),
                    //     'placeholder' => __('Initial Value', 'redq-rental'),
                    //     'css'         => 'width: 250px;',
                    //     'autoload'    => true,
                    //     'desc_tip'    => true,
                    //     'class'       => 'manage_stock_field',
                    // ),

                    // array(
                    //     'name'        => __('Total Instant Pay', 'redq-rental'),
                    //     'type'        => 'text',
                    //     'id'          => 'rnb_total_instant_pay_amount',
                    //     'default'     => __('Total Instant Pay Amount', 'redq-rental'),
                    //     'placeholder' => __('Total Instant Pay Amount', 'redq-rental'),
                    //     'css'         => 'width: 250px;',
                    //     'autoload'    => true,
                    //     'desc_tip'    => true,
                    //     'class'       => 'manage_stock_field',
                    // ),

                    array(
                        'name'        => __('Payment Due', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_payment_due_amount',
                        'default'     => __('Payment Due', 'redq-rental'),
                        'placeholder' => __('Payment Due', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => true,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Quote Total', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_quote_total_amount',
                        'default'     => __('Quote Total', 'redq-rental'),
                        'placeholder' => __('Quote Total', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => true,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Subtotal Amount', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_subtotal',
                        'default'     => __('Subtotal', 'redq-rental'),
                        'placeholder' => __('Subtotal', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('Grand Total', 'redq-rental'),
                        'type'        => 'text',
                        'id'          => 'rnb_grand_total',
                        'default'     => __('Grand Total', 'redq-rental'),
                        'placeholder' => __('Grand Total', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_price_breakdown_end',
                    ),

                    array(
                        'title' => __('Request For Quote Admin Profile', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_rfq_admin_profile',
                    ),

                    array(
                        'name'        => __('RFQ Admin Email', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Admin email that is used for request for quote emails', 'redq-rental'),
                        'id'          => 'rnb_rfq_admin_profile_email',
                        'placeholder' => __('Admin Email', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'name'        => __('RFQ Admin Name', 'redq-rental'),
                        'type'        => 'text',
                        'desc'        => __('Admin name that is used for request for quote emails', 'redq-rental'),
                        'id'          => 'rnb_rfq_admin_profile_name',
                        'placeholder' => __('Admin Name', 'redq-rental'),
                        'css'         => 'width: 250px;',
                        'autoload'    => false,
                        'desc_tip'    => true,
                        'class'       => 'manage_stock_field',
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_rfq_admin_profile',
                    ),

                    array(
                        'title' => __('GDPR Consent', 'redq-rental'),
                        'type'  => 'title',
                        'id'    => 'rnb_rfq_gdpr',
                    ),

                    array(
                        'title'   => __('Enable GDPR Consent', 'redq-rental'),
                        'id'      => 'rnb_rfq_gdpr_enable',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),

                    array(
                        'name'              => __('GDPR Message', 'redq-rental'),
                        'type'              => 'textarea',
                        'desc'              => __('This text will be display on the RFQ form after the checkbox input.', 'redq-rental'),
                        'id'                => 'rnb_rfq_gdpr_text',
                        'placeholder'       => __('Add GDPR Message Label', 'redq-rental'),
                        'default'           => __('Please accept to consent to your data being stored in line with the guidelines set out in our', 'redq-rental'),
                        'css'               => 'width: 550px;',
                        'autoload'          => false,
                        'desc_tip'          => true,
                        'custom_attributes' => array(
                            'rows' => '5',
                        ),
                    ),

                    array(
                        'title'    => __('Select GDPR Privacy Page', 'redq-rental'),
                        'desc'     => __('Select the GDPR privacy page, which will be displayed after the RFQ GDPR checkbox.', 'redq-rental'),
                        'id'       => 'rnb_rfq_gdpr_page',
                        'type'     => 'select',
                        'class'    => 'wc-enhanced-select',
                        'css'      => 'min-width:300px;',
                        'desc_tip' => true,
                        'options'  => wp_list_pluck(get_pages(), 'post_title', 'ID'),
                    ),

                    array(
                        'type' => 'sectionend',
                        'id'   => 'rnb_rfq_gdpr',
                    ),

                ));
            }

            return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
        }

        /**
         * Opening & Closing fields output
         *
         * @return void
         */
        public function rnb_opening_closing_output_field($field)
        {
            $defaults = [
                'fri_min'  => '00:00',
                'fri_max'  => '24:00',
                'sat_min'  => '00:00',
                'sat_max'  => '24:00',
                'sun_min'  => '00:00',
                'sun_max'  => '24:00',
                'mon_min'  => '00:00',
                'mon_max'  => '24:00',
                'thu_min'  => '00:00',
                'thu_max'  => '24:00',
                'wed_min'  => '00:00',
                'wed_max'  => '24:00',
                'thur_min' => '00:00',
                'thur_max' => '24:00'
            ];

            $value = !empty($field['value']) ? $field['value'] : [];

            if (!empty($field['value'])) {
                foreach ($defaults as $key => $default) {
                    if (empty($value[$key])) $value[$key] = $defaults[$key];
                }
            }

            $template = __DIR__ . '/views/html-validation-global-settings.php';

            if (file_exists($template)) {
                include $template;
            }
        }
    }

endif;

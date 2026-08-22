<?php

namespace REDQ_RnB\Admin;

if (!defined('ABSPATH')) {
    exit;
}
/**
 * woo-commerce meta boxes for RnB
 */
class MetaBoxes
{
    public function __construct()
    {
        add_filter('product_type_selector', array($this, 'redq_rental_product_type'));
        add_filter('woocommerce_product_data_tabs', array($this, 'redq_rental_additional_tabs'));
        add_action('woocommerce_product_data_panels', array($this, 'redq_rental_additional_tabs_panel'));
        add_action('woocommerce_product_options_general_product_data', array($this, 'redq_rental_general_tab_info'));
        add_action('woocommerce_process_product_meta', array($this, 'redq_rental_save_meta'));
    }

    public function redq_rental_product_type($product_types)
    {
        $product_types['redq_rental'] = __('Rental Product', 'redq-rental');
        return $product_types;
    }


    public function redq_rental_additional_tabs($product_tabs)
    {
        $product_tabs['rental_inventory'] = array(
            'label'    => __('Inventory', 'redq-rental'),
            'target'   => 'rental_inventory_product_data',
            'class'    => array('hide_if_grouped', 'show_if_redq_rental', 'hide_if_simple', 'hide_if_external', 'hide_if_variable'),
            'priority' => '80',
        );

        $product_tabs['price_discount'] = array(
            'label'    => __('Price Discount', 'redq-rental'),
            'target'   => 'price_discount_product_data',
            'class'    => array('hide_if_grouped', 'show_if_redq_rental', 'hide_if_simple', 'hide_if_external', 'hide_if_variable'),
            'priority' => '100'
        );

        $product_tabs['settings'] = array(
            'label'    => __('Settings', 'redq-rental'),
            'target'   => 'product_settings_data',
            'class'    => array('hide_if_grouped', 'show_if_redq_rental', 'hide_if_simple', 'hide_if_external', 'hide_if_variable'),
            'priority' => '110'
        );

        return $product_tabs;
    }


    public function redq_rental_general_tab_info()
    {
        global $post;
        $post_id = $post->ID;
        include('views/redq-rental-general-tab.php');
    }


    public function redq_rental_additional_tabs_panel()
    {
        global $post;
        $post_id = $post->ID;
        include('views/redq-rental-additional-tabs-panel.php');
    }


    /**
     * Save rental data
     *
     * @param int $post_id
     * @return void
     */
    public function redq_rental_save_meta($post_id)
    {
        if (isset($_POST['_redq_product_inventory'])) {

            global $wpdb;
            $pivot_table = $wpdb->prefix . 'rnb_inventory_product';

            $wpdb->delete($pivot_table, array('product' => $post_id), array('%d'));

            $values = array();
            $fields = array();

            if (isset($_POST['_redq_product_inventory'])) {
                foreach ($_POST['_redq_product_inventory'] as $pvi) {
                    $values[] = "(%d, %d)";
                    $fields[] = $pvi;
                    $fields[] = $post_id;
                }
            }

            $values = implode(",", $values);
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $pivot_table ( inventory, product ) VALUES $values",
                $fields
            ));

            //Manage product _price meta
            if (is_rental_product($post_id)) {
                $result = rnb_get_product_price($post_id);
                $price = $result['price'];
                update_post_meta($post_id, '_redq_product_inventory', $_POST['_redq_product_inventory']); //Required for demo importing
            }

            // update_post_meta($post_id, '_price', wc_format_decimal($price));
            //End
        }

        // Price discount data
        $price_discount_cost = array();
        if (isset($_POST['redq_price_discount_min_days']) && isset($_POST['redq_price_discount_max_days'])) {
            $price_discount_min_days = $_POST['redq_price_discount_min_days'];
            $price_discount_max_days = $_POST['redq_price_discount_max_days'];
            $price_discount_type = $_POST['redq_price_discount_type'];
            $price_discount_amount = $_POST['redq_price_discount'];

            for ($i = 0; $i < sizeof($price_discount_min_days); $i++) {
                if (!$price_discount_min_days[$i] || !$price_discount_max_days[$i] || !$price_discount_amount[$i]) continue;
                $price_discount_cost[$i]['min_days'] = intval($price_discount_min_days[$i]);
                $price_discount_cost[$i]['max_days'] = intval($price_discount_max_days[$i]);
                $price_discount_cost[$i]['discount_type'] = isset($price_discount_type[$i]) && !empty($price_discount_type[$i]) ? $price_discount_type[$i] : 'percentage';
                $price_discount_cost[$i]['discount_amount'] = isset($price_discount_amount[$i]) && !empty($price_discount_amount[$i]) ? (float) $price_discount_amount[$i] : 0;
            }
        }
        if (isset($price_discount_cost)) {
            update_post_meta($post_id, 'redq_price_discount_cost', $price_discount_cost);
        }


        if (isset($_POST['rental_inventory_count'])) {
            update_post_meta($post_id, 'redq_rental_inventory_count', $_POST['rental_inventory_count']);
        }

        // Choose settings

        if (isset($_POST['rnb_settings_for_display'])) {
            update_post_meta($post_id, 'rnb_settings_for_display', $_POST['rnb_settings_for_display']);
        }
        if (isset($_POST['rnb_settings_for_labels'])) {
            update_post_meta($post_id, 'rnb_settings_for_labels', $_POST['rnb_settings_for_labels']);
        }
        if (isset($_POST['rnb_settings_for_conditions'])) {
            update_post_meta($post_id, 'rnb_settings_for_conditions', $_POST['rnb_settings_for_conditions']);
        }
        if (isset($_POST['rnb_settings_for_validations'])) {
            update_post_meta($post_id, 'rnb_settings_for_validations', $_POST['rnb_settings_for_validations']);
        }


        $settings_data = array();

        if (isset($_POST['block_rental_dates'])) {
            update_post_meta($post_id, 'redq_block_general_dates', $_POST['block_rental_dates']);
        }

        if (isset($_POST['choose_date_format'])) {
            update_post_meta($post_id, 'redq_calendar_date_format', $_POST['choose_date_format']);
        }

        if (isset($_POST['choose_time_format'])) {
            update_post_meta($post_id, 'redq_calendar_time_format', $_POST['choose_time_format']);
        }

        if ($_POST['choose_date_format'] === 'd/m/Y') {
            update_post_meta($post_id, 'redq_choose_european_date_format', 'yes');
        } else {
            update_post_meta($post_id, 'redq_choose_european_date_format', 'no');
        }

        $choose_euro_format = get_post_meta($post_id, 'redq_choose_european_date_format', true);

        if (isset($_POST['redq_max_time_late'])) {
            update_post_meta($post_id, 'redq_max_time_late', $_POST['redq_max_time_late']);
        }

        if (isset($_POST['rnb_pay_extra_hours'])) {
            update_post_meta($post_id, 'rnb_pay_extra_hours', $_POST['rnb_pay_extra_hours']);
        }

        if (isset($_POST['redq_max_rental_days'])) {
            update_post_meta($post_id, 'redq_max_rental_days', $_POST['redq_max_rental_days']);
        }

        if (isset($_POST['redq_min_rental_days'])) {
            update_post_meta($post_id, 'redq_min_rental_days', $_POST['redq_min_rental_days']);
        }

        if (isset($_POST['redq_max_rental_hours'])) {
            update_post_meta($post_id, 'redq_max_rental_hours', $_POST['redq_max_rental_hours']);
        }

        if (isset($_POST['redq_min_rental_hours'])) {
            update_post_meta($post_id, 'redq_min_rental_hours', $_POST['redq_min_rental_hours']);
        }

        if (isset($_POST['redq_rental_starting_block_dates'])) {
            update_post_meta($post_id, 'redq_rental_starting_block_dates', $_POST['redq_rental_starting_block_dates']);
        }

        if (isset($_POST['redq_rental_before_booking_block_dates'])) {
            update_post_meta($post_id, 'redq_rental_before_booking_block_dates', $_POST['redq_rental_before_booking_block_dates']);
        }

        if (isset($_POST['redq_rental_post_booking_block_dates'])) {
            update_post_meta($post_id, 'redq_rental_post_booking_block_dates', $_POST['redq_rental_post_booking_block_dates']);
        }
        if (isset($_POST['redq_rental_before_booking_block_hours'])) {
            update_post_meta($post_id, 'redq_rental_before_booking_block_hours', $_POST['redq_rental_before_booking_block_hours']);
        }

        if (isset($_POST['redq_rental_post_booking_block_hours'])) {
            update_post_meta($post_id, 'redq_rental_post_booking_block_hours', $_POST['redq_rental_post_booking_block_hours']);
        }

        if (isset($_POST['redq_time_interval'])) {
            update_post_meta($post_id, 'redq_time_interval', $_POST['redq_time_interval']);
        }

        if (isset($_POST['rnb_show_price_type'])) {
            update_post_meta($post_id, 'rnb_show_price_type', $_POST['rnb_show_price_type']);
        }

        if (isset($_POST['redq_allowed_times'])) {
            update_post_meta($post_id, 'redq_allowed_times', $_POST['redq_allowed_times']);
        }

        if (isset($_POST['rnb_booking_layout'])) {
            update_post_meta($post_id, 'rnb_booking_layout', $_POST['rnb_booking_layout']);
        }

        if (isset($_POST['redq_rental_local_enable_single_day_time_based_booking'])) {
            update_post_meta($post_id, 'redq_rental_local_enable_single_day_time_based_booking', $_POST['redq_rental_local_enable_single_day_time_based_booking']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_enable_single_day_time_based_booking', 'closed');
        }

        $trailing_date = isset($_POST['rnb_include_trailing_date']) ? $_POST['rnb_include_trailing_date'] : 'closed';
        update_post_meta($post_id, 'rnb_include_trailing_date', $trailing_date);

        if (isset($_POST['redq_rental_local_quantity_on_days'])) {
            update_post_meta($post_id, 'redq_rental_local_quantity_on_days', $_POST['redq_rental_local_quantity_on_days']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_quantity_on_days', 'closed');
        }


        if (isset($_POST['redq_rental_off_days'])) {
            update_post_meta($post_id, 'redq_rental_off_days', $_POST['redq_rental_off_days']);
        } else {
            $em = array();
            update_post_meta($post_id, 'redq_rental_off_days', $em);
        }

        if (isset($_POST['rnb_inventory_title'])) {
            update_post_meta($post_id, 'rnb_inventory_title', $_POST['rnb_inventory_title']);
        }

        if (isset($_POST['redq_show_pricing_flipbox_text'])) {
            update_post_meta($post_id, 'redq_show_pricing_flipbox_text', $_POST['redq_show_pricing_flipbox_text']);
        }

        if (isset($_POST['redq_flip_pricing_plan_text'])) {
            update_post_meta($post_id, 'redq_flip_pricing_plan_text', $_POST['redq_flip_pricing_plan_text']);
        }

        if (isset($_POST['rnb_unit_price'])) {
            update_post_meta($post_id, 'rnb_unit_price', $_POST['rnb_unit_price']);
        }

        if (isset($_POST['redq_pickup_location_heading_title'])) {
            update_post_meta($post_id, 'redq_pickup_location_heading_title', $_POST['redq_pickup_location_heading_title']);
        }
        if (isset($_POST['redq_pickup_loc_placeholder'])) {
            update_post_meta($post_id, 'redq_pickup_loc_placeholder', $_POST['redq_pickup_loc_placeholder']);
        }
        if (isset($_POST['redq_dropoff_location_heading_title'])) {
            update_post_meta($post_id, 'redq_dropoff_location_heading_title', $_POST['redq_dropoff_location_heading_title']);
        }
        if (isset($_POST['redq_return_loc_placeholder'])) {
            update_post_meta($post_id, 'redq_return_loc_placeholder', $_POST['redq_return_loc_placeholder']);
        }

        if (isset($_POST['redq_pickup_date_heading_title'])) {
            update_post_meta($post_id, 'redq_pickup_date_heading_title', $_POST['redq_pickup_date_heading_title']);
        }

        if (isset($_POST['redq_pickup_date_placeholder'])) {
            update_post_meta($post_id, 'redq_pickup_date_placeholder', $_POST['redq_pickup_date_placeholder']);
        }

        if (isset($_POST['redq_pickup_time_placeholder'])) {
            update_post_meta($post_id, 'redq_pickup_time_placeholder', $_POST['redq_pickup_time_placeholder']);
        }


        if (isset($_POST['redq_dropoff_date_heading_title'])) {
            update_post_meta($post_id, 'redq_dropoff_date_heading_title', $_POST['redq_dropoff_date_heading_title']);
        }


        if (isset($_POST['redq_dropoff_date_placeholder'])) {
            update_post_meta($post_id, 'redq_dropoff_date_placeholder', $_POST['redq_dropoff_date_placeholder']);
        }

        if (isset($_POST['redq_dropoff_time_placeholder'])) {
            update_post_meta($post_id, 'redq_dropoff_time_placeholder', $_POST['redq_dropoff_time_placeholder']);
        }

        if (isset($_POST['rnb_quantity_label'])) {
            update_post_meta($post_id, 'rnb_quantity_label', $_POST['rnb_quantity_label']);
        }

        if (isset($_POST['redq_rnb_cat_heading'])) {
            update_post_meta($post_id, 'redq_rnb_cat_heading', $_POST['redq_rnb_cat_heading']);
        }

        if (isset($_POST['redq_resources_heading_title'])) {
            update_post_meta($post_id, 'redq_resources_heading_title', $_POST['redq_resources_heading_title']);
        }
        if (isset($_POST['redq_adults_heading_title'])) {
            update_post_meta($post_id, 'redq_adults_heading_title', $_POST['redq_adults_heading_title']);
        }

        if (isset($_POST['redq_adults_placeholder'])) {
            update_post_meta($post_id, 'redq_adults_placeholder', $_POST['redq_adults_placeholder']);
        }

        if (isset($_POST['redq_childs_heading_title'])) {
            update_post_meta($post_id, 'redq_childs_heading_title', $_POST['redq_childs_heading_title']);
        }

        if (isset($_POST['redq_childs_placeholder'])) {
            update_post_meta($post_id, 'redq_childs_placeholder', $_POST['redq_childs_placeholder']);
        }

        if (isset($_POST['redq_discount_text_title'])) {
            update_post_meta($post_id, 'redq_discount_text_title', $_POST['redq_discount_text_title']);
        }

        if (isset($_POST['redq_instance_pay_text_title'])) {
            update_post_meta($post_id, 'redq_instance_pay_text_title', $_POST['redq_instance_pay_text_title']);
        }

        if (isset($_POST['redq_total_cost_text_title'])) {
            update_post_meta($post_id, 'redq_total_cost_text_title', $_POST['redq_total_cost_text_title']);
        }

        if (isset($_POST['rnb_invalid_date_range_notice'])) {
            update_post_meta($post_id, 'rnb_invalid_date_range_notice', $_POST['rnb_invalid_date_range_notice']);
        }
        if (isset($_POST['rnb_max_day_notice'])) {
            update_post_meta($post_id, 'rnb_max_day_notice', $_POST['rnb_max_day_notice']);
        }
        if (isset($_POST['rnb_min_day_notice'])) {
            update_post_meta($post_id, 'rnb_min_day_notice', $_POST['rnb_min_day_notice']);
        }
        if (isset($_POST['rnb_max_hour_notice'])) {
            update_post_meta($post_id, 'rnb_max_hour_notice', $_POST['rnb_max_hour_notice']);
        }
        if (isset($_POST['rnb_min_hour_notice'])) {
            update_post_meta($post_id, 'rnb_min_hour_notice', $_POST['rnb_min_hour_notice']);
        }
        if (isset($_POST['rnb_quantity_notice'])) {
            update_post_meta($post_id, 'rnb_quantity_notice', $_POST['rnb_quantity_notice']);
        }

        if (isset($_POST['redq_book_now_button_text'])) {
            update_post_meta($post_id, 'redq_book_now_button_text', $_POST['redq_book_now_button_text']);
        }

        if (isset($_POST['redq_rfq_button_text'])) {
            update_post_meta($post_id, 'redq_rfq_button_text', $_POST['redq_rfq_button_text']);
        }

        if (isset($_POST['redq_username_title'])) {
            update_post_meta($post_id, 'redq_username_title', $_POST['redq_username_title']);
        }

        if (isset($_POST['redq_username_placeholder'])) {
            update_post_meta($post_id, 'redq_username_placeholder', $_POST['redq_username_placeholder']);
        }

        if (isset($_POST['redq_password_title'])) {
            update_post_meta($post_id, 'redq_password_title', $_POST['redq_password_title']);
        }

        if (isset($_POST['redq_password_placeholder'])) {
            update_post_meta($post_id, 'redq_password_placeholder', $_POST['redq_password_placeholder']);
        }

        if (isset($_POST['redq_first_name_title'])) {
            update_post_meta($post_id, 'redq_first_name_title', $_POST['redq_first_name_title']);
        }

        if (isset($_POST['redq_first_name_placeholder'])) {
            update_post_meta($post_id, 'redq_first_name_placeholder', $_POST['redq_first_name_placeholder']);
        }

        if (isset($_POST['redq_last_name_title'])) {
            update_post_meta($post_id, 'redq_last_name_title', $_POST['redq_last_name_title']);
        }

        if (isset($_POST['redq_last_name_placeholder'])) {
            update_post_meta($post_id, 'redq_last_name_placeholder', $_POST['redq_last_name_placeholder']);
        }

        if (isset($_POST['redq_phone_title'])) {
            update_post_meta($post_id, 'redq_phone_title', $_POST['redq_phone_title']);
        }

        if (isset($_POST['redq_phone_placeholder'])) {
            update_post_meta($post_id, 'redq_phone_placeholder', $_POST['redq_phone_placeholder']);
        }

        if (isset($_POST['redq_email_title'])) {
            update_post_meta($post_id, 'redq_email_title', $_POST['redq_email_title']);
        }

        if (isset($_POST['redq_email_placeholder'])) {
            update_post_meta($post_id, 'redq_email_placeholder', $_POST['redq_email_placeholder']);
        }

        if (isset($_POST['redq_message_title'])) {
            update_post_meta($post_id, 'redq_message_title', $_POST['redq_message_title']);
        }

        if (isset($_POST['redq_message_placeholder'])) {
            update_post_meta($post_id, 'redq_message_placeholder', $_POST['redq_message_placeholder']);
        }

        if (isset($_POST['redq_submit_button_text'])) {
            update_post_meta($post_id, 'redq_submit_button_text', $_POST['redq_submit_button_text']);
        }

        if (isset($_POST['redq_security_deposite_heading_title'])) {
            update_post_meta($post_id, 'redq_security_deposite_heading_title', $_POST['redq_security_deposite_heading_title']);
        }

        if (isset($_POST['redq_rental_local_show_pickup_date']) && !empty($_POST['redq_rental_local_show_pickup_date'])) {
            update_post_meta($post_id, 'redq_rental_local_show_pickup_date', $_POST['redq_rental_local_show_pickup_date']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_pickup_date', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_pickup_time']) && !empty($_POST['redq_rental_local_show_pickup_time'])) {
            update_post_meta($post_id, 'redq_rental_local_show_pickup_time', $_POST['redq_rental_local_show_pickup_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_pickup_time', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_dropoff_date']) && !empty($_POST['redq_rental_local_show_dropoff_date'])) {
            update_post_meta($post_id, 'redq_rental_local_show_dropoff_date', $_POST['redq_rental_local_show_dropoff_date']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_dropoff_date', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_dropoff_time']) && !empty($_POST['redq_rental_local_show_dropoff_time'])) {
            update_post_meta($post_id, 'redq_rental_local_show_dropoff_time', $_POST['redq_rental_local_show_dropoff_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_dropoff_time', 'closed');
        }

        if (isset($_POST['rnb_enable_quantity']) && !empty($_POST['rnb_enable_quantity'])) {
            update_post_meta($post_id, 'rnb_enable_quantity', $_POST['rnb_enable_quantity']);
        } else {
            update_post_meta($post_id, 'rnb_enable_quantity', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_pricing_flip_box']) && !empty($_POST['redq_rental_local_show_pricing_flip_box'])) {
            update_post_meta($post_id, 'redq_rental_local_show_pricing_flip_box', $_POST['redq_rental_local_show_pricing_flip_box']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_pricing_flip_box', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_price_discount_on_days']) && !empty($_POST['redq_rental_local_show_price_discount_on_days'])) {
            update_post_meta($post_id, 'redq_rental_local_show_price_discount_on_days', $_POST['redq_rental_local_show_price_discount_on_days']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_price_discount_on_days', 'closed');
        }


        if (isset($_POST['redq_rental_local_show_price_instance_payment']) && !empty($_POST['redq_rental_local_show_price_instance_payment'])) {
            update_post_meta($post_id, 'redq_rental_local_show_price_instance_payment', $_POST['redq_rental_local_show_price_instance_payment']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_price_instance_payment', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_request_quote']) && !empty($_POST['redq_rental_local_show_request_quote'])) {
            update_post_meta($post_id, 'redq_rental_local_show_request_quote', $_POST['redq_rental_local_show_request_quote']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_request_quote', 'closed');
        }

        if (isset($_POST['redq_rental_local_show_book_now']) && !empty($_POST['redq_rental_local_show_book_now'])) {
            update_post_meta($post_id, 'redq_rental_local_show_book_now', $_POST['redq_rental_local_show_book_now']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_show_book_now', 'closed');
        }


        // Required section
        if (isset($_POST['redq_rental_local_required_pickup_location']) && !empty($_POST['redq_rental_local_required_pickup_location'])) {
            update_post_meta($post_id, 'redq_rental_local_required_pickup_location', $_POST['redq_rental_local_required_pickup_location']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_required_pickup_location', 'closed');
        }

        if (isset($_POST['redq_rental_local_required_return_location']) && !empty($_POST['redq_rental_local_required_return_location'])) {
            update_post_meta($post_id, 'redq_rental_local_required_return_location', $_POST['redq_rental_local_required_return_location']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_required_return_location', 'closed');
        }

        if (isset($_POST['redq_rental_local_required_person']) && !empty($_POST['redq_rental_local_required_person'])) {
            update_post_meta($post_id, 'redq_rental_local_required_person', $_POST['redq_rental_local_required_person']);
        } else {
            update_post_meta($post_id, 'redq_rental_local_required_person', 'closed');
        }

        if (isset($_POST['redq_rental_required_local_pickup_time']) && !empty($_POST['redq_rental_required_local_pickup_time'])) {
            update_post_meta($post_id, 'redq_rental_required_local_pickup_time', $_POST['redq_rental_required_local_pickup_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_required_local_pickup_time', 'closed');
        }


        if (isset($_POST['redq_rental_required_local_return_time']) && !empty($_POST['redq_rental_required_local_return_time'])) {
            update_post_meta($post_id, 'redq_rental_required_local_return_time', $_POST['redq_rental_required_local_return_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_required_local_return_time', 'closed');
        }

        // Daily basis openning and closing time

        if (isset($_POST['redq_rental_fri_min_time']) && !empty($_POST['redq_rental_fri_min_time'])) {
            update_post_meta($post_id, 'redq_rental_fri_min_time', $_POST['redq_rental_fri_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_fri_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_fri_max_time']) && !empty($_POST['redq_rental_fri_max_time'])) {
            update_post_meta($post_id, 'redq_rental_fri_max_time', $_POST['redq_rental_fri_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_fri_max_time', '24:00');
        }

        if (isset($_POST['redq_rental_sat_min_time']) && !empty($_POST['redq_rental_sat_min_time'])) {
            update_post_meta($post_id, 'redq_rental_sat_min_time', $_POST['redq_rental_sat_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_sat_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_sat_max_time']) && !empty($_POST['redq_rental_sat_max_time'])) {
            update_post_meta($post_id, 'redq_rental_sat_max_time', $_POST['redq_rental_sat_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_sat_max_time', '24:00');
        }

        if (isset($_POST['redq_rental_sun_min_time']) && !empty($_POST['redq_rental_sun_min_time'])) {
            update_post_meta($post_id, 'redq_rental_sun_min_time', $_POST['redq_rental_sun_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_sun_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_sun_max_time']) && !empty($_POST['redq_rental_sun_max_time'])) {
            update_post_meta($post_id, 'redq_rental_sun_max_time', $_POST['redq_rental_sun_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_sun_max_time', '24:00');
        }

        if (isset($_POST['redq_rental_mon_min_time']) && !empty($_POST['redq_rental_mon_min_time'])) {
            update_post_meta($post_id, 'redq_rental_mon_min_time', $_POST['redq_rental_mon_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_mon_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_mon_max_time']) && !empty($_POST['redq_rental_mon_max_time'])) {
            update_post_meta($post_id, 'redq_rental_mon_max_time', $_POST['redq_rental_mon_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_mon_max_time', '24:00');
        }

        if (isset($_POST['redq_rental_thu_min_time']) && !empty($_POST['redq_rental_thu_min_time'])) {
            update_post_meta($post_id, 'redq_rental_thu_min_time', $_POST['redq_rental_thu_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_thu_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_thu_max_time']) && !empty($_POST['redq_rental_thu_max_time'])) {
            update_post_meta($post_id, 'redq_rental_thu_max_time', $_POST['redq_rental_thu_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_thu_max_time', '24:00');
        }

        if (isset($_POST['redq_rental_wed_min_time']) && !empty($_POST['redq_rental_wed_min_time'])) {
            update_post_meta($post_id, 'redq_rental_wed_min_time', $_POST['redq_rental_wed_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_wed_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_wed_max_time']) && !empty($_POST['redq_rental_wed_max_time'])) {
            update_post_meta($post_id, 'redq_rental_wed_max_time', $_POST['redq_rental_wed_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_wed_max_time', '24:00');
        }

        if (isset($_POST['redq_rental_thur_min_time']) && !empty($_POST['redq_rental_thur_min_time'])) {
            update_post_meta($post_id, 'redq_rental_thur_min_time', $_POST['redq_rental_thur_min_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_thur_min_time', '00:00');
        }

        if (isset($_POST['redq_rental_thur_max_time']) && !empty($_POST['redq_rental_thur_max_time'])) {
            update_post_meta($post_id, 'redq_rental_thur_max_time', $_POST['redq_rental_thur_max_time']);
        } else {
            update_post_meta($post_id, 'redq_rental_thur_max_time', '24:00');
        }
    }
}

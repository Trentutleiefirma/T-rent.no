<?php

namespace REDQ_RnB\Integration;

use REDQ_RnB\Traits\Legacy_Trait;
use REDQ_RnB\Traits\Assets_Trait;
use REDQ_RnB\Traits\Rental_Data_Trait;

use WP_Query;
use Carbon\Carbon;
use WC_Order_Query;
use WC_Order;

/**
 * Class Full Calendar
 */
class FullCalendarIntegration
{
    use Legacy_Trait, Assets_Trait, Rental_Data_Trait;

    /**
     * Init class
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets($hook)
    {
        $screen = get_current_screen();
        $screen_id = $screen ? $screen->id : '';

        if ($screen_id !== 'rnb_page_calendar') {
            return;
        }

        $scripts = $this->get_full_calendar_scripts();
        $styles  = $this->get_full_calendar_styles();

        foreach ($scripts as $handle => $script) {
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

        $this->rnb_prepare_calendar_data($hook);
    }

    /**
     * Show all booking data on full calendar.
     *
     * @since 2.4.0
     *
     * @param mixed $hook
     */
    public function rnb_prepare_calendar_data($hook)
    {
        global $wpdb;
        $calendar_data = [];
        $data_type = get_option('rnb_calendar_data_type', 'pre_fetched');

        $script = 'admin-page.js';
        if ($data_type === 'rest_api') {
            $script = 'admin-page-rest.js';
        }
        $calendarItems = $data_type !== 'rest_api' ? $this->prepare_calendar_items() : [];
        
        foreach ($calendarItems as $key => $item) {

            if (array_key_exists('start', $item) && array_key_exists('end', $item)) {
                $calendar_data[$key] = $item;
            }

            if (array_key_exists('start', $item) && !array_key_exists('end', $item)) {
                $start_info = isset($item['start_time']) && !empty($item['start_time']) ? $item['start'] . 'T' . $item['start_time'] : $item['start'];
                $return_info = isset($item['return_time']) && !empty($item['return_time']) ? $item['start'] . 'T' . $item['return_time'] : $item['start'];

                $item['start'] = rnb_format_date_time($start_info);
                $item['end'] = rnb_format_date_time($return_info);

                $calendar_data[$key] = $item;
            }

            if (array_key_exists('end', $item) && !array_key_exists('start', $item)) {
                $start_info = isset($item['start_time']) && !empty($item['start_time']) ? $item['end'] . 'T' . $item['start_time'] : $item['end'];
                $return_info = isset($item['return_time']) && !empty($item['return_time']) ? $item['end'] . 'T' . $item['return_time'] : $item['end'];

                $item['start'] = rnb_format_date_time($start_info);
                $item['end'] = rnb_format_date_time($return_info);

                $calendar_data[$key] = $item;
            }

            if (array_key_exists('start', $item) && array_key_exists('end', $item)) {
                $start_info = isset($item['start_time']) && !empty($item['start_time']) ? $item['start'] . 'T' . $item['start_time'] : $item['start'];
                $return_info = isset($item['return_time']) && !empty($item['return_time']) ? $item['end'] . 'T' . $item['return_time'] : $item['end'];

                $item['start'] = rnb_format_date_time($start_info);
                $item['end'] = rnb_format_date_time($return_info);

                $calendar_data[$key] = $item;
            }
        }

        wp_register_script('redq-admin-page', RNB_ROOT_URL . '/assets/js/' . $script . '', ['jquery'], $ver = false, true);
        wp_enqueue_script('redq-admin-page');

        $calender_button_label = [
            'today' => esc_html__('Today', 'redq-rental'),
            'month' => esc_html__('Month', 'redq-rental'),
            'week'  => esc_html__('Week', 'redq-rental'),
            'day'   => esc_html__('Day', 'redq-rental'),
            'list'  => esc_html__('list', 'redq-rental'),
        ];

        $loc_data = [
            'calendar_data'     => $calendar_data,
            'lang_domain'       => get_option('rnb_lang_domain', 'en'),
            'day_of_week_start' => (int) get_option('rnb_day_of_week_start', 1) - 1,
            'label'             => $calender_button_label,
        ];

        wp_localize_script('redq-admin-page', 'RNB_CALENDAR', $loc_data);
    }

    /**
     * Prepare calendar items
     *
     * @return array
     */
    public function prepare_calendar_items($args = [])
    {
        $results = [];

        $defaults = [
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'ids',
        ];
        $args = wp_parse_args($args, $defaults);
        $query = new WC_Order_Query($args);

        $orders = $query->get_orders();
        if (empty($orders)) {
            return $results;
        }

        foreach ($orders as $order_id) {
            $has_item = rnb_has_order_items($order_id);
            if (empty($has_item)) {
                continue;
            }

            $meta_exist = rnb_check_order_item_meta_exists($order_id, 'rnb_hidden_order_meta');

            if ($meta_exist) {
                $data = $this->get_rental_data_by_order_id($order_id);

                $order_details = $data['order_details'];
                if (!isset($data['item_details'])) {
                    continue;
                }

                $items = $data['item_details'];

                foreach ($items as $item_id => $item) {
                    $item_data   = $item['item_data'];
                    $rental_data = $item['rental_data'];
                    $item_id     = $item_data['id'];
                    $product_id  = $item_data['product_id'];
                    $quantity   = $item_data['quantity'];

                    $results[$item_id] = [
                        'post_status' => 'wc-' . $order_details['status'],
                        'title'       => html_entity_decode(get_the_title($product_id)) . ' ×' . $quantity,
                        'link'        => get_the_permalink($product_id),
                        'id'          => $order_id,
                        'color'       => rnb_get_status_to_color_map($order_details['status']),
                        'start'       => $rental_data['pickup_date'],
                        'start_time'  => $rental_data['pickup_time'],
                        'end'         => $rental_data['dropoff_date'],
                        'return_date' => $rental_data['dropoff_date'],
                        'return_time' => $rental_data['dropoff_time'],
                        'url'         => admin_url('post.php?post='.absint($order_id).'&action=edit'),
                        'description' => $this->prepare_popup_content($order_id, $order_details, $item)
                    ];
                }
            } else {
                //Start legacy support. this can be deleted later
                $order    = new WC_Order($order_id);
                if ($order->get_status() === 'wc-rnb-fake-order') {
                    continue;
                }
                foreach ($order->get_items() as $item_id => $item) {
                    $product_id = $item->get_data()['product_id'];
                    $product = wc_get_product($product_id);

                    if (empty($product) || $product->get_type() !== 'redq_rental') {
                        continue;
                    }

                    $results[$item_id] = rnb_prepare_calendar_item_data($item, $order_id, $order);
                }
                //End legacy support. this can be deleted later
            }
        }

        return $results;
    }

    public function prepare_popup_content($order_id, $order_details, $item)
    {
        $item_data = $item['item_data'];
        $rental_meta = $item['rental_meta'];

        $rental_meta = apply_filters('rnb_full_calendar_cart_item_data', $rental_meta);
        $quantity   = $item_data['quantity'];

        $description = '<table cellspacing="0" class="redq-rental-display-meta"><tbody><tr><th>' . __('Order ID:', 'redq-rental') . '</th><td># <a href="' . admin_url('post.php?post=' . absint($order_id) . '&action=edit') . '"> ' . $order_id . ' </a> </td></tr>';
        $description .= '<table cellspacing="0" class="redq-rental-display-meta"><tbody><tr><th>' . __('Quantity:', 'redq-rental') . '</th><td>' . $quantity . '</td></tr>';

        foreach ($rental_meta as $key => $data) {
            if (empty($data)) {
                continue;
            }

            $value = $data['type'] === 'single' ? $this->format_single_item_value($data['data']) :  $this->format_multiple_item_value($data['data']);
            if (empty($value)) {
                continue;
            }

            $description .= '<tr><th>' . $data['key'] . '</th><td>' . $value . '</td></tr>';
        }

        $description .= '<tr><th>' . esc_html__('Total Amount: ', 'redq-rental') . '</th><td>' . wc_price($order_details['total']) . '</td>';
        $description .= '</tbody></table>';

        return $description;
    }
}

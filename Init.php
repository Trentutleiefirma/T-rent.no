<?php

namespace REDQ_RnB;

use REDQ_RnB\Traits\Assets_Trait;
use Carbon\Carbon;

class Init
{
    use Assets_Trait;

    /**
     * Init class
     */
    public function __construct()
    {
        if (current_user_can('manage_options')) {
            add_action('rest_api_init', [$this, 'rnb_calendar_event_api']);
        }
        add_action('woocommerce_redq_rental_add_to_cart', [$this, 'rnb_template']);
        add_filter('woocommerce_integrations', [$this, 'rnb_integrations']);
        add_filter('woocommerce_get_settings_pages', [$this, 'rnb_get_settings_pages']);
        add_action('wp_head', [$this, 'rnb_prevent_ios_input_focus_zooming']);
    }

    /**
     * Rest API endpoint for rnb calendar
     *
     * @return void
     */
    public function rnb_calendar_event_api()
    {
        register_rest_route('rnb/v1', '/events', array(
            'methods' => 'POST',
            'callback' => [$this, 'rnb_calendar_api_callback'],
            'permission_callback' => function () {
                return true;
            }
        ));
    }

    /**
     * RnB Calendar api callback
     *
     * @param object $request
     * @return object
     */
    public function rnb_calendar_api_callback($request)
    {
        $start = $request->get_param('start');
        $end = $request->get_param('end');

        $format = 'Y-m-d';
        $start_date = Carbon::parse($start)->format($format);
        $end_date = Carbon::parse($end)->format($format);

        $posts_ids = rnb_get_rental_orders_by_dates($start_date, $end_date);
        if (empty($posts_ids)) {
            $response = new \WP_REST_Response([]);
            $response->set_status(200);
            return $response;
        }

        $args = [
            'post_type' => 'shop_order',
            'post__in'  => $posts_ids,
            'orderby'   => 'post__in',
        ];

        $calendar = new \REDQ_RnB\Integration\FullCalendarIntegration();

        $events = $calendar->prepare_calendar_items($args);
        if (count($events)) {
            $events = array_values($events);
        }

        $response = new \WP_REST_Response($events);
        $response->set_status(200);
        return $response;
    }

    /**
     * Book now form for rental product
     *
     * @return null
     * @since 1.0.0
     */
    public function rnb_template()
    {
        $template = 'single-product/add-to-cart/redq_rental.php';
        wc_get_template($template, $args = [], $template_path = '', RNB_PACKAGE_TEMPLATE_PATH);
    }

    /**
     * Google calendar settings page
     *
     * @param array $integrations
     * @return array
     */
    public function rnb_integrations($integrations)
    {
        $integrations[] = 'REDQ_RnB\Integration\GoogleCalendarIntegration';
        return $integrations;
    }

    /**
     * Get global setting page
     *
     * @param array $settings
     * @return array
     */
    public function rnb_get_settings_pages($settings)
    {
        $settings[] = new \REDQ_RnB\Admin\GlobalSettings();
        return $settings;
    }

    /**
     * Prevent ios input focus auto zooming
     *
     * @return null
     * @since 12.0.0
     */
    public function rnb_prevent_ios_input_focus_zooming()
    {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1 maximum-scale=1">';
    }
}

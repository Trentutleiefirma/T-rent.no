<?php

namespace REDQ_RnB\Integration;

use Carbon\Carbon;
use WC_Integration, WC_Logger, WC_Order, DateTime;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Google Calendar Integration.
 *
 * @version 4.0.1
 * @since 4.0.1
 * @return void
 */
class GoogleCalendarIntegration extends WC_Integration
{
    public $notice_title;
    public $notice_desc;
    public $docs_uri;
    protected $oauth_uri;
    protected $calendars_uri;
    protected $api_scope;
    protected $redirect_uri;
    public $client_id;
    protected $client_secret;
    protected $calendar_id;
    protected $debug;
    protected $log;

    /**
     * Google Calendar Initialization and required hooks
     */
    public function __construct()
    {
        $this->id                 = 'google_calendar';
        $this->plugin_id          = 'redq_rental_';
        $this->notice_title       = __('Connect RnB With Google Calendar To Trace Orders', 'redq-rental');
        $this->notice_desc = __('You need to do following things to get your required calendar credentials ', 'redq-rental');

        // Required End points
        $protocol = is_ssl() ? 'https' : null;
        $this->docs_uri = 'https://redq.gitbooks.io/woocommerce-rental-and-booking/content/google-calendar-integration.html';
        $this->oauth_uri     = 'https://accounts.google.com/o/oauth2/';
        $this->calendars_uri = 'https://www.googleapis.com/calendar/v3/calendars/';
        $this->api_scope     = 'https://www.googleapis.com/auth/calendar';
        $this->redirect_uri  = WC()->api_request_url('redq_rental_google_calendar', $protocol);

        // Required Credentials
        $this->client_id     = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->calendar_id   = $this->get_option('calendar_id');
        $this->debug         = 'yes';

        // Admin Settings and forms
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_redq_rental_google_calendar', array($this, 'rnb_oauth_redirect'));
        add_action('woocommerce_order_status_changed', array($this, 'rnb_google_cal_order_sync'), 10, 3);
        add_action('rnb_order_status_changed', array($this, 'rnb_google_cal_order_sync'), 10, 3);
        add_action('trashed_post', array($this, 'rnb_sync_trashed_booking'));
        add_action('untrashed_post', array($this, 'rnb_sync_non_trashed_booking'));

        if (is_admin()) {
            add_action('admin_notices', array($this, 'rnb_admin_notices'));
        }

        if ('yes' === $this->debug) {
            if (class_exists('WC_Logger')) {
                $this->log = new WC_Logger();
            } else {
                $this->log = WC()->logger();
            }
        }
    }

    /**
     * Initialize integration settings form fields.
     *
     * @version 4.0.1
     * @since 4.0.1
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'client_id' => array(
                'title'       => __('Google Client ID', 'redq-rental'),
                'type'        => 'text',
                'description' => __('Enter your Google Client ID.', 'redq-rental'),
                'default'     => '',
            ),
            'client_secret' => array(
                'title'       => __('Google Client Secret', 'redq-rental'),
                'type'        => 'text',
                'description' => __('Enter your Google Client Secret.', 'redq-rental'),
                'default'     => '',
            ),
            'calendar_id' => array(
                'title'       => __('Google Calendar ID', 'redq-rental'),
                'type'        => 'text',
                'description' => __('Enter your Calendar ID.', 'redq-rental'),
                'default'     => '',
            ),
            'authorization' => array(
                'title'       => __('Authentication', 'redq-rental'),
                'type'        => 'google_calendar_authorization',
            ),
        );
    }

    /**
     * Validate the Google Calendar Authorization field.
     *
     * @param  mixed $key
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return string
     */
    public function validate_google_calendar_authorization_field($key)
    {
        return '';
    }

    /**
     * Generate the google Calendar Authorization field.
     *
     * @param  mixed $key
     * @param  array $data
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return string
     */
    public function generate_google_calendar_authorization_html($key, $data)
    {
        $options       = $this->plugin_id . $this->id . '_';
        $id            = $options . $key;
        $client_id     = isset($_POST[$options . 'client_id']) ? sanitize_text_field($_POST[$options . 'client_id']) : $this->client_id;
        $client_secret = isset($_POST[$options . 'client_secret']) ? sanitize_text_field($_POST[$options . 'client_secret']) : $this->client_secret;
        $calendar_id   = isset($_POST[$options . 'calendar_id']) ? sanitize_text_field($_POST[$options . 'calendar_id']) : $this->calendar_id;
        $access_token  = $this->get_calendar_access_token();

        ob_start();

?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php echo wp_kses_post($data['title']); ?>
            </th>
            <td class="forminp">
                <?php
                if (!$access_token && ($client_id && $client_secret && $calendar_id)) :
                    $oauth_url = add_query_arg(
                        array(
                            'scope'           => $this->api_scope,
                            'redirect_uri'    => $this->redirect_uri,
                            'response_type'   => 'code',
                            'client_id'       => $client_id,
                            'approval_prompt' => 'force',
                            'access_type'     => 'offline',
                        ),
                        $this->oauth_uri . 'auth'
                    );
                ?>
                    <p class="submit"><a class="button button-primary" href="<?php echo esc_url($oauth_url); ?>"><?php _e('Connect with Google', 'redq-rental'); ?></a></p>
                <?php elseif ($access_token) : ?>
                    <p><?php _e('Successfully authenticated. Now Enjoy :) ', 'redq-rental'); ?></p>
                    <p class="submit"><a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('logout' => 'true'), $this->redirect_uri)); ?>"><?php _e('Disconnect', 'redq-rental'); ?></a></p>
                <?php else : ?>
                    <p><?php _e('Unable to authenticate, you must enter with your <strong>Client ID</strong>, <strong>Client Secret</strong> and <strong>Calendar ID</strong>.', 'redq-rental'); ?></p>
                <?php endif; ?>
            </td>
        </tr>
<?php
        return ob_get_clean();
    }

    /**
     * Admin Options.
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return string
     */
    public function admin_options()
    {
        echo '<h3>' . $this->notice_title . '</h3>';
        echo wpautop($this->notice_desc);

        echo '<p>' . sprintf(__('First you need to create a project in %1$s. After creating project, you must enable the <strong>Google Calendar API</strong> in <strong>Your Project > Library</strong>, Then go to <strong>Your Project > Credentials > Create Credentials</strong> & create an OAuth Client ID for a <strong>Web application</strong> and set the <strong>Authorized redirect URIs</strong> as <code>%2$s</code>.', 'redq-rental'), '<a href="https://console.developers.google.com/project" target="_blank">' . __('Google Developers Console', 'redq-rental') . '</a>', $this->redirect_uri) . '</p>';

        echo '<strong>' . sprintf(__('Please visit our online docs ' . '<a href="%1$s" target="_blank">' . __('Online Docs', 'redq-rental') . '</a>' . ' to get more idea', 'redq-rental'), $this->docs_uri) . '</strong>';

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';

        echo '<div><input type="hidden" name="section" value="' . $this->id . '" /></div>';
    }

    /**
     * Get Access Token For Google Calendar.
     *
     * @param  string $code Authorization code.
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return string Access token.
     */
    protected function get_calendar_access_token($code = '')
    {

        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Getting Google API Access Token...');
        }

        $access_token = get_transient('redq_rental_gcalendar_access_token');

        if (!$code && false !== $access_token) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'Access Token recovered by transients: ' . print_r($access_token, true));
            }
            return $access_token;
        }

        $refresh_token = get_option('redq_rental_gcalendar_refresh_token');

        if (!$code && $refresh_token) {

            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'Generating a new Access Token...');
            }

            $data = array(
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            );

            $params = array(
                'body'      => http_build_query($data),
                'sslverify' => false,
                'timeout'   => 60,
                'headers'   => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
            );

            $response = wp_remote_post($this->oauth_uri . 'token', $params);

            if (!is_wp_error($response) && 200 == $response['response']['code'] && 'OK' == $response['response']['message']) {
                $response_data = json_decode($response['body']);
                $access_token  = sanitize_text_field($response_data->access_token);

                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Google API Access Token generated successfully: ' . print_r($access_token, true));
                }

                // Set the transient.
                set_transient('redq_rental_gcalendar_access_token', $access_token, 3500);

                return $access_token;
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Error while generating the Access Token: ' . print_r($response, true));
                }
            }
        } elseif ('' != $code) {

            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'Renewing the Access Token...');
            }

            $data = array(
                'code'          => $code,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri'  => $this->redirect_uri,
                'grant_type'    => 'authorization_code',
            );

            $params = array(
                'body'      => http_build_query($data),
                'sslverify' => false,
                'timeout'   => 60,
                'headers'   => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
            );

            $response = wp_remote_post($this->oauth_uri . 'token', $params);

            if (!is_wp_error($response) && 200 == $response['response']['code'] && 'OK' == $response['response']['message']) {
                $response_data = json_decode($response['body']);
                $access_token  = sanitize_text_field($response_data->access_token);

                // Add refresh token.
                update_option('redq_rental_gcalendar_refresh_token', $response_data->refresh_token);

                // Set the transient.
                set_transient('redq_rental_gcalendar_access_token', $access_token, 3500);

                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Google API Access Token renewed successfully: ' . print_r($access_token, true));
                }
                return $access_token;
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Error while renewing the Access Token: ' . print_r($response, true));
                }
            }
        }

        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Failed to retrieve and generate the Access Token');
        }

        return '';
    }

    /**
     * OAuth Logout.
     *
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return bool
     */
    protected function oauth_logout()
    {
        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Leaving the Google Calendar app...');
        }

        $refresh_token = get_option('redq_rental_gcalendar_refresh_token');

        if ($refresh_token) {
            $params = array(
                'sslverify' => false,
                'timeout'   => 60,
                'headers'   => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
            );

            $response = wp_remote_get($this->oauth_uri . 'revoke?token=' . $refresh_token, $params);

            if (!is_wp_error($response) && 200 == $response['response']['code'] && 'OK' == $response['response']['message']) {
                delete_option('redq_rental_gcalendar_refresh_token');
                delete_transient('redq_rental_gcalendar_access_token');

                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Leave the Google Calendar app successfully');
                }

                return true;
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Error when leaving the Google Calendar app: ' . print_r($response, true));
                }
            }
        }

        if ('yes' === $this->debug) {
            $this->log->add($this->id, 'Failed to leave the Google Calendar app');
        }

        return false;
    }

    /**
     * Process the oauth redirect.
     *
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return void
     */
    public function rnb_oauth_redirect()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied!', 'redq-rental'));
        }

        $redirect_args = array(
            'page'    => 'wc-settings',
            'tab'     => 'integration',
            'section' => $this->id,
        );

        // OAuth.
        if (isset($_GET['code'])) {
            $code         = sanitize_text_field($_GET['code']);
            $access_token = $this->get_calendar_access_token($code);

            if ('' != $access_token) {
                $redirect_args['wc_gcalendar_oauth'] = 'success';

                wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')), 301);
                exit;
            }
        }
        if (isset($_GET['error'])) {

            $redirect_args['wc_gcalendar_oauth'] = 'fail';

            wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')), 301);
            exit;
        }

        // Logout.
        if (isset($_GET['logout'])) {
            $logout = $this->oauth_logout();
            $redirect_args['wc_gcalendar_logout'] = ($logout) ? 'success' : 'fail';

            wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')), 301);
            exit;
        }

        wp_die(__('Invalid request!', 'redq-rental'));
    }

    /**
     * Display admin screen notices.
     *
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return string
     */
    public function rnb_admin_notices()
    {
        $screen = get_current_screen();

        if ('woocommerce_page_wc-settings' == $screen->id && isset($_GET['wc_gcalendar_oauth'])) {
            if ('success' == $_GET['wc_gcalendar_oauth']) {
                echo '<div class="updated fade"><p><strong>' . __('Google Calendar', 'redq-rental') . '</strong> ' . __('Successful Authenticate', 'redq-rental') . '</p></div>';
            } else {
                echo '<div class="error fade"><p><strong>' . __('Google Calendar', 'redq-rental') . '</strong> ' . __('Failed to Authenticate to your account, please try again', 'redq-rental') . '</p></div>';
            }
        }

        if ('woocommerce_page_wc-settings' == $screen->id && isset($_GET['wc_gcalendar_logout'])) {
            if ('success' == $_GET['wc_gcalendar_logout']) {
                echo '<div class="updated fade"><p><strong>' . __('Google Calendar', 'redq-rental') . '</strong> ' . __('Account disconnected successfully!', 'redq-rental') . '</p></div>';
            } else {
                echo '<div class="error fade"><p><strong>' . __('Google Calendar', 'redq-rental') . '</strong> ' . __('Failed to disconnect to your account, please try again', 'redq-rental') . '</p></div>';
            }
        }
    }


    /**
     * Updates date availability with status of order.
     *
     * @version 4.0.1
     * @since 4.0.1
     *
     * @param string $new_status Status to change the order to. No internal wc- prefix is required.
     * @param string $note (default: '') Optional note to add.
     * @param bool $manual is this a manual order status change?
     * @return bool Successful change or not
     */
    public function rnb_google_cal_order_sync($order_id, $old_status, $new_status)
    {
        $order = new WC_Order($order_id);

        if ($order->get_status() === 'rnb-fake-order') {
            return;
        }

        $items = $order->get_items();

        if (empty($items)) {
            return;
        }

        $billing_email = $order->get_billing_email();
        $email         = $billing_email ? $billing_email : '';

        foreach ($items as $item_id => $item) {

            $product_id = $item->get_data()['product_id'];
            $product = wc_get_product($product_id);
            if (empty($product) || $product->get_type() !== 'redq_rental') {
                continue;
            }

            $item_data = rnb_prepare_calendar_item_data($item, $order_id, $order);

            $start       = $item_data['start'];
            $end         = $item_data['end'];
            $start_time  = isset($item_data['start_time']) ? $item_data['start_time'] : '';
            $end_time    = isset($item_data['return_time']) ?  $item_data['return_time'] : '';
            $end_time = $end_time === '24:00' ? '23:59' : $end_time;

            $description = $item_data['description'];

            $gmt = get_option('gmt_offset');
            $g   = sprintf("%02d", intval(abs($gmt)));
            $gmt = $gmt > 0 ? '+' . $g . ':00' : '-' . $g . ':00';

            if (!empty($start_time) && !empty($end_time)) {

                $start_info = ['dateTime' => '' . $start . 'T' . date("H:i:s", strtotime($start_time)) . ($gmt)];
                $end_info   = ['dateTime' => '' . $end . 'T' . date("H:i:s", strtotime($end_time)) . ($gmt)];
            } else {
                $start_info = ['date' => $start];
                $end_info   = ['date' => $end];
            }

            if (empty($email)) {
                $email = get_option('admin_email');
            }

            $data = [
                'summary'     => get_the_title($product_id) . ' | ' . esc_html__('Order Status', 'redq-rental') . ': [ ' . $new_status . ' ] ',
                'description' => $description,
                'colorId'     => 4,
                'attendees'   => [['email' => $email]],
                // 'attachments' => ['fileUrl' => 'http://rnb.dev/wp-admin/admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=543&_wpnonce=8ba5ff2133']
            ];

            $data['start'] = $start_info;
            $data['end'] = $end_info;

            if ($new_status !== 'cancelled') :
                $this->rnb_sync_booking($order_id, $item_id, $data);
            else :
                $this->rnb_remove_booking($order_id, $item_id);
            endif;
        }
    }

    /**
     * Sync Booking with Google Calendar when booking status changed.
     *
     * @version 4.0.1
     * @since 4.0.1
     *
     * @param  int $item Item ID
     * @param  array $data Booking Data
     *
     * @return void
     */
    public function rnb_sync_booking($order_id, $item_id, $data)
    {

        $api_url      = $this->calendars_uri . $this->calendar_id . '/events';
        $access_token = $this->get_calendar_access_token();
        $event_id = wc_get_order_item_meta($item_id, 'redq_google_cal_sync_id', true);

        // Connection params.
        $params = array(
            'method'    => 'POST',
            'body'      => json_encode(apply_filters('woocommerce_bookings_gcalendar_sync', $data)),
            'sslverify' => false,
            'timeout'   => 60,
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ),
        );

        // Update event.
        if ($event_id) {
            $api_url .= '/' . $event_id;
            $params['method'] = 'PUT';
        }

        $response = wp_remote_post($api_url, $params);

        if (!is_wp_error($response) && 200 == $response['response']['code'] && 'OK' == $response['response']['message']) {
            if ('yes' === $this->debug) {
                $this->log->add($this->id, 'Booking synchronized successfully!');
            }
            // Updated the Google Calendar event ID
            $response_data = json_decode($response['body'], true);
            wc_add_order_item_meta($item_id, 'redq_google_cal_sync_id', $response_data['id']);
        } elseif ('yes' === $this->debug) {
            $this->log->add($this->id, 'Error while synchronizing the booking #' . $order_id . ': ' . print_r($response, true));
        }
    }


    /**
     * Removed booking from calendar
     *
     * @version 4.0.1
     * @since 4.0.1
     * @param  int $item Item ID
     * @param  array $data Booking Data
     *
     * @return void
     */
    public function rnb_remove_booking($order_id, $item_id)
    {

        $event_id = wc_get_order_item_meta($item_id, 'redq_google_cal_sync_id', true);

        if ($event_id) {
            $api_url      = $this->calendars_uri . $this->calendar_id . '/events/' . $event_id;
            $access_token = $this->get_calendar_access_token();
            $params       = array(
                'method'    => 'DELETE',
                'sslverify' => false,
                'timeout'   => 60,
                'headers'   => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            );

            $response = wp_remote_post($api_url, $params);

            if (!is_wp_error($response) && 204 == $response['response']['code']) {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Booking removed successfully!');
                }
            } else {
                if ('yes' === $this->debug) {
                    $this->log->add($this->id, 'Error while removing the booking #' . $order_id . ': ' . print_r($response, true));
                }
            }
        }
    }


    /**
     * Retrieve booking from when restore order and sync with GCal
     *
     * @param  int $order_id Order ID
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return void
     */
    public function rnb_sync_non_trashed_booking($order_id)
    {
        $post_type = get_post_type($order_id);
        if (isset($post_type) && $post_type === 'shop_order') :
            global $wpdb;
            $order = new WC_Order($order_id);
            $billing_email = $order->get_billing_email();
            $email = $billing_email ? $billing_email : '';
            $items = $order->get_items();

            if (isset($items) && !empty($items)) {
                $gcal = array();
                foreach ($items as $item_key => $item_value) {
                    $item_id = $item_key;
                    $description = '';
                    $order_item_id = $item_value->get_id();
                    $product_id = $item_value->get_product_id();
                    $product = wc_get_product($product_id);
                    $product_type = $product ? $product->get_type() : '';

                    if (isset($product_type) && $product_type === 'redq_rental') {
                        $order_item_details = $item_value->get_formatted_meta_data('');
                        $order_status = $wpdb->get_var($wpdb->prepare("SELECT post_status FROM $wpdb->posts WHERE post_type = 'shop_order' AND ID = %d", $order_id));

                        foreach ($order_item_details as $order_item_key => $order_item_value) {

                            if ($order_item_value->key !== '_pickup_hidden_datetime' && $order_item_value->key !== '_return_hidden_datetime' && $order_item_value->key !== '_return_hidden_days' && $order_item_value->key !== 'redq_google_cal_sync_id' && $order_item_value->key !== 'booking_inventory') {
                                $description .= '<tr><th>' . $order_item_value->key . '</th><td>' . $order_item_value->value . '</td></tr>';
                            }

                            if ($order_item_value->key === '_pickup_hidden_datetime') {
                                $pickup_datetime = explode('|', $order_item_value->value);
                                $gcal[$order_item_id]['start'] = $pickup_datetime[0];
                                $gcal[$order_item_id]['start_time'] = isset($pickup_datetime[1]) ? date("H:i", strtotime("$pickup_datetime[0] $pickup_datetime[1]")) : '';

                                $start = $pickup_datetime[0];
                                $start_time = isset($pickup_datetime[1]) && !empty($pickup_datetime[1]) ? date("H:i", strtotime("$pickup_datetime[0] $pickup_datetime[1]")) : '';
                            }

                            if ($order_item_value->key === '_return_hidden_datetime') {
                                $return_datetime = explode('|', $order_item_value->value);
                                $gcal[$order_item_id]['return_date'] = $return_datetime[0];
                                $gcal[$order_item_id]['return_time'] = isset($return_datetime[1]) ? date("H:i", strtotime("$return_datetime[0] $return_datetime[1]")) : '';
                            }

                            if ($order_item_value->key === '_return_hidden_days') {
                                $start_day = $gcal[$order_item_id]['start'];
                                $end_day = new DateTime($start_day . ' + ' . $order_item_value->value . ' day');
                                $gcal[$order_item_id]['end'] = $end_day->format('Y-m-d');

                                $end = $end_day->format('Y-m-d');
                                $end_time = isset($gcal[$order_item_id]['return_time']) && !empty($gcal[$order_item_id]['return_time']) ? $gcal[$order_item_id]['return_time'] : '';
                            }
                        }

                        $gmt = get_option('gmt_offset');
                        $g = sprintf("%02d", intval(abs($gmt)));
                        $gmt = $gmt > 0 ? '+' . $g . ':00' : '-' . $g . ':00';

                        if (!empty($start_time) && !empty($end_time)) {
                            $start_info = array(
                                'dateTime' => '' . $start . 'T' . $start_time . ':00' . ($gmt) . '',
                            );
                            $end_info = array(
                                'dateTime' => '' . $end . 'T' . $end_time . ':00' . ($gmt) . '',
                            );
                        } else {
                            $start_info = array(
                                'date' => $start,
                            );
                            $end_info = array(
                                'date' => $end,
                            );
                        }

                        $data = array(
                            'summary'     => get_the_title($product_id) . ' | ' . esc_html__('Order Status', 'redq-rental') . ' : [ ' . $order_status . ' ] ',
                            'description' => $description,
                            'colorId' => 4,
                            'attendees' => array(
                                array('email' => $email),
                            ),
                            // 'attachments' => array(
                            //     'fileUrl' => 'http://rnb.dev/wp-admin/admin-ajax.php?action=generate_wpo_wcpdf&template_type=invoice&order_ids=543&_wpnonce=8ba5ff2133',
                            // ),
                        );

                        $data['start'] = $start_info;
                        $data['end'] = $end_info;

                        $this->rnb_sync_booking($order_id, $item_id, $data);
                    }
                }
            }

        endif;
    }

    /**
     * Remove/cancel the booking in Google Calendar
     *
     * @param  int $order_id Order ID
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return void
     */
    public function rnb_sync_trashed_booking($order_id)
    {

        $post_type = get_post_type($order_id);

        if (isset($post_type) && $post_type === 'shop_order') :
            $order = new WC_Order($order_id);
            $items = $order->get_items();
            if (isset($items) && !empty($items)) {
                foreach ($items as $item_key => $item_value) {
                    $item_id = $item_key;
                    $item_data = $item_value->get_data();
                    $product_id = $item_data['product_id'];
                    $product = wc_get_product($product_id);
                    $product_type = $product ? $product->get_type() : '';
                    if (isset($product_type) && $product_type === 'redq_rental') {
                        $this->rnb_remove_booking($order_id, $item_id);
                    }
                }
            }
        endif;
    }


    /**
     * Check array key from multi-dimentional array
     *
     * @version 4.0.1
     * @since 4.0.1
     *
     * @return int
     */
    public function get_multidimentional_array_index($products, $field, $value)
    {
        foreach ($products as $key => $product) {
            if ($product->$field === $value)
                return $key;
        }
        return false;
    }
}

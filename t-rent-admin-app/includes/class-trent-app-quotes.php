<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Admin_App_Quotes
{
    const REST_NAMESPACE = 't-rent-app/v1';
    const NOTES_META = '_t_rent_app_quote_notes';

    public static function register()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/quotes', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'rest_quotes'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/quote/(?P<id>\\d+)/decision', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_decision'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/quote/(?P<id>\\d+)/comment', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'rest_comment'],
            'permission_callback' => ['TRent_Admin_App', 'rest_permission'],
        ]);
    }

    public static function rest_quotes()
    {
        return rest_ensure_response(['quotes' => self::get_quotes()]);
    }

    public static function rest_decision(WP_REST_Request $request)
    {
        $quote_id = absint($request['id']);
        $post = self::get_quote_post($quote_id);
        if (is_wp_error($post)) {
            return $post;
        }

        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];
        $action = isset($data['action']) ? sanitize_key($data['action']) : '';
        $comment = isset($data['comment']) ? sanitize_textarea_field($data['comment']) : '';

        $targets = [
            'approve' => 'quote-accepted',
            'reject' => 'quote-cancelled',
        ];

        if (!isset($targets[$action])) {
            return new WP_Error('trent_invalid_quote_action', 'Ugyldig handling for forespørselen.', ['status' => 400]);
        }

        if (!in_array($post->post_status, ['quote-pending', 'quote-processing', 'quote-on-hold'], true)) {
            return new WP_Error(
                'trent_quote_already_decided',
                'Denne forespørselen er allerede behandlet. Oppdater listen før du prøver igjen.',
                ['status' => 409]
            );
        }

        $target_status = $targets[$action];
        $updated = wp_update_post([
            'ID' => $quote_id,
            'post_status' => $target_status,
        ], true);

        if (is_wp_error($updated)) {
            return new WP_Error('trent_quote_update_failed', 'Kunne ikke oppdatere forespørselen.', ['status' => 500]);
        }

        update_post_meta($quote_id, 'rnb_quote_need_view', false);

        if ($comment !== '') {
            self::append_note($quote_id, $comment);
        }

        self::send_status_email($quote_id, $target_status);

        return rest_ensure_response([
            'message' => $action === 'approve' ? 'Forespørselen er godkjent.' : 'Forespørselen er avslått.',
            'quotes' => self::get_quotes(),
        ]);
    }

    public static function rest_comment(WP_REST_Request $request)
    {
        $quote_id = absint($request['id']);
        $post = self::get_quote_post($quote_id);
        if (is_wp_error($post)) {
            return $post;
        }

        $data = $request->get_json_params();
        $data = is_array($data) ? $data : [];
        $comment = isset($data['comment']) ? sanitize_textarea_field($data['comment']) : '';

        if ($comment === '') {
            return new WP_Error('trent_quote_comment_required', 'Skriv en kommentar først.', ['status' => 400]);
        }

        self::append_note($quote_id, $comment);

        return rest_ensure_response([
            'message' => 'Kommentaren er lagret.',
            'quotes' => self::get_quotes(),
        ]);
    }

    private static function get_quote_post($quote_id)
    {
        $post = get_post($quote_id);

        if (!$post || $post->post_type !== 'request_quote') {
            return new WP_Error('trent_quote_missing', 'Forespørselen ble ikke funnet.', ['status' => 404]);
        }

        return $post;
    }

    private static function status_labels()
    {
        return [
            'quote-pending' => 'Venter på svar',
            'quote-processing' => 'Behandles',
            'quote-on-hold' => 'På vent',
            'quote-accepted' => 'Godkjent',
            'quote-completed' => 'Fullført',
            'quote-cancelled' => 'Avslått',
        ];
    }

    private static function get_quotes()
    {
        if (!post_type_exists('request_quote')) {
            return [];
        }

        $ids = get_posts([
            'post_type' => 'request_quote',
            'post_status' => array_keys(self::status_labels()),
            'posts_per_page' => 150,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        $quotes = [];
        foreach ($ids as $quote_id) {
            $quotes[] = self::format_quote((int) $quote_id);
        }

        return $quotes;
    }

    private static function format_quote($quote_id)
    {
        $post = get_post($quote_id);
        $status = $post ? (string) $post->post_status : '';
        $labels = self::status_labels();
        $form = self::quote_form_data($quote_id);

        $product_id = absint(get_post_meta($quote_id, '_product_id', true));
        if (!$product_id) {
            $product_id = absint(get_post_meta($quote_id, 'add-to-cart', true));
        }
        if (!$product_id && isset($form['values']['add-to-cart'])) {
            $product_id = absint($form['values']['add-to-cart']);
        }

        $product_name = $product_id ? get_the_title($product_id) : '';
        if ($product_name === '') {
            $product_name = $product_id ? 'Produkt #' . $product_id : 'Ukjent produkt';
        }

        $pickup_date = self::first_value($quote_id, $form['values'], ['pickup_date']);
        $pickup_time = self::first_value($quote_id, $form['values'], ['pickup_time']);
        $return_date = self::first_value($quote_id, $form['values'], ['dropoff_date', 'return_date']);
        $return_time = self::first_value($quote_id, $form['values'], ['dropoff_time', 'return_time']);

        $customer = self::customer_details($quote_id, $post, $form['forms']);
        $quote_price = (string) get_post_meta($quote_id, '_quote_price', true);
        $created_ts = $post ? (int) get_post_time('U', true, $post) : 0;
        $pickup_ts = self::quote_datetime_timestamp($pickup_date, $pickup_time, '08:00');
        $return_ts = self::quote_datetime_timestamp($return_date, $return_time, '20:00');
        $order_id = absint(get_post_meta($quote_id, '_rnb_rfq_order_id', true));
        $relevant = self::is_relevant_quote($status, $order_id, $pickup_ts, $return_ts, $created_ts);

        $notes = get_post_meta($quote_id, self::NOTES_META, true);
        $notes = is_array($notes) ? array_values($notes) : [];

        return [
            'id' => $quote_id,
            'product_id' => $product_id,
            'product_name' => $product_name,
            'status' => $status,
            'status_label' => isset($labels[$status]) ? $labels[$status] : $status,
            'created' => $created_ts ? wp_date('d.m.Y H:i', $created_ts, wp_timezone()) : '',
            'created_ts' => $created_ts,
            'pickup' => self::format_quote_datetime($pickup_date, $pickup_time, '08:00'),
            'return' => self::format_quote_datetime($return_date, $return_time, '20:00'),
            'pickup_ts' => $pickup_ts,
            'return_ts' => $return_ts,
            'order_id' => $order_id,
            'relevant' => $relevant,
            'customer_name' => $customer['name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'quote_price' => $quote_price,
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'NOK',
            'notes' => $notes,
            'checkout_url' => $status === 'quote-accepted' ? self::checkout_url($quote_id, $product_id) : '',
        ];
    }

    private static function quote_form_data($quote_id)
    {
        $result = ['values' => [], 'forms' => []];

        foreach (['order_quote_meta', 'unformatted_order_quote_meta'] as $meta_key) {
            $raw = get_post_meta($quote_id, $meta_key, true);
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                if (isset($entry['name']) && array_key_exists('value', $entry)) {
                    $name = (string) $entry['name'];
                    if (!array_key_exists($name, $result['values'])) {
                        $result['values'][$name] = $entry['value'];
                    }
                }

                if (isset($entry['forms']) && is_array($entry['forms'])) {
                    foreach ($entry['forms'] as $key => $value) {
                        $key = sanitize_key((string) $key);
                        if ($key !== '' && !array_key_exists($key, $result['forms'])) {
                            $result['forms'][$key] = is_scalar($value) ? (string) $value : '';
                        }
                    }
                }
            }
        }

        return $result;
    }

    private static function first_value($quote_id, $form_values, $keys)
    {
        foreach ($keys as $key) {
            $value = get_post_meta($quote_id, $key, true);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }

            if (isset($form_values[$key]) && is_scalar($form_values[$key]) && trim((string) $form_values[$key]) !== '') {
                return trim((string) $form_values[$key]);
            }
        }

        return '';
    }

    private static function customer_details($quote_id, $post, $forms)
    {
        $user_id = $post ? (int) $post->post_author : 0;
        $user = $user_id ? get_userdata($user_id) : false;

        $first = $user_id ? trim((string) get_user_meta($user_id, 'billing_first_name', true)) : '';
        $last = $user_id ? trim((string) get_user_meta($user_id, 'billing_last_name', true)) : '';
        $name = trim($first . ' ' . $last);

        if ($name === '' && isset($forms['name'])) {
            $name = trim((string) $forms['name']);
        }
        if ($name === '' && $user) {
            $name = trim((string) $user->display_name);
        }
        if ($name === '') {
            $name = 'Ukjent kunde';
        }

        $email = '';
        if (function_exists('rnb_get_quote_customer_email')) {
            $email = trim((string) rnb_get_quote_customer_email($quote_id));
        }
        if ($email === '' && isset($forms['email'])) {
            $email = trim((string) $forms['email']);
        }
        if ($email === '' && $user) {
            $email = trim((string) $user->user_email);
        }

        $phone = $user_id ? trim((string) get_user_meta($user_id, 'billing_phone', true)) : '';
        foreach (['phone', 'quote_phone', 'telephone', 'tel'] as $key) {
            if ($phone === '' && isset($forms[$key])) {
                $phone = trim((string) $forms[$key]);
            }
        }

        return ['name' => $name, 'email' => $email, 'phone' => $phone];
    }

    private static function append_note($quote_id, $comment)
    {
        $comment = sanitize_textarea_field($comment);
        if ($comment === '') {
            return;
        }

        $notes = get_post_meta($quote_id, self::NOTES_META, true);
        $notes = is_array($notes) ? array_values($notes) : [];

        $user = wp_get_current_user();
        $timestamp = time();

        $notes[] = [
            'id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('trent_', true),
            'text' => $comment,
            'created_at' => wp_date('d.m.Y H:i', $timestamp, wp_timezone()),
            'created_ts' => $timestamp,
            'user_id' => (int) $user->ID,
            'author' => $user->display_name ? (string) $user->display_name : (string) $user->user_login,
        ];

        if (count($notes) > 50) {
            $notes = array_slice($notes, -50);
        }

        update_post_meta($quote_id, self::NOTES_META, $notes);
    }

    private static function checkout_url($quote_id, $product_id)
    {
        if (
            !$product_id ||
            !class_exists('\\REDQ_RnB\\RequestForQuote') ||
            !method_exists('\\REDQ_RnB\\RequestForQuote', 'get_guest_checkout_id')
        ) {
            return '';
        }

        $page_id = (int) \REDQ_RnB\RequestForQuote::get_guest_checkout_id();
        if (!$page_id) {
            return '';
        }

        $url = get_permalink($page_id);
        if (!$url) {
            return '';
        }

        return esc_url_raw(add_query_arg([
            'rfq_checkout' => 1,
            'product_id' => $product_id,
            'quote_id' => $quote_id,
        ], $url));
    }

    private static function send_status_email($quote_id, $status)
    {
        if (
            !class_exists('\\REDQ_RnB\\Email') ||
            !function_exists('rnb_get_quote_admin_profile') ||
            !function_exists('rnb_get_quote_customer_email')
        ) {
            return;
        }

        $to_email = trim((string) rnb_get_quote_customer_email($quote_id));
        if ($to_email === '' || !is_email($to_email)) {
            return;
        }

        $profile = rnb_get_quote_admin_profile();
        $from_email = isset($profile['email']) ? (string) $profile['email'] : (string) get_option('admin_email');
        $from_name = isset($profile['name']) ? (string) $profile['name'] : (string) get_bloginfo('name');

        try {
            $email = new \REDQ_RnB\Email();

            if ($status === 'quote-accepted') {
                $email->quote_accepted_notify_customer(
                    $to_email,
                    __('Congratulations! Your quote request has been accepted', 'redq-rental'),
                    $from_email,
                    $from_name,
                    ['quote_id' => $quote_id]
                );
                return;
            }

            $email->quote_status_update_notify_customer(
                $to_email,
                __('Your quote request status has been updated', 'redq-rental'),
                $from_email,
                $from_name,
                ['quote_id' => $quote_id]
            );
        } catch (Throwable $e) {
            // Ikke rull tilbake status hvis e-postsystemet feiler.
        }
    }

    private static function is_relevant_quote($status, $order_id, $pickup_ts, $return_ts, $created_ts)
    {
        if (!in_array($status, ['quote-pending', 'quote-processing', 'quote-on-hold'], true)) {
            return false;
        }

        if ($order_id > 0) {
            return false;
        }

        $now = time();

        if ($return_ts > 0) {
            return $return_ts >= $now;
        }

        if ($pickup_ts > 0) {
            $timezone = wp_timezone();
            $today = new DateTimeImmutable('today', $timezone);
            return $pickup_ts >= $today->getTimestamp();
        }

        // Dersom en helt ny forespørsel mangler lesbare leiedatoer, behold den synlig
        // i én uke slik at en gyldig ny forespørsel ikke forsvinner ved dataproblemer.
        return $created_ts > 0 && $created_ts >= ($now - (7 * DAY_IN_SECONDS));
    }

    private static function quote_datetime_timestamp($date, $time, $default_time)
    {
        $date = is_scalar($date) ? trim((string) $date) : '';
        $time = is_scalar($time) ? trim((string) $time) : '';

        if ($date === '') {
            return 0;
        }

        if (strpos($date, '|') !== false) {
            list($date_part, $pipe_time) = array_pad(explode('|', $date, 2), 2, '');
            $date = trim($date_part);
            if ($time === '' && trim($pipe_time) !== '') {
                $time = trim($pipe_time);
            }
        }

        if ($time === '') {
            $time = $default_time;
        }

        $value = $date . ' ' . $time;
        $timezone = wp_timezone();
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y/m/d H:i:s',
            'Y/m/d H:i',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->getTimestamp();
            }
        }

        try {
            return (new DateTimeImmutable($value, $timezone))->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    }

    private static function format_quote_datetime($date, $time, $default_time)
    {
        $date = is_scalar($date) ? trim((string) $date) : '';
        $time = is_scalar($time) ? trim((string) $time) : '';

        if ($date === '') {
            return '';
        }

        if (strpos($date, '|') !== false) {
            list($date_part, $pipe_time) = array_pad(explode('|', $date, 2), 2, '');
            $date = trim($date_part);
            if ($time === '' && trim($pipe_time) !== '') {
                $time = trim($pipe_time);
            }
        }

        if ($time === '') {
            $time = $default_time;
        }

        $timestamp = self::quote_datetime_timestamp($date, $time, $default_time);
        if ($timestamp > 0) {
            return wp_date('d.m.Y H:i', $timestamp, wp_timezone());
        }

        return trim($date . ' ' . $time);
    }
}

TRent_Admin_App_Quotes::register();

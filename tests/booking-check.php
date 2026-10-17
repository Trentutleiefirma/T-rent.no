<?php

namespace REDQ_RnB\Traits {
    trait Assets_Trait {}
}

namespace REDQ_RnB {
    class Email
    {
        public static $accepted = 0;
        public static $updates = 0;

        public function quote_accepted_notify_customer(...$args)
        {
            self::$accepted++;
            return true;
        }

        public function quote_status_update_notify_customer(...$args)
        {
            self::$updates++;
        }
    }
}

namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';

    use REDQ_RnB\RequestForQuote;
    use REDQ_RnB\Traits\Data_Trait;
    use REDQ_RnB\Traits\Period_Trait;

    const ARRAY_A = 'ARRAY_A';

    $GLOBALS['quote_meta'] = [];
    $GLOBALS['order_statuses'] = [11 => 'processing', 12 => 'pending'];
    $GLOBALS['post_types'] = [];
    $GLOBALS['post_statuses'] = [];

    function add_action(...$args) {}
    function add_filter(...$args) {}
    function apply_filters($name, $value, ...$args) { return $value; }
    function absint($value) { return abs((int) $value); }
    function is_shop() { return false; }
    function sanitize_text_field($value) { return trim((string) $value); }
    function wp_unslash($value) { return $value; }
    function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
    function wp_date($format) { return date($format); }
    function wp_timezone() { return new \DateTimeZone('Europe/Oslo'); }
    function redq_rental_get_settings($product, $section) {
        if ($section === 'labels') {
            return ['labels' => ['invalid_range_notice' => 'Invalid date range']];
        }
        return ['conditions' => [
            'max_time_late' => 1,
            'date_format' => 'm/d/Y',
            'time_format' => '24-hours',
            'pay_extra_hours' => 'no',
            'euro_format' => 'no',
        ]];
    }
    function wc_get_order($id) {
        $status = $GLOBALS['order_statuses'][$id] ?? null;
        return $status ? new class($status) {
            private $status;
            public function __construct($status) { $this->status = $status; }
            public function get_status() { return $this->status; }
        } : false;
    }
    function rnb_get_quote_customer_email($id) { return 'customer@example.test'; }
    function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
    function rnb_get_quote_admin_profile() { return ['email' => 'owner@example.test', 'name' => 'T-Rent']; }
    function get_post_meta($id, $key, $single) { return $GLOBALS['quote_meta'][$id][$key] ?? ''; }
    function get_post_type($id) { return $GLOBALS['post_types'][$id] ?? ''; }
    function get_post_status($id) { return $GLOBALS['post_statuses'][$id] ?? ''; }
    function wc_get_product($id) {
        return $id ? new class {
            public function get_type() { return 'redq_rental'; }
        } : false;
    }
    function update_post_meta($id, $key, $value) { $GLOBALS['quote_meta'][$id][$key] = $value; }
    function delete_post_meta($id, $key) { unset($GLOBALS['quote_meta'][$id][$key]); }
    function current_time($format) { return '2026-10-09 10:00:00'; }
    function __($message, $domain) { return $message; }
    function esc_html($message) { return $message; }
    function esc_html__($message, $domain) { return $message; }
    function wp_verify_nonce($nonce, $action) { return true; }
    class JsonResponse extends \Exception {
        public $response;
        public function __construct($response) { $this->response = $response; }
    }
    function wp_send_json($response) { throw new JsonResponse($response); }

    class FakeDB
    {
        public $prefix = 'wp_';
        public $rows = [];
        public function prepare($query, ...$values) { return vsprintf(str_replace(['%d', '%s'], ['%s', "'%s'"], $query), $values); }
        public function get_results($query, $format) { return $this->rows; }
    }

    function expect_same($expected, $actual, $label) {
        if ($expected !== $actual) {
            fwrite(STDERR, "$label: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n");
            exit(1);
        }
        echo "PASS $label\n";
    }

    require dirname(__DIR__) . '/functions.php';
    require dirname(__DIR__) . '/Data_Trait.php';
    require dirname(__DIR__) . '/Period_Trait.php';
    require dirname(__DIR__) . '/Error_Trait.php';
    require dirname(__DIR__) . '/RequestForQuote.php';

    $calculator = new class { use Data_Trait; };
    $make_booking = function ($pickup, $return) use ($calculator) {
        [$pickup_date, $pickup_time] = explode(' ', $pickup);
        [$return_date, $return_time] = explode(' ', $return);
        return $calculator->calculate_rental_duration(100, compact('pickup_date', 'pickup_time', 'return_date', 'return_time'));
    };

    $one_day = $make_booking('2026-10-09 08:00', '2026-10-09 20:00');
    expect_same(1, $one_day['days'], 'same-day price is one day');
    expect_same(['2026-10-09'], $one_day['booked_dates']['saved'], 'same-day paid date');

    $evening = $make_booking('2026-10-09 19:00', '2026-10-10 20:00');
    expect_same(1, $evening['days'], 'evening pickup is one paid day');
    expect_same(['2026-10-10'], $evening['booked_dates']['saved'], 'evening pickup starts paid dates tomorrow');

    // Exact reproduction from quote #853: 15 Oct 20:00 to 16 Oct 20:00.
    $quote_853 = $make_booking('2026-10-15 20:00', '2026-10-16 20:00');
    expect_same(1, $quote_853['days'], 'quote 853 converts to one cart day');
    expect_same(['2026-10-16'], $quote_853['booked_dates']['saved'], 'quote 853 charges 16 October only');

    expect_same(
        1299.0,
        trent_rnb_quote_total_for_cart(1299, 1299, 299, 1),
        'correct one-day quote total stays unchanged'
    );
    expect_same(
        1299.0,
        trent_rnb_quote_total_for_cart(1598, 1299, 299, 1),
        'legacy extra day is removed during quote checkout'
    );
    expect_same(
        1399.0,
        trent_rnb_quote_total_for_cart(1399, 1299, 299, 1),
        'negotiated quote total stays unchanged'
    );

    $GLOBALS['quote_meta'][882]['add-to-cart'] = 736;
    $GLOBALS['quote_meta'][882]['unformatted_order_quote_meta'] = json_encode([
        ['name' => 'pickup_time', 'value' => '18:30'],
        ['name' => 'dropoff_time', 'value' => '20:00'],
    ]);
    $GLOBALS['quote_meta'][882]['order_quote_meta'] = json_encode([
        ['name' => 'pickup_time', 'value' => '00:00'],
        ['name' => 'dropoff_time', 'value' => '23:59'],
    ]);
    $GLOBALS['post_types'][882] = 'request_quote';
    $GLOBALS['post_statuses'][882] = 'quote-accepted';
    expect_same(true, trent_rnb_is_accepted_quote_checkout(882, 736), 'accepted quote checkout recognized');
    expect_same(false, trent_rnb_is_accepted_quote_checkout(882, 999), 'quote cannot bypass another product');
    $GLOBALS['post_statuses'][882] = 'quote-pending';
    expect_same(false, trent_rnb_is_accepted_quote_checkout(882, 736), 'pending quote cannot bypass pickup notice');
    $GLOBALS['post_statuses'][882] = 'quote-accepted';

    $restored_quote = $calculator->restore_quote_selected_times([
        'pickup_time' => '00:00',
        'dropoff_time' => '23:59',
        'return_time' => '23:59',
    ], 882);
    expect_same('18:30', $restored_quote['pickup_time'], 'accepted quote restores original pickup time');
    expect_same('20:00', $restored_quote['dropoff_time'], 'accepted quote restores original dropoff time');

    $cart_handler_source = file_get_contents(dirname(__DIR__) . '/CartHandler.php');
    expect_same(
        1,
        preg_match('/handle_form\(\$_POST,\s*\$is_quote_checkout\)/', $cart_handler_source),
        'accepted quote uses checkout validation during add to cart'
    );

    $long = $make_booking('2026-10-09 19:00', '2026-10-11 20:00');
    expect_same(['2026-10-10', '2026-10-11'], $long['booked_dates']['saved'], 'multi-day paid dates');

    $GLOBALS['wpdb'] = new FakeDB();
    $GLOBALS['wpdb']->rows = [
        ['order_id' => 11, 'pickup_datetime' => '2030-10-09 19:00:00', 'return_datetime' => '2030-10-10 20:00:00'],
        ['order_id' => 11, 'pickup_datetime' => '2030-10-12 08:00:00', 'return_datetime' => '2030-10-13 00:00:00'],
        ['order_id' => 12, 'pickup_datetime' => '2030-10-14 08:00:00', 'return_datetime' => '2030-10-15 20:00:00'],
    ];
    $calendar = new class { use Period_Trait; };
    $occupied = $calendar->get_confirmed_booking_status_dates(100, 200, ['date_format' => 'm/d/Y']);
    sort($occupied);
    expect_same(['10/09/2030', '10/10/2030', '10/12/2030'], $occupied, 'calendar occupancy includes free pickup, excludes midnight and pending');

    $now = new \DateTimeImmutable('now', wp_timezone());
    expect_same(false, trent_rnb_pickup_has_notice($now->modify('+50 minutes')->format('Y-m-d'), $now->modify('+50 minutes')->format('H:i'), 100), 'pickup within 50 minutes rejected');
    expect_same(true, trent_rnb_pickup_has_notice($now->modify('+70 minutes')->format('Y-m-d'), $now->modify('+70 minutes')->format('H:i'), 100), 'pickup after 70 minutes allowed');
    expect_same(true, trent_rnb_pickup_has_notice($now->modify('+70 minutes')->format('m/d/Y'), $now->modify('+70 minutes')->format('H:i'), 100), 'frontend pickup date format accepted');
    expect_same('pickup_date_required', trent_rnb_pickup_validation_error('', '12:00', 100), 'missing pickup date has a specific error');
    expect_same('pickup_time_required', trent_rnb_pickup_validation_error('2030-10-11', '', 100), 'missing pickup time has a specific error');
    expect_same('pickup_datetime_invalid', trent_rnb_pickup_validation_error('2030-02-31', '12:00', 100), 'invalid pickup date has a specific error');
    expect_same('pickup_too_soon', trent_rnb_pickup_validation_error($now->modify('+50 minutes')->format('Y-m-d'), $now->modify('+50 minutes')->format('H:i'), 100), 'short pickup notice has a specific error');
    expect_same(false, trent_rnb_pickup_has_notice('2030-02-31', '12:00', 100), 'invalid pickup date rejected');
    expect_same(false, trent_rnb_pickup_has_notice('2030-10-11', '', 100), 'missing pickup time rejected');

    $quote_template = file_get_contents(dirname(__DIR__) . '/request-quote.php');
    expect_same(1, preg_match('/<button\s+type="button"\s+id="quote-content-confirm"/', $quote_template), 'quote trigger cannot submit the rental form');
    expect_same(1, preg_match('/<button\s+type="button"\s+class="quote-submit"/', $quote_template), 'quote popup button cannot submit the rental form directly');

    $validator = new class { use \REDQ_RnB\Traits\Error_Trait; };
    $missing_date_errors = $validator->handle_form([
        'product_id' => 100,
        'inventory_id' => 200,
        'pickup_date' => '',
        'pickup_time' => '12:00',
    ]);
    expect_same('Velg hentedato.', $missing_date_errors[0], 'direct booking explains the missing pickup date');

    $direct_errors = $validator->handle_form([
        'product_id' => 100,
        'inventory_id' => 200,
        'pickup_date' => '2030-10-11',
        'pickup_time' => '',
    ]);
    expect_same(true, !empty($direct_errors), 'direct booking without pickup time rejected');
    expect_same('Velg hentetid.', $direct_errors[0], 'direct booking explains the missing pickup time');

    $request = (new \ReflectionClass(RequestForQuote::class))->newInstanceWithoutConstructor();
    $_POST = [
        'nonce' => 'valid',
        'product_id' => 100,
        'form_data' => [['name' => 'pickup_date', 'value' => '10/11/2030']],
    ];
    try {
        $request->request_for_a_quote();
        throw new \RuntimeException('Quote without pickup time was accepted');
    } catch (JsonResponse $response) {
        expect_same(400, $response->response['status_code'], 'quote without pickup time rejected before creation');
    }
    $_POST['form_data'][] = ['name' => 'pickup_time', 'value' => '12:00'];
    $_POST['form_data'][0]['value'] = '02/31/2030';
    try {
        $request->request_for_a_quote();
        throw new \RuntimeException('Quote with invalid date was accepted');
    } catch (JsonResponse $response) {
        expect_same(400, $response->response['status_code'], 'quote with invalid date rejected before creation');
    }

    $quote = (object) ['ID' => 31, 'post_type' => 'request_quote'];
    $request->notify_quote_status_transition('quote-accepted', 'quote-pending', $quote);
    $request->notify_quote_status_transition('quote-accepted', 'quote-pending', $quote);
    expect_same(1, \REDQ_RnB\Email::$accepted, 'accepted quote mail sent once');
    $request->notify_quote_status_transition('quote-cancelled', 'quote-accepted', $quote);
    expect_same(1, \REDQ_RnB\Email::$updates, 'cancelled quote status notification');
    $request->notify_quote_status_transition('quote-accepted', 'quote-cancelled', $quote);
    expect_same(2, \REDQ_RnB\Email::$accepted, 'new approval sends new accepted email');
    $request->notify_quote_status_transition('trash', 'quote-accepted', $quote);
    expect_same(1, \REDQ_RnB\Email::$updates, 'trash does not notify customer');
    $request->notify_quote_status_transition('quote-accepted', 'trash', $quote);
    expect_same(2, \REDQ_RnB\Email::$accepted, 'restoring from trash does not notify customer');
    echo "All booking checks passed\n";
}

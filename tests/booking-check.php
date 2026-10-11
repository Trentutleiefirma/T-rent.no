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

    function add_action(...$args) {}
    function add_filter(...$args) {}
    function apply_filters($name, $value, ...$args) { return $value; }
    function absint($value) { return abs((int) $value); }
    function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
    function wp_date($format) { return date($format); }
    function wp_timezone() { return new \DateTimeZone('Europe/Oslo'); }
    function redq_rental_get_settings($product, $section) {
        return ['conditions' => [
            'max_time_late' => 1,
            'date_format' => 'm/d/Y',
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
    function update_post_meta($id, $key, $value) { $GLOBALS['quote_meta'][$id][$key] = $value; }
    function current_time($format) { return '2026-10-09 10:00:00'; }
    function __($message, $domain) { return $message; }

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

    $request = (new \ReflectionClass(RequestForQuote::class))->newInstanceWithoutConstructor();
    $quote = (object) ['ID' => 31, 'post_type' => 'request_quote'];
    $request->notify_quote_status_transition('quote-accepted', 'quote-pending', $quote);
    $request->notify_quote_status_transition('quote-accepted', 'quote-pending', $quote);
    expect_same(1, \REDQ_RnB\Email::$accepted, 'accepted quote mail sent once');
    $request->notify_quote_status_transition('quote-rejected', 'quote-pending', $quote);
    expect_same(1, \REDQ_RnB\Email::$updates, 'other quote status notification');
    echo "All booking checks passed\n";
}

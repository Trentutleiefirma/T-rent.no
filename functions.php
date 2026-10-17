<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;

function is_rental_product($product_id)
{
    if (is_shop()) {
        return false;
    }

    $is_product = wc_get_product($product_id);
    $product_type = $is_product ? $is_product->get_type() : '';

    return $product_type && $product_type === 'redq_rental' ? true : false;
}

function rnb_generalized_date_format($date, $euro_format)
{
    $formatted_date = $euro_format === 'no' ? $date : strtotime(str_replace('/', '.', $date));
    return (new Carbon($formatted_date))->toDateString();
}

/**
 * T-Rent rents by calendar day, not elapsed hours.
 * Same date = 1 rental day. Each additional calendar date adds one rental day.
 * Exception: pickup between 19:00 and 21:00 on the previous calendar date
 * is free evening-before pickup and must never add an extra rental day.
 */
function rnb_get_duration($start, $end)
{
    $defaults = [
        'duration' => 0,
        'days'     => 0,
        'hours'    => 0,
    ];

    if (empty($start) || empty($end)) {
        return $defaults;
    }

    $start_date = $start->copy()->startOfDay();
    $end_date   = $end->copy()->startOfDay();

    if ($end_date->lt($start_date)) {
        return $defaults;
    }

    $calendar_diff = (int) $start_date->diffInDays($end_date);
    $days = $calendar_diff + 1;

    // Pickup 19:00-21:00 on the date before the paid rental period is free.
    // Subtract that pickup date for every multi-date booking, not just 1-day rentals.
    $pickup_time = $start->format('H:i');
    if (
        $calendar_diff >= 1 &&
        $pickup_time >= '19:00' &&
        $pickup_time <= '21:00'
    ) {
        $days = max(1, $days - 1);
    }

    // RnB expects duration internally in hours.
    $duration = 24 * $days;

    return wp_parse_args([
        'duration' => $duration,
        'days'     => $days,
        'hours'    => 0,
    ], $defaults);
}

/**
 * Keep an approved quote price, except for the legacy evening-pickup error.
 *
 * Older quotes could save exactly one extra daily charge even though the
 * corrected cart calculation is one rental day. Only that exact mismatch is
 * repaired; negotiated quote prices remain authoritative.
 */
function trent_rnb_quote_total_for_cart($quote_total, $calculated_total, $daily_cost, $days)
{
    $quote_total      = (float) $quote_total;
    $calculated_total = (float) $calculated_total;
    $daily_cost       = (float) $daily_cost;
    $days             = (int) $days;

    if (
        $days === 1 &&
        $daily_cost > 0 &&
        abs($quote_total - ($calculated_total + $daily_cost)) < 0.01
    ) {
        return $calculated_total;
    }

    return $quote_total;
}

/**
 * Check whether an add-to-cart request belongs to an accepted RnB quote.
 *
 * Accepted quote links are allowed to reach checkout after the original
 * preparation window has passed. Verify both the quote status and product so
 * an arbitrary quote_id cannot be used to bypass normal product validation.
 */
function trent_rnb_is_accepted_quote_checkout($quote_id, $product_id)
{
    $quote_id = absint($quote_id);
    $product_id = absint($product_id);

    if (!$quote_id || !$product_id) {
        return false;
    }

    if (get_post_type($quote_id) !== 'request_quote' || get_post_status($quote_id) !== 'quote-accepted') {
        return false;
    }

    $quote_product_id = absint(get_post_meta($quote_id, 'add-to-cart', true));
    if (!$quote_product_id) {
        $quote_product_id = absint(get_post_meta($quote_id, '_product_id', true));
    }

    return $quote_product_id === $product_id;
}

function rnb_get_default_inventory_id($product_id = null)
{
    if (empty($product_id)) {
        $product_id = get_the_ID();
    }

    $inventory_id = '';
    $inventory_ids = rnb_get_product_inventory_id($product_id);

    if (!empty($inventory_ids) && is_array($inventory_ids)) {
        $inventory_id = $inventory_ids[0];
    }

    return $inventory_id;
}

function rnb_oder_item_data_key()
{
    return apply_filters('rnb_order_item_data_key', 'rnb_hidden_order_meta');
}

function rnb_format_oc_time($args, $conditions)
{
    $oc_times = $args['openning_closing'];

    if (empty($oc_times)) {
        return $args;
    }

    $formatted = [];
    $day_to_dow = rnb_get_day_of_dow();
    $timeFormat = $conditions['time_format'] === '24-hours' ? 'H:i' : 'h:ia';

    foreach ($oc_times as $key => $oc_time) {
        $today = Carbon::now()->toDateString();
        $min = (new Carbon($today . $oc_time['min']))->format($timeFormat);
        $max_time = $oc_time['max'] == '24:00' ? '23:59' : $oc_time['max'];
        $max = (new Carbon($today . $max_time))->format($timeFormat);
        $new_key = $day_to_dow[$key];
        $formatted[$new_key] = [
            'min' => $min,
            'max' => $max,
        ];
    }

    $args['openning_closing'] = $formatted;
    return $args;
}

function rnb_map_date_format()
{
    return [
        'm/d/Y' => 'mm/dd/yy',
        'd/m/Y' => 'dd/mm/yy',
        'Y/m/d' => 'yy/mm/dd'
    ];
}

function rnb_get_day_of_dow()
{
    return [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];
}

/**
 * T-Rent default pickup: opening time for the selected day.
 */
function rnb_get_default_pickup_time($product_id, $form_data = [])
{
    $pickup_date = !empty($form_data['pickup_date']) ? Carbon::parse($form_data['pickup_date']) : Carbon::now();
    $day = (int) $pickup_date->dayOfWeek;

    // Weekdays 08:00, weekends 09:00.
    return in_array($day, [Carbon::SATURDAY, Carbon::SUNDAY], true) ? '09:00' : '08:00';
}

/**
 * T-Rent default return: 20:00 every day.
 */
function rnb_get_default_return_time($product_id, $form_data = [])
{
    return '20:00';
}

/**
 * Validate the selected pickup date and time.
 *
 * Returning a stable error code lets the direct-cart and quote paths show the
 * same useful message instead of treating a missing time as an invalid date.
 * An empty string means the selection is valid.
 */
function trent_rnb_pickup_validation_error($date, $time, $product_id)
{
    if (!is_scalar($date) || trim((string) $date) === '') {
        return 'pickup_date_required';
    }

    if (!is_scalar($time) || trim((string) $time) === '') {
        return 'pickup_time_required';
    }

    if (!$product_id) {
        return 'pickup_datetime_invalid';
    }

    $date = trim((string) $date);
    $time = trim((string) $time);

    try {
        $settings = redq_rental_get_settings($product_id, 'conditions');
        $conditions = $settings['conditions'] ?? [];
        $date_formats = array_unique([$conditions['date_format'] ?? 'm/d/Y', 'Y-m-d']);
        $time_formats = ($conditions['time_format'] ?? '24-hours') === '24-hours'
            ? ['H:i', 'H:i:s']
            : ['g:i a', 'h:i a', 'H:i'];

        foreach ($date_formats as $date_format) {
            // Quote forms use the product format; the cart may use ISO dates.
            foreach (array_unique($time_formats) as $format) {
                $pickup = \DateTimeImmutable::createFromFormat(
                    '!' . $date_format . ' ' . $format,
                    $date . ' ' . $time,
                    wp_timezone()
                );
                $errors = \DateTimeImmutable::getLastErrors();
                if ($pickup && ($errors === false || (!$errors['warning_count'] && !$errors['error_count']))) {
                    return $pickup >= (new \DateTimeImmutable('now', wp_timezone()))->modify('+60 minutes')
                        ? ''
                        : 'pickup_too_soon';
                }
            }
        }
    } catch (\Throwable $e) {
        // An invalid or unsupported date/time must never bypass the notice.
    }

    return 'pickup_datetime_invalid';
}

/**
 * Get the customer-facing message for a pickup validation error.
 */
function trent_rnb_pickup_validation_message($error_code)
{
    $messages = [
        'pickup_date_required' => __('Velg hentedato.', 'redq-rental'),
        'pickup_time_required' => __('Velg hentetid.', 'redq-rental'),
        'pickup_datetime_invalid' => __('Velg en gyldig hentedato og hentetid.', 'redq-rental'),
        'pickup_too_soon' => __('Hentetiden må være minst én time frem i tid.', 'redq-rental'),
    ];

    return $messages[$error_code] ?? $messages['pickup_datetime_invalid'];
}

/**
 * Backwards-compatible boolean used by older RnB integration points.
 */
function trent_rnb_pickup_has_notice($date, $time, $product_id)
{
    return trent_rnb_pickup_validation_error($date, $time, $product_id) === '';
}

function rnb_format_weekend($weekends)
{
    if (empty($weekends)) {
        return [];
    }

    $results = [];
    foreach ($weekends as $weekend) {
        $results[] = intval($weekend);
    }
    return $results;
}

function rnb_get_time_slots($interval = 30, $format = 'H:i')
{
    $period = new CarbonPeriod('00:00', '' . $interval . ' minutes', '24:00');
    $slots = [];

    foreach ($period as $item) {
        array_push($slots, $item->format($format));
    }

    return $slots;
}

/**
 * Availability is checked using actual pickup/dropoff datetimes.
 * Exact handover is not considered an overlap.
 */
function rnb_check_dates_overlap($args1, $args2)
{
    $pickup_datetime = Carbon::parse($args1['pickup_date'] . ' ' . ($args1['pickup_time'] ?? '00:00'));
    $return_datetime = Carbon::parse($args1['return_date'] . ' ' . ($args1['return_time'] ?? '23:59:59'));

    $pickup_datetime_2 = Carbon::parse($args2['pickup_date'] . ' ' . ($args2['pickup_time'] ?? '00:00'));
    $return_datetime_2 = Carbon::parse($args2['dropoff_date'] . ' ' . ($args2['dropoff_time'] ?? '23:59:59'));

    if ($return_datetime->lte($pickup_datetime) || $return_datetime_2->lte($pickup_datetime_2)) {
        return false;
    }

    return $pickup_datetime->lt($return_datetime_2) && $pickup_datetime_2->lt($return_datetime);
}

function load_custom_product_class($classname, $product_type)
{
    if ($product_type === 'redq_rental') {
        $classname = 'WC_Product_Redq_rental';
    }

    return $classname;
}

if (!function_exists('is_view_quote_page')) {
    function is_view_quote_page()
    {
        global $wp;
        $page_id = wc_get_page_id('myaccount');
        return ($page_id && is_page($page_id) && isset($wp->query_vars['view-quote']));
    }
}

if (!function_exists('is_rfq_page')) {
    function is_rfq_page()
    {
        global $wp;
        $page_id = wc_get_page_id('myaccount');
        return ($page_id && is_page($page_id) && isset($wp->query_vars['view-quote'])) || ($page_id && is_page($page_id) && isset($wp->query_vars['request-quote']));
    }
}

function rnb_get_instance_payment_type()
{
    $instance_payment_type = get_option('rnb_instance_payment_type');
    $instance_payment_type = empty($instance_payment_type) ? 'percent' : $instance_payment_type;
    return $instance_payment_type;
}

function rnb_convert_dates_in_common_format($dates)
{
    if (empty($dates)) {
        return [];
    }

    return array_map(function ($date) {
        return Carbon::parse($date)->format('Y-m-d');
    }, $dates);
}

/**
 * T-Rent time picker rules.
 *
 * Pickup:
 * - Monday-Friday 08:00-21:00
 * - Saturday-Sunday 09:00-21:00
 *
 * Return:
 * - Monday-Friday 08:00-20:00
 * - Saturday-Sunday 09:00-20:00
 *
 * RnB's datetimepicker normally renders the complete 24-hour list and only
 * disables times outside minTime/maxTime. For T-Rent we instead feed the
 * picker an explicit allowTimes list. This removes the grey out-of-hours
 * entries completely and keeps the closing time itself selectable.
 */
add_action('wp_enqueue_scripts', function () {
    if (!wp_script_is('rnb-calendar', 'registered')) {
        return;
    }

    $site_timezone = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
    $site_timezone = $site_timezone !== '' ? $site_timezone : 'UTC';
    $site_timezone_offset = wp_timezone()->getOffset(new \DateTimeImmutable('now', wp_timezone()));

    $trent_timepicker_fix = <<<'JS'
(function ($) {
  $(function () {
    if (!window.RNB_CALENDER_ACTION || typeof window.RNB_CALENDER_ACTION.init !== 'function') {
      return;
    }

    var originalCalendarInit = window.RNB_CALENDER_ACTION.init;
    var trentCalendarData = null;
    var trentSiteTimezone = __TRENT_SITE_TIMEZONE__;
    var trentSiteUtcOffsetSeconds = __TRENT_SITE_UTC_OFFSET__;

    function timeToMinutes(time) {
      if (!time) {
        return null;
      }

      var value = String(time).trim().toLowerCase();
      var match24 = value.match(/^(\d{1,2}):(\d{2})$/);

      if (match24) {
        var hour24 = parseInt(match24[1], 10);
        var minute24 = parseInt(match24[2], 10);
        if (hour24 >= 0 && hour24 <= 23 && minute24 >= 0 && minute24 <= 59) {
          return hour24 * 60 + minute24;
        }
      }

      var match12 = value.match(/^(\d{1,2}):(\d{2})\s*(am|pm)$/);
      if (match12) {
        var hour12 = parseInt(match12[1], 10) % 12;
        var minute12 = parseInt(match12[2], 10);
        if (match12[3] === 'pm') {
          hour12 += 12;
        }
        return hour12 * 60 + minute12;
      }

      return null;
    }

    function formatTime(minutes, conditions) {
      var hours = Math.floor(minutes / 60);
      var mins = minutes % 60;

      if (conditions.time_format === '24-hours') {
        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
      }

      var suffix = hours >= 12 ? 'pm' : 'am';
      var displayHour = hours % 12 || 12;
      return displayHour + ':' + String(mins).padStart(2, '0') + ' ' + suffix;
    }

    function parseCalendarDate(value, conditions) {
      if (!value) {
        return null;
      }

      var parts = String(value).split('/');
      var dateObj = null;

      if (parts.length === 3) {
        if (conditions.date_format === 'Y/m/d') {
          dateObj = new Date(
            parseInt(parts[0], 10),
            parseInt(parts[1], 10) - 1,
            parseInt(parts[2], 10)
          );
        } else if (conditions.euro_format === 'yes' || conditions.date_format === 'd/m/Y') {
          dateObj = new Date(
            parseInt(parts[2], 10),
            parseInt(parts[1], 10) - 1,
            parseInt(parts[0], 10)
          );
        } else {
          dateObj = new Date(
            parseInt(parts[2], 10),
            parseInt(parts[0], 10) - 1,
            parseInt(parts[1], 10)
          );
        }
      } else {
        dateObj = new Date(value);
      }

      return dateObj && !isNaN(dateObj.getTime()) ? dateObj : null;
    }

    function getSiteNow() {
      var current = new Date();

      if (
        trentSiteTimezone &&
        window.Intl &&
        typeof window.Intl.DateTimeFormat === 'function'
      ) {
        try {
          var formatter = new window.Intl.DateTimeFormat('en-US', {
            timeZone: trentSiteTimezone,
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
            hourCycle: 'h23',
          });

          if (typeof formatter.formatToParts === 'function') {
            var values = {};
            formatter.formatToParts(current).forEach(function (part) {
              if (part.type !== 'literal') {
                values[part.type] = parseInt(part.value, 10);
              }
            });

            if (
              Number.isFinite(values.year) &&
              Number.isFinite(values.month) &&
              Number.isFinite(values.day) &&
              Number.isFinite(values.hour) &&
              Number.isFinite(values.minute)
            ) {
              return {
                year: values.year,
                month: values.month - 1,
                day: values.day,
                hours: values.hour % 24,
                minutes: values.minute,
                seconds: Number.isFinite(values.second) ? values.second : 0,
              };
            }
          }
        } catch (error) {
          // A fixed UTC offset below covers browsers without IANA support.
        }
      }

      var shifted = new Date(
        current.getTime() + trentSiteUtcOffsetSeconds * 1000
      );

      return {
        year: shifted.getUTCFullYear(),
        month: shifted.getUTCMonth(),
        day: shifted.getUTCDate(),
        hours: shifted.getUTCHours(),
        minutes: shifted.getUTCMinutes(),
        seconds: shifted.getUTCSeconds(),
      };
    }

    function sameSiteDate(date, siteNow) {
      return !!(
        date &&
        siteNow &&
        date.getFullYear() === siteNow.year &&
        date.getMonth() === siteNow.month &&
        date.getDate() === siteNow.day
      );
    }

    function buildAllowedTimes(elementId, data) {
      if (!data || !data.calendar_props || !data.calendar_props.settings) {
        return [];
      }

      var conditions = data.calendar_props.settings.conditions || {};
      var isPickup = elementId === '#pickup-time';
      var dateValue = isPickup
        ? $('#pickup-date').val()
        : ($('#dropoff-date').val() || $('#pickup-date').val());
      var selectedDate = parseCalendarDate(dateValue, conditions);

      if (!selectedDate) {
        return [];
      }

      var day = selectedDate.getDay();
      var isWeekend = day === 0 || day === 6;
      var openMinutes = (isWeekend ? 9 : 8) * 60;
      var closeMinutes = (isPickup ? 21 : 20) * 60;
      var interval = parseInt(conditions.time_interval, 10);

      if (!interval || interval < 1) {
        interval = 30;
      }

      var minimumMinutes = openMinutes;
      var now = getSiteNow();

      // Use the shop timezone, not the visitor's device timezone. Otherwise a
      // device set to UTC can be offered slots that the Norway server rejects.
      if (sameSiteDate(selectedDate, now)) {
        var nowMinutes = now.hours * 60 + now.minutes;
        if (now.seconds > 0) {
          nowMinutes += 1;
        }
        if (isPickup) {
          nowMinutes += 60;
        }
        minimumMinutes = Math.max(
          minimumMinutes,
          Math.ceil(nowMinutes / interval) * interval
        );
      }

      // Same calendar date: return must be after pickup.
      if (!isPickup && $('#pickup-date').val() === $('#dropoff-date').val()) {
        var pickupMinutes = timeToMinutes($('#pickup-time').val());
        if (pickupMinutes !== null) {
          minimumMinutes = Math.max(minimumMinutes, pickupMinutes + interval);
        }
      }

      var minuteValues = [];
      for (var value = openMinutes; value <= closeMinutes; value += interval) {
        if (value >= minimumMinutes) {
          minuteValues.push(value);
        }
      }

      // Preserve RnB inventory/date-specific availability when it exists.
      var sourceTimes = null;
      var allowedByDate = data.allowed_datetime || {};

      if (
        dateValue &&
        Object.prototype.hasOwnProperty.call(allowedByDate, dateValue)
      ) {
        sourceTimes = Array.isArray(allowedByDate[dateValue])
          ? allowedByDate[dateValue]
          : [];
      } else if (
        Array.isArray(conditions.allowed_times) &&
        conditions.allowed_times.length > 0
      ) {
        sourceTimes = conditions.allowed_times;
      }

      if (sourceTimes !== null) {
        var allowedMinutes = {};
        sourceTimes.forEach(function (time) {
          var parsed = timeToMinutes(time);
          if (parsed !== null) {
            allowedMinutes[parsed] = true;
          }
        });

        minuteValues = minuteValues.filter(function (minutes) {
          return allowedMinutes[minutes] === true;
        });
      }

      return minuteValues.map(function (minutes) {
        return formatTime(minutes, conditions);
      });
    }

    function applyTRentPicker(elementId, data) {
      if (!$(elementId).length || typeof $.fn.datetimepicker !== 'function') {
        return;
      }

      var conditions = data.calendar_props.settings.conditions || {};
      var times = buildAllowedTimes(elementId, data);
      var $field = $(elementId);
      var currentMinutes = timeToMinutes($field.val());
      var allowedMinuteValues = times.map(timeToMinutes);

      if (
        currentMinutes !== null &&
        allowedMinuteValues.indexOf(currentMinutes) === -1
      ) {
        $field.val('');
      }

      var options = {
        datepicker: false,
        timepicker: times.length > 0,
        format: conditions.time_format === '24-hours' ? 'H:i' : 'h:i a',
        formatTime: conditions.time_format === '24-hours' ? 'H:i' : 'h:i a',
        scrollInput: false,
        minTime: false,
        maxTime: false,
        allowTimes: times.length > 0 ? times : ['__trent_no_time__'],
        onShow: function () {
          var freshTimes = buildAllowedTimes(elementId, data);
          this.setOptions({
            timepicker: freshTimes.length > 0,
            minTime: false,
            maxTime: false,
            allowTimes:
              freshTimes.length > 0 ? freshTimes : ['__trent_no_time__'],
          });
        },
      };

      $field.datetimepicker(options);
    }

    function refreshTRentPickers(data) {
      applyTRentPicker('#pickup-time', data);
      applyTRentPicker('#dropoff-time', data);
    }

    window.RNB_CALENDER_ACTION.init = function (data) {
      trentCalendarData = data;
      var result = originalCalendarInit.apply(this, arguments);

      setTimeout(function () {
        refreshTRentPickers(data);
      }, 0);

      $(document)
        .off('.trentTimeHours')
        .on(
          'change.trentTimeHours input.trentTimeHours',
          '#pickup-date, #dropoff-date, #pickup-time',
          function () {
            setTimeout(function () {
              refreshTRentPickers(data);
            }, 0);
          }
        )
        .on(
          'click.trentTimeHours',
          '#cal-submit-btn, #drop-cal-submit-btn',
          function () {
            setTimeout(function () {
              refreshTRentPickers(data);
            }, 0);
          }
        );

      return result;
    };

    if (!window.__trentTimePickerCaptureInstalled) {
      window.__trentTimePickerCaptureInstalled = true;

      var captureRefresh = function (event) {
        var target = event.target;
        if (!target || !trentCalendarData) {
          return;
        }

        if (target.id === 'pickup-time') {
          applyTRentPicker('#pickup-time', trentCalendarData);
        } else if (target.id === 'dropoff-time') {
          applyTRentPicker('#dropoff-time', trentCalendarData);
        }
      };

      document.addEventListener('mousedown', captureRefresh, true);
      document.addEventListener('touchstart', captureRefresh, true);
      document.addEventListener('focusin', captureRefresh, true);
    }
  });
})(jQuery);
JS;

    $trent_timepicker_fix = str_replace(
        ['__TRENT_SITE_TIMEZONE__', '__TRENT_SITE_UTC_OFFSET__'],
        [wp_json_encode($site_timezone), (string) $site_timezone_offset],
        $trent_timepicker_fix
    );

    wp_add_inline_script('rnb-calendar', $trent_timepicker_fix, 'after');
}, 100);


/**
 * T-Rent: purge cached rental product pages after an order changes.
 *
 * CALENDAR_DATA is printed into the product page, so a cached page can otherwise
 * keep showing the old green/red calendar after a booking is confirmed.
 */
if (!function_exists('trent_rnb_purge_order_product_cache')) {
    function trent_rnb_purge_order_product_cache($order_id)
    {
        $order_id = absint($order_id);

        if (!$order_id || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $product_ids = [];

        foreach ($order->get_items('line_item') as $item) {
            $product_id = absint($item->get_product_id());

            if (!$product_id) {
                continue;
            }

            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== 'redq_rental') {
                continue;
            }

            $product_ids[] = $product_id;
        }

        $product_ids = array_values(array_unique($product_ids));

        foreach ($product_ids as $product_id) {
            clean_post_cache($product_id);

            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($product_id);
            }

            // LiteSpeed Cache listens to this action when active.
            do_action('litespeed_purge_post', $product_id);
        }
    }
}

/**
 * Purge after the normal checkout/thank-you flow.
 * Priority 100 runs after RnB has activated its availability rows.
 */
add_action('woocommerce_thankyou', function ($order_id) {
    trent_rnb_purge_order_product_cache($order_id);
}, 100, 1);

/**
 * Purge whenever an order status changes so confirmed/cancelled bookings are
 * reflected on the storefront without a manual cache clear.
 */
add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status, $order) {
    trent_rnb_purge_order_product_cache($order_id);
}, 100, 4);

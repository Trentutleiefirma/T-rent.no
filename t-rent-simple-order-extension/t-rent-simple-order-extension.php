<?php
/**
 * Plugin Name: T-Rent – Enkel ordreforlengelse
 * Description: Forlenger sluttdatoen på en eksisterende RnB-leieordre uten å endre pris, depositum, betaling eller ordrestatus.
 * Version: 1.0.0
 * Author: T-Rent
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TRent_Simple_Order_Extension
{
    const ACTION = 't_rent_simple_extend_order';
    const NONCE_ACTION = 't_rent_simple_extend_order';

    public function __construct()
    {
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_admin_panel'], 40, 1);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_extension']);
        add_action('admin_notices', [$this, 'render_admin_notice']);
    }

    public function render_admin_panel($order)
    {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $items = $this->rental_items($order);
        if (empty($items)) {
            return;
        }

        echo '<div class="order_data_column" style="width:100%;clear:both;padding-top:18px">';
        echo '<h3 style="margin-bottom:6px">Forleng leie</h3>';
        echo '<p style="margin-top:0">Endrer kun siste leiedag og RnB-kalenderen. Pris, depositum, betaling og ordrestatus endres ikke.</p>';

        if ($this->order_is_closed($order)) {
            echo '<p><em>Denne ordren har status ' . esc_html(wc_get_order_status_name($order->get_status())) . ' og kan ikke forlenges.</em></p>';
            echo '</div>';
            return;
        }

        foreach ($items as $item_id => $item) {
            $current = $this->current_return($item);
            $inventory_id = $this->inventory_id($item);

            echo '<div style="border-top:1px solid #ddd;padding:14px 0 4px">';
            echo '<strong>' . esc_html($item->get_name()) . '</strong>';

            if (!$current) {
                echo '<p style="color:#b32d2e">Fant ikke eksisterende returdato. Ingen endring kan gjøres sikkert.</p>';
                echo '</div>';
                continue;
            }

            echo '<p style="margin:5px 0">Nåværende siste leiedag: <strong>' . esc_html(wp_date('d.m.Y', $current['timestamp'])) . '</strong></p>';

            if (!$inventory_id) {
                echo '<p style="color:#b32d2e">Fant ikke RnB-inventaret på ordrelinjen. Ingen endring kan gjøres sikkert.</p>';
                echo '</div>';
                continue;
            }

            $min_date = (new DateTimeImmutable($current['date'], wp_timezone()))
                ->modify('+1 day')
                ->format('Y-m-d');

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-top:8px">';
            echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
            echo '<input type="hidden" name="order_id" value="' . esc_attr($order->get_id()) . '">';
            echo '<input type="hidden" name="item_id" value="' . esc_attr($item_id) . '">';
            wp_nonce_field(self::NONCE_ACTION . '_' . $order->get_id() . '_' . $item_id, '_t_rent_extend_nonce');

            echo '<p class="form-field" style="margin:0">';
            echo '<label for="t_rent_new_return_' . esc_attr($item_id) . '"><strong>Ny siste leiedag</strong></label><br>';
            echo '<input id="t_rent_new_return_' . esc_attr($item_id) . '" type="date" name="new_return_date" min="' . esc_attr($min_date) . '" required>';
            echo '</p>';

            echo '<p style="margin:0"><button type="submit" class="button button-primary">Forleng leie</button></p>';
            echo '</form>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function handle_extension()
    {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;

        if (!$order_id || !$item_id) {
            wp_die(esc_html__('Mangler ordre eller ordrelinje.', 't-rent'));
        }

        if (!(current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders'))) {
            wp_die(esc_html__('Du har ikke tilgang til å endre denne ordren.', 't-rent'));
        }

        check_admin_referer(
            self::NONCE_ACTION . '_' . $order_id . '_' . $item_id,
            '_t_rent_extend_nonce'
        );

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke ordren.');
        }

        if ($this->order_is_closed($order)) {
            $this->redirect_with_notice($order_id, 'error', 'Ordren har en status som ikke kan forlenges.');
        }

        $item = $order->get_item($item_id);
        if (!$item || !$this->is_rental_item($item)) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke en gyldig RnB-leie på ordren.');
        }

        $new_return_date = isset($_POST['new_return_date'])
            ? sanitize_text_field(wp_unslash($_POST['new_return_date']))
            : '';

        if (!$this->valid_date($new_return_date)) {
            $this->redirect_with_notice($order_id, 'error', 'Ugyldig ny sluttdato.');
        }

        $current = $this->current_return($item);
        if (!$current) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke eksisterende returdato. Ingen endring ble gjort.');
        }

        $new_return = $this->local_timestamp($new_return_date, $current['time']);
        if (!$new_return || $new_return <= $current['timestamp']) {
            $this->redirect_with_notice($order_id, 'error', 'Ny siste leiedag må være senere enn dagens siste leiedag.');
        }

        $inventory_id = $this->inventory_id($item);
        if (!$inventory_id) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke RnB-inventaret. Ingen endring ble gjort.');
        }

        $availability_row = $this->own_availability_row($order_id, $item_id);
        if (!$availability_row) {
            $this->redirect_with_notice(
                $order_id,
                'error',
                'Fant ikke en entydig RnB-kalenderpost for denne ordrelinjen. Ingen endring ble gjort.'
            );
        }

        if ((int) $availability_row['inventory_id'] !== $inventory_id) {
            $this->redirect_with_notice(
                $order_id,
                'error',
                'Inventaret på ordren stemmer ikke med RnB-kalenderen. Ingen endring ble gjort.'
            );
        }

        if (!$this->extension_is_available(
            $order,
            $item,
            $inventory_id,
            $current['timestamp'],
            $new_return
        )) {
            $this->redirect_with_notice(
                $order_id,
                'error',
                'Kan ikke forlenge: utstyret er allerede booket eller blokkert i deler av den valgte perioden.'
            );
        }

        $old_date = new DateTimeImmutable($current['date'], wp_timezone());
        $new_date = new DateTimeImmutable($new_return_date, wp_timezone());
        $extension_days = (int) $old_date->diff($new_date)->days;

        if ($extension_days < 1) {
            $this->redirect_with_notice($order_id, 'error', 'Forlengelsen må være minst én kalenderdag.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rnb_availability';

        $old_db_return = (string) $availability_row['return_datetime'];
        $old_db_duration = isset($availability_row['rental_duration'])
            ? (string) $availability_row['rental_duration']
            : '';

        $new_db_duration = $old_db_duration;
        if ($old_db_duration !== '' && is_numeric($old_db_duration)) {
            $new_db_duration = (string) ((int) $old_db_duration + $extension_days);
        }

        $updated = $wpdb->update(
            $table,
            [
                'return_datetime' => wp_date('Y-m-d H:i:s', $new_return),
                'rental_duration' => $new_db_duration,
            ],
            ['id' => (int) $availability_row['id']],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            $this->redirect_with_notice(
                $order_id,
                'error',
                'Kunne ikke oppdatere RnB-kalenderen. Ingen ordredata ble endret.'
            );
        }

        try {
            $this->update_item_dates($item, $current, $new_return_date, $new_return, $extension_days);
            $item->save();

            $order->add_order_note(sprintf(
                'Leien ble forlenget fra %1$s til %2$s. Pris, depositum, betaling og ordrestatus ble ikke endret.',
                wp_date('d.m.Y', $current['timestamp']),
                wp_date('d.m.Y', $new_return)
            ));
            $order->save();
        } catch (Throwable $e) {
            $wpdb->update(
                $table,
                [
                    'return_datetime' => $old_db_return,
                    'rental_duration' => $old_db_duration,
                ],
                ['id' => (int) $availability_row['id']],
                ['%s', '%s'],
                ['%d']
            );

            $this->redirect_with_notice(
                $order_id,
                'error',
                'Ordredata kunne ikke lagres. Kalenderendringen ble rullet tilbake.'
            );
        }

        $this->redirect_with_notice(
            $order_id,
            'success',
            'Leien er forlenget til ' . wp_date('d.m.Y', $new_return) . '. Ingen betaling eller depositum ble endret.'
        );
    }

    private function update_item_dates($item, $current, $new_return_date, $new_return, $extension_days)
    {
        $return_time = wp_date('H:i', $new_return);

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $meta_key) {
            $data = $item->get_meta($meta_key, true);
            if (!is_array($data) || empty($data)) {
                continue;
            }

            $data['dropoff_date'] = $new_return_date;
            $data['return_date'] = $new_return_date;
            $data['dropoff_time'] = $return_time;
            $data['return_time'] = $return_time;

            if (isset($data['posted_data']) && is_array($data['posted_data'])) {
                $data['posted_data']['dropoff_date'] = $new_return_date;
                $data['posted_data']['return_date'] = $new_return_date;
                $data['posted_data']['dropoff_time'] = $return_time;
                $data['posted_data']['return_time'] = $return_time;
            }

            // Bevisst: ikke endre rental_days_and_costs eller prisgrunnlaget på en allerede opprettet ordre.\n            // Kun returdata oppdateres; RnB availability er kalenderens sperre.\n\n            $item->update_meta_data($meta_key, $data);
        }

        $hidden_value = $new_return_date . '|' . $return_time;

        $item->update_meta_data('_return_hidden_datetime', $hidden_value);

        if ($item->get_meta('return_hidden_datetime', true) !== '') {
            $item->update_meta_data('return_hidden_datetime', $hidden_value);
        }

        $hidden_days = $item->get_meta('_return_hidden_days', true);
        if (is_numeric($hidden_days)) {
            $item->update_meta_data('_return_hidden_days', (int) $hidden_days + $extension_days);
        }

        $legacy_hidden_days = $item->get_meta('return_hidden_days', true);
        if (is_numeric($legacy_hidden_days)) {
            $item->update_meta_data('return_hidden_days', (int) $legacy_hidden_days + $extension_days);
        }

        $item->update_meta_data('Siste leiedag', wp_date('d.m.Y', $new_return));
    }

    private function extension_is_available($order, $item, $inventory_id, $from_timestamp, $to_timestamp)
    {
        global $wpdb;

        $capacity = (int) get_post_meta($inventory_id, 'quantity', true);
        if ($capacity < 1) {
            $capacity = 1;
        }

        $own_quantity = max(1, (int) $item->get_quantity());
        if ($own_quantity > $capacity) {
            return false;
        }

        $table = $wpdb->prefix . 'rnb_availability';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, order_id, item_id, block_by, pickup_datetime, return_datetime
                 FROM {$table}
                 WHERE inventory_id = %d
                   AND delete_status = 0
                   AND NOT (order_id = %d AND item_id = %d)
                   AND pickup_datetime < %s
                   AND return_datetime > %s",
                $inventory_id,
                $order->get_id(),
                $item->get_id(),
                wp_date('Y-m-d H:i:s', $to_timestamp),
                wp_date('Y-m-d H:i:s', $from_timestamp)
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return true;
        }

        $events = [];

        foreach ($rows as $row) {
            $row_start = $this->local_timestamp_from_datetime($row['pickup_datetime']);
            $row_end = $this->local_timestamp_from_datetime($row['return_datetime']);

            if (!$row_start || !$row_end) {
                return false;
            }

            if ((string) $row['block_by'] !== 'CUSTOM') {
                $other_order_id = isset($row['order_id']) ? absint($row['order_id']) : 0;
                $other_order = $other_order_id ? wc_get_order($other_order_id) : false;

                if ($other_order && in_array($other_order->get_status(), ['cancelled', 'failed', 'refunded'], true)) {
                    continue;
                }
            }

            $start = max($from_timestamp, $row_start);
            $end = min($to_timestamp, $row_end);

            if ($start >= $end) {
                continue;
            }

            if ((string) $row['block_by'] === 'CUSTOM') {
                $quantity = $capacity;
            } else {
                $quantity = 1;
                if (!empty($row['item_id'])) {
                    $stored_qty = wc_get_order_item_meta((int) $row['item_id'], '_qty', true);
                    if (is_numeric($stored_qty) && (int) $stored_qty > 0) {
                        $quantity = (int) $stored_qty;
                    }
                }
            }

            if (!isset($events[$start])) {
                $events[$start] = ['start' => 0, 'end' => 0];
            }
            if (!isset($events[$end])) {
                $events[$end] = ['start' => 0, 'end' => 0];
            }

            $events[$start]['start'] += $quantity;
            $events[$end]['end'] += $quantity;
        }

        if (empty($events)) {
            return true;
        }

        ksort($events, SORT_NUMERIC);
        $active = 0;

        foreach ($events as $event) {
            $active -= (int) $event['end'];
            $active += (int) $event['start'];

            if (($active + $own_quantity) > $capacity) {
                return false;
            }
        }

        return true;
    }

    private function own_availability_row($order_id, $item_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'rnb_availability';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, inventory_id, return_datetime, rental_duration
                 FROM {$table}
                 WHERE order_id = %d
                   AND item_id = %d
                   AND delete_status = 0",
                $order_id,
                $item_id
            ),
            ARRAY_A
        );

        if (!is_array($rows) || count($rows) !== 1) {
            return null;
        }

        return $rows[0];
    }

    private function rental_items($order)
    {
        $items = [];

        foreach ($order->get_items('line_item') as $item_id => $item) {
            if ($this->is_rental_item($item)) {
                $items[$item_id] = $item;
            }
        }

        return $items;
    }

    private function is_rental_item($item)
    {
        if (!is_object($item) || !method_exists($item, 'get_product')) {
            return false;
        }

        $product = $item->get_product();
        if ($product && method_exists($product, 'get_type') && $product->get_type() === 'redq_rental') {
            return true;
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (is_array($data) && !empty($data)) {
                return true;
            }
        }

        return false;
    }

    private function inventory_id($item)
    {
        foreach (['booking_inventory', '_booking_inventory'] as $key) {
            $value = $item->get_meta($key, true);
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (is_array($data) && !empty($data['booking_inventory'])) {
                return absint($data['booking_inventory']);
            }
        }

        return 0;
    }

    private function current_return($item)
    {
        $date = '';
        $time = '';

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $key) {
            $data = $item->get_meta($key, true);
            if (!is_array($data) || empty($data)) {
                continue;
            }

            foreach (['dropoff_date', 'return_date'] as $date_key) {
                if (!empty($data[$date_key])) {
                    $date = (string) $data[$date_key];
                    break;
                }
            }

            foreach (['dropoff_time', 'return_time'] as $time_key) {
                if (!empty($data[$time_key])) {
                    $time = (string) $data[$time_key];
                    break;
                }
            }

            if ($date === '' && !empty($data['posted_data']) && is_array($data['posted_data'])) {
                foreach (['dropoff_date', 'return_date'] as $date_key) {
                    if (!empty($data['posted_data'][$date_key])) {
                        $date = (string) $data['posted_data'][$date_key];
                        break;
                    }
                }

                foreach (['dropoff_time', 'return_time'] as $time_key) {
                    if (!empty($data['posted_data'][$time_key])) {
                        $time = (string) $data['posted_data'][$time_key];
                        break;
                    }
                }
            }

            if ($date !== '') {
                break;
            }
        }

        if ($date === '') {
            foreach (['_return_hidden_datetime', 'return_hidden_datetime'] as $key) {
                $hidden = $item->get_meta($key, true);
                if (!is_scalar($hidden) || trim((string) $hidden) === '') {
                    continue;
                }

                $parts = explode('|', trim((string) $hidden), 2);
                $date = isset($parts[0]) ? $parts[0] : '';
                $time = !empty($parts[1]) ? $parts[1] : $time;
                break;
            }
        }

        $date = $this->normalize_date($date);
        if ($date === '') {
            return null;
        }

        $time = $this->normalize_time($time);
        $timestamp = $this->local_timestamp($date, $time);

        if (!$timestamp) {
            return null;
        }

        return [
            'date' => $date,
            'time' => $time,
            'timestamp' => $timestamp,
        ];
    }

    private function normalize_date($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = explode('|', $value, 2)[0];

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        $timestamp = strtotime($value);
        return $timestamp ? wp_date('Y-m-d', $timestamp) : '';
    }

    private function normalize_time($value)
    {
        if (is_scalar($value) && preg_match('/^(\d{1,2}):(\d{2})/', trim((string) $value), $matches)) {
            $hour = min(23, max(0, (int) $matches[1]));
            $minute = min(59, max(0, (int) $matches[2]));
            return sprintf('%02d:%02d', $hour, $minute);
        }

        return '20:00';
    }

    private function valid_date($date)
    {
        $parsed = DateTime::createFromFormat('!Y-m-d', (string) $date, wp_timezone());
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function local_timestamp($date, $time)
    {
        try {
            $dt = new DateTimeImmutable(trim($date . ' ' . $time), wp_timezone());
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    }

    private function local_timestamp_from_datetime($value)
    {
        try {
            $dt = new DateTimeImmutable((string) $value, wp_timezone());
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return 0;
        }
    }

    private function order_is_closed($order)
    {
        return in_array($order->get_status(), ['cancelled', 'failed', 'refunded'], true);
    }

    public function render_admin_notice()
    {
        if (empty($_GET['t_rent_extend_notice'])) {
            return;
        }

        $message = sanitize_text_field(wp_unslash($_GET['t_rent_extend_notice']));
        $type = isset($_GET['t_rent_extend_type'])
            ? sanitize_key(wp_unslash($_GET['t_rent_extend_type']))
            : 'success';

        $class = $type === 'error'
            ? 'notice notice-error is-dismissible'
            : 'notice notice-success is-dismissible';

        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }

    private function redirect_with_notice($order_id, $type, $message)
    {
        $order = wc_get_order($order_id);
        $url = $order ? $order->get_edit_order_url() : admin_url('edit.php?post_type=shop_order');

        $url = add_query_arg(
            [
                't_rent_extend_type' => $type,
                't_rent_extend_notice' => $message,
            ],
            $url
        );

        wp_safe_redirect($url);
        exit;
    }
}

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    new TRent_Simple_Order_Extension();
});

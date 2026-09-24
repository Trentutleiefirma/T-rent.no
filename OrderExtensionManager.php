<?php

namespace REDQ_RnB;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep rental extensions on the original WooCommerce/RnB order.
 *
 * The extension is stored as a fee line on the existing order. The original
 * RnB security deposit is never recreated or increased. A signed order-pay
 * link temporarily exposes only the unpaid extension amount to payment
 * gateways while the stored WooCommerce order total remains the full order
 * total (original rental + deposit + extension).
 */
class OrderExtensionManager
{
    const ACTION                    = 't_rent_extend_order';
    const NONCE_ACTION              = 't_rent_extend_order';
    const TOKEN_ARG                 = 't_rent_extension';
    const FEE_META                  = '_t_rent_extension_fee';
    const DUE_META                  = '_t_rent_extension_due';
    const TOKEN_META                = '_t_rent_extension_token';
    const FULL_TOTAL_META           = '_t_rent_extension_full_order_total';
    const PAID_META                 = '_t_rent_extension_paid_total';
    const ORIGINAL_TX_META          = '_t_rent_extension_original_transaction_id';
    const ORIGINAL_GATEWAY_META     = '_t_rent_extension_original_payment_method';
    const ORIGINAL_GATEWAY_TITLE    = '_t_rent_extension_original_payment_method_title';
    const PAYMENT_HISTORY_META      = '_t_rent_extension_payments';
    const PAYMENT_ACTIVE_UNTIL_META = '_t_rent_extension_payment_active_until';

    public function __construct()
    {
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_admin_panel'], 30, 1);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_extension']);
        add_action('admin_notices', [$this, 'admin_notice']);
        add_action('template_redirect', [$this, 'activate_payment_context'], 5);

        add_filter('woocommerce_order_needs_payment', [$this, 'order_needs_payment'], PHP_INT_MAX, 3);
        add_filter('woocommerce_order_get_total', [$this, 'payment_total'], PHP_INT_MAX, 2);
        add_filter('woocommerce_valid_order_statuses_for_payment_complete', [$this, 'payment_complete_statuses'], PHP_INT_MAX, 2);
        add_filter('woocommerce_order_button_text', [$this, 'payment_button_text'], PHP_INT_MAX, 1);

        add_action('woocommerce_pay_order_before_payment', [$this, 'render_payment_notice'], 5);
        add_action('woocommerce_pay_order_before_submit', [$this, 'render_payment_token_field'], 5);
        add_action('woocommerce_pre_payment_complete', [$this, 'capture_extension_payment_context'], 5, 2);
        add_action('woocommerce_payment_complete', [$this, 'mark_extension_paid'], 5, 2);
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

        $pending_due = $this->money($order->get_meta(self::DUE_META, true));
        $token       = (string) $order->get_meta(self::TOKEN_META, true);
        $pay_url     = ($pending_due > 0 && $token !== '') ? $this->payment_url($order, $token) : '';

        echo '<div class="order_data_column" style="width:100%;padding-top:18px;clear:both">';
        echo '<h3 style="margin-bottom:8px">Forleng leie</h3>';
        echo '<p style="margin-top:0">Forlengelsen blir liggende på denne ordren. Depositumet endres ikke.</p>';

        if ($pending_due > 0) {
            echo '<div class="notice notice-warning inline" style="margin:8px 0 14px;padding:10px 12px">';
            echo '<strong>Ubetalt forlengelse:</strong> ' . wp_kses_post(wc_price($pending_due, ['currency' => $order->get_currency()]));
            if ($pay_url !== '') {
                echo '<br><label for="t-rent-extension-pay-url"><strong>Betalingslenke:</strong></label>';
                echo '<input id="t-rent-extension-pay-url" type="text" readonly value="' . esc_attr($pay_url) . '" style="width:100%;margin-top:5px" onclick="this.select();">';
            }
            echo '</div>';
        }

        if (!$order->is_paid()) {
            echo '<p><em>Ordren må være betalt før den kan forlenges med denne funksjonen.</em></p>';
            echo '</div>';
            return;
        }

        if ($pending_due > 0) {
            echo '<p><em>Betal den pågående forlengelsen før du lager en ny.</em></p>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:520px">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        echo '<input type="hidden" name="order_id" value="' . esc_attr($order->get_id()) . '">';
        wp_nonce_field(self::NONCE_ACTION . '_' . $order->get_id(), '_t_rent_extension_nonce');

        if (count($items) > 1) {
            echo '<p class="form-field form-field-wide"><label for="t_rent_extension_item_id"><strong>Utstyr</strong></label><select id="t_rent_extension_item_id" name="item_id" style="width:100%">';
            foreach ($items as $item_id => $item) {
                echo '<option value="' . esc_attr($item_id) . '">' . esc_html($item->get_name()) . '</option>';
            }
            echo '</select></p>';
        } else {
            $item_id = array_key_first($items);
            echo '<input type="hidden" name="item_id" value="' . esc_attr($item_id) . '">';
        }

        $first_item = reset($items);
        $current_end = $this->current_return_date($first_item);

        echo '<p class="form-field form-field-wide"><label for="t_rent_extension_return_date"><strong>Ny siste leiedag</strong></label>';
        echo '<input id="t_rent_extension_return_date" type="date" name="new_return_date" min="' . esc_attr($current_end ?: wp_date('Y-m-d')) . '" required style="width:100%">';
        if ($current_end) {
            echo '<span class="description">Nåværende siste leiedag: ' . esc_html(wp_date('d.m.Y', strtotime($current_end))) . '</span>';
        }
        echo '</p>';

        echo '<p class="form-field form-field-wide"><label for="t_rent_extension_amount"><strong>Pris for forlengelsen inkl. mva</strong></label>';
        echo '<input id="t_rent_extension_amount" type="number" name="extension_amount" min="1" step="0.01" required style="width:100%" placeholder="f.eks. 1500">';
        echo '<span class="description">Dette beløpet legges til samme ordre. Eksisterende depositum beholdes uendret.</span></p>';

        echo '<p><button type="submit" class="button button-primary">Forleng leie</button></p>';
        echo '</form>';
        echo '</div>';
    }

    public function handle_extension()
    {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        if (!$order_id || !(current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders'))) {
            wp_die(esc_html__('Du har ikke tilgang til å endre denne ordren.', 'redq-rental'));
        }

        check_admin_referer(self::NONCE_ACTION . '_' . $order_id, '_t_rent_extension_nonce');

        $order = wc_get_order($order_id);
        if (!$order) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke ordren.');
        }

        if (!$order->is_paid()) {
            $this->redirect_with_notice($order_id, 'error', 'Ordren må være betalt før den kan forlenges.');
        }

        if ($this->money($order->get_meta(self::DUE_META, true)) > 0) {
            $this->redirect_with_notice($order_id, 'error', 'Det finnes allerede en ubetalt forlengelse på ordren.');
        }

        $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
        $item = $order->get_item($item_id);
        if (!$item || !$this->is_rental_item($item)) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke RnB-leieproduktet på ordren.');
        }

        $new_return_date = isset($_POST['new_return_date']) ? sanitize_text_field(wp_unslash($_POST['new_return_date'])) : '';
        $extension_amount = isset($_POST['extension_amount']) ? $this->money(wp_unslash($_POST['extension_amount'])) : 0;

        if (!$this->valid_date($new_return_date)) {
            $this->redirect_with_notice($order_id, 'error', 'Ugyldig sluttdato.');
        }

        if ($extension_amount <= 0) {
            $this->redirect_with_notice($order_id, 'error', 'Forlengelsesbeløpet må være større enn 0 kr.');
        }

        $current_return = $this->current_return_datetime($item);
        if (!$current_return) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke eksisterende returdato i RnB-dataene.');
        }

        $return_time = wp_date('H:i:s', $current_return);
        $new_return_timestamp = strtotime($new_return_date . ' ' . $return_time);

        if (!$new_return_timestamp || $new_return_timestamp <= $current_return) {
            $this->redirect_with_notice($order_id, 'error', 'Ny sluttdato må være senere enn dagens sluttdato.');
        }

        $inventory_id = $this->inventory_id($item);
        if (!$inventory_id) {
            $this->redirect_with_notice($order_id, 'error', 'Fant ikke RnB-inventaret for ordren.');
        }

        if (!$this->extension_is_available($order, $item, $inventory_id, $current_return, $new_return_timestamp)) {
            $this->redirect_with_notice($order_id, 'error', 'Maskinen er allerede booket i deler av den valgte forlengelsen.');
        }

        $before_total = $this->money($order->get_total('edit'));
        $fee = $this->upsert_extension_fee($order, $item, $extension_amount);

        if (!$fee) {
            $this->redirect_with_notice($order_id, 'error', 'Kunne ikke legge forlengelsen til ordren.');
        }

        $order->calculate_totals(false);
        $order->save();

        $after_total = $this->money($order->get_total('edit'));
        $amount_due = $this->money($after_total - $before_total);

        if ($amount_due <= 0) {
            $this->redirect_with_notice($order_id, 'error', 'Forlengelsen ga ikke noe nytt beløp å betale.');
        }

        $this->update_rental_dates($order, $item, $new_return_date, $new_return_timestamp);

        $token = wp_generate_password(40, false, false);
        $order->update_meta_data(self::DUE_META, $amount_due);
        $order->update_meta_data(self::TOKEN_META, $token);
        $order->update_meta_data(self::FULL_TOTAL_META, $after_total);

        if ($order->get_meta(self::ORIGINAL_TX_META, true) === '') {
            $order->update_meta_data(self::ORIGINAL_TX_META, (string) $order->get_transaction_id('edit'));
        }
        if ($order->get_meta(self::ORIGINAL_GATEWAY_META, true) === '') {
            $order->update_meta_data(self::ORIGINAL_GATEWAY_META, (string) $order->get_payment_method('edit'));
            $order->update_meta_data(self::ORIGINAL_GATEWAY_TITLE, (string) $order->get_payment_method_title('edit'));
        }

        $order->add_order_note(sprintf(
            'Leien ble forlenget til %1$s. Tillegg å betale: %2$s. Depositumet er uendret.',
            wp_date('d.m.Y', $new_return_timestamp),
            wp_strip_all_tags(wc_price($amount_due, ['currency' => $order->get_currency()]))
        ));
        $order->save();

        $this->redirect_with_notice($order_id, 'success', 'Leien er forlenget. Betalingslenken ligger nå på ordren.');
    }

    public function order_needs_payment($needs_payment, $order, $valid_statuses)
    {
        if ($this->is_extension_payment_request($order)) {
            return $this->money($order->get_meta(self::DUE_META, true)) > 0;
        }

        return $needs_payment;
    }

    public function payment_total($total, $order)
    {
        if (!is_a($order, 'WC_Order')) {
            return $total;
        }

        if ($this->is_extension_payment_request($order) || $this->is_extension_gateway_request($order)) {
            $due = $this->money($order->get_meta(self::DUE_META, true));
            if ($due > 0) {
                return $due;
            }
        }

        return $total;
    }

    public function activate_payment_context()
    {
        $order = $this->request_order();
        if (!$order || !$this->is_extension_payment_request($order)) {
            return;
        }

        $order->update_meta_data(self::PAYMENT_ACTIVE_UNTIL_META, time() + DAY_IN_SECONDS);
        $order->save();

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('t_rent_extension_order_id', $order->get_id());
        }
    }

    public function payment_complete_statuses($statuses, $order)
    {
        if (!is_a($order, 'WC_Order')) {
            return $statuses;
        }

        if ($this->money($order->get_meta(self::DUE_META, true)) > 0) {
            $statuses[] = $order->get_status();
            $statuses = array_values(array_unique(array_filter($statuses)));
        }

        return $statuses;
    }

    public function payment_button_text($text)
    {
        $order = $this->request_order();
        if (!$order || !$this->is_extension_payment_request($order)) {
            return $text;
        }

        $due = $this->money($order->get_meta(self::DUE_META, true));
        if ($due <= 0) {
            return $text;
        }

        return sprintf('Betal forlengelse – %s', wp_strip_all_tags(wc_price($due, ['currency' => $order->get_currency()])));
    }

    public function render_payment_notice()
    {
        $order = $this->request_order();
        if (!$order || !$this->is_extension_payment_request($order)) {
            return;
        }

        $due = $this->money($order->get_meta(self::DUE_META, true));
        $full_total = $this->money($order->get_meta(self::FULL_TOTAL_META, true));

        echo '<div class="woocommerce-info" style="margin-bottom:18px">';
        echo '<strong>Du betaler kun forlengelsen:</strong> ' . wp_kses_post(wc_price($due, ['currency' => $order->get_currency()]));
        if ($full_total > 0) {
            echo '<br><small>Ordre #' . esc_html($order->get_order_number()) . ' beholder full ordreverdi på ' . wp_kses_post(wc_price($full_total, ['currency' => $order->get_currency()])) . '. Eksisterende depositum belastes ikke på nytt.</small>';
        }
        echo '</div>';
    }

    public function render_payment_token_field()
    {
        $order = $this->request_order();
        if (!$order || !$this->is_extension_payment_request($order)) {
            return;
        }

        $token = $this->request_token();
        if ($token !== '') {
            echo '<input type="hidden" name="' . esc_attr(self::TOKEN_ARG) . '" value="' . esc_attr($token) . '">';
        }
    }

    public function capture_extension_payment_context($order_id, $transaction_id = '')
    {
        $order = wc_get_order($order_id);
        if (!$order || $this->money($order->get_meta(self::DUE_META, true)) <= 0) {
            return;
        }

        if ($order->get_meta(self::ORIGINAL_TX_META, true) === '') {
            $order->update_meta_data(self::ORIGINAL_TX_META, (string) $order->get_transaction_id('edit'));
        }
        if ($order->get_meta(self::ORIGINAL_GATEWAY_META, true) === '') {
            $order->update_meta_data(self::ORIGINAL_GATEWAY_META, (string) $order->get_payment_method('edit'));
            $order->update_meta_data(self::ORIGINAL_GATEWAY_TITLE, (string) $order->get_payment_method_title('edit'));
        }
        $order->save();
    }

    public function mark_extension_paid($order_id, $transaction_id = '')
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $due = $this->money($order->get_meta(self::DUE_META, true));
        if ($due <= 0) {
            return;
        }

        $paid_total = $this->money($order->get_meta(self::PAID_META, true)) + $due;
        $history = $order->get_meta(self::PAYMENT_HISTORY_META, true);
        $history = is_array($history) ? $history : [];
        $history[] = [
            'amount'         => $due,
            'transaction_id' => (string) $transaction_id,
            'gateway'        => (string) $order->get_payment_method('edit'),
            'gateway_title'  => (string) $order->get_payment_method_title('edit'),
            'paid_at'        => current_time('mysql'),
        ];

        $order->update_meta_data(self::PAID_META, $paid_total);
        $order->update_meta_data(self::PAYMENT_HISTORY_META, $history);
        $order->delete_meta_data(self::DUE_META);
        $order->delete_meta_data(self::TOKEN_META);
        $order->delete_meta_data(self::PAYMENT_ACTIVE_UNTIL_META);

        if (function_exists('WC') && WC()->session) {
            WC()->session->__unset('t_rent_extension_order_id');
        }

        $original_tx = (string) $order->get_meta(self::ORIGINAL_TX_META, true);
        $original_gateway = (string) $order->get_meta(self::ORIGINAL_GATEWAY_META, true);
        $original_gateway_title = (string) $order->get_meta(self::ORIGINAL_GATEWAY_TITLE, true);

        if ($original_tx !== '') {
            $order->set_transaction_id($original_tx);
        }
        if ($original_gateway !== '') {
            $order->set_payment_method($original_gateway);
        }
        if ($original_gateway_title !== '') {
            $order->set_payment_method_title($original_gateway_title);
        }

        $order->add_order_note(sprintf(
            'Forlengelsen er betalt: %1$s.%2$s',
            wp_strip_all_tags(wc_price($due, ['currency' => $order->get_currency()])),
            $transaction_id ? ' Transaksjon: ' . sanitize_text_field($transaction_id) : ''
        ));
        $order->save();
    }

    public function admin_notice()
    {
        if (empty($_GET['t_rent_extension_notice'])) {
            return;
        }

        $type = isset($_GET['t_rent_extension_type']) ? sanitize_key(wp_unslash($_GET['t_rent_extension_type'])) : 'success';
        $message = sanitize_text_field(wp_unslash($_GET['t_rent_extension_notice']));
        $class = ($type === 'error') ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';

        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }

    private function upsert_extension_fee($order, $rental_item, $extension_amount_gross)
    {
        $fee = null;
        $existing_gross = 0;

        foreach ($order->get_items('fee') as $candidate) {
            if ((string) $candidate->get_meta(self::FEE_META, true) === 'yes') {
                $fee = $candidate;
                $existing_gross = $this->money($candidate->get_total('edit') + $candidate->get_total_tax('edit'));
                break;
            }
        }

        $target_gross = $this->money($existing_gross + $extension_amount_gross);
        $tax_rate = $this->effective_tax_rate($rental_item);
        $target_net = $tax_rate > 0 ? $target_gross / (1 + $tax_rate) : $target_gross;
        $target_net = $this->money($target_net);

        if (!$fee) {
            $fee = new \WC_Order_Item_Fee();
            $fee->set_name('Forlengelse');
            $fee->add_meta_data(self::FEE_META, 'yes', true);
            $order->add_item($fee);
        }

        $fee->set_amount($target_net);
        $fee->set_total($target_net);

        if ($tax_rate > 0) {
            $fee->set_tax_status('taxable');
            $fee->set_tax_class($rental_item->get_tax_class());
        } else {
            $fee->set_tax_status('none');
            $fee->set_tax_class('');
        }

        $fee->calculate_taxes($this->tax_location($order));
        $fee->save();
        return $fee;
    }

    private function tax_location($order)
    {
        $shipping_country = (string) $order->get_shipping_country();
        $use_shipping = $shipping_country !== '';

        return [
            'country'  => $use_shipping ? $shipping_country : (string) $order->get_billing_country(),
            'state'    => $use_shipping ? (string) $order->get_shipping_state() : (string) $order->get_billing_state(),
            'postcode' => $use_shipping ? (string) $order->get_shipping_postcode() : (string) $order->get_billing_postcode(),
            'city'     => $use_shipping ? (string) $order->get_shipping_city() : (string) $order->get_billing_city(),
        ];
    }

    private function update_rental_dates($order, $item, $new_return_date, $new_return_timestamp)
    {
        $rental_data = $this->rental_data($item);
        if (empty($rental_data)) {
            return;
        }

        $new_date = wp_date('Y-m-d', $new_return_timestamp);
        $return_time = wp_date('H:i', $new_return_timestamp);

        $rental_data['dropoff_date'] = $new_date;
        $rental_data['return_date'] = $new_date;
        $rental_data['dropoff_time'] = $return_time;
        $rental_data['return_time'] = $return_time;

        if (!isset($rental_data['posted_data']) || !is_array($rental_data['posted_data'])) {
            $rental_data['posted_data'] = [];
        }
        $rental_data['posted_data']['order_type'] = 'extend_order';
        $rental_data['posted_data']['dropoff_date'] = $new_date;
        $rental_data['posted_data']['return_date'] = $new_date;
        $rental_data['posted_data']['dropoff_time'] = $return_time;
        $rental_data['posted_data']['return_time'] = $return_time;

        $pickup_date = $this->pickup_date($rental_data);
        if ($pickup_date) {
            $days = max(1, (int) floor((strtotime($new_date) - strtotime($pickup_date)) / DAY_IN_SECONDS) + 1);
            if (!isset($rental_data['rental_days_and_costs']) || !is_array($rental_data['rental_days_and_costs'])) {
                $rental_data['rental_days_and_costs'] = [];
            }
            $rental_data['rental_days_and_costs']['days'] = $days;
            $rental_data['rental_days_and_costs']['flat_hours'] = $days * 24;
            $rental_data['rental_days_and_costs']['actual_hours'] = $days * 24;

            $saved = [];
            for ($i = 0; $i < $days; $i++) {
                $saved[] = wp_date('Y-m-d', strtotime('+' . $i . ' day', strtotime($pickup_date)));
            }
            if (!isset($rental_data['rental_days_and_costs']['booked_dates']) || !is_array($rental_data['rental_days_and_costs']['booked_dates'])) {
                $rental_data['rental_days_and_costs']['booked_dates'] = [];
            }
            $rental_data['rental_days_and_costs']['booked_dates']['saved'] = $saved;
        }

        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $meta_key) {
            if (is_array($item->get_meta($meta_key, true))) {
                $item->update_meta_data($meta_key, $rental_data);
            }
        }

        $item->update_meta_data('_return_hidden_datetime', $new_date . '|' . $return_time);
        $item->update_meta_data('Siste leiedag', wp_date('d.m.Y', $new_return_timestamp));
        $item->save();

        global $wpdb;
        $table = $wpdb->prefix . 'rnb_availability';
        $wpdb->update(
            $table,
            [
                'return_datetime' => wp_date('Y-m-d H:i:s', $new_return_timestamp),
                'rental_duration' => isset($rental_data['rental_days_and_costs']['days']) ? (string) $rental_data['rental_days_and_costs']['days'] : null,
                'updated_at'      => current_time('mysql'),
                'delete_status'   => 0,
            ],
            [
                'order_id' => $order->get_id(),
                'item_id'  => $item->get_id(),
            ],
            ['%s', '%s', '%s', '%d'],
            ['%d', '%d']
        );
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
                "SELECT order_id, item_id, pickup_datetime, return_datetime, block_by
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
            $start = max($from_timestamp, strtotime($row['pickup_datetime']));
            $end = min($to_timestamp, strtotime($row['return_datetime']));
            if (!$start || !$end || $start >= $end) {
                continue;
            }

            if ($row['block_by'] === 'CUSTOM') {
                $quantity = $capacity;
            } else {
                $quantity = max(1, (int) wc_get_order_item_meta((int) $row['item_id'], '_qty', true));
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

        return !empty($this->rental_data($item));
    }

    private function rental_data($item)
    {
        foreach (['rnb_hidden_order_meta', '_rnb_hidden_order_meta'] as $meta_key) {
            $data = $item->get_meta($meta_key, true);
            if (is_array($data) && !empty($data)) {
                return $data;
            }
        }
        return [];
    }

    private function inventory_id($item)
    {
        $data = $this->rental_data($item);
        if (!empty($data['booking_inventory'])) {
            return absint($data['booking_inventory']);
        }

        return absint($item->get_meta('_booking_inventory', true));
    }

    private function current_return_date($item)
    {
        $timestamp = $this->current_return_datetime($item);
        return $timestamp ? wp_date('Y-m-d', $timestamp) : '';
    }

    private function current_return_datetime($item)
    {
        $data = $this->rental_data($item);
        $date = '';
        $time = '';

        foreach (['dropoff_date', 'return_date'] as $key) {
            if (!empty($data[$key])) {
                $date = (string) $data[$key];
                break;
            }
        }

        foreach (['dropoff_time', 'return_time'] as $key) {
            if (!empty($data[$key])) {
                $time = (string) $data[$key];
                break;
            }
        }

        if ($date === '' && !empty($data['posted_data']) && is_array($data['posted_data'])) {
            foreach (['dropoff_date', 'return_date'] as $key) {
                if (!empty($data['posted_data'][$key])) {
                    $date = (string) $data['posted_data'][$key];
                    break;
                }
            }
            foreach (['dropoff_time', 'return_time'] as $key) {
                if (!empty($data['posted_data'][$key])) {
                    $time = (string) $data['posted_data'][$key];
                    break;
                }
            }
        }

        if ($date !== '') {
            $timestamp = strtotime($date . ' ' . ($time !== '' ? $time : '20:00:00'));
            if ($timestamp) {
                return $timestamp;
            }
        }

        $hidden = (string) $item->get_meta('_return_hidden_datetime', true);
        if ($hidden !== '') {
            $parts = explode('|', $hidden, 2);
            $timestamp = strtotime($parts[0] . ' ' . (!empty($parts[1]) ? $parts[1] : '20:00:00'));
            if ($timestamp) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function pickup_date($data)
    {
        if (!empty($data['pickup_date'])) {
            return wp_date('Y-m-d', strtotime($data['pickup_date']));
        }
        if (!empty($data['posted_data']['pickup_date'])) {
            return wp_date('Y-m-d', strtotime($data['posted_data']['pickup_date']));
        }
        return '';
    }

    private function effective_tax_rate($item)
    {
        $net = (float) $item->get_total('edit');
        $tax = (float) $item->get_total_tax('edit');
        if ($net > 0 && $tax > 0) {
            return $tax / $net;
        }
        return 0.0;
    }

    private function payment_url($order, $token)
    {
        return add_query_arg(self::TOKEN_ARG, rawurlencode($token), $order->get_checkout_payment_url());
    }

    private function request_order()
    {
        if (!function_exists('wc_get_order')) {
            return null;
        }

        $order_id = function_exists('get_query_var') ? absint(get_query_var('order-pay')) : 0;
        if (!$order_id && isset($_REQUEST['order_id'])) {
            $order_id = absint(wp_unslash($_REQUEST['order_id']));
        }
        if (!$order_id && isset($_REQUEST['key']) && function_exists('wc_get_order_id_by_order_key')) {
            $order_id = (int) wc_get_order_id_by_order_key(wc_clean(wp_unslash($_REQUEST['key'])));
        }

        return $order_id ? wc_get_order($order_id) : null;
    }

    private function request_token()
    {
        if (!isset($_REQUEST[self::TOKEN_ARG])) {
            return '';
        }
        return sanitize_text_field(wp_unslash($_REQUEST[self::TOKEN_ARG]));
    }

    private function is_extension_payment_request($order)
    {
        if (!is_a($order, 'WC_Order')) {
            return false;
        }

        $stored = (string) $order->get_meta(self::TOKEN_META, true);
        $provided = $this->request_token();
        if ($stored === '' || $provided === '' || !hash_equals($stored, $provided)) {
            return false;
        }

        return $this->money($order->get_meta(self::DUE_META, true)) > 0;
    }

    private function is_extension_gateway_request($order)
    {
        if (!is_a($order, 'WC_Order')) {
            return false;
        }

        $due = $this->money($order->get_meta(self::DUE_META, true));
        $active_until = (int) $order->get_meta(self::PAYMENT_ACTIVE_UNTIL_META, true);

        if ($due <= 0 || $active_until < time()) {
            return false;
        }

        if (function_exists('WC') && WC()->session) {
            $session_order_id = absint(WC()->session->get('t_rent_extension_order_id'));
            if ($session_order_id === $order->get_id()) {
                return true;
            }
        }

        if (isset($_REQUEST['wc-api']) || isset($_REQUEST['wc_api']) || isset($_REQUEST['wc-ajax'])) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        return false;
    }

    private function valid_date($date)
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function money($value)
    {
        if (function_exists('wc_format_decimal')) {
            return (float) wc_format_decimal($value, wc_get_price_decimals());
        }
        return round((float) $value, 2);
    }

    private function redirect_with_notice($order_id, $type, $message)
    {
        $url = wp_get_referer();
        if (!$url) {
            $url = admin_url('post.php?post=' . absint($order_id) . '&action=edit');
        }
        $url = add_query_arg([
            't_rent_extension_type'   => $type,
            't_rent_extension_notice' => $message,
        ], $url);
        wp_safe_redirect($url);
        exit;
    }
}

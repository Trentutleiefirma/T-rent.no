<?php
namespace REDQ_RnB;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * T-Rent standalone deposit authorization product.
 *
 * This is deliberately separate from RnB security deposits used on rental
 * products. The customer checks out directly, but the supported payment gateway
 * only authorizes/reserves the amount. Acowebs partial payments remain disabled.
 */
class DepositManager
{
    const PRODUCT_SKU    = 't-rent-depositum';
    const CART_KEY       = 't_rent_deposit_amount';
    const CONFIG_VERSION = '2';
    const ORDER_META     = '_t_rent_deposit_authorization';

    public function __construct()
    {
        // Create the separate WooCommerce product if it does not exist yet.
        add_action('init', [$this, 'ensure_deposit_product'], 20);

        // Only the standalone Depositum product gets this amount selector.
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_product_amount_selector'], 5);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_deposit_add_to_cart'], 99, 6);
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'single_add_to_cart_text'], 20, 2);
        add_filter('woocommerce_get_price_html', [$this, 'deposit_price_html'], 20, 2);
        add_filter('woocommerce_add_to_cart_redirect', [$this, 'redirect_deposit_to_checkout'], 99);

        // Store and price only this standalone product.
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 99, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item'], 99, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_deposit_price'], 99);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 20, 4);

        // A deposit authorization must always be a separate order.
        add_filter('woocommerce_order_item_needs_processing', [$this, 'deposit_needs_processing'], PHP_INT_MAX, 3);
        add_filter('woocommerce_available_payment_gateways', [$this, 'limit_deposit_payment_gateways'], PHP_INT_MAX);
        add_filter('woocommerce_order_button_text', [$this, 'deposit_order_button_text'], PHP_INT_MAX);
        add_action('woocommerce_checkout_order_created', [$this, 'mark_deposit_order'], 5);
        add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'mark_deposit_order'], 5);

        // Per-order authorization for the installed card gateways.
        add_filter('option_woocommerce_stripe_settings', [$this, 'stripe_authorization_settings'], PHP_INT_MAX, 2);
        add_filter('wc_stripe_generate_payment_request', [$this, 'stripe_authorization_request'], PHP_INT_MAX, 3);
        add_filter('wc_stripe_generate_create_intent_request', [$this, 'stripe_intent_authorization_request'], PHP_INT_MAX, 3);

        add_filter('option_woocommerce_woocommerce_payments_settings', [$this, 'woopayments_authorization_settings'], PHP_INT_MAX, 2);
        add_filter('wcpay_create_and_confirm_intent_request', [$this, 'woopayments_authorization_request'], PHP_INT_MAX, 2);

        /*
         * T-Rent does not use Acowebs partial payments. Rental orders remain
         * fully paid. The standalone deposit uses payment-gateway authorization,
         * not an Acowebs payment plan.
         */
        if (defined('AWCDP_VERSION')) {
            add_filter('awcdp_disable_deposit_condition', [$this, 'disable_acowebs_deposits'], PHP_INT_MAX, 2);
            add_action('woocommerce_before_calculate_totals', [$this, 'disable_acowebs_cart_mode'], PHP_INT_MAX);
            add_action('woocommerce_checkout_update_order_meta', [$this, 'remove_acowebs_order_meta'], PHP_INT_MAX);
            add_action('woocommerce_store_api_checkout_update_order_meta', [$this, 'remove_acowebs_order_meta'], PHP_INT_MAX);
        }
    }

    /**
     * Create one hidden simple WooCommerce product that can be shared by URL.
     * It is NOT a redq_rental product and therefore has no dates or booking data.
     */
    public function ensure_deposit_product()
    {
        if (!function_exists('wc_get_product_id_by_sku') || !class_exists('WC_Product_Simple')) {
            return 0;
        }

        $product_id = (int) wc_get_product_id_by_sku(self::PRODUCT_SKU);

        if ($product_id) {
            $product = wc_get_product($product_id);

            if (
                $product
                && (string) $product->get_meta('_t_rent_deposit_config_version', true) !== self::CONFIG_VERSION
            ) {
                $this->configure_deposit_product($product);
                $product_id = (int) $product->save();
            }

            $this->disable_acowebs_for_product($product_id);
            return $product_id;
        }

        $product = new \WC_Product_Simple();
        $product->set_sku(self::PRODUCT_SKU);
        $this->configure_deposit_product($product);

        $product_id = (int) $product->save();
        $this->disable_acowebs_for_product($product_id);

        return $product_id;
    }

    private function configure_deposit_product($product)
    {
        $product->set_name('Depositum');
        $product->set_slug('depositum');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_sold_individually(true);
        $product->set_regular_price('0');
        $product->set_price('0');
        $product->set_tax_status('none');
        $product->set_short_description(
            'Reserver depositum mellom 1 000 og 5 000 kr. Beløpet reserveres og trekkes ikke ved bestilling.'
        );
        $product->set_description(
            'Dette er en separat reservasjon av depositum. Kunden godkjenner beløpet direkte i checkout; '
            . 'det sendes ingen forespørsel. Beløpet trekkes ikke når reservasjonen opprettes. '
            . 'Det opprettes ingen booking, ingen leiedager og ingen reservasjon av utstyr.'
        );
        $product->update_meta_data('_t_rent_deposit_config_version', self::CONFIG_VERSION);
    }

    /**
     * Acowebs partial payments are not part of T-Rent's payment model.
     *
     * The plugin passes the product object as the filter value. Returning
     * false is its documented/internal signal to skip deposit handling.
     */
    public function disable_acowebs_deposits($condition, $default = true)
    {
        return false;
    }

    /**
     * Remove stale Acowebs cart/session state, including carts restored from
     * sessions created before this compatibility guard was installed.
     */
    public function disable_acowebs_cart_mode($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if ($cart && is_a($cart, 'WC_Cart')) {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart->cart_contents[$cart_item_key]['awcdp_deposit'])) {
                    unset($cart->cart_contents[$cart_item_key]['awcdp_deposit']);
                }
            }

            if (defined('AWCDP_VERSION')) {
                if (!is_array($cart->deposit_info ?? null)) {
                    $cart->deposit_info = [];
                }

                $cart->deposit_info['deposit_enabled'] = false;
            }
        }

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('awcdp_deposit_option', 'full');
            WC()->session->set('deposit_enabled', false);
        }
    }

    /**
     * Ensure new orders cannot retain Acowebs schedules that would later set
     * the order status to "partially-paid". Supports classic and block checkout.
     */
    public function remove_acowebs_order_meta($order_or_id)
    {
        $order = is_a($order_or_id, 'WC_Order')
            ? $order_or_id
            : wc_get_order($order_or_id);

        if (!$order) {
            return;
        }

        $meta_keys = [
            '_awcdp_deposits_payment_schedule',
            '_awcdp_deposits_order_has_deposit',
            '_awcdp_deposits_deposit_paid',
            '_awcdp_deposits_second_payment_paid',
            '_awcdp_deposits_deposit_amount',
            '_awcdp_deposits_second_payment',
            '_awcdp_deposits_deposit_breakdown',
            '_awcdp_deposits_deposit_payment_time',
            '_awcdp_deposits_second_payment_reminder_email_sent',
        ];

        foreach ($meta_keys as $meta_key) {
            $order->delete_meta_data($meta_key);
        }

        $order->update_meta_data('_awcdp_deposit_option', 'full');
        $order->update_meta_data('_awcdp_is_deposit', 'no');

        foreach ($order->get_items() as $item) {
            $item->delete_meta_data('awcdp_deposit_meta');
            $item->save();
        }

        $order->save();
    }

    private function disable_acowebs_for_product($product_id)
    {
        if ($product_id <= 0) {
            return;
        }

        update_post_meta($product_id, '_awcdp_deposit_enabled', 'no');
        update_post_meta($product_id, '_awcdp_deposit_force_deposit', 'no');
    }

    private function product_id()
    {
        if (!function_exists('wc_get_product_id_by_sku')) {
            return 0;
        }

        return (int) wc_get_product_id_by_sku(self::PRODUCT_SKU);
    }

    private function is_deposit_product($product_or_id)
    {
        $id = is_object($product_or_id) && method_exists($product_or_id, 'get_id')
            ? (int) $product_or_id->get_id()
            : (int) $product_or_id;

        $deposit_product_id = $this->product_id();

        return $deposit_product_id > 0 && $id === $deposit_product_id;
    }

    private function cart_has_deposit()
    {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (!empty($item['product_id']) && $this->is_deposit_product($item['product_id'])) {
                return true;
            }
        }

        return false;
    }

    private function cart_has_non_deposit()
    {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            if (empty($item['product_id']) || !$this->is_deposit_product($item['product_id'])) {
                return true;
            }
        }

        return false;
    }

    private function cart_is_deposit_only()
    {
        return $this->cart_has_deposit() && !$this->cart_has_non_deposit();
    }

    private function order_is_deposit_only($order)
    {
        if (!is_a($order, 'WC_Order')) {
            return false;
        }

        $has_deposit = false;

        foreach ($order->get_items('line_item') as $item) {
            $is_deposit = '' !== (string) $item->get_meta('_t_rent_deposit_amount', true)
                || $this->is_deposit_product($item->get_product_id());

            if (!$is_deposit) {
                return false;
            }

            $has_deposit = true;
        }

        return $has_deposit;
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

        if (
            !$order_id
            && isset($_REQUEST['key'])
            && function_exists('wc_get_order_id_by_order_key')
        ) {
            $order_key = wc_clean(wp_unslash($_REQUEST['key']));
            $order_id = (int) wc_get_order_id_by_order_key($order_key);
        }

        return $order_id ? wc_get_order($order_id) : null;
    }

    private function payment_context_is_deposit_only($order = null)
    {
        if (is_a($order, 'WC_Order')) {
            return $this->order_is_deposit_only($order);
        }

        if ($this->cart_is_deposit_only()) {
            return true;
        }

        $request_order = $this->request_order();

        return $request_order ? $this->order_is_deposit_only($request_order) : false;
    }

    private function valid_amount($amount)
    {
        $amount = function_exists('wc_format_decimal')
            ? (float) wc_format_decimal($amount)
            : (float) $amount;

        return ($amount >= 1000 && $amount <= 5000) ? round($amount, 2) : 0;
    }

    /**
     * Amount selector shown only on /produkt/depositum/.
     * ?depositum=3000 can be used to preselect an amount in a shared link.
     */
    public function render_product_amount_selector()
    {
        global $product;

        if (!$product || !$this->is_deposit_product($product)) {
            return;
        }

        $selected = 0;

        if (isset($_REQUEST[self::CART_KEY])) {
            $selected = $this->valid_amount(wp_unslash($_REQUEST[self::CART_KEY]));
        } elseif (isset($_GET['depositum'])) {
            $selected = $this->valid_amount(wp_unslash($_GET['depositum']));
        }

        echo '<div class="t-rent-standalone-deposit" style="margin:0 0 18px;max-width:420px">';
        echo '<label for="t-rent-standalone-deposit-amount" style="display:block;font-weight:600;margin-bottom:6px">Velg depositum</label>';
        echo '<select id="t-rent-standalone-deposit-amount" name="' . esc_attr(self::CART_KEY) . '" required style="width:100%;max-width:320px">';
        echo '<option value="">Velg beløp</option>';

        for ($amount = 1000; $amount <= 5000; $amount += 500) {
            echo '<option value="' . esc_attr($amount) . '" ' . selected($selected, $amount, false) . '>'
                . esc_html(wp_strip_all_tags(wc_price($amount)))
                . '</option>';
        }

        echo '</select>';
        echo '<p style="margin:8px 0 0;font-size:.92em">Beløpet reserveres hos betalingsleverandøren og trekkes ikke ved bestilling. Ingen forespørsel sendes.</p>';
        echo '</div>';
    }

    public function validate_deposit_add_to_cart(
        $passed,
        $product_id,
        $quantity,
        $variation_id = 0,
        $variations = [],
        $cart_item_data = []
    ) {
        if (!$this->is_deposit_product($product_id)) {
            if ($this->cart_has_deposit()) {
                wc_add_notice(
                    'Depositum må betales som en separat ordre. Fullfør eller fjern depositumet først.',
                    'error'
                );
                return false;
            }

            return $passed;
        }

        if ($this->cart_has_non_deposit()) {
            wc_add_notice(
                'Depositum må reserveres som en separat ordre. Tøm handlekurven før du fortsetter.',
                'error'
            );
            return false;
        }

        if (!empty($cart_item_data[self::CART_KEY])) {
            $amount = $this->valid_amount($cart_item_data[self::CART_KEY]);
        } else {
            $amount = isset($_REQUEST[self::CART_KEY])
                ? $this->valid_amount(wp_unslash($_REQUEST[self::CART_KEY]))
                : 0;
        }

        if ($amount <= 0) {
            wc_add_notice('Velg depositum mellom 1 000 og 5 000 kr.', 'error');
            return false;
        }

        return $passed;
    }

    public function single_add_to_cart_text($text, $product)
    {
        return $this->is_deposit_product($product) ? 'Reserver depositum' : $text;
    }

    public function deposit_price_html($price_html, $product)
    {
        if (!$this->is_deposit_product($product)) {
            return $price_html;
        }

        return '<span class="price">1 000–5 000 kr</span>';
    }

    /**
     * For this standalone product only, go directly to normal WooCommerce checkout.
     */
    public function redirect_deposit_to_checkout($url)
    {
        $requested_product_id = isset($_REQUEST['add-to-cart'])
            ? absint($_REQUEST['add-to-cart'])
            : 0;

        if ($requested_product_id && $this->is_deposit_product($requested_product_id)) {
            return wc_get_checkout_url();
        }

        return $url;
    }

    public function deposit_needs_processing($needs_processing, $product, $order_id)
    {
        return $this->is_deposit_product($product) ? true : $needs_processing;
    }

    public function limit_deposit_payment_gateways($gateways)
    {
        if (!$this->payment_context_is_deposit_only() || !is_array($gateways)) {
            return $gateways;
        }

        $allowed_gateways = ['stripe', 'woocommerce_payments', 'vipps'];

        foreach (array_keys($gateways) as $gateway_id) {
            if (!in_array($gateway_id, $allowed_gateways, true)) {
                unset($gateways[$gateway_id]);
            }
        }

        // The gateway instances may have loaded their settings before the
        // dynamic option filters below ran. Keep this request authorization-only.
        if (
            isset($gateways['stripe']->settings)
            && is_array($gateways['stripe']->settings)
        ) {
            $gateways['stripe']->settings['capture'] = 'no';
        }

        if (
            isset($gateways['woocommerce_payments']->settings)
            && is_array($gateways['woocommerce_payments']->settings)
        ) {
            $gateways['woocommerce_payments']->settings['manual_capture'] = 'yes';
        }

        return $gateways;
    }

    public function deposit_order_button_text($text)
    {
        return $this->payment_context_is_deposit_only() ? 'Reserver depositum' : $text;
    }

    public function mark_deposit_order($order)
    {
        if (!$this->order_is_deposit_only($order)) {
            return;
        }

        if ('authorization-only' === $order->get_meta(self::ORDER_META, true)) {
            return;
        }

        $order->update_meta_data(self::ORDER_META, 'authorization-only');
        $order->add_order_note(
            'Depositumordre: Betalingsgatewayen skal kun reservere beløpet (autorisasjon), ikke trekke det ved bestilling.'
        );
        $order->save();
    }

    public function stripe_authorization_settings($settings, $option = '')
    {
        if (!$this->payment_context_is_deposit_only() || !is_array($settings)) {
            return $settings;
        }

        $settings['capture'] = 'no';

        return $settings;
    }

    public function stripe_authorization_request($request, $order, $prepared_payment_method = null)
    {
        if ($this->order_is_deposit_only($order) && is_array($request)) {
            $request['capture'] = 'false';
        }

        return $request;
    }

    public function stripe_intent_authorization_request($request, $order, $prepared_payment_method = null)
    {
        if ($this->order_is_deposit_only($order) && is_array($request)) {
            $request['capture_method'] = 'manual';
        }

        return $request;
    }

    public function woopayments_authorization_settings($settings, $option = '')
    {
        if (!$this->payment_context_is_deposit_only() || !is_array($settings)) {
            return $settings;
        }

        $settings['manual_capture'] = 'yes';

        return $settings;
    }

    public function woopayments_authorization_request($request, $payment_information = null)
    {
        $order = null;

        if (is_a($payment_information, 'WC_Order')) {
            $order = $payment_information;
        } elseif (
            is_object($payment_information)
            && method_exists($payment_information, 'get_order')
        ) {
            $order = $payment_information->get_order();
        }

        if (
            $this->payment_context_is_deposit_only($order)
            && is_object($request)
            && method_exists($request, 'set_capture_method')
        ) {
            $request->set_capture_method(true);
        }

        return $request;
    }

    public function add_cart_item_data($data, $product_id)
    {
        if (!$this->is_deposit_product($product_id)) {
            return $data;
        }

        if (isset($_REQUEST[self::CART_KEY])) {
            $amount = $this->valid_amount(wp_unslash($_REQUEST[self::CART_KEY]));

            if ($amount > 0) {
                $data[self::CART_KEY] = $amount;
                // Makes the selected amount explicit in the cart item identity.
                $data['t_rent_deposit_unique'] = md5((string) $amount);
            }
        }

        return $data;
    }

    public function restore_cart_item($item, $values)
    {
        // Acowebs may have persisted this in an older customer session.
        unset($item['awcdp_deposit']);

        if (isset($values[self::CART_KEY])) {
            $item[self::CART_KEY] = (float) $values[self::CART_KEY];
        }

        if (isset($values['t_rent_deposit_unique'])) {
            $item['t_rent_deposit_unique'] = $values['t_rent_deposit_unique'];
        }

        return $item;
    }

    /**
     * Change price only when the cart line is the separate Depositum product.
     * RnB rental products are ignored completely.
     */
    public function set_deposit_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!$cart || !is_a($cart, 'WC_Cart')) {
            return;
        }

        $product_id = $this->product_id();
        if (!$product_id) {
            return;
        }

        foreach ($cart->get_cart() as $item) {
            if (
                empty($item['product_id'])
                || (int) $item['product_id'] !== $product_id
                || empty($item[self::CART_KEY])
            ) {
                continue;
            }

            $amount = $this->valid_amount($item[self::CART_KEY]);

            if ($amount > 0 && isset($item['data']) && is_object($item['data'])) {
                $item['data']->set_price($amount);
            }
        }
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order)
    {
        // Defensive cleanup in case another plugin re-added the metadata late.
        $item->delete_meta_data('awcdp_deposit_meta');

        if (
            empty($values[self::CART_KEY])
            || empty($values['data'])
            || !$this->is_deposit_product($values['data'])
        ) {
            return;
        }

        $amount = $this->valid_amount($values[self::CART_KEY]);
        if ($amount <= 0) {
            return;
        }

        $item->add_meta_data('Type', 'Depositum – beløpet reserveres', true);
        $item->add_meta_data('_t_rent_deposit_amount', $amount, true);
    }
}

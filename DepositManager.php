<?php
namespace REDQ_RnB;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * T-Rent standalone deposit product.
 *
 * This is deliberately separate from RnB security deposits used on rental
 * products. T-Rent accepts full payment at checkout; Acowebs partial-payment
 * handling is disabled below so it cannot split either rental or deposit orders.
 */
class DepositManager
{
    const PRODUCT_SKU = 't-rent-depositum';
    const CART_KEY    = 't_rent_deposit_amount';

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

        /*
         * T-Rent does not use Acowebs partial payments. Depositum is a normal,
         * fully-paid WooCommerce product, and rental orders must also be paid
         * in full. Keep Acowebs from changing cart totals or order statuses.
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
            $this->disable_acowebs_for_product($product_id);
            return $product_id;
        }

        $product = new \WC_Product_Simple();
        $product->set_name('Depositum');
        $product->set_slug('depositum');
        $product->set_sku(self::PRODUCT_SKU);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_regular_price('0');
        $product->set_price('0');
        $product->set_tax_status('none');
        $product->set_short_description(
            'Separat depositum for leie utenom T-Rent sin nettbooking. Velg beløp mellom 1 000 og 5 000 kr.'
        );
        $product->set_description(
            'Dette er kun betaling av depositum. Det opprettes ingen booking, ingen leiedager og ingen reservasjon av utstyr. '
            . 'Produktet kan brukes for eksempel ved leie via Hygglo, Finn eller andre manuelle avtaler.'
        );

        $product_id = (int) $product->save();
        $this->disable_acowebs_for_product($product_id);

        return $product_id;
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
        echo '<p style="margin:8px 0 0;font-size:.92em">Kun depositum. Dette påvirker ikke en booking eller leieperiode.</p>';
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
            return $passed;
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
        return $this->is_deposit_product($product) ? 'Gå til betaling' : $text;
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

        $item->add_meta_data('Type', 'Separat depositum', true);
        $item->add_meta_data('_t_rent_deposit_amount', $amount, true);
    }
}

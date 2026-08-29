<?php
namespace REDQ_RnB;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * T-Rent standalone deposit product.
 *
 * This is deliberately separate from RnB security deposits used on normal
 * rental bookings. Nothing in this class changes, removes or adds deposit
 * handling to ordinary rental products or their checkout flow.
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

        return (int) $product->save();
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

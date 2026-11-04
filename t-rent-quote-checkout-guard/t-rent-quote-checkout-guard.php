<?php
/**
 * Plugin Name: T-Rent tilbudsvakt for kassen
 * Description: Krever et gyldig og godkjent RnB-tilbud før utleieprodukter kan legges i handlekurven eller betales.
 * Version: 1.0.0
 * Author: T-Rent
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, woocommerce-rental-and-booking
 * Text Domain: t-rent-quote-checkout-guard
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('T_RENT_QUOTE_GUARD_VERSION')) {
    define('T_RENT_QUOTE_GUARD_VERSION', '1.0.0');
}

/**
 * Keep T-Rent's quote-only rental flow closed until a quote is accepted.
 *
 * RnB's public quote-to-cart Ajax action normally trusts the supplied quote ID,
 * and WooCommerce may restore a persistent cart when a customer logs in. This
 * class validates the quote at every boundary instead of trusting the button or
 * the browser session.
 */
final class T_Rent_Quote_Checkout_Guard
{
    /** @var bool */
    private $notice_added = false;

    /**
     * Register hooks after WooCommerce and RnB have loaded.
     */
    public function __construct()
    {
        add_action('wp_ajax_rnb_quote_booking_data', [$this, 'validate_quote_to_cart_request'], 0);
        add_action('wp_ajax_nopriv_rnb_quote_booking_data', [$this, 'validate_quote_to_cart_request'], 0);

        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 999, 6);
        add_action('woocommerce_check_cart_items', [$this, 'validate_cart'], 1);
        add_action('woocommerce_checkout_process', [$this, 'validate_cart'], 1);
        add_action('woocommerce_store_api_cart_errors', [$this, 'validate_store_api_cart'], 10, 2);

        add_filter('gettext', [$this, 'clarify_existing_email_message'], 20, 3);
        add_action('wp', [$this, 'maybe_hide_checkout_button'], 20);
        add_action('template_redirect', [$this, 'maybe_redirect_invalid_checkout'], 20);
    }

    /**
     * Explain that RnB's duplicate-email response is an account/login check,
     * not a failed or completed quote request.
     *
     * @param string $translation
     * @param string $text
     * @param string $domain
     * @return string
     */
    public function clarify_existing_email_message($translation, $text, $domain)
    {
        if (
            $domain === 'redq-rental'
            && $text === 'Sorry! Email already exist. Please login'
        ) {
            return 'E-postadressen er allerede registrert. Logg inn og send forespørselen på nytt.';
        }

        return $translation;
    }

    /**
     * Reject RnB's quote-to-cart Ajax request before RnB can add an unapproved
     * or already-used quote to the session.
     */
    public function validate_quote_to_cart_request()
    {
        $quote_id = isset($_POST['quote_id']) ? absint(wp_unslash($_POST['quote_id'])) : 0;
        $product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $result = $this->validate_quote($quote_id, $product_id, []);

        if ($result['valid']) {
            return;
        }

        wp_send_json(
            [
                'success' => false,
                'status_code' => 403,
                'message' => $this->public_error_message($result['reason']),
            ],
            403
        );
    }

    /**
     * Require an accepted quote for every new rental line.
     *
     * @param bool  $passed
     * @param int   $product_id
     * @param int   $quantity
     * @param int   $variation_id
     * @param array $variations
     * @param array $cart_item_data
     * @return bool
     */
    public function validate_add_to_cart(
        $passed,
        $product_id,
        $quantity,
        $variation_id = 0,
        $variations = [],
        $cart_item_data = []
    ) {
        if (!$passed || !$this->quote_is_required($product_id)) {
            return $passed;
        }

        if ($this->is_order_extension_request($cart_item_data)) {
            return $passed;
        }

        $quote_id = $this->quote_id_from_request($cart_item_data);
        $result = $this->validate_quote($quote_id, $product_id, $cart_item_data);

        if ($result['valid']) {
            return $passed;
        }

        $this->add_notice($this->public_error_message($result['reason']));
        return false;
    }

    /**
     * Validate already-restored cart lines, including WooCommerce's persistent
     * cart that is merged into the session after login.
     */
    public function validate_cart()
    {
        $invalid = $this->invalid_cart_items();

        if (empty($invalid)) {
            return;
        }

        $this->add_notice($this->cart_error_message($invalid));
    }

    /**
     * Add the same error to Cart/Checkout blocks' Store API response.
     *
     * @param WP_Error $errors
     * @param mixed    $cart
     */
    public function validate_store_api_cart($errors, $cart = null)
    {
        if (!is_wp_error($errors)) {
            return;
        }

        $invalid = $this->invalid_cart_items($cart);
        if (empty($invalid)) {
            return;
        }

        $errors->add(
            't_rent_quote_required',
            wp_strip_all_tags($this->cart_error_message($invalid))
        );
    }

    /**
     * Remove the classic cart's proceed button while a rental line is invalid.
     */
    public function maybe_hide_checkout_button()
    {
        if (!function_exists('is_cart') || !is_cart() || empty($this->invalid_cart_items())) {
            return;
        }

        remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        add_action('woocommerce_proceed_to_checkout', [$this, 'render_blocked_checkout_message'], 20);
    }

    /**
     * Explain why the cart cannot proceed in place of the checkout button.
     */
    public function render_blocked_checkout_message()
    {
        echo '<p class="woocommerce-error t-rent-quote-guard-message" role="alert">'
            . esc_html__('Send en forespørsel og vent på godkjenning før du går til kassen.', 't-rent-quote-checkout-guard')
            . '</p>';
    }

    /**
     * Do not render a usable checkout page for an invalid persistent cart.
     */
    public function maybe_redirect_invalid_checkout()
    {
        if (
            is_admin()
            || wp_doing_ajax()
            || !function_exists('is_checkout')
            || !is_checkout()
            || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay'))
            || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received'))
        ) {
            return;
        }

        $invalid = $this->invalid_cart_items();
        if (empty($invalid)) {
            return;
        }

        $this->add_notice($this->cart_error_message($invalid));
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    /**
     * Find rental cart lines whose quote is missing, pending, mismatched, used,
     * or owned by another logged-in customer.
     *
     * @param mixed $cart Optional WC_Cart instance.
     * @return array<int,array<string,mixed>>
     */
    private function invalid_cart_items($cart = null)
    {
        if ($cart === null && function_exists('WC') && WC()) {
            $cart = WC()->cart;
        }

        if (
            (!is_object($cart) || !method_exists($cart, 'get_cart'))
            && function_exists('WC')
            && WC()
        ) {
            $cart = WC()->cart;
        }

        if (!is_object($cart) || !method_exists($cart, 'get_cart')) {
            return [];
        }

        $invalid = [];
        foreach ((array) $cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = !empty($cart_item['product_id'])
                ? absint($cart_item['product_id'])
                : 0;

            if (!$product_id || !$this->quote_is_required($product_id)) {
                continue;
            }

            if ($this->is_order_extension_request($cart_item)) {
                continue;
            }

            $quote_id = $this->quote_id_from_cart_item($cart_item);
            $result = $this->validate_quote($quote_id, $product_id, $cart_item);
            if ($result['valid']) {
                continue;
            }

            $product = wc_get_product($product_id);
            $invalid[] = [
                'cart_item_key' => $cart_item_key,
                'product_id' => $product_id,
                'product_name' => $product ? $product->get_name() : '',
                'quote_id' => $quote_id,
                'reason' => $result['reason'],
            ];
        }

        return $invalid;
    }

    /**
     * Validate a quote without trusting request or session state.
     *
     * @param int   $quote_id
     * @param int   $product_id
     * @param array $cart_item_data
     * @return array{valid:bool,reason:string}
     */
    private function validate_quote($quote_id, $product_id, $cart_item_data)
    {
        $quote_id = absint($quote_id);
        $product_id = absint($product_id);

        if (!$quote_id) {
            return ['valid' => false, 'reason' => 'missing'];
        }

        if (get_post_type($quote_id) !== 'request_quote') {
            return ['valid' => false, 'reason' => 'invalid'];
        }

        if (get_post_status($quote_id) !== 'quote-accepted') {
            return ['valid' => false, 'reason' => 'not_accepted'];
        }

        if (get_post_meta($quote_id, '_rnb_rfq_order_id', true)) {
            return ['valid' => false, 'reason' => 'used'];
        }

        if (!$this->quote_allows_product($quote_id, $product_id, $cart_item_data)) {
            return ['valid' => false, 'reason' => 'product_mismatch'];
        }

        if (!$this->current_customer_can_use_quote($quote_id)) {
            return ['valid' => false, 'reason' => 'wrong_customer'];
        }

        return ['valid' => true, 'reason' => ''];
    }

    /**
     * Main quote products must match exactly. Linked/add-on products are only
     * accepted when they were saved in the quote itself.
     */
    private function quote_allows_product($quote_id, $product_id, $cart_item_data)
    {
        $main_product_id = absint(get_post_meta($quote_id, 'add-to-cart', true));
        if (!$main_product_id) {
            $main_product_id = absint(get_post_meta($quote_id, '_product_id', true));
        }

        if ($main_product_id === (int) $product_id) {
            return true;
        }

        if (!$this->looks_like_linked_cart_item($cart_item_data)) {
            return false;
        }

        return $this->quote_contains_linked_product($quote_id, $product_id);
    }

    /**
     * Recognise both the existing add-on implementation and the standalone
     * linked-bookings plugin without trusting a generic quote_id field.
     */
    private function looks_like_linked_cart_item($cart_item_data)
    {
        foreach (['trent_addon_product', 'trent_linked_product', '_trent_linked_product'] as $key) {
            if (!empty($cart_item_data[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check the quote's saved add-on snapshot and serialized request fields.
     */
    private function quote_contains_linked_product($quote_id, $product_id)
    {
        foreach (
            [
                '_trent_addon_snapshot',
                '_trent_linked_booking_snapshot',
                '_trent_linked_booking_products',
                '_trent_linked_products',
            ] as $meta_key
        ) {
            $value = get_post_meta($quote_id, $meta_key, true);
            if ($this->structured_value_contains_product($value, $product_id)) {
                return true;
            }
        }

        foreach (['unformatted_order_quote_meta', 'order_quote_meta'] as $meta_key) {
            $rows = $this->decode_quote_meta(get_post_meta($quote_id, $meta_key, true));

            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['name'])) {
                    continue;
                }

                $name = sanitize_key(str_replace('[]', '', (string) $row['name']));
                if (strpos($name, 'addon') === false && strpos($name, 'linked') === false) {
                    continue;
                }

                if ($this->structured_value_contains_product($row['value'] ?? null, $product_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A logged-in customer may only use their own accepted quote. Guest quote
     * links remain supported because T-Rent deliberately enables guest checkout.
     */
    private function current_customer_can_use_quote($quote_id)
    {
        if (!is_user_logged_in()) {
            return true;
        }

        $user_id = get_current_user_id();
        $quote_user_id = absint(get_post_meta($quote_id, '_quote_user', true));
        $author_id = absint(get_post_field('post_author', $quote_id));

        if ($user_id && ($user_id === $quote_user_id || $user_id === $author_id)) {
            return true;
        }

        $user = wp_get_current_user();
        $allowed_emails = array_filter(
            [
                isset($user->user_email) ? sanitize_email($user->user_email) : '',
                sanitize_email((string) get_user_meta($user_id, 'billing_email', true)),
            ]
        );
        $quote_email = $this->quote_customer_email($quote_id);

        return $quote_email !== '' && in_array($quote_email, $allowed_emails, true);
    }

    /**
     * Read the customer email from RnB's stored quote form.
     */
    private function quote_customer_email($quote_id)
    {
        foreach (['order_quote_meta', 'unformatted_order_quote_meta'] as $meta_key) {
            $rows = $this->decode_quote_meta(get_post_meta($quote_id, $meta_key, true));
            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['name'])) {
                    continue;
                }

                $name = sanitize_key(str_replace(['-', '[]'], ['_', ''], (string) $row['name']));
                if (in_array($name, ['quote_email', 'billing_email', 'email'], true)) {
                    return sanitize_email((string) ($row['value'] ?? ''));
                }
            }
        }

        return '';
    }

    /**
     * @param mixed $raw
     * @return array
     */
    private function decode_quote_meta($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Recursively compare IDs while keeping the search bounded to saved quote
     * structures selected by the caller.
     *
     * @param mixed $value
     * @param int   $product_id
     */
    private function structured_value_contains_product($value, $product_id)
    {
        if (is_string($value)) {
            $maybe_unserialized = maybe_unserialize($value);
            if ($maybe_unserialized !== $value) {
                return $this->structured_value_contains_product($maybe_unserialized, $product_id);
            }

            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->structured_value_contains_product($decoded, $product_id);
            }
        }

        if (is_scalar($value)) {
            return (string) absint($value) === (string) absint($product_id);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $child) {
            if ((string) absint($key) === (string) absint($product_id) && absint($key) > 0) {
                return true;
            }

            if ($this->structured_value_contains_product($child, $product_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $cart_item_data
     * @return int
     */
    private function quote_id_from_request($cart_item_data)
    {
        $quote_id = $this->quote_id_from_cart_item($cart_item_data);
        if ($quote_id) {
            return $quote_id;
        }

        return isset($_POST['quote_id']) ? absint(wp_unslash($_POST['quote_id'])) : 0;
    }

    /**
     * @param array $cart_item
     * @return int
     */
    private function quote_id_from_cart_item($cart_item)
    {
        if (!empty($cart_item['rental_data']['quote_id'])) {
            return absint($cart_item['rental_data']['quote_id']);
        }

        if (!empty($cart_item['quote_id'])) {
            return absint($cart_item['quote_id']);
        }

        return 0;
    }

    /**
     * RnB's explicit extension flow belongs to an existing order and must not
     * be mistaken for a new direct rental.
     */
    private function is_order_extension_request($data)
    {
        $order_type = '';

        if (!empty($data['order_type'])) {
            $order_type = $data['order_type'];
        } elseif (!empty($data['rental_data']['posted_data']['order_type'])) {
            $order_type = $data['rental_data']['posted_data']['order_type'];
        } elseif (!empty($_POST['order_type'])) {
            $order_type = wp_unslash($_POST['order_type']);
        }

        return sanitize_key((string) $order_type) === 'extend_order';
    }

    /**
     * @param int $product_id
     * @return bool
     */
    private function quote_is_required($product_id)
    {
        $product = wc_get_product($product_id);
        $required = $product && $product->is_type('redq_rental');

        return (bool) apply_filters(
            't_rent_quote_checkout_guard_required',
            $required,
            $product_id,
            $product
        );
    }

    /**
     * @param string $reason
     * @return string
     */
    private function public_error_message($reason)
    {
        if ($reason === 'wrong_customer') {
            return esc_html__(
                'Dette tilbudet tilhører en annen kundekonto. Logg inn med riktig konto eller send en ny forespørsel.',
                't-rent-quote-checkout-guard'
            );
        }

        if ($reason === 'used') {
            return esc_html__(
                'Dette tilbudet er allerede brukt. Send en ny forespørsel hvis du vil bestille på nytt.',
                't-rent-quote-checkout-guard'
            );
        }

        return esc_html__(
            'Utleieproduktet kan ikke betales direkte. Send først en forespørsel og vent til tilbudet er godkjent.',
            't-rent-quote-checkout-guard'
        );
    }

    /**
     * @param array $invalid
     * @return string
     */
    private function cart_error_message($invalid)
    {
        $names = array_values(array_unique(array_filter(array_column($invalid, 'product_name'))));

        if (!empty($names)) {
            return sprintf(
                /* translators: %s is a comma-separated product-name list. */
                esc_html__('Kan ikke gå til kassen: %s mangler et gyldig, godkjent tilbud. Fjern varen og send en forespørsel.', 't-rent-quote-checkout-guard'),
                esc_html(implode(', ', $names))
            );
        }

        return $this->public_error_message('invalid');
    }

    /**
     * @param string $message
     */
    private function add_notice($message)
    {
        if ($this->notice_added || !function_exists('wc_add_notice')) {
            return;
        }

        if (!function_exists('wc_has_notice') || !wc_has_notice($message, 'error')) {
            wc_add_notice($message, 'error');
        }

        $this->notice_added = true;
    }
}

add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    new T_Rent_Quote_Checkout_Guard();
}, 40);

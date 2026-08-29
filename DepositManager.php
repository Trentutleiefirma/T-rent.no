<?php
namespace REDQ_RnB;

if (!defined('ABSPATH')) exit;

class DepositManager
{
    const PRODUCT_SKU = 't-rent-depositum';
    const NONCE_ACTION = 't_rent_depositum';
    const CART_KEY = 't_rent_deposit_amount';

    public function __construct()
    {
        add_action('init', [$this, 'ensure_deposit_product'], 20);

        // Keep RnB's own security-deposit fee separate from T-Rent's deposit product.
        add_action('woocommerce_cart_calculate_fees', [$this, 'remove_rnb_deposit_fee'], 1);

        // Optional deposit selector when a customer is already in cart/checkout.
        add_action('woocommerce_review_order_before_order_total', [$this, 'render_deposit_selector']);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'render_deposit_selector']);
        add_action('wp_footer', [$this, 'render_deposit_script']);
        add_action('wp_ajax_t_rent_update_deposit', [$this, 'ajax_update_deposit']);
        add_action('wp_ajax_nopriv_t_rent_update_deposit', [$this, 'ajax_update_deposit']);

        // Standalone deposit product: useful for Hygglo/Finn/manual rentals.
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_product_amount_selector'], 5);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_deposit_add_to_cart'], 99, 3);
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'single_add_to_cart_text'], 20, 2);
        add_filter('woocommerce_get_price_html', [$this, 'deposit_price_html'], 20, 2);
        add_filter('woocommerce_add_to_cart_redirect', [$this, 'redirect_deposit_to_checkout'], 99);

        // Cart/order handling for the variable deposit amount.
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 99, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item'], 99, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_deposit_price'], 99);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 20, 4);
    }

    public function remove_rnb_deposit_fee()
    {
        global $wp_filter;
        if (empty($wp_filter['woocommerce_cart_calculate_fees']->callbacks)) return;

        foreach ($wp_filter['woocommerce_cart_calculate_fees']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function = isset($callback['function']) ? $callback['function'] : null;
                if (
                    is_array($function)
                    && isset($function[0], $function[1])
                    && is_object($function[0])
                    && $function[1] === 'add_deposit_total_as_fee'
                    && $function[0] instanceof CartHandler
                ) {
                    remove_action('woocommerce_cart_calculate_fees', $function, $priority);
                }
            }
        }
    }

    /**
     * Creates the standalone WooCommerce deposit product once.
     * It is hidden from the catalogue, but its direct product URL works.
     */
    public function ensure_deposit_product()
    {
        $product_id = (int) wc_get_product_id_by_sku(self::PRODUCT_SKU);
        if ($product_id) return $product_id;

        $product = new \WC_Product_Simple();
        $product->set_name('Depositum');
        $product->set_slug('depositum');
        $product->set_sku(self::PRODUCT_SKU);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_price(0);
        $product->set_regular_price(0);
        $product->set_tax_status('none');
        $product->set_short_description('Betal depositum for leie hos T-Rent. Velg beløp mellom 1 000 og 5 000 kr.');
        $product->set_description(
            'Dette produktet brukes kun til depositum og er ikke en leiedag. '
            . 'Det er ikke koblet til RnB-bookingen og kan brukes ved leie via Hygglo, Finn eller manuelle avtaler. '
            . 'Depositum håndteres som en egen ordrelinje slik at det kan tilbakebetales separat.'
        );

        return $product->save();
    }

    private function product_id()
    {
        return (int) wc_get_product_id_by_sku(self::PRODUCT_SKU);
    }

    private function is_deposit_product($product_or_id)
    {
        $id = is_object($product_or_id) && method_exists($product_or_id, 'get_id')
            ? (int) $product_or_id->get_id()
            : (int) $product_or_id;

        return $id > 0 && $id === $this->product_id();
    }

    private function valid_amount($amount)
    {
        $amount = (float) wc_format_decimal($amount);
        return ($amount >= 1000 && $amount <= 5000) ? round($amount, 2) : 0;
    }

    /**
     * Selector shown on the standalone Depositum product page.
     * A shareable URL may preselect the amount with ?depositum=3000.
     */
    public function render_product_amount_selector()
    {
        global $product;
        if (!$product || !$this->is_deposit_product($product)) return;

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
        for ($i = 1000; $i <= 5000; $i += 500) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($selected, $i, false) . '>'
                . esc_html(wp_strip_all_tags(wc_price($i)))
                . '</option>';
        }
        echo '</select>';
        echo '<p style="margin:8px 0 0;font-size:.92em">Kun depositum – ingen leiedager eller booking opprettes. Beløpet ligger som en separat ordrelinje og kan refunderes separat.</p>';
        echo '</div>';
    }

    public function validate_deposit_add_to_cart($passed, $product_id, $quantity)
    {
        if (!$this->is_deposit_product($product_id)) return $passed;

        $amount = isset($_REQUEST[self::CART_KEY])
            ? $this->valid_amount(wp_unslash($_REQUEST[self::CART_KEY]))
            : 0;

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
        if (!$this->is_deposit_product($product)) return $price_html;
        return '<span class="price">1 000–5 000 kr</span>';
    }

    /**
     * When the standalone product is used, skip the cart and go straight to checkout.
     */
    public function redirect_deposit_to_checkout($url)
    {
        $requested_product_id = isset($_REQUEST['add-to-cart']) ? absint($_REQUEST['add-to-cart']) : 0;
        if ($requested_product_id && $this->is_deposit_product($requested_product_id)) {
            return wc_get_checkout_url();
        }
        return $url;
    }

    public function render_deposit_selector()
    {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return;

        $amount = 0;
        foreach (WC()->cart->get_cart() as $item) {
            if (!empty($item[self::CART_KEY])) {
                $amount = (float) $item[self::CART_KEY];
                break;
            }
        }

        echo '<tr class="t-rent-deposit-selector"><th colspan="3">Depositum</th><td><select id="t-rent-deposit-amount">';
        echo '<option value="0">Ingen depositum</option>';
        for ($i = 1000; $i <= 5000; $i += 500) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($amount, $i, false) . '>'
                . esc_html(wp_strip_all_tags(wc_price($i)))
                . '</option>';
        }
        echo '</select><br><small>Valgfritt – 1 000–5 000 kr</small></td></tr>';
    }

    public function render_deposit_script()
    {
        if (!is_cart() && !is_checkout()) return;
        ?>
        <script>
        jQuery(function($){
            $(document.body).on('change','#t-rent-deposit-amount',function(){
                $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>',{
                    action:'t_rent_update_deposit',
                    nonce:'<?php echo esc_js(wp_create_nonce(self::NONCE_ACTION)); ?>',
                    amount:$(this).val()
                }).done(function(){
                    if($('form.checkout').length){
                        $(document.body).trigger('update_checkout');
                    } else {
                        window.location.reload();
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_update_deposit()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!function_exists('WC') || !WC()->cart) wp_send_json_error([], 400);

        $amount = $this->valid_amount(isset($_POST['amount']) ? wc_clean(wp_unslash($_POST['amount'])) : 0);
        $product_id = $this->product_id() ?: $this->ensure_deposit_product();

        foreach (WC()->cart->get_cart() as $key => $item) {
            if (!empty($item[self::CART_KEY])) {
                WC()->cart->remove_cart_item($key);
            }
        }

        if ($amount > 0 && $product_id) {
            WC()->cart->add_to_cart($product_id, 1, 0, [], [self::CART_KEY => $amount]);
        }

        WC()->cart->calculate_totals();
        wp_send_json_success(['amount' => $amount]);
    }

    public function add_cart_item_data($data, $product_id)
    {
        if (!$this->is_deposit_product($product_id)) return $data;

        if (isset($_REQUEST[self::CART_KEY])) {
            $amount = $this->valid_amount(wp_unslash($_REQUEST[self::CART_KEY]));
            if ($amount > 0) {
                $data[self::CART_KEY] = $amount;
            }
        }

        return $data;
    }

    public function restore_cart_item($item, $values)
    {
        if (isset($values[self::CART_KEY])) {
            $item[self::CART_KEY] = (float) $values[self::CART_KEY];
        }
        return $item;
    }

    public function set_deposit_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $product_id = $this->product_id();
        if (!$product_id || !$cart || !is_a($cart, 'WC_Cart')) return;

        foreach ($cart->get_cart() as $item) {
            if ((int) $item['product_id'] !== $product_id || empty($item[self::CART_KEY])) continue;

            $amount = $this->valid_amount($item[self::CART_KEY]);
            if ($amount > 0) {
                $item['data']->set_price($amount);
            }
        }
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order)
    {
        if (empty($values[self::CART_KEY]) || !$this->is_deposit_product($values['data'])) return;

        $amount = $this->valid_amount($values[self::CART_KEY]);
        if ($amount <= 0) return;

        $item->add_meta_data('Depositum – separat/refunderbart', wp_strip_all_tags(wc_price($amount)), true);
        $item->add_meta_data('_t_rent_deposit_amount', $amount, true);
    }
}

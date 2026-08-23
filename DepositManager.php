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
        add_action('woocommerce_review_order_before_order_total', [$this, 'render_deposit_selector']);
        add_action('woocommerce_cart_totals_before_order_total', [$this, 'render_deposit_selector']);
        add_action('wp_footer', [$this, 'render_deposit_script']);
        add_action('wp_ajax_t_rent_update_deposit', [$this, 'ajax_update_deposit']);
        add_action('wp_ajax_nopriv_t_rent_update_deposit', [$this, 'ajax_update_deposit']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 99, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item'], 99, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_deposit_price'], 99);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 20, 4);
    }

    public function ensure_deposit_product()
    {
        $product_id = (int) wc_get_product_id_by_sku(self::PRODUCT_SKU);
        if ($product_id) return $product_id;
        $product = new \WC_Product_Simple();
        $product->set_name('Depositum');
        $product->set_sku(self::PRODUCT_SKU);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_price(0);
        $product->set_regular_price(0);
        $product->set_tax_status('none');
        $product->set_description('Refunderbart depositum for utleie. Ikke en leiedag og ikke koblet til RnB-bookingen.');
        return $product->save();
    }

    private function product_id()
    {
        return (int) wc_get_product_id_by_sku(self::PRODUCT_SKU);
    }

    private function valid_amount($amount)
    {
        $amount = (float) $amount;
        return ($amount >= 1000 && $amount <= 5000) ? round($amount, 2) : 0;
    }

    public function render_deposit_selector()
    {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return;
        $amount = 0;
        foreach (WC()->cart->get_cart() as $item) {
            if (!empty($item[self::CART_KEY])) { $amount = (float) $item[self::CART_KEY]; break; }
        }
        echo '<tr class="t-rent-deposit-selector"><th colspan="3">Depositum</th><td><select id="t-rent-deposit-amount">';
        echo '<option value="0">Ingen depositum</option>';
        for ($i = 1000; $i <= 5000; $i += 500) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($amount, $i, false) . '>' . esc_html(wp_strip_all_tags(wc_price($i))) . '</option>';
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
                    if($('form.checkout').length){$(document.body).trigger('update_checkout');}
                    else{window.location.reload();}
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
            if (!empty($item[self::CART_KEY])) WC()->cart->remove_cart_item($key);
        }
        if ($amount > 0 && $product_id) WC()->cart->add_to_cart($product_id, 1, 0, [], [self::CART_KEY => $amount]);
        WC()->cart->calculate_totals();
        wp_send_json_success(['amount' => $amount]);
    }

    public function add_cart_item_data($data, $product_id)
    {
        if ((int)$product_id !== $this->product_id()) return $data;
        if (isset($_REQUEST[self::CART_KEY])) {
            $amount = $this->valid_amount(wp_unslash($_REQUEST[self::CART_KEY]));
            if ($amount > 0) $data[self::CART_KEY] = $amount;
        }
        return $data;
    }

    public function restore_cart_item($item, $values)
    {
        if (isset($values[self::CART_KEY])) $item[self::CART_KEY] = (float)$values[self::CART_KEY];
        return $item;
    }

    public function set_deposit_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $product_id = $this->product_id();
        if (!$product_id || !$cart || !is_a($cart, 'WC_Cart')) return;
        foreach ($cart->get_cart() as $item) {
            if ((int)$item['product_id'] !== $product_id || empty($item[self::CART_KEY])) continue;
            $amount = $this->valid_amount($item[self::CART_KEY]);
            if ($amount > 0) $item['data']->set_price($amount);
        }
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order)
    {
        if (empty($values[self::CART_KEY]) || (int)$values['data']->get_id() !== $this->product_id()) return;
        $amount = $this->valid_amount($values[self::CART_KEY]);
        if ($amount <= 0) return;
        $item->add_meta_data('Depositum – separat/refunderbart', wc_price($amount), true);
        $item->add_meta_data('_t_rent_deposit_amount', $amount, true);
    }
}

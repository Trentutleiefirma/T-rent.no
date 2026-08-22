<?php

namespace REDQ_RnB;

/**
 * Modify existing hook
 */
class Hook
{
    public function __construct()
    {
        add_filter('woocommerce_is_purchasable', [$this, 'is_rentable'], 10, 2);
        add_filter('woocommerce_get_price_html', [$this, 'product_price_html'], 10, 2);
        add_filter('rnb_prepared_price_summary', [$this, 'prepare_price_summary'], 10, 3);
        add_filter('rnb_prepared_cart_item_data', [$this, 'prepared_cart_item_data'], 10, 3);
        add_filter('rnb_prepared_order_item_data', [$this, 'prepared_cart_item_data'], 10, 3);
    }

    /**
     * Make rental product rentable even price is 0
     *
     * @param boolean $purchasable
     * @param object $product
     * @return boolean
     */
    public function is_rentable($purchasable, $product)
    {
        $product_id = $product->get_id();
        $product_inventory = rnb_get_product_inventory_id($product_id);

        if (!is_rental_product($product_id)) {
            return $purchasable;
        }

        if (empty($product_inventory)) {
            return false;
        }

        return true;
    }

    /**
     * product_price_html
     *
     * @param mixed $price_html
     * @param mixed $product
     *
     * @return string
     */
    public function product_price_html($price_html, $product)
    {
        $product_id = $product->get_id();
        $product_type = wc_get_product($product_id)->get_type();

        if ($product_type !== 'redq_rental') {
            return $price_html;
        }

        $inventory = rnb_get_product_inventory_id($product_id);
        $result = rnb_get_product_price($product_id);

        $price_limit = $result['price_limit'];
        $price = $result['price'];
        $prefix = $result['prefix'];
        $suffix = $result['suffix'];

        $range = $price_limit['min'] !== $price_limit['max'] && $result['show_range'] ? wc_price($price_limit['min']) . ' - ' . wc_price($price_limit['max']) : wc_price($price_limit['min']);
        $css_class = apply_filters('rnb_product_price_class', "rnb_price_unit_$product_id", $product);

        if (count($inventory)) {
            $price_html = '<span class="amount ' . $css_class . '"> ' . $prefix . '' . $range . '' . $suffix . '</span>';
            update_post_meta($product_id, '_price', $price);
        } else {
            $price_html = sprintf(__('Inventory Missing', 'redq-rental'));
        }

        $price_html = $price_html . $product->get_price_suffix();

        update_post_meta($product_id, 'rnb_price_html', $price_html);

        return $price_html;
    }

    public function prepare_price_summary($item_data, $product_id, $posted_data)
    {
        $summary = [];
        $skipped_keys = ['deposit_free_total', 'extras_total'];
        $if_keys_exist = ['discount_total', 'pickup_location', 'return_location', 'category', 'resource', 'adult', 'child' ];

        if (empty($item_data['deposit'])) {
            unset($item_data['deposit_free_total']);
        }

        foreach ($item_data as $key => $data) {
            if (!isset($data['summary']) || !$data['summary']) {
                continue;
            }

            if (in_array($key, $if_keys_exist)) {
                $keyToRemove = array_search('deposit_free_total', $skipped_keys);
                if ($keyToRemove !== false) {
                    unset($skipped_keys[$keyToRemove]);
                }
            }

            if (empty($data['summary_key']) || in_array($key, $skipped_keys)) {
                continue;
            }

            if ($key === 'discount_total' && empty($data['total'])) {
                continue;
            }

            $cost = wc_price($data['total']);
            if ('discount_total' == $key) {
                $cost = '- ' . $cost;
            }
          
            $summary[$key] = [
                'text'   => $data['summary_key'],
                'amount' => $data['total'],
                'cost'   => $cost,
            ];
        }

        return apply_filters('rnb_breakdown_summary', $summary, $item_data, $product_id, $posted_data);
    }

    public function prepared_cart_item_data($rental_item, $product_id = null, $cart_item = [])
    {
        $results = [];
        $skipped_keys = ['deposit_free_total', 'extras_total', 'total', 'total_hour'];
        if (empty($rental_item)) {
            return $results;
        }
        if(isset($rental_item['discount_total']['total']) && !$rental_item['discount_total']['total']){
            $skipped_keys[] = 'discount_total';
        }

        foreach ($rental_item as $key => $data) {
            if (in_array($key, $skipped_keys)) {
                continue;
            }

            $results[$key] = $data;
        }

        return $results;
    }
}

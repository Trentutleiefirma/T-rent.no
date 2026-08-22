<?php

namespace REDQ_RnB;

/**
 * Display Tax on single product page
 */
class TaxDisplay
{
    public function __construct()
    {
        add_filter('rnb_prepared_price_summary', [$this, 'rnb_epo_display_data_on_product_page_display_tax'], 10, 3);
    }

    function rnb_epo_display_data_on_product_page_display_tax($item_data, $product_id, $posted_data)
    {
        if ('yes' === get_option('woocommerce_calc_taxes')) {
            // Get the product object
            $product = wc_get_product($product_id);
            $epo_price = 0;
            if (function_exists('rnb_epo_return_total')) {
                $epo_price = rnb_epo_return_total($item_data, $product_id, $posted_data);
            }

            $prices_include_tax = get_option('woocommerce_prices_include_tax') === 'yes';
            $subtotal = (float) $item_data['deposit_free_total']['total'] + (float) $epo_price;

            // Get tax rates
            $tax_rates = \WC_Tax::get_rates($product->get_tax_class());
            $tax_rate = reset($tax_rates);

            if ($prices_include_tax) {
                // If prices include tax, we need to back-calculate the tax amount
                $tax_amount = \WC_Tax::calc_inclusive_tax($subtotal, $tax_rates);
                $total_tax = array_sum($tax_amount);
                // Remove tax from subtotal
                $subtotal = $subtotal - $total_tax;
            } else {
                // If prices exclude tax, calculate tax normally
                $taxes = \WC_Tax::calc_tax($subtotal, $tax_rates, false);
                $total_tax = array_sum($taxes);
            }

            $tax_label = isset($tax_rate['label']) ? $tax_rate['label'] : esc_html__('Tax', 'woocommerce');

            // Add tax information to the item data
            $tax_data = [
                'type' => 'single',
                'key' => $tax_label,
                'summary' => 1,
                'summary_key' => $tax_label,
                'total' => $total_tax,
                'data' => [
                    'name' => $tax_label,
                    'cost' => $total_tax
                ]
            ];

            // Insert tax information before the total
            $new_item_data = [];
            foreach ($item_data as $key => $value) {
                if ($key === 'total') {
                    $new_item_data['tax'] = $tax_data;
                }
                $new_item_data[$key] = $value;
            }

            // Update the total
            if (!$prices_include_tax) {
                $new_item_data['total']['total'] += $total_tax;
                $new_item_data['total']['data']['cost'] += $total_tax;
            }

            return $new_item_data;
        }
        return $item_data;
    }
}

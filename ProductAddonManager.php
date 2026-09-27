<?php

namespace REDQ_RnB;

use REDQ_RnB\Traits\Error_Trait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Allow customers to add extra rental products to the same booking.
 *
 * The main RnB product is booked normally. Selected extra rental products are
 * then added to the same WooCommerce cart with the same pickup/return dates.
 * Each extra product still runs through RnB availability validation and its own
 * rental price calculation.
 */
class ProductAddonManager extends Booking_Manager
{
    use Error_Trait;

    private static $adding_addons = false;

    public function __construct()
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_addon_selector'], 6);
        add_action('woocommerce_add_to_cart', [$this, 'add_selected_products'], 30, 6);
    }

    /**
     * Show available rental products as optional additions on a rental product.
     */
    public function render_addon_selector()
    {
        global $product;

        if (!$product || !$product->is_type('redq_rental')) {
            return;
        }

        $products = $this->get_available_addon_products((int) $product->get_id());

        if (empty($products)) {
            return;
        }

        wp_nonce_field('trent_addon_booking', 'trent_addon_nonce');

        echo '<div class="trent-booking-addons" style="margin:18px 0;padding:14px;border:1px solid #e5e5e5;border-radius:6px;">';
        echo '<details>';
        echo '<summary style="cursor:pointer;font-weight:600;">' . esc_html__('Legg til flere produkter', 'redq-rental') . '</summary>';
        echo '<p style="margin:10px 0 12px;">' . esc_html__('Valgte produkter får samme leiedatoer automatisk. Pris og tilgjengelighet beregnes separat for hvert produkt.', 'redq-rental') . '</p>';

        foreach ($products as $addon_product) {
            $addon_id = (int) $addon_product->get_id();

            echo '<label style="display:flex;align-items:flex-start;gap:8px;margin:8px 0;">';
            echo '<input type="checkbox" name="trent_addon_products[]" value="' . esc_attr($addon_id) . '" style="margin-top:4px;">';
            echo '<span>' . esc_html($addon_product->get_name()) . '</span>';
            echo '</label>';
        }

        echo '</details>';
        echo '</div>';
    }

    /**
     * Add selected extra products after the main rental product has been added.
     */
    public function add_selected_products($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (self::$adding_addons) {
            return;
        }

        if (empty($_POST['trent_addon_products']) || !is_array($_POST['trent_addon_products'])) {
            return;
        }

        if (
            empty($_POST['trent_addon_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['trent_addon_nonce'])),
                'trent_addon_booking'
            )
        ) {
            return;
        }

        $main_product = wc_get_product($product_id);
        if (!$main_product || !$main_product->is_type('redq_rental')) {
            return;
        }

        $selected_ids = array_values(array_unique(array_filter(array_map(
            'absint',
            wp_unslash($_POST['trent_addon_products'])
        ))));

        if (empty($selected_ids)) {
            return;
        }

        $original_post = $_POST;
        $added_names = [];
        $failed_names = [];

        self::$adding_addons = true;

        try {
            foreach ($selected_ids as $addon_id) {
                if ($addon_id === (int) $product_id) {
                    continue;
                }

                $addon_product = wc_get_product($addon_id);

                if (
                    !$addon_product
                    || !$addon_product->is_type('redq_rental')
                    || $addon_product->get_status() !== 'publish'
                    || $addon_product->get_catalog_visibility() === 'hidden'
                ) {
                    continue;
                }

                $addon_form = $this->build_addon_form($original_post, $addon_id);

                if (is_wp_error($addon_form)) {
                    $failed_names[] = $addon_product->get_name();
                    continue;
                }

                // CartHandler reads the current request when it validates and
                // creates rental_data, so temporarily present the extra product
                // as the product being booked.
                $_POST = $addon_form;

                $added_key = WC()->cart->add_to_cart($addon_id, 1);

                // Always restore the customer's original booking request before
                // processing the next product or returning to WooCommerce.
                $_POST = $original_post;

                if ($added_key) {
                    $added_names[] = $addon_product->get_name();
                } else {
                    $failed_names[] = $addon_product->get_name();
                }
            }
        } finally {
            $_POST = $original_post;
            self::$adding_addons = false;
        }

        if (!empty($added_names)) {
            wc_add_notice(
                sprintf(
                    esc_html__('Ekstra produkt lagt til med samme leiedatoer: %s', 'redq-rental'),
                    esc_html(implode(', ', $added_names))
                ),
                'success'
            );
        }

        if (!empty($failed_names)) {
            wc_add_notice(
                sprintf(
                    esc_html__('Kunne ikke legge til følgende produkt på valgte datoer: %s', 'redq-rental'),
                    esc_html(implode(', ', $failed_names))
                ),
                'error'
            );
        }
    }

    /**
     * Build the minimal RnB request required for an extra rental product.
     *
     * Product-specific extras/resources are intentionally not copied from the
     * main product. Delivery/distance data is also not copied, so one booking
     * does not accidentally charge the same delivery cost once per product.
     */
    private function build_addon_form(array $source, $addon_id)
    {
        if (!function_exists('rnb_get_product_inventory_id')) {
            return new \WP_Error('missing_inventory_function');
        }

        $form = [
            'add-to-cart'       => (int) $addon_id,
            'order_type'        => 'new_order',
            'inventory_quantity'=> 1,
        ];

        foreach ([
            'pickup_date',
            'pickup_time',
            'dropoff_date',
            'dropoff_time',
            'return_date',
            'return_time',
        ] as $key) {
            if (isset($source[$key]) && $source[$key] !== '') {
                $form[$key] = is_scalar($source[$key])
                    ? sanitize_text_field(wp_unslash((string) $source[$key]))
                    : $source[$key];
            }
        }

        if (empty($form['pickup_date'])) {
            return new \WP_Error('missing_pickup_date');
        }

        if (empty($form['dropoff_date']) && !empty($form['return_date'])) {
            $form['dropoff_date'] = $form['return_date'];
        }

        if (empty($form['return_date']) && !empty($form['dropoff_date'])) {
            $form['return_date'] = $form['dropoff_date'];
        }

        if (empty($form['dropoff_date']) && empty($form['return_date'])) {
            $form['dropoff_date'] = $form['pickup_date'];
            $form['return_date'] = $form['pickup_date'];
        }

        $inventory_ids = rnb_get_product_inventory_id($addon_id);
        if (empty($inventory_ids) || !is_array($inventory_ids)) {
            return new \WP_Error('missing_inventory');
        }

        foreach ($inventory_ids as $inventory_id) {
            $candidate = $form;
            $candidate['booking_inventory'] = (int) $inventory_id;

            $required_deposits = $this->get_required_deposits((int) $inventory_id);
            if (!empty($required_deposits)) {
                $candidate['security_deposites'] = $required_deposits;
            }

            $normalized = $this->rearrange_form_data($candidate);
            if (!is_array($normalized)) {
                continue;
            }

            $errors = $this->handle_form($normalized);
            if (empty($errors)) {
                return $normalized;
            }
        }

        return new \WP_Error('not_available');
    }

    /**
     * RnB marks non-clickable security deposits as mandatory.
     */
    private function get_required_deposits($inventory_id)
    {
        $required = [];
        $deposits = get_the_terms($inventory_id, 'deposite');

        if (empty($deposits) || is_wp_error($deposits)) {
            return $required;
        }

        foreach ($deposits as $deposit) {
            $clickable = get_term_meta(
                $deposit->term_id,
                'inventory_sd_price_clickable_term_meta',
                true
            );

            if ($clickable === 'no') {
                $required[] = (int) $deposit->term_id;
            }
        }

        return $required;
    }

    /**
     * Rental products that can be selected as additions.
     */
    private function get_available_addon_products($exclude_product_id)
    {
        $products = wc_get_products([
            'status'  => 'publish',
            'limit'   => -1,
            'orderby' => 'name',
            'order'   => 'ASC',
            'return'  => 'objects',
        ]);

        $results = [];

        foreach ($products as $candidate) {
            if (
                !$candidate
                || (int) $candidate->get_id() === (int) $exclude_product_id
                || !$candidate->is_type('redq_rental')
                || $candidate->get_catalog_visibility() === 'hidden'
            ) {
                continue;
            }

            $inventory_ids = function_exists('rnb_get_product_inventory_id')
                ? rnb_get_product_inventory_id($candidate->get_id())
                : [];

            if (empty($inventory_ids)) {
                continue;
            }

            $results[] = $candidate;
        }

        return $results;
    }
}

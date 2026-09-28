<?php

namespace REDQ_RnB;

use REDQ_RnB\Traits\Error_Trait;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * T-Rent: add extra rental products to the same RnB booking/quote.
 *
 * The selector is rendered inside RnB's own booking-button flow, so it also
 * appears when a product only uses "Forespørsel" and has no direct Book button.
 *
 * Selected product IDs are part of the normal RnB quote form. When an accepted
 * quote is later converted to the cart, the extra products are added with the
 * same pickup/return dates, but with their own inventory and price calculation.
 */
class ProductAddonManager extends Booking_Manager
{
    use Error_Trait;

    private static $adding_addons = false;
    private static $selector_rendered = false;

    /**
     * Temporary data calculated during the RFQ AJAX request. WordPress creates
     * the request_quote post later in the same request, where we persist this
     * context as private post meta.
     */
    private $pending_quote_context = null;

    public function __construct()
    {
        /*
         * Primary placement: immediately before the RnB booking/quote buttons.
         * This is the important hook for T-Rent because the products use RFQ.
         */
        add_action('rnb_plain_booking_button', [$this, 'render_addon_selector'], 5);

        /*
         * Fallbacks for RnB layouts/themes that place the booking content
         * differently. render_addon_selector() has a duplicate guard.
         */
        add_action('rnb_main_rental_content', [$this, 'render_addon_selector'], 65);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_addon_selector'], 6);

        /*
         * Validate selected extra products before RnB creates the quote.
         * RnB's own request callback is registered at the default priority 10.
         */
        add_action('wp_ajax_redq_request_for_a_quote', [$this, 'validate_quote_addons'], 1);
        add_action('wp_ajax_nopriv_redq_request_for_a_quote', [$this, 'validate_quote_addons'], 1);

        /*
         * RnB already recalculates the main product whenever the booking form
         * changes. Extend that same response with selected add-on rent/deposit
         * so the visible summary and hidden quote_price stay in sync.
         */
        add_filter('rnb_calculate_inventory_data', [$this, 'include_addons_in_price_response'], 20, 2);

        /*
         * validate_quote_addons() runs before RnB creates the request_quote post.
         * Persist the exact add-on calculation as soon as that post is inserted.
         */
        add_action('save_post_request_quote', [$this, 'persist_pending_quote_context'], 5, 3);

        /*
         * Handles both direct booking and accepted quote -> cart.
         */
        add_action('woocommerce_add_to_cart', [$this, 'add_selected_products'], 30, 6);
    }

    /**
     * Visible selector on the product booking page.
     */
    public function render_addon_selector()
    {
        if (self::$selector_rendered) {
            return;
        }

        global $product;

        if (!$product || !$product->is_type('redq_rental')) {
            return;
        }

        $product_groups = $this->get_available_addon_products((int) $product->get_id());

        $has_products = false;
        foreach ($product_groups as $group_products) {
            if (!empty($group_products)) {
                $has_products = true;
                break;
            }
        }

        if (!$has_products) {
            return;
        }

        self::$selector_rendered = true;

        wp_nonce_field('trent_addon_booking', 'trent_addon_nonce');

        echo '<div class="trent-booking-addons" style="margin:16px 0;padding:14px;border:1px solid rgba(0,0,0,.18);border-radius:6px;">';
        echo '<label for="trent-addon-product-picker" style="display:block;font-weight:700;margin-bottom:7px;">'
            . esc_html__('Legg til produkt', 'redq-rental')
            . '</label>';

        echo '<div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">';
        echo '<select id="trent-addon-product-picker" style="flex:1 1 230px;min-width:0;">';
        echo '<option value="">' . esc_html__('Velg produkt', 'redq-rental') . '</option>';

        $group_labels = [
            'related' => __('Relaterte produkter', 'redq-rental'),
            'linked'  => __('Koblede produkter', 'redq-rental'),
            'other'   => __('Alle andre produkter', 'redq-rental'),
        ];

        foreach ($product_groups as $group_key => $group_products) {
            if (empty($group_products)) {
                continue;
            }

            $label = isset($group_labels[$group_key]) ? $group_labels[$group_key] : __('Produkter', 'redq-rental');
            echo '<optgroup label="' . esc_attr($label) . '">';

            foreach ($group_products as $addon_product) {
                echo '<option value="' . esc_attr($addon_product->get_id()) . '">'
                    . esc_html($addon_product->get_name())
                    . '</option>';
            }

            echo '</optgroup>';
        }

        echo '</select>';
        echo '<button type="button" id="trent-addon-product-add" class="button" style="flex:0 0 auto;">'
            . esc_html__('Legg til', 'redq-rental')
            . '</button>';
        echo '</div>';

        echo '<div id="trent-addon-selected" style="margin-top:8px;"></div>';
        echo '<small style="display:block;margin-top:7px;opacity:.8;">'
            . esc_html__('Tilleggsproduktet bruker samme hente- og returdato. Tilgjengelighet kontrolleres separat.', 'redq-rental')
            . '</small>';
        echo '</div>';

        ?>
        <script>
        (function () {
            function initTrentAddons() {
                var picker = document.getElementById('trent-addon-product-picker');
                var addButton = document.getElementById('trent-addon-product-add');
                var selected = document.getElementById('trent-addon-selected');

                if (!picker || !addButton || !selected || addButton.dataset.trentReady === '1') {
                    return;
                }

                addButton.dataset.trentReady = '1';

                function hasProduct(id) {
                    return !!selected.querySelector('input[name="trent_addon_products[]"][value="' + id + '"]');
                }

                function addProduct(id, label) {
                    if (!id || hasProduct(id)) {
                        return;
                    }

                    var row = document.createElement('div');
                    row.className = 'trent-addon-row';
                    row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;padding:7px 9px;margin:5px 0;border:1px solid rgba(0,0,0,.12);border-radius:4px;';

                    var text = document.createElement('span');
                    text.textContent = label;

                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'trent_addon_products[]';
                    hidden.value = id;

                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'button';
                    remove.textContent = '<?php echo esc_js(__('Fjern', 'redq-rental')); ?>';
                    remove.style.cssText = 'padding:3px 8px;min-height:auto;';
                    remove.addEventListener('click', function () {
                        row.remove();
                        var form = row.closest('form.rnb-cart') || document.querySelector('form.rnb-cart');
                        if (form && window.jQuery) {
                            window.jQuery(form).trigger('change');
                        }
                    });

                    row.appendChild(text);
                    row.appendChild(hidden);
                    row.appendChild(remove);
                    selected.appendChild(row);
                }

                addButton.addEventListener('click', function () {
                    var option = picker.options[picker.selectedIndex];

                    if (!option || !option.value) {
                        return;
                    }

                    addProduct(option.value, option.text);
                    picker.value = '';

                    var form = selected.closest('form.rnb-cart') || document.querySelector('form.rnb-cart');
                    if (form && window.jQuery) {
                        window.jQuery(form).trigger('change');
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTrentAddons);
            } else {
                initTrentAddons();
            }
        })();
        </script>
        <?php
    }

    /**
     * Add selected add-on products to RnB's normal live price calculation.
     *
     * The main product is still calculated entirely by RnB. We only calculate
     * each extra rental product with the same dates, then merge its rent and
     * refundable deposit into the response RnB already sends to main-script.js.
     */
    public function include_addons_in_price_response($response, $posted_data)
    {
        $form = isset($_POST['form']) && is_array($_POST['form'])
            ? wp_unslash($_POST['form'])
            : [];

        $raw_ids = isset($form['trent_addon_products'])
            ? (array) $form['trent_addon_products']
            : [];

        $selected_ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));

        if (empty($selected_ids) || empty($response['price_breakdown']) || !is_array($response['price_breakdown'])) {
            return $response;
        }

        $addon_rows = [];
        $addon_rent_total = 0.0;
        $addon_deposit_total = 0.0;
        $addon_grand_total = 0.0;
        $addon_booking_cost = 0.0;

        foreach ($selected_ids as $addon_id) {
            $addon_product = wc_get_product($addon_id);

            if (!$addon_product || !$addon_product->is_type('redq_rental')) {
                continue;
            }

            $calculated = $this->prepare_addon_rental_data($form, $addon_id, false);

            if (is_wp_error($calculated)) {
                if (empty($response['error']) || !is_array($response['error'])) {
                    $response['error'] = [];
                }

                $response['error'][] = sprintf(
                    esc_html__('%s er ikke tilgjengelig i valgt periode.', 'redq-rental'),
                    $addon_product->get_name()
                );
                continue;
            }

            $rental_data = $calculated['rental_data'];
            $breakdown = $rental_data['rental_days_and_costs']['price_breakdown'];
            $quantity = !empty($rental_data['quantity'])
                ? max(1, (int) $rental_data['quantity'])
                : 1;

            $rent = isset($breakdown['deposit_free_total'])
                ? (float) $breakdown['deposit_free_total'] * $quantity
                : 0.0;
            $deposit = isset($breakdown['deposit_total'])
                ? (float) $breakdown['deposit_total'] * $quantity
                : 0.0;
            $grand = isset($breakdown['total'])
                ? (float) $breakdown['total'] * $quantity
                : ($rent + $deposit);
            $booking_cost = isset($rental_data['rental_days_and_costs']['cost'])
                ? (float) $rental_data['rental_days_and_costs']['cost'] * $quantity
                : $rent;

            $addon_rent_total += $rent;
            $addon_deposit_total += $deposit;
            $addon_grand_total += $grand;
            $addon_booking_cost += $booking_cost;

            $addon_rows['trent_addon_' . $addon_id] = [
                'text'   => $addon_product->get_name(),
                'amount' => $rent,
                'cost'   => wc_price($rent),
            ];
        }

        if (empty($addon_rows)) {
            return $response;
        }

        $summary = $response['price_breakdown'];
        $updated = [];
        $rows_inserted = false;

        foreach ($summary as $key => $row) {
            /*
             * Show each add-on immediately before RnB's subtotal ("Sum").
             * If that row is absent we insert before deposit/total below.
             */
            if (!$rows_inserted && $key === 'deposit_free_total') {
                foreach ($addon_rows as $addon_key => $addon_row) {
                    $updated[$addon_key] = $addon_row;
                }
                $rows_inserted = true;
            }

            if (in_array($key, ['deposit_free_total'], true) && is_array($row)) {
                $row['amount'] = (float) ($row['amount'] ?? 0) + $addon_rent_total;
                $row['cost'] = wc_price($row['amount']);
            }

            if ($key === 'deposit' && is_array($row)) {
                $row['amount'] = (float) ($row['amount'] ?? 0) + $addon_deposit_total;
                $row['cost'] = wc_price($row['amount']);
            }

            if (in_array($key, ['total', 'grand_total', 'quote_total'], true) && is_array($row)) {
                $row['amount'] = (float) ($row['amount'] ?? 0) + $addon_grand_total;
                $row['cost'] = wc_price($row['amount']);
            }

            if (!$rows_inserted && in_array($key, ['deposit', 'total', 'grand_total', 'quote_total'], true)) {
                foreach ($addon_rows as $addon_key => $addon_row) {
                    $updated[$addon_key] = $addon_row;
                }
                $rows_inserted = true;
            }

            $updated[$key] = $row;
        }

        if (!$rows_inserted) {
            foreach ($addon_rows as $addon_key => $addon_row) {
                $updated[$addon_key] = $addon_row;
            }
        }

        /*
         * A main product without its own deposit may not have a deposit row.
         * Add one when an add-on has a refundable deposit.
         */
        if ($addon_deposit_total > 0 && !isset($updated['deposit'])) {
            $general = redq_rental_get_settings(
                isset($form['add-to-cart']) ? absint($form['add-to-cart']) : 0,
                'general'
            );
            $deposit_label = !empty($general['general']['deposit_amount'])
                ? $general['general']['deposit_amount']
                : __('Sikkerhetsbeløp', 'redq-rental');

            $before_total = [];
            $inserted = false;
            foreach ($updated as $key => $row) {
                if (!$inserted && in_array($key, ['total', 'grand_total', 'quote_total'], true)) {
                    $before_total['deposit'] = [
                        'text'   => $deposit_label,
                        'amount' => $addon_deposit_total,
                        'cost'   => wc_price($addon_deposit_total),
                    ];
                    $inserted = true;
                }
                $before_total[$key] = $row;
            }
            if (!$inserted) {
                $before_total['deposit'] = [
                    'text'   => $deposit_label,
                    'amount' => $addon_deposit_total,
                    'cost'   => wc_price($addon_deposit_total),
                ];
            }
            $updated = $before_total;
        }

        $response['price_breakdown'] = $updated;

        if (isset($response['total_cost'])) {
            $main_booking_cost = isset($posted_data['rental_days_and_costs']['cost'])
                ? (float) $posted_data['rental_days_and_costs']['cost']
                : 0.0;
            $main_quantity = !empty($posted_data['quantity'])
                ? max(1, (int) $posted_data['quantity'])
                : 1;
            $response['total_cost'] = wc_price(($main_booking_cost * $main_quantity) + $addon_booking_cost);
        }

        $response['trent_addons'] = [
            'rent_total'    => $addon_rent_total,
            'deposit_total' => $addon_deposit_total,
            'total'         => $addon_grand_total,
        ];

        return $response;
    }

    /**
     * Validate add-ons before RnB saves a quote.
     *
     * This does not reserve them yet; it prevents a quote from being sent with
     * an already unavailable extra product. Availability is checked again when
     * the accepted quote is converted to the cart.
     */
    public function validate_quote_addons()
    {
        if (
            empty($_POST['nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['nonce'])),
                'rnb_rfq_nonce'
            )
        ) {
            return;
        }

        if (empty($_POST['form_data']) || !is_array($_POST['form_data'])) {
            return;
        }

        $form_data = wp_unslash($_POST['form_data']);
        $selected_ids = $this->get_addon_ids_from_serialized_form($form_data);

        if (empty($selected_ids)) {
            return;
        }

        $source_form = $this->serialized_form_to_source($form_data);
        $names = [];
        $snapshot = [];
        $addon_total = 0.0;

        foreach ($selected_ids as $addon_id) {
            $addon_product = wc_get_product($addon_id);

            if (!$addon_product || !$addon_product->is_type('redq_rental')) {
                wp_send_json([
                    'success'     => false,
                    'status_code' => 400,
                    'message'     => esc_html__('Ett av tilleggsproduktene finnes ikke lenger.', 'redq-rental'),
                ]);
            }

            $calculated = $this->prepare_addon_rental_data($source_form, $addon_id, false);

            if (is_wp_error($calculated)) {
                wp_send_json([
                    'success'     => false,
                    'status_code' => 400,
                    'message'     => sprintf(
                        esc_html__('%s er ikke tilgjengelig i valgt periode. Velg andre datoer eller fjern produktet.', 'redq-rental'),
                        esc_html($addon_product->get_name())
                    ),
                ]);
            }

            $breakdown = $calculated['rental_data']['rental_days_and_costs']['price_breakdown'];
            $quantity = !empty($calculated['rental_data']['quantity'])
                ? max(1, (int) $calculated['rental_data']['quantity'])
                : 1;
            $rent = isset($breakdown['deposit_free_total']) ? (float) $breakdown['deposit_free_total'] * $quantity : 0;
            $deposit = isset($breakdown['deposit_total']) ? (float) $breakdown['deposit_total'] * $quantity : 0;

            $addon_total += $rent + $deposit;

            $snapshot[(int) $addon_id] = [
                'product_id'  => (int) $addon_id,
                'form'        => $calculated['form'],
                'rental_data' => $calculated['rental_data'],
                'rent_total'  => $rent,
                'deposit_total' => $deposit,
            ];

            $names[] = sprintf(
                '%s (leie %s, depositum %s)',
                $addon_product->get_name(),
                wp_strip_all_tags(wc_price($rent)),
                wp_strip_all_tags(wc_price($deposit))
            );
        }

        /*
         * Add one human-readable row to the quote data as well. The original
         * trent_addon_products[] fields remain untouched and are what we use
         * later when the quote is converted to the cart.
         */
        if (!empty($names)) {
            $_POST['form_data'][] = [
                'name'  => 'Tilleggsprodukter',
                'value' => implode(', ', $names),
            ];
        }

        /*
         * RnB saves quote_price as the official quote total. Its normal value
         * contains the main product including its deposit. Add the complete
         * add-on total (rent + deposit) here so the quote itself is correct.
         *
         * The original main-product quote price is kept privately and is used
         * later by Ajax::rnb_quote_booking_data(), preventing the add-on from
         * being counted once on the main line and then again on its own line.
         */
        /*
         * Calculate the main product total again on the server instead of
         * trusting the browser's hidden quote_price. The live price already
         * includes add-ons, so adding addon_total to that client value would
         * count the extras twice.
         */
        $main_form = $this->rearrange_form_data($source_form);
        $main_rental_data = $this->prepare_form_data($main_form, false);
        $main_breakdown = isset($main_rental_data['rental_days_and_costs']['price_breakdown'])
            ? $main_rental_data['rental_days_and_costs']['price_breakdown']
            : [];
        $main_quantity = !empty($main_rental_data['quantity'])
            ? max(1, (int) $main_rental_data['quantity'])
            : 1;

        $base_quote_price = isset($main_breakdown['total'])
            ? (float) $main_breakdown['total'] * $main_quantity
            : 0.0;

        $_POST['quote_price'] = wc_format_decimal(
            $base_quote_price + $addon_total,
            wc_get_price_decimals()
        );

        $this->pending_quote_context = [
            'base_quote_price' => $base_quote_price,
            'addon_total'      => $addon_total,
            'snapshot'         => $snapshot,
        ];
    }

    /**
     * Save the quote calculation produced by validate_quote_addons().
     *
     * This intentionally uses private post meta instead of adding technical
     * fields to order_quote_meta, so customers/admin do not see internal data.
     */
    public function persist_pending_quote_context($post_id, $post, $update)
    {
        if (
            empty($this->pending_quote_context)
            || !is_object($post)
            || $post->post_type !== 'request_quote'
            || wp_is_post_revision($post_id)
        ) {
            return;
        }

        update_post_meta(
            $post_id,
            '_trent_addon_base_quote_price',
            (float) $this->pending_quote_context['base_quote_price']
        );
        update_post_meta(
            $post_id,
            '_trent_addon_quote_total',
            (float) $this->pending_quote_context['addon_total']
        );
        update_post_meta(
            $post_id,
            '_trent_addon_snapshot',
            $this->pending_quote_context['snapshot']
        );

        $this->pending_quote_context = null;
    }

    /**
     * Add selected extra products when the main rental item enters the cart.
     */
    public function add_selected_products($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (self::$adding_addons) {
            return;
        }

        $quote_id = !empty($cart_item_data['rental_data']['quote_id'])
            ? absint($cart_item_data['rental_data']['quote_id'])
            : 0;

        $quote_snapshot = [];

        if ($quote_id) {
            $selected_ids = $this->get_quote_addon_ids($quote_id);
            $source_form = !empty($cart_item_data['rental_data']['posted_data'])
                && is_array($cart_item_data['rental_data']['posted_data'])
                ? $cart_item_data['rental_data']['posted_data']
                : [];

            $stored_snapshot = get_post_meta($quote_id, '_trent_addon_snapshot', true);
            if (is_array($stored_snapshot)) {
                $quote_snapshot = $stored_snapshot;
            }
        } else {
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

            $selected_ids = array_values(array_unique(array_filter(array_map(
                'absint',
                wp_unslash($_POST['trent_addon_products'])
            ))));

            $source_form = $_POST;
        }

        $main_product = wc_get_product($product_id);

        if (!$main_product || !$main_product->is_type('redq_rental') || empty($selected_ids)) {
            return;
        }

        $original_post = $_POST;
        $source_form = is_array($source_form) ? $source_form : [];
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
                ) {
                    continue;
                }

                $calculated = null;

                /*
                 * For an accepted quote, use the exact calculation saved when
                 * the request was sent. This keeps the quoted rent/deposit
                 * stable even if product pricing is edited before payment.
                 */
                if (
                    $quote_id
                    && isset($quote_snapshot[$addon_id])
                    && is_array($quote_snapshot[$addon_id])
                    && !empty($quote_snapshot[$addon_id]['form'])
                    && is_array($quote_snapshot[$addon_id]['form'])
                    && !empty($quote_snapshot[$addon_id]['rental_data'])
                    && is_array($quote_snapshot[$addon_id]['rental_data'])
                ) {
                    $calculated = [
                        'form'        => $quote_snapshot[$addon_id]['form'],
                        'rental_data' => $quote_snapshot[$addon_id]['rental_data'],
                    ];
                } else {
                    $calculated = $this->prepare_addon_rental_data($source_form, $addon_id, true);
                }

                if (is_wp_error($calculated)) {
                    $failed_names[] = $addon_product->get_name();
                    continue;
                }

                $addon_form = $calculated['form'];
                $addon_rental_data = $calculated['rental_data'];
                $cart_item_data = [];

                /*
                 * Accepted quote: CartHandler preserves rental_data when quote_id
                 * exists. The add-on therefore keeps its own price + deposit.
                 */
                if ($quote_id) {
                    $addon_rental_data['quote_id'] = $quote_id;
                    $addon_rental_data['posted_data'] = $addon_form;
                    $cart_item_data['rental_data'] = $addon_rental_data;
                    $cart_item_data['trent_addon_product'] = true;
                }

                $_POST = $addon_form;
                $added_key = WC()->cart->add_to_cart(
                    $addon_id,
                    1,
                    '',
                    [],
                    $cart_item_data
                );
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
     * Convert RnB/jQuery serializeArray() data to the date/time source we need.
     */
    private function serialized_form_to_source(array $form_data)
    {
        $source = [];

        foreach ($form_data as $field) {
            if (!is_array($field) || empty($field['name']) || !array_key_exists('value', $field)) {
                continue;
            }

            $raw_name = (string) $field['name'];
            $is_array_field = substr($raw_name, -2) === '[]';
            $name = $is_array_field ? substr($raw_name, 0, -2) : $raw_name;

            if ($name === 'trent_addon_products') {
                continue;
            }

            if ($is_array_field) {
                if (!isset($source[$name]) || !is_array($source[$name])) {
                    $source[$name] = [];
                }

                $source[$name][] = $field['value'];
                continue;
            }

            if (!array_key_exists($name, $source)) {
                $source[$name] = $field['value'];
            }
        }

        return $source;
    }

    /**
     * Product IDs selected on the product page.
     */
    private function get_addon_ids_from_serialized_form(array $form_data)
    {
        $ids = [];

        foreach ($form_data as $field) {
            if (
                !is_array($field)
                || empty($field['name'])
                || $field['name'] !== 'trent_addon_products[]'
                || !isset($field['value'])
            ) {
                continue;
            }

            $id = absint($field['value']);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Product IDs saved in the original quote form.
     */
    private function get_quote_addon_ids($quote_id)
    {
        $raw = get_post_meta($quote_id, 'unformatted_order_quote_meta', true);
        $form_data = json_decode($raw, true);

        if (!is_array($form_data)) {
            return [];
        }

        return $this->get_addon_ids_from_serialized_form($form_data);
    }

    /**
     * Build and calculate one add-on with its own RnB inventory, rental price
     * and security deposit. build_addon_form() already normalizes + validates.
     */
    private function prepare_addon_rental_data(array $source, $addon_id, $add_cart = false)
    {
        $form = $this->build_addon_form($source, $addon_id);

        if (is_wp_error($form)) {
            return $form;
        }

        $rental_data = $this->prepare_form_data($form, $add_cart);

        if (
            !is_array($rental_data)
            || empty($rental_data['rental_days_and_costs'])
            || empty($rental_data['rental_days_and_costs']['price_breakdown'])
        ) {
            return new \WP_Error('addon_price_calculation_failed');
        }

        return [
            'form'        => $form,
            'rental_data' => $rental_data,
        ];
    }

    /**
     * Build a minimal valid RnB request for an extra product.
     *
     * Dates/times are inherited. Inventory, required deposit and rental price
     * are resolved for the extra product itself.
     */
    private function build_addon_form(array $source, $addon_id)
    {
        if (!function_exists('rnb_get_product_inventory_id')) {
            return new \WP_Error('missing_inventory_function');
        }

        $form = [
            'add-to-cart'        => (int) $addon_id,
            'order_type'         => 'new_order',
            'inventory_quantity' => 1,
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

            $addon_deposits = $this->get_addon_deposits((int) $inventory_id);

            if (!empty($addon_deposits)) {
                $candidate['security_deposites'] = $addon_deposits;
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
     * Carry the selected add-on product's own RnB deposit configuration with it.
     *
     * The normal RnB product page allows a deposit term to be clickable, but an
     * add-on product has no second booking form where the customer can select it.
     * Therefore every deposit term assigned to the chosen inventory is included
     * for the add-on. RnB then calculates the refundable deposit amount normally.
     */
    private function get_addon_deposits($inventory_id)
    {
        $deposit_ids = [];
        $deposits = get_the_terms($inventory_id, 'deposite');

        if (empty($deposits) || is_wp_error($deposits)) {
            return $deposit_ids;
        }

        foreach ($deposits as $deposit) {
            $deposit_ids[] = (int) $deposit->term_id;
        }

        return array_values(array_unique(array_filter($deposit_ids)));
    }

    /**
     * Prioritise useful add-ons instead of presenting one long alphabetical list.
     *
     * 1. WooCommerce related products (category/tag relation)
     * 2. Manually linked products (upsells + cross-sells)
     * 3. All remaining published RnB rental products
     *
     * A product is shown only once. Hidden catalog products are intentionally
     * allowed because T-Rent may hide bookable products from Google/catalog.
     */
    private function get_available_addon_products($exclude_product_id)
    {
        $current = wc_get_product($exclude_product_id);

        $related_ids = function_exists('wc_get_related_products')
            ? wc_get_related_products($exclude_product_id, 20, [$exclude_product_id])
            : [];

        $linked_ids = [];

        if ($current) {
            $linked_ids = array_merge(
                method_exists($current, 'get_upsell_ids') ? $current->get_upsell_ids() : [],
                method_exists($current, 'get_cross_sell_ids') ? $current->get_cross_sell_ids() : []
            );
        }

        $related_ids = array_values(array_unique(array_map('absint', $related_ids)));
        $linked_ids  = array_values(array_unique(array_map('absint', $linked_ids)));

        // Related products have first priority, so remove duplicates from linked.
        $linked_ids = array_values(array_diff($linked_ids, $related_ids));

        $all_ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'post__not_in'   => [(int) $exclude_product_id],
        ]);

        $used_ids = array_merge($related_ids, $linked_ids);
        $other_ids = array_values(array_diff(array_map('absint', $all_ids), $used_ids));

        return [
            'related' => $this->prepare_addon_product_group($related_ids, $exclude_product_id),
            'linked'  => $this->prepare_addon_product_group($linked_ids, $exclude_product_id),
            'other'   => $this->prepare_addon_product_group($other_ids, $exclude_product_id),
        ];
    }

    /**
     * Keep only published RnB rental products that actually have inventory.
     */
    private function prepare_addon_product_group(array $product_ids, $exclude_product_id)
    {
        $results = [];

        foreach ($product_ids as $product_id) {
            $product_id = absint($product_id);

            if (!$product_id || $product_id === (int) $exclude_product_id) {
                continue;
            }

            $candidate = wc_get_product($product_id);

            if (
                !$candidate
                || !$candidate->is_type('redq_rental')
                || $candidate->get_status() !== 'publish'
            ) {
                continue;
            }

            $inventory_ids = function_exists('rnb_get_product_inventory_id')
                ? rnb_get_product_inventory_id($product_id)
                : [];

            if (empty($inventory_ids)) {
                continue;
            }

            $results[] = $candidate;
        }

        usort($results, function ($a, $b) {
            return strnatcasecmp($a->get_name(), $b->get_name());
        });

        return $results;
    }
}

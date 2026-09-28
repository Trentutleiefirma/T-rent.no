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
    private static $pending_quote_snapshot = [];

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
         * Live price/deposit preview for an added product on the booking page.
         */
        add_action('wp_ajax_trent_rnb_addon_quote_data', [$this, 'ajax_addon_quote_data']);
        add_action('wp_ajax_nopriv_trent_rnb_addon_quote_data', [$this, 'ajax_addon_quote_data']);

        /*
         * Save quoted add-on financial data as private quote meta. This keeps
         * add-on price/deposit fixed to the accepted quote without exposing a
         * technical JSON field in the customer's quote form.
         */
        add_action('save_post_request_quote', [$this, 'save_pending_quote_snapshot'], 5, 3);

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
        echo '<div id="trent-addon-totals" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid rgba(0,0,0,.12);font-weight:600;"></div>';
        echo '<small style="display:block;margin-top:7px;opacity:.8;">'
            . esc_html__('Tilleggsproduktet bruker samme hente- og returdato. Tilgjengelighet kontrolleres separat.', 'redq-rental')
            . '</small>';
        echo '</div>';

        ?>
        <script>
        (function ($) {
            function initTrentAddons() {
                var picker = document.getElementById('trent-addon-product-picker');
                var addButton = document.getElementById('trent-addon-product-add');
                var selected = document.getElementById('trent-addon-selected');
                var totals = document.getElementById('trent-addon-totals');
                var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

                if (!picker || !addButton || !selected || !totals || addButton.dataset.trentReady === '1') {
                    return;
                }

                addButton.dataset.trentReady = '1';

                function nonceValue() {
                    var nonce = document.querySelector('input[name="trent_addon_nonce"]');
                    return nonce ? nonce.value : '';
                }

                function formData() {
                    var form = $('form.rnb-cart');
                    if (!form.length) {
                        form = $('form.cart').first();
                    }
                    return form.serializeArray();
                }

                function hasProduct(id) {
                    return !!selected.querySelector('input[name="trent_addon_products[]"][value="' + id + '"]');
                }

                function money(value) {
                    var number = parseFloat(value || 0);
                    try {
                        return new Intl.NumberFormat('nb-NO', {
                            style: 'currency',
                            currency: 'NOK',
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 2
                        }).format(number);
                    } catch (e) {
                        return number.toFixed(2) + ' kr';
                    }
                }

                function updateTotals() {
                    var rows = selected.querySelectorAll('.trent-addon-row');
                    var addonRent = 0;
                    var addonDeposit = 0;
                    var addonTotal = 0;

                    rows.forEach(function (row) {
                        addonRent += parseFloat(row.dataset.rent || 0);
                        addonDeposit += parseFloat(row.dataset.deposit || 0);
                        addonTotal += parseFloat(row.dataset.total || 0);
                    });

                    if (!rows.length) {
                        totals.style.display = 'none';
                        totals.innerHTML = '';
                        return;
                    }

                    var mainQuoteInput = document.querySelector('.quote_price');
                    var mainTotal = mainQuoteInput ? parseFloat(mainQuoteInput.value || 0) : 0;
                    var combined = mainTotal + addonTotal;

                    totals.style.display = 'block';
                    totals.innerHTML =
                        '<div>Tillegg leie: <strong>' + money(addonRent) + '</strong></div>' +
                        '<div>Tillegg depositum: <strong>' + money(addonDeposit) + '</strong></div>' +
                        '<div>Forespørsel totalt: <strong>' + money(combined) + '</strong></div>';
                }

                function loadPrice(row, done) {
                    var id = row.dataset.productId;
                    var detail = row.querySelector('.trent-addon-detail');

                    row.dataset.rent = '0';
                    row.dataset.deposit = '0';
                    row.dataset.total = '0';
                    row.dataset.available = '0';

                    if (detail) {
                        detail.textContent = 'Beregner pris og depositum…';
                    }

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'trent_rnb_addon_quote_data',
                            nonce: nonceValue(),
                            addon_id: id,
                            form_data: formData()
                        }
                    }).done(function (response) {
                        if (!response || !response.success || !response.data) {
                            var message = response && response.data && response.data.message
                                ? response.data.message
                                : 'Kunne ikke beregne tilleggsproduktet.';
                            if (detail) {
                                detail.textContent = message;
                            }
                            row.style.borderColor = '#b32d2e';
                            updateTotals();
                            if (done) done(false);
                            return;
                        }

                        row.dataset.rent = String(response.data.rental || 0);
                        row.dataset.deposit = String(response.data.deposit || 0);
                        row.dataset.total = String(response.data.total || 0);
                        row.dataset.available = '1';
                        row.style.borderColor = 'rgba(0,0,0,.12)';

                        if (detail) {
                            detail.innerHTML =
                                'Leie: <strong>' + response.data.rental_html + '</strong>' +
                                ' · Depositum: <strong>' + response.data.deposit_html + '</strong>';
                        }

                        updateTotals();
                        if (done) done(true);
                    }).fail(function () {
                        if (detail) {
                            detail.textContent = 'Kunne ikke beregne tilleggsproduktet.';
                        }
                        row.style.borderColor = '#b32d2e';
                        updateTotals();
                        if (done) done(false);
                    });
                }

                function createRow(id, label) {
                    if (!id || hasProduct(id)) {
                        return null;
                    }

                    var row = document.createElement('div');
                    row.className = 'trent-addon-row';
                    row.dataset.productId = id;
                    row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 9px;margin:5px 0;border:1px solid rgba(0,0,0,.12);border-radius:4px;';

                    var info = document.createElement('div');
                    info.style.cssText = 'min-width:0;flex:1 1 auto;';

                    var title = document.createElement('div');
                    title.style.fontWeight = '600';
                    title.textContent = label;

                    var detail = document.createElement('div');
                    detail.className = 'trent-addon-detail';
                    detail.style.cssText = 'font-size:.9em;margin-top:2px;opacity:.85;';
                    detail.textContent = 'Beregner pris og depositum…';

                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'trent_addon_products[]';
                    hidden.value = id;

                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'button';
                    remove.textContent = '<?php echo esc_js(__('Fjern', 'redq-rental')); ?>';
                    remove.style.cssText = 'padding:3px 8px;min-height:auto;flex:0 0 auto;';
                    remove.addEventListener('click', function () {
                        row.remove();
                        updateTotals();
                    });

                    info.appendChild(title);
                    info.appendChild(detail);
                    row.appendChild(info);
                    row.appendChild(hidden);
                    row.appendChild(remove);
                    selected.appendChild(row);

                    return row;
                }

                addButton.addEventListener('click', function () {
                    var option = picker.options[picker.selectedIndex];

                    if (!option || !option.value || hasProduct(option.value)) {
                        return;
                    }

                    var row = createRow(option.value, option.text);
                    picker.value = '';

                    if (row) {
                        loadPrice(row);
                    }
                });

                /*
                 * RnB recalculates the main product after date/inventory changes.
                 * Recalculate every selected add-on at the same time so both
                 * rental price and deposit always match the chosen period.
                 */
                $(document).on('do_stuff_after_successful_data_fetch', function () {
                    var rows = selected.querySelectorAll('.trent-addon-row');

                    if (!rows.length) {
                        updateTotals();
                        return;
                    }

                    rows.forEach(function (row) {
                        loadPrice(row);
                    });
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTrentAddons);
            } else {
                initTrentAddons();
            }
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Return the selected add-on's own RnB rental price and deposit for the
     * dates currently chosen on the main booking form.
     */
    public function ajax_addon_quote_data()
    {
        if (
            empty($_POST['nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['nonce'])),
                'trent_addon_booking'
            )
        ) {
            wp_send_json_error(['message' => __('Ugyldig forespørsel.', 'redq-rental')], 403);
        }

        $addon_id = !empty($_POST['addon_id']) ? absint($_POST['addon_id']) : 0;
        $form_data = !empty($_POST['form_data']) && is_array($_POST['form_data'])
            ? wp_unslash($_POST['form_data'])
            : [];

        if (!$addon_id || empty($form_data)) {
            wp_send_json_error(['message' => __('Velg datoer før tilleggsprodukt beregnes.', 'redq-rental')], 400);
        }

        $source_form = $this->serialized_form_to_source($form_data);
        $calculated = $this->calculate_addon_data($source_form, $addon_id, false);

        if (is_wp_error($calculated)) {
            $product = wc_get_product($addon_id);
            $name = $product ? $product->get_name() : __('Produktet', 'redq-rental');

            wp_send_json_error([
                'message' => sprintf(
                    __('%s er ikke tilgjengelig i valgt periode.', 'redq-rental'),
                    $name
                ),
            ], 400);
        }

        $breakdown = $calculated['rental_data']['rental_days_and_costs']['price_breakdown'];
        $rental = isset($breakdown['deposit_free_total']) ? (float) $breakdown['deposit_free_total'] : 0;
        $deposit = isset($breakdown['deposit_total']) ? (float) $breakdown['deposit_total'] : 0;
        $total = isset($breakdown['total']) ? (float) $breakdown['total'] : ($rental + $deposit);

        wp_send_json_success([
            'rental'       => $rental,
            'deposit'      => $deposit,
            'total'        => $total,
            'rental_html'  => wp_strip_all_tags(wc_price($rental)),
            'deposit_html' => wp_strip_all_tags(wc_price($deposit)),
            'total_html'   => wp_strip_all_tags(wc_price($total)),
        ]);
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
        $main_quote_total = isset($_POST['quote_price'])
            ? (float) wc_format_decimal(wp_unslash($_POST['quote_price']))
            : 0;

        $addon_total = 0;
        $lines = [];
        $snapshots = [];

        foreach ($selected_ids as $addon_id) {
            $addon_product = wc_get_product($addon_id);

            if (!$addon_product || !$addon_product->is_type('redq_rental')) {
                wp_send_json([
                    'success'     => false,
                    'status_code' => 400,
                    'message'     => esc_html__('Ett av tilleggsproduktene finnes ikke lenger.', 'redq-rental'),
                ]);
            }

            $calculated = $this->calculate_addon_data($source_form, $addon_id, false);

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
            $rental = isset($breakdown['deposit_free_total']) ? (float) $breakdown['deposit_free_total'] : 0;
            $deposit = isset($breakdown['deposit_total']) ? (float) $breakdown['deposit_total'] : 0;
            $total = isset($breakdown['total']) ? (float) $breakdown['total'] : ($rental + $deposit);

            $addon_total += $total;
            $snapshots[(int) $addon_id] = [
                'product_id'            => (int) $addon_id,
                'rental_days_and_costs' => $calculated['rental_data']['rental_days_and_costs'],
            ];

            $lines[] = sprintf(
                '%s – leie %s, depositum %s',
                $addon_product->get_name(),
                wp_strip_all_tags(wc_price($rental)),
                wp_strip_all_tags(wc_price($deposit))
            );
        }

        /*
         * RnB's top-level $_POST['quote_price'] must remain the MAIN product
         * price because Ajax::rnb_quote_booking_data() later uses _quote_price
         * as the main cart item's price. Only the quote form's visible/stored
         * total is changed to main + add-ons, preventing double charging.
         */
        $combined_total = $main_quote_total + $addon_total;
        $quote_price_found = false;

        foreach ($form_data as &$field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }

            if ($field['name'] === 'quote_price') {
                $field['value'] = wc_format_decimal($combined_total);
                $quote_price_found = true;
            }
        }
        unset($field);

        if (!$quote_price_found) {
            $form_data[] = [
                'name'  => 'quote_price',
                'value' => wc_format_decimal($combined_total),
            ];
        }

        if (!empty($lines)) {
            $form_data[] = [
                'name'  => 'Tilleggsprodukter',
                'value' => implode(' | ', $lines),
            ];
        }

        self::$pending_quote_snapshot = $snapshots;
        $_POST['form_data'] = $form_data;
    }

    /**
     * Save the server-calculated add-on pricing/deposit snapshot on the quote
     * post at creation time. The normal RnB RequestForQuote handler creates the
     * request_quote post after validate_quote_addons() has run.
     */
    public function save_pending_quote_snapshot($post_id, $post, $update)
    {
        if (
            $update
            || !$post
            || $post->post_type !== 'request_quote'
            || empty(self::$pending_quote_snapshot)
        ) {
            return;
        }

        update_post_meta(
            $post_id,
            '_trent_addon_quote_snapshot',
            wp_json_encode(self::$pending_quote_snapshot)
        );

        self::$pending_quote_snapshot = [];
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

        if ($quote_id) {
            $selected_ids = $this->get_quote_addon_ids($quote_id);
            $source_form = !empty($cart_item_data['rental_data']['posted_data'])
                && is_array($cart_item_data['rental_data']['posted_data'])
                ? $cart_item_data['rental_data']['posted_data']
                : [];
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

                $calculated = $this->calculate_addon_data($source_form, $addon_id, true);

                if (is_wp_error($calculated)) {
                    $failed_names[] = $addon_product->get_name();
                    continue;
                }

                $addon_form = $calculated['form'];
                $addon_rental_data = $calculated['rental_data'];

                if ($quote_id) {
                    $quoted_snapshot = $this->get_quote_addon_snapshot($quote_id, $addon_id);
                    if (!empty($quoted_snapshot)) {
                        $addon_rental_data = $this->apply_quote_addon_snapshot(
                            $addon_rental_data,
                            $quoted_snapshot
                        );
                    }
                }

                /*
                 * Pass fully prepared rental_data explicitly. Giving the add-on
                 * the quote_id makes CartHandler preserve these data instead of
                 * rebuilding the item from the main product's quote payload.
                 */
                if ($quote_id) {
                    $addon_rental_data['quote_id'] = $quote_id;
                }

                $addon_rental_data['posted_data'] = $addon_form;
                $addon_cart_data = [
                    'rental_data' => $addon_rental_data,
                    'trent_addon_product' => true,
                ];

                $_POST = $addon_form;
                $added_key = WC()->cart->add_to_cart(
                    $addon_id,
                    1,
                    '',
                    [],
                    $addon_cart_data
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

            $name = rtrim((string) $field['name'], '[]');

            if ($name === 'trent_addon_products') {
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
     * Get the add-on financial snapshot saved when the quote was submitted.
     */
    private function get_quote_addon_snapshot($quote_id, $addon_id)
    {
        $raw = get_post_meta($quote_id, '_trent_addon_quote_snapshot', true);

        if (empty($raw)) {
            return [];
        }

        $snapshots = json_decode($raw, true);

        if (!is_array($snapshots)) {
            return [];
        }

        $key = (string) absint($addon_id);

        return isset($snapshots[$key]) && is_array($snapshots[$key])
            ? $snapshots[$key]
            : [];
    }

    /**
     * Keep current availability/date data but use the financial values that
     * belonged to the accepted quote. This mirrors RnB's own _quote_price
     * behavior for the main product.
     */
    private function apply_quote_addon_snapshot(array $rental_data, array $snapshot)
    {
        if (
            empty($snapshot['rental_days_and_costs'])
            || !is_array($snapshot['rental_days_and_costs'])
            || empty($rental_data['rental_days_and_costs'])
            || !is_array($rental_data['rental_days_and_costs'])
        ) {
            return $rental_data;
        }

        $quoted = $snapshot['rental_days_and_costs'];
        $current = $rental_data['rental_days_and_costs'];

        foreach (['price_breakdown', 'cost', 'instant_pay', 'due_payment', 'line_total'] as $key) {
            if (array_key_exists($key, $quoted)) {
                $current[$key] = $quoted[$key];
            }
        }

        $rental_data['rental_days_and_costs'] = $current;

        return $rental_data;
    }

    /**
     * Validate an add-on using its own inventory/deposit settings and prepare
     * the exact rental_data RnB needs for price, deposit and checkout.
     */
    private function calculate_addon_data(array $source, $addon_id, $add_cart = false)
    {
        $addon_form = $this->build_addon_form($source, $addon_id);

        if (is_wp_error($addon_form)) {
            return $addon_form;
        }

        $rental_data = $this->prepare_form_data($addon_form, $add_cart);

        if (
            !is_array($rental_data)
            || empty($rental_data['rental_days_and_costs'])
            || empty($rental_data['rental_days_and_costs']['price_breakdown'])
        ) {
            return new \WP_Error('addon_price_calculation_failed');
        }

        return [
            'form'        => $addon_form,
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

<?php
/**
 * Plugin Name: T-Rent Ordreinformasjon
 * Description: Fast henteinformasjon i kundens ordrebekreftelse, med maskininfo når den finnes.
 * Version: 1.1.0
 * Author: T-Rent
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

if (!class_exists('TRent_Order_Email_Information', false)) {
    final class TRent_Order_Email_Information
    {
        private const MAP_URL = 'https://maps.app.goo.gl/SGGrLyovcMW5RNvH6';
        private const SNAPSHOT_META = '_trent_maskin_info_snapshot';
        private const SNAPSHOT_MARKER_META = '_trent_maskin_info_snapshot_saved';

        public static function register()
        {
            // Save the machine instructions with new order lines. The checkout hook
            // covers normal purchases, while the new-item hook also covers RnB quotes,
            // manually created orders and other order-creation flows.
            add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'snapshot_checkout_item'], 10, 4);
            add_action('woocommerce_new_order_item', [__CLASS__, 'snapshot_new_order_item'], 10, 3);

            // Register independently of RnB's quote emails and the active theme.
            add_action('woocommerce_email_order_details', [__CLASS__, 'prepare'], 1, 4);
            add_action('woocommerce_email_order_details', [__CLASS__, 'general_information'], 5, 4);
            add_action('woocommerce_email_order_details', [__CLASS__, 'machine_information'], 20, 4);
            add_action('before_woocommerce_init', [__CLASS__, 'declare_compatibility']);
        }

        public static function declare_compatibility()
        {
            if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        }

        private static function is_customer_email($order, $sent_to_admin, $email, $ids)
        {
            return !$sent_to_admin
                && $order instanceof \WC_Order
                && is_object($email)
                && isset($email->id)
                && in_array($email->id, $ids, true);
        }

        private static function has_visible_machine_information($info)
        {
            if (!is_string($info)) {
                return false;
            }

            $text = wp_strip_all_tags($info);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', $text);

            return is_string($text) && $text !== '';
        }

        private static function product_machine_information($product)
        {
            if (!is_object($product) || !is_callable([$product, 'get_meta'])) {
                return '';
            }

            $info = $product->get_meta('maskin_info', true);

            // A variation containing only empty HTML (for example <p></p> or
            // &nbsp;) must inherit the instructions from its parent product.
            if (!self::has_visible_machine_information($info)
                && is_callable([$product, 'get_parent_id'])
                && $product->get_parent_id()) {
                $parent = wc_get_product($product->get_parent_id());
                if ($parent) {
                    $info = $parent->get_meta('maskin_info', true);
                }
            }

            if (!self::has_visible_machine_information($info)) {
                return '';
            }

            return wp_kses_post($info);
        }

        private static function save_snapshot($item, $product)
        {
            if (!$item instanceof \WC_Order_Item_Product
                || !is_callable([$item, 'add_meta_data'])
                || !is_object($product)) {
                return false;
            }

            $item->add_meta_data(self::SNAPSHOT_META, self::product_machine_information($product), true);
            $item->add_meta_data(self::SNAPSHOT_MARKER_META, 'yes', true);

            return true;
        }

        public static function snapshot_checkout_item($item, $cart_item_key = '', $values = [], $order = null)
        {
            if (!$item instanceof \WC_Order_Item_Product
                || $item->get_meta(self::SNAPSHOT_MARKER_META, true) === 'yes') {
                return;
            }

            $product = isset($values['data']) && is_object($values['data'])
                ? $values['data']
                : $item->get_product();

            self::save_snapshot($item, $product);
        }

        public static function snapshot_new_order_item($item_id, $item, $order_id)
        {
            if (!$item instanceof \WC_Order_Item_Product
                || $item->get_meta(self::SNAPSHOT_MARKER_META, true) === 'yes') {
                return;
            }

            if (self::save_snapshot($item, $item->get_product()) && is_callable([$item, 'save_meta_data'])) {
                $item->save_meta_data();
            }
        }

        private static function item_machine_information($item, $product)
        {
            // New orders use their saved copy so later product edits or deletion
            // cannot change the information in a resend or completed-order email.
            if ($item->get_meta(self::SNAPSHOT_MARKER_META, true) === 'yes') {
                $info = $item->get_meta(self::SNAPSHOT_META, true);
                return self::has_visible_machine_information($info) ? wp_kses_post($info) : '';
            }

            // Existing orders created before version 1.1.0 have no snapshot.
            return self::product_machine_information($product);
        }

        private static function item_deduplication_key($item, $product)
        {
            if (is_object($product) && is_callable([$product, 'get_id']) && $product->get_id()) {
                return 'product:' . $product->get_id();
            }
            if (is_callable([$item, 'get_product_id']) && $item->get_product_id()) {
                return 'product:' . $item->get_product_id();
            }
            if (is_callable([$item, 'get_id']) && $item->get_id()) {
                return 'item:' . $item->get_id();
            }

            return 'object:' . spl_object_hash($item);
        }

        public static function prepare($order, $sent_to_admin = false, $plain_text = false, $email = null)
        {
            if (!self::is_customer_email($order, $sent_to_admin, $email, ['customer_processing_order', 'customer_completed_order'])) {
                return;
            }

            // Replace only the two known older T-Rent hooks, not other email hooks.
            // Do this when rendering so snippets loaded after this plugin are covered.
            $legacy = [
                ['woocommerce_email_before_order_table', 'trent_processing_order_information'],
                ['woocommerce_email_order_details', 'trent_vis_maskin_info_i_mail'],
            ];
            foreach ($legacy as $entry) {
                while (false !== ($priority = has_action($entry[0], $entry[1]))) {
                    remove_action($entry[0], $entry[1], $priority);
                }
            }
        }

        public static function general_information($order, $sent_to_admin = false, $plain_text = false, $email = null)
        {
            if (!self::is_customer_email($order, $sent_to_admin, $email, ['customer_processing_order'])) {
                return;
            }

            // This block must never depend on product metadata, product existence,
            // or whether any order line has maskin_info.
            if ($plain_text) {
                echo "\nHENTING OG TILBAKELEVERING\n";
                echo "Utstyret settes foran låven til høyre og er klart til henting til avtalt tid, om ikke annet er avtalt.\n";
                echo "Tilbakelevering senest kl. 20:00 siste leiedag, om ikke annet er avtalt.\n\n";
                echo "ÅPNINGSTIDER\nMandag–fredag: 08:00–20:00\nLørdag–søndag: 09:00–20:00\n";
                echo "Henting kvelden før kl. 19:00–21:00 er mulig etter avtale dersom utstyret er ledig.\n\n";
                echo "KONTAKT OSS\nTelefon: 920 72 993\nAdresse/kart: " . self::MAP_URL . "\n\n";
                return;
            }
            ?>
            <div class="trent-order-information" style="margin:20px 0;padding:16px;background:#f7f7f7;border-left:4px solid #333;">
                <h2 style="margin:0 0 10px;">Henting og tilbakelevering</h2>
                <p style="margin:0 0 10px;">Utstyret settes foran låven til høyre og er klart til henting til avtalt tid, om ikke annet er avtalt.</p>
                <p style="margin:0 0 16px;">Tilbakelevering senest <strong>kl. 20:00 siste leiedag</strong>, om ikke annet er avtalt.</p>
                <p style="margin:0 0 6px;"><strong>Åpningstider</strong><br>Mandag–fredag: 08:00–20:00<br>Lørdag–søndag: 09:00–20:00</p>
                <p style="margin:0 0 16px;">Henting kvelden før kl. 19:00–21:00 er mulig etter avtale dersom utstyret er ledig.</p>
                <p style="margin:0;"><strong>Kontakt oss</strong><br>Telefon: <a href="tel:+4792072993">920 72 993</a><br>Adresse: <a href="<?php echo esc_url(self::MAP_URL); ?>">Vis hentested i Google Maps</a></p>
            </div>
            <?php
        }

        public static function machine_information($order, $sent_to_admin = false, $plain_text = false, $email = null)
        {
            if (!self::is_customer_email($order, $sent_to_admin, $email, ['customer_processing_order', 'customer_completed_order'])) {
                return;
            }

            $shown = [];
            foreach ($order->get_items('line_item') as $item) {
                if (!$item instanceof \WC_Order_Item_Product) {
                    continue;
                }

                $product = $item->get_product();
                $info = self::item_machine_information($item, $product);
                if (!self::has_visible_machine_information($info)) {
                    continue;
                }

                $deduplication_key = self::item_deduplication_key($item, $product);
                if (isset($shown[$deduplication_key])) {
                    continue;
                }
                $shown[$deduplication_key] = true;

                if ($plain_text) {
                    echo "\nINFO OM MASKINEN DIN – " . wp_strip_all_tags($item->get_name()) . "\n";
                    // Preserve links and line breaks when converting saved HTML to text.
                    $text = preg_replace_callback('/<a\b[^>]*href=["\x27]([^"\x27]+)["\x27][^>]*>(.*?)<\/a>/is', static function ($match) {
                        $url = esc_url_raw(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
                        return wp_strip_all_tags($match[2]) . ($url ? ' (' . $url . ')' : '');
                    }, $info);
                    $text = preg_replace('/<br\s*\/?\s*>|<\/(?:p|div|li|h[1-6])\s*>/i', "\n", $text);
                    echo html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES, 'UTF-8') . "\n\n";
                } else {
                    echo '<div class="trent-machine-information" style="margin:20px 0;">';
                    echo '<h2>Info om maskinen din – ' . esc_html($item->get_name()) . '</h2>';
                    echo wp_kses_post(make_clickable(wpautop(wp_kses_post($info))));
                    echo '</div>';
                }
            }
        }
    }

    TRent_Order_Email_Information::register();
}

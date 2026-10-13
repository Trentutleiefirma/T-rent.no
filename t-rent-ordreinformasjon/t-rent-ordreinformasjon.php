<?php
/**
 * Plugin Name: T-Rent Ordreinformasjon
 * Description: Fast henteinformasjon i kundens ordrebekreftelse, med maskininfo når den finnes.
 * Version: 1.0.0
 * Author: T-Rent
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

if (!class_exists('TRent_Order_Email_Information', false)) {
    final class TRent_Order_Email_Information
    {
        private const MAP_URL = 'https://maps.app.goo.gl/SGGrLyovcMW5RNvH6';

        public static function register()
        {
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
                if (!$product) {
                    continue;
                }
                $info = $product->get_meta('maskin_info', true);
                // Variations can inherit the machine instructions from their parent.
                if ((!is_string($info) || trim($info) === '') && $product->get_parent_id()) {
                    $parent = wc_get_product($product->get_parent_id());
                    if ($parent) {
                        $info = $parent->get_meta('maskin_info', true);
                    }
                }
                if (!is_string($info) || trim(wp_strip_all_tags($info)) === '') {
                    continue;
                }
                $product_id = $product->get_id();
                if (isset($shown[$product_id])) {
                    continue;
                }
                $shown[$product_id] = true;

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

<?php
/**
 * Standalone regression check. Run with PHP and a WordPress wp-includes directory:
 * php tests/order-email-information-check.php /path/to/wordpress/wp-includes
 * Uses real WordPress hooks, escaping, formatting and KSES; no database or mail.
 */
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$support = isset($argv[1]) ? rtrim($argv[1], '/\\') : '';
if (!is_file($support . '/plugin.php')) {
    fwrite(STDERR, "Pass a WordPress wp-includes directory.\n");
    exit(1);
}
define('ABSPATH', dirname($support) . '/');
define('WPINC', 'wp-includes');
require $support . '/compat.php';
require $support . '/plugin.php';
require $support . '/formatting.php';
require $support . '/kses.php';

// WordPress environment and WooCommerce read-only order/product test doubles.
function get_option($key) { return $key === 'blog_charset' ? 'UTF-8' : false; }
function is_utf8_charset($value = null) { return true; }
function _canonical_charset($charset) { return 'UTF-8'; }
function wp_allowed_protocols() { return ['http', 'https', 'mailto', 'tel']; }
function wp_is_valid_utf8($text) { return true; }
function wp_mail() { throw new Exception('Tests must never send email.'); }
class WC_Order {
    private $items;
    public function __construct($items) { $this->items = $items; }
    public function get_items($type) { return $this->items; }
}
class WC_Order_Item_Product {
    private static $next_id = 1;
    private $id;
    private $product;
    private $name;
    private $meta = [];
    public function __construct($product, $name = 'Testmaskin') {
        $this->id = self::$next_id++;
        $this->product = $product;
        $this->name = $name;
    }
    public function get_id() { return $this->id; }
    public function get_product() { return $this->product; }
    public function set_product($product) { $this->product = $product; }
    public function get_product_id() { return $this->product && is_callable([$this->product, 'get_id']) ? $this->product->get_id() : 0; }
    public function get_name() { return $this->name; }
    public function get_meta($key, $single = true) { return isset($this->meta[$key]) ? $this->meta[$key] : ''; }
    public function add_meta_data($key, $value, $unique = false) { $this->meta[$key] = $value; }
    public function save_meta_data() {}
}
class Test_Product {
    private $id;
    private $info;
    private $parent;
    public function __construct($id, $info, $parent = 0) { $this->id = $id; $this->info = $info; $this->parent = $parent; }
    public function get_id() { return $this->id; }
    public function get_meta($key, $single = true) { return $key === 'maskin_info' ? $this->info : ''; }
    public function get_parent_id() { return $this->parent; }
    public function set_info($info) { $this->info = $info; }
}
function wc_get_product($id) { return isset($GLOBALS['products'][$id]) ? $GLOBALS['products'][$id] : false; }
function trent_processing_order_information() { echo 'OLD_GENERAL'; }
function trent_vis_maskin_info_i_mail() { echo 'OLD_MACHINE'; }

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) { throw new Exception('FAILED: ' . $label); }
    ++$checks;
}
function render_test_email($items, $id = 'customer_processing_order', $plain = false, $admin = false) {
    $order = new WC_Order($items);
    ob_start();
    do_action('woocommerce_email_order_details', $order, $admin, $plain, (object) ['id' => $id]);
    return ob_get_clean();
}
require dirname(__DIR__) . '/t-rent-ordreinformasjon.php';
// Including a second copy must not redeclare the class or duplicate hooks.
include dirname(__DIR__) . '/t-rent-ordreinformasjon.php';
// Simulate the core order-details callback at its real priority, including the nested hook.
add_action('woocommerce_email_order_details', function ($order, $admin, $plain, $email) {
    do_action('woocommerce_email_before_order_table', $order, $admin, $plain, $email);
    echo $plain ? "ORDER_TABLE\n" : '<table><tr><td>ORDER_TABLE</td></tr></table>';
}, 10, 4);
add_action('woocommerce_email_order_details', function () { echo 'UNRELATED_HOOK'; }, 30, 4);

$empty = new WC_Order_Item_Product(new Test_Product(480, ''), '65 kg hoppetusse');
foreach ([false, true] as $plain) {
    foreach ([[$empty], [], [new WC_Order_Item_Product(false)]] as $items) {
        $mail = render_test_email($items, 'customer_processing_order', $plain);
        check(strpos($mail, 'foran låven til høyre') !== false, 'pickup exists without machine info/product');
        check(strpos($mail, '08:00–20:00') !== false && strpos($mail, '09:00–20:00') !== false, 'both opening hours');
        check(strpos($mail, '20:00 siste leiedag') !== false, 'return deadline');
        check(strpos($mail, 'Du finner brukerinformasjon og tips til utstyret lenger ned i denne e-posten.') !== false, 'usage-information pointer');
        check(strpos($mail, '920 72 993') !== false, 'contact number');
        check(strpos($mail, 'maps.app.goo.gl/SGGrLyovcMW5RNvH6') !== false, 'map link');
        check(strpos($mail, 'Info om maskinen din') === false && strpos($mail, 'INFO OM MASKINEN DIN') === false, 'no empty machine block');
        check(strpos($mail, 'foran låven') < strpos($mail, 'ORDER_TABLE'), 'information before unchanged order table');
        check(substr_count($mail, 'foran låven') === 1, 'one general block');
        check(strpos($mail, 'UNRELATED_HOOK') !== false, 'preserve other callbacks');
        if ($plain) { check($mail === strip_tags($mail), 'plain text has no HTML'); }
    }
}

$machine_product = new Test_Product(494, "<p>Maskintips</p><p><a href=\"https://example.com/manual\">Manual</a></p><script>alert(1)</script><img src=x onerror=alert(2)>");
$machine = new WC_Order_Item_Product($machine_product, 'Maskin <script>bad</script>');
add_action('woocommerce_email_before_order_table', 'trent_processing_order_information', 10, 4);
add_action('woocommerce_email_order_details', 'trent_vis_maskin_info_i_mail', 20, 4);
$mail = render_test_email([$empty, $machine, $machine]);
check(substr_count($mail, 'class="trent-machine-information"') === 1, 'one machine block per product');
check(strpos($mail, 'Maskintips') > strpos($mail, 'ORDER_TABLE'), 'machine info follows order table');
check(strpos($mail, 'OLD_GENERAL') === false && strpos($mail, 'OLD_MACHINE') === false, 'replace known legacy callbacks');
check(strpos($mail, '<script>') === false && strpos($mail, 'onerror=') === false, 'sanitize content and escape names');
check(strpos($mail, 'https://example.com/manual') !== false, 'keep manual URL');
$plain = render_test_email([$machine], 'customer_processing_order', true);
check(strpos($plain, 'Manual (https://example.com/manual)') !== false, 'plain text preserves link destination');
check(strpos($plain, 'alert(1)') === false && strpos($plain, '<') === false, 'plain text removes scripts and markup');

// Visually empty variation HTML must inherit from the parent product.
$GLOBALS['products'][9] = new Test_Product(9, 'Parent instructions');
$variant = new WC_Order_Item_Product(new Test_Product(10, '<p>&nbsp;</p>', 9));
check(strpos(render_test_email([$variant]), 'Parent instructions') !== false, 'empty variation HTML inherits instructions');
check(strpos(render_test_email([new WC_Order_Item_Product(new Test_Product(11, ['invalid']))]), 'foran låven') !== false, 'malformed metadata cannot suppress general info');

// A checkout snapshot must survive later product edits and product deletion.
$snapshot_product = new Test_Product(12, '<p>Original instructions</p>');
$snapshot_item = new WC_Order_Item_Product($snapshot_product, 'Snapshot machine');
do_action('woocommerce_checkout_create_order_line_item', $snapshot_item, 'cart-key', ['data' => $snapshot_product], new WC_Order([]));
$snapshot_product->set_info('<p>Changed instructions</p>');
$snapshot_item->set_product(false);
$snapshot_mail = render_test_email([$snapshot_item]);
check(strpos($snapshot_mail, 'Original instructions') !== false, 'snapshot survives product edit and deletion');
check(strpos($snapshot_mail, 'Changed instructions') === false, 'snapshot prevents later product content');

// Empty information is also snapshotted, so later product edits do not rewrite history.
$empty_snapshot_product = new Test_Product(13, '<p></p>');
$empty_snapshot_item = new WC_Order_Item_Product($empty_snapshot_product, 'Initially empty machine');
do_action('woocommerce_new_order_item', $empty_snapshot_item->get_id(), $empty_snapshot_item, 100);
$empty_snapshot_product->set_info('Added later');
check(strpos(render_test_email([$empty_snapshot_item]), 'Added later') === false, 'empty snapshot remains empty');

// Old orders without a snapshot retain the live-product fallback.
$legacy_product = new Test_Product(14, 'Legacy live instructions');
$legacy_item = new WC_Order_Item_Product($legacy_product, 'Legacy machine');
check(strpos(render_test_email([$legacy_item]), 'Legacy live instructions') !== false, 'old order uses live product fallback');

foreach (['new_order', 'customer_on_hold_order', 'customer_invoice', 'customer_refunded_order', 'quote-accepted'] as $id) {
    $mail = render_test_email([$machine], $id);
    check(strpos($mail, 'trent-order-information') === false && strpos($mail, 'trent-machine-information') === false, 'leave unrelated email ' . $id);
}
$admin = render_test_email([$machine], 'customer_processing_order', false, true);
check(strpos($admin, 'trent-order-information') === false && strpos($admin, 'trent-machine-information') === false, 'exclude administrator emails');
$completed = render_test_email([$machine], 'customer_completed_order');
check(strpos($completed, 'trent-order-information') === false
    && strpos($completed, 'Du finner brukerinformasjon og tips til utstyret') === false
    && strpos($completed, 'Maskintips') !== false,
    'preserve previous completed-mail machine-info scope');
ob_start();
TRent_Order_Email_Information::general_information(null, false, false, null);
TRent_Order_Email_Information::machine_information(null, false, false, null);
check(ob_get_clean() === '', 'invalid email context is safe');
// A later rendering/resend must still contain the complete block.
check(substr_count(render_test_email([$empty]), 'foran låven') === 1, 'subsequent mail remains complete');
do_action('before_woocommerce_init'); // Also safe if WooCommerce is unavailable.
echo "PASS: {$checks} checks; no emails sent and no prices, dates or order status changed.\n";

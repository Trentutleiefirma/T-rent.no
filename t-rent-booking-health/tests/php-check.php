<?php
define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
$hooks = []; $enqueued = []; $localized = []; $inline = []; $events = []; $can_manage = false; $rental = true; $on_product = true; $no_cache = 0;
function add_action($name, $callback, $priority = 10, $args = 1) { global $hooks; $hooks[$name] = [$callback, $priority, $args]; }
function register_activation_hook($file, $callback) {}
function is_product() { global $on_product; return $on_product; }
function get_queried_object_id() { return 494; }
function wc_get_product($id) { return new class { function is_type($type) { global $rental; return $rental; } }; }
function wp_unslash($value) { return $value; }
function nocache_headers() { global $no_cache; $no_cache++; }
function wp_enqueue_script(...$args) { global $enqueued; $enqueued[] = $args; }
function wp_localize_script($handle, $name, $value) { global $localized; $localized = $value; }
function plugins_url($path, $file) { return 'https://t-rent.test/wp-content/plugins/t-rent-booking-health/' . $path; }
function admin_url($path) { return 'https://t-rent.test/wp-admin/' . $path; }
function get_permalink($id) { return 'https://t-rent.test/product/test/'; }
function wp_script_is($handle, $status) { return true; }
function wp_scripts() { return (object) ['registered'=>['wcpay-frontend-tracks'=>(object)['src'=>'https://t-rent.test/wp-content/plugins/woocommerce-payments/dist/frontend-tracks.js?ver=11']]]; }
function trailingslashit($url) { return rtrim($url, '/') . '/'; }
function wp_json_encode($value) { return json_encode($value); }
function wp_add_inline_script($handle, $value, $position) { global $inline; $inline[] = [$handle, $value, $position]; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function absint($value) { return abs((int) $value); }
function get_option($key, $default = false) { global $events; return $events; }
function update_option($key, $value, $autoload) { global $events; $events = $value; if ($autoload !== false) throw new Exception('Autoload enabled'); }
function current_user_can($capability) { global $can_manage; return $can_manage; }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function wp_date($format, $time) { return date($format, $time); }
function add_submenu_page(...$args) {}
function do_action($name) { global $cleared; $cleared = $name; }
require dirname(__DIR__) . '/t-rent-booking-health.php';
$checks = 0;
function check($condition, $message) { global $checks; $checks++; if (!$condition) throw new Exception($message); }
check(isset($hooks['wp_verify_nonce_failed']) && $hooks['wp_verify_nonce_failed'][2] === 4, 'Log genuine server-side failures');
$_GET = ['_trent_booking_refresh'=>['malformed']];
check(TRent_Booking_Health::refresh_id() === '', 'Reject array refresh input');
$_GET = ['_trent_booking_refresh'=>'<script>bad</script>'];
check(TRent_Booking_Health::refresh_id() === '', 'Reject unsafe refresh marker');
$_GET = [];
TRent_Booking_Health::refresh_headers();
check($no_cache === 0, 'Ordinary page caching remains available');
$_GET = ['_trent_booking_refresh'=>'unique-refresh-id-123'];
$on_product = false;
TRent_Booking_Health::refresh_headers();
TRent_Booking_Health::enqueue();
check($no_cache === 0 && count($enqueued) === 0, 'No changes to non-product pages');
$on_product = true; $rental = false;
TRent_Booking_Health::enqueue();
check(count($enqueued) === 0, 'No changes to non-rental products');
$rental = true;
TRent_Booking_Health::refresh_headers();
check($no_cache === 1 && DONOTCACHEPAGE, 'Fresh snapshots are not cacheable');
TRent_Booking_Health::enqueue();
check(count($enqueued) === 1 && $localized['refreshId'] === 'unique-refresh-id-123', 'Echo the exact refresh marker');
check($localized['productUrl'] === 'https://t-rent.test/product/test/', 'Use canonical product URL');
TRent_Booking_Health::repair_tracks_config();
check(count($inline) === 1 && strpos($inline[0][1], 'window.wcpayAssets ||') !== false, 'Preserve existing tracking configuration');
check(strpos($inline[0][1], 'frontend-tracks.js') === false, 'Use asset directory, not script URL');
TRent_Booking_Health::activate();
check($cleared === 'cache_enabler_clear_complete_cache', 'Refresh old cached HTML on activation');
$_POST = ['action'=>'delete_user', 'product_id'=>42];
TRent_Booking_Health::record_nonce_failure('SECRET', 'delete_user', null, 'SECRET');
check(count($events) === 0, 'Do not log unrelated private actions');
$events = [['at'=>time()-15*DAY_IN_SECONDS,'action'=>'old','product_id'=>1]];
for ($i=0;$i<55;$i++) $events[] = ['at'=>time(), 'action'=>'rnb_get_inventory_data', 'product_id'=>1];
$_POST = ['action'=>'rnb_calculate_inventory_data', 'form'=>['add-to-cart'=>494], 'email'=>'private@example.test'];
TRent_Booking_Health::record_nonce_failure('SECRET_NONCE', 'rnb_ajax_nonce', (object)['email'=>'private@example.test'], 'SECRET_TOKEN');
check(count($events) === 50, 'Bound event retention');
check(end($events)['product_id'] === 494, 'Record the affected product');
check(array_keys(end($events)) === ['at','action','product_id'], 'Only record approved diagnostic fields');
check(strpos(json_encode($events), 'SECRET') === false && strpos(json_encode($events), '@') === false, 'No tokens or personal data in logs');
ob_start(); TRent_Booking_Health::admin_page(); $html = ob_get_clean();
check($html === '', 'Reject unauthorized log viewing');
$can_manage = true;
$events[] = ['at'=>time(),'action'=>'<script>alert(1)</script>','product_id'=>494];
ob_start(); TRent_Booking_Health::admin_page(); $html = ob_get_clean();
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Escape diagnostic output');
echo "PASS: $checks PHP checks\n";

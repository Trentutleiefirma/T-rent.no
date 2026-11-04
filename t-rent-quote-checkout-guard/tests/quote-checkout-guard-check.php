<?php

define('ABSPATH', __DIR__ . '/');

$GLOBALS['tqcg_hooks'] = [];
$GLOBALS['tqcg_notices'] = [];
$GLOBALS['tqcg_post_types'] = [];
$GLOBALS['tqcg_post_statuses'] = [];
$GLOBALS['tqcg_post_meta'] = [];
$GLOBALS['tqcg_post_authors'] = [];
$GLOBALS['tqcg_user_meta'] = [];
$GLOBALS['tqcg_products'] = [];
$GLOBALS['tqcg_logged_in'] = false;
$GLOBALS['tqcg_user_id'] = 0;

class WooCommerce
{
}

class TQCG_Product
{
    private $id;
    private $type;
    private $name;

    public function __construct($id, $type, $name)
    {
        $this->id = $id;
        $this->type = $type;
        $this->name = $name;
    }

    public function is_type($type)
    {
        return $this->type === $type;
    }

    public function get_name()
    {
        return $this->name;
    }
}

class TQCG_Cart
{
    private $items;

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function get_cart()
    {
        return $this->items;
    }
}

function add_action($hook, $callback, $priority = 10, $args = 1)
{
    $GLOBALS['tqcg_hooks'][$hook][$priority][] = $callback;
}

function add_filter($hook, $callback, $priority = 10, $args = 1)
{
    add_action($hook, $callback, $priority, $args);
}

function remove_action()
{
}

function apply_filters($hook, $value)
{
    return $value;
}

function absint($value)
{
    return abs((int) $value);
}

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_email($value)
{
    return filter_var((string) $value, FILTER_SANITIZE_EMAIL);
}

function sanitize_text_field($value)
{
    return trim(strip_tags((string) $value));
}

function wp_unslash($value)
{
    return $value;
}

function maybe_unserialize($value)
{
    if (!is_string($value)) {
        return $value;
    }

    $result = @unserialize($value);
    return $result === false && $value !== 'b:0;' ? $value : $result;
}

function get_post_type($post_id)
{
    return $GLOBALS['tqcg_post_types'][$post_id] ?? null;
}

function get_post_status($post_id)
{
    return $GLOBALS['tqcg_post_statuses'][$post_id] ?? null;
}

function get_post_meta($post_id, $key, $single = false)
{
    $value = $GLOBALS['tqcg_post_meta'][$post_id][$key] ?? ($single ? '' : []);
    return $single ? $value : (array) $value;
}

function get_post_field($field, $post_id)
{
    if ($field === 'post_author') {
        return $GLOBALS['tqcg_post_authors'][$post_id] ?? 0;
    }

    return '';
}

function get_user_meta($user_id, $key, $single = false)
{
    return $GLOBALS['tqcg_user_meta'][$user_id][$key] ?? ($single ? '' : []);
}

function is_user_logged_in()
{
    return $GLOBALS['tqcg_logged_in'];
}

function get_current_user_id()
{
    return $GLOBALS['tqcg_user_id'];
}

function wp_get_current_user()
{
    $user = new stdClass();
    $user->user_email = $GLOBALS['tqcg_user_meta'][$GLOBALS['tqcg_user_id']]['user_email'] ?? '';
    return $user;
}

function wc_get_product($product_id)
{
    return $GLOBALS['tqcg_products'][$product_id] ?? null;
}

function wc_add_notice($message, $type)
{
    $GLOBALS['tqcg_notices'][] = [$type, $message];
}

function wc_has_notice()
{
    return false;
}

function esc_html__($text)
{
    return $text;
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function wp_strip_all_tags($text)
{
    return strip_tags($text);
}

require dirname(__DIR__) . '/t-rent-quote-checkout-guard.php';

function tqcg_expect($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function tqcg_invoke($object, $method, array $args = [])
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($object, $args);
}

function tqcg_quote($quote_id, $status, $product_id, array $extra_meta = [])
{
    $GLOBALS['tqcg_post_types'][$quote_id] = 'request_quote';
    $GLOBALS['tqcg_post_statuses'][$quote_id] = $status;
    $GLOBALS['tqcg_post_meta'][$quote_id] = array_merge(
        [
            '_product_id' => $product_id,
            '_quote_user' => 7,
            '_rnb_rfq_order_id' => '',
        ],
        $extra_meta
    );
    $GLOBALS['tqcg_post_authors'][$quote_id] = 7;
}

$GLOBALS['tqcg_products'][494] = new TQCG_Product(494, 'redq_rental', 'Stubbefres');
$GLOBALS['tqcg_products'][780] = new TQCG_Product(780, 'redq_rental', 'Tilhenger');
$GLOBALS['tqcg_products'][900] = new TQCG_Product(900, 'simple', 'Hansker');

$guard = new T_Rent_Quote_Checkout_Guard();

$message = $guard->clarify_existing_email_message(
    'Epost opptatt',
    'Sorry! Email already exist. Please login',
    'redq-rental'
);
tqcg_expect(
    $message === 'E-postadressen er allerede registrert. Logg inn og send forespørselen på nytt.',
    'the duplicate-email response explains the required next step'
);

$result = tqcg_invoke($guard, 'validate_quote', [0, 494, []]);
tqcg_expect($result['reason'] === 'missing', 'a rental without quote is rejected');

tqcg_quote(990, 'quote-pending', 494);
$result = tqcg_invoke($guard, 'validate_quote', [990, 494, []]);
tqcg_expect($result['reason'] === 'not_accepted', 'a pending quote is rejected');

tqcg_quote(990, 'quote-accepted', 494);
$result = tqcg_invoke($guard, 'validate_quote', [990, 494, []]);
tqcg_expect($result['valid'] === true, 'an accepted matching quote is allowed for guest checkout');

$GLOBALS['tqcg_post_meta'][990]['_rnb_rfq_order_id'] = 1200;
$result = tqcg_invoke($guard, 'validate_quote', [990, 494, []]);
tqcg_expect($result['reason'] === 'used', 'an already-used quote is rejected');
$GLOBALS['tqcg_post_meta'][990]['_rnb_rfq_order_id'] = '';

$result = tqcg_invoke($guard, 'validate_quote', [990, 780, []]);
tqcg_expect($result['reason'] === 'product_mismatch', 'a quote cannot authorize a different product');

$GLOBALS['tqcg_post_meta'][990]['_trent_addon_snapshot'] = [
    780 => ['product_id' => 780],
];
$linked_item = ['trent_addon_product' => true, 'rental_data' => ['quote_id' => 990]];
$result = tqcg_invoke($guard, 'validate_quote', [990, 780, $linked_item]);
tqcg_expect($result['valid'] === true, 'a saved linked product is allowed with the accepted quote');

$result = tqcg_invoke($guard, 'validate_quote', [990, 780, ['rental_data' => ['quote_id' => 990]]]);
tqcg_expect($result['reason'] === 'product_mismatch', 'a linked product flag cannot be omitted or forged generically');

$GLOBALS['tqcg_logged_in'] = true;
$GLOBALS['tqcg_user_id'] = 8;
$GLOBALS['tqcg_user_meta'][8] = [
    'user_email' => 'other@example.com',
    'billing_email' => 'other@example.com',
];
$result = tqcg_invoke($guard, 'validate_quote', [990, 494, []]);
tqcg_expect($result['reason'] === 'wrong_customer', 'a logged-in customer cannot use another customer quote');

$GLOBALS['tqcg_post_meta'][990]['order_quote_meta'] = json_encode(
    [['name' => 'quote-email', 'value' => 'other@example.com']]
);
$result = tqcg_invoke($guard, 'validate_quote', [990, 494, []]);
tqcg_expect($result['valid'] === true, 'matching quote and account email authorizes a migrated quote');

$GLOBALS['tqcg_logged_in'] = false;
$GLOBALS['tqcg_user_id'] = 0;
$_POST = [];
$passed = $guard->validate_add_to_cart(true, 494, 1, 0, [], []);
tqcg_expect($passed === false, 'direct rental add-to-cart is blocked');
tqcg_expect(count($GLOBALS['tqcg_notices']) === 1, 'direct add-to-cart receives one customer-facing error');

$_POST = ['order_type' => 'extend_order'];
$passed = $guard->validate_add_to_cart(true, 494, 1, 0, [], []);
tqcg_expect($passed === true, 'RnB order extensions remain allowed');

$_POST = [];
$cart = new TQCG_Cart(
    [
        'stale' => [
            'product_id' => 494,
            'rental_data' => ['quote_id' => 0],
        ],
        'accepted' => [
            'product_id' => 494,
            'rental_data' => ['quote_id' => 990],
        ],
        'ordinary' => [
            'product_id' => 900,
        ],
    ]
);
$invalid = tqcg_invoke($guard, 'invalid_cart_items', [$cart]);
tqcg_expect(count($invalid) === 1 && $invalid[0]['cart_item_key'] === 'stale', 'persistent carts only flag the invalid rental line');

fwrite(STDOUT, "All quote checkout guard checks passed.\n");

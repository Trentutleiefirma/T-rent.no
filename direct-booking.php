<?php

/**
 * Redq rental product add to cart
 *
 * @author      redqteam
 * @package     RedqTeam/Templates
 * @version     1.0.0
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $product; 
$product_id = '';
if($product){
    $product_id = $product->get_id();
}elseif(isset($args['product_id']) && $args['product_id'] != ''){
    $product_id = $args['product_id'];
    $product = wc_get_product($product_id);
}
$displays = redq_rental_get_settings($product_id, 'display');
$book_now = $displays['display']['book_now'];

$redq_product_inventory = rnb_get_product_inventory_id($product_id);
?>
<?php if ($book_now === 'open') : ?>
    <button type="submit" class="single_add_to_cart_button redq_add_to_cart_button btn-book-now button alt"><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
<?php endif; ?>

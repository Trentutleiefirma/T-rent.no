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
    exit;
}

global $product;
$product_id = $product->get_id();

$redq_product_inventory = rnb_get_product_inventory_id($product_id);

if (empty($redq_product_inventory)) {
    return;
}

$inventories = get_posts(array(
    'post_type'      => 'inventory',
    'post__in'       => $redq_product_inventory,
    'posts_per_page' => -1,
    'orderby'        => 'post__in',
));

foreach ($inventories as $index => $inventory) {
    $inventories[$index]->quantity = get_post_meta($inventory->ID, 'quantity', true);
}

$labels = redq_rental_get_settings(get_the_ID(), 'labels', array('inventory'));
$labels = $labels['labels'];
$min_inventory = apply_filters('rnb_show_min_inventory', 2);
?>

<?php do_action('rnb_before_inventory'); ?>

<div class="payable-inventory rnb-component-wrapper rnb-select-wrapper rq-sidebar-select rnb-booking-inventory" <?php echo (count($inventories) < $min_inventory) ? 'style="display:none"' : ''; ?>>
    <h5><?php echo esc_attr($labels['inventory']); ?></h5>
    <select class="redq-select-boxes rnb-select-box" id="booking_inventory" name="booking_inventory" data-post-id="<?php echo $product_id ?>">
        <?php foreach ($inventories as $inventory) : ?>
            <option value="<?php echo $inventory->ID ?>"><?php echo $inventory->post_title ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php do_action('rnb_after_inventory'); ?>
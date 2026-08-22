<?php do_action('rnb_before_quantity'); ?>

<input type="text" style="height: 0;padding: 0;margin: 0;border: 0;width: 0 !important;" class="inventory-qty-next">
<?php
$displays = redq_rental_get_settings(get_the_ID(), 'display');
$displays = $displays['display'];
$field_type = apply_filters('rnb_quantity_field_type', 'number');
?>
<?php if (isset($displays['quantity']) && $displays['quantity'] !== 'closed') : ?>
    <div class="redq-quantity rnb-select-wrapper rnb-component-wrapper">
        <?php
        $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('quantity'));
        $labels = $labels['labels'];
        ?>
        <?php if ($field_type !== 'hidden'): ?>
            <h5><?php echo esc_attr($labels['quantity']); ?></h5>
        <?php endif; ?>

        <?php do_action('rnb_after_quantity_title'); ?>

        <input type="<?php echo esc_attr($field_type); ?>" name="inventory_quantity" class="inventory-qty" min="" max="" value="1">
    </div>
<?php endif; ?>

<?php do_action('rnb_after_quantity'); ?>
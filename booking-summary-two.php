<div class="booking-pricing-info rnb-booking-summery-layout-two" style="display: none">
    <?php do_action('rnb_before_price_breakdown'); ?>

    <?php
    $general_data = redq_rental_get_settings(get_the_ID(), 'general');
    $labels       = redq_rental_get_settings(get_the_ID(), 'labels', array('booking_info'));
    $displays     = redq_rental_get_settings(get_the_ID(), 'display');
    $labels       = $labels['labels'];
    $displays     = $displays['display'];
    $general      = $general_data['general'];
    ?>

    <?php if (isset($displays['instance_payment']) && $displays['instance_payment'] === 'open') : ?>
        <p class="pre-payment"><?php echo esc_attr($labels['instance_pay']); ?><span style="float: right;"><?php echo esc_attr($general['instance_pay']); ?>%</span></p>
    <?php endif; ?>

    <h3 class="booking_cost"><?php echo esc_attr($labels['total_cost']); ?><span style="float: right;"></span></h3>

    <?php do_action('rnb_after_price_breakdown'); ?>
</div>
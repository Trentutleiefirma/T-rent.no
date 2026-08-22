<?php do_action('rnb_before_price_breakdown'); ?>
<?php
$rnb_booking_layout = get_post_meta(get_the_ID(), 'rnb_booking_layout', true);
if ($rnb_booking_layout === 'layout_two') {
?>
    <div class="booking-pricing-info booking-layout-hidden" style="display: none !important;">
        <?php
        $general_data = redq_rental_get_settings(get_the_ID(), 'general');
        $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('booking_info'));
        $displays = redq_rental_get_settings(get_the_ID(), 'display');
        $labels = $labels['labels'];
        $displays = $displays['display'];
        $general = $general_data['general'];
        ?>
        <h3 class="booking_cost"><?php echo esc_attr($labels['total_cost']); ?><span style="float: right;"></span></h3>
    </div>
<?php } else { ?>
    <div class="rnb-loader">
        <div class="booking-pricing-info" style="display: none">
            <?php
            $general_data = redq_rental_get_settings(get_the_ID(), 'general');
            $labels       = redq_rental_get_settings(get_the_ID(), 'labels', array('booking_info'));
            $displays     = redq_rental_get_settings(get_the_ID(), 'display');
            $labels       = $labels['labels'];
            $displays     = $displays['display'];
            $general      = $general_data['general'];
            ?>
            <div class="booking_cost"></div>
        </div>
    </div>
<?php } ?>

<?php do_action('rnb_after_price_breakdown'); ?>
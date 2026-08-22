<div class="date-time-picker rnb-component-wrapper">

    <?php do_action('rnb_before_pickup_datetime'); ?>

    <?php
    global $product;

    $product_id = '';
    if($product){
        $product_id = $product->get_id();
    }elseif(isset($args['product_id']) && $args['product_id'] != ''){
        $product_id = $args['product_id'];
    }
    $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('pickup_date'));
    $displays = redq_rental_get_settings(get_the_ID(), 'display');
    $conditions = redq_rental_get_settings($product_id, 'conditions');
    $labels = $labels['labels'];
    $displays = $displays['display'];
    $conditions = $conditions['conditions'];

    ?>


    <?php if (isset($displays['pickup_date']) && $displays['pickup_date'] !== 'closed') : ?>
        <?php
        if (function_exists('rnb_normalize_params')) {
            $params = rnb_normalize_params($product_id, $_GET);
        }
        $start_date = isset($params['start_date']) ? $params['start_date'] : '';
        ?>
        <h5><?php echo esc_attr($labels['pickup_datetime']); ?></h5>
        <span class="pick-up-date-picker">
            <i class="fas fa-calendar-alt"></i>
            <input type="text" name="pickup_date" id="pickup-date" placeholder="<?php echo esc_attr($labels['pickup_date']); ?>" value="<?php echo esc_attr($start_date); ?>" readonly>
        </span>
    <?php endif; ?>

    <?php if (isset($displays['pickup_time']) && $displays['pickup_time'] !== 'closed') : ?>
        <span class="pick-up-time-picker">
            <i class="fas fa-clock"></i>
            <input type="text" name="pickup_time" id="pickup-time" placeholder="<?php echo esc_attr($labels['pickup_time']); ?>" value="" readonly>
        </span>
    <?php endif; ?>

    <?php do_action('rnb_after_pickup_datetime'); ?>
</div>

<div id="pickup-modal-body" style="display: none;">
    <h5 class="pick-modal-title"><?php echo esc_attr($labels['pickup_datetime']); ?></h5>
    <div id="mobile-datepicker"></div>
    <span id="cal-close-btn">
        <i class="fas fa-times"></i>
    </span>
    <button type="button" id="cal-submit-btn">
        <i class="fas fa-check-circle"></i>
        <?php echo esc_html__('Submit', 'redq-rental'); ?>
    </button>
</div>
<div class="date-time-picker rnb-component-wrapper">

    <?php do_action('rnb_before_return_datetime'); ?>

    <?php
    global $product;
    $product_id = '';
    if($product){
        $product_id = $product->get_id();
    }elseif(isset($args['product_id']) && $args['product_id'] != ''){
        $product_id = $args['product_id'];
    }
	
    $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('return_date'));
    $displays = redq_rental_get_settings(get_the_ID(), 'display');
    $conditions = redq_rental_get_settings($product_id, 'conditions');
    $labels = $labels['labels'];
    $displays = $displays['display'];
    $conditions = $conditions['conditions'];
    ?>
    <?php if (isset($displays['return_date']) && $displays['return_date'] !== 'closed') : ?>
        <?php
		if (function_exists('rnb_normalize_params')) {
			$params = rnb_normalize_params($product_id, $_GET);
		}
		$return_date = isset($params['return_date']) ? $params['return_date'] : '';
		?>
        <h5><?php echo esc_attr($labels['return_datetime']); ?></h5>
        <span class="drop-off-date-picker">
            <i class="fas fa-calendar-alt"></i>
            <input type="text" name="dropoff_date" id="dropoff-date" placeholder="<?php echo esc_attr($labels['return_date']); ?>" value="<?php echo esc_attr($return_date); ?>" readonly>
        </span>
    <?php endif; ?>

    <?php if (isset($displays['return_time']) && $displays['return_time'] !== 'closed') : ?>
        <span class="drop-off-time-picker">
            <i class="fas fa-clock"></i>
            <input type="text" name="dropoff_time" id="dropoff-time" placeholder="<?php echo esc_attr($labels['return_time']); ?>" value="" readonly>
        </span>
    <?php endif; ?>

    <?php do_action('rnb_after_return_datetime'); ?>

</div>

<div id="dropoff-modal-body" style="display: none;">
    <h5 class="drop-modal-title"><?php echo esc_attr($labels['return_datetime']); ?></h5>
    <div id="drop-mobile-datepicker"></div>
    <span id="drop-cal-close-btn">
        <i class="fas fa-times"></i>
    </span>
    <button type="button" id="drop-cal-submit-btn">
        <i class="fa fa-check-circle"></i>
        <?php echo esc_html__('Submit', 'redq-rental'); ?>
    </button>
</div>
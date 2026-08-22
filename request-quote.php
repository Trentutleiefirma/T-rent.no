<?php
global $product;
$product_id = $product->get_id();
$displays = redq_rental_get_settings($product_id, 'display');
$general = redq_rental_get_settings($product_id, 'general');
$labels = redq_rental_get_settings($product_id, 'labels', array('rfq_form'));
$labels = $labels['labels'];
$show_quote = $displays['display']['rfq'];
$user_pass = $general['general']['rfq_user_pass'];
$gdpr_text = $general['general']['gdpr_text'];
$gdpr_page = $general['general']['gdpr_page'];

if ($show_quote === 'open') {
    $customer_first_name = '';
    $customer_last_name = '';
    $customer_phone = '';
    $customer_email = '';
    if (is_user_logged_in()) {
        global $current_user;
        $customer_first_name = get_user_meta($current_user->ID, 'billing_first_name', true);
        $customer_last_name = get_user_meta($current_user->ID, 'billing_last_name', true);
        $customer_phone = get_user_meta($current_user->ID, 'billing_phone', true);
        $customer_email = get_user_meta($current_user->ID, 'billing_email', true);
    } ?>
    <button id="quote-content-confirm" class="redq_request_for_a_quote btn-book-now button"><?php echo esc_html($product->single_request_for_quote_text()); ?></button>

    <div id="quote-popup" class="rnb-popup mfp-hide">
        <?php if (!is_user_logged_in() && $user_pass === 'no') : ?>
            <p>
                <span><?php echo $labels['username_title']; ?></span>
                <input type="text" name="quote-username" id="quote-username" placeholder="<?php echo $labels['username']; ?>" value="" required="true" />
            </p>
            <p>
                <span><?php echo $labels['password_title']; ?></span>
                <input type="password" name="quote-password" id="quote-password" placeholder="<?php echo $labels['password']; ?>" value="" required="true" />
            </p>
        <?php endif ?>

        <p>
            <span><?php echo $labels['first_name_title']; ?></span>
            <input type="text" name="quote-first-name" id="quote-first-name" placeholder="<?php echo $labels['first_name']; ?>" value="<?php echo esc_attr($customer_first_name) ?>" required="true" />
        </p>
        <p>
            <span><?php echo $labels['last_name_title']; ?></span>
            <input type="text" name="quote-last-name" id="quote-last-name" placeholder="<?php echo $labels['last_name']; ?>" value="<?php echo esc_attr($customer_last_name) ?>" required="true" />
        </p>
        <p>
            <span><?php echo $labels['email_title']; ?></span>
            <input type="email" name="quote-email" id="quote-email" placeholder="<?php echo $labels['email']; ?>" value="<?php echo esc_attr($customer_email) ?>" required="true" />
        </p>
        <p>
            <span><?php echo $labels['phone_title']; ?></span>
            <input type="text" name="quote-phone" id="quote-phone" placeholder="<?php echo $labels['phone']; ?>" value="<?php echo esc_attr($customer_phone) ?>" required="true" />
        </p>
        <p>
            <span><?php echo $labels['message_title']; ?></span>
            <textarea name="quote-message" id="quote-message" placeholder="<?php echo $labels['message']; ?>"></textarea>
        </p>

        <?php if (is_gdpr_enable($product_id)) : ?>
            <p>
                <label class="rnb-gdpr-checkbox-field" for="accept_gdpr">
                    <input type="checkbox" name="accept_gdpr" id="accept_gdpr" required="true">
                    <span class="rnb-gdpr-checkbox-field-text">
                        <?php echo $gdpr_text; ?> <a href="<?php echo get_the_permalink($gdpr_page); ?>"><?php echo get_the_title($gdpr_page); ?></a>
                    </span>
                </label>
            </p>
        <?php endif; ?>

        <p>
            <button class="quote-submit"><?php echo $labels['submit_button']; ?><i class="fas fa-spinner fa-pulse fa-fw"></i></button>
        </p>
        <div class="quote-modal-message"></div>
    </div>

<?php
} ?>
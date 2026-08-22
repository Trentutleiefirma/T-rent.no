<?php

namespace REDQ_RnB;

use REDQ_RnB\Email;
use REDQ_RnB\Traits\Assets_Trait;

/**
 * RedQ_Request_For_A_Quote
 */
class RequestForQuote
{
    use Assets_Trait;

    public function __construct()
    {
        add_action('wp_ajax_redq_request_for_a_quote', array($this, 'request_for_a_quote'));
        add_action('wp_ajax_nopriv_redq_request_for_a_quote', array($this, 'request_for_a_quote'));

        if (self::is_quote_menu_enabled()) {
            add_filter('query_vars', array($this, 'request_quote_query_vars'), 0);
            add_filter('woocommerce_account_menu_items', array($this, 'request_quote_my_account_menu_items'), 10, 1);
            add_action('woocommerce_account_request-quote_endpoint', array($this, 'request_quote_endpoint_content'));
            add_action('woocommerce_account_view-quote_endpoint', array($this, 'view_quote_endpoint_content'));
        }

        add_action('save_post', array($this, 'redq_save_post'), 10, 2);
        //add_action( 'publish_post', array( $this, 'check_user_publish' ), 10, 2 );
        add_filter('add_menu_classes', array($this, 'bubble_count_number'));
        add_shortcode( 'rfq_guest_checkout_form', [$this, 'rfq_guest_checkout_form'] );
    }

    // Calculate and display count number
    public function bubble_count_number($menu)
    {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'request_quote' AND post_status = 'quote-pending'";
        $count = $wpdb->get_var($query);

        $check_menu_str = 'edit.php?post_type=request_quote';

        // loop through $menu items, find match, add indicator
        foreach ($menu as $menu_key => $menu_data) {
            if ($check_menu_str != $menu_data[2])
                continue;
            $menu[$menu_key][0] .= " <span class='update-plugins count-$count'><span class='plugin-count'>" . number_format_i18n($count) . '</span></span>';
        }

        return $menu;
    }

    public static function request_quote_endpoints()
    {
        add_rewrite_endpoint('request-quote', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('view-quote', EP_ALL);
    }

    public static function is_quote_menu_enabled()
    {
        $quote_menu = get_option('rnb_enable_rft_endpoint', 'yes');
        return $quote_menu == 'yes' ? true : false;
    }

    function redq_change_publish_button($translation, $text)
    {
        if ('request_quote' == get_post_type()) {
            if ($text == 'Publish')
                return 'Update Quote';

            if ($text == 'Update')
                return 'Update Quote';
        }

        return $translation;
    }

    public function redq_save_post($post_id, $post)
    {

        if (isset($_POST['previous_post_status']) && ($_POST['previous_post_status'] !== $post->post_status)) {
            // send email

            $form_data = json_decode(get_post_meta($post_id, 'order_quote_meta', true), true);

            $from_name = '';
            $from_email = '';
            $from_phone = '';
            $product_id = '';
            $to_email = '';
            $to_author_id = '';

            $message_from_sender_html = '';

            foreach ($form_data as $key => $meta) {
                /**
                 * Get the post author_id, author_email, prodct_id
                 */
                if (isset($meta['name']) && $meta['name'] === 'add-to-cart') {
                    $product_id = $meta['value'];
                    $to_author_id = get_post_field('post_author', $product_id);
                    $to_email = get_the_author_meta('user_email', $to_author_id);
                }
                /**
                 * Get the customer name, email, phone, message
                 */
                else if (isset($meta['forms'])) {
                    $forms = $meta['forms'];
                    foreach ($forms as $k => $v) {
                        $message_from_sender_html .= "<p>" . $k . " : " . $v . "</p>";
                        if ($k === 'email') {
                            $from_email = $v;
                        }
                        if ($k === 'name') {
                            $from_name = $v;
                        }
                    }
                }
            }

            switch ($post->post_status) {
                case 'quote-accepted':
                    // send email to the customer

                    // $prodct_id = get_post_meta($post->ID, 'add-to-cart', true);
                    // $from_author_id = get_post_field('post_author', $prodct_id);
                    // $from_email = get_the_author_meta('user_email', $from_author_id);
                    // $from_name = get_the_author_meta('user_nicename', $from_author_id);

                    $admin_profile = rnb_get_quote_admin_profile();

                    $from_email = $admin_profile['email'];
                    $from_name  = $admin_profile['name'];

                    // To info
                    //$to_author_id = get_post_field('post_author', $post->ID);
                    //$to_email = get_the_author_meta('user_email', $to_author_id);

                    // To info for customer
                    $to_email = rnb_get_quote_customer_email($post->ID);

                    $quote_id = $post->ID;

                    $subject = __("Congratulations! Your quote request has been accepted", "redq-rental");
                    $data_object = array(
                        'quote_id' => $quote_id,
                    );

                    // Send the mail to the customer
                    $email = new Email();
                    $email->quote_accepted_notify_customer($to_email, $subject, $from_email, $from_name, $data_object);
                    break;

                default:
                    // send email to the customer

                    // $prodct_id = get_post_meta($post->ID, 'add-to-cart', true);
                    // $from_author_id = get_post_field('post_author', $prodct_id);
                    // $from_email = get_the_author_meta('user_email', $from_author_id);
                    // $from_name = get_the_author_meta('user_nicename', $from_author_id);

                    $admin_profile = rnb_get_quote_admin_profile();

                    $from_email = $admin_profile['email'];
                    $from_name  = $admin_profile['name'];

                    // To info
                    //$to_author_id = get_post_field('post_author', $post->ID);
                    //$to_email = get_the_author_meta('user_email', $to_author_id);

                    // To info for customer
                    $to_email = rnb_get_quote_customer_email($post->ID);

                    $quote_id = $post->ID;

                    $subject = __("Your quote request status has been updated", "redq-rental");
                    $data_object = array(
                        'quote_id' => $quote_id,
                    );

                    // Send the mail to the customer
                    $email = new Email();
                    $email->quote_status_update_notify_customer($to_email, $subject, $from_email, $from_name, $data_object);
                    break;
            }
        }

        if (isset($_POST['quote_price'])) {
            update_post_meta($post_id, '_quote_price', $_POST['quote_price']);
        }

        if (isset($_POST['add-quote-message']) && !empty($_POST['add-quote-message'])) {

            global $current_user;
            $time = current_time('mysql');

            $data = array(
                'comment_post_ID'      => $post->ID,
                'comment_author'       => $current_user->user_nicename,
                'comment_author_email' => $current_user->user_email,
                'comment_author_url'   => $current_user->user_url,
                'comment_content'      => $_POST['add-quote-message'],
                'comment_type'         => 'quote_message',
                'comment_parent'       => 0,
                'user_id'              => $current_user->ID,
                'comment_author_IP'    => self::get_the_user_ip(),
                'comment_agent'        => $_SERVER['HTTP_USER_AGENT'],
                'comment_date'         => $time,
                'comment_approved'     => 1,
            );

            $comment_id = wp_insert_comment($data);

            // send email to the customer
            // $prodct_id = get_post_meta($post->ID, 'add-to-cart', true);
            // $from_author_id = get_post_field('post_author', $prodct_id);
            // $from_email = get_the_author_meta('user_email', $from_author_id);
            // $from_name = get_the_author_meta('user_nicename', $from_author_id);

            $admin_profile = rnb_get_quote_admin_profile();
            $from_email = $admin_profile['email'];
            $from_name = $admin_profile['name'];

            // To info
            $to_author_id = get_post_field('post_author', $post->ID);
            $to_email = get_the_author_meta('user_email', $to_author_id);

            $quote_id = $post->ID;

            $subject = __("New reply for your quote request", 'redq-rental');
            $reply_message = $_POST['add-quote-message'];
            $data_object = array(
                'reply_message' => $reply_message,
                'quote_id'      => $quote_id,
            );

            // Send the mail to the customer
            $email = new Email();
            $email->owner_reply_message($to_email, $subject, $from_email, $from_name, $data_object);
        }
    }

    public static function get_the_user_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return apply_filters('redq_rental_get_ip', $ip);
    }

    public function view_quote_endpoint_content($quote_id)
    {
        $this->rfq_assets();

        wc_get_template('myaccount/view-quote.php', array(
            'quote_id' => $quote_id
        ), $template_path = '', RNB_PACKAGE_TEMPLATE_PATH);
    }

    public function request_quote_endpoint_content($current_page)
    {
        $this->rfq_assets();

        $current_page  = empty($current_page) ? 1 : absint($current_page);

        wc_get_template('myaccount/request-quote.php', $args = array('current_page' => $current_page), $template_path = '', RNB_PACKAGE_TEMPLATE_PATH);
    }

    public function request_quote_query_vars($vars)
    {
        $vars[] = 'request-quote';
        $vars[] = 'view-quote';

        return $vars;
    }

    public function request_quote_my_account_menu_items($items)
    {
        unset($items['customer-logout']);
        $items['request-quote'] = __('Request Quote', 'redq-rental');
        $items['customer-logout'] = __('Logout', 'redq-rental');

        return $items;
    }

    public function request_for_a_quote()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rnb_rfq_nonce')) {
            wp_send_json([
                'success' => false,
                'message' => esc_html__('Nonce value cannot be verified', 'redq-rental')
            ]);
        }

        $posted = $_POST;
        $form_data = $posted['form_data'];
        $product_id = $posted['product_id'];

        $user_data = $posted['user_data'];
        $user_email = $user_data['quote_email'];

        if (!empty(email_exists($user_email)) && !is_user_logged_in()) {
            $results = [
                'success' => false,
                'message' => esc_html__('Sorry! Email already exist. Please login', 'redq-rental')
            ];
            wp_send_json($results);
        }

        $email = new Email();

        if (!is_user_logged_in()) {
            $user_name = isset($user_data['quote_username']) ? $user_data['quote_username'] : $user_email;
            $user_pass = isset($user_data['quote_password']) ? $user_data['quote_password'] :  substr(str_shuffle(MD5(microtime())), 0, 8);
            $user_args = [
                'user_login' => $user_name,
                'user_email' => $user_email,
                'first_name' => $user_data['quote_first_name'],
                'last_name'  => $user_data['quote_last_name'],
                'user_pass'  => $user_pass,
                'role'       => 'customer',
            ];

            $new_user_id = wp_insert_user($user_args);
            if (is_wp_error($new_user_id)) {
                $results = [
                    'success'     => false,
                    'status_code' => 400,
                    'message'     => $new_user_id->get_error_message()
                ];
                wp_send_json($results);
            }

            wp_set_auth_cookie($new_user_id);
            update_user_meta($new_user_id, 'billing_first_name',  $user_data['quote_first_name']);
            update_user_meta($new_user_id, 'billing_last_name',  $user_data['quote_last_name']);
            update_user_meta($new_user_id, 'billing_phone',  $user_data['quote_phone']);
            update_user_meta($new_user_id, 'billing_email', $user_email);
            update_user_meta($new_user_id, 'user_pass', $user_pass);

            $mail_data = [
                'username' => $user_name,
                'password' => $user_pass,
                'email'    => $user_email,
            ];
            $email->rnb_email_user_password($user_email, $mail_data);
        }

        // Create post object
        if (!isset($new_user_id)) {
            $new_user_id = get_current_user_id();
        }
        $my_post = [
            'post_title'  => date('Y-m-d H:i:s', current_time('timestamp', 1)),
            'post_status' => 'quote-pending',
            'post_type'   => 'request_quote',
            'post_author' => $new_user_id,
            'meta_input'   => array(
                'rnb_quote_need_view' => true,
            ),
        ];

        // Insert the post into the database
        $post_id = wp_insert_post($my_post);
        foreach ($form_data as $key => $meta) {
            if (isset($meta['name'])) {
                update_post_meta($post_id, $meta['name'], $meta['value']);
            }
        }

        $unformatted_form_data = $form_data;

        $resources = array();
        $categories = array();
        $deposits = array();

        if (isset($form_data) && is_array($form_data)) {
            foreach ($form_data as $key => $value) {
                if (isset($value['name']) && !empty($value['value'])) :
                    if ($value['name'] === 'extras[]') {
                        array_push($resources, $value['value']);
                        unset($form_data[$key]);
                    }
                    if ($value['name'] === 'security_deposites[]') {
                        array_push($deposits, $value['value']);
                        unset($form_data[$key]);
                    }
                    if ($value['name'] === 'categories[]') {
                        array_push($categories, $value['value']);
                        unset($form_data[$key]);
                    }
                endif;
            }
        }

        if (isset($resources) && !empty($resources)) {
            $extras = array();
            $extras['name'] = 'extras';
            $extras['value'] = $resources;
            $form_data[] = $extras;
        }

        if (isset($categories) && !empty($categories)) {
            $categories_ara = array();
            $categories_ara['name'] = 'categories';
            $categories_ara['value'] = $categories;
            $form_data[] = $categories_ara;
        }

        if (isset($deposits) && !empty($deposits)) {
            $deposits_ara = array();
            $deposits_ara['name'] = 'security_deposites';
            $deposits_ara['value'] = $deposits;
            $form_data[] = $deposits_ara;
        }

        $form_data = array_values($form_data);
        if ($post_id) {

            if (isset($_POST['quote_price'])) {
                update_post_meta($post_id, '_quote_price', $_POST['quote_price']);
            }

            update_post_meta($post_id, 'order_quote_meta', json_encode($form_data, JSON_UNESCAPED_UNICODE), true);
            update_post_meta($post_id, 'unformatted_order_quote_meta', json_encode($unformatted_form_data, JSON_UNESCAPED_UNICODE), true);
            update_post_meta($post_id, '_quote_user', $new_user_id, true);
            update_post_meta($post_id, '_product_id', $product_id, true);

            $to_email      = $user_email;
            $to_name       = $user_data['quote_first_name'] . ' ' . $user_data['quote_last_name'];
            $reply_message = $user_data['quote_message'];

            global $current_user;

            $data = array(
                'comment_post_ID'      => $post_id,
                'comment_author'       => $current_user->user_nicename,
                'comment_author_email' => $current_user->user_email,
                'comment_author_url'   => $current_user->user_url,
                'comment_content'      => $reply_message,
                'comment_type'         => 'quote_message',
                'comment_parent'       => 0,
                'user_id'              => $new_user_id,
                'comment_author_IP'    => self::get_the_user_ip(),
                'comment_agent'        => $_SERVER['HTTP_USER_AGENT'],
                'comment_date'         =>  current_time('mysql'),
                'comment_approved'     => 1,
            );

            $comment_id = wp_insert_comment($data);
            $admin_profile = rnb_get_quote_admin_profile();

            $from_email = $admin_profile['email'];
            $from_name  = $admin_profile['name'];

            // To info
            $subject = esc_html__("Your quote request has been placed", 'redq-rental');
            $data_object = array(
                'reply_message' => $reply_message,
                'quote_id'      => $post_id,
            );

            // Send the mail to the customer

            $email->customer_place_quote_request($to_email, $subject, $from_email, $from_name, $data_object);

            // Send the mail to the owner
            $to_email_owner = $from_email;
            $subject_owner = esc_html__('You have a new quote request', 'redq-rental');
            $from_email_customer = $to_email;
            $from_name_customer = $to_name;

            $email->owner_notify_place_quote_request($to_email_owner, $subject_owner, $from_email_customer, $from_name_customer, $data_object);

            $results = [
                'success'     => true,
                'status_code' => 200,
                'message'     => esc_html__('Thanks! Your email has been sent.', 'redq-rental')
            ];
            wp_send_json($results);
        }

        wp_die();
    }

    public function rfq_assets()
    {
        $scripts = $this->get_front_scripts();
        $styles  = $this->get_front_styles();

        foreach ($scripts as $handle => $script) {
            if (isset($script['scope']) && in_array('rfq', $script['scope'])) {
                wp_enqueue_script($handle);
            }
        }

        foreach ($styles as $handle => $style) {
            if (isset($script['scope']) && in_array('rfq', $script['scope'])) {
                wp_enqueue_style($handle);
            }
        }

        $translated_strings = rnb_get_translated_strings();
        wp_localize_script('rnb-rfq', 'RFQ_DATA', [
            'ajax_url'           => rnb_get_ajax_url(),
            'translated_strings' => $translated_strings,
            'enable_gdpr'        =>  'yes', //is_gdpr_enable($product_id)
        ]);

        wp_localize_script('rnb-quote', 'RFQ_QUOTE', [
            'ajax_url' => rnb_get_ajax_url(),
            'nonce'    => wp_create_nonce('rnb_rfq_nonce'),
        ]);
    }
    public function rfq_guest_checkout_form($atts, $content = null) {
        // Start output buffering at the very beginning
        ob_start();
        
        $is_display_form = isset($_GET['rfq_checkout']) ? $_GET['rfq_checkout'] : false;
        $product_id = isset($_GET['product_id']) ? $_GET['product_id'] : false;
        $quote_id = isset($_GET['quote_id']) ? $_GET['quote_id'] : false;
        $redirect = false;
        $has_order = get_post_meta($quote_id, '_rnb_rfq_order_id', true);
        $redirect_url = home_url();
        // Redirect to home if any required parameter is missing
        if (!$is_display_form || !$product_id || !$quote_id) {
           $redirect = true;
        }
        $checkout_page_url = wc_get_checkout_url();

        if(!empty($has_order)){
            $redirect = true;
            $redirect_url = $checkout_page_url;
        }
    
        $this->rfq_assets();
        ?>
        <form action="" method="post" class="rfq-to-cart" name="rfq-to-cart">
            <input type="hidden" value="<?php echo esc_attr($product_id); ?>" name="product_id">
            <input type="hidden" value="<?php echo esc_attr($quote_id); ?>" name="quote_id">
            <input type="hidden" value="<?php echo esc_url($checkout_page_url); ?>" name="checkout_url">
            <input type="hidden" value="<?php echo esc_attr($redirect); ?>" name="quote_redirect_home" data-redirect="<?php echo esc_url($redirect_url); ?>" class="rfq-home-redirect">
            <!-- <input type="submit" value="<?php echo esc_html__('Submit', 'redq-rental'); ?>"> -->
            <button style="width: 100px; background: transparent; border:0; max-width: 100%; display: block; margin: auto">
                <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'><circle fill='#A99E9C' stroke='#A99E9C' stroke-width='15' r='15' cx='40' cy='65'><animate attributeName='cy' calcMode='spline' dur='2' values='65;135;65;' keySplines='.5 0 .5 1;.5 0 .5 1' repeatCount='indefinite' begin='-.4'></animate></circle><circle fill='#A99E9C' stroke='#A99E9C' stroke-width='15' r='15' cx='100' cy='65'><animate attributeName='cy' calcMode='spline' dur='2' values='65;135;65;' keySplines='.5 0 .5 1;.5 0 .5 1' repeatCount='indefinite' begin='-.2'></animate></circle><circle fill='#A99E9C' stroke='#A99E9C' stroke-width='15' r='15' cx='160' cy='65'><animate attributeName='cy' calcMode='spline' dur='2' values='65;135;65;' keySplines='.5 0 .5 1;.5 0 .5 1' repeatCount='indefinite' begin='0'></animate></circle></svg>
            </button>
        </form>
        <?php
        return ob_get_clean();
    }
    public static function get_guest_checkout_id () {
        $page_title = 'Quote Checkout Redirect';
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'name' => sanitize_title($page_title),
            'posts_per_page' => 1,
            'fields' => 'ids' // Only get the ID to improve performance
        );
        $posts = get_posts($args);
        if(isset($posts[0])){
            return $posts[0];
        }
        return '';
    }
}

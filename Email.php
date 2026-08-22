<?php

namespace REDQ_RnB;

class Email
{

    public function __construct()
    {
        add_action('request_quote_item_details_template', array($this, 'request_quote_item_details_template'));
    }

    public function rnb_email_user_password($to, $mail_data)
    {
        $subject = esc_html__('User password for Request for Quote', 'redq-rental');

        ob_start();
        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/user-password.php');
        $message = ob_get_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        wp_mail($to, $subject, $message, $headers);
    }

    public function quote_accepted_notify_customer($to, $subject, $from, $from_name, $data_object)
    {
        ob_start();

        $heading = $subject;
        $quote_id = $data_object['quote_id'];
        $quote = $this->request_quote_item_details($quote_id);

        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/quote-accepted-notify-customer.php');

        $message = ob_get_contents();

        ob_end_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        // Filters for the email
        add_filter('wp_mail_from', function ($email) use ($from) {
            return $from;
        });
        add_filter('wp_mail_from_name', function ($name) use ($from_name) {
            return $from_name;
        });
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // extract($args);
        // Send
        wp_mail($to, $subject, $message, $headers);

        // Unhook filters
        remove_filter('wp_mail_from', array($this, 'get_from_address'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    public function quote_status_update_notify_customer($to, $subject, $from, $from_name, $data_object)
    {
        ob_start();

        $heading = $subject;
        $quote_id = $data_object['quote_id'];
        $quote = $this->request_quote_item_details($quote_id);

        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/quote-status-update-notify-customer.php');

        $message = ob_get_contents();

        ob_end_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        // Filters for the email
        add_filter('wp_mail_from', function ($email) use ($from) {
            return $from;
        });
        add_filter('wp_mail_from_name', function ($name) use ($from_name) {
            return $from_name;
        });
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // extract($args);
        // Send
        wp_mail($to, $subject, $message, $headers);

        // Unhook filters
        remove_filter('wp_mail_from', array($this, 'get_from_address'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    public function owner_reply_message($to, $subject, $from, $from_name, $data_object)
    {
        ob_start();

        $heading = $subject;
        $reply_message = stripslashes($data_object['reply_message']);
        $quote_id = $data_object['quote_id'];
        $quote = $this->request_quote_item_details($quote_id);

        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/owner-reply-message.php');

        $message = ob_get_contents();

        ob_end_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        // Filters for the email
        add_filter('wp_mail_from', function ($email) use ($from) {
            return $from;
        });
        add_filter('wp_mail_from_name', function ($name) use ($from_name) {
            return $from_name;
        });
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // extract($args);
        // Send
        wp_mail($to, $subject, $message, $headers);

        // Unhook filters
        remove_filter('wp_mail_from', array($this, 'get_from_address'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    public function customer_reply_message($to, $subject, $from, $from_name, $data_object)
    {
        ob_start();

        $heading = $subject;
        $reply_message = stripslashes($data_object['reply_message']);
        $quote_id = $data_object['quote_id'];
        $quote = $this->request_quote_item_details($quote_id);

        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/customer-reply-message.php');

        $message = ob_get_contents();

        ob_end_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        // Filters for the email
        add_filter('wp_mail_from', function ($email) use ($from) {
            return $from;
        });
        add_filter('wp_mail_from_name', function ($name) use ($from_name) {
            return $from_name;
        });
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // extract($args);
        // Send
        wp_mail($to, $subject, $message, $headers);

        // Unhook filters
        remove_filter('wp_mail_from', array($this, 'get_from_address'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    public function customer_place_quote_request($to, $subject, $from, $from_name, $data_object)
    {
        ob_start();

        $heading = $subject;
        $reply_message = stripslashes($data_object['reply_message']);
        $quote_id = $data_object['quote_id'];

        $quote = $this->request_quote_item_details($quote_id);


        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/customer-place-quote-request.php');

        $message = ob_get_contents();

        ob_end_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        // Filters for the email
        add_filter('wp_mail_from', function ($email) use ($from) {
            return $from;
        });
        add_filter('wp_mail_from_name', function ($name) use ($from_name) {
            return $from_name;
        });
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // extract($args);
        // Send
        wp_mail($to, $subject, $message, $headers);

        // Unhook filters
        remove_filter('wp_mail_from', array($this, 'get_from_address'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    public function owner_notify_place_quote_request($to, $subject, $from, $from_name, $data_object)
    {
        ob_start();

        $heading = $subject;
        $reply_message = stripslashes($data_object['reply_message']);
        $quote_id = $data_object['quote_id'];

        $quote = $this->request_quote_item_details($quote_id);

        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/owner-notify-place-quote-request.php');

        $message = ob_get_contents();

        ob_end_clean();

        //Prepare headers for HTML
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

        // Filters for the email
        add_filter('wp_mail_from', function ($email) use ($from) {
            return $from;
        });
        add_filter('wp_mail_from_name', function ($name) use ($from_name) {
            return $from_name;
        });
        add_filter('wp_mail_content_type', array($this, 'get_content_type'));

        // extract($args);
        // Send
        wp_mail($to, $subject, $message, $headers);

        // Unhook filters
        remove_filter('wp_mail_from', array($this, 'get_from_address'));
        remove_filter('wp_mail_from_name', array($this, 'get_from_name'));
        remove_filter('wp_mail_content_type', array($this, 'get_content_type'));
    }

    public function request_quote_item_details($quote_id)
    {

        $quote = array(
            'id'                  => $quote_id,
            'product_id'          => $this->get_quote_product_id($quote_id),
            'product_title'       => $this->get_quote_product_title($quote_id),
            'product_url'         => $this->get_quote_product_url($quote_id),
            'form_data'           => $this->get_quote_form_data($quote_id),
            'customer_name'       => $this->get_quote_customer_name($quote_id),
            'customer_email'      => $this->get_quote_customer_email($quote_id),
            'customer_view_quote' => redq_get_view_quote_url($quote_id),
            'admin_view_quote'    => redq_get_view_quote_admin_url($quote_id),
            'quote_status'        => redq_get_quote_status_name(get_post_status($quote_id)),
        );

        return $quote;
    }

    public function request_quote_item_details_template($quote_id)
    {

        $quote = $this->request_quote_item_details($quote_id);

        extract($quote);

        include(RNB_PACKAGE_TEMPLATE_PATH . 'rnb/emails/request-quote-item-details.php');
    }

    public function get_quote_product_id($quote_id)
    {
        return get_post_meta($quote_id, 'add-to-cart', true);
    }

    public function get_quote_product_title($quote_id)
    {
        $product_id = $this->get_quote_product_id($quote_id);

        return get_the_title($product_id);
    }

    public function get_quote_product_url($quote_id)
    {
        $product_id = $this->get_quote_product_id($quote_id);

        return get_the_permalink($product_id);
    }

    public function get_quote_form_data($quote_id)
    {
        return json_decode(get_post_meta($quote_id, 'order_quote_meta', true), true);
    }

    public function get_quote_customer_details_form($quote_id)
    {
        $form_data = $this->get_quote_form_data($quote_id);

        $customer_details_form = array();
        foreach ($form_data as $key => $value) {

            if (isset($value['forms'])) {
                $customer_details_form = $value['forms'];
            }
        }

        return $customer_details_form;
    }

    public function get_quote_customer_name($quote_id)
    {
        $form_data = $this->get_quote_customer_details_form($quote_id);
        $customer_name = '';
        foreach ($form_data as $key => $value) {
            if ($key === 'quote_first_name') {
                $customer_name .= $value;
            }
            if ($key === 'quote_last_name') {
                $customer_name .= ' ' . $value;
            }
        }
        return $customer_name;
    }

    public function get_quote_customer_email($quote_id)
    {
        $form_data = $this->get_quote_customer_details_form($quote_id);
        foreach ($form_data as $key => $value) {
            if ($key === 'quote_email') {
                $customer_email = $value;
            }
        }
        return $customer_email;
    }

    public function get_content_type($content_type)
    {
        return 'text/html';
    }


    public function get_from_address()
    {

        $email_settings = get_option('up_email_settings');
        if (!empty($email_settings['fromEmailAddr']) && $email_settings['fromEmailAddr']) {
            return $email_settings['fromEmailAddr'];
        }

        return get_option('admin_email');
    }


    public function get_from_name()
    {

        $email_settings = get_option('up_email_settings');
        if (!empty($email_settings['fromName']) && $email_settings['fromName']) {
            return $email_settings['fromName'];
        }

        return get_bloginfo('name');
    }

    // public function up_get_email_template( $value, $args = array() ) {

    //  return include(UP_DIR.'/templates/emails/'.$value.'.php');
    // }
}

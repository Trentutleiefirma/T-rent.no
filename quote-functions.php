<?php

/**
 * Get all quote statuses.
 *
 * @return array
 * @since 2.2
 */
function redq_get_quote_statuses()
{
    $quote_statuses = array(
        'quote-pending'    => _x('Pending', 'Quote status', 'redq-rental'),
        'quote-processing' => _x('Processing', 'Quote status', 'redq-rental'),
        'quote-on-hold'    => _x('On Hold', 'Quote status', 'redq-rental'),
        'quote-completed'  => _x('Completed', 'Quote status', 'redq-rental'),
        'quote-cancelled'  => _x('Cancelled', 'Quote status', 'redq-rental'),
    );
    return apply_filters('rnb_quote_statuses', $quote_statuses);
}

/**
 * Get the nice name for an quote status.
 *
 * @param string $status
 * @return string
 * @since  2.2
 */
function redq_get_quote_status_name($status)
{
    $statuses = redq_get_quote_statuses();
    $status = 'quote-' === substr($status, 0, 6) ? substr($status, 6) : $status;
    $status = isset($statuses['quote-' . $status]) ? $statuses['quote-' . $status] : $status;

    return $status;
}


function redq_get_view_quote_url($quote_id)
{

    $view_quote_url = wc_get_endpoint_url('view-quote', $quote_id, wc_get_page_permalink('myaccount'));

    return apply_filters('rnb_get_view_qoute_url', $view_quote_url, $quote_id);
}

function redq_get_view_quote_admin_url($quote_id)
{

    $view_quote_admin_url = admin_url('post.php?post=' . $quote_id) . '&action=edit';

    return apply_filters('rnb_get_view_quote_admin_url', $view_quote_admin_url, $quote_id);
}

if (!function_exists('reqd_account_view_quote')) {

    /**
     * My Account > View order template.
     *
     * @param int $quote_id Order ID.
     */
    function reqd_account_view_quote($quote_id)
    {
        // WC_Shortcode_My_Account::view_order( absint( $quote_id ) );

        wc_get_template('myaccount/view-quote.php', array(
            'status'   => $status, // @deprecated 2.2
            'quote_id' => $quote_id
        ));
    }
}

/**
 * Exclude request for a quote comment
 */
add_filter('comments_clauses', 'exclude_request_quote_comments_clauses');
function exclude_request_quote_comments_clauses($clauses)
{
    global $wpdb, $typenow;

    if (!$clauses['join']) {
        $clauses['join'] = '';
    }

    if (!stristr($clauses['join'], "JOIN $wpdb->posts ON")) {
        $clauses['join'] .= " LEFT JOIN $wpdb->posts ON comment_post_ID = $wpdb->posts.ID ";
    }

    if ($clauses['where']) {
        $clauses['where'] .= ' AND ';
    }

    $clauses['where'] .= " $wpdb->posts.post_type NOT IN ('request_quote') ";

    return $clauses;
}

add_action('redq_rental_reply_submit', 'redq_rental_reply_submit');
function redq_rental_reply_submit()
{
    // if this fails, check_admin_referer() will automatically print a "failed" page and die.
    if (!empty($_POST) && check_admin_referer('quote_reply_action', 'quote_reply_nonce_field')) {
        // process form data

        if (isset($_POST['quote-reply-message']) && !empty($_POST['quote-reply-message'])) {

            global $current_user;
            $posted = $_POST;

            $quote_id = $posted['quote-reply-id'];
            $reply_message = $posted['quote-reply-message'];

            $time = current_time('mysql');

            $data = array(
                'comment_post_ID'      => $quote_id,
                'comment_author'       => $current_user->user_nicename,
                'comment_author_email' => $current_user->user_email,
                'comment_author_url'   => $current_user->user_url,
                'comment_content'      => $reply_message,
                'comment_type'         => 'quote_message',
                'comment_parent'       => 0,
                'user_id'              => $current_user->ID,
                'comment_author_IP'    => REDQ_RnB\RequestForQuote::get_the_user_ip(),
                'comment_agent'        => $_SERVER['HTTP_USER_AGENT'],
                'comment_date'         => $time,
                'comment_approved'     => 1,
            );

            $comment_id = wp_insert_comment($data);


            // send email to the product owner

            // $prodct_id = get_post_meta($quote_id, 'add-to-cart', true);
            // $to_author_id = get_post_field('post_author', $prodct_id);
            // $to_email = get_the_author_meta('user_email', $to_author_id);

            $admin_profile = rnb_get_quote_admin_profile();
            $to_email = $admin_profile['email'];

            // FROM info
            $from_author_id = get_post_field('post_author', $quote_id);
            $from_email = get_the_author_meta('user_email', $from_author_id);
            $from_name = get_the_author_meta('user_nicename', $from_author_id);

            $subject = __("New reply from customer quote request", "redq-rental");

            $data_object = array(
                'reply_message' => $reply_message,
                'quote_id'      => $quote_id,
            );

            // Send the mail to the customer
            $email = new REDQ_RnB\Email();
            $email->customer_reply_message($to_email, $subject, $from_email, $from_name, $data_object);

            // Reload the page again
            echo "<meta http-equiv='refresh' content='0'>";
        }
    }
}

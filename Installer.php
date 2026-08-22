<?php

namespace REDQ_RnB;

class Installer
{
    /**
     * Initialize class functions
     *
     * @return void
     */
    public function run()
    {
        $this->add_version();
        $this->create_tables();
        $this->rfq_endpoints();
        $this->rfq_statuses();
        $this->generate_rfq_checkout_redirect_page();
    }
    
    /**
     * Store plugin information
     *
     * @return void
     */
    public function add_version()
    {
        $installed = get_option('rnb_installed');

        if (!$installed) {
            update_option('rnb_installed', time());
        }

        update_option('rnb_version', RNB_VERSION);

        $uid_message = get_option('rnb_uid_error_message');
        if (empty($uid_message)) {
            update_option('rnb_uid_error_message', 'U29ycnkhIExpY2Vuc2Uga2V5IGlzIG5vdCBhY3RpdmF0ZWQ=');
        }
    }

    /**
     * Create custom tables
     *
     * @return void
     */
    public function create_tables()
    {
        global $wpdb;

        $wpdb->hide_errors();

        $collate = '';

        if ($wpdb->has_cap('collation')) {
            $collate = $wpdb->get_charset_collate();
        }

        $schema = "CREATE TABLE {$wpdb->prefix}rnb_availability (
                id BIGINT UNSIGNED NOT NULL auto_increment,
                pickup_datetime timestamp NULL,
                return_datetime timestamp NULL,
                rental_duration varchar(200) NULL,
                product_id BIGINT UNSIGNED NULL,
                inventory_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,
                item_id BIGINT UNSIGNED NULL,
                lang varchar(200) NOT NULL DEFAULT 'en',
				created_at timestamp NOT NULL DEFAULT 0,
                updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                block_by ENUM('FRONTEND_ORDER', 'BACKEND_ORDER', 'CUSTOM') NOT NULL DEFAULT 'CUSTOM',
                delete_status boolean DEFAULT 0 NOT NULL,
                PRIMARY KEY (id)
						) $collate;
				CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rnb_inventory_product (
                    inventory BIGINT UNSIGNED NOT NULL,
                    product BIGINT UNSIGNED NOT NULL,
					KEY inventory (inventory),
					KEY product (product)) $collate;";

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($schema);
    }

    /**
     * RFQ endpoints
     *
     * @return void
     */
    public function rfq_endpoints()
    {
        add_rewrite_endpoint('request-quote', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('view-quote', EP_ALL);
    }

    /**
     * RFQ statuses
     *
     * @return array
     */
    public static function rfq_statuses()
    {
        $quote_statuses = apply_filters(
            'redq_register_request_quote_post_statuses',
            [
                'quote-pending' => array(
                    'label'                     => _x('Pending', 'Quote status', 'redq-rental'),
                    'public'                    => false,
                    'protected'                 => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Pending <span class = "count">(%s)</span>', 'Pending <span class = "count">(%s)</span>', 'redq-rental')
                ),
                'quote-processing' => array(
                    'label'                     => _x('Processing', 'Quote status', 'redq-rental'),
                    'public'                    => false,
                    'protected'                 => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Processing <span class = "count">(%s)</span>', 'Processing <span class = "count">(%s)</span>', 'redq-rental')
                ),
                'quote-on-hold' => array(
                    'label'                     => _x('On Hold', 'Quote status', 'redq-rental'),
                    'public'                    => false,
                    'protected'                 => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('On Hold <span class = "count">(%s)</span>', 'On Hold <span class = "count">(%s)</span>', 'redq-rental')
                ),
                'quote-accepted' => array(
                    'label'                     => _x('Accepted', 'Quote status', 'redq-rental'),
                    'public'                    => false,
                    'protected'                 => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Accepted <span class = "count">(%s)</span>', 'Accepted <span class = "count">(%s)</span>', 'redq-rental')
                ),
                'quote-completed' => array(
                    'label'                     => _x('Completed', 'Quote status', 'redq-rental'),
                    'public'                    => false,
                    'protected'                 => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Completed <span class = "count">(%s)</span>', 'Completed <span class = "count">(%s)</span>', 'redq-rental')
                ),
                'quote-cancelled' => array(
                    'label'                     => _x('Cancelled', 'Quote status', 'redq-rental'),
                    'public'                    => false,
                    'protected'                 => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Cancelled <span class = "count">(%s)</span>', 'Cancelled <span class = "count">(%s)</span>', 'redq-rental')
                ),
            ]
        );

        foreach ($quote_statuses as $quote_status => $values) {
            register_post_status($quote_status, $values);
        }
    }
    /**
     * RFQ Redirect Gust Checkout page
     */
    public function generate_rfq_checkout_redirect_page() {
        $page_title = 'Quote Checkout Redirect';
        $page_content = '[rfq_gust_checkout_form]'; // Add your shortcode here
        $page_template = ''; // Optional: Specify a page template
    
        // Check if the page already exists using get_posts
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'name' => sanitize_title($page_title),
            'posts_per_page' => 1,
            'fields' => 'ids' // Only get the ID to improve performance
        );
    
        $posts = get_posts($args);
    
        // If the page doesn't exist, create it
        if (empty($posts)) {
            $page = array(
                'post_title'    => $page_title,
                'post_content'  => $page_content,
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_author'   => 1,
                'post_name'     => sanitize_title($page_title),
                'post_template' => $page_template,
            );
    
            // Insert the page into the database
            wp_insert_post($page);
        }
    
    }
}

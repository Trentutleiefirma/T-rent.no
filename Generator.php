<?php

namespace REDQ_RnB\Admin;


use REDQ_RnB\Installer;
use REDQ_RnB\Traits\Admin_Trait;

/**
 * Class WC_Redq_Rental_Post_Types
 *
 * @author      RedQTeam
 * @category    Admin
 * @package     RnB\Admin
 * @version     1.0.3
 * @since       1.0.3
 */
if (!defined('ABSPATH')) {
    exit;
}

class Generator
{
    use Admin_Trait;

    public function __construct()
    {
        add_action('init', [$this, 'register_post_types'], 10, 1);
        add_action('save_post', [$this, 'inventory_save_post']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('pre_get_posts', [$this, 'quote_pre_get_posts'], 1);
        add_filter('manage_request_quote_posts_columns', [$this, 'rfq_head']);
        add_action('manage_request_quote_posts_custom_column', [$this, 'rfq_content'], 10, 2);
        add_filter('page_row_actions', [$this, 'remove_row_actions'], 10, 2);
    }

    /**
     * Handle Post Type, Taxonomy, Term Meta
     *
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function register_post_types()
    {
        $labels = array(
            'name'               => __('Inventories', 'redq-rental'),
            'singular_name'      => __('Inventory', 'redq-rental'),
            'menu_name'          => __('Inventory', 'redq-rental'),
            'name_admin_bar'     => __('Inventory', 'redq-rental'),
            'add_new'            => __('Add New Inventory', 'redq-rental'),
            'add_new_item'       => __('Add New Inventory', 'redq-rental'),
            'new_item'           => __('New Inventory', 'redq-rental'),
            'edit_item'          => __('Edit Inventory', 'redq-rental'),
            'view_item'          => __('View Inventory', 'redq-rental'),
            'all_items'          => __('All inventory', 'redq-rental'),
            'search_items'       => __('Search inventory', 'redq-rental'),
            'parent_item_colon'  => __('Parent inventory:', 'redq-rental'),
            'not_found'          => __('No inventory found.', 'redq-rental'),
            'not_found_in_trash' => __('No inventory found in Trash.', 'redq-rental')
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('Description.', 'redq-rental'),
            'public'             => true,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'inventory'),
            'capability_type'    => 'post',
            'menu_icon'          => 'dashicons-image-filter',
            'has_archive'        => false,
            'hierarchical'       => true,
            'menu_position'      => 57,
            'supports'           => array('title', 'thumbnail'),
            'capability_type'    => 'post',
            'capabilities'       => array( // 'create_posts' => 'do_not_allow', // Removes support for the "Add New" function ( use 'do_not_allow' instead of false for multisite set ups )
            ),
            'map_meta_cap'       => true,
        );

        register_post_type('inventory', $args);

        //Register taxonomies
        $taxonomy_args = rnb_get_inventory_taxonomies();
        if (sizeof($taxonomy_args)) {
            foreach ($taxonomy_args as $key => $taxonomy_arg) {
                $this->redq_register_inventory_taxonomies($taxonomy_arg['taxonomy'], $taxonomy_arg['label'], $taxonomy_arg['post_type']);
            }
        }

        //Initialize taxonomies term meta
        $this->redq_rental_initialize_taxonomy_term_meta();

        $labels = array(
            'name'               => _x('Quote Request', 'post type general name', 'redq-rental'),
            'singular_name'      => _x('Quote Request', 'post type singular name', 'redq-rental'),
            'menu_name'          => _x('Quote', 'admin menu', 'redq-rental'),
            'name_admin_bar'     => _x('Quote Request', 'add new on admin bar', 'redq-rental'),
            'add_new'            => _x('Add New', 'request_quote', 'redq-rental'),
            'add_new_item'       => __('Add New Quote', 'redq-rental'),
            'new_item'           => __('New Quote Request', 'redq-rental'),
            'edit_item'          => __('Edit Quote Request', 'redq-rental'),
            'view_item'          => __('View Quote Request', 'redq-rental'),
            'all_items'          => __('All Quotes', 'redq-rental'),
            'search_items'       => __('Search Quote', 'redq-rental'),
            'parent_item_colon'  => __('Parent Quote:', 'redq-rental'),
            'not_found'          => __('No Quote found.', 'redq-rental'),
            'not_found_in_trash' => __('No Quote found in Trash.', 'redq-rental')
        );

        $args = array(
            'labels'          => $labels,
            'description'     => __('Description.', 'redq-rental'),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'query_var'       => true,
            'rewrite'         => array('slug' => 'request_quote'),
            'capability_type' => 'post',
            'menu_icon'       => 'dashicons-awards',
            'has_archive'     => false,
            'hierarchical'    => true,
            'menu_position'   => 57,
            'supports'        => array(''),
            'map_meta_cap'    => true, //After disabling new qoute capabilities if this is not set then row actions are disabled. So no edit or trash will be availabe.
            'capabilities'    => array(
                'create_posts' => false  //Removing Add new quote capabilities
            ),
        );

        register_post_type('request_quote', $args);

        Installer::rfq_statuses();
    }

    /**
     * Only Inventory Save Posts
     *
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function inventory_save_post($post_id)
    {
        $post_type = get_post_type($post_id);

        if ($post_type !== 'inventory') {
            return;
        }

        global $wpdb;

        $tablename       = $wpdb->prefix . 'rnb_availability';
        $update_payloads = [];
        $payloads        = [];

        if (isset($_POST['redq_availability_block_by']) && isset($_POST['redq_availability_pickup_datetime']) && isset($_POST['redq_availability_dropoff_datetime']) && isset($_POST['redq_availability_row_id'])) {
            $row_id          = $_POST['redq_availability_row_id'];
            $block_by        = $_POST['redq_availability_block_by'];
            $pickup_datetime = $_POST['redq_availability_pickup_datetime'];
            $return_datetime = $_POST['redq_availability_dropoff_datetime'];

            for ($i = 0; $i < sizeof($block_by); $i++) {
                if (!empty($row_id[$i])) {
                    $update_payloads[$i]['id']              = $row_id[$i];
                    $update_payloads[$i]['block_by']        = $block_by[$i];
                    $update_payloads[$i]['pickup_datetime'] = $pickup_datetime[$i];
                    $update_payloads[$i]['return_datetime'] = $return_datetime[$i];
                    $update_payloads[$i]['rental_duration'] = rnb_calculate_date_difference($pickup_datetime[$i], $return_datetime[$i]);
                } else {
                    // CREATE
                    $payloads[$i]['block_by'] = $block_by[$i];
                    $payloads[$i]['pickup_datetime'] = $pickup_datetime[$i];
                    $payloads[$i]['return_datetime'] = $return_datetime[$i];
                    $payloads[$i]['rental_duration'] = rnb_calculate_date_difference($pickup_datetime[$i], $return_datetime[$i]);
                    $payloads[$i]['inventory_id'] = $post_id;
                }
            }
        }

        //Update Existing Rows
        if (count($update_payloads)) {
            foreach ($update_payloads as $payload) {
                $row_id = $payload['id'];
                unset($payload['id']);
                $wpdb->update($tablename, $payload, ['id' => $row_id]);
            }
        }

        //Delete rows
        if (isset($_POST['redq_availability_remove_id'])) {
            $remove_id = json_decode($_POST['redq_availability_remove_id']);
            if (!empty($remove_id) && is_array($remove_id) && count($remove_id) > 0) {
                foreach ($remove_id as $id) {
                    $wpdb->delete($tablename, array('id' => $id));
                }
            }
        }

        //Create rows
        $products_by_inventory = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rnb_inventory_product WHERE inventory = $post_id", ARRAY_A);
        if (isset($products_by_inventory) && !empty($products_by_inventory)) {
            foreach ($products_by_inventory as $key => $product_by_inventory) {
                $product_id = $product_by_inventory['product'];

                if (!count($payloads)) {
                    continue;
                }

                $values = $place_holders = [];

                foreach ($payloads as $data) {
                    array_push($values, $data['block_by'], $data['pickup_datetime'], $data['return_datetime'], $data['rental_duration'], $data['inventory_id'], $product_id, null, null);
                    $place_holders[] = "( %s, %s, %s, %s, %d, %d, %d, %d)";
                }

                rnb_custom_date_insert($place_holders, $values);
            }
        }
        //End database insertion
    }

    /**
     * Availability management meta box define
     * @param callback redq_inventory_availability_control_cb, id redq_inventory_availability_control
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function register_meta_boxes()
    {
        remove_meta_box('submitdiv', 'request_quote', 'side');
        add_meta_box(
            'redq_request_for_a_quote_control',
            __('Request For A Quote Management', 'redq-rental'),
            'redq_request_for_a_quote_control_cb',
            'request_quote',
            'normal',
            'low'
        );

        add_meta_box(
            'redq_inventory_quantity',
            __('Inventory Management', 'redq-rental'),
            'redq_inventory_management_cb',
            'inventory',
            'normal',
            'high'
        );

        add_meta_box(
            'redq_inventory_availability_control',
            __('Availability Management', 'redq-rental'),
            'redq_inventory_availability_control_cb',
            'inventory',
            'normal',
            'low'
        );

        add_meta_box(
            'redq_request_for_a_quote_save',
            __('Quote Actions', 'redq-rental'),
            'redq_request_for_a_quote_save_cb',
            'request_quote',
            'side',
            'high'
        );

        add_meta_box(
            'redq_request_for_a_quote_message',
            __('Request For A Quote Message', 'redq-rental'),
            'redq_request_for_a_quote_message_cb',
            'request_quote',
            'normal',
            'high'
        );

        add_meta_box(
            'product_inventory_mapping',
            __('Attached Inventories', 'redq-rental'),
            'product_inventory_mapping_cb',
            'product',
            'side',
            'high'
        );

        add_meta_box(
            'inventory_product_mapping',
            __('Attached Products', 'redq-rental'),
            'inventory_product_mapping_cb',
            'inventory',
            'side',
            'high'
        );
    }

    /**
     * Quote pre get posts
     *
     * @param object $query
     * @return void
     */
    public function quote_pre_get_posts($query)
    {
        if (is_admin() && $query->query['post_type'] == 'request_quote') {
            if (!isset($query->query['post_status']) && empty($query->query['post_status'])) {
                $query->set('post_status', array('quote-pending', 'quote-processing', 'quote-on-hold', 'quote-accepted', 'quote-completed', 'quote-cancelled'));
            }
            $query->set('order', 'DESC');
        }
    }

    /**
     * RFQ heads
     *
     * @param array $defaults
     * @return array
     */
    public function rfq_head($defaults)
    {
        unset($defaults['title']);
        unset($defaults['date']);

        $defaults['quote']             = esc_html__('Quote', 'redq-rental');
        $defaults['status']            = esc_html__('Status', 'redq-rental');
        $defaults['new']               = '';
        $defaults['product']           = esc_html__('Product', 'redq-rental');
        $defaults['email']             = esc_html__('Email', 'redq-rental');
        $defaults['pickup_date']       = esc_html__('Pickup Date', 'redq-rental');
        $defaults['dropoff_date']      = esc_html__('Dropoff Date', 'redq-rental');
        $defaults['quote_total_price'] = esc_html__('Quote Total Price	', 'redq-rental');
        $defaults['date']              = esc_html__('Date', 'redq-rental');

        return $defaults;
    }

    /**
     * Show All corresponding value for each column
     *
     * @param string $column_name
     * @param int $post_ID
     * @return void
     */
    public function rfq_content($column_name, $post_ID)
    {
        $order_quote_meta = json_decode(stripslashes(get_post_meta($post_ID, 'order_quote_meta', true)), true);

        if (is_null($order_quote_meta)) {
            $order_quote_meta = json_decode(get_post_meta($post_ID, 'order_quote_meta', true), true);
        }
        $forms = array();
        foreach ($order_quote_meta as $key => $meta) {

            if (array_key_exists('forms', $meta)) {
                $forms = $meta['forms'];
            }
        }
        if ($column_name == 'new') {
            $new_text = get_post_meta($post_ID, 'rnb_quote_need_view', true);
            if($new_text){
                $text = esc_html__('New', 'redq-rental');
                printf('<span style="color: #fff; background: red; padding: 5px; border-radius: 3px">%s</span>', $text);
            }
        }
        if ($column_name == 'quote') { ?>
            <p>
                <a href="<?php get_admin_url() ?>post.php?post=<?php echo $post_ID ?>&amp;action=edit"><strong><?php echo '#' . $post_ID ?></strong></a> <?php esc_html_e('by', 'redq-rental') ?> <?php echo $forms['quote_first_name'] . ' ' . $forms['quote_last_name'] ?>
            </p>
        <?php }

        if ($column_name == 'status') {
            echo ucfirst(substr(get_post($post_ID)->post_status, 6));
        }
        if ($column_name == 'product') {
            $product_id = get_post_meta($post_ID, 'add-to-cart', true);
            $product_title = get_the_title($product_id);
            $product_url = get_the_permalink($product_id); ?>
            <a href="<?php echo esc_url($product_url) ?>" target="_blank"><?php echo $product_title ?></a>
            <?php
        }
        if ($column_name == 'date') {
            echo get_post($post_ID)->date;
        }
        if ($column_name == 'email') {
            foreach ($order_quote_meta as $meta) {
                if (isset($meta['forms'])) {
                    $contacts = $meta['forms'];
                    if(isset($contacts['quote_email'])){ ?>
                            <a href="mailto:<?php echo $contacts['quote_email'] ?>"><?php echo $contacts['quote_email'] ?></a>
                  <?php  }
                }
            }
        }
        if ($column_name == 'pickup_date') {
            $date_time = [];
            foreach ($order_quote_meta as $meta) {
                if(isset($meta['name'])){
                    if($meta['name'] == 'pickup_date'){
                        $date_time['data_date'] = $meta['value'];
                    }
                    if($meta['name'] == 'pickup_time'){
                        $date_time['data_time'] = $meta['value'];
                    }
                }                
            }
            if(!empty($date_time)){
                $date = isset($date_time['data_date'])? $date_time['data_date'] : '';
                $time = isset($date_time['data_time'])? $date_time['data_time'] : '';
                $dateTimeString = $date . ' ' . $time;
                $timestamp = strtotime($dateTimeString);
                $formattedDate= date_i18n('Y/m/d \a\t h:i a', $timestamp);
                echo esc_html($formattedDate);
            }
        }
        if ($column_name == 'dropoff_date') {
            $date_time = [];
            foreach ($order_quote_meta as $meta) {
                if(isset($meta['name'])){
                    if($meta['name'] == 'dropoff_date'){
                        $date_time['data_date'] = $meta['value'];
                    }
                    if($meta['name'] == 'dropoff_time'){
                        $date_time['data_time'] = $meta['value'];
                    }
                }                
            }
            if(!empty($date_time)){
                $date = isset($date_time['data_date'])? $date_time['data_date'] : '';
                $time = isset($date_time['data_time'])? $date_time['data_time'] : '';
                $dateTimeString = $date . ' ' . $time;
                $timestamp = strtotime($dateTimeString);
                $formattedDate= date_i18n('Y/m/d \a\t h:i a', $timestamp);
                echo esc_html($formattedDate);
            }
        }
        if ($column_name == 'quote_total_price') {
            $price = [];
            foreach ($order_quote_meta as $meta) {
                if(isset($meta['name'])){
                    if($meta['name'] == 'quote_price'){
                        $price['price'] = $meta['value'];
                    }
                    if($meta['name'] == 'inventory_quantity'){
                        $price['qty'] = $meta['value'];
                    }
                }                
            }
            $quote_price = isset($price['price']) ? $price['price'] : '';
            $qty   = isset($price['qty']) ? $price['qty'] : '';
            $total_price = (int) $quote_price * (int) $qty;
            if(function_exists('wc_price')){
               $price_html =  sprintf(
                    __('%sx %s = %s', 'redq-rental'),
                    wc_price($quote_price),
                    $qty,
                    wc_price($total_price)
                );
                echo  wp_kses_post($price_html);
            }
        }
    }

    /**
     * Remove Quick Edit from Row Actions
     *
     * @param array $actions
     * @param object $post
     * @return arrau
     */
    public function remove_row_actions($actions, $post)
    {
        if ($post->post_type == 'request_quote' && isset($actions['inline hide-if-no-js'])) {
            unset($actions['inline hide-if-no-js']);
        }

        return $actions;
    }
}

<?php

namespace REDQ_RnB\Admin;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Admin\Table;
use Automattic\WooCommerce\Internal\Admin\Orders\ListTable;
use WC_Order;
use WP_List_Table;
use REDQ_RnB\Traits\Order_Trait;
use REDQ_RnB\Traits\Rental_Data_Trait;

class Orders_List_Table extends WP_List_Table
{
    use Order_Trait, Rental_Data_Trait;
    /**
     * Order type.
     *
     * @var string
     */
    private $order_type;
    /**
     * The data store-agnostic list table implementation (introduced to support custom order tables),
     * which we use here to render columns.
     *
     * @var ListTable $orders_list_table
     */
    private $orders_list_table;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        add_action('admin_footer', array($this, 'order_preview_template'));
        add_action('admin_footer', array($this, 'enqueue_scripts'));
    }

    /**
     * Prepares the list of items for displaying.
     */
    public function prepare_items()
    {
        $current_page = $this->get_pagenum();
        $per_page = get_option('rnb_admin_order_list_per_page', get_option('posts_per_page'));
        $order_status = isset($_GET['status']) ? $_GET['status'] : 'all';
        $total_page = $this->total_rental_order($order_status);
        /**
         * Get item data
         */
        $order_ids = $this->fetch_rental_orders($per_page, $current_page, $this->post_order());

        if (isset($_REQUEST['s'])) {
            $order = $this->get_search_query($_REQUEST['s'], $current_page, $per_page, $this->post_order());
            $order_ids = $order['results'];
            $total_page = $order['total_count'];
        }
        $this->items = $this->get_orders($order_ids);

        /**
         * Table pagination 
         */
        $this->set_pagination_args([
            "total_items" => $total_page,
            "per_page" => $per_page
        ]);
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
    }

    /**
     * Message to be displayed when there are no items
     *
     * @since 11.0.7
     */
    public function no_items()
    {
        if (empty($this->items)) {
            $links = '<a href="' . admin_url('/admin.php?page=wc-settings&tab=advanced&section=features') . '" target="_blank">' . __('Order data storage', 'inspect') . '</a>';
            echo sprintf(__('No items found. Please check %s and sync pending orders', 'redq-rental'), $links);
            return;
        }

        _e('No items found.', 'redq-rental');
    }

    /**
     * Get hidden columns list 
     * @return array
     */
    public function get_search_query($data, $page_number = 1, $per_page = 10, $args = [])
    {
        global $wpdb;
        $search_term = sanitize_text_field($data);
        $page_number = isset($args['page_number']) ? intval($args['page_number']) : $page_number;
        $per_page = isset($args['per_page']) ? intval($args['per_page']) : $per_page;

        // Parameters for dynamic ordering
        $order = isset($args['order']) ? $args['order'] : 'DESC';
        $orderby = isset($args['orderby']) ? $args['orderby'] : 'wp_posts.ID';
        if ($orderby == 'p.total_amount') {
            $orderby = 'total_amount';
        }

        // Generate a unique key for the cache based on parameters
        $offset = (int)($page_number - 1) * (int)$per_page;

        // Modified query to select count
        $count_query = "
            SELECT COUNT(DISTINCT wp_posts.ID) as total_count
            FROM  wp_posts
                INNER JOIN {$wpdb->prefix}woocommerce_order_items ON wp_posts.ID = wp_woocommerce_order_items.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta ON wp_woocommerce_order_itemmeta.order_item_id = wp_woocommerce_order_items.order_item_id
                INNER JOIN {$wpdb->prefix}term_relationships ON wp_woocommerce_order_itemmeta.meta_value = wp_term_relationships.object_id
                INNER JOIN {$wpdb->prefix}term_taxonomy ON wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id
                INNER JOIN {$wpdb->prefix}terms ON wp_term_taxonomy.term_id = wp_terms.term_id
                INNER JOIN {$wpdb->prefix}wc_order_addresses ON wp_posts.ID = wp_wc_order_addresses.order_id
                INNER JOIN {$wpdb->prefix}wc_orders ON wp_posts.ID = wp_wc_orders.id
                WHERE post_type IN ('shop_order_placehold', 'shop_order')
                AND status != 'trash'
                AND slug = 'redq_rental'
                AND (
                    address_1 LIKE '%{$search_term}%' OR 
                    address_2 LIKE '%{$search_term}%' OR 
                    first_name LIKE '%{$search_term}%' OR
                    order_item_name LIKE '%{$search_term}%' OR
                    last_name LIKE '%{$search_term}%' OR
                    company LIKE '%{$search_term}%' OR
                    email LIKE '%{$search_term}%' OR
                    country LIKE '%{$search_term}%' OR
                    wp_posts.ID = '{$search_term}'  -- Added condition for order ID
                )
        ";

        $total_count = $wpdb->get_var($count_query);

        // Original query for results
        $query = "
            SELECT wp_posts.ID
            FROM  wp_posts
                INNER JOIN {$wpdb->prefix}woocommerce_order_items ON wp_posts.ID = wp_woocommerce_order_items.order_id
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta ON wp_woocommerce_order_itemmeta.order_item_id = wp_woocommerce_order_items.order_item_id
                INNER JOIN {$wpdb->prefix}term_relationships ON wp_woocommerce_order_itemmeta.meta_value = wp_term_relationships.object_id
                INNER JOIN {$wpdb->prefix}term_taxonomy ON wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id
                INNER JOIN {$wpdb->prefix}terms ON wp_term_taxonomy.term_id = wp_terms.term_id
                INNER JOIN {$wpdb->prefix}wc_order_addresses ON wp_posts.ID = wp_wc_order_addresses.order_id
                INNER JOIN {$wpdb->prefix}wc_orders ON wp_posts.ID = wp_wc_orders.id
                WHERE post_type IN ('shop_order_placehold', 'shop_order')
                AND status != 'trash'
                AND slug = 'redq_rental'
                AND (
                    address_1 LIKE '%{$search_term}%' OR 
                    address_2 LIKE '%{$search_term}%' OR 
                    first_name LIKE '%{$search_term}%' OR
                    order_item_name LIKE '%{$search_term}%' OR
                    last_name LIKE '%{$search_term}%' OR
                    company LIKE '%{$search_term}%' OR
                    email LIKE '%{$search_term}%' OR
                    country LIKE '%{$search_term}%' OR
                    wp_posts.ID = '{$search_term}'  -- Added condition for order ID
                )
            GROUP BY wp_posts.ID
            ORDER BY {$orderby} {$order}
            LIMIT {$per_page} OFFSET {$offset}
        ";

        $results = $wpdb->get_results($query, ARRAY_A);
        $results = array_map(function ($item) {
            return array('order_id' => $item['ID']);
        }, $results);

        return array(
            'total_count' => $total_count,
            'results' => $results,
        );
    }

    public function get_hidden_columns()
    {
        $default_columns = $this->get_columns();
        $display = get_option('rnb_screen_order_columns', array_keys($default_columns));
        $get_invisible_data = array_diff(array_flip($default_columns), $display);
        $new_data = array_flip($get_invisible_data);

        if (array_key_exists('cb', $new_data)) {
            unset($new_data['cb']);
        }
        $hidden_list = array_keys($new_data);

        return $hidden_list;
    }

    /**
     * Render the table.
     *
     * @return void
     */
    public function display()
    {
        /**
         * Get screens option 
         */
        $this->screen_options();
        $post_type = get_post_type_object($this->order_type);
        $search_label  = '';
        if (!empty($_GET['s'])) {
            $search_label  = '<span class="subtitle">';
            $search_label .= sprintf(
                /* translators: %s: Search query. */
                __('Search results for: %s', 'redq-rental'),
                '<strong>' . esc_html($_GET['s']) . '</strong>'
            );
            $search_label .= '</span>';
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo wp_kses_post("<div class='wrap'>");
        if ($this->should_render_blank_state()) {
            $this->render_blank_state();
            return null;
        }
        $this->views();
        echo '<form id="wc-orders-filter-rnb" method="get" action="' . esc_url(get_admin_url(null, 'admin.php')) . '"> 
        <input type="hidden" name = "page" value="rnb-order">';

        $this->print_hidden_form_fields();
        $this->search_box(esc_html__('Search orders', 'redq-rental'), 'orders-search-input');

        parent::display();
        echo '</form> </div>';
        wp_enqueue_style('woocommerce_admin_styles');
    }

    /**
     * Screen options 
     * @return void 
     */
    public function screen_options()
    {
        ob_start();
        include __DIR__ . '/views/html-order-screen-options.php';
        echo ob_get_clean();
    }

    /**
     * Table columns 
     * @return array
     */
    public function get_columns()
    {
        return [
            'cb'               => '<input type = "checkbox">',
            'order_id'         => esc_html__('Order ID', 'redq-rental'),
            'order_status'     => esc_html__('Status', 'redq-rental'),
            'quick_view'       => esc_html__('Quick View', 'redq-rental'),
            'date_created_gmt' => esc_html__('Order Date', 'redq-rental'),
            'pickup_date_time' => esc_html__('Pickup DateTime', 'redq-rental'),
            'return_date_time' => esc_html__('Return DateTime', 'redq-rental'),
            'duration'         => esc_html__('Duration', 'redq-rental'),
            'deposit'          => esc_html__('Deposit', 'redq-rental'),
            'total_amount'     => esc_html__('Total', 'redq-rental'),
        ];
    }

    /**
     * Column cb 
     * @return string
     */
    public function column_cb($item)
    {
        $url = $item['order_id'];
        $order_id = '';
        preg_match('/post=(\d+)/', $url, $matches);
        if ($matches[1]) {
            $order_id = intval($matches[1]);
        }
        // Use sprintf to format the HTML string
        $html = sprintf('<input type="checkbox" name="order_id[]" value="%s">', $order_id);
        // Return the formatted HTML string
        return $html;
    }

    /**
     * default columns 
     */
    public function column_default($item, $column_name)
    {
        return isset($item[$column_name]) ? $item[$column_name] : '';
    }

    /**
     * Sortable columns list 
     * @return array 
     */
    public function get_sortable_columns()
    {
        return array(
            'order_id' => array('ID', false),
            'date_created_gmt' => array('post_date', false),
            'total_amount' => array('total_amount', false),
        );
    }

    /**
     * Get only order for redq rental
     * @return array 
     */
    public function get_orders($order_ids)
    {
        $table_data = [];

        if (empty($order_ids) || !is_array($order_ids)) {
            return $table_data;
        }

        foreach ($order_ids as $order_data) {
            $order_id = $order_data['order_id'];

            $payloads = $this->get_rental_data_by_order_id($order_id);
            $details = $payloads['order_details'];

            $edit_url = admin_url("post.php?post={$order_id}&action=edit");
            $id = '<a href = "' . esc_url($edit_url) . '">#' . $order_id . ' ' . $details['customer_name'] . '</a>';
            $quick_view = '<a href = "#" class = "order-preview" data-order-id = "' . esc_attr($order_id) . '" title = "Preview" style = "float: none; margin-left: 14%; display: inline-block; border: 2px solid transparent"></a>';

            $formatted_data = [
                'order_id'         => $id,
                'date_created_gmt' => $this->render_order_date($details['date_created']),
                'order_status'     => $this->render_order_status($details['status']),
                'total_amount'     => wc_price($details['total']),
                'pickup_date_time' => $details['pickup_period'],
                'return_date_time' => $details['return_period'],
                'duration'         => $details['duration'],
                'deposit'          => wc_price($details['deposit']),
                'quick_view'       => $quick_view
            ];

            $table_data[] = $formatted_data;
        }

        return $table_data;
    }

    /**
     * Renders the order number, customer name and provides a preview link.
     *
     * @param WC_Order $order The order object for the current row.
     *
     * @return void
     */
    public function customer_name($order)
    {
        $buyer = '';
        if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
            /* translators: 1: first name 2: last name */
            $buyer = trim(sprintf(_x('%1$s %2$s', 'full name', 'redq-rental'), $order->get_billing_first_name(), $order->get_billing_last_name()));
        } elseif ($order->get_billing_company()) {
            $buyer = trim($order->get_billing_company());
        } elseif ($order->get_customer_id()) {
            $user  = get_user_by('id', $order->get_customer_id());
            $buyer = ucwords($user->display_name);
        }

        /**
         * Filter buyer name in list table orders.
         *
         * @since 3.7.0
         *
         * @param string   $buyer Buyer name.
         * @param WC_Order $order Order data.
         */
        $buyer = apply_filters('woocommerce_admin_order_buyer_name', $buyer, $order);
        return $buyer;
    }

    /**
     * Post order by 
     */
    public function post_order()
    {
        $order = [];
        $order['order'] = 'DESC';
        if (isset($_GET['order'])) {
            $order['order'] = $_GET['order'];
        }
        $order['orderby'] = 'ID';
        if (isset($_GET['orderby'])) {
            $order['orderby'] = $_GET['orderby'];
        }
        $order['status'] = '';
        if (isset($_GET['status'])) {
            $order['status'] = $_GET['status'];
        }
        return  $order;
    }

    /**
     * Get Bulk Action 
     */
    protected function get_bulk_actions()
    {
        $selected_status = $_GET['status'] ?? false;

        if ('trash' === $selected_status) {
            $actions = array(
                'untrash' => __('Restore', 'redq-rental'),
                'delete'  => __('Delete permanently', 'redq-rental'),
            );
        } else {
            $actions = array(
                'processing' => __('Change status to processing', 'redq-rental'),
                'on-hold'    => __('Change status to on-hold', 'redq-rental'),
                'completed'  => __('Change status to completed', 'redq-rental'),
                'cancelled'  => __('Change status to cancelled', 'redq-rental'),
                'trash'      => __('Move to Trash', 'redq-rental'),
            );
        }

        if (wc_string_to_bool(get_option('woocommerce_allow_bulk_remove_personal_data', 'no'))) {
            $actions['remove_personal_data'] = __('Remove personal data', 'redq-rental');
        }

        return $actions;
    }

    public function get_views()
    {
        $status_links = array();
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : '';
        $status_list = $this->get_status();
        $status_links['all'] = sprintf(
            '<a href="%s" %s>%s <span> (%s)</span></a>',
            remove_query_arg(['status', 's']),
            empty($status) ? 'class="current"' : '',
            esc_html__('All', 'redq-rental'),
            $this->total_rental_order()
        );
        if (!empty($status_list)) {
            foreach ($status_list as $key => $list) {
                if ($this->total_rental_order($key)) {
                    $link = add_query_arg('status', $key);
                    $status_links[$key] = sprintf(
                        '<a href="%s" %s>%s <span class="count">(%d)</span></a>',
                        remove_query_arg('s', $link),
                        ($status === $key) ? 'class="current"' : '',
                        esc_html($list),
                        $this->total_rental_order($key)  // Your method to get the count
                    );
                };
            }
        }
        // Add more custom status links as needed

        return $status_links;
    }
    private function get_status()
    {
        $status = wc_get_order_statuses();
        $status['trash'] = esc_html__('Trash', 'redq-rental');
        return $status;
    }

    public function order_preview_template()
    {
        echo $this->get_order_preview_template();
    }

    public function enqueue_scripts()
    {
        echo $this->get_order_preview_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_enqueue_script('wc-orders');
        wp_enqueue_script('wc-backbone-modal');
    }

    public function get_order_preview_template()
    {
        $order_edit_url_placeholder =
            wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? esc_url(admin_url('admin.php?page=wc-orders&action=edit')) . '&id={{ data.data.id }}'
            : esc_url(admin_url('post.php?action=edit')) . '&post={{ data.data.id }}';

        ob_start();
        include __DIR__ . '/views/html-order-preview-popup.php';
        return ob_get_clean();
    }
}

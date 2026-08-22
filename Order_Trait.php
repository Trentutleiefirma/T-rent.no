<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use REDQ_RnB\Traits\Rental_Data_Trait;

/**
 * Handle rental data
 */
trait Order_Trait
{

    /**
     * Fetch only rental orders
     */
    public function fetch_rental_orders($per_page = 10, $page_number = 1, $args = [])
    {
        global $wpdb;
        // Parameters for pagination
        $page_number = isset($args['page_number']) ? intval($args['page_number']) : $page_number;
        $per_page = isset($args['per_page']) ? intval($args['per_page']) : $per_page;

        // Parameters for dynamic ordering
        $order = isset($args['order']) ? $args['order'] : 'DESC';
        $orderby = isset($args['orderby']) ? 'p.' . $args['orderby'] : 'p.ID';
        if ($orderby == 'p.total_amount') {
            $orderby = 'total_amount';
        }
        // Generate a unique key for the cache based on parameters
        $offset = (int)($page_number - 1) * (int)$per_page;
        //  Post status 
        $post_status = isset($args['status']) ? $args['status'] : '';
        $post_status_in = '';
        if ('' != $post_status) {
            $post_status_in = $wpdb->prepare("AND os.status = %s", $args['status']);
        } else {
            $post_status_in = "AND os.status NOT IN ('trash') -- Exclude orders in the 'trash' status";
        }
        $query = "
            SELECT p.ID as order_id, p.post_date as order_date, p.post_status as order_status,
                SUM(oim_order_total.meta_value) as total_amount
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_order_total ON oi.order_item_id = oim_order_total.order_item_id
            INNER JOIN {$wpdb->prefix}term_relationships tr ON oim.meta_value = tr.object_id
            INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
            INNER JOIN {$wpdb->prefix}wc_orders os ON p.ID = os.id
            WHERE p.post_type IN ('shop_order_placehold', 'shop_order')
            AND oim.meta_key = '_product_id'
            AND tt.taxonomy = 'product_type'
            AND t.slug = 'redq_rental'
            {$post_status_in}
            GROUP BY p.ID
            ORDER BY {$orderby} {$order}        
            LIMIT %d, %d;
        ";
        $prepared_query = $wpdb->prepare($query, $offset, $per_page);
        $orders = $wpdb->get_results($prepared_query, ARRAY_A);
        return $orders;
    }

    /**
     * Get total order count 
     */
    public function total_rental_order($count_type = 'all')
    {
        global $wpdb;
        $wc_orders_join = '';
        if ($count_type !== 'all') {
            $wc_orders_join = $wpdb->prepare("
                AND os.status = %s
            ", $count_type);
        }

        // trash count 
        $trash_count = '';
        if ($count_type != 'trash') {
            $trash_count = "AND os.status NOT IN ('trash') -- Exclude orders in the 'trash' status";
        }

        $query = "
            SELECT COUNT(DISTINCT p.ID) as total_count
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}term_relationships tr ON oim.meta_value = tr.object_id
            INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
            INNER JOIN {$wpdb->prefix}wc_orders os ON p.ID = os.id
            WHERE p.post_type IN ('shop_order_placehold', 'shop_order')
            AND oim.meta_key = '_product_id'
            AND tt.taxonomy = 'product_type'
            AND t.slug = 'redq_rental'
            {$wc_orders_join}  -- Include or exclude wc_orders table based on $count_type
            $trash_count
        ";

        $total_count = $wpdb->get_var($query);

        return $total_count;
    }

    public function render_order_status($status)
    {
        $status_name = wc_get_order_status_name($status);
        return sprintf('<mark class="order-status %s tips"><span>%s</span></mark>', esc_attr(sanitize_html_class('status-' . $status)), esc_html($status_name));
    }

    public function render_order_date($date)
    {
        $carbon_date = new Carbon($date->date('c'));
        return $carbon_date->format('M d, Y');
    }

    public function render_order_date_column($order)
    {
        $order_timestamp = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : '';

        if (!$order_timestamp) {
            return '&ndash;';
        }

        // Check if the order was created within the last 24 hours, and not in the future.
        if ($order_timestamp > strtotime('-1 day', time()) && $order_timestamp <= time()) {
            $show_date = sprintf(
                /* translators: %s: human-readable time difference */
                _x('%s ago', '%s = human-readable time difference', 'redq-rental'),
                human_time_diff($order->get_date_created()->getTimestamp(), time())
            );
        } else {
            $show_date = $order->get_date_created()->date_i18n(apply_filters('woocommerce_admin_order_date_format', __('M j, Y', 'redq-rental'))); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
        }

        return sprintf(
            '<time datetime="%1$s" title="%2$s">%3$s</time>',
            esc_attr($order->get_date_created()->date('c')),
            esc_html($order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'))),
            esc_html($show_date)
        );
    }
}

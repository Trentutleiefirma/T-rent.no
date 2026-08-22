<?php

if (!defined('ABSPATH')) {
    exit;
}

$my_quotes_columns = apply_filters('woocommerce_my_account_my_orders_columns', array(
    'quote-number'  => __('Order', 'woocommerce'),
    'quote-date'    => __('Date', 'woocommerce'),
    'quote-status'  => __('Status', 'woocommerce'),
    'quote-total'   => __('Total', 'woocommerce'),
    'quote-actions' => '&nbsp;',
));

// $customer_quotes = get_posts(apply_filters('redq_my_account_my_quote_query', array(
//     // 'numberposts' => 3, //$order_count,
//     'paged'       => $current_page,
//     // 'meta_key'    => '_quote_user',
//     // 'meta_value'  => get_current_user_id(),
//     'author'        =>  get_current_user_id(),
//     'post_type'   => 'request_quote',
//     'post_status' => array('quote-pending', 'quote-processing', 'quote-on-hold', 'quote-accepted', 'quote-completed', 'quote-cancelled')
// )));

$customer_quotes = new WP_Query(apply_filters('redq_my_account_my_quote_query', array(
    'posts_per_page' => get_option('posts_per_page'), //$order_count,
    'paged'          => $current_page,
    'author'         => get_current_user_id(),
    'post_type'      => 'request_quote',
    'post_status'    => array('quote-pending', 'quote-processing', 'quote-on-hold', 'quote-accepted', 'quote-completed', 'quote-cancelled')
)));
?>


<table class="shop_table shop_table_responsive my_account_orders">
    <thead>
        <tr>
            <?php foreach ($my_quotes_columns as $column_id => $column_name) : ?>
                <th class="<?php echo esc_attr($column_id); ?>"><span class="nobr"><?php echo esc_html($column_name); ?></span></th>
            <?php endforeach; ?>
        </tr>
    </thead>

    <tbody>
        <?php if ( $customer_quotes->have_posts() ) : while ( $customer_quotes->have_posts() ) : $customer_quotes->the_post(); ?>
            <tr class="order">
                <?php foreach ($my_quotes_columns as $column_id => $column_name) : ?>
                    <td class="<?php echo esc_attr($column_id); ?>" data-title="<?php echo esc_attr($column_name); ?>" <?php echo ($column_id === 'quote-actions') ? 'style="text-align: right;"' : ''; ?>>
                        <?php if (has_action('redq_my_account_my_quotes_column_' . $column_id)) : ?>
                            <?php do_action('redq_my_account_my_quotes_column_' . $column_id, $order); ?>

                        <?php elseif ('quote-number' === $column_id) : ?>
                            <a href="<?php echo esc_url(redq_get_view_quote_url(get_the_ID())); ?>">
                                <?php echo _x('#', 'hash before order number', 'redq-rental') . get_the_ID(); ?>
                            </a>

                        <?php elseif ('quote-date' === $column_id) : ?>
                            <time datetime="<?php echo date('Y-m-d', strtotime(get_the_date())); ?>" title="<?php echo esc_attr(strtotime(get_the_date())); ?>"><?php echo date_i18n(get_option('date_format'), strtotime(get_the_date())); ?></time>

                        <?php elseif ('quote-status' === $column_id) : ?>
                            <?php echo redq_get_quote_status_name(get_post_status()); ?>

                        <?php elseif ('quote-total' === $column_id) : ?>
                            <span class="woocommerce-Price-amount amount">
                                <?php echo wc_price(get_post_meta(get_the_ID(), '_quote_price', true)); ?>
                            </span>
                            <?php esc_html_e('for 1 items', 'redq-rental') ?>
                        <?php elseif ('quote-actions' === $column_id) :

                            echo '<a href="' . esc_url(redq_get_view_quote_url(get_the_ID())) . '" class="button view">' . esc_html('view', 'redq-rental') . '</a>';

                        endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php wp_reset_postdata(); ?>
        <?php endwhile; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if ( 1 < $customer_quotes->max_num_pages ) : ?>
    <div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
        <?php if ( 1 !== $current_page ) : ?>
            <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url( wc_get_endpoint_url( 'request-quote', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'redq-rental' ); ?></a>
        <?php endif; ?>

        <?php if ( intval( $customer_quotes->max_num_pages ) !== $current_page ) : ?>
            <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url( wc_get_endpoint_url( 'request-quote', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'redq-rental' ); ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
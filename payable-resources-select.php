<?php
global $post;
$inventory_id = rnb_get_default_inventory_id($post->ID);
$conditions   = redq_rental_get_settings($post->ID, 'conditions')['conditions'];

$resources = rnb_arrange_resource_data($post->ID, $inventory_id, $conditions);
$results = [];

foreach ($resources['data'] as $resource) {
    $results[] = [
        'id'              => $resource['id'],
        'text'            => $resource['resource_name'],
        'price'           => $resource['resource_cost'],
        'formatted_price' => wc_price($resource['resource_cost']),
    ];
}

$results = array_merge(
    [['id' => '']],
    $results
);

$resource_json = wp_json_encode($results);
$resource_attr = function_exists('wc_esc_json') ? wc_esc_json($resource_json) : _wp_specialchars($resource_json, ENT_QUOTES, 'UTF-8', true);
?>


<?php do_action('rnb_before_resource'); ?>

<div class="payable-extras booking-section-single rnb-select-wrapper rnb-component-wrapper rq-sidebar-select" data-resources="<?php echo $resource_attr; ?>">
    <h5> <?php echo esc_attr($resources['title']); ?> </h5>
    <select name="extras[]" data-placeholder="<?php echo esc_html__('-- Select --', 'redq-rental'); ?>"></select>
</div>

<?php do_action('rnb_after_resource'); ?>

<script>
    jQuery(document).ready(function($) {
        const resources = $('.payable-extras').data('resources');

        function formatResult(item) {
            if (!item.id) {
                return item.text;
            }

            var itemMarkup = $('<span class="item-text">' + item.text + '</span>');
            if (item.price) {
                itemMarkup.append('<span class="item-price">' + item.formatted_price + '</span>');
            }

            return itemMarkup;
        }

        function formatTemplate(item) {
            if (!item.id) {
                return item.text;
            }

            var itemMarkup = $('<span class="item-text">' + item.text + '</span>');
            if (item.price) {
                itemMarkup.append('<span class="item-price">' + item.formatted_price + '</span>');
            }

            return itemMarkup;
        }

        $("[name='extras[]']").select2({
            width: '100%',
            placeholder: function() {
                return $(this).data('placeholder');
            },
            templateResult: formatTemplate,
            templateSelection: formatResult,
            data: resources,
        });
    });
</script>
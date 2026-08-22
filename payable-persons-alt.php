<?php
global $post;
$inventory_id = rnb_get_default_inventory_id($post->ID);
$conditions   = redq_rental_get_settings($post->ID, 'conditions')['conditions'];

$person       = rnb_arrange_adult_data($post->ID, $inventory_id, $conditions);

$results = [];

foreach ($person['data'] as $adult) {
    $results[] = [
        'id' => $adult['id'],
        'text' => $adult['person_count'],
        'price' => $adult['person_cost'],
        'formatted_price' => wc_price($adult['person_cost']),
    ];
}

$results = array_merge(
    [['id' => '']],
    $results
);

$person_json = wp_json_encode($results);
$person_attr = function_exists('wc_esc_json') ? wc_esc_json($person_json) : _wp_specialchars($person_json, ENT_QUOTES, 'UTF-8', true);
?>

<?php do_action('rnb_before_adult'); ?>


<div class="payable-person additional-person-adult rnb-select-wrapper rnb-component-wrapper rq-sidebar-select" data-person="<?php echo $person_attr; ?>">
    <h5> <?php echo esc_attr($person['title']); ?> </h5>
    <select name="additional_adults_info" data-placeholder="<?php echo esc_attr($person['placeholder']); ?>"></select>
</div>


<?php do_action('rnb_after_child'); ?>


<script>
    jQuery(document).ready(function($) {
        const person = $('.payable-person').data('person');

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

        $("[name='additional_adults_info']").select2({
            width: '100%',
            placeholder: function() {
                return $(this).data('placeholder');
            },
            templateResult: formatTemplate,
            templateSelection: formatResult,
            data: person,
        });
    });
</script>
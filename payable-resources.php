<?php do_action('rnb_before_resource'); ?>

<div id="resourcePreview" class="payable-extras booking-section-single rnb-component-wrapper rq-sidebar-select"></div>

<script type="text/html" id="resourceBuilder">
    <% if(items.length){ %>
        <h5><%= title %></h5>
        <?php do_action('rnb_after_resource_title'); ?>
        <% _.each(items, function(item, index) { %>
        <div class="attributes">
            <label class="custom-block">
                <input type="checkbox" name="extras[]" value="<%= item.id %>" class="booking-extra" <% if(item.clickable === 'no'){ %> checked readonly onclick="return false" <% } %> <% if( selectedItems.includes(item.resource_slug)){ %> checked <% } %>>
                <%= item.resource_name %> <%= item.extra_meta %>
            </label>
            <% if(item.description){ %>
            <p><%= item.description %></p>
            <% } %>
        </div>
        <% }) %>
    <% } %>
</script>

<?php do_action('rnb_after_resource'); ?>

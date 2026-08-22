<?php do_action('rnb_before_category'); ?>

<div id="categoryPreview" class="payable-categories rnb-component-wrapper rq-sidebar-select"></div>

<script type="text/html" id="categoryBuilder">
    <% if(items.length){ %>
    <h5><%= title %></h5>

    <?php do_action('rnb_after_category_title'); ?>

    <% _.each(items, function(item, index) { %>

    <div class="attributes categories-attr">
        <label class="custom-block">
            <input type="checkbox" name="categories[]" value="<%= item.id %>" class="booking-extra" <% if(item.clickable === 'no'){ %> checked readonly onclick="return false" <% } %> <% if( selectedItems.includes(item.slug)){ %> checked <% } %> />
            <%= item.name %> <%= item.extra_meta %>
        </label>

        <div class="quantity">
            <label class="screen-reader-text" for="<%= item.quantity_input.input_id %>"><%= item.quantity_input.placeholder %></label>
            <input type="number" data-cat_id="<%= item.id %>" id="<%= item.quantity_input.input_id %>" class="input-text qty text" step="<%= item.quantity_input.step %>" min="<%= item.quantity_input.min_value %>" max="<%= 0 < item.quantity_input.max_value ?  item.quantity_input.max_value : ''  %>" name="<%= item.quantity_input.input_name %>[]" value="<%= item.quantity_input.input_value %>" title="<%= item.quantity_input.title %>" size="4" pattern="<%= item.quantity_input.pattern %>" inputmode="<%= item.quantity_input.inputmode %>" aria-labelledby="<%= item.quantity_input.labelledby %>" />
        </div>
    </div> 
    <% if(item.description){ %>
    <p><%= item.description %></p>
    <% } %>
    <% }) %>
    <% } %>
</script>

<?php do_action('rnb_after_category'); ?>
<?php
global $product;
$product_id = $product->get_id();
$allowed_html = wp_kses_allowed_html();
$extra_labels = redq_rental_get_settings($product_id, 'layout_two', array('inventory', 'location', 'datetime', 'resource', 'person', 'deposit', 'summary'));
$extra_datetime_labels = $extra_labels['datetime'];
$extra_location_labels = $extra_labels['location'];

$redq_product_inventory = rnb_get_product_inventory_id($product_id);

$labels = redq_rental_get_settings($product_id, 'labels', array('inventory'));
$labels = $labels['labels'];

if (empty($redq_product_inventory)) {
    return;
}

$inventories = get_posts(array(
    'post_type' => 'inventory',
    'post__in'  => $redq_product_inventory,
    'orderby' => 'post__in'
));

foreach ($inventories as $index => $inventory) {
    $inventories[$index]->quantity = get_post_meta($inventory->ID, 'quantity', true);
}

$inventory_id = rnb_get_default_inventory_id($product_id);
$conditions = redq_rental_get_settings($product_id, 'conditions');
$conditional_data = $conditions['conditions'];

?>



<!--Start Stepper Modal-->
<a id="showBooking" href="#animatedModal"><?php echo esc_html($product->single_add_to_cart_text()); ?></a>

<div id="animatedModal" class="rnb-animated-modal">
    <div class="modal-content-body">
        <div class="modal-header">
            <div class="close-animatedModal"><i class="fas fa-times"></i></div>
            <div class="title-wrapper">
                <div class="title">
                    <h3><?php echo esc_attr($extra_location_labels['location_top_heading']); ?></h3>
                    <p><?php echo wp_kses($extra_location_labels['location_top_desc'], $allowed_html); ?></p>
                </div>
                <div class="price total-rental-price">
                    <h2></h2>
                </div>
            </div>
        </div>
        <!-- End Modal Header -->

        <div class="modal-content">
            <div id="rnbSmartwizard">

                <h3> <span class="tab-identifier" data-id="inventory"> <i class="icon ion-ios-location"></i> <?php echo esc_html__('Inventory', 'redq-rental'); ?> </span></h3>
                <section class="rnb-step-content-wrapper">
                    <div id="rnb-step-1" class="">
                        <?php if (!empty($extra_labels['inventory']['inventory_inner_heading']) || !empty($extra_labels['inventory']['inventory_inner_desc'])) { ?>
                            <header class="section-title">
                                <h4><?php echo esc_attr($extra_labels['inventory']['inventory_inner_heading']); ?></h4>
                                <p><?php echo wp_kses($extra_labels['inventory']['inventory_inner_desc'], $allowed_html); ?></p>
                            </header>
                        <?php } ?>
                        <!-- end .section-title -->
                        <div class="payable-inventory rnb-component-wrapper rnb-select-wrapper redq-pick-up-location" <?php echo (count($inventories) < 1) ? 'style="display:none"' : ''; ?>>
                            <h5><?php echo esc_attr($labels['inventory']); ?></h5>
                            <select class="rnb-select-box" id="booking_inventory" name="booking_inventory" data-post-id="<?php echo $product_id ?>">
                                <?php foreach ($inventories as $inventory) : ?>
                                    <option value="<?php echo $inventory->ID ?>"><?php echo $inventory->post_title ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>
                <!-- End #1sd Step -->


                <?php $extra_location_labels = $extra_labels['location']; ?>
                <h3><span class="tab-identifier" data-id="location"> <i class="icon ion-ios-location"></i> <?php echo esc_html__('Location', 'redq-rental'); ?></span></h3>
                <section class="rnb-step-content-wrapper">
                    <div id="rnb-step-2" class="">
                        <?php if (!empty($extra_location_labels['location_inner_heading']) || !empty($extra_location_labels['location_inner_desc'])) { ?>
                            <header class="section-title">
                                <h4><?php echo esc_attr($extra_location_labels['location_inner_heading']); ?></h4>
                                <p><?php echo wp_kses($extra_location_labels['location_inner_desc'], $allowed_html); ?></p>
                            </header>
                        <?php } ?>

                        <div class="rnb-google-location-map-area">
                            <div class="rnb-autocomplete-input-group">
                                <input type="text" name="pickup_location" data-latlng="notset" class="rnb-pickup-location" id="rnb-origin-autocomplete" />
                                <input type="text" name="dropoff_location" data-latlng="notset" class="rnb-dropoff-location" id="rnb-destination-autocomplete" />
                            </div>
                            <div id="rnb-map" style="height:300px; width:100%"></div>
                            <div id="right-panel" style="display: none;">
                                <p><?php echo esc_html__('Total Distance:', 'redq-rental'); ?> <span id="total"></span> </p>
                            </div>
                            <input type="hidden" name="total_distance" class="rnb-distance" />
                        </div>
                    </div>
                </section>
                <!-- End #2nd Step -->

                <h3> <span class="tab-identifier" data-id="duration"> <i class="icon ion-calendar"></i> <?php echo esc_html__('Duration', 'redq-rental'); ?></span></h3>
                <section class="rnb-step-content-wrapper">
                    <div id="rnb-step-3" class="">
                        <?php if (!empty($extra_datetime_labels['date_inner_heading']) || !empty($extra_datetime_labels['date_inner_desc'])) { ?>
                            <header class="section-title">
                                <h4><?php echo esc_attr($extra_datetime_labels['date_inner_heading']); ?></h4>
                                <p><?php echo wp_kses($extra_datetime_labels['date_inner_desc'], $allowed_html); ?></p>
                            </header>
                            <header class="date-error-message section-title">
                                <h4 class="date-error-message-text">
                                    <i class="fa fa-info-circle"></i>
                                    <?php echo esc_html__('Duration selection is not correct!', 'redq-rental'); ?>
                                </h4>
                            </header>
                        <?php } ?>
                        <!-- end .section-title -->
                        <div class="rnb-date-select-area">
                            <?php
                            rnb_pickup_datetimes();
                            rnb_return_datetimes();
                            ?>
                        </div>
                        <?php
                        $displays = redq_rental_get_settings(get_the_ID(), 'display');
                        $displays = $displays['display'];
                        ?>
                        <?php if (isset($displays['quantity']) && $displays['quantity'] !== 'closed') : ?>
                            <div class="redq-quantity rnb-select-wrapper rnb-component-wrapper">
                                <?php
                                $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('quantity'));
                                $labels = $labels['labels'];
                                ?>
                                <h5><?php echo esc_attr($labels['quantity']); ?></h5>
                                <input type="number" name="inventory_quantity" class="inventory-qty" min="" max="" value="1">
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                <!-- End #3rd Step -->



                <?php $resources = rnb_arrange_resource_data($product_id, $inventory_id, $conditional_data); ?>
                <?php if (isset($resources['data']) && !empty($resources['data'])) : ?>
                    <?php
                    $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('resources'));
                    $labels = $labels['labels'];
                    $extra_resource_labels = $extra_labels['resource'];
                    ?>
                    <h3><span class="tab-identifier" data-id="resource"><i class="icon ion-briefcase"></i> <?php echo esc_attr($labels['resource']); ?> </span> </h3>
                    <section class="rnb-step-content-wrapper">
                        <div id="rnb-step-4" class="">
                            <?php if (!empty($extra_resource_labels['resource_inner_heading']) || !empty($extra_resource_labels['resource_inner_desc'])) { ?>
                                <header class="section-title">
                                    <h4><?php echo esc_attr($extra_resource_labels['resource_inner_heading']); ?></h4>
                                    <p><?php echo wp_kses($extra_resource_labels['resource_inner_desc'], $allowed_html); ?></p>
                                </header>
                            <?php } ?>
                            <div id="resourceModalPreview"></div>
                            <script type="text/html" id="resourceModalBuilder">
                                <% if(items.length){ %>
                                <div class="rnb-resource-checkbox-area">
                                    <% _.each(items, function(item, index) { %>
                                    <label class="rnb-control rnb-control-checkbox" for="rnb-resource-<%= index+1 %>">
                                        <span class="title"><%= item.resource_name %></span>
                                        <span class="meta">
                                            <%= item.extra_meta %>
                                        </span>
                                        <input type="checkbox" id="rnb-resource-<%= index+1 %>" data-items="<%= index+1 %>" name="extras[]" value="<%= item.id %>" class="booking-extra">
                                    </label>
                                    <% }) %>
                                </div>
                                <% } %>
                            </script>
                        </div>
                    </section>
                <?php endif; ?>
                <!-- End #4th Step -->


                <?php
                $adult_data = rnb_arrange_adult_data($product_id, $inventory_id, $conditional_data);
                $child_data = rnb_arrange_child_data($product_id, $inventory_id, $conditional_data);

                $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('person'));
                $labels = $labels['labels'];
                $extra_person_labels = $extra_labels['person'];
                ?>

                <?php if (count($adult_data['data']) || count($child_data['data'])) : ?>
                    <h3><span class="tab-identifier" data-id="person"><i class="icon ion-person-stalker"></i> <?php echo esc_html__('Person', 'redq-rental'); ?> </span> </h3>
                    <section class="rnb-step-content-wrapper">
                        <div id="rnb-step-5" class="">
                            <?php if (!empty($extra_person_labels['person_inner_heading']) || !empty($extra_person_labels['person_inner_desc'])) { ?>
                                <header class="section-title">
                                    <h4><?php echo esc_attr($extra_person_labels['person_inner_heading']); ?></h4>
                                    <p><?php echo wp_kses($extra_person_labels['person_inner_desc'], $allowed_html); ?></p>
                                </header>
                            <?php } ?>
                            <div class="rnb-persons-section">
                                <div class="person-warning">
                                    <span class="adultWarning" style="display: none"><?php echo esc_html__('Adult is required', 'redq-rental'); ?></span>
                                    <span class="childWarning" style="display: none"><?php echo esc_html__('Child is required', 'redq-rental'); ?></span>
                                </div>
                                <?php if (count($adult_data['data'])) : ?>
                                    <h4><?php echo esc_attr($labels['adults']); ?></h4>
                                    <div id="adultModalPreview"></div>
                                    <script type="text/html" id="adultModalBuilder">
                                        <% if(items.length){ %>
                                        <div class="person-radiobtns-area rnb-adult-area">
                                            <% _.each(items, function(item, index) { %>
                                            <label class="rnb-control rnb-control-radio rnb-adult-label" for="rnb-adult-<%= index+1 %>">
                                                <span class="title"><%= item.person_count %></span>
                                                <span class="meta">
                                                    <%= item.extra_meta_modal %>
                                                </span>
                                                <input type="radio" id="rnb-adult-<%= index+1 %>" data-items="<%= index+1 %>" name="additional_adults_info" value="<%= item.id %>" class="additional_adults_info" />
                                            </label>
                                            <% }) %>
                                        </div>
                                        <% } %>
                                    </script>
                                <?php endif; ?>
                                <!-- end .adult-radio-area -->

                                <?php if (count($child_data['data'])) : ?>
                                    <h4><?php echo esc_attr($labels['childs']); ?></h4>
                                    <div id="childModalPreview"></div>
                                    <script type="text/html" id="childModalBuilder">
                                        <% if(items.length){ %>
                                        <div class="person-radiobtns-area rnb-child-area">
                                            <% _.each(items, function(item, index) { %>
                                            <label class="rnb-control rnb-control-radio rnb-child-label" for="rnb-child-<%= index+1 %>">
                                                <span class="title"><%= item.person_count %></span>
                                                <span class="meta">
                                                    <%= item.extra_meta_modal %>
                                                </span>
                                                <input type="radio" id="rnb-child-<%= index+1 %>" data-items="<%= index+1 %>" name="additional_childs_info" value="<%= item.id %>" class="additional_childs_info" />
                                            </label>
                                            <% }) %>
                                        </div>
                                        <% } %>
                                    </script>
                                <?php endif; ?>
                                <!-- end .child-radio-area -->
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <!-- End #5th Step -->


                <?php $security_deposit = rnb_arrange_security_deposit_data($product_id, $inventory_id, $conditional_data); ?>
                <?php if (isset($security_deposit['data']) && count($security_deposit['data'])) : ?>
                    <?php
                    $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('deposites'));
                    $labels = $labels['labels'];
                    $extra_deposit_labels = $extra_labels['deposit'];
                    ?>
                    <h3><span class="tab-identifier" data-id="deposit"><i class="icon ion-card"></i> <?php echo esc_attr($labels['deposite']); ?> </span> </h3>
                    <section class="rnb-step-content-wrapper">
                        <div id="rnb-step-6" class="">
                            <?php if (!empty($extra_deposit_labels['deposit_inner_heading']) || !empty($extra_deposit_labels['deposit_inner_desc'])) { ?>
                                <header class="section-title">
                                    <h4><?php echo esc_attr($extra_deposit_labels['deposit_inner_heading']); ?></h4>
                                    <p><?php echo wp_kses($extra_deposit_labels['deposit_inner_desc'], $allowed_html); ?></p>
                                </header>
                            <?php } ?>
                            <!-- end .section-title -->
                            <div id="depositModalPreview"></div>
                            <script type="text/html" id="depositModalBuilder">
                                <% if(items.length){ %>
                                <div class="rnb-deposite-section">
                                    <div class="deposite-checkbox-area">
                                        <% _.each(items, function(item, index) { %>
                                        <label class="rnb-control rnb-control-checkbox rnb-deposit-label rnb-control-checkbox-deposit <% if(item.security_deposite_clickable === 'no'){ %> checked <% } %>" for="rnb-deposit-<%= index+1 %>">
                                            <span class="title"><%= item.security_deposite_name %></span>
                                            <span class="meta">
                                                <%= item.extra_meta %>
                                            </span>
                                            <input type="checkbox" id="rnb-deposit-<%= index+1 %>" data-items="<%= index+1 %>" name="security_deposites[]" value="<%= item.id %>" <% if(item.security_deposite_clickable === 'no'){ %> checked readonly onclick="return false" <% } %> />
                                        </label>
                                        <% }) %>
                                    </div>
                                </div>
                                <% } %>
                            </script>
                        </div>
                    </section>
                <?php endif; ?>
                <!-- End #6th Step -->
                <?php do_action('rnb_before_booking_summary', $product_id, $inventory_id); ?>
                <?php $extra_summary_labels = $extra_labels['summary']; ?>
                <h3><span class="tab-identifier" data-id="summary"><i class="icon ion-calculator"></i> <?php echo esc_html__('Summary', 'redq-rental'); ?></span></h3>
                <section class="rnb-step-content-wrapper">
                    <div id="rnb-step-7" class="modal-summary" data-error="false">
                        <?php if (!empty($extra_summary_labels['summary_inner_heading']) || !empty($extra_summary_labels['summary_inner_desc'])) { ?>
                            <header class="section-title">
                                <h4><?php echo esc_attr($extra_summary_labels['summary_inner_heading']); ?></h4>
                                <p><?php echo wp_kses($extra_summary_labels['summary_inner_desc'], $allowed_html); ?></p>
                            </header>
                        <?php } ?>

                        <div class="booking-summay"></div>

                        <div class="rnb-booking-summay rnb-default-hidden-page">
                            <?php rnb_booking_summary_two(); ?>
                        </div>

                    </div>
                </section>
                <!-- End #7th Step -->
            </div>
        </div>
        <!-- End Modal Content -->
    </div>
</div>
<!-- End stepper modal -->
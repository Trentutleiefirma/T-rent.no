<?php
/**
 * Template Name: Home Page
 */

get_header();
?>

<main id="primary">

    <?php 
    $land_excavator_main_slider_wrap = absint(get_theme_mod('land_excavator_enable_slider', 0));
    if($land_excavator_main_slider_wrap == 1): 
    ?>
        <section id="main-slider-wrap">
            <div class="owl-carousel">
                <?php for ($land_excavator_main_i=1; $land_excavator_main_i <= 3; $land_excavator_main_i++): ?>
                    <?php if ($land_excavator_slider_image = get_theme_mod('land_excavator_slider_image'.$land_excavator_main_i)): ?>
                        <div class="main-slider-inner-box">
                            <img src="<?php echo esc_url($land_excavator_slider_image); ?>" alt="<?php echo esc_attr( get_theme_mod('land_excavator_slider_heading'.$land_excavator_main_i) ); ?>">
                            <div class="main-slider-content-box">
                                <?php if ($land_excavator_top_text = get_theme_mod('land_excavator_slider_top_text'.$land_excavator_main_i)): ?>
                                    <p class="slider-top"><?php echo esc_html($land_excavator_top_text); ?></p>
                                <?php endif; ?>
                                <?php if ($land_excavator_heading = get_theme_mod('land_excavator_slider_heading' . $land_excavator_main_i)): ?>
                                    <h1><?php echo esc_html($land_excavator_heading); ?></h1>
                                <?php endif; ?>

                                <?php if ($land_excavator_text = get_theme_mod('land_excavator_slider_text'.$land_excavator_main_i)): ?>
                                    <p class="slider-content"><?php echo esc_html($land_excavator_text); ?></p>
                                <?php endif; ?>
                                <div class="main-slider-button">
                                    <?php if ( get_theme_mod('land_excavator_slider_button1_link'.$land_excavator_main_i) ||  get_theme_mod('land_excavator_slider_button1_text'.$land_excavator_main_i )) : ?><a class="slide-btn-1" href="<?php echo esc_url( get_theme_mod('land_excavator_slider_button1_link'.$land_excavator_main_i) ); ?>"><?php echo esc_html( get_theme_mod('land_excavator_slider_button1_text'.$land_excavator_main_i) ); ?></a><?php endif; ?>
                                    <?php if ( get_theme_mod('land_excavator_slider_button2_link'.$land_excavator_main_i) ||  get_theme_mod('land_excavator_slider_button2_text'.$land_excavator_main_i )) : ?><a class="slide-btn-2" href="<?php echo esc_url( get_theme_mod('land_excavator_slider_button2_link'.$land_excavator_main_i) ); ?>"><?php echo esc_html( get_theme_mod('land_excavator_slider_button2_text'.$land_excavator_main_i) ); ?></a><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </section>
    <?php endif; ?>

   <?php 
    $land_excavator_main_expert_wrap = absint(get_theme_mod('construction_equipments_enable_product_section', 1));
    if ($land_excavator_main_expert_wrap == 1): 
    ?>
        <section id="main-expert-wrap">
            <div class="container">
                <div class="title-head">
                    <?php if( get_theme_mod('construction_equipments_projetcs_main_text') != '' ){ ?>
                        <p class="project-top-text"><?php echo esc_html(get_theme_mod('construction_equipments_projetcs_main_text',''));?></p>
                    <?php }?>
                    <?php if( get_theme_mod('construction_equipments_projetcs_main_heading') != '' ){ ?>
                        <h2><?php echo esc_html(get_theme_mod('construction_equipments_projetcs_main_heading',''));?></h2>
                    <?php }?>
                </div>
                <div class="flex-row">
                    <?php 
                    // Check if WooCommerce is active
                    if (class_exists('WooCommerce')) {
                        $args = array( 
                            'post_type' => 'product',
                            'product_cat' => esc_html(get_theme_mod('construction_equipments_product_category')), // Escaped for security
                            'order' => 'ASC',
                            'posts_per_page' => 10
                        );
                        $construction_equipments_loop = new WP_Query($args);
                    
                        // Start the loop to display products
                        while ($construction_equipments_loop->have_posts()) : $construction_equipments_loop->the_post(); 
                            global $product;
                            ?>
                            <div class="product-box">  
                                <div class="product-box-content">
                                    <!-- Product Image -->
                                    <div class="product-image">
                                        <?php 
                                        if (has_post_thumbnail()) {
                                            the_post_thumbnail('shop_catalog');
                                        } else {
                                            echo '<img src="' . esc_url(wc_placeholder_img_src()) . '" alt="' . esc_attr__('Placeholder', 'construction-equipments') . '" />';
                                        }
                                        ?>
                                    </div>
                                    <!-- Product Title -->
                                    <h6 class="product-heading-text">
                                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                    </h6>

                                    <!-- Product Rating -->
                                    <div class="product-rating">
                                        <?php 
                                        if ($product->is_type('simple')) {
                                            woocommerce_template_loop_rating(); 
                                        }
                                        ?>
                                    </div>
                                    <!-- Product Price -->
                                    <p class="product-price d-flex <?php echo esc_attr(apply_filters('woocommerce_product_price_class', 'price')); ?>">
                                        <?php echo wp_kses_post($product->get_price_html()); ?>
                                    </p>
                                    <!-- Add to Cart Button -->
                                    <div class="cart-button align-items-center justify-content-center">
                                        <?php 
                                        if ($product->is_type('simple')) {
                                            woocommerce_template_loop_add_to_cart(); 
                                        }
                                        ?>  
                                    </div>
                                </div>
                            </div> 
                        <?php 
                        endwhile; 
                        wp_reset_postdata(); 
                    } 
                    ?>
                </div>  
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
get_footer();
?>
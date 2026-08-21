<?php

// Enqueue parent and child styles
function construction_equipments_child_enqueue_styles() {

    // 1. Load parent theme CSS
    wp_enqueue_style(
        'land-excavator-style', // Parent handle
        get_template_directory_uri() . '/style.css',
        array(),
        LAND_EXCAVATOR_VERSION
    );

    // 2. Load child theme CSS (depends on parent)
    wp_enqueue_style(
        'land-excavator-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('land-excavator-style'),
        wp_get_theme()->get('Version')
    );
}
add_action( 'wp_enqueue_scripts', 'construction_equipments_child_enqueue_styles' );

// Enqueue child theme JS in addition to parent JS
function construction_equipments_child_scripts() {

    // Load child custom JS
    wp_enqueue_script(
        'land-excavator-child-js',
        get_stylesheet_directory_uri() . '/revolution/assets/js/custom.js', // <-- your file in child theme
        array('jquery'), // load after jQuery
        wp_get_theme()->get('Version'),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'construction_equipments_child_scripts' );

// Theme setup
if (!function_exists('construction_equipments_setup')) :
    function construction_equipments_setup() {
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('custom-header');
        add_theme_support('responsive-embeds');
        add_theme_support('post-thumbnails');
        add_theme_support('align-wide');
        add_editor_style(array('assets/css/editor-style.css'));
        add_theme_support('custom-background', apply_filters('construction_equipments_custom_background_args', array(
            'default-color' => 'ffffff',
            'default-image' => '',
        )));

        if ( ! defined( 'LAND_EXCAVATOR_IMPORT_URL' ) ) {
            define( 'LAND_EXCAVATOR_IMPORT_URL', esc_url( admin_url( 'themes.php?page=constructionequipments-demoimport' ) ) );
        }
        if ( ! defined( 'LAND_EXCAVATOR_WELCOME_MESSAGE' ) ) {
            define( 'LAND_EXCAVATOR_WELCOME_MESSAGE', __( 'Welcome to Construction Equipments', 'construction-equipments' ) );
        }

    }
endif;
add_action('after_setup_theme', 'construction_equipments_setup');

// Set content width
function construction_equipments_content_width() {
    $GLOBALS['content_width'] = apply_filters('construction_equipments_content_width', 1170);
}
add_action('after_setup_theme', 'construction_equipments_content_width', 0);

// Register widget areas
function construction_equipments_widgets_init() {
    register_sidebar(array(
        'name' => __('Sidebar Widget Area', 'construction-equipments'),
        'id' => 'land-excavator-sidebar-primary',
        'description' => __('The Primary Widget Area', 'construction-equipments'),
        'before_widget' => '<aside id="%1$s" class="widget %2$s">',
        'after_widget' => '</aside>',
        'before_title' => '<h4 class="widget-title">',
        'after_title' => '</h4><div class="title"><span class="shap"></span></div>',
    ));
    register_sidebar(array(
        'name' => __('Footer Widget Area', 'construction-equipments'),
        'id' => 'land-excavator-footer-widget-area',
        'description' => __('The Footer Widget Area', 'construction-equipments'),
        'before_widget' => '<div class="footer-widget col-lg-3 col-sm-6 wow fadeIn" data-wow-delay="0.2s"><aside id="%1$s" class="widget %2$s">',
        'after_widget' => '</aside></div>',
        'before_title' => '<h5 class="widget-title w-title">',
        'after_title' => '</h5><span class="shap"></span>',
    ));
}
add_action('widgets_init', 'construction_equipments_widgets_init');

// Remove customizer settings
function construction_equipments_remove_custom($wp_customize) {

    $wp_customize->remove_setting('land_excavator_primary_color');
    $wp_customize->remove_control('land_excavator_primary_color');

    $wp_customize->remove_setting('land_excavator_header_info_email');
    $wp_customize->remove_control('land_excavator_header_info_email');

    $wp_customize->remove_setting('land_excavator_header_mail_icon');
    $wp_customize->remove_control('land_excavator_header_mail_icon');
    
    $wp_customize->remove_section( 'land_excavator_projetcs_sec' );

}
add_action('customize_register', 'construction_equipments_remove_custom', 1000);

function construction_equipments_child_customize_register( $wp_customize ) {

    $wp_customize->add_setting( 'construction_equipments_primary_color',
        array(
        'default'           => '#EDB509',
        'capability'        => 'edit_theme_options',
        'sanitize_callback' => 'sanitize_hex_color',
        )
    );
    $wp_customize->add_control( 
        new WP_Customize_Color_Control( 
        $wp_customize, 
        'construction_equipments_primary_color',
        array(
            'label'      => esc_html__( 'Primary Color', 'construction-equipments' ),
            'section'    => 'land_excavator_global_color_section',
            'settings'   => 'construction_equipments_primary_color',
        ) ) 
    );

    /*
    * Customizer feature bike section
    */
    /*Project Options*/
    $wp_customize->add_section('construction_equipments_products_section', array(
        'priority'       => 5,
        'capability'     => 'edit_theme_options',
        'theme_supports' => '',
        'title'          => __('Our Product Options', 'construction-equipments'),
        'panel'       => 'land_excavator_panel',
    ));

    /*Project Enable Option*/
    $wp_customize->add_setting(
        'construction_equipments_enable_product_section',
        array(
            'capability'        => 'edit_theme_options',
            'transport'         => 'refresh',
            'default'           => 1,
            'sanitize_callback' => 'land_excavator_sanitize_checkbox',
        )
    );

    $wp_customize->add_control(
        'construction_equipments_enable_product_section',
        array(
            'label'       => __('Enable Product Category Section', 'construction-equipments'),
            'description' => __('Checked to show the product section', 'construction-equipments'),
            'section'     => 'construction_equipments_products_section',
            'type'        => 'checkbox',
        )
    );

    /*Product Section Heading*/
    $wp_customize->add_setting(
        'construction_equipments_projetcs_main_text',
        array(
            'capability'        => 'edit_theme_options',
            'transport'         => 'refresh',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    $wp_customize->add_control(
        'construction_equipments_projetcs_main_text',
        array(
            'label'       => __('Edit Section Heading', 'construction-equipments'),
            'description' => __('Edit product section heading', 'construction-equipments'),
            'section'     => 'construction_equipments_products_section',
            'type'        => 'text',
        )
    );

    /*Product Section Heading*/
    $wp_customize->add_setting(
        'construction_equipments_projetcs_main_heading',
        array(
            'capability'        => 'edit_theme_options',
            'transport'         => 'refresh',
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        )
    );
    $wp_customize->add_control(
        'construction_equipments_projetcs_main_heading',
        array(
            'label'       => __('Edit Section Heading', 'construction-equipments'),
            'description' => __('Edit product section heading', 'construction-equipments'),
            'section'     => 'construction_equipments_products_section',
            'type'        => 'text',
        )
    );

    $args = array(
       'type'      => 'product',
        'taxonomy' => 'product_cat'
    );
    $categories = get_categories($args);
        $cat_posts = array();
            $i = 0;
            $cat_posts[]='Select';
        foreach($categories as $category){
            if($i==0){
            $default = $category->slug;
            $i++;
        }
        $cat_posts[$category->slug] = $category->name;
    }

    $wp_customize->add_setting('construction_equipments_product_category',array(
        'sanitize_callback' => 'land_excavator_sanitize_choices',
    ));
    $wp_customize->add_control('construction_equipments_product_category',array(
        'type'    => 'select',
        'choices' => $cat_posts,
        'label' => __('Select Product Category','construction-equipments'),
        'section' => 'construction_equipments_products_section',
    ));

    $wp_customize->add_setting( 
		'land_excavator_product_settings_upgraded_features',
		array(
			'sanitize_callback' => 'sanitize_text_field'
		)
	);

	$wp_customize->add_control(
		'land_excavator_product_settings_upgraded_features', 
		array(
			'type'=> 'hidden',
			'description' => "
				<div class='customizer-upgraded-features'>
					<span class='pro-head'>Want more product options?</span><br/>
					<span class='pro-text'>						
						<a target='_blank' href='". esc_url(LAND_EXCAVATOR_BUY_NOW) ." '>Check Land Excavator PRO version.</a>
					</div>
				</div>
			",
			'section' => 'construction_equipments_products_section'
		)
	);

}
add_action( 'customize_register', 'construction_equipments_child_customize_register', 20 );

// Global colors
function construction_equipments_dynamic_colors() {
    $construction_equipments_primary_color = get_theme_mod('construction_equipments_primary_color', '#EDB509');

    $construction_equipments_custom_css = ':root {';
    $construction_equipments_custom_css .= '--primary-color: ' . esc_attr( $construction_equipments_primary_color ) . ';';
    $construction_equipments_custom_css .= '}';

    echo '<style type="text/css">' . $construction_equipments_custom_css . '</style>';
}
add_action('wp_head', 'construction_equipments_dynamic_colors');

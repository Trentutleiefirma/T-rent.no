<?php
/**
 * The header for our theme
 *
 * @package Construction Equipments
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">

    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
    <a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'construction-equipments' ); ?></a>

    <?php
    $land_excavator_preloader_wrap = absint(get_theme_mod('land_excavator_enable_preloader', 0));
    if ($land_excavator_preloader_wrap === 1): ?>
        <div id="loader">
            <div class="loader-container">
                <div id="preloader" class="loader-2">
                    <div class="dot"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <header id="masthead" class="site-header">
        <div class="header-info-box">
            <div class="container">
                <div class="flex-row">
                    <div class="header-info-left">
                        <?php if (get_theme_mod('land_excavator_header_info_phone','Toll Free: +1234-567-7890')): ?>
                            <span class="contact-info">
                                <span class="main-box phone">
                                    <span class="main-head"><a href="tel:<?php echo esc_attr(get_theme_mod('land_excavator_header_info_phone', 'Toll Free: +1234-567-7890')); ?>"><i class="<?php echo esc_attr(get_theme_mod('land_excavator_header_phone_icon','fas fa-phone')); ?>"></i><?php echo esc_html(get_theme_mod('land_excavator_header_info_phone','Toll Free: +1234-567-7890')); ?></a></span>
                                </span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="header-info-right">
                        <?php if ( get_theme_mod('land_excavator_facebook_link_option','#') ) : ?><a href="<?php echo esc_url(get_theme_mod('land_excavator_facebook_link_option', '#')); ?>"><i class="fab fa-facebook"></i></a><?php endif; ?>
                        <?php if ( get_theme_mod('land_excavator_twitter_link_option','#') ) : ?><a href="<?php echo esc_url(get_theme_mod('land_excavator_twitter_link_option', '#')); ?>"><i class="fab fa-twitter"></i></span><?php endif; ?>
                        <?php if ( get_theme_mod('land_excavator_instagram_link_option','#') ) : ?><a href="<?php echo esc_url(get_theme_mod('land_excavator_instagram_link_option', '#')); ?>"><i class="fab fa-instagram"></i></a><?php endif; ?>
                        <?php if ( get_theme_mod('land_excavator_linkedin_link_option','#') ) : ?><a href="<?php echo esc_url(get_theme_mod('land_excavator_linkedin_link_option', '#')); ?>"><i class="fab fa-linkedin-in"></i></span><?php endif; ?>
                        <?php if ( get_theme_mod('land_excavator_pinterest_link_option','#') ) : ?><a href="<?php echo esc_url(get_theme_mod('land_excavator_pinterest_link_option', '#')); ?>"><i class="fab fa-pinterest-p"></i></a><?php endif; ?>
                        <?php if ( get_theme_mod('land_excavator_youtube_link_option','#') ) : ?><a href="<?php echo esc_url(get_theme_mod('land_excavator_youtube_link_option', '#')); ?>"><i class="fab fa-youtube"></i></a><?php endif; ?>
                        <?php if( get_theme_mod( 'land_excavator_header_search', false) == 1) { ?>
                            <span class="search-bar">
                                <button type="button" class="open-search"><i class="fas fa-search"></i></button>
                            </span>
                        <?php } ?>
                        <div class="search-outer">
                            <div class="inner_searchbox">
                                <?php get_search_form(); ?>
                            </div>
                            <button type="button" class="search-close"><i class="fa fa-window-close"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php $land_excavator_has_header_image = has_header_image();

        if ($land_excavator_has_header_image) {
            $land_excavator_header_image_url = esc_url(get_header_image());
        } else {
            $land_excavator_header_image_url = '';
        }
        ?>
        <div class="header-menu-box <?php echo esc_attr( get_theme_mod( 'land_excavator_enable_sticky_header', false ) ? 'sticky-header' : '' ); ?>" style="background-image: url('<?php echo $land_excavator_header_image_url; ?>');">
            <div class="container">
                <div class="flex-row">
                    <div class="nav-menu-header-left">
                        <div class="site-branding">
                            <?php
                            the_custom_logo();
                            if (is_front_page() && is_home()):
                                if (get_theme_mod('land_excavator_site_title_text', true)): ?>
                                    <h1 class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a></h1>
                                <?php endif;
                            else:
                                if (get_theme_mod('land_excavator_site_title_text', true)): ?>
                                    <p class="site-title"><a href="<?php echo esc_url(home_url('/')); ?>" rel="home"><?php bloginfo('name'); ?></a></p>
                                <?php endif;
                            endif;

                            $land_excavator_description = get_bloginfo('description', 'display');
                            if ($land_excavator_description || is_customize_preview()):
                                if (get_theme_mod('land_excavator_site_tagline_text', false)): ?>
                                    <p class="site-description"><?php echo esc_html($land_excavator_description); ?></p>
                                <?php endif;
                            endif; ?>
                        </div>
                    </div>
                    <div class="nav-menu-header-center">
                        <nav id="site-navigation" class="main-navigation">
                            <button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
                                <span class="screen-reader-text"><?php esc_html_e('Primary Menu', 'construction-equipments'); ?></span>
                                <i class="fas fa-bars"></i>
                            </button>
                            <?php
                            wp_nav_menu(array(
                                'theme_location' => 'menu-1',
                                'menu_id'        => 'primary-menu',
                            ));
                            ?>
                        </nav>
                    </div>
                    <div class="nav-menu-header-right">
                        <div class="header-button">
                            <?php if (get_theme_mod('land_excavator_header_button_link') || get_theme_mod('land_excavator_header_button_text', 'Get A Quote')): ?>
                                <a href="<?php echo esc_url(get_theme_mod('land_excavator_header_button_link')); ?>"><?php echo esc_html(get_theme_mod('land_excavator_header_button_text', 'Get A Quote')); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</div>
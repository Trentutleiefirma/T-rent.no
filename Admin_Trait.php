<?php

namespace REDQ_RnB\Traits;

use REDQ_RnB\Admin\TextTermMeta;
use REDQ_RnB\Admin\ImageTermMeta;
use REDQ_RnB\Admin\SelectTermMeta;
use REDQ_RnB\Admin\Term_Meta_Color;
use REDQ_RnB\Admin\IconTermMeta;
use RedQ_Rental_And_Bookings;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Handle rental data
 */
trait Admin_Trait
{
    /**
     * Create all taxonomies
     *
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function redq_register_inventory_taxonomies($taxonomy, $name, $post_type)
    {
        $labels = array(
            'name'              => _x(ucwords($name), 'taxonomy general name', 'redq-rental'),
            'singular_name'     => _x($name, 'taxonomy singular name'),
            'search_items'      => __('Search ' . $name . '', 'redq-rental'),
            'all_items'         => __('All ' . $name . '', 'redq-rental'),
            'parent_item'       => __('Parent ' . $name . '', 'redq-rental'),
            'parent_item_colon' => __('Parent ' . $name . ':', 'redq-rental'),
            'edit_item'         => __('Edit ' . $name . '', 'redq-rental'),
            'update_item'       => __('Update ' . $name . '', 'redq-rental'),
            'add_new_item'      => __('Add New ' . $name . '', 'redq-rental'),
            'new_item_name'     => __('New ' . $name . ' Name', 'redq-rental'),
            'menu_name'         => ucwords($name),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui_menu'      => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'public'            => true,
            'rewrite'           => array('slug' => $taxonomy),
        );

        register_taxonomy(str_replace(' ', '_', $taxonomy), $post_type, $args);
    }

    /**
     * Create all term meta
     *
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function redq_rental_initialize_taxonomy_term_meta()
    {
        $term_meta_args = rnb_term_meta_data_provider();

        if (sizeof($term_meta_args)) {
            foreach ($term_meta_args as $key => $term_meta_args) {
                switch ($term_meta_args['args']['type']) {
                    case 'text':
                        $this->redq_register_inventory_text_term_meta($term_meta_args['taxonomy'], $term_meta_args['args']);
                        break;
                    case 'select':
                        $this->redq_register_inventory_select_term_meta($term_meta_args['taxonomy'], $term_meta_args['args']);
                        break;
                    case 'image':
                        $this->redq_register_inventory_image_term_meta($term_meta_args['taxonomy'], $term_meta_args['args']);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    /**
     * Call text type term meta
     *
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function redq_register_inventory_text_term_meta($taxonomy, $args)
    {
        new TextTermMeta($taxonomy, $args);
    }

    /**
     * Call icon type term meta
     *
     * @author RedQTeam
     * @version 2.0.3
     * @since 2.0.3
     */
    public function redq_register_inventory_icon_term_meta($taxonomy, $args)
    {
        new IconTermMeta($taxonomy, $args);
    }

    /**
     * Call image type term meta
     *
     * @author RedQTeam
     * @version 2.0.3
     * @since 2.0.3
     */
    public function redq_register_inventory_image_term_meta($taxonomy, $args)
    {
        new ImageTermMeta($taxonomy, $args);
    }

    /**
     * Call select type term meta
     *
     * @author RedQTeam
     * @version 2.0.0
     * @since 2.0.0
     */
    public function redq_register_inventory_select_term_meta($taxonomy, $args)
    {
        new SelectTermMeta($taxonomy, $args);
    }
}

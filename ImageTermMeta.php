<?php

namespace REDQ_RnB\Admin;

/**
 * Handles taxonomies in admin
 *
 * @class    Rnb_Term_Meta_Generator_Image
 * @version  2.3.10
 * @package  RnB/Admin
 * @category Class
 * @author   RedQTeam
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * ImageTermMeta class.
 */
class ImageTermMeta
{
    public $taxonomy_name;
    public $fields;
    public $title;
    public $column_name;
    public $desc;
    public $term_key;


    /**
     * Constructor.
     */
    function __construct($taxonomy_name, $fields)
    {

        $this->taxonomy_name = $taxonomy_name;
        $this->fields = $fields;

        // Category/term ordering
        add_action('create_term', array($this, 'redq_rental_create_term'), 5, 3);
        add_action('delete_term', array($this, 'redq_rental_delete_term'), 5);

        // Add form
        add_action($taxonomy_name . '_add_form_fields', array($this, 'redq_rental_add_taxonomy_fields'));
        add_action($taxonomy_name . '_edit_form_fields', array($this, 'redq_rental_edit_taxonomy_fields'), 10);
        add_action('created_term', array($this, 'redq_rental_save_taxonomy_fields'), 10, 3);
        add_action('edit_term', array($this, 'redq_rental_save_taxonomy_fields'), 10, 3);

        // Add columns
        add_filter('manage_edit-' . $taxonomy_name . '_columns', array($this, 'redq_rental_taxonomy_columns'));
        add_filter('manage_' . $taxonomy_name . '_custom_column', array($this, 'redq_rental_taxonomy_column'), 10, 3);

        // Taxonomy page descriptions
        add_action($taxonomy_name . '_pre_add_form', array($this, 'redq_rental_taxonomy_description'));

        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if (!empty($attribute_taxonomies)) {
            foreach ($attribute_taxonomies as $attribute) {
                add_action('pa_' . $attribute->attribute_name . '_pre_add_form', array($this, 'redq_rental_product_attribute_description'));
            }
        }

        // Maintain hierarchy of terms
        add_filter('wp_terms_checklist_args', array($this, 'disable_checked_ontop'));


        if (isset($fields['title']) && !empty($fields['title'])) {
            $this->title = $fields['title'];
        } else {
            $this->title = 'unknown';
        }


        if (isset($fields['column_name']) && !empty($fields['column_name'])) {
            $this->column_name = $fields['column_name'];
        } else {
            $this->column_name = '';
        }

        if (isset($fields['desc']) && !empty($fields['desc'])) {
            $this->desc = $fields['desc'];
        } else {
            $this->desc = '';
        }

        if (isset($fields['id']) && !empty($fields['id'])) {
            $this->term_key = $fields['id'];
        } else {
            $this->term_key = '';
        }
    }

    /**
     * Order term when created (put in position 0).
     *
     * @param mixed $term_id
     * @param mixed $tt_id
     * @param string $taxonomy
     */
    public function redq_rental_create_term($term_id, $tt_id = '', $taxonomy = '')
    {
        if ($this->taxonomy_name != $taxonomy && !taxonomy_is_product_attribute($taxonomy)) {
            return;
        }

        $meta_name = taxonomy_is_product_attribute($taxonomy) ? 'order_' . esc_attr($taxonomy) : 'order';

        update_term_meta($term_id, $meta_name, 0);
    }

    /**
     * When a term is deleted, delete its meta.
     *
     * @param mixed $term_id
     */
    public function redq_rental_delete_term($term_id)
    {
        global $wpdb;

        $term_id = absint($term_id);

        if ($term_id && get_option('db_version') < 34370) {
            $wpdb->delete($wpdb->woocommerce_termmeta, array('woocommerce_term_id' => $term_id), array('%d'));
        }
    }

    /**
     * Category thumbnail fields.
     */
    public function redq_rental_add_taxonomy_fields()
    {
?>

        <div class="form-field term-thumbnail-wrap">
            <label><?php echo esc_attr($this->title); ?></label>
            <div id="resource_thumbnail" style="float: left; margin-right: 10px;"><img src="<?php echo esc_url(wc_placeholder_img_src()); ?>" width="60px" height="60px" /></div>
            <div style="line-height: 60px;">
                <input type="hidden" id="resource_thumbnail_id" name="<?php echo esc_attr($this->term_key); ?>" />
                <button type="button" class="upload_image_button button"><?php _e('Upload/Add image', 'woocommerce'); ?></button>
                <button type="button" class="remove_image_button button"><?php _e('Remove image', 'woocommerce'); ?></button>
            </div>
            <script type="text/javascript">
                // Only show the "remove image" button when needed
                if (!jQuery('#resource_thumbnail_id').val()) {
                    jQuery('.remove_image_button').hide();
                }

                // Uploading files
                var file_frame;

                jQuery(document).on('click', '.upload_image_button', function(event) {

                    event.preventDefault();

                    // If the media frame already exists, reopen it.
                    if (file_frame) {
                        file_frame.open();
                        return;
                    }

                    // Create the media frame.
                    file_frame = wp.media.frames.downloadable_file = wp.media({
                        title: '<?php _e("Choose an image", "woocommerce"); ?>',
                        button: {
                            text: '<?php _e("Use image", "woocommerce"); ?>'
                        },
                        multiple: false
                    });

                    // When an image is selected, run a callback.
                    file_frame.on('select', function() {
                        var attachment = file_frame.state().get('selection').first().toJSON();

                        jQuery('#resource_thumbnail_id').val(attachment.id);
                        jQuery('#resource_thumbnail').find('img').attr('src', attachment.sizes.full.url);
                        jQuery('.remove_image_button').show();
                    });

                    // Finally, open the modal.
                    file_frame.open();
                });

                jQuery(document).on('click', '.remove_image_button', function() {
                    jQuery('#resource_thumbnail').find('img').attr('src', '<?php echo esc_js(wc_placeholder_img_src()); ?>');
                    jQuery('#resource_thumbnail_id').val('');
                    jQuery('.remove_image_button').hide();
                    return false;
                });

                jQuery(document).ajaxComplete(function(event, request, options) {
                    if (request && 4 === request.readyState && 200 === request.status &&
                        options.data && 0 <= options.data.indexOf('action=add-tag')) {

                        var res = wpAjax.parseAjaxResponse(request.responseXML, 'ajax-response');
                        if (!res || res.errors) {
                            return;
                        }
                        // Clear Thumbnail fields on submit
                        jQuery('#resource_thumbnail').find('img').attr('src', '<?php echo esc_js(wc_placeholder_img_src()); ?>');
                        jQuery('#resource_thumbnail_id').val('');
                        jQuery('.remove_image_button').hide();
                        // Clear Display type field on submit
                        jQuery('#display_type').val('');
                        return;
                    }
                });
            </script>
            <div class="clear"></div>
        </div>
    <?php
    }

    /**
     * Edit category thumbnail field.
     *
     * @param mixed $term Term (category) being edited
     */
    public function redq_rental_edit_taxonomy_fields($term)
    {

        $display_type = get_term_meta($term->term_id, 'display_type', true);
        $thumbnail_id = absint(get_term_meta($term->term_id, $this->term_key, true));

        if ($thumbnail_id) {
            $image = wp_get_attachment_thumb_url($thumbnail_id);
        } else {
            $image = wc_placeholder_img_src();
        }
    ?>

        <tr class="form-field">
            <th scope="row" valign="top"><label><?php echo esc_attr($this->title); ?></label></th>
            <td>
                <div id="resource_thumbnail" style="float: left; margin-right: 10px;"><img src="<?php echo esc_url($image); ?>" width="60px" height="60px" /></div>
                <div style="line-height: 60px;">
                    <input type="hidden" id="resource_thumbnail_id" name="<?php echo esc_attr($this->term_key); ?>" value="<?php echo $thumbnail_id; ?>" />
                    <button type="button" class="upload_image_button button"><?php _e('Upload/Add image', 'woocommerce'); ?></button>
                    <button type="button" class="remove_image_button button"><?php _e('Remove image', 'woocommerce'); ?></button>
                </div>
                <script type="text/javascript">
                    // Only show the "remove image" button when needed
                    if ('0' === jQuery('#resource_thumbnail_id').val()) {
                        jQuery('.remove_image_button').hide();
                    }

                    // Uploading files
                    var file_frame;

                    jQuery(document).on('click', '.upload_image_button', function(event) {

                        event.preventDefault();

                        // If the media frame already exists, reopen it.
                        if (file_frame) {
                            file_frame.open();
                            return;
                        }

                        // Create the media frame.
                        file_frame = wp.media.frames.downloadable_file = wp.media({
                            title: '<?php _e("Choose an image", "woocommerce"); ?>',
                            button: {
                                text: '<?php _e("Use image", "woocommerce"); ?>'
                            },
                            multiple: false
                        });

                        // When an image is selected, run a callback.
                        file_frame.on('select', function() {
                            var attachment = file_frame.state().get('selection').first().toJSON();

                            console.log(attachment, 'attachment');

                            jQuery('#resource_thumbnail_id').val(attachment.id);
                            jQuery('#resource_thumbnail').find('img').attr('src', attachment.sizes.full.url);
                            jQuery('.remove_image_button').show();
                        });

                        // Finally, open the modal.
                        file_frame.open();
                    });

                    jQuery(document).on('click', '.remove_image_button', function() {
                        jQuery('#resource_thumbnail').find('img').attr('src', '<?php echo esc_js(wc_placeholder_img_src()); ?>');
                        jQuery('#resource_thumbnail_id').val('');
                        jQuery('.remove_image_button').hide();
                        return false;
                    });
                </script>
                <div class="clear"></div>
            </td>
        </tr>
<?php
    }

    /**
     * redq_rental_save_taxonomy_fields function.
     *
     * @param mixed $term_id Term ID being saved
     * @param mixed $tt_id
     * @param string $taxonomy
     */
    public function redq_rental_save_taxonomy_fields($term_id, $tt_id = '', $taxonomy = '')
    {
        if (isset($_POST['display_type']) && $this->taxonomy_name === $taxonomy) {
            update_term_meta($term_id, 'display_type', esc_attr($_POST['display_type']));
        }
        if (isset($_POST[$this->term_key]) && $this->taxonomy_name === $taxonomy) {
            update_term_meta($term_id, $this->term_key, absint($_POST[$this->term_key]));
        }
    }

    /**
     * Description for resource page to aid users.
     */
    public function redq_rental_taxonomy_description()
    {
        echo wpautop(__('Product categories for your store can be managed here. To change the order of categories on the front-end you can drag and drop to sort them. To see more categories listed click the "screen options" link at the top of the page.', 'redq-rental'));
    }

    /**
     * Description for shipping class page to aid users.
     */
    public function redq_rental_product_attribute_description()
    {
        echo wpautop(__('Attribute terms can be assigned to products and variations.<br/><br/><b>Note</b>: Deleting a term will remove it from all products and variations to which it has been assigned. Recreating a term will not automatically assign it back to products.', 'woocommerce'));
    }

    /**
     * Thumbnail column added to category admin.
     *
     * @param mixed $columns
     * @return array
     */
    public function redq_rental_taxonomy_columns($columns)
    {
        $new_columns = array();

        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
            unset($columns['cb']);
        }

        $new_columns['thumb'] = $this->column_name;

        return array_merge($new_columns, $columns);
    }

    /**
     * Thumbnail column value added to category admin.
     *
     * @param string $columns
     * @param string $column
     * @param int $id
     * @return array
     */
    public function redq_rental_taxonomy_column($columns, $column, $id)
    {

        if ('thumb' == $column) {

            $thumbnail_id = get_term_meta($id, $this->term_key, true);

            if ($thumbnail_id) {
                $image = wp_get_attachment_thumb_url($thumbnail_id);
            } else {
                $image = wc_placeholder_img_src();
            }

            // Prevent esc_url from breaking spaces in urls for image embeds
            // Ref: https://core.trac.wordpress.org/ticket/23605
            $image = str_replace(' ', '%20', $image);

            $columns .= '<img src="' . esc_url($image) . '" alt="' . esc_attr__('Thumbnail', 'woocommerce') . '" class="wp-post-image" height="48" width="48" />';
        }

        return $columns;
    }

    /**
     * Maintain term hierarchy when editing a product.
     *
     * @param  array $args
     * @return array
     */
    public function disable_checked_ontop($args)
    {
        if (!empty($args[$this->taxonomy_name]) && $this->taxonomy_name === $args[$this->taxonomy_name]) {
            $args['checked_ontop'] = false;
        }
        return $args;
    }
}

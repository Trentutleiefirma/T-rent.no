<?php

namespace REDQ_RnB\Admin;

/**
 * Class Up_Term_Meta_Generator_icon
 *
 * @author      RedQTeam
 * @category    Admin
 * @package     REDQ\Admin
 * @version     2.0.3
 * @since       2.0.3
 */
class IconTermMeta
{

    function __construct($taxonomy_name, $fields)
    {

        $this->taxonomy_name = $taxonomy_name;
        $this->fields = $fields;

        add_action($taxonomy_name . '_add_form_fields', array($this, 'add_taxonomy_form_field'));
        add_action('created_' . $taxonomy_name, array($this, 'save_taxonomy_form_field'));
        add_action($taxonomy_name . '_edit_form_fields', array($this, 'edit_taxonomy_form_field'));
        add_action('edited_' . $taxonomy_name, array($this, 'save_edit_taxonomy_form_field'));
        add_filter('manage_edit-' . $taxonomy_name . '_columns', array($this, 'manage_columns_for_taxonomy_form_field'));
        add_filter('manage_' . $taxonomy_name . '_custom_column', array($this, 'show_columns_data'), 10, 3);
        add_filter('manage_edit-' . $taxonomy_name . '_sortable_columns', array($this, 'sorting_term_column'), 10, 3);

        if (isset($fields['title']) && !empty($fields['title'])) {
            $this->title = $fields['title'];
        } else {
            $this->title = 'unknown';
        }

        if (isset($fields['sub_title']) && !empty($fields['sub_title'])) {
            $this->sub_title = $fields['sub_title'];
        } else {
            $this->sub_title = '';
        }

        if (isset($fields['column_name']) && !empty($fields['column_name'])) {
            $this->column_name = $fields['column_name'];
        } else {
            $this->column_name = '';
        }

        if (isset($fields['placeholder']) && !empty($fields['placeholder'])) {
            $this->placeholder = $fields['placeholder'];
        } else {
            $this->placeholder = '';
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

        if (isset($fields['text_type']) && !empty($fields['text_type'])) {
            $this->text_type = $fields['text_type'];
        } else {
            $this->text_type = '';
        }

        if (isset($fields['required']) && !empty($fields['required'])) {
            $this->required = 'form-required';
        } else {
            $this->required = 'optional-required';
        }
    }

    public function add_taxonomy_form_field($taxonomy)
    {  ?>

        <div class="form-field term-name-wrap <?php echo esc_attr($this->required); ?>">
            <label for="<?php echo esc_attr($this->term_key); ?>"><?php echo esc_attr($this->title); ?>&nbsp;<span class="term-subtitle"><?php echo esc_attr($this->sub_title); ?></span></label>
            <input name="<?php echo esc_attr($this->term_key); ?>" id="<?php echo esc_attr($this->term_key); ?>" type="text" placeholder="<?php echo esc_attr($this->placeholder); ?>" value="" size="40">
            <p><?php echo esc_attr($this->desc); ?></p>
        </div>

    <?php
    }

    public function save_taxonomy_form_field($term_id)
    {
        if (isset($_POST[$this->term_key]) && '' !== $_POST[$this->term_key]) {
            $value = $_POST[$this->term_key];
            add_term_meta($term_id, $this->term_key, $value, true);
        }
    }

    public function edit_taxonomy_form_field($term)
    {
        $value = get_term_meta($term->term_id, $this->term_key, true);
    ?>
        <tr class="form-field <?php echo esc_attr($this->required); ?> term-name-wrap">
            <th scope="row"><label for="name"><?php echo esc_attr($this->title); ?></label></th>
            <td><input name="<?php echo esc_attr($this->term_key); ?>" id="<?php echo esc_attr($this->term_key); ?>" type="text" placeholder="<?php echo esc_attr($this->placeholder); ?>" value="<?php echo esc_attr($value); ?>" size="40">
        </tr>

<?php
    }

    public function save_edit_taxonomy_form_field($term_id)
    {
        if (isset($_POST[$this->term_key])) {
            $value = $_POST[$this->term_key];
            update_term_meta($term_id, $this->term_key, $value);
        }
    }

    public function manage_columns_for_taxonomy_form_field($columns)
    {
        $columns[$this->title] = __($this->column_name, 'redq-rental');
        return $columns;
    }


    public function show_columns_data($content, $column_name, $term_id)
    {

        if ($column_name !== $this->title) {
            return $content;
        }

        $term_id = absint($term_id);
        $value = get_term_meta($term_id, $this->term_key, true);

        if (!empty($value)) {
            $content .= "<i class='$value'></i>";
        }

        return $content;
    }



    public function sorting_term_column($sortable)
    {
        $sortable[$this->title] = $this->title;
        return $sortable;
    }
}

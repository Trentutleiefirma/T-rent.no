<?php

namespace REDQ_RnB\Admin;

/**
 * Class Up_Term_Meta_Generator_select
 *
 * @author      RedQTeam
 * @category    Admin
 * @package     RnB\Admin
 * @version     1.0.3
 * @since       1.0.3
 */
class SelectTermMeta
{
    public $taxonomy_name;
    public $fields;
    public $title;
    public $sub_title;
    public $column_name;
    public $placeholder;
    public $desc;
    public $term_key;
    public $text_type;
    public $required;

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
            $this->term_key = $fields['uid'];
        }
    }

    public function add_taxonomy_form_field($taxonomy)
    { ?>

        <div class="form-field term-group">
            <label for="featuret-group"><?php echo esc_attr($this->title); ?>&nbsp;<span class="term-subtitle"><?php echo esc_attr($this->sub_title); ?></span></label>
            <select class="postform" id="<?php echo esc_attr($this->term_key); ?>" name="<?php echo esc_attr($this->term_key); ?>">
                <option value=""><?php _e('none', 'my_plugin'); ?></option>
                <?php foreach ($this->fields['options'] as $_group_key => $_group) : ?>
                    <option value="<?php echo $_group['key']; ?>" class=""><?php echo $_group['value']; ?></option>
                <?php endforeach; ?>
            </select>
            <p><?php echo esc_attr($this->desc); ?></p>
        </div>

    <?php
    }

    public function save_taxonomy_form_field($term_id)
    {
        if (isset($_POST[$this->term_key]) && '' !== $_POST[$this->term_key]) {
            $value = sanitize_title($_POST[$this->term_key]);
            add_term_meta($term_id, $this->term_key, $value, true);
        }
    }


    public function edit_taxonomy_form_field($term)
    {

        $value = get_term_meta($term->term_id, $this->term_key, true);

    ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="<?php echo esc_attr($this->term_key); ?>"><?php echo esc_attr($this->title); ?></label></th>
            <td>
                <select class="postform" id="<?php echo esc_attr($this->term_key); ?>" name="<?php echo esc_attr($this->term_key); ?>">
                    <option value=""><?php _e('none', 'userplace'); ?></option>
                    <?php foreach ($this->fields['options'] as $_group_key => $_group) : ?>
                        <option value="<?php echo $_group['key']; ?>" <?php selected($value, $_group['key']); ?>><?php echo $_group['value']; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

<?php
    }


    public function save_edit_taxonomy_form_field($term_id)
    {
        if (isset($_POST[$this->term_key]) && '' !== $_POST[$this->term_key]) {
            $value = sanitize_title($_POST[$this->term_key]);
            update_term_meta($term_id, $this->term_key, $value);
        }
    }

    public function manage_columns_for_taxonomy_form_field($columns)
    {
        $columns[$this->fields['title']] = __($this->column_name, 'userplace');
        return $columns;
    }

    public function show_columns_data($content, $column_name, $term_id)
    {

        if ($column_name !== $this->fields['title']) {
            return $content;
        }

        $term_id = absint($term_id);
        $value = get_term_meta($term_id, $this->term_key, true);

        if (!empty($value)) {
            $content .=  esc_attr($value);
        }

        return $content;
    }

    public function sorting_term_column($sortable)
    {
        $sortable[$this->fields['title']] = $this->fields['title'];
        return $sortable;
    }
}

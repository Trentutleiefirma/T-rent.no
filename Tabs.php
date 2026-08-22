<?php

namespace REDQ_RnB;

use WC_Product_Redq_Rental;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rental Product Tab Class.
 *
 * The WooCommerce product class handles individual product data.
 *
 * @class        WC_Product_Redq_Rental
 * @version    1.0.0
 * @since        1.0.0
 * @package        WooCommerce-rental-and-booking/includes
 * @category    Class
 * @author        RedQTeam
 */
class Tabs
{
    /**
     * Constructor.
     *
     * @param null
     */
    public function __construct()
    {
        add_action('woocommerce_product_tabs', array($this, 'redq_rental_product_tabs'));
    }


    /**
     * Initialize attribute and feature tabs
     *
     * @param $tabs
     * @return WC_Product or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public function redq_rental_product_tabs($tabs)
    {

        $product_type = wc_get_product(get_the_ID())->get_type();
        $get_general = redq_rental_get_settings(get_the_ID(), 'general');
        $general_data = $get_general['general'];

        if (isset($product_type) && $product_type === 'redq_rental') {
            $item_attributes = WC_Product_Redq_Rental::redq_get_rental_non_payable_attributes('attributes', get_the_ID());
            if (is_array($item_attributes) && !empty($item_attributes)) {
                if (array_key_exists_recursive('attributes', $item_attributes)) {
                    $tabs['attributes'] = array(
                        'title'    => $general_data['attribute_tab'],
                        'priority' => 5,
                        'callback' =>  "REDQ_RnB\Tabs::redq_attributes", //'Tabs::redq_attributes'
                    );
                }
            }

            $additional_features = WC_Product_Redq_Rental::redq_get_rental_non_payable_attributes('features', get_the_ID());

            if (is_array($additional_features) && !empty($additional_features) && array_key_exists_recursive('features', $additional_features)) {
                $tabs['features'] = array(
                    'title'    => $general_data['feature_tab'],
                    'priority' => 8,
                    'callback' => "REDQ_RnB\Tabs::redq_features",
                );
            }
        }

        return $tabs;
    }

    /**
     * Attributes tab callback function
     *
     * @return void or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public static function redq_attributes()
    {
        global $product;
        $inventory = rnb_get_product_inventory_id($product->get_id());
        $show_inventory_name = is_array($inventory) && count($inventory) > 1 ? true : false;

        $item_attributes = WC_Product_Redq_Rental::redq_get_rental_non_payable_attributes('attributes');
        if (array_key_exists_recursive('attributes', $item_attributes)) :
            foreach ($item_attributes as $attributes) :
                if (array_key_exists_recursive('attributes', $attributes)) :
?>
                    <div class="item-arrtributes">
                        <?php if ($show_inventory_name) : ?>
                            <h5><?php echo $attributes['title'] ?></h5>
                        <?php endif; ?>
                        <ul class="attributes">
                            <?php foreach ($attributes['attributes'] as $key => $value) { ?>
                                <li>
                                    <?php if (isset($value['image']) && !empty($value['image'])) : ?>
                                        <?php $img = wp_get_attachment_image_src($value['image']); ?>
                                        <span class="rnb-attribute-image"><img src="<?php echo esc_url(wp_get_attachment_image_url($value['image'])); ?>" alt="img"></i></span>
                                    <?php else : ?>
                                        <span class="attribute-icon"><i class="<?php echo esc_attr($value['icon']); ?>"></i></span>
                                    <?php endif; ?>
                                    <span class="attribute-name"><?php echo esc_attr($value['name']); ?></span>
                                    <span class="attribute-vaue">: <?php echo esc_attr($value['value']); ?></span>
                                </li>
                            <?php

                            } ?>
                        </ul>
                    </div>
            <?php endif;
            endforeach; ?>
        <?php endif; ?>
    <?php

    }


    /**
     * Features tab callback function
     *
     * @return void or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public static function redq_features()
    {
        global $product;
        $inventory = rnb_get_product_inventory_id($product->get_id());
        $show_inventory_name = is_array($inventory) && count($inventory) > 1 ? true : false;

        $additional_features = WC_Product_Redq_Rental::redq_get_rental_non_payable_attributes('features', get_the_ID());

        if (!array_key_exists_recursive('features', $additional_features)) {
            return;
        }
    ?>
        <?php foreach ($additional_features as $features) : ?>
            <?php
            if (!array_key_exists_recursive('features', $features)) {
                continue;
            }
            ?>
            <div class="item-extras">
                <?php if ($show_inventory_name) : ?>
                    <h5><?php echo $features['title'] ?></h5>
                <?php endif; ?>
                <ul class="attributes">
                    <?php foreach ($features['features'] as $key => $value) { ?>
                        <li>
                            <?php if (isset($value['image']) && !empty($value['image'])) : ?>
                                <span class="rnb-attribute-image"><img src="<?php echo esc_url(wp_get_attachment_image_url($value['image'])); ?>" alt="img"></i></span>
                            <?php endif; ?>
                            <span class="attribute-name"><?php echo esc_attr($value['name']); ?></span>
                        </li>
                    <?php
                    } ?>
                </ul>
            </div>
        <?php endforeach ?>
<?php

    }
}
// new Tabs();

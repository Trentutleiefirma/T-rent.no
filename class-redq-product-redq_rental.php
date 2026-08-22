<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Rental Product Class.
 *
 * The WooCommerce product class handles individual product data.
 *
 * @class        WC_Product_Redq_Rental
 * @version        1.0.0
 * @package        WooCommerce-rental-and-booking/includes
 * @category    Class
 * @author        RedQTeam
 */
class WC_Product_Redq_Rental extends WC_Product
{
    public $product_type;

    /**
     * Constructor.
     *
     * @param mixed $product
     */
    public function __construct($product)
    {
        $this->product_type = 'redq_rental';
        parent::__construct($product);
        add_filter('woocommerce_product_add_to_cart_text', array($this, 'rnb_shop_button_text'), 20, 2);
    }


    /**
     * Get product pricing type
     *
     * @param $product_id
     * @return WC_Product or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public function redq_get_pricing_type($product_id)
    {
        if (isset($product_id) && !empty($product_id)) {
            $id = $product_id;
        } else {
            $id = get_the_ID();
        }

        $pricing_type = get_post_meta($id, 'pricing_type', true);

        return apply_filters('redq_pricing_type', $pricing_type);
    }


    /**
     * Get product daily pricing
     *
     * @param $product_id
     * @return WC_Product or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public function redq_get_daily_pricing($product_id)
    {
        if (isset($product_id) && !empty($product_id)) {
            $id = $product_id;
        } else {
            $id = get_the_ID();
        }

        $daily_pricing = get_post_meta($id, 'redq_daily_pricing', true);

        return apply_filters('redq_daily_pricing', $daily_pricing);
    }


    /**
     * Get product monthly pricing
     *
     * @param $product_id
     * @return WC_Product or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public function redq_get_monthly_pricing($product_id)
    {
        if (isset($product_id) && !empty($product_id)) {
            $id = $product_id;
        } else {
            $id = get_the_ID();
        }

        $monthly_pricing = get_post_meta($id, 'redq_monthly_pricing', true);

        return apply_filters('redq_monthly_pricing', $monthly_pricing);
    }


    /**
     * Get product day ranges pricing
     *
     * @param $product_id
     * @return WC_Product or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public function redq_get_day_ranges_pricing($product_id)
    {
        if (isset($product_id) && !empty($product_id)) {
            $id = $product_id;
        } else {
            $id = get_the_ID();
        }

        $day_ranges_pricing_plans = get_post_meta($id, 'redq_day_ranges_cost', true);

        return apply_filters('redq_day_ranges_pricing', $day_ranges_pricing_plans);
    }

    /**
     * Get product payable resources, person , security deposite, pickup and dorpoff locations
     *
     * @param string $taxonomy
     * @param int $inventory_id
     * @return array | boolean or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public static function redq_get_rental_payable_attributes($taxonomy, $inventory_id = null)
    {
        $args = array(
            'orderby' => 'name',
            'order'   => 'ASC',
            'fields'  => 'all',
        );
        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($inventory_id, $taxonomy, $args);
        }

        switch ($taxonomy) {

            case 'pickup_location':
                $pickup_locations = array();
                if (isset($terms) && is_array($terms)) {
                    foreach ($terms as $key => $value) {
                        $term_id = $value->term_id;
                        $pickup_cost = get_term_meta($term_id, 'inventory_pickup_cost_termmeta', true);
                        $selected = get_term_meta($term_id, 'inventory_pickup_location_selected_term_meta', true);
                        $pickup_lat = get_term_meta($term_id, 'inventory_pickup_location_lat', true);
                        $pickup_long = get_term_meta($term_id, 'inventory_pickup_location_lng', true);

                        $pickup_locations[$key]['id'] = $term_id;
                        $pickup_locations[$key]['title'] = $value->name;
                        $pickup_locations[$key]['slug'] = $value->slug;
                        $pickup_locations[$key]['address'] = $value->description ? $value->description : $value->name;
                        $pickup_locations[$key]['cost'] = $pickup_cost ? $pickup_cost : 0;
                        $pickup_locations[$key]['selected'] = $selected ? $selected : 'no';
                        $pickup_locations[$key]['lat'] = $pickup_lat;
                        $pickup_locations[$key]['lon'] = $pickup_long;
                    }
                }

                return apply_filters('rnb_payable_pickup_location', $pickup_locations, $inventory_id, $taxonomy);

                break;

            case 'dropoff_location':
                $dropoff_locations = array();

                if (isset($terms) && is_array($terms)) {
                    foreach ($terms as $key => $value) {
                        $term_id = $value->term_id;
                        $dropoff_cost = get_term_meta($term_id, 'inventory_dropoff_cost_termmeta', true);
                        $selected = get_term_meta($term_id, 'inventory_dropoff_location_selected_term_meta', true);
                        $dropoff_lat = get_term_meta($term_id, 'inventory_dropoff_location_lat', true);
                        $dropoff_long = get_term_meta($term_id, 'inventory_dropoff_location_lng', true);
                        $dropoff_locations[$key]['id'] = $term_id;
                        $dropoff_locations[$key]['title'] = $value->name;
                        $dropoff_locations[$key]['slug'] = $value->slug;
                        $dropoff_locations[$key]['address'] = $value->description ? $value->description : $value->name;
                        $dropoff_locations[$key]['cost'] = $dropoff_cost ? $dropoff_cost : 0;
                        $dropoff_locations[$key]['selected'] = $selected ? $selected : 'no';
                        $dropoff_locations[$key]['lat'] = $dropoff_lat;
                        $dropoff_locations[$key]['lon'] = $dropoff_long;
                    }
                }

                return apply_filters('rnb_payable_return_location', $dropoff_locations, $inventory_id, $taxonomy);

                break;

            case 'resource':
                $resources = [];

                if (isset($terms) && is_array($terms)) {
                    foreach ($terms as $key => $term) {
                        $cost        = get_term_meta($term->term_id, 'inventory_resource_cost_termmeta', true);
                        $applicable  = get_term_meta($term->term_id, 'inventory_price_applicable_term_meta', true);
                        $hourly_cost = get_term_meta($term->term_id, 'inventory_hourly_cost_termmeta', true);
                        $clickable   = get_term_meta($term->term_id, 'rnb_resource_clickable', true);
                        $resources[] = [
                            'id'                   => $term->term_id,
                            'resource_name'        => $term->name,
                            'description'          => $term->description,
                            'resource_slug'        => $term->slug,
                            'resource_cost'        => $cost ? $cost : 0,
                            'resource_applicable'  => $applicable,
                            'resource_hourly_cost' => $hourly_cost ? $hourly_cost : 0,
                            'clickable'            => $clickable ? $clickable : 'yes'
                        ];
                    }
                }

                return apply_filters('rnb_payable_resources', $resources, $inventory_id, $taxonomy);
                break;

            case 'person':
                $person_info = array();
                $persons = array();
                if (isset($terms) && is_array($terms)) {
                    foreach ($terms as $key => $value) {
                        $term_id = $value->term_id;
                        $payable = get_term_meta($term_id, 'inventory_person_payable_or_not', true);
                        $type = get_term_meta($term_id, 'inventory_person_type', true);
                        $cost = get_term_meta($term_id, 'inventory_person_cost_termmeta', true);
                        $applicable = get_term_meta($term_id, 'inventory_person_price_applicable_term_meta', true);
                        $hourly_cost = get_term_meta($term_id, 'inventory_peroson_hourly_cost_termmeta', true);
                        $person_info[$key]['id'] = $term_id;
                        $person_info[$key]['person_type'] = $type;
                        $person_info[$key]['person_count'] = $value->name;
                        $person_info[$key]['person_slug'] = $value->slug;
                        $person_info[$key]['person_payable'] = $payable;
                        $person_info[$key]['person_cost'] = $cost ? $cost : 0;
                        $person_info[$key]['person_cost_applicable'] = $applicable;
                        $person_info[$key]['person_hourly_cost'] = $hourly_cost ? $hourly_cost : 0;
                    }
                }

                if (isset($person_info) && !empty($person_info)) {
                    foreach ($person_info as $key => $value) {
                        if ($value['person_type'] === 'child') {
                            $persons['childs'][] = $value;
                        } else {
                            $persons['adults'][] = $value;
                        }
                    }
                }

                return apply_filters('rnb_payable_person', $persons, $inventory_id, $taxonomy);

                break;

            case 'deposite':
                $security_deposites = array();

                if (isset($terms) && is_array($terms)) {
                    foreach ($terms as $key => $value) {
                        $term_id = $value->term_id;
                        $sd_cost = get_term_meta($term_id, 'inventory_sd_cost_termmeta', true);
                        $sd_applicable = get_term_meta($term_id, 'inventory_sd_price_applicable_term_meta', true);
                        $sd_clickable = get_term_meta($term_id, 'inventory_sd_price_clickable_term_meta', true);
                        $sd_hourly_cost = get_term_meta($term_id, 'inventory_sd_hourly_cost_termmeta', true);
                        $security_deposites[$key]['id'] = $term_id;
                        $security_deposites[$key]['security_deposite_name'] = $value->name;
                        $security_deposites[$key]['security_deposite_slug'] = $value->slug;
                        $security_deposites[$key]['description'] = $value->description;
                        $security_deposites[$key]['security_deposite_cost'] = $sd_cost ? $sd_cost : 0;
                        $security_deposites[$key]['security_deposite_applicable'] = $sd_applicable;
                        $security_deposites[$key]['security_deposite_clickable'] = $sd_clickable;
                        $security_deposites[$key]['security_deposite_hourly_cost'] = $sd_hourly_cost ? $sd_hourly_cost : 0;
                    }
                }

                return apply_filters('rnb_payable_security_deposite', $security_deposites, $inventory_id, $taxonomy);

                break;


            case 'rnb_categories':

                $rnb_categories = array();

                if (isset($terms) && is_array($terms)) {
                    foreach ($terms as $key => $value) {
                        $term_id = $value->term_id;
                        $payable = get_term_meta($term_id, 'inventory_rnb_cat_payable_or_not', true);
                        $qty = get_term_meta($term_id, 'inventory_rnb_cat_qty', true);
                        $cost = get_term_meta($term_id, 'inventory_rnb_cat_cost_termmeta', true);
                        $applicable = get_term_meta($term_id, 'inventory_rnb_cat_price_applicable_term_meta', true);
                        $clickable = get_term_meta($term_id, 'inventory_rnb_cat_clickable_term_meta', true);
                        $hourly_cost = get_term_meta($term_id, 'inventory_rnb_cat_hourly_cost_termmeta', true);
                        $rnb_categories[$key]['id'] = $term_id;
                        $rnb_categories[$key]['name'] = $value->name;
                        $rnb_categories[$key]['slug'] = $value->slug;
                        $rnb_categories[$key]['description'] = $value->description;
                        $rnb_categories[$key]['qty'] = $qty;
                        $rnb_categories[$key]['payable'] = $payable;
                        $rnb_categories[$key]['cost'] = $cost ? $cost : 0;
                        $rnb_categories[$key]['applicable'] = $applicable;
                        $rnb_categories[$key]['clickable'] = $clickable;
                        $rnb_categories[$key]['hourlycost'] = $hourly_cost ? $hourly_cost : 0;
                    }
                }

                return apply_filters('rnb_payable_category', $rnb_categories, $inventory_id, $taxonomy);

                break;


            default:
                return false;
                break;
        }
    }


    /**
     * Get product non-payable attributes and features
     *
     * @param string $taxonomy
     * @param int $product_id
     * @version        1.7.0
     * @access public
     * @return  array
     */
    public static function redq_get_rental_non_payable_attributes($taxonomy, $product_id = null)
    {

        if (empty($product_id)) {
            $product_id = get_the_ID();
        }

        $redq_product_inventory = rnb_get_product_inventory_id($product_id);
        if (count($redq_product_inventory) === 0) {
            return;
        }

        $inventories = get_posts(array(
            'post_type'      => 'Inventory',
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'posts_per_page' => -1,
            'post__in'       => $redq_product_inventory,
        ));

        foreach ($inventories as $index => $inventory) {
            $inventories[$index]->$taxonomy = wp_get_post_terms($inventory->ID, $taxonomy, array('order' => 'DESC', 'orderby' => 'menu_order'));
        }

        switch ($taxonomy) {

            case 'attributes':
                $attributes = array();
                if ($inventories) {
                    foreach ($inventories as $index => $inventory) {
                        $attributes[$index]['ID'] = $inventory->ID;
                        $attributes[$index]['title'] = $inventory->post_title;
                        if (isset($inventory->$taxonomy) && !empty($inventory->$taxonomy)) {
                            foreach ($inventory->$taxonomy as $key => $term) {
                                $term_id = $term->term_id;
                                $name = get_term_meta($term_id, 'inventory_attribute_name', true);
                                $avalue = get_term_meta($term_id, 'inventory_attribute_value', true);
                                $icon = get_term_meta($term_id, 'inventory_attribute_icon', true);
                                $selected_icon = get_term_meta($term_id, 'choose_attribute_icon', true);
                                $image = get_term_meta($term_id, 'attributes_image_icon', true);
                                $highlighted = get_term_meta($term_id, 'inventory_attribute_highlighted', true);
                                $attributes[$index][$taxonomy][$key]['name'] = isset($name) && !empty($name) ? $name : $term->name;
                                $attributes[$index][$taxonomy][$key]['value'] = $avalue;
                                $attributes[$index][$taxonomy][$key]['selected_icon'] = $selected_icon === 'image' ? $selected_icon : 'icon';
                                $attributes[$index][$taxonomy][$key]['icon'] = $icon;
                                $attributes[$index][$taxonomy][$key]['image'] = $image;
                                $attributes[$index][$taxonomy][$key]['highlighted'] = $highlighted === 'yes' ? $highlighted : 'no';
                            }
                        }
                    }
                }

                return apply_filters('redq_non_payable_attributes', $attributes);

                break;

            case 'features':
                $features = [];

                if (empty($inventories)) {
                    $features =  apply_filters('redq_non_payable_features', $features);
                }

                foreach ($inventories as $index => $inventory) {
                    $features[$index]['ID'] = $inventory->ID;
                    $features[$index]['title'] = $inventory->post_title;

                    if (empty($inventory->$taxonomy)) {
                        continue;
                    }

                    foreach ($inventory->$taxonomy as $key => $term) {
                        $highlighted = get_term_meta($term->term_id, 'inventory_feature_highlighted', true);
                        $image = get_term_meta($term->term_id, 'feature_image_icon', true);
                        $features[$index][$taxonomy][$key]['name'] = $term->name;
                        $features[$index][$taxonomy][$key]['highlighted'] = $highlighted;
                        $features[$index][$taxonomy][$key]['image'] = $image;
                    }
                }

                return apply_filters('redq_non_payable_features', $features);
                break;

            default:
                return 'something goes wrong';
                break;
        }
    }

    public function rnb_product_features($product_id)
    {
        return 'this function can be accessed through product object';
    }


    /**
     * Get all weekend days
     *
     * @param $year
     * @param $month
     * @param $day
     * @return DatePeriod or WC_Product_Rental_product
     * @throws Exception
     * @version        1.7.0
     * @access public
     */
    public static function getWednesdays($year, $month, $day)
    {
        return new DatePeriod(
            new DateTime("first $day of $year-$month"),
            DateInterval::createFromDateString('next ' . $day . ''),
            new DateTime("last day of $year-$month-01")
        );
    }


    /**
     * Get car company name
     *
     * @param string $taxonomy
     * @return array or WC_Product_Rental_product
     * @version        1.7.0
     * @access public
     */
    public static function redq_get_rental_car_company($taxonomy)
    {

        $post_id = get_the_ID();
        $car_companies = array();

        $args = array(
            'orderby' => 'name',
            'order'   => 'ASC',
            'fields'  => 'all',
        );

        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($post_id, $taxonomy, $args);
        }

        if (isset($terms) && is_array($terms)) {
            foreach ($terms as $key => $value) {
                $term_id = $value->term_id;
                $car_companies[$key]['name'] = $value->name;
            }
        }
        return $car_companies;
    }

    /**
     * Get the add to cart button text.
     *
     * @access public
     * @param $label
     * @param $product
     * @return string
     */
    public function rnb_shop_button_text($label, $product)
    {
        if (!empty($product) && $product->get_type() === 'redq_rental') {
            return get_option('rnb_shop_page_button', __('Read More', 'redq-rental'));
        }
        return $label;
    }


    /**
     * Get the add to cart button text.
     *
     * @access public
     * @return string
     */
    public function single_add_to_cart_text()
    {
        return apply_filters('rnb_product_add_to_cart_text', $this->get_button_text(), $this);
    }


    /**
     * Get the Request for quote button text.
     *
     * @access public
     * @return string
     */
    public function single_request_for_quote_text()
    {
        return apply_filters('rnb_product_request_for_quote_text', $this->get_rfq_button_text(), $this);
    }


    /**
     * Get button text.
     *
     * @access public
     * @return string
     */
    public function get_button_text()
    {
        $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('buttons'));
        $labels = $labels['labels'];

        return $labels['book_now'];
    }


    /**
     * Get RFQ button text.
     *
     * @access public
     * @return string
     */
    public function get_rfq_button_text()
    {
        $labels = redq_rental_get_settings(get_the_ID(), 'labels', array('buttons'));
        $labels = $labels['labels'];
        return $labels['rfq'];
    }


    /**
     * Translate keys
     *
     * @param $type
     * @param $pricing
     * @return string
     * @version        1.7.0
     * @access public
     */
    public static function redq_translate_keys($type, $pricing)
    {

        switch ($type) {

            case 'daily_pricing':
                $translated_days = array();
                foreach ($pricing as $key => $value) {
                    switch ($key) {
                        case 'friday':
                            $key = __('Friday', 'redq-rental');
                            break;
                        case 'saturday':
                            $key = __('Saturday', 'redq-rental');
                            break;
                        case 'sunday':
                            $key = __('Sunday', 'redq-rental');
                            break;
                        case 'monday':
                            $key = __('Monday', 'redq-rental');
                            break;
                        case 'tuesday':
                            $key = __('Tuesday', 'redq-rental');
                            break;
                        case 'wednesday':
                            $key = __('Wednesday', 'redq-rental');
                            break;
                        case 'thursday':
                            $key = __('Thursday', 'redq-rental');
                            break;

                        default:
                            break;
                    }
                    $translated_days[$key] = $value;
                }

                return apply_filters('redq_translated_days', $translated_days);

                break;

            case 'monthly_pricing':
                $translated_month = array();
                foreach ($pricing as $key => $value) {
                    switch ($key) {
                        case 'january':
                            $key = __('january', 'redq-rental');
                            break;
                        case 'february':
                            $key = __('february', 'redq-rental');
                            break;
                        case 'march':
                            $key = __('march', 'redq-rental');
                            break;
                        case 'april':
                            $key = __('april', 'redq-rental');
                            break;
                        case 'may':
                            $key = __('may', 'redq-rental');
                            break;
                        case 'june':
                            $key = __('june', 'redq-rental');
                            break;
                        case 'july':
                            $key = __('july', 'redq-rental');
                            break;
                        case 'august':
                            $key = __('august', 'redq-rental');
                            break;
                        case 'september':
                            $key = __('september', 'redq-rental');
                            break;
                        case 'october':
                            $key = __('october', 'redq-rental');
                            break;
                        case 'november':
                            $key = __('november', 'redq-rental');
                            break;
                        case 'december':
                            $key = __('december', 'redq-rental');
                            break;
                        default:
                            break;
                    }

                    $translated_month[$key] = $value;
                }

                return apply_filters('redq_translated_months', $translated_month);

                break;

            default:
                return 'something goes wrong';
                break;
        }
    }
}

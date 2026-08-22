<?php

namespace REDQ_RnB\Admin;

/**
 * Class Rnb_Save_Meta
 *
 *
 * @author      RedQTeam
 * @category    Admin
 * @package     Rnb\Admin
 * @version     8.0.0
 * @since       8.0.0
 */

class SaveMeta
{
    public function __construct()
    {
        add_action('save_post', array($this, 'save_postdata'), 10, 2);
    }
    public function save_postdata($post_id, $post)
    {
        if ($post->post_type != 'inventory') {
            return;
        }
        if (! isset($_POST['inventory_meta_saving'])) {
            return;
        }

        if (isset($_POST['quantity'])) {
            update_post_meta($post_id, 'quantity', $_POST['quantity']);
        }

        if (isset($_POST['pricing_type'])) {
            update_post_meta($post_id, 'pricing_type', $_POST['pricing_type']);
        }

        if (isset($_POST['distance_unit_type'])) {
            update_post_meta($post_id, 'distance_unit_type', $_POST['distance_unit_type']);
        }

        if (isset($_POST['perkilo_price'])) {
            update_post_meta($post_id, 'perkilo_price', $_POST['perkilo_price']);
        }

        //Handle hourly pricing
        if (isset($_POST['hourly_pricing_type'])) {
            update_post_meta($post_id, 'hourly_pricing_type', $_POST['hourly_pricing_type']);
        }

        if (isset($_POST['hourly_price'])) {
            update_post_meta($post_id, 'hourly_price', $_POST['hourly_price']);
        }

        $hourly_range_cost = array();
        if (isset($_POST['redq_min_hours']) && isset($_POST['redq_max_hours']) && isset($_POST['redq_hourly_range_cost'])) {
            $min_hours = $_POST['redq_min_hours'];
            $max_hours = $_POST['redq_max_hours'];
            $range_cost = $_POST['redq_hourly_range_cost'];
            $cost_applicable = $_POST['redq_hourly_range_cost_applicable'];
            for ($i = 0; $i < sizeof($min_hours); $i++) {
                $hourly_range_cost[$i]['min_hours'] = $min_hours[$i];
                $hourly_range_cost[$i]['max_hours'] = $max_hours[$i];
                $hourly_range_cost[$i]['range_cost'] = $range_cost[$i];
                $hourly_range_cost[$i]['cost_applicable'] = isset($cost_applicable[$i]) && !empty($cost_applicable[$i]) ? $cost_applicable[$i] : 'per_hour';
            }
        }
        if (isset($hourly_range_cost)) {
            update_post_meta($post_id, 'redq_hourly_ranges_cost', $hourly_range_cost);
        }

        if (isset($_POST['general_price'])) {
            update_post_meta($post_id, 'general_price', $_POST['general_price']);
        }

        $redq_daily_pricing = array();
        $redq_monthly_pricing = array();
        if (isset($_POST['friday_price'])) {
            $redq_daily_pricing['friday'] = $_POST['friday_price'];
        }

        if (isset($_POST['saturday_price'])) {
            $redq_daily_pricing['saturday'] = $_POST['saturday_price'];
        }

        if (isset($_POST['sunday_price'])) {
            $redq_daily_pricing['sunday'] = $_POST['sunday_price'];
        }

        if (isset($_POST['monday_price'])) {
            $redq_daily_pricing['monday'] = $_POST['monday_price'];
        }

        if (isset($_POST['tuesday_price'])) {
            $redq_daily_pricing['tuesday'] = $_POST['tuesday_price'];
        }

        if (isset($_POST['wednesday_price'])) {
            $redq_daily_pricing['wednesday'] = $_POST['wednesday_price'];
        }

        if (isset($_POST['thursday_price'])) {
            $redq_daily_pricing['thursday'] = $_POST['thursday_price'];
        }

        update_post_meta($post_id, 'redq_daily_pricing', $redq_daily_pricing);

        if (isset($_POST['january_price'])) {
            $redq_monthly_pricing['january'] = $_POST['january_price'];
        }

        if (isset($_POST['february_price'])) {
            $redq_monthly_pricing['february'] = $_POST['february_price'];
        }

        if (isset($_POST['march_price'])) {
            $redq_monthly_pricing['march'] = $_POST['march_price'];
        }

        if (isset($_POST['april_price'])) {
            $redq_monthly_pricing['april'] = $_POST['april_price'];
        }

        if (isset($_POST['may_price'])) {
            $redq_monthly_pricing['may'] = $_POST['may_price'];
        }

        if (isset($_POST['june_price'])) {
            $redq_monthly_pricing['june'] = $_POST['june_price'];
        }

        if (isset($_POST['july_price'])) {
            $redq_monthly_pricing['july'] = $_POST['july_price'];
        }

        if (isset($_POST['august_price'])) {
            $redq_monthly_pricing['august'] = $_POST['august_price'];
        }

        if (isset($_POST['september_price'])) {
            $redq_monthly_pricing['september'] = $_POST['september_price'];
        }

        if (isset($_POST['october_price'])) {
            $redq_monthly_pricing['october'] = $_POST['october_price'];
        }

        if (isset($_POST['november_price'])) {
            $redq_monthly_pricing['november'] = $_POST['november_price'];
        }

        if (isset($_POST['december_price'])) {
            $redq_monthly_pricing['december'] = $_POST['december_price'];
        }

        update_post_meta($post_id, 'redq_monthly_pricing', $redq_monthly_pricing);


        // Day ranges data save
        $days_range_cost = array();
        if (isset($_POST['redq_min_days']) && isset($_POST['redq_max_days']) && isset($_POST['redq_days_range_cost'])) {
            $min_days = $_POST['redq_min_days'];
            $max_days = $_POST['redq_max_days'];
            $range_cost = $_POST['redq_days_range_cost'];
            $cost_applicable = $_POST['redq_day_range_cost_applicable'];
            for ($i = 0; $i < sizeof($min_days); $i++) {
                $days_range_cost[$i]['min_days'] = $min_days[$i];
                $days_range_cost[$i]['max_days'] = $max_days[$i];
                $days_range_cost[$i]['range_cost'] = $range_cost[$i];
                $days_range_cost[$i]['cost_applicable'] = isset($cost_applicable[$i]) && !empty($cost_applicable[$i]) ? $cost_applicable[$i] : 'per_day';
            }
        }
        if (isset($days_range_cost)) {
            update_post_meta($post_id, 'redq_day_ranges_cost', $days_range_cost);
        }
    }
}

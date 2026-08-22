<?php

namespace REDQ_RnB\Traits;

use Carbon\Carbon;


trait Assets_Trait
{
    /**
     * Get front scripts
     *
     * @return array
     */
    public function get_front_scripts()
    {
        $general = 'general';
        $rfq     = 'rfq';

        return [
            'chosen.jquery' => [
                'src'     => RNB_ASSETS . '/js/chosen.jquery.js',
                'version' => filemtime(RNB_PATH . '/assets/js/chosen.jquery.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'clone' => [
                'src'     => RNB_ASSETS . '/js/clone.js',
                'version' => filemtime(RNB_PATH . '/assets/js/clone.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'jquery.steps' => [
                'src'     => RNB_ASSETS . '/js/jquery.steps.js',
                'version' => filemtime(RNB_PATH . '/assets/js/jquery.steps.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'jquery.magnific-popup.min' => [
                'src'     => RNB_ASSETS . '/js/jquery.magnific-popup.min.js',
                'version' => filemtime(RNB_PATH . '/assets/js/jquery.magnific-popup.min.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general, $rfq]
            ],
            'jquery.datetimepicker.full' => [
                'src'     => RNB_ASSETS . '/js/jquery.datetimepicker.full.js',
                'version' => filemtime(RNB_PATH . '/assets/js/jquery.datetimepicker.full.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'rnb-calendar' => [
                'src'     => RNB_ASSETS . '/js/rnb-calendar.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-calendar.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'rnb-template' => [
                'src'     => RNB_ASSETS . '/js/rnb-template.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-template.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'rnb-init' => [
                'src'     => RNB_ASSETS . '/js/rnb-init.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-init.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'rnb-quote' => [
                'src'     => RNB_ASSETS . '/js/rnb-quote.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-quote.js'),
                'deps'    => ['jquery'],
                'scope' => [$general, $rfq]
            ],
            'front-end-scripts' => [
                'src'     => RNB_ASSETS . '/js/main-script.js',
                'version' => filemtime(RNB_PATH . '/assets/js/main-script.js'),
                'deps'    => ['jquery', 'underscore', 'chosen.jquery', 'rnb-calendar', 'rnb-template', 'rnb-init'],
                'scope' => [$general]
            ],
            'rnb-validation' => [
                'src'     => RNB_ASSETS . '/js/rnb-validation.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-validation.js'),
                'deps'    => ['jquery'],
                'scope'   => [$general]
            ],
            'rnb-rfq' => [
                'src'     => RNB_ASSETS . '/js/rnb-rfq.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-rfq.js'),
                'deps'    => ['jquery', 'underscore', 'chosen.jquery', 'rnb-calendar', 'rnb-template', 'rnb-init'],
                'scope' => [$general, $rfq]
            ],
            'rnb-cancel-order' => [
                'src'     => RNB_ASSETS . '/js/rnb-cancel-order.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-cancel-order.js'),
                'deps'    => ['jquery'],
                'scope' => [$general]
            ],
        ];
    }

    /**
     * Get Styles
     *
     * @return array
     */
    public function get_front_styles()
    {
        $general = 'general';
        $rfq = 'rfq';

        return [
            'chosen' => [
                'src'     => RNB_ASSETS . '/css/chosen.css',
                'version' => filemtime(RNB_PATH . '/assets/css/chosen.css'),
                'scope' => [$general]
            ],
            'jquery.steps' => [
                'src'     => RNB_ASSETS . '/css/jquery.steps.css',
                'version' => filemtime(RNB_PATH . '/assets/css/jquery.steps.css'),
                'scope' => [$general]
            ],
            'magnific-popup' => [
                'src'     => RNB_ASSETS . '/css/magnific-popup.css',
                'version' => filemtime(RNB_PATH . '/assets/css/magnific-popup.css'),
                'scope' => [$general, $rfq]
            ],
            'fontawesome.min' => [
                'src'     => RNB_ASSETS . '/css/fontawesome.min.css',
                'version' => filemtime(RNB_PATH . '/assets/css/fontawesome.min.css'),
                'scope' => [$general]
            ],
            'jquery.datetimepicker' => [
                'src'     => RNB_ASSETS . '/css/jquery.datetimepicker.css',
                'version' => filemtime(RNB_PATH . '/assets/css/jquery.datetimepicker.css'),
                'scope' => [$general]
            ],
            'rental-global' => [
                'src'     => RNB_ASSETS . '/css/rental-global.css',
                'version' => filemtime(RNB_PATH . '/assets/css/rental-global.css'),
                'scope' => [$general]
            ],
            'quote-front' => [
                'src'     => RNB_ASSETS . '/css/quote-front.css',
                'version' => filemtime(RNB_PATH . '/assets/css/quote-front.css'),
                'scope' => [$rfq]
            ],
            'rnb-cancel-order' => [
                'src'     => RNB_ASSETS . '/css/rnb-cancel-order.css',
                'version' => filemtime(RNB_PATH . '/assets/css/rnb-cancel-order.css'),
                'scope' => [$general]
            ],
            'rental-style' => [
                'src'     => RNB_ASSETS . '/css/rental-style.css',
                'version' => filemtime(RNB_PATH . '/assets/css/rental-style.css'),
                'scope' => [$general]
            ],
        ];
    }

    /**
     * Get admin scripts
     *
     * @return array
     */
    public function get_admin_scripts()
    {
        return [
            'jquery-ui' => [
                'src'     => RNB_ASSETS . '/js/jquery-ui.js',
                'version' => filemtime(RNB_PATH . '/assets/js/jquery-ui.js'),
                'deps'    => ['jquery'],
            ],
            'select2.min' => [
                'src'     => RNB_ASSETS . '/js/select2.min.js',
                'version' => filemtime(RNB_PATH . '/assets/js/select2.min.js'),
                'deps'    => ['jquery']
            ],
            'jquery.datetimepicker.full' => [
                'src'     => RNB_ASSETS . '/js/jquery.datetimepicker.full.js',
                'version' => filemtime(RNB_PATH . '/assets/js/jquery.datetimepicker.full.js'),
                'deps'    => ['jquery']
            ],
            'icon-picker' => [
                'src'     => RNB_ASSETS . '/js/icon-picker.js',
                'version' => filemtime(RNB_PATH . '/assets/js/icon-picker.js'),
                'deps'    => ['jquery']
            ],
            'rnb-admin' => [
                'src'     => RNB_ASSETS . '/js/rnb-admin.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-admin.js'),
                'deps'    => ['jquery', 'jquery-ui-tabs', 'jquery-ui-datepicker']
            ],
            'rnb-order' => [
                'src'     => RNB_ASSETS . '/js/rnb-order.js',
                'version' => filemtime(RNB_PATH . '/assets/js/rnb-order.js'),
                'deps'    => ['jquery', 'jquery-ui-tabs', 'jquery-ui-datepicker']
            ],
            'rnb-doc' => [
                'src'     => RNB_ASSETS . '/js/doc-link.js',
                'version' => filemtime(RNB_PATH . '/assets/js/doc-link.js'),
                'deps'    => ['jquery']
            ],
            'admin-order-list' => [
                'src'     => RNB_ASSETS . '/js/admin-order-list.js',
                'version' => filemtime(RNB_PATH . '/assets/js/admin-order-list.js'),
                'deps'    => ['jquery']
            ],
        ];
    }

    /**
     * Get admin Styles
     *
     * @return array
     */
    public function get_admin_styles()
    {
        return [
            'fontawesome.min' => [
                'src'     => RNB_ASSETS . '/css/fontawesome.min.css',
                'version' => filemtime(RNB_PATH . '/assets/css/fontawesome.min.css'),
            ],
            'jquery-ui' => [
                'src'     => RNB_ASSETS . '/css/jquery-ui.css',
                'version' => filemtime(RNB_PATH . '/assets/css/jquery-ui.css'),
            ],
            'jquery.datetimepicker' => [
                'src'     => RNB_ASSETS . '/css/jquery.datetimepicker.css',
                'version' => filemtime(RNB_PATH . '/assets/css/jquery.datetimepicker.css'),
            ],
            'rnb-quote' => [
                'src'     => RNB_ASSETS . '/css/rnb-quote.css',
                'version' => filemtime(RNB_PATH . '/assets/css/rnb-quote.css'),
            ],
            'rnb-admin' => [
                'src'     => RNB_ASSETS . '/css/rnb-admin.css',
                'version' => filemtime(RNB_PATH . '/assets/css/rnb-admin.css'),
            ],
            'rnb-addon-page' => [
                'src'     => RNB_ASSETS . '/css/rnb-addon-page.css',
                'version' => filemtime(RNB_PATH . '/assets/css/rnb-addon-page.css'),
            ],
        ];
    }

    /**
     * Full calendar scripts
     */
    public function get_full_calendar_scripts()
    {
        return [
            'main.min' => [
                'src'     => RNB_ASSETS . '/js/full-calendar/main.min.js',
                'version' => filemtime(RNB_PATH . '/assets/js/full-calendar/main.min.js'),
                'deps'    => ['jquery'],
            ],
            'daygrid' => [
                'src'     => RNB_ASSETS . '/js/full-calendar/daygrid.js',
                'version' => filemtime(RNB_PATH . '/assets/js/full-calendar/daygrid.js'),
                'deps'    => ['jquery'],
            ],
            'timegrid' => [
                'src'     => RNB_ASSETS . '/js/full-calendar/timegrid.js',
                'version' => filemtime(RNB_PATH . '/assets/js/full-calendar/timegrid.js'),
                'deps'    => ['jquery'],
            ],
            'listgrid' => [
                'src'     => RNB_ASSETS . '/js/full-calendar/listgrid.js',
                'version' => filemtime(RNB_PATH . '/assets/js/full-calendar/listgrid.js'),
                'deps'    => ['jquery'],
            ],
            'jquery.magnific-popup.min' => [
                'src'     => RNB_ASSETS . '/js/jquery.magnific-popup.min.js',
                'version' => filemtime(RNB_PATH . '/assets/js/jquery.magnific-popup.min.js'),
                'deps'    => ['jquery'],
            ],
        ];
    }

    /**
     * Get full calendar Styles
     *
     * @return array
     */
    public function get_full_calendar_styles()
    {
        return [
            'main.min' => [
                'src'     => RNB_ASSETS . '/css/full-calendar/main.min.css',
                'version' => filemtime(RNB_PATH . '/assets/css/full-calendar/main.min.css'),
            ],
            'daygrid' => [
                'src'     => RNB_ASSETS . '/css/full-calendar/daygrid.css',
                'version' => filemtime(RNB_PATH . '/assets/css/full-calendar/daygrid.css'),
            ],
            'timegrid' => [
                'src'     => RNB_ASSETS . '/css/full-calendar/timegrid.css',
                'version' => filemtime(RNB_PATH . '/assets/css/full-calendar/timegrid.css'),
            ],
            'magnific-popup' => [
                'src'     => RNB_ASSETS . '/css/magnific-popup.css',
                'version' => filemtime(RNB_PATH . '/assets/css/magnific-popup.css'),
            ],
        ];
    }

    public function get_doc_details()
    {
        $url = 'https://rnb-doc.vercel.app';
        $docs = [
            [
                'name' =>  'Inventories',
                'link' =>  $url . '/inventory/quantity',
            ],
            [
                'name' =>  'Pickup Location',
                'link' => $url . '/booking-form/pickup-location',
            ],
            [
                'name' =>  'Dropoff Location',
                'link' => $url . '/booking-form/return-location',
            ],
            [
                'name' =>  'Resources',
                'link' =>  $url . '/booking-form/resources',
            ],
            [
                'name' =>  'RnB Categories',
                'link' =>  $url . '/booking-form/rnb-categories',
            ],
            [
                'name' =>  'Person',
                'link' => $url . '/booking-form/person',
            ],
            [
                'name' =>  'Deposit',
                'link' => $url . '/booking-form/deposit',
            ],
            [
                'name' =>  'Attributes',
                'link' => $url . '/booking-form/attributes',
            ],
            [
                'name' =>  'Features',
                'link' => $url . '/booking-form/features',
            ]
        ];

        return apply_filters('rnb_doc_details', $docs);
    }
}

<?php

namespace REDQ_RnB;

class ColorControl
{
    public function __construct()
    {
        add_action('wp_head', array($this, 'rnb_base_colors_css_wrap'));
    }

    public function rnb_base_colors_css_wrap()
    {
        $rnb_color_value = redq_rental_global_color_control();
        if ($rnb_color_value !== '#b07aa4') {
            list($sr, $sg, $sb) = sscanf($rnb_color_value, "#%02x%02x%02x");

            echo '<style>
      .custom-block input[type="checkbox"]:checked + span:after {
        background: ' . $rnb_color_value . ';
        border-color: ' . $rnb_color_value . ';
      }

      .xdsoft_datetimepicker
      .xdsoft_timepicker
      .xdsoft_time_box
      > div
      > div.xdsoft_current {
        background: ' . $rnb_color_value . ' !important;
      }      
      
      #quote-content-confirm {
        color: #fff;
        background-color: ' . $rnb_color_value . ' !important;
        height: 48px;
        font-size: 14px;
        margin: 0;
        font-weight: 600;
        font-family: Lato, sans-serif;
      }

      #animatedModal.rnb-animated-modal .modal-content-body .modal-header {
        padding: 20px 25px;
        background-color: ' . $rnb_color_value . ' ;
        border-radius: 3px 3px 0 0;
      }      

      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      .steps
      ul
      li.current
      a,
      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      .steps
      ul
      li.current:hover
      a {
        font-weight: 600;
        color: #fff !important;
        background-color: ' . $rnb_color_value . ' !important;
      }

      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      .steps
      ul
      li:hover
      a {
        color: ' . $rnb_color_value . ' !important;
      }

      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      > .actions
      > ul
      > li
      > a,
      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      > .actions
      > ul
      > li
      > button {
        border: 1px solid ' . $rnb_color_value . ';
      }      

      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      > .actions
      > ul
      > li
      > a:focus,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li
        > button:focus,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li
        > a:visited,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li
        > button:visited,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li
        > button:hover,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li
        > a:hover {
          background-color: ' . $rnb_color_value . ';
      }

      #animatedModal.rnb-animated-modal
      .rnb-step-content-wrapper
      header.section-title {
        background-color: rgba(' . $sr . ', ' . $sg . ', ' . $sb . ', 0.25);
        border-left: 4px solid ' . $rnb_color_value . ';
      }

      #cal-submit-btn {
        background: ' . $rnb_color_value . ';
      }
      #drop-cal-submit-btn {
        background: ' . $rnb_color_value . ';
      }

      #animatedModal.rnb-animated-modal
      .modal-content-body
      .modal-content
      #rnbSmartwizard
      > .actions
      > ul
      > li.disabled
      > a,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li.disabled
        > a:hover,
      #animatedModal.rnb-animated-modal
        .modal-content-body
        .modal-content
        #rnbSmartwizard
        > .actions
        > ul
        > li.disabled
        > a:focus {
        background-color: rgba(' . $sr . ', ' . $sg . ', ' . $sb . ', 0.3);
        border: 1px solid rgba(' . $sr . ', ' . $sg . ', ' . $sb . ', 0.22);
      }

      #animatedModal.rnb-animated-modal .rnb-control.rnb-control-checkbox.checked,
      #animatedModal.rnb-animated-modal .rnb-control.rnb-control-radio.checked {
        background-color: rgba(' . $sr . ', ' . $sg . ', ' . $sb . ', 0.61);
        border: 1px solid rgba(' . $sr . ', ' . $sg . ', ' . $sb . ', 1);
      }


      </style>';
        }
    }
}

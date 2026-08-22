<?php

namespace REDQ_RnB\Integration;

class BeThemeSupport{
    /**
     * Init class
     */
    public function __construct()
    {
        $beTheme = wp_get_theme();
        if ( 'betheme' != $beTheme->template ) return;

        add_action('wp_head', [$this, 'betheme_styles']);
    }

    public function betheme_styles()
    {
        ?>
            <style>
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart {
                    display: block !important;
                }
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart input[type=number]{
                    font-size: 14px;
                    line-height: 10px;
                    padding: 10px !important;
                }
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart input[type=number]::-webkit-outer-spin-button,
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart input[type=number]::-webkit-inner-spin-button {
                    -webkit-appearance: inner-spin-button;
                    appearance: inner-spin-button;
                }
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart h5,
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart input,
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart input::placeholder,
                body.theme-betheme.woocommerce.single-product form.cart.rnb-cart .chosen-container-single .chosen-default {
                    color: var(--mfn-woo-body-color) !important;
                }
            </style>
        <?php
    }
}
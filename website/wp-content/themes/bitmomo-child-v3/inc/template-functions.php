<?php
/** Template rendering helpers. @package Bitmomo */

if (!defined('ABSPATH')) exit;

if (!function_exists('bitmomo_render_brand')) {
    function bitmomo_render_brand() {
        echo '<div class="bm-brand">';
        if (function_exists('the_custom_logo') && has_custom_logo()) {
            the_custom_logo();
        } else {
            printf(
                '<a class="bm-brand-fallback" href="%s" aria-label="%s"><span class="bm-logo-dot" aria-hidden="true"></span><span class="bm-brand-text">BITMOMO</span></a>',
                esc_url(home_url('/')),
                esc_attr__('Home', 'bitmomo')
            );
        }
        echo '</div>';
    }
}

if (!function_exists('bitmomo_render_menu_toggle')) {
    function bitmomo_render_menu_toggle() {
        printf(
            '<button class="bm-hamburger" id="bm-hamburger" type="button" aria-label="%s" aria-controls="bm-nav" aria-expanded="false"><span></span><span></span><span></span></button>',
            esc_attr__('Menu', 'bitmomo')
        );
    }
}

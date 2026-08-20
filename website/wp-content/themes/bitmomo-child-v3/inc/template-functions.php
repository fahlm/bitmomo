<?php
/** Template rendering helpers. @package Bitmomo */

if (!defined('ABSPATH')) exit;

if (!function_exists('bitmomo_render_brand')) {
    function bitmomo_render_brand() {
        $logo_url = get_stylesheet_directory_uri() . '/assets/images/bitmomo-logo.png';

        printf(
            '<div class="bm-brand"><a class="bm-brand-logo" href="%s" aria-label="%s"><img src="%s" width="1254" height="1254" alt="%s" decoding="async"></a></div>',
            esc_url(home_url('/')),
            esc_attr__('Bitmomo home', 'bitmomo'),
            esc_url($logo_url),
            esc_attr__('Bitmomo', 'bitmomo')
        );
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

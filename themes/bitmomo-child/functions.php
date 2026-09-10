<?php
/**
 * Enqueue styles for the Bitmomo child theme.
 *
 * We load the parent theme’s stylesheet first, then our custom
 * styles defined in custom.css.  This ensures the child styles
 * override the parent where necessary without permanently altering
 * the parent theme files.  Additional scripts could also be
 * enqueued here if needed.
 */
function bitmomo_child_enqueue_styles() {
    // Parent style
    wp_enqueue_style( 'hello-elementor-style', get_template_directory_uri() . '/style.css' );
    // Custom child style
    wp_enqueue_style( 'bitmomo-child-style', get_stylesheet_directory_uri() . '/custom.css', array( 'hello-elementor-style' ), wp_get_theme()->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'bitmomo_child_enqueue_styles' );

/**
 * Set up theme defaults and register support for various WordPress features.
 */
function bitmomo_child_setup() {
    // Let WordPress manage the document title.
    add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'bitmomo_child_setup' );
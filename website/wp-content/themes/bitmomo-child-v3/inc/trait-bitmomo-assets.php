<?php
/** Bitmomo Assets Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Assets_Trait {
    public function theme_setup() {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('responsive-embeds');
        add_theme_support('editor-styles');

        register_nav_menus([
            'primary' => __('Primary Menu', 'bitmomo'),
            'footer'  => __('Footer Menu', 'bitmomo')
        ]);

        add_image_size('bm-card', BM_CARD_IMAGE_WIDTH, BM_CARD_IMAGE_HEIGHT, true);
        add_theme_support('custom-logo', [
            'height'      => 40,
            'width'       => 160,
            'flex-height' => true,
            'flex-width'  => true
        ]);
        $GLOBALS['content_width'] = BM_CONTENT_WIDTH;
    }

    /**
     * Canonical CSS dependency graph.
     *
     * Parent theme is compatibility only. foundation.css owns tokens/reset;
     * navigation-footer.css owns global chrome; public-surfaces.css owns generic
     * WordPress surfaces; route-specific files own homepage/research layouts.
     * The historical 50KB custom.css is intentionally no longer in the public
     * cascade: new fixes must land in the owning layer instead of overriding it.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'hello-elementor-style',
            get_template_directory_uri() . '/style.css',
            [],
            null
        );

        $this->enqueue_theme_style(
            'bitmomo-foundation',
            'assets/css/foundation.css',
            ['hello-elementor-style']
        );
        $this->enqueue_theme_style(
            'bitmomo-navigation-footer',
            'assets/css/navigation-footer.css',
            ['bitmomo-foundation']
        );
        $this->enqueue_theme_style(
            'bitmomo-public-surfaces',
            'assets/css/public-surfaces.css',
            ['bitmomo-foundation', 'bitmomo-navigation-footer']
        );

        if (is_front_page()) {
            $this->enqueue_theme_style(
                'bitmomo-home',
                'assets/css/home.css',
                ['bitmomo-public-surfaces']
            );
        }

        if (is_single() || is_category('riset')) {
            $this->enqueue_theme_style(
                'bitmomo-research',
                'assets/css/research.css',
                ['bitmomo-public-surfaces']
            );
        }

        $frontend_js_path = get_stylesheet_directory() . '/assets/js/bitmomo-frontend.js';
        if (file_exists($frontend_js_path)) {
            wp_enqueue_script(
                'bitmomo-frontend',
                get_stylesheet_directory_uri() . '/assets/js/bitmomo-frontend.js',
                [],
                $this->get_file_version($frontend_js_path),
                true
            );
            wp_localize_script('bitmomo-frontend', 'bitmomoConfig', [
                'ajaxUrl'  => admin_url('admin-ajax.php'),
                'ctaNonce' => wp_create_nonce('bitmomo_cta_click'),
            ]);
        }
    }

    private function enqueue_theme_style($handle, $relative_path, $dependencies = []) {
        $path = get_stylesheet_directory() . '/' . ltrim($relative_path, '/');
        if (!file_exists($path)) return;

        wp_enqueue_style(
            $handle,
            get_stylesheet_directory_uri() . '/' . ltrim($relative_path, '/'),
            $dependencies,
            $this->get_file_version($path)
        );
    }

    public function remove_bloat() {
        $acts = [
            ['wp_head','print_emoji_detection_script',7],
            ['wp_print_styles','print_emoji_styles'],
            ['admin_print_scripts','print_emoji_detection_script'],
            ['admin_print_styles','print_emoji_styles'],
            ['wp_head','wp_oembed_add_discovery_links'],
            ['wp_head','rest_output_link_wp_head'],
            ['wp_head','wp_generator'],
            ['wp_head','rsd_link'],
            ['wp_head','wlwmanifest_link'],
        ];
        foreach ($acts as $a) {
            if (count($a)===3) remove_action($a[0],$a[1],$a[2]); else remove_action($a[0],$a[1]);
        }
    }

    public function optimize_assets() {
        if (is_admin()) return;

        wp_dequeue_style('hello-elementor-fonts');
        wp_dequeue_style('classic-theme-styles');
        add_filter('elementor/frontend/print_google_fonts','__return_false',99);
    }

    public function filter_loader_src($src) {
        if (!$src) return $src;
        if (str_contains($src, 'fonts.googleapis.com')) {
            $src = add_query_arg('display','swap',$src);
        }
        return $src;
    }

    /** No speculative external preconnects: every connection must earn its cost. */
    public function add_resource_hints($urls,$rel) {
        return $urls;
    }
}

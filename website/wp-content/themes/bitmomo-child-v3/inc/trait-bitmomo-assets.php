<?php
/** Bitmomo Assets Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Assets_Trait {
    /* ---------- Setup ---------- */
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
     * Canonical public CSS boundary.
     *
     * Public Bitmomo routes no longer inherit Hello Elementor or the frozen
     * 50KB custom.css layer. Those files remain in source only as migration
     * evidence for non-frontend/admin contexts.
     */
    private function uses_canonical_public_css() {
        return !is_admin() && !is_feed() && !is_embed();
    }

    public function enqueue_styles() {
        $canonical_public = $this->uses_canonical_public_css();
        $public_surface_deps = array();

        if ($canonical_public) {
            $foundation_path = get_stylesheet_directory() . '/assets/css/public-foundation.bundle.css';
            if (file_exists($foundation_path)) {
                wp_enqueue_style(
                    'bitmomo-public-foundation',
                    get_stylesheet_directory_uri() . '/assets/css/public-foundation.bundle.css',
                    array(),
                    $this->get_file_version($foundation_path)
                );
                $public_surface_deps = array('bitmomo-public-foundation');
            }
        } else {
            wp_enqueue_style(
                'hello-elementor-style',
                get_template_directory_uri() . '/style.css',
                array(),
                null
            );

            $custom_css_path = get_stylesheet_directory() . '/custom.css';
            wp_enqueue_style(
                'bitmomo-child',
                get_stylesheet_directory_uri() . '/custom.css',
                array('hello-elementor-style'),
                $this->get_file_version($custom_css_path)
            );

            $design_system_path = get_stylesheet_directory() . '/assets/css/design-system.css';
            $public_base_deps = array('bitmomo-child');
            if (file_exists($design_system_path)) {
                wp_enqueue_style(
                    'bitmomo-design-system',
                    get_stylesheet_directory_uri() . '/assets/css/design-system.css',
                    array('bitmomo-child'),
                    $this->get_file_version($design_system_path)
                );
                $public_base_deps = array('bitmomo-design-system');
            }

            $readability_css_path = get_stylesheet_directory() . '/assets/css/public-readability.css';
            if (file_exists($readability_css_path)) {
                wp_enqueue_style(
                    'bitmomo-public-readability',
                    get_stylesheet_directory_uri() . '/assets/css/public-readability.css',
                    $public_base_deps,
                    $this->get_file_version($readability_css_path)
                );
                $public_base_deps = array('bitmomo-public-readability');
            }

            $public_surface_deps = $public_base_deps;
            $public_surfaces_css_path = get_stylesheet_directory() . '/assets/css/public-surfaces.css';
            if (file_exists($public_surfaces_css_path)) {
                wp_enqueue_style(
                    'bitmomo-public-surfaces',
                    get_stylesheet_directory_uri() . '/assets/css/public-surfaces.css',
                    $public_base_deps,
                    $this->get_file_version($public_surfaces_css_path)
                );
                $public_surface_deps = array('bitmomo-public-surfaces');
            }

            $navigation_footer_css_path = get_stylesheet_directory() . '/assets/css/navigation-footer.css';
            if (file_exists($navigation_footer_css_path)) {
                wp_enqueue_style(
                    'bitmomo-navigation-footer',
                    get_stylesheet_directory_uri() . '/assets/css/navigation-footer.css',
                    $public_surface_deps,
                    $this->get_file_version($navigation_footer_css_path)
                );
                $public_surface_deps = array('bitmomo-navigation-footer');
            }
        }

        if (is_single()) {
            $article_css_path = get_stylesheet_directory() . '/assets/css/article-reading.css';
            if (file_exists($article_css_path)) {
                wp_enqueue_style(
                    'bitmomo-article-reading',
                    get_stylesheet_directory_uri() . '/assets/css/article-reading.css',
                    $public_surface_deps,
                    $this->get_file_version($article_css_path)
                );
            }
        }

        if (is_front_page()) {
            $home_bundle_path = get_stylesheet_directory() . '/assets/css/home.bundle.css';
            if (file_exists($home_bundle_path)) {
                wp_enqueue_style(
                    'bitmomo-home',
                    get_stylesheet_directory_uri() . '/assets/css/home.bundle.css',
                    $public_surface_deps,
                    $this->get_file_version($home_bundle_path)
                );
            }
        }

        if (is_category('riset')) {
            $research_css_path = get_stylesheet_directory() . '/assets/css/research.css';
            if (file_exists($research_css_path)) {
                wp_enqueue_style(
                    'bitmomo-research',
                    get_stylesheet_directory_uri() . '/assets/css/research.css',
                    $public_surface_deps,
                    $this->get_file_version($research_css_path)
                );
            }
        }

        if (is_page('tentang-kami')) {
            $about_css_path = get_stylesheet_directory() . '/assets/css/about.css';
            if (file_exists($about_css_path)) {
                wp_enqueue_style(
                    'bitmomo-about',
                    get_stylesheet_directory_uri() . '/assets/css/about.css',
                    $public_surface_deps,
                    $this->get_file_version($about_css_path)
                );
            }
        }

        $frontend_js_path = get_stylesheet_directory() . '/assets/js/bitmomo-frontend.js';
        wp_enqueue_script(
            'bitmomo-frontend',
            get_stylesheet_directory_uri() . '/assets/js/bitmomo-frontend.js',
            array(),
            $this->get_file_version($frontend_js_path),
            true
        );
        wp_localize_script('bitmomo-frontend', 'bitmomoConfig', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'ctaNonce' => wp_create_nonce('bitmomo_cta_click'),
        ));
    }

    /* ---------- Bloat / Assets ---------- */
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

        if ($this->uses_canonical_public_css()) {
            foreach (array(
                'hello-elementor',
                'hello-elementor-style',
                'hello-elementor-theme-style',
                'hello-elementor-header-footer',
                'global-styles',
                'wp-block-library',
                'wp-block-library-theme',
            ) as $handle) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }

            global $wp_styles;
            if (isset($wp_styles) && is_object($wp_styles) && isset($wp_styles->queue)) {
                foreach ((array) $wp_styles->queue as $handle) {
                    if (0 === strpos((string) $handle, 'elementor-')) {
                        wp_dequeue_style($handle);
                    }
                }
            }
        }

        add_filter('elementor/frontend/print_google_fonts','__return_false',99);
    }

    public function filter_loader_src($src) {
        if (!$src) return $src;
        if (str_contains($src, 'fonts.googleapis.com')) {
            $src = add_query_arg('display','swap',$src);
        }
        return $src;
    }

    public function add_resource_hints($urls,$rel) {
        // No speculative third-party preconnects. Add one only when a public
        // route actually owns a stable render-critical dependency on it.
        return $urls;
    }
}

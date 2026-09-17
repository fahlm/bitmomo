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

    public function enqueue_styles() {
        wp_enqueue_style(
            'hello-elementor-style',
            get_template_directory_uri() . '/style.css',
            [],
            null
        );

        // Frozen legacy layer. It stays first until migrated away, and must not
        // receive new public-surface ownership.
        $custom_css_path = get_stylesheet_directory() . '/custom.css';
        wp_enqueue_style(
            'bitmomo-child',
            get_stylesheet_directory_uri() . '/custom.css',
            ['hello-elementor-style'],
            $this->get_file_version($custom_css_path)
        );

        // Canonical cross-surface foundation: tokens, keyboard focus, touch
        // targets, reduced motion and form defaults. Every modern public layer
        // depends on this rather than adding more rules to custom.css.
        $design_system_path = get_stylesheet_directory() . '/assets/css/design-system.css';
        $public_base_deps = ['bitmomo-child'];
        if (file_exists($design_system_path)) {
            wp_enqueue_style(
                'bitmomo-design-system',
                get_stylesheet_directory_uri() . '/assets/css/design-system.css',
                ['bitmomo-child'],
                $this->get_file_version($design_system_path)
            );
            $public_base_deps = ['bitmomo-design-system'];
        }

        $readability_css_path = get_stylesheet_directory() . '/assets/css/public-readability.css';
        if (file_exists($readability_css_path)) {
            wp_enqueue_style(
                'bitmomo-public-readability',
                get_stylesheet_directory_uri() . '/assets/css/public-readability.css',
                $public_base_deps,
                $this->get_file_version($readability_css_path)
            );
            $public_base_deps = ['bitmomo-public-readability'];
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
            $public_surface_deps = ['bitmomo-public-surfaces'];
        }

        $navigation_footer_css_path = get_stylesheet_directory() . '/assets/css/navigation-footer.css';
        if (file_exists($navigation_footer_css_path)) {
            wp_enqueue_style(
                'bitmomo-navigation-footer',
                get_stylesheet_directory_uri() . '/assets/css/navigation-footer.css',
                $public_surface_deps,
                $this->get_file_version($navigation_footer_css_path)
            );
            $public_surface_deps = ['bitmomo-navigation-footer'];
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
            // One explicit homepage owner. The old Opportunity stylesheet is no
            // longer loaded because the canonical front page no longer renders
            // that standalone component.
            $home_css_path = get_stylesheet_directory() . '/assets/css/home.css';
            if (file_exists($home_css_path)) {
                wp_enqueue_style(
                    'bitmomo-home',
                    get_stylesheet_directory_uri() . '/assets/css/home.css',
                    $public_surface_deps,
                    $this->get_file_version($home_css_path)
                );
                $public_surface_deps = ['bitmomo-home'];
            }

            // Compact trust strip has its own bounded owner so homepage.css does
            // not accumulate provider/infrastructure-specific presentation.
            $home_infrastructure_css_path = get_stylesheet_directory() . '/assets/css/home-infrastructure.css';
            if (file_exists($home_infrastructure_css_path)) {
                wp_enqueue_style(
                    'bitmomo-home-infrastructure',
                    get_stylesheet_directory_uri() . '/assets/css/home-infrastructure.css',
                    $public_surface_deps,
                    $this->get_file_version($home_infrastructure_css_path)
                );
                $public_surface_deps = ['bitmomo-home-infrastructure'];
            }

            $conversion_css_path = get_stylesheet_directory() . '/assets/css/home-conversion.css';
            if (file_exists($conversion_css_path)) {
                wp_enqueue_style(
                    'bitmomo-home-conversion',
                    get_stylesheet_directory_uri() . '/assets/css/home-conversion.css',
                    $public_surface_deps,
                    $this->get_file_version($conversion_css_path)
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
            [],
            $this->get_file_version($frontend_js_path),
            true
        );
        wp_localize_script('bitmomo-frontend', 'bitmomoConfig', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'ctaNonce' => wp_create_nonce('bitmomo_cta_click'),
        ]);
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

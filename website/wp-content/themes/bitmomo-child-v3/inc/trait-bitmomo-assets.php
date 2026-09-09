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
        // Parent (Hello Elementor) style
        wp_enqueue_style('hello-elementor-style',
            get_template_directory_uri() . '/style.css', [], null);

        // Critical CSS inline (optional)
        $critical_path = get_stylesheet_directory() . '/critical.css';
        if (file_exists($critical_path)) {
            printf("<style>%s</style>\n", @file_get_contents($critical_path));
        }

        // Child custom.css (cache-busting via mtime)
        $custom_css_path = get_stylesheet_directory() . '/custom.css';
        $custom_css_uri  = get_stylesheet_directory_uri() . '/custom.css';
        $version = $this->get_file_version($custom_css_path);
        wp_enqueue_style('bitmomo-child', $custom_css_uri, ['hello-elementor-style'], $version);

        // Typeface. The site previously loaded none: the stack listed Inter
        // AFTER system-ui, so it never applied and every visitor saw their own
        // OS default. Archivo carries the full 400-800 range the design
        // already uses, so no existing element changes weight.
        //
        // Loading this also makes the fonts.gstatic.com preconnect in
        // add_resource_hints() purposeful — until now it opened a connection
        // to a host the site never used. Self-hosting the woff2 in the theme
        // would be better still (no third-party request, nothing leaked to a
        // CDN) and is the intended follow-up; this gets the face live now.
        wp_enqueue_style(
            'bitmomo-fonts',
            'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap',
            [],
            null
        );

        // Typography and control-surface layer. Deliberately a separate file
        // from custom.css: custom.css on staging is far ahead of every branch
        // here, so it must not be rewritten from source. This one is purely
        // additive and loads last, so removing it restores the previous look
        // exactly.
        $typography_path = get_stylesheet_directory() . '/assets/css/bitmomo-typography.css';
        if (file_exists($typography_path)) {
            wp_enqueue_style(
                'bitmomo-typography',
                get_stylesheet_directory_uri() . '/assets/css/bitmomo-typography.css',
                ['bitmomo-child', 'bitmomo-fonts'],
                $this->get_file_version($typography_path)
            );
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
        if ($rel==='preconnect') {
            $urls[] = ['href'=>'https://fonts.gstatic.com','crossorigin'=>true];
            $urls[] = ['href'=>'https://cdn.bitmomo.id','crossorigin'=>true];
        }
        return $urls;
    }
}

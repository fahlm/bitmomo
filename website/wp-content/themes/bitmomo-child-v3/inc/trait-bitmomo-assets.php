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

        // Opportunity V1 launch hierarchy. Kept additive so the frozen legacy
        // stylesheet remains untouched until the UI is accepted in staging.
        if (is_front_page()) {
            $opportunity_css = <<<'CSS'
.bm-hero-opportunity{margin:0 0 22px;padding:20px 22px;border:1px solid rgba(38,208,198,.22);border-radius:14px;background:rgba(7,20,33,.72)}
.bm-hero-opportunity-main{display:flex;align-items:baseline;justify-content:space-between;gap:16px}
.bm-hero-opportunity-main span,.bm-direction-context-label,.bm-btc-opportunity-meta,.bm-btc-opportunity-tag,.bm-btc-reference{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.12em}
.bm-hero-opportunity-main span{color:#71839f;font-size:10px;font-weight:800}
.bm-hero-opportunity-main strong{font-size:clamp(28px,3vw,40px);line-height:1;color:var(--teal)}
.bm-hero-opportunity.is-normal .bm-hero-opportunity-main strong{color:#9db2cf}
.bm-hero-opportunity.is-low .bm-hero-opportunity-main strong,.bm-hero-opportunity.is-unavailable .bm-hero-opportunity-main strong{color:#71839f}
.bm-hero-opportunity p{margin:12px 0 0;color:#aebdd2;font-size:13px;line-height:1.55}
.bm-hero-opportunity small{display:block;margin-top:12px;color:#6f839e;font:800 9px/1.4 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.12em}
.bm-direction-head--current{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;padding:0 0 18px;border-bottom:1px solid var(--stroke)}
.bm-direction-head--current .bm-direction-metric{align-items:flex-start;text-align:left}
.bm-direction-head--current strong{margin-top:2px;color:var(--ink);font-size:12px;text-align:left}
.bm-direction-context-label{margin:18px 0 0;color:#71839f;font-size:9px;font-weight:800}
.bm-direction-card .bm-direction-chart{margin-top:12px}
.bm-direction-card .bm-direction-meta{grid-template-columns:minmax(100px,.7fr) minmax(0,1.7fr) minmax(100px,.7fr)}
.bm-btc-opportunity{padding:clamp(26px,3.4vw,44px);border-bottom:1px solid var(--stroke);background:linear-gradient(110deg,rgba(38,208,198,.09),rgba(12,28,42,.12))}
.bm-btc-opportunity.is-normal{background:linear-gradient(110deg,rgba(121,150,190,.08),rgba(12,28,42,.12))}
.bm-btc-opportunity.is-low,.bm-btc-opportunity.is-unavailable{background:rgba(8,20,33,.5)}
.bm-btc-opportunity-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}
.bm-btc-opportunity-head>div{min-width:0}
.bm-btc-opportunity .bm-btc-kicker{margin-bottom:10px;color:var(--teal)}
.bm-btc-opportunity strong{display:block;color:var(--teal);font-size:clamp(38px,5vw,58px);line-height:1}
.bm-btc-opportunity.is-normal strong{color:#aebdd2}
.bm-btc-opportunity.is-low strong,.bm-btc-opportunity.is-unavailable strong{color:#71839f}
.bm-btc-opportunity>p{max-width:720px;margin:18px 0 0;color:#b9c7d7;font-size:16px;line-height:1.6}
.bm-btc-opportunity-tag{flex:0 0 auto;padding:7px 10px;border:1px solid var(--stroke);border-radius:999px;color:#7f92ad;font-size:9px;font-weight:800;text-transform:uppercase}
.bm-btc-opportunity-meta{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-top:20px;color:#667b98;font-size:9px;font-weight:800}
.bm-btc-opportunity-meta time{letter-spacing:.06em}
.bm-btc-bias strong.is-bullish{color:#46dda5}.bm-btc-bias strong.is-bearish{color:#ff8080}.bm-btc-bias strong.is-neutral{color:#aebdd2}
.bm-btc-confidence-segments{grid-template-columns:repeat(3,minmax(18px,1fr))}
.bm-btc-reference{color:#8fa2bd;font-size:10px;font-weight:800;white-space:nowrap}
@media(max-width:760px){
 .bm-direction-head--current{grid-template-columns:1fr 1fr}.bm-direction-head--current .bm-direction-metric--state{grid-column:1/-1}
 .bm-hero-opportunity{padding:18px}.bm-hero-opportunity-main{align-items:flex-start;flex-direction:column;gap:8px}
 .bm-btc-opportunity{padding:25px 22px}.bm-btc-opportunity-head{flex-direction:column;gap:14px}.bm-btc-opportunity-tag{align-self:flex-start}
 .bm-btc-opportunity>p{font-size:15px}.bm-btc-opportunity-meta{align-items:flex-start;flex-direction:column;gap:8px}
 .bm-btc-reference{white-space:normal}
}
CSS;
            wp_add_inline_style('bitmomo-child', $opportunity_css);
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
<?php
/**
 * Bitmomo Child Theme â€” ULTRA v4.2
 * - Solid LCP/preload + loading policy
 * - Universal SUBSCRIBE trigger + modal MailPoet
 * - Safe assets optimization (tanpa merusak Gutenberg/Elementor)
 * - Optional critical.css inline
 * - HAMBURGER MENU INJECTION (NEW)
 */

if (!defined('ABSPATH')) exit;

define('BM_VERSION', '4.2');
define('BM_MAILPOET_FORM_ID', 2);
define('BM_ARCHIVE_POSTS_PER_PAGE', 18);
define('BM_CARD_IMAGE_WIDTH', 800);
define('BM_CARD_IMAGE_HEIGHT', 450);
define('BM_CONTENT_WIDTH', 1120);
define('BM_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

class Bitmomo_Performance_Optimizer {

    private static $instance = null;
    private $first_card_post_id = 0;
    private $performance_timer = 0;
    private $did_preload_first_card = false;
    private $did_preload_featured  = false;
    private $did_preload_hero      = false;

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->performance_timer = microtime(true);
        $this->init_hooks();
    }

    private function init_hooks() {
        // Core
        add_action('after_setup_theme',   [$this, 'theme_setup'], 5);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_styles'], 20);

        // Bloat/Assets
        add_action('init',                   [$this, 'remove_bloat'], 1);
        add_action('wp_enqueue_scripts',     [$this, 'optimize_assets'], 100);
        add_action('wp_default_scripts',     [$this, 'optimize_jquery']);

        // Images
        add_filter('wp_img_tag_add_decoding_attr',          [$this, 'set_image_decoding']);
        add_filter('wp_get_attachment_image_attributes',    [$this, 'force_image_dimensions'], 10, 3);
        add_filter('the_content',                           [$this, 'optimize_content_images'], 8);
        add_filter('the_content',                           [$this, 'set_lcp_image_priority'], 9);
        add_filter('wp_calculate_image_sizes',              [$this, 'optimize_image_sizes'], 10, 5);

        // Advanced assets
        add_filter('script_loader_tag', [$this, 'defer_javascript'], 10, 3);
        add_filter('style_loader_src',  [$this, 'filter_loader_src']);
        add_filter('script_loader_src', [$this, 'filter_loader_src']);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);

        // Content/nav
        add_action('wp',                    [$this, 'detect_first_card_post']);
        add_action('wp_head',               [$this, 'preload_critical_assets'], 5);
        add_filter('post_thumbnail_html',   [$this, 'optimize_thumbnail_loading'], 10, 5);
        add_filter('the_content',           [$this, 'add_content_enhancements'], 15);
        add_action('pre_get_posts',         [$this, 'modify_archive_query']);
        add_action('template_redirect',     [$this, 'handle_subscribe_redirect']);

        // Universal subscribe triggers + fallback CSS
        add_filter('nav_menu_link_attributes', [$this, 'force_subscribe_link_attrs'], 10, 3);
        add_filter('the_content',               [$this, 'force_subscribe_links_in_content'], 11);
        add_action('wp_head',                   [$this, 'inline_img_fallback_css'], 1);

        // Modal/footer + debug
        add_action('wp_footer', [$this, 'render_mailpoet_modal'], 100);
        add_action('wp_footer', [$this, 'performance_debug'], 999);
        
        // === HAMBURGER MENU INJECTION (NEW) ===
        add_action('wp_footer', [$this, 'inject_hamburger_menu'], 98);
    }

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

    public function defer_javascript($tag,$handle,$src) {
        if (is_admin() || empty($src)) return $tag;

        $skip = [
            'jquery','jquery-core','jquery-migrate',
            'elementor-frontend','elementor-webpack-runtime','elementor-sticky','elementor-waypoints',
            'imagesloaded','swiper',
            'mailpoet-form','mailpoet-public','litespeed-cache',
            'wp-polyfill','wp-i18n'
        ];
        if (in_array($handle, $skip, true)) return $tag;

        if (str_contains($tag,'defer') || str_contains($tag,'async')) return $tag;

        return sprintf("<script src=\"%s\" defer></script>\n", esc_url($src));
    }

    public function filter_loader_src($src) {
        if (!$src) return $src;
        $src = remove_query_arg('ver',$src);
        if (str_contains($src, 'fonts.googleapis.com')) {
            $src = add_query_arg('display','swap',$src);
        }
        return $src;
    }

    public function optimize_jquery($scripts) {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $deps = $scripts->registered['jquery']->deps;
            $scripts->registered['jquery']->deps = array_diff($deps, ['jquery-migrate']);
        }
    }

    public function add_resource_hints($urls,$rel) {
        if ($rel==='preconnect') {
            $urls[] = ['href'=>'https://fonts.gstatic.c²È="25Ì¬¡mxùt¨ýq‰¡É•˜õl‰ptü¡mx‰pœùqÌt¨ü½ÍÕ‰ÍÉ¥‰”¼ýðÍÕ‰ÍÉ¥‰”¥l‰ptýmxùt¨¤ùù¤œ°(€€€€€€€€€€€™Õ¹Ñ¥½¸ ‘´¥ì(€€€€€€€€€€€€€€€€‘Ñ…œ€ô€‘µlÁtì(€€€€€€€€€€€€€€€€‘Ñ…œ€ôÁÉ•}É•Á±…” ù¡É•˜õl‰ptýmx‰pœùqÌt¨ü½ÍÕ‰ÍÉ¥‰”¼ýl‰ptýù¤œ°€¡É•˜ôˆÍÕ‰ÍÉ¥‰”ˆœ°€‘Ñ…œ¤ì(€€€€€€€€€€€€€€€¥˜€¡ÍÑÉ¥Á½Ì ‘Ñ…œ°¡É•˜ôœ¤ôôõ™…±Í”¤€‘Ñ…œ€ôÍÑÉ}¥É•Á±…” œñ„€œ°œñ„¡É•˜ôˆÍÕ‰ÍÉ¥‰”ˆ€œ°‘Ñ…œ¤ì(€€€€€€€€€€€€€€€¥˜€¡ÍÑÉ¥Á½Ì ‘Ñ…œ°±…ÍÌôœ¤„ôõ™…±Í”¤ì(€€€€€€€€€€€€€€€€€€€€‘Ñ…œ€ôÁÉ•}É•Á±…” ù±…ÍÌõl‰pt¡mx‰pt¨¥l‰puù¤œ°±…ÍÌôˆÄ©Ìµ½Á•¸µÍÕ‰ÍÉ¥‰”ˆœ°‘Ñ…œ¤ì(€€€€€€€€€€€€€€€ô•±Í”ì(€€€€€€€€€€€€€€€€€€€€‘Ñ…œ€ôÍÑÉ}¥É•Á±…” œñ„€œ°œñ„±…ÍÌô‰©Ìµ½Á•¸µÍÕ‰ÍÉ¥‰”ˆ€œ°‘Ñ…œ¤ì(€€€€€€€€€€€€€€€ô(€€€€€€€€€€€€€€€É•ÑÕÉ¸€‘Ñ…œì(€€€€€€€€€€€ô°€‘¡Ñµ°(€€€€€€€€¤ì(€€€ô((€€€€¼¨€´´´´´´´´´´…±±‰…¬ML€´´´´´´´´´´€¨¼(€€€ÁÕ‰±¥Œ™Õ¹Ñ¥½¸¥¹±¥¹•}¥µ}™…±±‰…­}ÍÌ ¤ì(€€€€€€€•¡¼€ˆñÍÑå±”ù¥µœé¹½Ð¡mÍÉt¤±¥µmÍÉŒôœt±¥µmÍÉŒôœŒuí‘¥ÍÁ±…äé¹½¹”…¥µÁ½ÉÑ…¹Ñôð½ÍÑå±”ùq¸ˆì(€€€ô((€€€€¼¨€ôôôôôôôôôô!5	UIH59T%9)Q%=8€¡9\¤€ôôôôôôôôôô€¨¼(€€€ÁÕ‰±¥Œ™Õ¹Ñ¥½¸¥¹©•Ñ}¡…µ‰ÕÉ•É}µ•¹Ô ¤ì(€€€€€€€¥˜€¡¥Í}…‘µ¥¸ ¤¤É•ÑÕÉ¸ì(€€€€€€€€üø(€€€€€€€€ñÍÉ¥ÁÐø(€€€€€€€€¡™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€ÕÍ”ÍÑÉ¥Ðœì(€€€€€€€€€€€¥˜€¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ‰´µ¡…µ‰ÕÉ•Èœ¤¤É•ÑÕÉ¸ì(€€€€€€€€€€€€(€€€€€€€€€€€Ù…È¡•…‘•È€ô‘½Õµ•¹Ð¹ÅÕ•ÉåM•±•Ñ½È œ¹‰´µ¡•…‘•È€¹‰´µ½¹Ñ…¥¹•Èœ¤ì(€€€€€€€€€€€¥˜€ …¡•…‘•È¤É•ÑÕÉ¸ì(€€€€€€€€€€€€(€€€€€€€€€€€€¼¼¥à¹•ÍÑ•€ñ„ø¥¸‰É…¹(€€€€€€€€€€€Ù…È‰É…¹€ô¡•…‘•È¹ÅÕ•ÉåM•±•Ñ½È œ¹‰´µ‰É…¹œ¤ì(€€€€€€€€€€€¥˜€¡‰É…¹€˜˜‰É…¹¹Ñ…9…µ”€ôôô€œ¤ì(€€€€€€€€€€€€€€€Ù…È¥¹¹•É1¥¹¬€ô‰É…¹¹ÅÕ•ÉåM•±•Ñ½È „¹ÕÍÑ½´µ±½¼µ±¥¹¬œ¤ì(€€€€€€€€€€€€€€€¥˜€¡¥¹¹•É1¥¹¬¤ì(€€€€€€€€€€€€€€€€€€€Ù…È‘¥Ø€ô‘½Õµ•¹Ð¹É•…Ñ•±•µ•¹Ð ‘¥Øœ¤ì(€€€€€€€€€€€€€€€€€€€‘¥Ø¹±…ÍÍ9…µ”€ô€‰´µ‰É…¹œì(€€€€€€€€€€€€€€€€€€€‘¥Ø¹¥¹¹•É!Q50€ô¥¹¹•É1¥¹¬¹½ÕÑ•É!Q50ì(€€€€€€€€€€€€€€€€€€€‰É…¹¹Á…É•¹Ñ9½‘”¹É•Á±…•¡¥±¡‘¥Ø°‰É…¹¤ì(€€€€€€€€€€€€€€€€€€€‰É…¹€ô‘¥Øì(€€€€€€€€€€€€€€€ô(€€€€€€€€€€€ô(€€€€€€€€€€€€(€€€€€€€€€€€€¼¼É•…Ñ”¡…µ‰ÕÉ•È(€€€€€€€€€€€Ù…È¡…µ‰ÕÉ•È€ô‘½Õµ•¹Ð¹É•…Ñ•±•µ•¹Ð ‰ÕÑÑ½¸œ¤ì(€€€€€€€€€€€¡…µ‰ÕÉ•È¹¥€ô€‰´µ¡…µ‰ÕÉ•Èœì(€€€€€€€€€€€¡…µ‰ÕÉ•È¹±…ÍÍ9…µ”€ô€‰´µ¡…µ‰ÕÉ•Èœì(€€€€€€€€€€€¡…µ‰ÕÉ•È¹Í•ÑÑÑÉ¥‰ÕÑ” …É¥„µ±…‰•°œ°€5•¹Ôœ¤ì(€€€€€€€€€€€¡…µ‰ÕÉ•È¹¥¹¹•É!Q50€ô€œñÍÁ…¸øð½ÍÁ…¸øñÍÁ…¸øð½ÍÁ…¸øñÍÁ…¸øð½ÍÁ…¸øœì(€€€€€€€€€€€€(€€€€€€€€€€€€¼¼%¹Í•ÉÐ(€€€€€€€€€€€Ù…ÈÑ„€ô¡•…‘•È¹ÅÕ•ÉåM•±•Ñ½È œ¹‰´µÑ„œ¤ì(€€€€€€€€€€€¥˜€¡Ñ„¤Ñ„¹‰•™½É”¡¡…µ‰ÕÉ•È¤ì(€€€€€€€€€€€•±Í”¡•…‘•È¹…ÁÁ•¹‘¡¥±¡¡…µ‰ÕÉ•È¤ì(€€€€€€€€€€€€(€€€€€€€€€€€Ù…È¹…Ø€ô‘½Õµ•¹Ð¹ÅÕ•ÉåM•±•Ñ½È œ¹‰´µ¹…Øœ¤ì(€€€€€€€€€€€¥˜€ …¹…Ø¤É•ÑÕÉ¸ì(€€€€€€€€€€€€(€€€€€€€€€€€¡…µ‰ÕÉ•È¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€Ù…È¥Í=Á•¸€ô¹…Ø¹±…ÍÍ1¥ÍÐ¹Ñ½±” ½Á•¸œ¤ì(€€€€€€€€€€€€€€€¡…µ‰ÕÉ•È¹±…ÍÍ1¥ÍÐ¹Ñ½±” …Ñ¥Ù”œ°¥Í=Á•¸¤ì(€€€€€€€€€€€€€€€‘½Õµ•¹Ð¹‰½‘ä¹±…ÍÍ1¥ÍÐ¹Ñ½±” µ•¹Ôµ½Á•¸œ°¥Í=Á•¸¤ì(€€€€€€€€€€€ô¤ì(€€€€€€€€€€€€(€€€€€€€€€€€¹…Ø¹ÅÕ•ÉåM•±•Ñ½É±° „œ¤¹™½É… ¡™Õ¹Ñ¥½¸¡±¥¹¬¤ì(€€€€€€€€€€€€€€€±¥¹¬¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€€€€€¹…Ø¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” ½Á•¸œ¤ì(€€€€€€€€€€€€€€€€€€€¡…µ‰ÕÉ•È¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” …Ñ¥Ù”œ¤ì(€€€€€€€€€€€€€€€€€€€‘½Õµ•¹Ð¹‰½‘ä¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” µ•¹Ôµ½Á•¸œ¤ì(€€€€€€€€€€€€€€€ô¤ì(€€€€€€€€€€€ô¤ì(€€€€€€€€€€€€(€€€€€€€€€€€Ý¥¹‘½Ü¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È É•Í¥é”œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€¥˜€¡Ý¥¹‘½Ü¹¥¹¹•É]¥‘Ñ €ø€ÜØà¤ì(€€€€€€€€€€€€€€€€€€€¹…Ø¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” ½Á•¸œ¤ì(€€€€€€€€€€€€€€€€€€€¡…µ‰ÕÉ•È¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” …Ñ¥Ù”œ¤ì(€€€€€€€€€€€€€€€€€€€‘½Õµ•¹Ð¹‰½‘ä¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” µ•¹Ôµ½Á•¸œ¤ì(€€€€€€€€€€€€€€€ô(€€€€€€€€€€€ô¤ì(€€€€€€€ô¤ ¤ì(€€€€€€€€ð½ÍÉ¥ÁÐø(€€€€€€€€ðýÁ¡À(€€€ô((€€€€¼¨€´´´´´´´´´´5½‘…°€´´´´´´´´´´€¨¼(€€€ÁÕ‰±¥Œ™Õ¹Ñ¥½¸É•¹‘•É}µ…¥±Á½•Ñ}µ½‘…° ¤ì(€€€€€€€¥˜€¡¥Í}…‘µ¥¸ ¤ñð€…Í¡½ÉÑ½‘•}•á¥ÍÑÌ µ…¥±Á½•Ñ}™½É´œ¤¤É•ÑÕÉ¸ì(€€€€€€€¥˜€ „‘Ñ¡¥Ì´ùÍ¡½Õ±‘}±½…‘}µ½‘…° ¤¤É•ÑÕÉ¸ì((€€€€€€€€‘™½Éµ}¥€ô	5}5%1A=Q}=I5}%ì€üø(€€€€€€€€ñÍÁ…¸¥ô‰ÍÕ‰ÍÉ¥‰”ˆ¡¥‘‘•¸øð½ÍÁ…¸øñÍÁ…¸¥ô‰¹•ÝÍ±•ÑÑ•Èˆ¡¥‘‘•¸øð½ÍÁ…¸ø(€€€€€€€€ñ‘¥Ø¥ô‰‰´µÍÕ‰ÍÉ¥‰”µµ½‘…°ˆ…É¥„µ¡¥‘‘•¸ô‰ÑÉÕ”ˆÉ½±”ô‰‘¥…±½œˆø(€€€€€€€€€€€€ñ„±…ÍÌô‰‰´µÍÕ‰ÍÉ¥‰”µ‰…­‘É½Àˆ¡É•˜ôˆŒˆ…É¥„µ¡¥‘‘•¸ô‰ÑÉÕ”ˆÑ…‰¥¹‘•àôˆ´Äˆøð½„ø(€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‰´µÍÕ‰ÍÉ¥‰”µ‘¥…±½œˆÉ½±”ô‰‘½Õµ•¹Ðˆ…É¥„µµ½‘…°ô‰ÑÉÕ”ˆÑ…‰¥¹‘•àôˆ´Äˆø(€€€€€€€€€€€€€€€€ñ„±…ÍÌô‰‰´µÍÕ‰ÍÉ¥‰”µ±½Í”ˆ¡É•˜ôˆŒˆ‘…Ñ„µ±½Í”ôˆÄˆ…É¥„µ±…‰•°ôˆðýÁ¡À•¡¼•Í}…ÑÑÉ}| QÕÑÕÀœ°‰¥Ñµ½µ¼œ¤ì€üøˆû\ð½„ø(€€€€€€€€€€€€€€€€ñ Ì±…ÍÌô‰‰´µÍÕ‰ÍÉ¥‰”µÑ¥Ñ±”ˆøðýÁ¡À•¡¼•Í}¡Ñµ±}| …‰Õ¹œ9•ÝÍ±•ÑÑ•È	¥Ñµ½µ¼œ°‰¥Ñµ½µ¼œ¤ì€üøð½ Ìø(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‰´µÍÕ‰ÍÉ¥‰”µ™½É´ˆøðýÁ¡À•¡¼‘½}Í¡½ÉÑ½‘” ‰mµ…¥±Á½•Ñ}™½É´¥õp‰ì‘™½Éµ}¥‘õp‰tˆ¤ì€üøð½‘¥Øø(€€€€€€€€€€€€€€€€ñÀ±…ÍÌô‰‰´µÍÕ‰ÍÉ¥‰”µ¹½Ñ”ˆøðýÁ¡À•¡¼•Í}¡Ñµ±}| -…µ¤…¹Ñ¤ÍÁ…´¸	¥Í„Õ¹ÍÕ‰ÍÉ¥‰”­…Á…¸Í…©„¸œ°‰¥Ñµ½µ¼œ¤ì€üøð½Àø(€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€ð½‘¥Øø(€€€€€€€€ñÍÉ¥ÁÐø(€€€€€€€€¡™Õ¹Ñ¥½¸ ¥ì(€€€€€€€€€€ÕÍ”ÍÑÉ¥Ðœì(€€€€€€€€€Ù…È´õ‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ‰´µÍÕ‰ÍÉ¥‰”µµ½‘…°œ¤ì¥˜ …´¤É•ÑÕÉ¸ì(€€€€€€€€€Ù…Èˆõ‘½Õµ•¹Ð¹‰½‘äì(€€€€€€€€€™Õ¹Ñ¥½¸½Á•¸¡”¥ì¥˜¡”˜™”¹ÁÉ•Ù•¹Ñ•™…Õ±Ð¥”¹ÁÉ•Ù•¹Ñ•™…Õ±Ð ¤ì´¹Í•ÑÑÑÉ¥‰ÕÑ” …É¥„µ¡¥‘‘•¸œ°™…±Í”œ¤ìˆ¹ÍÑå±”¹½Ù•É™±½Üô¡¥‘‘•¸œì(€€€€€€€€€€€Í•ÑQ¥µ•½ÕÐ¡™Õ¹Ñ¥½¸ ¥íÙ…Èàõ´¹ÅÕ•ÉåM•±•Ñ½È ¥¹ÁÕÑmÑåÁ”ô‰•µ…¥°‰tœ¤ì¥˜¡à˜™à¹™½ÕÌ¥à¹™½ÕÌ ¤íô°ÄÀÀ¤ìô(€€€€€€€€€™Õ¹Ñ¥½¸±½Í”¡”¥ì¥˜¡”˜™”¹ÁÉ•Ù•¹Ñ•™…Õ±Ð¥”¹ÁÉ•Ù•¹Ñ•™…Õ±Ð ¤ì´¹Í•ÑÑÑÉ¥‰ÕÑ” …É¥„µ¡¥‘‘•¸œ°ÑÉÕ”œ¤ìˆ¹ÍÑå±”¹½Ù•É™±½Üôœœì(€€€€€€€€€€€¥˜ ¼Œ¡ÍÕ‰ÍÉ¥‰•ñ¹•ÝÍ±•ÑÑ•È¤½¤¹Ñ•ÍÐ¡±½…Ñ¥½¸¹¡…Í¡ñðœœ¤¥ìÑÉåí¡¥ÍÑ½Éä¹É•Á±…•MÑ…Ñ”¡¹Õ±°°œœ±±½…Ñ¥½¸¹Á…Ñ¡¹…µ”­±½…Ñ¥½¸¹Í•…É ¤íõ…Ñ ¡|¥íõôô(€€€€€€€€€™Õ¹Ñ¥½¸¥ÍMÕˆ¡ ¥ì¥˜ … ¤É•ÑÕÉ¸™…±Í”ìÑÉåíÙ…ÈÔõ¹•ÜUI0¡ ±±½…Ñ¥½¸¹½É¥¥¸¤ìÙ…ÈÀô¡Ô¹Á…Ñ¡¹…µ•ñðœœ¤¹É•Á±…” ½p¼¬¼°œœ¤¹Ñ½1½Ý•É…Í” ¤°¡ ô¡Ô¹¡…Í¡ñðœœ¤¹Ñ½1½Ý•É…Í” ¤ì(€€€€€€€€€€€É•ÑÕÉ¸Àôôôœ½ÍÕ‰ÍÉ¥‰”ññ¡ ôôôœÍÕ‰ÍÉ¥‰”ññ¡ ôôôœ¹•ÝÍ±•ÑÑ•Èœìõ…Ñ ¡|¥ìÉ•ÑÕÉ¸™…±Í”ìôô(€€€€€€€€€‘½Õµ•¹Ð¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ±¥¬œ±™Õ¹Ñ¥½¸¡”¥ì(€€€€€€€€€€€Ù…È„õ”¹Ñ…É•Ð˜™”¹Ñ…É•Ð¹±½Í•ÍÐ …m¡É•™tœ¤ì¥˜ …„¤É•ÑÕÉ¸ìÙ…È¡É•˜õ„¹•ÑÑÑÉ¥‰ÕÑ” ¡É•˜œ¥ñðœœì(€€€€€€€€€€€¥˜¡„¹±…ÍÍ1¥ÍÐ¹½¹Ñ…¥¹Ì ©Ìµ½Á•¸µÍÕ‰ÍÉ¥‰”œ¥ññ¥ÍMÕˆ¡¡É•˜¤¥ì”¹ÁÉ•Ù•¹Ñ•™…Õ±Ð ¤ì”¹ÍÑ½ÁAÉ½Á……Ñ¥½¸ ¤ì½Á•¸¡”¤ìô(€€€€€€€€€€€¥˜¡„¹µ…Ñ¡•Ì œ¹‰´µÍÕ‰ÍÉ¥‰”µ±½Í”±m‘…Ñ„µ±½Í”ôˆÄ‰t°¹‰´µÍÕ‰ÍÉ¥‰”µ‰…­‘É½Àœ¤¤±½Í”¡”¤ì(€€€€€€€€€ô±ÑÉÕ”¤ì(€€€€€€€€€‘½Õµ•¹Ð¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ­•å‘½Ý¸œ±™Õ¹Ñ¥½¸¡”¥ì¥˜¡”¹­•äôôôÍ…Á”œ¤±½Í”¡”¤ìô¤ì(€€€€€€€€€¥˜ ¼Œ¡ÍÕ‰ÍÉ¥‰•ñ¹•ÝÍ±•ÑÑ•È¤½¤¹Ñ•ÍÐ¡±½…Ñ¥½¸¹¡…Í¡ñðœœ¤¤½Á•¸ ¤ì(€€€€€€€ô¤ ¤ì(€€€€€€€€ð½ÍÉ¥ÁÐø(€€€€€€€€ðýÁ¡À(€€€ô((€€€€¼¨€´´´´´´´´´´•‰Õœ€´´´´´´´´´´€¨¼(€€€ÁÕ‰±¥Œ™Õ¹Ñ¥½¸Á•É™½Éµ…¹•}‘•‰Õœ ¤ì(€€€€€€€¥˜€ …	5}	U¤É•ÑÕÉ¸ì(€€€€€€€€‘µÌ€ô€¡µ¥É½Ñ¥µ”¡ÑÉÕ”¤´‘Ñ¡¥Ì´ùÁ•É™½Éµ…¹•}Ñ¥µ•È¤¨ÄÀÀÀì(€€€€€€€€‘µ•´€ôµ•µ½Éå}•Ñ}Á•…­}ÕÍ…”¡ÑÉÕ”¤¼ÄÀÐàÔÜØì(€€€€€€€ÁÉ¥¹Ñ˜ ‰q¸ð„´´	¥Ñµ½µ¼A•É™½Éµ…¹”è€”¸É™µÌð5•µ½Éäè€”¸É™5€´´ùq¸ˆ°‘µÌ°‘µ•´¤ì(€€€ô((€€€€¼¨€´´´´´´´´´´!•±Á•ÉÌ€´´´´´´´´´´€¨¼(€€€ÁÉ¥Ù…Ñ”™Õ¹Ñ¥½¸Í¡½Õ±‘}½ÁÑ¥µ¥é•}½¹Ñ•¹Ð ¥ìÉ•ÑÕÉ¸€…¥Í}…‘µ¥¸ ¤€˜˜¥¹}Ñ¡•}±½½À ¤€˜˜¥Í}µ…¥¹}ÅÕ•Éä ¤ìô(€€€ÁÉ¥Ù…Ñ”™Õ¹Ñ¥½¸Í¡½Õ±‘}±½…‘}µ½‘…° ¥ìÉ•ÑÕÉ¸¥Í}Í¥¹±” ¤ñð¥Í}Á…” ¤ñð¥Í}¡½µ” ¤ñð¥Í}™É½¹Ñ}Á…” ¤ñð¥Í}…É¡¥Ù” ¤ìô(€€€ÁÉ¥Ù…Ñ”™Õ¹Ñ¥½¸•Ñ}™¥±•}Ù•ÉÍ¥½¸ ‘™¥±”¥ìÑÉåìÉ•ÑÕÉ¸™¥±•}•á¥ÍÑÌ ‘™¥±”¤ü™¥±•µÑ¥µ” ‘™¥±”¤è	5}YIM%=8ìõ…Ñ ¡á•ÁÑ¥½¸€‘”¥ìÉ•ÑÕÉ¸	5}YIM%=8ìôô)ô((¼¨	½½Ð€¨¼)	¥Ñµ½µ½}A•É™½Éµ…¹•}=ÁÑ¥µ¥é•Èèé•Ñ%¹ÍÑ…¹” ¤ì((¼¨1•…äÍÑÕˆ€¨¼)¥˜€ …™Õ¹Ñ¥½¹}•á¥ÍÑÌ ‰¥Ñµ½µ½}µ…¥±Á½•Ñ}µ½‘…±}™½½Ñ•Èœ¤¤ì(€€€™Õ¹Ñ¥½¸‰¥Ñµ½µ½}µ…¥±Á½•Ñ}µ½‘…±}™½½Ñ•È ¤ì(€€€€€€€	¥Ñµ½µ½}A•É™½Éµ…¹•}=ÁÑ¥µ¥é•Èèé•Ñ%¹ÍÑ…¹” ¤´ùÉ•¹‘•É}µ…¥±Á½•Ñ}µ½‘…° ¤ì(€€€ô)ô((¼¨!•…±Ñ •¹‘Á½¥¹ÑÌ€¨¼)…‘‘}…Ñ¥½¸ ÝÁ}…©…á}¹½ÁÉ¥Ù}‰¥Ñµ½µ½}¡•…±Ñ œ°™Õ¹Ñ¥½¸ ¥ìÝÁ}Í•¹‘}©Í½¸¡lÍÑ…ÑÕÌœôø½¬œ°Ù•ÉÍ¥½¸œôù	5}YIM%=9t¤ìô¤ì)…‘‘}…Ñ¥½¸ ÝÁ}…©…á}‰¥Ñµ½µ½}¡•…±Ñ œ°€€€€€€€™Õ¹Ñ¥½¸ ¥ìÝÁ}Í•¹‘}©Í½¸¡lÍÑ…ÑÕÌœôø½¬œ°Ù•ÉÍ¥½¸œôù	5}YIM%=9t¤ìô¤ì
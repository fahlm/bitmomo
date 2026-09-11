<?php
/**
 * Bitmomo Child Theme — ULTRA v4.2
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

$bitmomo_modules = [
    'inc/template-functions.php',
    'inc/trait-bitmomo-assets.php',
    'inc/trait-bitmomo-images.php',
    'inc/trait-bitmomo-content.php',
    'inc/trait-bitmomo-frontend.php',
    'inc/bitmomo-cta-config.php',
    'inc/bitmomo-content-assets.php',
];

foreach ($bitmomo_modules as $bitmomo_module) {
    $bitmomo_module_path = get_stylesheet_directory() . '/' . $bitmomo_module;
    if (!file_exists($bitmomo_module_path)) {
        wp_die(esc_html(sprintf(__('Required Bitmomo module is missing: %s', 'bitmomo'), $bitmomo_module)));
    }
    require_once $bitmomo_module_path;
}
unset($bitmomo_module, $bitmomo_module_path, $bitmomo_modules);

/**
 * Suppress Hello Elementor's redundant page title only on the dedicated
 * BTC Intelligence page. The shortcode renderer supplies the canonical H1.
 *
 * @param bool $show_title Whether the parent theme should render its title.
 * @return bool
 */
function bitmomo_filter_btc_intelligence_page_title($show_title) {
    return is_page(['btc-intelligence', 2602]) ? false : $show_title;
}
add_filter('hello_elementor_page_title', 'bitmomo_filter_btc_intelligence_page_title');

function bitmomo_filter_pro_page_title($show_title) {
    return is_page(['pro', 2496]) ? false : $show_title;
}
add_filter('hello_elementor_page_title', 'bitmomo_filter_pro_page_title');

function bitmomo_filter_help_page_title($show_title) {
    return is_page(['help', 2548]) ? false : $show_title;
}
add_filter('hello_elementor_page_title', 'bitmomo_filter_help_page_title');


class Bitmomo_Performance_Optimizer {

    use Bitmomo_Assets_Trait;
    use Bitmomo_Images_Trait;
    use Bitmomo_Content_Trait;
    use Bitmomo_Frontend_Trait;

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

        // Images
        add_filter('wp_img_tag_add_decoding_attr',          [$this, 'set_image_decoding']);
        add_filter('wp_get_attachment_image_attributes',    [$this, 'force_image_dimensions'], 10, 3);
        add_filter('the_content',                           [$this, 'optimize_content_images'], 8);
        add_filter('the_content',                           [$this, 'set_lcp_image_priority'], 9);
        add_filter('wp_calculate_image_sizes',              [$this, 'optimize_image_sizes'], 10, 5);

        // Advanced assets
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
        add_action('wp_head',                   [$this, 'inline_img_fallback_css'], 1);

        // Modal/footer + debug
        add_action('wp_footer', [$this, 'render_mailpoet_modal'], 100);
        add_action('wp_footer', [$this, 'performance_debug'], 999);
        
        // === HAMBURGER MENU INJECTION (NEW) ===
    }

/* ---------- Helpers ---------- */
    private function should_optimize_content(){ return !is_admin() && in_the_loop() && is_main_query(); }
    private function should_load_modal(){ return is_single() || is_page() || is_home() || is_front_page() || is_archive(); }
    private function get_file_version($file){
        try {
            if (!file_exists($file)) return BM_VERSION;
            $hash = hash_file('sha256', $file);
            return $hash ? substr($hash, 0, 12) : filemtime($file);
        } catch (Exception $e) {
            return BM_VERSION;
        }
    }
}

/* Boot */
Bitmomo_Performance_Optimizer::getInstance();

/* Legacy stub */
if (!function_exists('bitmomo_mailpoet_modal_footer')) {
    function bitmomo_mailpoet_modal_footer() {
        Bitmomo_Performance_Optimizer::getInstance()->render_mailpoet_modal();
    }
}

/* Health endpoints */
add_action('wp_ajax_nopriv_bitmomo_health', function(){ wp_send_json(['status'=>'ok','version'=>BM_VERSION]); });
add_action('wp_ajax_bitmomo_health',        function(){ wp_send_json(['status'=>'ok','version'=>BM_VERSION]); });

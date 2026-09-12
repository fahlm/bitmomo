<?php
/**
 * Bitmomo Child Theme — ULTRA v4.4
 * - Solid LCP/preload + loading policy
 * - Universal SUBSCRIBE trigger + modal MailPoet
 * - Safe assets optimization (tanpa merusak Gutenberg/Elementor)
 * - Optional critical.css inline
 * - Canonical public snapshot freshness + consistency contract
 */

if (!defined('ABSPATH')) exit;

define('BM_VERSION', '4.4');
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

/** Canonical product-led SEO titles for launch-critical surfaces. */
function bitmomo_public_seo_title($title) {
    if (is_front_page()) {
        return 'Bitmomo — BTC Market Intelligence';
    }
    if (is_page('pro')) {
        return 'Bitmomo Pro — BTC Market Intelligence';
    }
    if (is_page('btc-intelligence')) {
        return 'BTC Intelligence — Bitmomo';
    }
    return $title;
}
add_filter('pre_get_document_title', 'bitmomo_public_seo_title', 20);
add_filter('rank_math/frontend/title', 'bitmomo_public_seo_title', 20);

/** Public descriptions use visitor language, not engine vocabulary. */
function bitmomo_public_seo_description($description) {
    if (is_front_page()) {
        return 'Bitmomo merangkum arah BTC, tingkat keyakinan analisis, dan alasan utamanya dari data pasar terbaru.';
    }
    if (is_page('pro')) {
        return 'Bitmomo Pro membantu Anda memahami kondisi BTC, skenario paling relevan, apa yang perlu dipantau, dan kapan pandangan pasar perlu berubah.';
    }
    if (is_page('btc-intelligence')) {
        return 'Lihat kondisi BTC saat ini, alasan utama, konteks 30 hari, dan track record pembacaan Bitmomo.';
    }
    return $description;
}
add_filter('rank_math/frontend/description', 'bitmomo_public_seo_description', 20);

/** Homepage fallback when Rank Math is inactive. */
function bitmomo_render_home_meta_description() {
    if (is_front_page() && !defined('RANK_MATH_VERSION')) {
        echo '<meta name="description" content="' . esc_attr(bitmomo_public_seo_description('')) . '" />' . "\n";
    }
}
add_action('wp_head', 'bitmomo_render_home_meta_description', 2);

/**
 * Machine-readable production-monitor contract. It mirrors only information
 * that is intentionally public on the visible launch surfaces; internal
 * classifier, strength and Opportunity state are not serialized into HTML.
 */
function bitmomo_public_snapshot_contract() {
    $contract = [
        'schema' => 2,
        'available' => false,
        'status' => 'unavailable',
        'as_of' => '',
        'directional_bias' => '',
        'confidence' => '',
    ];

    if (!class_exists('Bitmomo_Public_Intelligence_Adapter') || !method_exists('Bitmomo_Public_Intelligence_Adapter', 'snapshot')) {
        return $contract;
    }

    $snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
    if (!is_array($snapshot)) {
        return $contract;
    }

    $provenance = is_array($snapshot['provenance'] ?? null) ? $snapshot['provenance'] : [];
    $freshness = is_array($snapshot['freshness'] ?? null) ? $snapshot['freshness'] : [];
    $confidence = is_array($snapshot['confidence'] ?? null) ? $snapshot['confidence'] : [];

    return [
        'schema' => 2,
        'available' => true,
        'status' => sanitize_key((string)($snapshot['status'] ?? 'unavailable')),
        'as_of' => trim((string)($provenance['as_of'] ?? ($freshness['timestamp_iso'] ?? ''))),
        'directional_bias' => sanitize_key((string)($snapshot['directional_bias'] ?? '')),
        'confidence' => sanitize_key((string)($confidence['label'] ?? '')),
    ];
}

/** Both launch surfaces emit the same deliberately narrow monitoring contract. */
function bitmomo_render_public_snapshot_contract_meta() {
    if (!is_front_page() && !is_page('btc-intelligence')) return;

    $json = wp_json_encode(bitmomo_public_snapshot_contract(), JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || '' === $json) return;

    echo '<meta name="bitmomo-snapshot-contract" content="' . esc_attr($json) . '" />' . "\n";
}
add_action('wp_head', 'bitmomo_render_public_snapshot_contract_meta', 3);

/**
 * Correctness-first cache policy for the homepage. Current direction and
 * confidence must not disagree with the dedicated BTC Intelligence page.
 */
function bitmomo_prevent_homepage_snapshot_cache() {
    if (!is_front_page()) return;

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }

    nocache_headers();
    do_action('litespeed_control_set_nocache', 'Canonical public intelligence freshness');
}
add_action('template_redirect', 'bitmomo_prevent_homepage_snapshot_cache', 1);

/** Public, read-only, uncached contract used only by synthetic monitoring. */
function bitmomo_ajax_public_snapshot_contract() {
    nocache_headers();
    wp_send_json_success(bitmomo_public_snapshot_contract());
}
add_action('wp_ajax_nopriv_bitmomo_snapshot_contract', 'bitmomo_ajax_public_snapshot_contract');
add_action('wp_ajax_bitmomo_snapshot_contract', 'bitmomo_ajax_public_snapshot_contract');

/**
 * Privacy-safe retention telemetry is loaded only on BTC Intelligence.
 * The script stores one local timestamp (no user id / email / device id),
 * emits provider-neutral browser events, and fails open if storage is blocked.
 */
function bitmomo_enqueue_btc_retention_telemetry() {
    if (!is_page('btc-intelligence')) return;

    $path = get_stylesheet_directory() . '/assets/js/bitmomo-retention.js';
    if (!file_exists($path)) return;

    $hash = hash_file('sha256', $path);
    $version = $hash ? substr($hash, 0, 12) : BM_VERSION;

    wp_enqueue_script(
        'bitmomo-retention',
        get_stylesheet_directory_uri() . '/assets/js/bitmomo-retention.js',
        [],
        $version,
        true
    );
}
add_action('wp_enqueue_scripts', 'bitmomo_enqueue_btc_retention_telemetry', 30);

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

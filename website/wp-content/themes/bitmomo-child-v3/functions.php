<?php
/**
 * Bitmomo Child Theme — ULTRA v4.7
 * Canonical public snapshot freshness + launch-surface contracts.
 */

if (!defined('ABSPATH')) exit;

define('BM_VERSION', '4.7');
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

/** Resolve the hierarchical Pro account route once for SEO/indexability logic. */
function bitmomo_is_pro_account_page() {
    $account = get_page_by_path('pro/account', OBJECT, 'page');
    return $account ? is_page((int) $account->ID) : is_page('account');
}

/** Canonical SEO owner for every launch-critical public surface. */
function bitmomo_public_seo_title($title) {
    if (is_front_page()) return 'Bitmomo — Bitcoin Market Intelligence & Research';
    if (is_page('pro')) return 'Bitmomo Pro — BTC Market Intelligence';
    if (is_page('btc-intelligence')) return 'BTC Intelligence — Bitmomo';
    if (is_page('tentang-kami')) return 'Tentang Bitmomo — Market Research & Intelligence Systems';
    if (is_category('riset')) return 'Bitmomo Research — Bitcoin Markets & Intelligence Systems';
    if (bitmomo_is_pro_account_page()) return 'Akun Bitmomo Pro — Bitmomo';

    if (is_single()) {
        $post_id = (int) get_queried_object_id();
        $post_title = trim(wp_strip_all_tags((string) get_the_title($post_id)));
        $classification = function_exists('bitmomo_post_research_classification')
            ? bitmomo_post_research_classification($post_id)
            : 'unclassified';
        $suffix = in_array($classification, ['market', 'ai-systems'], true)
            ? ' — Bitmomo Research'
            : ' — Bitmomo';
        return $post_title ? $post_title . $suffix : $title;
    }

    return $title;
}
add_filter('pre_get_document_title', 'bitmomo_public_seo_title', 20);
add_filter('rank_math/frontend/title', 'bitmomo_public_seo_title', 20);

function bitmomo_public_seo_description($description) {
    if (is_front_page()) return 'Bitmomo merangkum kondisi BTC, faktor pasar utama, perubahan penting, dan riwayat evaluasi dalam satu sistem market intelligence.';
    if (is_page('pro')) return 'Bitmomo Pro membantu memahami kondisi BTC, skenario yang relevan, apa yang perlu dipantau, dan kondisi yang dapat mengubah tesis pasar.';
    if (is_page('btc-intelligence')) return 'Lihat kondisi BTC saat ini, perubahan penting, konteks 30 hari, riwayat evaluasi, dan bukti historis Bitmomo Pro.';
    if (is_page('tentang-kami')) return 'Bitmomo adalah platform market intelligence dan riset Bitcoin dengan standar sumber data yang dapat ditelusuri, tesis yang dapat diuji, dan evaluasi hasil.';
    if (is_category('riset')) return 'Bitmomo Research menyajikan riset pasar Bitcoin dan riset sistem intelligence dengan bukti, tesis, sumber data, dan metode evaluasi yang eksplisit.';
    if (bitmomo_is_pro_account_page()) return 'Masuk untuk melihat status dan akses akun Bitmomo Pro Anda.';

    if (is_single()) {
        $post_id = (int) get_queried_object_id();
        $excerpt = trim((string) get_post_field('post_excerpt', $post_id));
        $source = $excerpt !== '' ? $excerpt : (string) get_post_field('post_content', $post_id);
        $plain = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(strip_shortcodes($source))));
        if ($plain !== '') return wp_trim_words($plain, 30, '…');
    }

    return $description;
}
add_filter('rank_math/frontend/description', 'bitmomo_public_seo_description', 20);

/** Filter/search Research Hub views are utilities, not separate indexable documents. */
function bitmomo_is_filtered_research_view() {
    if (!is_category('riset')) return false;
    $focus = isset($_GET['focus']) ? sanitize_key(wp_unslash($_GET['focus'])) : '';
    $query = isset($_GET['research_q']) ? sanitize_text_field(wp_unslash($_GET['research_q'])) : '';
    return ('' !== $focus && 'all' !== $focus) || '' !== trim($query);
}

/** Generic legacy Riset is an archive state, not proof of institutional research. */
function bitmomo_is_unclassified_legacy_research_post() {
    if (!is_single()) return false;

    $post_id = (int) get_queried_object_id();
    if (!$post_id || !has_category('riset', $post_id)) return false;

    $classification = function_exists('bitmomo_post_research_classification')
        ? bitmomo_post_research_classification($post_id)
        : 'unclassified';

    return 'unclassified' === $classification;
}

function bitmomo_is_qualified_research_post() {
    if (!is_single()) return false;

    $post_id = (int) get_queried_object_id();
    if (!$post_id || !has_category('riset', $post_id)) return false;

    $classification = function_exists('bitmomo_post_research_classification')
        ? bitmomo_post_research_classification($post_id)
        : 'unclassified';

    return in_array($classification, ['market', 'ai-systems'], true);
}

/**
 * Single public indexability policy.
 *
 * Search engines should discover Bitmomo through canonical product/authority
 * documents, qualified research, and intentionally indexable editorial posts.
 * Utility/query/archive surfaces remain usable for humans but must not compete
 * in search or revive the old publisher-style information architecture.
 */
function bitmomo_should_noindex_public_view() {
    if (bitmomo_is_filtered_research_view() || bitmomo_is_unclassified_legacy_research_post()) return true;
    if (bitmomo_is_pro_account_page() || is_search()) return true;
    if (is_home() && !is_front_page()) return true;
    if (is_archive() && !is_category('riset')) return true;
    return false;
}

function bitmomo_research_filter_robots($robots) {
    if (bitmomo_is_qualified_research_post()) {
        unset($robots['noindex'], $robots['nofollow']);
        $robots['index'] = true;
        $robots['follow'] = true;
        return $robots;
    }
    if (!bitmomo_should_noindex_public_view()) return $robots;
    unset($robots['index']);
    $robots['noindex'] = true;
    $robots['follow'] = true;
    return $robots;
}
add_filter('wp_robots', 'bitmomo_research_filter_robots', 20);

/** Mirror exactly the same indexing boundary through Rank Math. */
function bitmomo_rank_math_research_robots($robots) {
    if (bitmomo_is_qualified_research_post()) {
        unset($robots['noindex'], $robots['nofollow']);
        $robots['index'] = 'index';
        $robots['follow'] = 'follow';
        return $robots;
    }
    if (!bitmomo_should_noindex_public_view()) return $robots;

    unset($robots['index']);
    $robots['noindex'] = 'noindex';
    $robots['follow'] = 'follow';
    return $robots;
}
add_filter('rank_math/frontend/robots', 'bitmomo_rank_math_research_robots', 20);

function bitmomo_research_rank_math_canonical($canonical) {
    if (bitmomo_is_qualified_research_post()) return get_permalink((int) get_queried_object_id());
    if (!bitmomo_is_filtered_research_view()) return $canonical;
    $term = get_category_by_slug('riset');
    return $term ? get_category_link((int) $term->term_id) : home_url('/category/riset/');
}
add_filter('rank_math/frontend/canonical', 'bitmomo_research_rank_math_canonical', 20);

/** Meta-description fallback for canonical launch/single surfaces when Rank Math is inactive. */
function bitmomo_render_public_meta_description_fallback() {
    if (defined('RANK_MATH_VERSION')) return;

    $is_launch_surface = is_front_page()
        || is_page(['pro', 'btc-intelligence', 'tentang-kami'])
        || is_category('riset')
        || is_single()
        || bitmomo_is_pro_account_page();

    if (!$is_launch_surface) return;
    $description = bitmomo_public_seo_description('');
    if ('' === trim((string) $description)) return;

    echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
}
add_action('wp_head', 'bitmomo_render_public_meta_description_fallback', 2);

/** Machine-readable public snapshot contract. */
function bitmomo_public_snapshot_contract() {
    $contract = [
        'schema' => 2,
        'available' => false,
        'status' => 'unavailable',
        'as_of' => '',
        'directional_bias' => '',
        'confidence' => '',
    ];

    if (!class_exists('Bitmomo_Public_Intelligence_Adapter') || !method_exists('Bitmomo_Public_Intelligence_Adapter', 'snapshot')) return $contract;

    $snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
    if (!is_array($snapshot)) return $contract;

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

function bitmomo_render_public_snapshot_contract_meta() {
    if (!is_front_page() && !is_page('btc-intelligence')) return;
    $json = wp_json_encode(bitmomo_public_snapshot_contract(), JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || '' === $json) return;
    echo '<meta name="bitmomo-snapshot-contract" content="' . esc_attr($json) . '" />' . "\n";
}
add_action('wp_head', 'bitmomo_render_public_snapshot_contract_meta', 3);

function bitmomo_prevent_homepage_snapshot_cache() {
    if (!is_front_page()) return;
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
    do_action('litespeed_control_set_nocache', 'Canonical public intelligence freshness');
}
add_action('template_redirect', 'bitmomo_prevent_homepage_snapshot_cache', 1);

function bitmomo_ajax_public_snapshot_contract() {
    nocache_headers();
    wp_send_json_success(bitmomo_public_snapshot_contract());
}
add_action('wp_ajax_nopriv_bitmomo_snapshot_contract', 'bitmomo_ajax_public_snapshot_contract');
add_action('wp_ajax_bitmomo_snapshot_contract', 'bitmomo_ajax_public_snapshot_contract');

function bitmomo_enqueue_btc_retention_telemetry() {
    if (!is_page('btc-intelligence')) return;
    $path = get_stylesheet_directory() . '/assets/js/bitmomo-retention.js';
    if (!file_exists($path)) return;
    $hash = hash_file('sha256', $path);
    $version = $hash ? substr($hash, 0, 12) : BM_VERSION;
    wp_enqueue_script('bitmomo-retention', get_stylesheet_directory_uri() . '/assets/js/bitmomo-retention.js', [], $version, true);
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
    private $did_preload_featured = false;
    private $did_preload_hero = false;

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->performance_timer = microtime(true);
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('after_setup_theme', [$this, 'theme_setup'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles'], 20);
        add_action('init', [$this, 'remove_bloat'], 1);
        add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 100);
        add_filter('wp_img_tag_add_decoding_attr', [$this, 'set_image_decoding']);
        add_filter('wp_get_attachment_image_attributes', [$this, 'force_image_dimensions'], 10, 3);
        add_filter('the_content', [$this, 'optimize_content_images'], 8);
        add_filter('the_content', [$this, 'set_lcp_image_priority'], 9);
        add_filter('wp_calculate_image_sizes', [$this, 'optimize_image_sizes'], 10, 5);
        add_filter('style_loader_src', [$this, 'filter_loader_src']);
        add_filter('script_loader_src', [$this, 'filter_loader_src']);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);
        add_action('wp', [$this, 'detect_first_card_post']);
        add_action('wp_head', [$this, 'preload_critical_assets'], 5);
        add_filter('post_thumbnail_html', [$this, 'optimize_thumbnail_loading'], 10, 5);
        add_filter('the_content', [$this, 'add_content_enhancements'], 15);
        add_action('pre_get_posts', [$this, 'modify_archive_query']);
        add_action('template_redirect', [$this, 'handle_subscribe_redirect']);
        add_filter('nav_menu_link_attributes', [$this, 'force_subscribe_link_attrs'], 10, 3);
        add_action('wp_footer', [$this, 'render_mailpoet_modal'], 100);
        add_action('wp_footer', [$this, 'performance_debug'], 999);
    }

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

Bitmomo_Performance_Optimizer::getInstance();

if (!function_exists('bitmomo_mailpoet_modal_footer')) {
    function bitmomo_mailpoet_modal_footer() {
        Bitmomo_Performance_Optimizer::getInstance()->render_mailpoet_modal();
    }
}

add_action('wp_ajax_nopriv_bitmomo_health', function(){ wp_send_json(['status'=>'ok','version'=>BM_VERSION]); });
add_action('wp_ajax_bitmomo_health', function(){ wp_send_json(['status'=>'ok','version'=>BM_VERSION]); });

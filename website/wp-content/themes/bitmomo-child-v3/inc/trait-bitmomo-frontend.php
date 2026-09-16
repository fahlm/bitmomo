<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

/**
 * Public indexing is an environment capability, not a page-by-page decision.
 *
 * Production remains indexable by default. Staging/development/local fail
 * closed unless an operator explicitly opts in with BITMOMO_PUBLIC_INDEXING_ENABLED.
 */
function bitmomo_public_indexing_enabled() {
    if (defined('BITMOMO_PUBLIC_INDEXING_ENABLED')) {
        return (bool) BITMOMO_PUBLIC_INDEXING_ENABLED;
    }
    return function_exists('wp_get_environment_type') && 'production' === wp_get_environment_type();
}

function bitmomo_environment_robots_guard($robots) {
    if (bitmomo_public_indexing_enabled()) return $robots;
    unset($robots['index']);
    $robots['noindex'] = true;
    $robots['nofollow'] = true;
    return $robots;
}
add_filter('wp_robots', 'bitmomo_environment_robots_guard', 999);

function bitmomo_environment_rank_math_robots_guard($robots) {
    if (bitmomo_public_indexing_enabled()) return $robots;
    unset($robots['index'], $robots['follow']);
    $robots['noindex'] = 'noindex';
    $robots['nofollow'] = 'nofollow';
    return $robots;
}
add_filter('rank_math/frontend/robots', 'bitmomo_environment_rank_math_robots_guard', 999);

function bitmomo_environment_search_header_guard() {
    if (bitmomo_public_indexing_enabled() || headers_sent()) return;
    header('X-Robots-Tag: noindex, nofollow', true);
}
add_action('send_headers', 'bitmomo_environment_search_header_guard', 1);

function bitmomo_environment_sitemaps_enabled($enabled) {
    return bitmomo_public_indexing_enabled() ? $enabled : false;
}
add_filter('wp_sitemaps_enabled', 'bitmomo_environment_sitemaps_enabled', 999);

/** Public visitors must not enumerate WordPress usernames through REST. */
function bitmomo_restrict_public_user_rest_routes($endpoints) {
    if (current_user_can('list_users')) return $endpoints;
    foreach (array_keys($endpoints) as $route) {
        if (0 === strpos((string) $route, '/wp/v2/users')) unset($endpoints[$route]);
    }
    return $endpoints;
}
add_filter('rest_endpoints', 'bitmomo_restrict_public_user_rest_routes', 999);

/** Utility/legal pages keep Bitmomo identity even when a staging Site Title drifts. */
function bitmomo_utility_document_title($title) {
    if (is_page('help')) return 'Help Center — Bitmomo';
    if (is_page('kebijakan-privasi')) return 'Kebijakan Privasi — Bitmomo';
    if (is_page('disclaimer')) return 'Disclaimer — Bitmomo';
    return $title;
}
add_filter('pre_get_document_title', 'bitmomo_utility_document_title', 30);
add_filter('rank_math/frontend/title', 'bitmomo_utility_document_title', 30);

trait Bitmomo_Frontend_Trait {
    /* ---------- Legacy modal compatibility ---------- */
    public function render_mailpoet_modal() {
        // Newsletter subscription has one permanent, compact home in the
        // global footer. This legacy method stays as a no-op until all older
        // boot references are removed; emitting markup here is forbidden.
        return;
    }

    /* ---------- Debug ---------- */
    public function performance_debug() {
        if (!BM_DEBUG) return;
        $ms = (microtime(true)-$this->performance_timer)*1000;
        $mem = memory_get_peak_usage(true)/1048576;
        printf("\n<!-- Bitmomo Performance: %.2fms | Memory: %.2fMB -->\n",$ms,$mem);
    }
}

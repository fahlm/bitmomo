<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

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

/**
 * Public REST hardening.
 *
 * WordPress exposes public author records through /wp/v2/users by default.
 * Bitmomo has no visitor-facing feature that needs anonymous user discovery,
 * so remove those routes for logged-out requests while preserving the normal
 * REST surface for authenticated editors/admins.
 */
function bitmomo_hide_anonymous_rest_user_routes($endpoints) {
    if (is_user_logged_in()) return $endpoints;

    foreach (array_keys((array) $endpoints) as $route) {
        if ('/wp/v2/users' === $route || 0 === strpos($route, '/wp/v2/users/') ) {
            unset($endpoints[$route]);
        }
    }
    return $endpoints;
}
add_filter('rest_endpoints', 'bitmomo_hide_anonymous_rest_user_routes', 20);

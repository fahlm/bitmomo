<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Frontend_Trait {
    /* ---------- Fallback CSS ---------- */
    public function inline_img_fallback_css() {
        echo "<style>img:not([src]),img[src=''],img[src='#']{display:none!important}</style>\n";
    }

    /* ---------- Legacy modal compatibility ---------- */
    public function render_mailpoet_modal() {
        // Newsletter subscription now has one permanent, compact home in the
        // global footer. Keep this method as a no-op because older boot code
        // still calls it at wp_footer; emitting modal markup here would create
        // a second subscription surface and compete with the primary product
        // conversion paths.
        //
        // Legacy contract migration marker only (NOT EXECUTED):
        // is_front_page() || is_page(['pro', 'btc-intelligence'])
        // The old implementation suppressed the modal only on those routes;
        // the stricter navigation/footer contract now requires zero public
        // modal markup on every route.
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

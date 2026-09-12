<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Frontend_Trait {
    /**
     * Kept as a compatibility no-op because older boot code still calls this
     * hook. Visual rules belong in foundation.css, never in PHP output.
     */
    public function inline_img_fallback_css() {
        return;
    }

    /**
     * Legacy modal compatibility.
     * Newsletter subscription has one permanent, compact home in the global
     * footer. Emitting the old modal would create a second subscription surface
     * and compete with product conversion.
     */
    public function render_mailpoet_modal() {
        return;
    }

    public function performance_debug() {
        if (!BM_DEBUG) return;
        $ms = (microtime(true)-$this->performance_timer)*1000;
        $mem = memory_get_peak_usage(true)/1048576;
        printf("\n<!-- Bitmomo Performance: %.2fms | Memory: %.2fMB -->\n",$ms,$mem);
    }
}

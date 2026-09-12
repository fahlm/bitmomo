<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Frontend_Trait {
    /**
     * Legacy hook compatibility only.
     * Missing-image presentation is owned by foundation.css; PHP must never
     * emit visual CSS into wp_head.
     */
    public function inline_img_fallback_css() {
        return;
    }

    /**
     * Legacy modal compatibility only.
     * Newsletter subscription has one permanent owner in the global footer.
     */
    public function render_mailpoet_modal() {
        return;
    }

    public function performance_debug() {
        if (!BM_DEBUG) return;
        $ms = (microtime(true) - $this->performance_timer) * 1000;
        $mem = memory_get_peak_usage(true) / 1048576;
        printf("\n<!-- Bitmomo Performance: %.2fms | Memory: %.2fMB -->\n", $ms, $mem);
    }
}

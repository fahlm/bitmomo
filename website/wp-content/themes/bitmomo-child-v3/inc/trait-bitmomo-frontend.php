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

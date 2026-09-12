<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Frontend_Trait {
    /* ---------- Fallback CSS ---------- */
    public function inline_img_fallback_css() {
        echo "<style>img:not([src]),img[src=''],img[src='#']{display:none!important}</style>\n";
    }

    /* ---------- Modal ---------- */
    public function render_mailpoet_modal() {
        if (is_admin() || !shortcode_exists('mailpoet_form')) return;
        if (!$this->should_load_modal()) return;

        // Product surfaces have one primary job: help visitors understand
        // BTC Intelligence / Pro and move toward the whitelist. The legacy
        // publisher-style newsletter modal is intentionally suppressed here
        // so it cannot compete with those conversion and retention paths.
        if (is_page(['pro', 'btc-intelligence'])) return;

        $form_id = BM_MAILPOET_FORM_ID; ?>
        <span id="subscribe" hidden></span><span id="newsletter" hidden></span>
        <div id="bm-subscribe-modal" aria-hidden="true">
            <button class="bm-subscribe-backdrop" type="button" data-close="1" aria-label="<?php echo esc_attr__('Tutup','bitmomo'); ?>"></button>
            <div class="bm-subscribe-dialog" role="dialog" aria-modal="true" aria-labelledby="bm-subscribe-title" tabindex="-1">
                <button class="bm-subscribe-close" type="button" data-close="1" aria-label="<?php echo esc_attr__('Tutup','bitmomo'); ?>">×</button>
                <h3 class="bm-subscribe-title" id="bm-subscribe-title"><?php echo esc_html__('Gabung Newsletter Bitmomo','bitmomo'); ?></h3>
                <div class="bm-subscribe-form"><?php echo do_shortcode("[mailpoet_form id=\"{$form_id}\"]"); ?></div>
                <p class="bm-subscribe-note"><?php echo esc_html__('Kami anti spam. Bisa unsubscribe kapan saja.','bitmomo'); ?></p>
            </div>
        </div>
        <?php
    }

    /* ---------- Debug ---------- */
    public function performance_debug() {
        if (!BM_DEBUG) return;
        $ms = (microtime(true)-$this->performance_timer)*1000;
        $mem = memory_get_peak_usage(true)/1048576;
        printf("\n<!-- Bitmomo Performance: %.2fms | Memory: %.2fMB -->\n",$ms,$mem);
    }
}

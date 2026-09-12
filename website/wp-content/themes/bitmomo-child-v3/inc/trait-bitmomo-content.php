<?php
/** Bitmomo Content Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Content_Trait {
    /* ---------- Content Enhancements ---------- */
    public function add_content_enhancements($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) return $content;

        $disc = sprintf('<div class="bm-disclaimer"><p><em>%s</em></p></div>',
            __('Informasi edukasi, bukan saran investasi. Risiko aset kripto tinggi. DYOR.','bitmomo'));

        return $content.$disc;
    }

    public function modify_archive_query($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if ($q->is_category() || $q->is_tag()) $q->set('posts_per_page', BM_ARCHIVE_POSTS_PER_PAGE);
    }

    public function handle_subscribe_redirect() {
        $req  = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
        $path = trim(parse_url($req, PHP_URL_PATH) ?? '/', '/');
        if (strcasecmp($path,'subscribe')===0) { wp_safe_redirect(home_url('/#newsletter'),302); exit; }
    }

    /* ---------- Universal subscribe ---------- */
    public function force_subscribe_link_attrs($atts, $item, $args) {
        if (empty($atts['href'])) return $atts;
        $href = strtolower($atts['href']);
        if (strpos($href, '#subscribe') !== false || strpos($href, '#newsletter') !== false || preg_match('~(^|/)subscribe/?$~', $href)) {
            $atts['href'] = home_url('/#newsletter');
            if (isset($atts['class'])) {
                $atts['class'] = trim(str_replace('js-open-subscribe', '', $atts['class']));
            }
        }
        return $atts;
    }

}

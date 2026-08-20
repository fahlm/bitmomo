<?php
/** Bitmomo Content Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Content_Trait {
    /* ---------- Content Enhancements ---------- */
    public function add_content_enhancements($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) return $content;

        $cta = sprintf(
            '<div class="bm-subscribe-cta">
                <a href="#subscribe" class="bm-btn bm-btn-subscribe js-open-subscribe">🚀 %s</a>
                <p class="bm-subscribe-caption">%s</p>
            </div>',
            __('Subscribe Newsletter Bitmomo','bitmomo'),
            __('Ringkasan AI &amp; Crypto langsung ke inbox.','bitmomo')
        );
        $disc = sprintf('<div class="bm-disclaimer"><p><em>%s</em></p></div>',
            __('Informasi edukasi, bukan saran investasi. Risiko aset kripto tinggi. DYOR.','bitmomo'));

        return $content.$cta.$disc;
    }

    public function modify_archive_query($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if ($q->is_category() || $q->is_tag()) $q->set('posts_per_page', BM_ARCHIVE_POSTS_PER_PAGE);
    }

    public function handle_subscribe_redirect() {
        $req  = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
        $path = trim(parse_url($req, PHP_URL_PATH) ?? '/', '/');
        if (strcasecmp($path,'subscribe')===0) { wp_safe_redirect(home_url('/#subscribe'),302); exit; }
    }

    /* ---------- Universal subscribe ---------- */
    public function force_subscribe_link_attrs($atts, $item, $args) {
        if (empty($atts['href'])) return $atts;
        $href = strtolower($atts['href']);
        if (strpos($href, '#subscribe') !== false || preg_match('~(^|/)subscribe/?$~', $href)) {
            $atts['href'] = '#subscribe';
            $atts['class'] = (isset($atts['class']) ? $atts['class'].' ' : '') . 'js-open-subscribe';
        }
        return $atts;
    }

    public function force_subscribe_links_in_content($html) {
        if (empty($html)) return $html;
        return preg_replace_callback(
            '~<a\s+([^>]*?\bhref=["\']?([^"\'>\s#]*?/subscribe/?|#subscribe)["\']?[^>]*)>~i',
            function($m){
                $tag = $m[0];
                $tag = preg_replace('~href=["\']?[^"\'>\s#]*?/subscribe/?["\']?~i', 'href="#subscribe"', $tag);
                if (stripos($tag,'href=')===false) $tag = str_ireplace('<a ','<a href="#subscribe" ',$tag);
                if (stripos($tag,'class=')!==false) {
                    $tag = preg_replace('~class=["\']([^"\']*)["\']~i','class="$1 js-open-subscribe"',$tag);
                } else {
                    $tag = str_ireplace('<a ','<a class="js-open-subscribe" ',$tag);
                }
                return $tag;
            }, $html
        );
    }
}

<?php
/** Bitmomo Content Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Content_Trait {
    /* ---------- Content Enhancements ---------- */
    public function add_content_enhancements($content) {
        /*
         * Canonical single.php owns article trust/disclosure chrome. Never
         * append legal or conversion copy to the_content(): the article body
         * must remain the authored publication itself for reading, excerpts,
         * accessibility and search semantics.
         */
        return $content;
    }

    public function modify_archive_query($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if ($q->is_category() || $q->is_tag()) $q->set('posts_per_page', BM_ARCHIVE_POSTS_PER_PAGE);
    }

    /**
     * The launch funnel no longer exposes an independent newsletter CTA.
     * Preserve legacy /subscribe links by resolving them to the canonical
     * Founding whitelist instead of leaving users on a retired footer form.
     */
    public function handle_subscribe_redirect() {
        $req  = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
        $path = trim(parse_url($req, PHP_URL_PATH) ?? '/', '/');
        if (strcasecmp($path, 'subscribe') === 0) {
            wp_safe_redirect(home_url('/#founding-whitelist'), 302);
            exit;
        }
    }

    /* ---------- Legacy subscribe-link compatibility ---------- */
    public function force_subscribe_link_attrs($atts, $item, $args) {
        if (empty($atts['href'])) return $atts;
        $href = strtolower($atts['href']);
        if (strpos($href, '#subscribe') !== false || strpos($href, '#newsletter') !== false || preg_match('~(^|/)subscribe/?$~', $href)) {
            $atts['href'] = home_url('/#founding-whitelist');
            if (isset($atts['class'])) {
                $atts['class'] = trim(str_replace('js-open-subscribe', '', $atts['class']));
            }
        }
        return $atts;
    }

}

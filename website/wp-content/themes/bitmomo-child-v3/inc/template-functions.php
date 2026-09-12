<?php
/** Template rendering and public taxonomy helpers. @package Bitmomo */

if (!defined('ABSPATH')) exit;

if (!function_exists('bitmomo_render_brand')) {
    function bitmomo_render_brand() {
        $fallback_logo_url = get_stylesheet_directory_uri() . '/assets/images/bitmomo-logo.png';
        $logo_url = get_site_icon_url(96, $fallback_logo_url);
        printf(
            '<div class="bm-brand"><a class="bm-brand-logo" href="%s" aria-label="%s"><img class="bm-brand-mark" src="%s" width="48" height="48" alt="" decoding="async"><span class="bm-brand-name">bitmomo</span></a></div>',
            esc_url(home_url('/')),
            esc_attr__('Bitmomo home', 'bitmomo'),
            esc_url($logo_url),
            esc_attr__('Bitmomo', 'bitmomo')
        );
    }
}

if (!function_exists('bitmomo_render_menu_toggle')) {
    function bitmomo_render_menu_toggle() {
        printf(
            '<button class="bm-hamburger" id="bm-hamburger" type="button" aria-label="%s" aria-controls="bm-nav" aria-expanded="false"><span></span><span></span><span></span></button>',
            esc_attr__('Buka menu', 'bitmomo')
        );
    }
}

if (!function_exists('bitmomo_public_social_links')) {
    /** @return array<string,array{label:string,url:string}> */
    function bitmomo_public_social_links() {
        $links = array(
            'telegram' => array('label' => 'Telegram', 'url' => (string) apply_filters('bitmomo_telegram_url', 'https://t.me/bitmomodaily')),
            'youtube'  => array('label' => 'YouTube', 'url' => (string) apply_filters('bitmomo_youtube_url', 'https://www.youtube.com/@bitmomoid')),
            'x'        => array('label' => 'X', 'url' => (string) apply_filters('bitmomo_x_url', 'https://x.com/bitmomoid')),
        );
        foreach ($links as $key => $link) {
            $links[$key]['url'] = '' === trim((string) $link['url']) ? '' : esc_url_raw($link['url']);
        }
        return $links;
    }
}

if (!function_exists('bitmomo_normalize_public_page_body_headings')) {
    function bitmomo_normalize_public_page_body_headings($html) {
        $source = (string) $html;
        $normalized = preg_replace(array('/<h1(\b[^>]*)>/i', '/<\/h1\s*>/i'), array('<h2$1>', '</h2>'), $source);
        return is_string($normalized) ? $normalized : $source;
    }
}

if (!function_exists('bitmomo_research_taxonomy')) {
    /**
     * Canonical taxonomy IDs used by every public research surface.
     * Missing terms are represented by 0 so callers fail closed.
     *
     * @return array{riset_id:int,ai_lab_tag_id:int}
     */
    function bitmomo_research_taxonomy() {
        static $context = null;
        if (is_array($context)) return $context;

        $riset = get_category_by_slug('riset');
        $ai_lab = get_term_by('slug', 'ai-lab', 'post_tag');
        $context = array(
            'riset_id' => $riset ? (int) $riset->term_id : 0,
            'ai_lab_tag_id' => ($ai_lab && !is_wp_error($ai_lab)) ? (int) $ai_lab->term_id : 0,
        );
        return $context;
    }
}

if (!function_exists('bitmomo_post_research_lane')) {
    /** Return ai, market, or general using one taxonomy-aware rule. */
    function bitmomo_post_research_lane($post_id) {
        $post_id = (int) $post_id;
        if ($post_id < 1) return 'general';
        if (has_tag('ai-lab', $post_id)) return 'ai';

        $taxonomy = bitmomo_research_taxonomy();
        $riset_id = (int) $taxonomy['riset_id'];
        if ($riset_id < 1) return 'general';

        foreach ((array) get_the_category($post_id) as $category) {
            $category_id = (int) $category->term_id;
            if ($category_id === $riset_id || term_is_ancestor_of($riset_id, $category_id, 'category')) return 'market';
        }
        return 'general';
    }
}

if (!function_exists('bitmomo_research_classification_label')) {
    function bitmomo_research_classification_label($post_id) {
        $lane = bitmomo_post_research_lane($post_id);
        if ('ai' === $lane) return __('AI SYSTEMS RESEARCH', 'bitmomo');
        if ('market' === $lane) return __('CRYPTO MARKET RESEARCH', 'bitmomo');
        return __('BITMOMO RESEARCH', 'bitmomo');
    }
}

if (!function_exists('bitmomo_primary_public_category')) {
    /** Prefer a specific child category over the generic Riset parent. */
    function bitmomo_primary_public_category($post_id) {
        $categories = (array) get_the_category((int) $post_id);
        if (!$categories) return null;
        $taxonomy = bitmomo_research_taxonomy();
        $riset_id = (int) $taxonomy['riset_id'];
        foreach ($categories as $category) {
            if ((int) $category->term_id !== $riset_id) return $category;
        }
        return $categories[0];
    }
}

if (!function_exists('bitmomo_estimated_reading_minutes')) {
    /** Unicode-safe reading-time estimate; presentation only, never analytics. */
    function bitmomo_estimated_reading_minutes($post_id, $words_per_minute = 220) {
        $content = (string) get_post_field('post_content', (int) $post_id);
        $plain = trim(wp_strip_all_tags(strip_shortcodes($content)));
        if ('' === $plain) return 1;
        $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
        $count = is_array($words) ? count($words) : 0;
        return max(1, (int) ceil($count / max(1, (int) $words_per_minute)));
    }
}

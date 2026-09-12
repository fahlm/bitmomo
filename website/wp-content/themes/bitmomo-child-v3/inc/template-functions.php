<?php
/** Template rendering helpers. @package Bitmomo */

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
    /**
     * Canonical public social destinations.
     *
     * @return array<string,array{label:string,url:string}>
     */
    function bitmomo_public_social_links() {
        $links = array(
            'telegram' => array(
                'label' => 'Telegram',
                'url'   => (string) apply_filters('bitmomo_telegram_url', 'https://t.me/bitmomodaily'),
            ),
            'youtube' => array(
                'label' => 'YouTube',
                'url'   => (string) apply_filters('bitmomo_youtube_url', 'https://www.youtube.com/@bitmomoid'),
            ),
            'x' => array(
                'label' => 'X',
                'url'   => (string) apply_filters('bitmomo_x_url', 'https://x.com/bitmomoid'),
            ),
        );

        foreach ($links as $key => $link) {
            if ('' === trim((string) $link['url'])) {
                $links[$key]['url'] = '';
                continue;
            }
            $links[$key]['url'] = esc_url_raw($link['url']);
        }

        return $links;
    }
}

if (!function_exists('bitmomo_market_research_taxonomy_slugs')) {
    /**
     * Explicit vocabulary that qualifies a Riset post as Market Research.
     * Broad Riset membership alone is intentionally insufficient.
     *
     * @return string[]
     */
    function bitmomo_market_research_taxonomy_slugs() {
        return array(
            'bitcoin',
            'btc',
            'makro',
            'macro',
            'market-structure',
            'derivatives',
            'funding-rate',
            'etf',
            'liquidity',
            'likuiditas',
            'fundamental',
            'fundamentals',
        );
    }
}

if (!function_exists('bitmomo_post_is_market_research')) {
    /**
     * Market Research requires Riset + an explicit market taxonomy term and
     * must not carry the AI Systems tag.
     */
    function bitmomo_post_is_market_research($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id || !has_category('riset', $post_id) || has_tag('ai-lab', $post_id)) return false;

        foreach (bitmomo_market_research_taxonomy_slugs() as $slug) {
            if (has_category($slug, $post_id) || has_tag($slug, $post_id)) return true;
        }

        return false;
    }
}

if (!function_exists('bitmomo_post_is_ai_systems_research')) {
    /** AI Systems Research requires both Riset and the explicit ai-lab tag. */
    function bitmomo_post_is_ai_systems_research($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        return $post_id && has_category('riset', $post_id) && has_tag('ai-lab', $post_id);
    }
}

if (!function_exists('bitmomo_post_research_classification')) {
    /**
     * Canonical public classification. Never infer institutional research from
     * a generic category label or category ordering.
     *
     * @return string market|ai-systems|unclassified
     */
    function bitmomo_post_research_classification($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (bitmomo_post_is_market_research($post_id)) return 'market';
        if (bitmomo_post_is_ai_systems_research($post_id)) return 'ai-systems';
        return 'unclassified';
    }
}

if (!function_exists('bitmomo_normalize_public_page_body_headings')) {
    /** Keep the canonical ordinary-page title as the only H1. */
    function bitmomo_normalize_public_page_body_headings($html) {
        $source = (string) $html;
        $normalized = preg_replace(
            array('/<h1(\b[^>]*)>/i', '/<\/h1\s*>/i'),
            array('<h2$1>', '</h2>'),
            $source
        );

        return is_string($normalized) ? $normalized : $source;
    }
}

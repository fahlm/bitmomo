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
     * These are the official Bitmomo public channels. Filters are kept as a
     * narrow configuration seam so a future handle migration does not require
     * touching footer markup.
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
     * Merely belonging to the broad Riset category is intentionally not enough:
     * old general-AI/editorial content must not inherit institutional labels.
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
     * Market Research requires the Riset category, no AI Lab tag, and at
     * least one explicit market taxonomy term.
     *
     * @param int $post_id Post ID, defaults to the current post.
     * @return bool
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

if (!function_exists('bitmomo_normalize_public_page_body_headings')) {
    /**
     * Keep the canonical ordinary-page title as the only H1.
     *
     * Legacy WordPress/Elementor page bodies can retain their own H1 even
     * after the child theme takes ownership of the outer page template. On
     * ordinary pages, those body headings are content hierarchy and are
     * therefore demoted to H2. Product shortcode surfaces bypass this helper
     * and retain ownership of their own heading structure.
     *
     * @param string $html Rendered WordPress page-body HTML.
     * @return string
     */
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

/** Authority routes must never inherit old publisher-first SEO metadata. */
if (!function_exists('bitmomo_authority_seo_title')) {
    function bitmomo_authority_seo_title($title) {
        if (is_page('tentang-kami')) {
            return 'Tentang Bitmomo — Crypto Market & AI Systems Research';
        }
        if (is_category('riset')) {
            return 'Bitmomo Research — Crypto Markets & AI Systems';
        }
        return $title;
    }
}
add_filter('pre_get_document_title', 'bitmomo_authority_seo_title', 25);
add_filter('rank_math/frontend/title', 'bitmomo_authority_seo_title', 25);

if (!function_exists('bitmomo_authority_seo_description')) {
    function bitmomo_authority_seo_description($description) {
        if (is_page('tentang-kami')) {
            return 'Bitmomo adalah research & intelligence platform untuk crypto markets dan AI systems, dengan evidence, provenance, invalidation, dan accountability sebagai standar.';
        }
        if (is_category('riset')) {
            return 'Bitmomo Research menggabungkan crypto market research dan AI systems research untuk menghasilkan intelligence yang dapat ditelusuri, diuji, dan diperbaiki.';
        }
        return $description;
    }
}
add_filter('rank_math/frontend/description', 'bitmomo_authority_seo_description', 25);

if (!function_exists('bitmomo_render_authority_meta_fallback')) {
    function bitmomo_render_authority_meta_fallback() {
        if (defined('RANK_MATH_VERSION') || (!is_page('tentang-kami') && !is_category('riset'))) return;
        echo '<meta name="description" content="' . esc_attr(bitmomo_authority_seo_description('')) . '" />' . "\n";
    }
}
add_action('wp_head', 'bitmomo_render_authority_meta_fallback', 2);

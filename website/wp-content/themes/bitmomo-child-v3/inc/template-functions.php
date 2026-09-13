<?php
/** Template rendering helpers. @package Bitmomo */

if (!defined('ABSPATH')) exit;

if (!function_exists('bitmomo_render_brand')) {
    function bitmomo_render_brand() {
        $logo_url = get_site_icon_url(96);
        $mark = $logo_url
            ? sprintf('<img class="bm-brand-mark" src="%s" width="48" height="48" alt="" decoding="async">', esc_url($logo_url))
            : '<span class="bm-brand-mark-fallback" aria-hidden="true">b</span>';

        printf(
            '<div class="bm-brand"><a class="bm-brand-logo" href="%s" aria-label="%s">%s<span class="bm-brand-name">bitmomo</span></a></div>',
            esc_url(home_url('/')),
            esc_attr__('Bitmomo home', 'bitmomo'),
            $mark
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
     * Public finance surfaces fail closed: a channel is rendered only when an
     * HTTPS destination is explicitly configured via wp-config constant or the
     * matching filter. There are deliberately no hard-coded public fallbacks.
     *
     * @return array<string,array{label:string,url:string}>
     */
    function bitmomo_public_social_links() {
        $definitions = array(
            'telegram' => array(
                'label'    => 'Telegram',
                'constant' => 'BITMOMO_TELEGRAM_URL',
                'filter'   => 'bitmomo_telegram_url',
            ),
            'youtube' => array(
                'label'    => 'YouTube',
                'constant' => 'BITMOMO_YOUTUBE_URL',
                'filter'   => 'bitmomo_youtube_url',
            ),
            'x' => array(
                'label'    => 'X',
                'constant' => 'BITMOMO_X_URL',
                'filter'   => 'bitmomo_x_url',
            ),
        );
        $links = array();

        foreach ($definitions as $key => $definition) {
            $configured = defined($definition['constant']) ? (string) constant($definition['constant']) : '';
            $candidate = trim((string) apply_filters($definition['filter'], $configured));
            if ('' === $candidate) continue;

            $url = esc_url_raw($candidate, array('https'));
            if ('' === $url || 0 !== stripos($url, 'https://') || !wp_http_validate_url($url)) continue;

            $links[$key] = array(
                'label' => $definition['label'],
                'url'   => $url,
            );
        }

        return $links;
    }
}

if (!function_exists('bitmomo_market_research_taxonomy_slugs')) {
    function bitmomo_market_research_taxonomy_slugs() {
        return array(
            'bitcoin', 'btc', 'makro', 'macro', 'market-structure', 'derivatives',
            'funding-rate', 'etf', 'liquidity', 'likuiditas', 'fundamental', 'fundamentals',
        );
    }
}

if (!function_exists('bitmomo_post_is_market_research')) {
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
    function bitmomo_post_is_ai_systems_research($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        return $post_id && has_category('riset', $post_id) && has_tag('ai-lab', $post_id);
    }
}

if (!function_exists('bitmomo_post_research_classification')) {
    function bitmomo_post_research_classification($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (bitmomo_post_is_market_research($post_id)) return 'market';
        if (bitmomo_post_is_ai_systems_research($post_id)) return 'ai-systems';
        return 'unclassified';
    }
}

if (!function_exists('bitmomo_post_publication_label')) {
    function bitmomo_post_publication_label($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        $classification = bitmomo_post_research_classification($post_id);
        if ('market' === $classification) return 'MARKET RESEARCH';
        if ('ai-systems' === $classification) return 'INTELLIGENCE SYSTEMS RESEARCH';
        $categories = get_the_category($post_id);
        foreach ($categories as $category) {
            if ('riset' === $category->slug) continue;
            return strtoupper((string) $category->name);
        }
        return 'PUBLIKASI';
    }
}

if (!function_exists('bitmomo_research_focus_filters')) {
    function bitmomo_research_focus_filters() {
        return array(
            'all' => array('label' => 'All Research', 'discipline' => 'all', 'terms' => array()),
            'bitcoin' => array('label' => 'Bitcoin', 'discipline' => 'market', 'terms' => array('bitcoin', 'btc')),
            'macro' => array('label' => 'Macro', 'discipline' => 'market', 'terms' => array('macro', 'makro')),
            'market-structure' => array('label' => 'Market Structure', 'discipline' => 'market', 'terms' => array('market-structure')),
            'derivatives' => array('label' => 'Derivatives', 'discipline' => 'market', 'terms' => array('derivatives', 'funding-rate')),
            'flows' => array('label' => 'ETF & Flows', 'discipline' => 'market', 'terms' => array('etf')),
            'liquidity' => array('label' => 'Liquidity', 'discipline' => 'market', 'terms' => array('liquidity', 'likuiditas')),
            'systems' => array('label' => 'Intelligence Systems', 'discipline' => 'ai-systems', 'terms' => array('ai-lab')),
        );
    }
}

if (!function_exists('bitmomo_post_matches_research_focus')) {
    function bitmomo_post_matches_research_focus($post_id, $focus) {
        $post_id = (int) $post_id;
        $filters = bitmomo_research_focus_filters();
        $focus = isset($filters[$focus]) ? (string) $focus : 'all';
        $classification = bitmomo_post_research_classification($post_id);
        if ('unclassified' === $classification) return false;
        if ('all' === $focus) return true;
        $filter = $filters[$focus];
        if ('ai-systems' === $filter['discipline']) return 'ai-systems' === $classification;
        if ('market' !== $classification) return false;
        foreach ($filter['terms'] as $slug) {
            if (has_category($slug, $post_id) || has_tag($slug, $post_id)) return true;
        }
        return false;
    }
}

if (!function_exists('bitmomo_post_research_topic_label')) {
    function bitmomo_post_research_topic_label($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        $classification = bitmomo_post_research_classification($post_id);
        if ('ai-systems' === $classification) return 'Intelligence Systems';
        if ('market' !== $classification) return 'Research';
        $priority = array(
            'etf' => 'ETF & Flows', 'funding-rate' => 'Derivatives', 'derivatives' => 'Derivatives',
            'market-structure' => 'Market Structure', 'macro' => 'Macro', 'makro' => 'Macro',
            'liquidity' => 'Liquidity', 'likuiditas' => 'Liquidity', 'fundamental' => 'Fundamentals',
            'fundamentals' => 'Fundamentals', 'bitcoin' => 'Bitcoin', 'btc' => 'Bitcoin',
        );
        foreach ($priority as $slug => $label) {
            if (has_category($slug, $post_id) || has_tag($slug, $post_id)) return $label;
        }
        return 'Market Research';
    }
}

if (!function_exists('bitmomo_post_reading_minutes')) {
    function bitmomo_post_reading_minutes($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return 1;
        $content = (string) get_post_field('post_content', $post_id);
        $words = str_word_count(wp_strip_all_tags(strip_shortcodes($content)));
        return max(1, (int) ceil($words / 220));
    }
}

if (!function_exists('bitmomo_post_manual_deck')) {
    function bitmomo_post_manual_deck($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return '';
        $excerpt = trim((string) get_post_field('post_excerpt', $post_id));
        if ('' === $excerpt) return '';
        return trim(wp_strip_all_tags(strip_shortcodes($excerpt)));
    }
}

if (!function_exists('bitmomo_post_has_meaningful_update')) {
    function bitmomo_post_has_meaningful_update($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return false;
        $published = (int) get_post_time('U', true, $post_id);
        $modified  = (int) get_post_modified_time('U', true, $post_id);
        return $published > 0 && $modified > ($published + DAY_IN_SECONDS);
    }
}

if (!function_exists('bitmomo_normalize_public_page_body_headings')) {
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

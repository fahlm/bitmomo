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
     * Explicit taxonomy vocabulary that qualifies a Riset post as market
     * research. "Riset but not ai-lab" is intentionally NOT enough: legacy
     * general-AI/editorial posts must never be mislabeled as market analysis.
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
     * A post is Market Research only when it is in the Riset category,
     * is not an AI Lab publication, and has at least one explicit market
     * category/tag from the canonical vocabulary above.
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

if (!function_exists('bitmomo_public_runtime_fingerprint')) {
    function bitmomo_public_runtime_fingerprint() {
        static $fingerprint = null;
        if (null !== $fingerprint) return $fingerprint;

        $fingerprint = '';
        $theme_root = get_stylesheet_directory();
        $contract_path = $theme_root . '/data/runtime-fingerprint.json';
        if (!is_file($contract_path) || !is_readable($contract_path)) return $fingerprint;

        $contract = json_decode((string) file_get_contents($contract_path), true);
        $files = is_array($contract) && isset($contract['files']) && is_array($contract['files'])
            ? array_values(array_unique(array_filter(array_map('strval', $contract['files']))))
            : array();
        if (!$files) return $fingerprint;

        sort($files, SORT_STRING);
        $context = hash_init('sha256');
        foreach ($files as $relative) {
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            if ('' === $relative || str_contains($relative, '../')) return $fingerprint;

            $full_path = $theme_root . '/' . $relative;
            if (!is_file($full_path) || !is_readable($full_path)) return $fingerprint;

            $digest = hash_file('sha256', $full_path);
            if (!is_string($digest) || '' === $digest) return $fingerprint;
            hash_update($context, $relative . "\0" . $digest . "\0");
        }

        $fingerprint = hash_final($context);
        return $fingerprint;
    }
}

if (!function_exists('bitmomo_ajax_public_runtime_fingerprint')) {
    function bitmomo_ajax_public_runtime_fingerprint() {
        nocache_headers();
        $fingerprint = bitmomo_public_runtime_fingerprint();
        if ('' === $fingerprint) {
            wp_send_json_error(array('status' => 'unavailable'), 503);
        }
        wp_send_json_success(array(
            'status'      => 'ok',
            'fingerprint' => $fingerprint,
        ));
    }
    add_action('wp_ajax_nopriv_bitmomo_runtime_fingerprint', 'bitmomo_ajax_public_runtime_fingerprint');
    add_action('wp_ajax_bitmomo_runtime_fingerprint', 'bitmomo_ajax_public_runtime_fingerprint');
}

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

if (!function_exists('bitmomo_public_runtime_fingerprint')) {
    /**
     * Byte-level identity for launch-critical public theme files.
     *
     * A human-maintained version string is insufficient for source/runtime
     * parity because multiple commits can share the same version. The file
     * set lives in data/runtime-fingerprint.json and the fingerprint is the
     * SHA-256 of each sorted relative path plus that file's SHA-256 digest.
     * Missing or malformed inputs fail closed by returning an empty value.
     *
     * @return string 64-character SHA-256 or an empty string on failure.
     */
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
    /** Public, read-only identity endpoint for release parity checks. */
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

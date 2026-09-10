<?php
/**
 * Bitmomo Speed Theme Functions
 * Version: 2.0
 */

if (!defined('ABSPATH')) exit;

// Constants
define('BM_THEME_VERSION', '2.0');
define('BM_THEME_URI', get_stylesheet_directory_uri());
define('BM_THEME_PATH', get_stylesheet_directory());

/**
 * Theme Setup
 */
function bitmomo_speed_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list',
        'gallery', 'caption', 'style', 'script'
    ]);
    add_theme_support('responsive-embeds');
    add_theme_support('wp-block-styles');
    add_theme_support('align-wide');

    register_nav_menus([
        'primary' => 'Primary Navigation',
        'footer'  => 'Footer Navigation',
    ]);

    // Image sizes
    add_image_size('bm-card', 800, 450, true);
    add_image_size('bm-hero', 1200, 600, true);

    global $content_width;
    $content_width = 1120;
}
add_action('after_setup_theme', 'bitmomo_speed_setup');

/**
 * Enqueue Styles
 */
function bitmomo_speed_scripts() {
    wp_enqueue_style('bitmomo-style', get_stylesheet_uri(), [], BM_THEME_VERSION);
}
add_action('wp_enqueue_scripts', 'bitmomo_speed_scripts');

/**
 * Remove WP Bloat
 */
function bitmomo_remove_bloat() {
    // Emoji
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');

    // Misc head
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
}
add_action('init', 'bitmomo_remove_bloat');

/**
 * Optimize Images inside content
 */
function bitmomo_optimize_images($content) {
    if (!is_main_query() || !in_the_loop()) return $content;

    // Add lazy by default
    $content = preg_replace(
        '/<img(?![^>]+loading=)/i',
        '<img loading="lazy" decoding="async" ',
        $content
    );

    // First image eager
    static $first = false;
    if (!$first && is_singular()) {
        $content = preg_replace(
            '/<img([^>]*?)loading="lazy"([^>]*?)>/i',
            '<img$1loading="eager" fetchpriority="high"$2>',
            $content,
            1
        );
        $first = true;
    }
    return $content;
}
add_filter('the_content', 'bitmomo_optimize_images', 10);

/**
 * Optimize Featured Images
 */
function bitmomo_optimize_featured_image($html, $post_id, $attachment_id, $size, $attr) {
    $meta = wp_get_attachment_metadata($attachment_id);
    if ($meta && isset($meta['width'], $meta['height'])) {
        if (!str_contains($html, 'width=')) {
            $html = str_replace('<img ', '<img width="' . $meta['width'] . '" height="' . $meta['height'] . '" ', $html);
        }
    }

    if (is_home() || is_archive()) {
        static $first_card = true;
        if ($first_card) {
            $html = str_replace('<img ', '<img loading="eager" fetchpriority="high" ', $html);
            $first_card = false;
        } else {
            $html = str_replace('<img ', '<img loading="lazy" decoding="async" ', $html);
        }
    } else {
        $html = str_replace('<img ', '<img loading="eager" fetchpriority="high" ', $html);
    }

    return $html;
}
add_filter('post_thumbnail_html', 'bitmomo_optimize_featured_image', 10, 5);

/**
 * Resource Hints
 */
function bitmomo_resource_hints() {
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
    echo '<meta name="theme-color" content="#0c1c2a">' . "\n";
}
add_action('wp_head', 'bitmomo_resource_hints', 1);

/**
 * Helper: Reading Time
 */
function bm_reading_time($post_id = null) {
    $post = get_post($post_id);
    if (!$post) return 1;
    $words = str_word_count(strip_tags($post->post_content));
    return max(1, ceil($words / 200));
}

/**
 * Helper: Excerpt
 */
function bm_get_excerpt($post_id = null, $length = 25) {
    $post = get_post($post_id);
    if (!$post) return '';
    $source = $post->post_excerpt ?: strip_tags($post->post_content);
    return wp_trim_words($source, $length, '...');
}

/**
 * Helper: Thumbnail
 */
function bm_get_thumbnail($post_id = null, $size = 'bm-card', $attrs = []) {
    if (!has_post_thumbnail($post_id)) {
        return '<div class="bm-placeholder" style="aspect-ratio:16/9;background:#0a1d2a;display:flex;align-items:center;justify-content:center;color:#666;font-size:14px;">No Image</div>';
    }
    $default = ['class' => 'bm-thumbnail', 'loading' => 'lazy', 'decoding' => 'async'];
    return get_the_post_thumbnail($post_id, $size, array_merge($default, $attrs));
}

/**
 * Fallback Menu
 */
function bm_fallback_menu() {
    echo '<ul class="bm-nav">';
    echo '<li><a href="' . home_url() . '">Home</a></li>';
    echo '<li><a href="' . home_url('/about') . '">About</a></li>';
    echo '<li><a href="' . home_url('/contact') . '">Contact</a></li>';
    echo '</ul>';
}

/**
 * Navigation Walker
 */
class BM_Nav_Walker extends Walker_Nav_Menu {
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $class_names = $classes ? ' class="' . esc_attr(join(' ', $classes)) . '"' : '';
        $attrs = !empty($item->url) ? ' href="' . esc_attr($item->url) . '"' : '';
        $output .= '<li' . $class_names . '><a' . $attrs . '>' . apply_filters('the_title', $item->title, $item->ID) . '</a>';
    }
    public function end_el(&$output, $item, $depth = 0, $args = null) {
        $output .= '</li>';
    }
}

/**
 * Optimize Archive Queries
 */
function bitmomo_optimize_queries($query) {
    if (!is_admin() && $query->is_main_query()) {
        if ($query->is_category() || $query->is_tag()) {
            $query->set('posts_per_page', 12);
        }
    }
}
add_action('pre_get_posts', 'bitmomo_optimize_queries');

/**
 * Excerpt length + more
 */
add_filter('excerpt_length', fn() => 25);
add_filter('excerpt_more', fn() => '...');

/**
 * Debug Perf (dev only)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        $ms = round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2);
        $mem = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        echo "\n<!-- Bitmomo Perf: {$ms}ms | {$mem}MB -->\n";
    }, 999);
}

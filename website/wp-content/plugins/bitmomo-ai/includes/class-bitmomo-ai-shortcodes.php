<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Shortcodes {
    public static function register() {
        add_shortcode('bitmomo_market_insights', [__CLASS__, 'insights']);
        add_shortcode('bitmomo_bitcoin_signal', [__CLASS__, 'signal']);
    }

    public static function insights($atts) {
        $atts = shortcode_atts(['limit' => 3], $atts, 'bitmomo_market_insights');
        $query = new WP_Query([
            'post_type' => Bitmomo_AI_Content_Types::INSIGHT,
            'post_status' => 'publish',
            'posts_per_page' => min(12, max(1, absint($atts['limit']))),
            'no_found_rows' => true,
        ]);
        ob_start();
        echo '<section class="bm-ai-list" aria-label="' . esc_attr__('AI Market Insights', 'bitmomo-ai') . '">';
        while ($query->have_posts()) { $query->the_post();
            printf('<article class="bm-ai-card"><h3><a href="%s">%s</a></h3><p>%s</p><time datetime="%s">%s</time></article>', esc_url(get_permalink()), esc_html(get_the_title()), esc_html(get_the_excerpt()), esc_attr(get_the_date('c')), esc_html(get_the_date()));
        }
        echo '</section>';
        wp_reset_postdata();
        return ob_get_clean();
    }

    public static function signal() {
        $posts = get_posts(['post_type' => Bitmomo_AI_Content_Types::SIGNAL, 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true]);
        if (!$posts) return '';
        $post = $posts[0];
        $direction = get_post_meta($post->ID, '_bm_direction', true) ?: 'neutral';
        $confidence = min(100, max(0, absint(get_post_meta($post->ID, '_bm_confidence', true))));
        $timeframe = get_post_meta($post->ID, '_bm_timeframe', true);
        return sprintf('<section class="bm-signal bm-signal--%1$s"><p class="bm-signal-label">%2$s</p><h3><a href="%3$s">%4$s</a></h3><dl><div><dt>%5$s</dt><dd>%6$s</dd></div><div><dt>%7$s</dt><dd>%8$d%%</dd></div><div><dt>%9$s</dt><dd>%10$s</dd></div></dl><p class="bm-signal-disclaimer">%11$s</p></section>', esc_attr($direction), esc_html__('Latest Bitcoin Signal', 'bitmomo-ai'), esc_url(get_permalink($post)), esc_html(get_the_title($post)), esc_html__('Direction', 'bitmomo-ai'), esc_html(ucfirst($direction)), esc_html__('Confidence', 'bitmomo-ai'), $confidence, esc_html__('Timeframe', 'bitmomo-ai'), esc_html($timeframe ?: '—'), esc_html__('Educational information only; not financial advice.', 'bitmomo-ai'));
    }
}

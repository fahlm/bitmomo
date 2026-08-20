<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Content_Types {
    const INSIGHT = 'bm_ai_insight';
    const SIGNAL  = 'bm_btc_signal';
    const NONCE   = 'bitmomo_ai_signal_meta';

    public static function register() {
        register_post_type(self::INSIGHT, [
            'labels' => ['name' => __('AI Market Insights', 'bitmomo-ai'), 'singular_name' => __('AI Market Insight', 'bitmomo-ai')],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-chart-line',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'market-insight'],
        ]);

        register_post_type(self::SIGNAL, [
            'labels' => ['name' => __('Bitcoin Signals', 'bitmomo-ai'), 'singular_name' => __('Bitcoin Signal', 'bitmomo-ai')],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-chart-area',
            'supports' => ['title', 'editor', 'excerpt', 'author', 'revisions'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'bitcoin-signal'],
        ]);

        foreach (['direction', 'confidence', 'timeframe', 'market_price', 'generated_at', 'model'] as $key) {
            register_post_meta(self::SIGNAL, '_bm_' . $key, [
                'single' => true,
                'type' => in_array($key, ['confidence'], true) ? 'integer' : 'string',
                'show_in_rest' => true,
                'auth_callback' => function () { return current_user_can('edit_posts'); },
                'sanitize_callback' => $key === 'confidence' ? 'absint' : 'sanitize_text_field',
            ]);
        }
    }

    public static function add_meta_boxes() {
        add_meta_box('bitmomo-ai-signal', __('Signal Details', 'bitmomo-ai'), [__CLASS__, 'render_signal_box'], self::SIGNAL, 'normal', 'high');
    }

    public static function render_signal_box($post) {
        wp_nonce_field(self::NONCE, self::NONCE);
        $fields = [
            'direction' => __('Direction (bullish, neutral, bearish)', 'bitmomo-ai'),
            'confidence' => __('Confidence (0–100)', 'bitmomo-ai'),
            'timeframe' => __('Timeframe', 'bitmomo-ai'),
            'market_price' => __('Market price snapshot', 'bitmomo-ai'),
            'generated_at' => __('Generated at (UTC)', 'bitmomo-ai'),
            'model' => __('Model/provider', 'bitmomo-ai'),
        ];
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_bm_' . $key, true);
            printf('<p><label for="bm-%1$s"><strong>%2$s</strong></label><br><input class="widefat" id="bm-%1$s" name="bm_%1$s" value="%3$s"></p>', esc_attr($key), esc_html($label), esc_attr($value));
        }
        echo '<p><em>' . esc_html__('Signals remain drafts until an editor reviews and publishes them.', 'bitmomo-ai') . '</em></p>';
    }

    public static function save_meta($post_id) {
        if (get_post_type($post_id) !== self::SIGNAL) return;
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach (['direction', 'confidence', 'timeframe', 'market_price', 'generated_at', 'model'] as $key) {
            if (!isset($_POST['bm_' . $key])) continue;
            $value = sanitize_text_field(wp_unslash($_POST['bm_' . $key]));
            if ($key === 'confidence') $value = min(100, max(0, absint($value)));
            if ($key === 'direction' && !in_array($value, ['bullish', 'neutral', 'bearish'], true)) $value = 'neutral';
            update_post_meta($post_id, '_bm_' . $key, $value);
        }
    }
}

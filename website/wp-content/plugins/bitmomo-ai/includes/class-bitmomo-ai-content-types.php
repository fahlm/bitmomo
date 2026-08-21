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
        $outcome_status = get_post_meta($post->ID, '_bm_outcome_status', true);
        if ($outcome_status === 'evaluated') {
            $result = get_post_meta($post->ID, '_bm_outcome_direction', true);
            $return = (float) get_post_meta($post->ID, '_bm_outcome_return_pct', true);
            echo '<hr><p><strong>' . esc_html__('24-hour validation:', 'bitmomo-ai') . '</strong> ' . esc_html(sprintf('%s — return %.2f%%', $result, $return)) . '</p>';
        } elseif ($outcome_status === 'pending') {
            echo '<hr><p><strong>' . esc_html__('24-hour validation:', 'bitmomo-ai') . '</strong> ' . esc_html__('pending', 'bitmomo-ai') . '</p>';
        }
        $release = Bitmomo_AI_Editorial_Gate::status($post->ID);
        $levels_complete = self::levels_complete($post->ID);
        $age_label = $release['age_minutes'] === null
            ? __('Waktu data tidak valid', 'bitmomo-ai')
            : sprintf(__('%d menit sejak dibuat', 'bitmomo-ai'), $release['age_minutes']);
        if ($release['fresh']) {
            $age_label .= ' · ' . sprintf(__('berlaku %d menit lagi', 'bitmomo-ai'), $release['expires_in_minutes']);
        }

        echo '<hr><div class="bm-release-gate">';
        echo '<h3>' . esc_html__('Checklist sebelum rilis', 'bitmomo-ai') . '</h3>';
        echo '<p><span class="bm-release-state ' . esc_attr($release['ready'] ? 'is-ready' : 'is-waiting') . '">' . esc_html($release['ready'] ? __('SIAP DIRILIS', 'bitmomo-ai') : __('BELUM SIAP DIRILIS', 'bitmomo-ai')) . '</span></p>';
        self::render_check_item(
            $release['quality_passed'],
            __('Pemeriksaan otomatis', 'bitmomo-ai'),
            $release['quality_passed'] ? __('Semua pemeriksaan kualitas lulus.', 'bitmomo-ai') : __('Perbarui analisis sampai quality gate lulus.', 'bitmomo-ai')
        );
        self::render_check_item(
            $release['fresh'],
            __('Kesegaran data', 'bitmomo-ai'),
            $age_label,
            $release['near_expiry']
        );
        self::render_check_item(
            $levels_complete,
            __('Level harga', 'bitmomo-ai'),
            $levels_complete ? __('Support, resistance, dan batas risiko tersedia.', 'bitmomo-ai') : __('Satu atau lebih level harga belum tersedia.', 'bitmomo-ai')
        );
        self::render_check_item(
            $release['approved'],
            __('Pemeriksaan editor', 'bitmomo-ai'),
            $release['approved'] ? __('Sudah dikonfirmasi.', 'bitmomo-ai') : __('Menunggu konfirmasi editor.', 'bitmomo-ai')
        );
        if ($release['near_expiry']) {
            echo '<p class="bm-release-warning"><strong>' . esc_html__('Perhatian:', 'bitmomo-ai') . '</strong> ' . esc_html__('Data akan segera kedaluwarsa. Periksa kembali harga terkini sebelum merilis.', 'bitmomo-ai') . '</p>';
        }
        if ($release['reasons']) echo '<p class="bm-release-reasons">' . esc_html(implode(' ', $release['reasons'])) . '</p>';
        echo '<p class="bm-release-confirm"><label><input type="checkbox" name="bm_editor_approved" value="yes" ' . checked($release['approved'], true, false) . '> <strong>' . esc_html__('Saya telah memeriksa kesimpulan, support, resistance, risiko, dan sumber data.', 'bitmomo-ai') . '</strong></label></p>';
        echo '<p><em>' . esc_html__('Setelah dicentang, simpan draft terlebih dahulu. Publikasi tetap dilakukan secara manual.', 'bitmomo-ai') . '</em></p>';
        echo '</div>';
    }

    private static function levels_complete($post_id) {
        foreach (['_bm_support_low', '_bm_support_high', '_bm_resistance_low', '_bm_resistance_high', '_bm_risk_level'] as $key) {
            if ((float) get_post_meta($post_id, $key, true) <= 0) return false;
        }
        return true;
    }

    private static function render_check_item($passed, $title, $description, $warning = false) {
        $class = $passed ? ($warning ? 'is-warning' : 'is-passed') : 'is-failed';
        $icon = $passed ? ($warning ? '!' : '✓') : '×';
        printf(
            '<div class="bm-release-check %1$s"><span class="bm-release-icon">%2$s</span><div><strong>%3$s</strong><br><span>%4$s</span></div></div>',
            esc_attr($class),
            esc_html($icon),
            esc_html($title),
            esc_html($description)
        );
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
        update_post_meta($post_id, '_bm_editor_approved', isset($_POST['bm_editor_approved']) && sanitize_text_field(wp_unslash($_POST['bm_editor_approved'])) === 'yes' ? 'yes' : 'no');
    }
}

<?php
/**
 * Plugin Name: Bitmomo AI
 * Description: Editorial foundation for AI Market Insight and Bitcoin Signal.
 * Version: 1.0.27
 * Author: Bitmomo
 * Text Domain: bitmomo-ai
 */

if (!defined('ABSPATH')) exit;

define('BITMOMO_AI_VERSION', '1.0.27');
define('BITMOMO_AI_FILE', __FILE__);
define('BITMOMO_AI_DIR', plugin_dir_path(__FILE__));
define('BITMOMO_AI_URL', plugin_dir_url(__FILE__));

/**
 * Canonical access layer for public and paid intelligence projections.
 *
 * The validated plugin preview is the single source of truth. Theme JSON is
 * intentionally not consulted here: manual files may be used as archives, but
 * must never be presented silently as current market intelligence.
 */
final class Bitmomo_AI_Intelligence {
    const FRESH_AGE_SECONDS = 6 * HOUR_IN_SECONDS;
    const DELAYED_AGE_SECONDS = 30 * HOUR_IN_SECONDS;

    public static function free_projection() {
        $source = self::validated_source();
        if (!$source) return self::unavailable_projection();

        $timestamp = $source['timestamp'];
        $age = max(0, time() - $timestamp);
        if ($age > self::DELAYED_AGE_SECONDS) return self::unavailable_projection();

        $data = $source['data'];
        $evaluation = $source['evaluation'];
        $bias = sanitize_key((string) ($evaluation['bias'] ?? 'neutral'));
        if (!in_array($bias, ['bullish', 'neutral', 'bearish'], true)) $bias = 'neutral';

        $state = $age <= self::FRESH_AGE_SECONDS ? 'fresh' : 'delayed';

        return [
            'status' => $state,
            'price' => (float) ($data['close'] ?? 0),
            'bias' => $bias,
            'market_state' => self::market_state_label($bias),
            'confidence' => min(100, max(0, (int) ($evaluation['confidence'] ?? 0))),
            'primary_driver' => self::primary_driver($evaluation),
            'timestamp' => $timestamp,
            'timestamp_iso' => gmdate('c', $timestamp),
            'freshness_label' => self::freshness_label($timestamp, $state),
        ];
    }

    /**
     * Paid projection. It fails closed until an entitlement provider opts in.
     * Nothing in the public homepage or public REST API calls this method.
     */
    public static function pro_projection() {
        if (!(bool) apply_filters('bitmomo_ai_pro_entitled', false)) return null;

        $source = self::validated_source();
        if (!$source) return null;

        $evaluation = $source['evaluation'];
        $risk = is_array($evaluation['risk'] ?? null) ? $evaluation['risk'] : [];

        return [
            'timestamp' => $source['timestamp'],
            'score' => (int) ($evaluation['score'] ?? 0),
            'axes' => is_array($evaluation['axes'] ?? null) ? $evaluation['axes'] : [],
            'support_zone' => [
                'low' => (float) ($risk['support_zone_low'] ?? 0),
                'high' => (float) ($risk['support_zone_high'] ?? 0),
            ],
            'resistance_zone' => [
                'low' => (float) ($risk['resistance_zone_low'] ?? 0),
                'high' => (float) ($risk['resistance_zone_high'] ?? 0),
            ],
            'invalidation' => (float) ($risk['invalidation'] ?? 0),
            'risk' => $risk,
        ];
    }

    private static function validated_source() {
        $preview = get_option('bitmomo_ai_latest_preview', []);
        $gate = get_option('bitmomo_ai_latest_quality_gate', []);
        if (!is_array($preview) || !is_array($gate)) return null;
        if (!in_array((string) ($gate['status'] ?? ''), ['passed', 'degraded'], true)) return null;
        if (empty($preview['data']) || empty($preview['evaluation'])) return null;

        $data = is_array($preview['data']) ? $preview['data'] : [];
        $evaluation = is_array($preview['evaluation']) ? $preview['evaluation'] : [];
        $quality = is_array($evaluation['quality'] ?? null) ? $evaluation['quality'] : [];
        if (!in_array((string) ($quality['status'] ?? ''), ['complete', 'degraded'], true)) return null;

        $timestamp = strtotime((string) ($preview['time'] ?? ($data['timestamp'] ?? '')));
        if (!$timestamp || $timestamp > time() + (5 * MINUTE_IN_SECONDS)) return null;
        if ((float) ($data['close'] ?? 0) <= 0) return null;

        return [
            'data' => $data,
            'evaluation' => $evaluation,
            'timestamp' => $timestamp,
        ];
    }

    private static function unavailable_projection() {
        return [
            'status' => 'unavailable',
            'message' => __('Update BTC terbaru belum tersedia.', 'bitmomo-ai'),
            'detail' => __('Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo-ai'),
        ];
    }

    private static function market_state_label($bias) {
        $labels = [
            'bullish' => __('Bullish', 'bitmomo-ai'),
            'bearish' => __('Bearish', 'bitmomo-ai'),
            'neutral' => __('Netral', 'bitmomo-ai'),
        ];
        return $labels[$bias] ?? $labels['neutral'];
    }

    private static function primary_driver(array $evaluation) {
        $axes = is_array($evaluation['axes'] ?? null) ? $evaluation['axes'] : [];
        $scores = [];
        foreach (['direction', 'structure', 'carry', 'crowding', 'volatility'] as $name) {
            $scores[$name] = abs((int) ($axes[$name]['score'] ?? 0));
        }
        arsort($scores);
        $primary = (string) array_key_first($scores);
        $score = (int) ($axes[$primary]['score'] ?? 0);

        if ($primary === 'volatility') {
            return __('Volatilitas menjadi faktor paling dominan; pergerakan harga dapat berubah lebih cepat.', 'bitmomo-ai');
        }
        if ($primary === 'structure') {
            return $score > 0
                ? __('Struktur harga menjadi pendorong utama dan saat ini cenderung menguat.', 'bitmomo-ai')
                : ($score < 0
                    ? __('Struktur harga menjadi pendorong utama dan saat ini cenderung melemah.', 'bitmomo-ai')
                    : __('Struktur harga masih berada dalam rentang dan belum memberi arah yang kuat.', 'bitmomo-ai'));
        }
        if ($primary === 'carry') {
            return __('Kondisi funding dan basis futures menjadi faktor utama dalam pembacaan pasar saat ini.', 'bitmomo-ai');
        }
        if ($primary === 'crowding') {
            return __('Perubahan posisi pelaku pasar menjadi faktor utama dalam pembacaan saat ini.', 'bitmomo-ai');
        }
        return $score > 0
            ? __('Momentum empat jam menjadi pendorong utama dan masih mendukung arah naik.', 'bitmomo-ai')
            : ($score < 0
                ? __('Momentum empat jam menjadi pendorong utama dan masih menekan arah pasar.', 'bitmomo-ai')
                : __('Momentum empat jam belum cukup kuat untuk memberi arah yang tegas.', 'bitmomo-ai'));
    }

    private static function freshness_label($timestamp, $state) {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $local = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        $now = new DateTimeImmutable('now', $timezone);
        $time = $local->format('H:i') . ' WIB';

        if ($state === 'fresh' && $local->format('Y-m-d') === $now->format('Y-m-d')) {
            return sprintf(__('Diperbarui hari ini, %s', 'bitmomo-ai'), $time);
        }

        $age = human_time_diff($timestamp, time());
        return $state === 'delayed'
            ? sprintf(__('Tertunda · diperbarui %s lalu', 'bitmomo-ai'), $age)
            : sprintf(__('Diperbarui %s lalu, %s', 'bitmomo-ai'), $age, $time);
    }
}

require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-content-types.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-shortcodes.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-signal-engine.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-quality-gate.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-performance.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-editorial-gate.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-admin-notices.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-report.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-webhook.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-binance.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-scheduler.php';

final class Bitmomo_AI_Plugin {
    public static function boot() {
        add_action('init', ['Bitmomo_AI_Content_Types', 'register']);
        add_action('add_meta_boxes', ['Bitmomo_AI_Content_Types', 'add_meta_boxes']);
        add_action('save_post', ['Bitmomo_AI_Content_Types', 'save_meta']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        Bitmomo_AI_Webhook::register();
        Bitmomo_AI_Scheduler::register();
        Bitmomo_AI_Shortcodes::register();
        Bitmomo_AI_Editorial_Gate::register();
        Bitmomo_AI_Admin_Notices::register();
    }

    public static function enqueue_assets() {
        if (!is_singular() && !is_front_page() && !is_home()) return;
        wp_enqueue_style('bitmomo-ai', BITMOMO_AI_URL . 'assets/css/frontend.css', [], BITMOMO_AI_VERSION);
        wp_enqueue_style('bitmomo-ai-dashboard-layout', BITMOMO_AI_URL . 'assets/css/dashboard-layout.css', ['bitmomo-ai'], BITMOMO_AI_VERSION);
        wp_enqueue_style('bitmomo-ai-audience-dashboard', BITMOMO_AI_URL . 'assets/css/audience-dashboard.css', ['bitmomo-ai-dashboard-layout'], BITMOMO_AI_VERSION);
        wp_enqueue_style('bitmomo-ai-brief-dashboard', BITMOMO_AI_URL . 'assets/css/brief-dashboard.css', ['bitmomo-ai-audience-dashboard'], BITMOMO_AI_VERSION);
    }

    public static function enqueue_admin_assets($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== Bitmomo_AI_Content_Types::SIGNAL) return;
        wp_enqueue_style('bitmomo-ai-admin', BITMOMO_AI_URL . 'assets/css/admin.css', [], BITMOMO_AI_VERSION);
    }
}

Bitmomo_AI_Plugin::boot();

register_activation_hook(__FILE__, function () {
    Bitmomo_AI_Content_Types::register();
    Bitmomo_AI_Scheduler::schedule();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    Bitmomo_AI_Scheduler::unschedule();
    flush_rewrite_rules();
});

<?php
/**
 * Plugin Name: Bitmomo AI
 * Description: Editorial foundation for AI Market Insight and Bitcoin Signal.
 * Version: 1.3.0
 * Author: Bitmomo
 * Text Domain: bitmomo-ai
 */

if (!defined('ABSPATH')) exit;

define('BITMOMO_AI_VERSION', '1.3.0');
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
        $direction_strength = sanitize_key((string) ($evaluation['direction_strength'] ?? ''));
        if (!in_array($direction_strength, ['strong_bearish', 'bearish', 'neutral', 'bullish', 'strong_bullish'], true)) {
            $direction_strength = Bitmomo_AI_Signal_Engine::direction_strength((float) ($evaluation['score'] ?? 0));
        }

        return [
            'status' => $state,
            'price' => (float) ($data['close'] ?? 0),
            'bias' => $bias,
            'direction_strength' => $direction_strength,
            'market_state' => self::market_state_label($bias),
            'confidence' => min(100, max(0, (int) ($evaluation['confidence'] ?? 0))),
            'primary_driver' => self::primary_driver($evaluation),
            'key_drivers' => self::key_drivers($evaluation),
            'timestamp' => $timestamp,
            'timestamp_iso' => gmdate('c', $timestamp),
            'freshness_label' => self::freshness_label($timestamp, $state),
            'latest_attempt' => self::public_attempt(),
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
        if (self::source_is_stale($source)) return null;

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
            'edition' => $source['edition'],
            'source_record_id' => $source['source_record_id'],
            'comparison_source_record_id' => $source['comparison_source_record_id'],
        ];
    }

    public static function regime_projection() {
        $source = self::validated_source();
        if (!$source || empty($source['data']['regime_metrics'])) return null;
        if (self::source_is_stale($source)) return null;
        $evaluation = $source['evaluation'];
        $axes = is_array($evaluation['axes'] ?? null) ? $evaluation['axes'] : [];
        $source_timestamp = strtotime((string) ($source['data']['quality']['last_closed_candle'] ?? ($source['data']['timestamp'] ?? ''))) ?: (int) $source['timestamp'];
        $analysis_date = wp_date('Y-m-d', $source_timestamp, new DateTimeZone('Asia/Jakarta'));
        return [
            'source_record_id' => 'bitmomo-ai:regime:' . $analysis_date . ':' . $source['edition'],
            'timestamp_iso' => gmdate('c', $source_timestamp),
            'edition' => $source['edition'],
            'provenance' => 'recorded_live',
            'metrics' => $source['data']['regime_metrics'],
            'directional_bias' => (string) ($evaluation['bias'] ?? 'neutral'),
            'directional_confidence' => (float) ($evaluation['confidence'] ?? 0),
            'direction_score' => (float) ($axes['direction']['score'] ?? 0),
            'open_interest_change_pct' => $axes['crowding']['oi_change_24h_pct'] ?? null,
            'funding_rate' => $axes['carry']['funding_rate'] ?? null,
            'basis_pct' => $axes['carry']['basis_pct'] ?? null,
        ];
    }

    private static function validated_source() {
        $preview = class_exists('Bitmomo_AI_Runtime_State') ? Bitmomo_AI_Runtime_State::latest_valid_snapshot() : get_option('bitmomo_ai_latest_preview', []);
        if (!is_array($preview)) return null;
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
            'edition' => in_array(($preview['edition'] ?? ''), ['morning', 'us_session'], true) ? $preview['edition'] : 'us_session',
            'source_record_id' => sanitize_text_field((string) ($preview['source_record_id'] ?? '')),
            'comparison_source_record_id' => sanitize_text_field((string) ($preview['comparison_source_record_id'] ?? '')),
        ];
    }

    private static function public_attempt() {
        if (!class_exists('Bitmomo_AI_Runtime_State')) return [];
        $attempt = Bitmomo_AI_Runtime_State::latest_attempt();
        if (!$attempt) return [];
        return [
            'attempted_at' => sanitize_text_field((string) ($attempt['attempted_at'] ?? '')),
            'edition' => sanitize_key((string) ($attempt['edition'] ?? '')),
            'status' => sanitize_key((string) ($attempt['status'] ?? 'unknown')),
            'quality_status' => sanitize_key((string) ($attempt['quality_gate']['status'] ?? 'unknown')),
        ];
    }

    private static function source_is_stale(array $source) {
        return (time() - (int) ($source['timestamp'] ?? 0)) > self::DELAYED_AGE_SECONDS;
    }

    private static function unavailable_projection() {
        return [
            'status' => 'unavailable',
            'message' => __('Update BTC terbaru belum tersedia.', 'bitmomo-ai'),
            'detail' => __('Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo-ai'),
            'latest_attempt' => self::public_attempt(),
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

    /**
     * Dynamic "Faktor Utama" / Key Drivers list — the customer-facing
     * replacement for the single primary_driver() sentence above.
     * primary_driver() is kept byte-for-byte unchanged for backward
     * compatibility with any existing consumer of that singular field;
     * this is purely an additive field on the same free_projection() shape.
     *
     * All selection/ranking/copy logic lives in Bitmomo_AI_Key_Drivers so it
     * can be unit-tested in isolation from this canonical access layer.
     *
     * @return string[] 1 to Bitmomo_AI_Key_Drivers::MAX_DRIVERS sentences.
     */
    private static function key_drivers(array $evaluation) {
        return Bitmomo_AI_Key_Drivers::derive($evaluation);
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
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-key-drivers.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-quality-gate.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-runtime-state.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-performance.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-scorecard.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-public-intelligence-adapter.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-editorial-gate.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-admin-notices.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-report.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-webhook.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-binance.php';
require_once BITMOMO_AI_DIR . 'includes/class-bitmomo-ai-regime-metrics.php';
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

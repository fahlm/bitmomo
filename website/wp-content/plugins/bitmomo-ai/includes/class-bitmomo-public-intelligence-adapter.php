<?php
if (!defined('ABSPATH')) exit;

/** Narrow, fail-closed public contract over canonical intelligence stores. */
final class Bitmomo_Public_Intelligence_Adapter {
    const HISTORY_LIMIT = 30;
    const PUBLIC_DISPLAY_TIMEZONE = 'Asia/Jakarta';

    public static function snapshot() {
        if (!class_exists('Bitmomo_AI_Intelligence')) return null;
        $projection = Bitmomo_AI_Intelligence::free_projection();
        if (!is_array($projection) || !in_array(($projection['status'] ?? ''), ['fresh', 'delayed'], true)) return null;

        $regime = self::latest_regime();
        $strength = self::strength_or_null($projection['direction_strength'] ?? null);
        $bias = self::bias_or_null($projection['bias'] ?? null);
        $public_source = sanitize_text_field((string) ($projection['source'] ?? ''));
        $as_of = sanitize_text_field((string) ($projection['timestamp_iso'] ?? ''));
        if ($bias === null || $strength === null || $public_source === '' || $as_of === '') return null;
        $session_intelligence = is_array($projection['session_intelligence'] ?? null) ? $projection['session_intelligence'] : [];
        if (is_array($session_intelligence['current_setup'] ?? null)) {
            $session_intelligence['current_setup']['market_state'] = self::regime_or_null($regime['regime'] ?? null);
        }
        $opportunity = class_exists('Bitmomo_AI_Opportunity_Store')
            ? Bitmomo_AI_Opportunity_Store::public_latest()
            : ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];

        return [
            'status' => (string) $projection['status'],
            'btc_reference_price' => (float) ($projection['price'] ?? 0),
            'opportunity' => $opportunity,
            'market_state' => self::regime_or_null($regime['regime'] ?? null),
            'directional_bias' => $bias,
            'direction_strength' => $strength,
            'confidence' => [
                'value' => min(100, max(0, (int) ($projection['confidence'] ?? 0))),
                'label' => self::confidence_label($projection['confidence'] ?? 0),
            ],
            'freshness' => [
                'state' => (string) $projection['status'],
                'label' => (string) ($projection['freshness_label'] ?? ''),
                'timestamp' => (int) ($projection['timestamp'] ?? 0),
                'timestamp_iso' => $as_of,
            ],
            'provenance' => [
                'source' => $public_source,
                'as_of' => $as_of,
                'timezone' => self::PUBLIC_DISPLAY_TIMEZONE,
            ],
            'latest_attempt' => is_array($projection['latest_attempt'] ?? null) ? $projection['latest_attempt'] : [],
            'key_drivers' => self::public_drivers($projection['key_drivers'] ?? []),
            'session' => [
                'edition_id' => sanitize_text_field((string) ($projection['edition_id'] ?? '')),
                'type' => Bitmomo_AI_Session_Intelligence::normalize_session_type($projection['session_type'] ?? ''),
                'label' => sanitize_text_field((string) ($projection['session_label'] ?? '')),
                'anchor' => sanitize_text_field((string) ($projection['session_anchor'] ?? '')),
                'market_timezone' => Bitmomo_AI_Session_Intelligence::MARKET_TIMEZONE,
                'us_market_status' => sanitize_key((string) ($projection['us_market_status'] ?? 'regular_session_day')),
            ],
            'session_intelligence' => $session_intelligence,
            'versions' => [
                'engine' => defined('BITMOMO_AI_VERSION') ? BITMOMO_AI_VERSION : 'unknown',
                'classifier' => self::version_or_unknown($regime['classifier_version'] ?? ''),
                'opportunity' => (string) ($opportunity['methodology_version'] ?? 'opportunity-v1'),
            ],
        ];
    }

    /** Public-safe shell for rendering honest unavailable/partial surfaces. */
    public static function surface_context() {
        $opportunity = class_exists('Bitmomo_AI_Opportunity_Store')
            ? Bitmomo_AI_Opportunity_Store::public_latest()
            : ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];
        $projection = class_exists('Bitmomo_AI_Intelligence')
            ? Bitmomo_AI_Intelligence::free_projection()
            : [];
        $source = is_array($projection) ? sanitize_text_field((string) ($projection['source'] ?? '')) : '';
        $as_of = is_array($projection) ? sanitize_text_field((string) ($projection['timestamp_iso'] ?? '')) : '';

        return [
            'opportunity' => $opportunity,
            'provenance' => [
                'source' => $source !== '' ? $source : null,
                'as_of' => $as_of !== '' && strtotime($as_of) ? $as_of : null,
                'timezone' => self::PUBLIC_DISPLAY_TIMEZONE,
            ],
        ];
    }

    public static function history() {
        if (!class_exists('Bitmomo_Regime_State_Store')) return self::empty_history();
        $records = Bitmomo_Regime_State_Store::instance()->get_recent(self::HISTORY_LIMIT * 2);
        if (!is_array($records)) return self::empty_history();

        $official = [];
        foreach ($records as $record) {
            if (!is_array($record) || ($record['provenance'] ?? '') !== 'recorded_live') continue;
            $date = substr((string) ($record['as_of'] ?? $record['date'] ?? ''), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            if (!isset($official[$date]) || (($record['edition'] ?? '') === 'us_session' && ($official[$date]['edition'] ?? '') !== 'us_session')) {
                $official[$date] = $record;
            }
        }
        ksort($official);
        $official = array_slice($official, -self::HISTORY_LIMIT, null, true);

        $days = [];
        foreach ($official as $date => $record) {
            $bias = self::bias_or_null($record['directional_bias'] ?? null);
            $regime = self::regime_or_null($record['regime'] ?? null);
            if ($bias === null || $regime === null) continue;
            $day = [
                'date' => $date,
                'market_state' => $regime,
                'market_state_certainty' => min(100, max(0, (int) ($record['regime_confidence'] ?? 0))),
                'directional_bias' => $bias,
                'version_group' => self::version_or_unknown($record['classifier_version'] ?? ''),
            ];
            $strength = self::strength_or_null($record['direction_strength'] ?? null);
            if ($strength !== null) $day['direction_strength'] = $strength;
            $days[] = $day;
        }

        return ['target_days' => self::HISTORY_LIMIT, 'available_days' => count($days), 'days' => $days];
    }

    public static function evaluation_summary() {
        if (!class_exists('Bitmomo_AI_Scorecard_Repository')) return null;
        $scorecard = Bitmomo_AI_Scorecard_Repository::build();
        if (!is_array($scorecard)) return null;

        $versions = [];
        foreach ((array) ($scorecard['versions'] ?? []) as $version => $metrics) {
            if (!is_array($metrics)) continue;
            $versions[self::version_or_unknown($version)] = [
                'all' => self::metric($metrics['all'] ?? []),
                'rolling_30' => self::metric($metrics['rolling_30'] ?? []),
                'by_direction' => self::metric_map($metrics['by_direction'] ?? []),
                'confidence_buckets' => array_values(array_map([__CLASS__, 'metric'], (array) ($metrics['confidence_calibration'] ?? []))),
            ];
        }

        $range_versions = [];
        foreach ((array) ($scorecard['expected_range']['versions'] ?? []) as $version => $metric) {
            $range_versions[self::version_or_unknown($version)] = self::metric($metric);
        }

        return [
            'provenance' => 'canonical_evaluation_scorecard',
            'version_policy' => (string) ($scorecard['version_policy'] ?? 'SINGLE_VERSION'),
            'sample_rules' => array_map('intval', (array) ($scorecard['sample_rules'] ?? [])),
            'directional_evaluation' => $versions,
            'expected_range_evaluation' => [
                'policy' => (string) ($scorecard['expected_range']['policy'] ?? 'FROZEN_ORIGINAL_ONLY'),
                'version_policy' => (string) ($scorecard['expected_range']['version_policy'] ?? 'SINGLE_VERSION'),
                'versions' => $range_versions,
            ],
            'regime_performance' => self::regime_metrics($scorecard['regime_evaluation'] ?? []),
            'data_quality' => self::metric($scorecard['data_quality'] ?? []),
        ];
    }

    private static function latest_regime() {
        if (!class_exists('Bitmomo_Regime_State_Store')) return [];
        $record = Bitmomo_Regime_State_Store::instance()->get_latest();
        return is_array($record) && ($record['provenance'] ?? '') === 'recorded_live' ? $record : [];
    }

    private static function empty_history() { return ['target_days' => self::HISTORY_LIMIT, 'available_days' => 0, 'days' => []]; }
    private static function bias_or_null($value) { $value = sanitize_key((string) $value); return in_array($value, ['bearish', 'neutral', 'bullish'], true) ? $value : null; }
    private static function strength_or_null($value) { $value = sanitize_key((string) $value); return in_array($value, ['strong_bearish', 'bearish', 'neutral', 'bullish', 'strong_bullish'], true) ? $value : null; }
    private static function regime_or_null($value) { $value = sanitize_key((string) $value); return $value === '' ? null : $value; }
    private static function version_or_unknown($value) { $value = sanitize_text_field((string) $value); return trim($value) === '' ? 'unknown' : $value; }
    private static function confidence_label($value) { $value = min(100, max(0, (int) $value)); return $value >= 70 ? 'high' : ($value >= 40 ? 'medium' : 'low'); }
    private static function public_drivers($drivers) { return array_slice(array_values(array_filter(array_map('sanitize_text_field', is_array($drivers) ? $drivers : []))), 0, 5); }

    private static function metric($row) {
        if (!is_array($row)) return ['n' => 0, 'sample_status' => 'INSUFFICIENT SAMPLE'];
        $allowed = ['n', 'conclusive_n', 'correct', 'incorrect', 'inconclusive', 'accuracy_pct', 'range', 'range_hit_pct', 'low_breach_pct', 'high_breach_pct', 'average_width_pct', 'average_forward_return_pct', 'average_forward_volatility_pct', 'stale_rate_pct', 'blocked_degraded_rate_pct', 'missing_data_rate_pct', 'settlement_n', 'settlement_completeness_pct', 'sample_status'];
        return array_intersect_key($row, array_flip($allowed));
    }

    private static function metric_map($rows) {
        $out = [];
        foreach ((array) $rows as $key => $row) $out[sanitize_key((string) $key) ?: 'unknown'] = self::metric($row);
        return $out;
    }

    private static function regime_metrics($evaluation) {
        $evaluation = is_array($evaluation) ? $evaluation : [];
        $versions = [];
        foreach ((array) ($evaluation['versions'] ?? []) as $version => $rows) $versions[self::version_or_unknown($version)] = self::metric_map($rows);
        return [
            'append_only_n' => (int) ($evaluation['append_only_n'] ?? 0),
            'transition_n' => (int) ($evaluation['transition_n'] ?? 0),
            'transition_frequency_pct' => isset($evaluation['transition_frequency_pct']) ? (float) $evaluation['transition_frequency_pct'] : null,
            'versions' => $versions,
        ];
    }
}

<?php
if (!defined('ABSPATH')) exit;

/** Narrow, fail-closed public contract over canonical intelligence stores. */
final class Bitmomo_Public_Intelligence_Adapter {
    const HISTORY_LIMIT = 30;
    const PUBLIC_DISPLAY_TIMEZONE = 'Asia/Jakarta';
    const ALLOWED_REGIMES = ['accumulation', 'expansion', 'distribution', 'capitulation', 'transition'];
    const OPPORTUNITY_FRESH_SECONDS = 15 * MINUTE_IN_SECONDS;
    const OPPORTUNITY_MAX_SECONDS = 30 * MINUTE_IN_SECONDS;
    const MAJOR_BRIEF_GRACE_SECONDS = 20 * MINUTE_IN_SECONDS;

    public static function snapshot() {
        if (!class_exists('Bitmomo_AI_Intelligence')) return null;
        $projection = Bitmomo_AI_Intelligence::free_projection();
        if (!is_array($projection) || !in_array(($projection['status'] ?? ''), ['fresh', 'delayed'], true)) return null;
        if (!class_exists('Bitmomo_AI_Session_Intelligence')) return null;

        $raw_session_type = $projection['session_type'] ?? '';
        if (!Bitmomo_AI_Session_Intelligence::is_supported_session_type($raw_session_type)) return null;
        $session_type = Bitmomo_AI_Session_Intelligence::normalize_session_type($raw_session_type);

        // Major Brief freshness follows the next expected session anchor rather
        // than an arbitrary wall-clock age. A valid Pre-Open brief remains the
        // current brief until Post-Close is due (+grace), and vice versa.
        $status = self::major_brief_status($projection);
        $is_fresh = 'fresh' === $status;
        $canonical_source_id = sanitize_text_field((string) ($projection['edition_id'] ?? ''));
        $regime = self::regime_for_source($canonical_source_id);
        $strength = self::strength_or_null($projection['direction_strength'] ?? null);
        $bias = self::bias_or_null($projection['bias'] ?? null);
        $public_source = sanitize_text_field((string) ($projection['source'] ?? ''));
        $as_of = sanitize_text_field((string) ($projection['timestamp_iso'] ?? ''));
        $as_of_timestamp = strtotime($as_of);
        $price = $projection['price'] ?? null;
        $confidence = $projection['confidence'] ?? null;
        if (
            $bias === null
            || $strength === null
            || $public_source === ''
            || $as_of === ''
            || !$as_of_timestamp
            || $as_of_timestamp > time() + (5 * MINUTE_IN_SECONDS)
            || $canonical_source_id === ''
            || !is_numeric($price)
            || (float) $price <= 0
            || !is_numeric($confidence)
        ) return null;

        $market_state = $is_fresh ? self::regime_or_null($regime['regime'] ?? null) : null;
        $market_state_certainty = $is_fresh && $market_state !== null && isset($regime['regime_confidence'])
            ? min(100, max(0, (int) $regime['regime_confidence']))
            : null;

        $session_intelligence = $is_fresh && is_array($projection['session_intelligence'] ?? null)
            ? $projection['session_intelligence']
            : [];
        if ($is_fresh && is_array($session_intelligence['current_setup'] ?? null)) {
            $session_intelligence['current_setup']['market_state'] = $market_state;
        }

        // Slow-clock lineage stays frozen with the Major Brief. The top-level
        // Opportunity is a separate fast-clock public state and is only exposed
        // through the store's own freshness gate. This keeps two clocks honest.
        $canonical_opportunity = $is_fresh
            ? self::canonical_opportunity($session_intelligence['opportunity'] ?? null)
            : ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];
        if ($is_fresh) {
            $session_intelligence['opportunity'] = $canonical_opportunity;
        }
        $latest_opportunity = class_exists('Bitmomo_AI_Opportunity_Store')
            ? Bitmomo_AI_Opportunity_Store::public_latest()
            : ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];
        $opportunity = self::surface_opportunity($latest_opportunity);

        return [
            'status' => $status,
            // A delayed Major Brief may retain provenance/reference price, while
            // its directional assessment fails closed. Market Pulse remains an
            // independent fast clock: fresh <=15m, delayed 15-30m, unavailable >30m.
            'btc_reference_price' => (float) $price,
            'opportunity' => $opportunity,
            'market_state' => $market_state,
            'market_state_certainty' => $market_state_certainty,
            'directional_bias' => $is_fresh ? $bias : null,
            'direction_strength' => $is_fresh ? $strength : null,
            'confidence' => $is_fresh
                ? [
                    'value' => min(100, max(0, (int) $confidence)),
                    'label' => self::confidence_label($confidence),
                ]
                : [
                    'value' => null,
                    'label' => '',
                ],
            'freshness' => [
                'state' => $status,
                'label' => self::major_brief_freshness_label($projection, $status),
                'timestamp' => (int) ($projection['timestamp'] ?? 0),
                'timestamp_iso' => $as_of,
            ],
            'provenance' => [
                'source' => $public_source,
                'as_of' => $as_of,
                'timezone' => self::PUBLIC_DISPLAY_TIMEZONE,
            ],
            'latest_attempt' => is_array($projection['latest_attempt'] ?? null) ? $projection['latest_attempt'] : [],
            'key_drivers' => $is_fresh ? self::public_drivers($projection['key_drivers'] ?? []) : [],
            'session' => [
                'edition_id' => sanitize_text_field((string) ($projection['edition_id'] ?? '')),
                'type' => $session_type,
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

    public static function surface_context() {
        $latest_opportunity = class_exists('Bitmomo_AI_Opportunity_Store')
            ? Bitmomo_AI_Opportunity_Store::public_latest()
            : ['status' => 'unavailable', 'methodology_version' => 'opportunity-v1'];
        $opportunity = self::surface_opportunity($latest_opportunity);
        $projection = class_exists('Bitmomo_AI_Intelligence') ? Bitmomo_AI_Intelligence::free_projection() : [];
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
            $date = class_exists('Bitmomo_Regime_History') && method_exists('Bitmomo_Regime_History', 'market_date')
                ? Bitmomo_Regime_History::market_date($record)
                : substr((string) ($record['as_of'] ?? $record['date'] ?? ''), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            $edition = (string) ($record['edition'] ?? '');
            $existing_edition = isset($official[$date]) ? (string) ($official[$date]['edition'] ?? '') : '';
            $is_us_session = in_array($edition, ['us_session', 'us_post_close'], true);
            $existing_is_us_session = in_array($existing_edition, ['us_session', 'us_post_close'], true);
            if (!isset($official[$date]) || ($is_us_session && !$existing_is_us_session)) $official[$date] = $record;
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
                'latest_generated_at' => sanitize_text_field((string) ($metrics['latest_generated_at'] ?? '')),
                'outcome_methodology' => self::version_or_unknown($metrics['outcome_methodology'] ?? ''),
                'all' => self::metric($metrics['all'] ?? []),
                'rolling_30' => self::metric($metrics['rolling_30'] ?? []),
                'by_direction' => self::metric_map($metrics['by_direction'] ?? []),
                'confidence_buckets' => array_values(array_map([__CLASS__, 'metric'], (array) ($metrics['confidence_calibration'] ?? []))),
            ];
        }

        $range_versions = [];
        foreach ((array) ($scorecard['expected_range']['versions'] ?? []) as $version => $metric) $range_versions[self::version_or_unknown($version)] = self::metric($metric);

        return [
            'provenance' => 'canonical_evaluation_scorecard',
            'version_policy' => (string) ($scorecard['version_policy'] ?? 'SINGLE_VERSION'),
            'sample_rules' => array_map('intval', (array) ($scorecard['sample_rules'] ?? [])),
            'directional_evaluation' => $versions,
            'expected_range_evaluation' => [
                'policy' => (string) ($scorecard['expected_range']['policy'] ?? 'FROZEN_VERSIONED_ORIGINAL_ONLY'),
                'version_policy' => (string) ($scorecard['expected_range']['version_policy'] ?? 'SINGLE_VERSION'),
                'versions' => $range_versions,
            ],
            'regime_performance' => self::regime_metrics($scorecard['regime_evaluation'] ?? []),
            'data_quality' => self::metric($scorecard['data_quality'] ?? []),
        ];
    }

    private static function regime_for_source($source_record_id) {
        $source_record_id = sanitize_text_field((string) $source_record_id);
        if ($source_record_id === '' || !class_exists('Bitmomo_Regime_State_Store')) return [];
        $records = Bitmomo_Regime_State_Store::instance()->get_recent(self::HISTORY_LIMIT * 2);
        foreach ((array) $records as $record) {
            if (!is_array($record) || ($record['provenance'] ?? '') !== 'recorded_live') continue;
            if (hash_equals($source_record_id, (string) ($record['source_record_id'] ?? ''))) return $record;
        }
        return [];
    }

    private static function major_brief_status(array $projection) {
        $fallback = sanitize_key((string) ($projection['status'] ?? 'delayed'));
        $raw_session_type = $projection['session_type'] ?? '';
        $anchor_raw = trim((string) ($projection['session_anchor'] ?? ''));
        if (
            $anchor_raw === ''
            || !class_exists('Bitmomo_AI_Session_Intelligence')
            || !Bitmomo_AI_Session_Intelligence::is_supported_session_type($raw_session_type)
        ) return in_array($fallback, ['fresh', 'delayed'], true) ? $fallback : 'delayed';

        try {
            $anchor = new DateTimeImmutable($anchor_raw);
            $next_type = Bitmomo_AI_Session_Intelligence::opposite($raw_session_type);
            $next_anchor = Bitmomo_AI_Session_Intelligence::next_anchor($next_type, $anchor->modify('+1 minute'));
            $deadline = $next_anchor->modify('+' . self::MAJOR_BRIEF_GRACE_SECONDS . ' seconds');
            return time() <= $deadline->getTimestamp() ? 'fresh' : 'delayed';
        } catch (Exception $exception) {
            unset($exception);
            return in_array($fallback, ['fresh', 'delayed'], true) ? $fallback : 'delayed';
        }
    }

    private static function major_brief_freshness_label(array $projection, $status) {
        $timestamp = (int) ($projection['timestamp'] ?? 0);
        if (!$timestamp) $timestamp = strtotime((string) ($projection['timestamp_iso'] ?? '')) ?: 0;
        if (!$timestamp) return 'fresh' === $status ? 'Major Brief aktif' : 'Major Brief tertunda';

        $age = function_exists('human_time_diff') ? human_time_diff($timestamp, time()) : '';
        if ('fresh' === $status) {
            return $age !== '' ? sprintf(__('Major Brief aktif · diterbitkan %s lalu', 'bitmomo-ai'), $age) : __('Major Brief aktif', 'bitmomo-ai');
        }
        return $age !== '' ? sprintf(__('Major Brief tertunda · brief terakhir %s lalu', 'bitmomo-ai'), $age) : __('Major Brief tertunda', 'bitmomo-ai');
    }

    private static function empty_history() { return ['target_days' => self::HISTORY_LIMIT, 'available_days' => 0, 'days' => []]; }
    private static function bias_or_null($value) { $value = sanitize_key((string) $value); return in_array($value, ['bearish', 'neutral', 'bullish'], true) ? $value : null; }
    private static function strength_or_null($value) { $value = sanitize_key((string) $value); return in_array($value, ['strong_bearish', 'bearish', 'neutral', 'bullish', 'strong_bullish'], true) ? $value : null; }
    private static function regime_or_null($value) { $value = sanitize_key((string) $value); return in_array($value, self::ALLOWED_REGIMES, true) ? $value : null; }
    private static function version_or_unknown($value) { $value = sanitize_text_field((string) $value); return trim($value) === '' ? 'unknown' : $value; }
    private static function confidence_label($value) { $value = min(100, max(0, (int) $value)); return $value >= 70 ? 'high' : ($value >= 40 ? 'medium' : 'low'); }
    private static function public_drivers($drivers) { return array_slice(array_values(array_filter(array_map('sanitize_text_field', is_array($drivers) ? $drivers : []))), 0, 5); }

    private static function canonical_opportunity($opportunity) {
        $methodology = class_exists('Bitmomo_AI_Opportunity') ? Bitmomo_AI_Opportunity::METHODOLOGY_VERSION : 'opportunity-v1';
        if (!is_array($opportunity) || ($opportunity['status'] ?? '') !== 'available') {
            return ['status' => 'unavailable', 'methodology_version' => $methodology];
        }
        $state = strtoupper(sanitize_key((string) ($opportunity['state'] ?? '')));
        $knowledge_time = sanitize_text_field((string) ($opportunity['knowledge_time'] ?? ''));
        if (!in_array($state, ['HIGH', 'NORMAL', 'LOW'], true) || !strtotime($knowledge_time)) {
            return ['status' => 'unavailable', 'methodology_version' => $methodology];
        }
        return [
            'status' => 'available',
            'state' => $state,
            'methodology_version' => sanitize_text_field((string) ($opportunity['methodology_version'] ?? $methodology)),
            'knowledge_time' => $knowledge_time,
            'changed' => !empty($opportunity['changed']),
        ];
    }

    private static function surface_opportunity($opportunity) {
        $methodology = class_exists('Bitmomo_AI_Opportunity') ? Bitmomo_AI_Opportunity::METHODOLOGY_VERSION : 'opportunity-v1';
        if (!is_array($opportunity) || ($opportunity['status'] ?? '') !== 'available') {
            return ['status' => 'unavailable', 'methodology_version' => $methodology];
        }
        $state = strtoupper(sanitize_key((string) ($opportunity['state'] ?? '')));
        $knowledge_time = sanitize_text_field((string) ($opportunity['knowledge_time'] ?? ''));
        $knowledge_timestamp = strtotime($knowledge_time) ?: 0;
        if (!in_array($state, ['HIGH', 'NORMAL', 'LOW'], true) || !$knowledge_timestamp) {
            return ['status' => 'unavailable', 'methodology_version' => $methodology];
        }

        $age_seconds = max(0, time() - $knowledge_timestamp);
        if ($age_seconds > self::OPPORTUNITY_MAX_SECONDS) {
            return ['status' => 'unavailable', 'methodology_version' => $methodology];
        }

        return [
            'status' => 'available',
            'state' => $state,
            'methodology_version' => sanitize_text_field((string) ($opportunity['methodology_version'] ?? $methodology)),
            'freshness_state' => $age_seconds <= self::OPPORTUNITY_FRESH_SECONDS ? 'fresh' : 'delayed',
            'age_seconds' => $age_seconds,
            'changed' => !empty($opportunity['changed']),
        ];
    }

    private static function metric($row) {
        if (!is_array($row)) return ['n' => 0, 'sample_status' => 'INSUFFICIENT SAMPLE'];
        $allowed = ['n', 'conclusive_n', 'correct', 'incorrect', 'inconclusive', 'accuracy_pct', 'range', 'range_hit_pct', 'low_breach_pct', 'high_breach_pct', 'average_width_pct', 'average_forward_return_pct', 'average_forward_volatility_pct', 'stale_rate_pct', 'blocked_degraded_rate_pct', 'missing_data_rate_pct', 'settlement_n', 'settlement_evaluated_n', 'settlement_missed_n', 'settlement_pending_n', 'settlement_completeness_pct', 'sample_status'];
        $metric = array_intersect_key($row, array_flip($allowed));
        if (($metric['sample_status'] ?? '') === 'INSUFFICIENT SAMPLE') unset($metric['accuracy_pct']);
        return $metric;
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
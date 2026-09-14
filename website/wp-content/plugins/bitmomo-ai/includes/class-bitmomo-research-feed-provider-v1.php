<?php
if (!defined('ABSPATH')) exit;

/**
 * Read-only provider that bridges current canonical BTC runtime state into
 * Research Feed V1. It has no hooks, persistence, network calls, or publishing.
 */
final class Bitmomo_Research_Feed_Provider_V1 {
    const PROVIDER_VERSION = 'research-feed-provider-v1';
    const MAX_LINEAGE_DRIFT_SECONDS = 1;

    /**
     * Project the current canonical BTC intelligence into Research Feed V1.
     *
     * The existing Bitmomo_AI_Intelligence::free_projection() remains the
     * validation boundary for the public-safe market snapshot. Runtime state is
     * consulted only for canonical quality/source lineage and must match the
     * projection timestamp, price, and directional bias exactly enough to prove
     * both reads refer to the same snapshot.
     */
    public static function current_btc($now = null) {
        $now = self::now($now);

        if (!class_exists('Bitmomo_Research_Feed_V1')) {
            return self::failure(['research_feed_v1_unavailable']);
        }
        if (!class_exists('Bitmomo_AI_Intelligence') || !method_exists('Bitmomo_AI_Intelligence', 'free_projection')) {
            return self::failure(['btc_intelligence_dependency_unavailable']);
        }
        if (!class_exists('Bitmomo_AI_Runtime_State') || !method_exists('Bitmomo_AI_Runtime_State', 'latest_valid_snapshot')) {
            return self::failure(['btc_runtime_state_dependency_unavailable']);
        }

        $projection = Bitmomo_AI_Intelligence::free_projection();
        if (!is_array($projection) || !in_array(self::key($projection['status'] ?? ''), ['fresh', 'delayed'], true)) {
            return self::failure(['btc_intelligence_unavailable']);
        }

        $snapshot = Bitmomo_AI_Runtime_State::latest_valid_snapshot();
        if (!is_array($snapshot) || empty($snapshot['data']) || empty($snapshot['evaluation'])) {
            return self::failure(['canonical_snapshot_unavailable']);
        }

        $projection_ts = self::timestamp($projection['timestamp_iso'] ?? ($projection['timestamp'] ?? null));
        $snapshot_ts = self::snapshot_timestamp($snapshot);
        if (!$projection_ts || !$snapshot_ts || abs($projection_ts - $snapshot_ts) > self::MAX_LINEAGE_DRIFT_SECONDS) {
            return self::failure(['canonical_lineage_timestamp_mismatch']);
        }

        $projection_price = self::number($projection['price'] ?? null);
        $snapshot_price = self::number($snapshot['data']['close'] ?? null);
        if ($projection_price === null || $projection_price <= 0 || $snapshot_price === null || $snapshot_price <= 0) {
            return self::failure(['canonical_lineage_price_invalid']);
        }
        $price_tolerance = max(0.00000001, abs($snapshot_price) * 0.0000000001);
        if (abs($projection_price - $snapshot_price) > $price_tolerance) {
            return self::failure(['canonical_lineage_price_mismatch']);
        }

        $projection_bias = self::key($projection['bias'] ?? '');
        $snapshot_bias = self::key($snapshot['evaluation']['bias'] ?? '');
        if (!in_array($projection_bias, ['bullish', 'neutral', 'bearish'], true) || $projection_bias !== $snapshot_bias) {
            return self::failure(['canonical_lineage_bias_mismatch']);
        }

        $quality = self::key($snapshot['evaluation']['quality']['status'] ?? '');
        if (!in_array($quality, ['complete', 'degraded'], true)) {
            return self::failure(['canonical_quality_status_invalid']);
        }

        $producer = self::text($projection['source'] ?? '');
        if ($producer === '') {
            return self::failure(['canonical_provenance_source_missing']);
        }

        $source_record_id = self::text($snapshot['source_record_id'] ?? '');
        if ($source_record_id === '') {
            $source_record_id = 'bitmomo-ai:' . $snapshot_ts;
        }

        $opportunity = is_array($snapshot['opportunity'] ?? null) ? $snapshot['opportunity'] : [];
        if (($opportunity['status'] ?? '') !== 'available') $opportunity = [];

        $source = [
            'status' => self::key($projection['status'] ?? ''),
            'quality_status' => $quality,
            'btc_reference_price' => $projection_price,
            'directional_bias' => $projection_bias,
            'direction_strength' => self::key($projection['direction_strength'] ?? ''),
            'confidence' => ['value' => $projection['confidence'] ?? null],
            'key_drivers' => is_array($projection['key_drivers'] ?? null) ? $projection['key_drivers'] : [],
            'opportunity' => $opportunity,
            'source_record_id' => $source_record_id,
            'provenance' => [
                'source' => $producer,
                'as_of' => gmdate('c', $projection_ts),
            ],
            'versions' => [
                'engine' => defined('BITMOMO_AI_VERSION') ? BITMOMO_AI_VERSION : 'unknown',
                'opportunity' => self::text($opportunity['methodology_version'] ?? ''),
            ],
            'topics' => ['bitcoin', 'market-intelligence'],
            'tags' => ['canonical-runtime'],
        ];

        return Bitmomo_Research_Feed_V1::from_btc_intelligence($source, $now);
    }

    /**
     * Build one read-only feed snapshot from current BTC intelligence plus
     * explicitly supplied manual/original research records.
     */
    public static function build(array $manual_sources = [], $now = null) {
        $now = self::now($now);
        $accepted = [];
        $rejected = [];

        $btc = self::current_btc($now);
        if (!empty($btc['valid']) && is_array($btc['item'] ?? null)) {
            $accepted[] = $btc['item'];
        } else {
            $rejected[] = [
                'source_type' => 'btc_intelligence',
                'source_index' => null,
                'errors' => self::errors($btc['errors'] ?? ['btc_intelligence_unavailable']),
            ];
        }

        foreach ($manual_sources as $index => $manual) {
            if (!is_array($manual)) {
                $rejected[] = [
                    'source_type' => 'manual_research',
                    'source_index' => $index,
                    'errors' => ['source_not_array'],
                ];
                continue;
            }
            $result = Bitmomo_Research_Feed_V1::from_manual_research($manual, $now);
            if (!empty($result['valid']) && is_array($result['item'] ?? null)) {
                $accepted[] = $result['item'];
                continue;
            }
            $rejected[] = [
                'source_type' => 'manual_research',
                'source_index' => $index,
                'errors' => self::errors($result['errors'] ?? ['manual_research_invalid']),
            ];
        }

        $dedupe = Bitmomo_Research_Feed_V1::deduplicate($accepted);
        $items = is_array($dedupe['items'] ?? null) ? $dedupe['items'] : [];

        return [
            'contract_version' => Bitmomo_Research_Feed_V1::CONTRACT_VERSION,
            'provider_version' => self::PROVIDER_VERSION,
            'generated_at' => gmdate('c', $now),
            'items' => $items,
            'duplicates' => is_array($dedupe['duplicates'] ?? null) ? $dedupe['duplicates'] : [],
            'rejected' => $rejected,
            'counts' => [
                'items' => count($items),
                'distribution_eligible' => count(array_filter($items, function ($item) {
                    return is_array($item) && !empty($item['distribution']['eligible']);
                })),
                'duplicates' => count((array) ($dedupe['duplicates'] ?? [])),
                'rejected' => count($rejected),
            ],
        ];
    }

    private static function snapshot_timestamp(array $snapshot) {
        $candidates = [
            $snapshot['generated_at'] ?? null,
            $snapshot['time'] ?? null,
            $snapshot['data']['timestamp'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $timestamp = self::timestamp($candidate);
            if ($timestamp) return $timestamp;
        }
        return 0;
    }

    private static function timestamp($value) {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $timestamp = (int) $value;
            return $timestamp > 0 ? $timestamp : 0;
        }
        if (!is_scalar($value)) return 0;
        $timestamp = strtotime((string) $value);
        return $timestamp ?: 0;
    }

    private static function number($value) {
        if (!is_numeric($value)) return null;
        $value = (float) $value;
        return is_finite($value) ? $value : null;
    }

    private static function text($value) {
        if (!is_scalar($value)) return '';
        $value = strip_tags((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private static function key($value) {
        $value = strtolower(self::text($value));
        return preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function errors($errors) {
        if (!is_array($errors)) $errors = [$errors];
        $out = [];
        foreach ($errors as $error) {
            $error = self::key($error);
            if ($error !== '') $out[] = $error;
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);
        return $out;
    }

    private static function failure(array $errors) {
        return ['valid' => false, 'errors' => self::errors($errors), 'item' => null];
    }

    private static function now($now) {
        return $now === null ? time() : (is_numeric($now) ? (int) $now : time());
    }
}

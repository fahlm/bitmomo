<?php
if (!defined('ABSPATH')) exit;

/** Persists generation attempts independently from the last valid snapshot. */
final class Bitmomo_AI_Runtime_State {
    const ATTEMPT_OPTION = 'bitmomo_ai_latest_attempt';
    const VALID_OPTION = 'bitmomo_ai_latest_valid_snapshot';

    public static function record_attempt($edition, $status, array $data = [], array $gate = [], $error = null) {
        $diagnostics = is_array($data['source_diagnostics'] ?? null) ? $data['source_diagnostics'] : [];
        if ($error instanceof WP_Error) {
            $error_data = $error->get_error_data();
            if (is_array($error_data) && is_array($error_data['source_diagnostics'] ?? null)) {
                $diagnostics = $error_data['source_diagnostics'];
            }
        }

        $required = array_values(array_map(function ($row) {
            return sanitize_key((string) ($row['requested_input'] ?? ''));
        }, $diagnostics));
        $required = array_values(array_filter($required));
        $available = array_values(array_map(function ($row) {
            return sanitize_key((string) ($row['requested_input'] ?? ''));
        }, array_filter($diagnostics, function ($row) { return !empty($row['success']); })));

        $attempt = [
            'attempted_at' => gmdate('c'),
            'edition' => in_array($edition, ['morning', 'us_session'], true) ? $edition : 'us_session',
            'status' => sanitize_key((string) $status),
            'completeness' => max(0, min(100, (int) ($data['quality']['completeness_pct'] ?? 0))),
            'quality_gate' => self::safe_gate($gate),
            'required_inputs' => $required,
            'available_inputs' => $available,
            'missing_inputs' => array_values(array_diff($required, $available)),
            'source_diagnostics' => $diagnostics,
        ];
        if ($error instanceof WP_Error) {
            $attempt['error'] = [
                'category' => sanitize_key((string) $error->get_error_code()),
                'message' => sanitize_text_field($error->get_error_message()),
            ];
        }
        update_option(self::ATTEMPT_OPTION, $attempt, false);
        return $attempt;
    }

    public static function record_valid_snapshot(array $record, array $gate) {
        $record['quality_gate'] = self::safe_gate($gate);
        update_option(self::VALID_OPTION, $record, false);
        update_option('bitmomo_ai_latest_preview', $record, false);
        return $record;
    }

    public static function latest_attempt() {
        $attempt = get_option(self::ATTEMPT_OPTION, []);
        return is_array($attempt) ? $attempt : [];
    }

    public static function latest_valid_snapshot() {
        $record = get_option(self::VALID_OPTION, []);
        if (!is_array($record) || empty($record['data']) || empty($record['evaluation'])) {
            $record = get_option('bitmomo_ai_latest_preview', []);
        }
        if (!is_array($record) || empty($record['data']) || empty($record['evaluation'])) return [];

        $quality = is_array($record['evaluation']['quality'] ?? null) ? $record['evaluation']['quality'] : [];
        if (!in_array((string) ($quality['status'] ?? ''), ['complete', 'degraded'], true)) return [];
        return $record;
    }

    public static function latest_valid_metadata($now = null) {
        $record = self::latest_valid_snapshot();
        if (!$record) return [];
        $generated_at = (string) ($record['generated_at'] ?? $record['time'] ?? '');
        $timestamp = strtotime($generated_at) ?: 0;
        $now = null === $now ? time() : (int) $now;
        return [
            'canonical_record_id' => sanitize_text_field((string) ($record['source_record_id'] ?? '')),
            'edition' => sanitize_key((string) ($record['edition'] ?? '')),
            'generated_at' => $generated_at,
            'source_provenance' => sanitize_key((string) ($record['provenance'] ?? '')),
            'quality_result' => sanitize_key((string) ($record['quality_gate']['status'] ?? 'unknown')),
            'age_seconds' => $timestamp ? max(0, $now - $timestamp) : null,
            'freshness_state' => self::freshness_state($record, $now),
        ];
    }

    public static function freshness_state(array $record, $now = null) {
        $timestamp = strtotime((string) ($record['generated_at'] ?? $record['time'] ?? ''));
        $now = null === $now ? time() : (int) $now;
        if (!$timestamp || $timestamp > $now + (5 * MINUTE_IN_SECONDS)) return 'unavailable';
        $age = max(0, $now - $timestamp);
        if ($age <= Bitmomo_AI_Intelligence::FRESH_AGE_SECONDS) return 'fresh';
        return $age <= Bitmomo_AI_Intelligence::DELAYED_AGE_SECONDS ? 'delayed' : 'unavailable';
    }

    private static function safe_gate(array $gate) {
        return [
            'status' => sanitize_key((string) ($gate['status'] ?? 'unknown')),
            'checked_at' => sanitize_text_field((string) ($gate['checked_at'] ?? '')),
            'failed_keys' => array_values(array_map('sanitize_key', (array) ($gate['failed_keys'] ?? []))),
            'critical_failures' => array_values(array_map('sanitize_key', (array) ($gate['critical_failures'] ?? []))),
            'hard_blocked' => !empty($gate['hard_blocked']),
        ];
    }
}

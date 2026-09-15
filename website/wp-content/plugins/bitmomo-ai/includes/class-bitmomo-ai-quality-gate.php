<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Quality_Gate {
    const MAX_AGE_MINUTES = 90;
    const MIN_COMPLETENESS_PCT = 80;
    const MAX_LEVEL_DISTANCE_PCT = 12;
    const MIN_PASSED_CHECKS = 7;
    const CRITICAL_CHECKS = ['freshness', 'source_freshness', 'completeness', 'price', 'zones', 'bias_score', 'invalidation'];
    const DERIVATIVE_MAX_AGE_MINUTES = [
        'funding' => 600,
        'premium_index' => 30,
        'open_interest' => 150,
        'global_long_short_ratio' => 150,
        'taker_buy_sell_ratio' => 150,
    ];

    public static function check(array $data, array $evaluation) {
        $result = self::inspect($data, $evaluation);
        update_option('bitmomo_ai_latest_quality_gate', $result, false);
        if (in_array($result['status'], ['passed', 'degraded'], true)) return true;
        return new WP_Error('bitmomo_quality_gate_failed', implode(' ', $result['errors']), ['status' => 422]);
    }

    public static function inspect(array $data, array $evaluation) {
        $checks = [];
        $errors = [];
        $add = function ($key, $passed, $success, $failure) use (&$checks, &$errors) {
            $message = $passed ? $success : $failure;
            $checks[] = ['key' => sanitize_key($key), 'passed' => (bool) $passed, 'message' => sanitize_text_field($message)];
            if (!$passed) $errors[] = sanitize_text_field($failure);
        };

        $quality = (array) ($evaluation['quality'] ?? $data['quality'] ?? []);
        $timestamp = strtotime((string) ($data['timestamp'] ?? ''));
        $calculated_age = $timestamp ? max(0, (int) floor((time() - $timestamp) / 60)) : PHP_INT_MAX;
        $age = isset($quality['data_age_minutes']) ? max(0, (int) $quality['data_age_minutes']) : $calculated_age;
        $axis_count = 0;
        foreach (['volatility', 'direction', 'carry', 'structure', 'crowding'] as $axis) {
            if (!empty($data[$axis]) && is_array($data[$axis])) $axis_count++;
        }
        $computed_completeness = 60 + ($axis_count * 8);
        $completeness = isset($quality['completeness_pct']) ? (int) $quality['completeness_pct'] : $computed_completeness;
        $quality_status = (string) ($quality['status'] ?? ($age <= self::MAX_AGE_MINUTES ? 'complete' : 'stale'));
        $price = (float) ($data['close'] ?? 0);
        $risk = (array) ($evaluation['risk'] ?? []);
        $support_low = (float) ($risk['support_zone_low'] ?? 0);
        $support_high = (float) ($risk['support_zone_high'] ?? 0);
        $resistance_low = (float) ($risk['resistance_zone_low'] ?? 0);
        $resistance_high = (float) ($risk['resistance_zone_high'] ?? 0);
        $invalidation = (float) ($risk['invalidation'] ?? 0);
        $bias = (string) ($evaluation['bias'] ?? 'neutral');
        $score = (int) ($evaluation['score'] ?? 0);

        $add('freshness', $timestamp && $timestamp <= time() + (5 * MINUTE_IN_SECONDS) && $age <= self::MAX_AGE_MINUTES && $quality_status !== 'stale',
            sprintf('Data terbaru: %d menit.', $age),
            sprintf('Pembaruan ditahan: data sudah berusia %d menit; batas maksimum %d menit.', $age, self::MAX_AGE_MINUTES));

        $stale_sources = self::stale_derivative_sources((array) ($data['source_diagnostics'] ?? []));
        $add('source_freshness', empty($stale_sources),
            'Input derivatif yang digunakan masih berada dalam batas freshness masing-masing.',
            sprintf('Pembaruan ditahan: input derivatif terlalu tua atau tidak memiliki timestamp tepercaya (%s).', implode(', ', $stale_sources)));

        $add('completeness', $completeness >= self::MIN_COMPLETENESS_PCT && $axis_count === 5,
            sprintf('Kelengkapan data %d%% dan lima kelompok data tersedia.', $completeness),
            sprintf('Pembaruan ditahan: kelengkapan data %d%%; minimum %d%% dan lima kelompok data wajib tersedia.', $completeness, self::MIN_COMPLETENESS_PCT));
        $add('price', $price > 0,
            'Harga acuan tersedia.',
            'Pembaruan ditahan: harga acuan tidak valid.');

        $zones_valid = $support_low > 0 && $support_high >= $support_low && $resistance_low > 0 && $resistance_high >= $resistance_low && $support_high < $resistance_low;
        $add('zones', $zones_valid,
            'Urutan support dan resistance valid.',
            'Pembaruan ditahan: support dan resistance tidak valid atau saling bertumpuk.');

        $levels_near_price = $price > 0 && $zones_valid
            && (abs($price - $support_low) / $price * 100) <= self::MAX_LEVEL_DISTANCE_PCT
            && (abs($resistance_high - $price) / $price * 100) <= self::MAX_LEVEL_DISTANCE_PCT;
        $add('level_distance', $levels_near_price,
            'Level operasional berada dalam jarak wajar dari harga acuan.',
            sprintf('Pembaruan ditahan: level operasional berjarak lebih dari %d%% dari harga acuan.', self::MAX_LEVEL_DISTANCE_PCT));

        $bias_score_valid = ($bias === 'bullish' && $score >= 20) || ($bias === 'bearish' && $score <= -20) || ($bias === 'neutral' && $score > -20 && $score < 20);
        $add('bias_score', $bias_score_valid,
            'Arah utama konsisten dengan skor gabungan.',
            'Pembaruan ditahan: arah utama bertentangan dengan skor gabungan.');

        $invalidation_valid = ($bias === 'bullish' && $invalidation > 0 && $invalidation < $price)
            || ($bias === 'bearish' && $invalidation > $price)
            || ($bias === 'neutral' && $invalidation === 0.0);
        $add('invalidation', $invalidation_valid,
            'Level risiko konsisten dengan bias utama.',
            'Pembaruan ditahan: level risiko bertentangan dengan bias utama.');

        $passed_count = count(array_filter($checks, function ($check) { return !empty($check['passed']); }));
        $failed_keys = array_values(array_map(function ($check) { return $check['key']; }, array_filter($checks, function ($check) { return empty($check['passed']); })));
        $critical_failures = array_values(array_intersect($failed_keys, self::CRITICAL_CHECKS));
        $hard_blocked = !empty($critical_failures) || $passed_count < self::MIN_PASSED_CHECKS;
        $status = $hard_blocked ? 'blocked' : ($passed_count === count($checks) ? 'passed' : 'degraded');

        return [
            'status' => $status,
            'checked_at' => gmdate('c'),
            'checks' => $checks,
            'errors' => $errors,
            'passed_count' => $passed_count,
            'total_count' => count($checks),
            'minimum_passed' => self::MIN_PASSED_CHECKS,
            'failed_keys' => $failed_keys,
            'critical_failures' => $critical_failures,
            'hard_blocked' => $hard_blocked,
            'summary' => $status === 'passed'
                ? sprintf('%d pemeriksaan lulus.', count($checks))
                : ($status === 'degraded'
                    ? sprintf('%d/%d pemeriksaan lulus; kegagalan non-kritis diizinkan.', $passed_count, count($checks))
                    : implode(' ', $errors)),
        ];
    }

    /** Successful derivative inputs may influence the engine only while their observation timestamp is trustworthy and recent enough for that series. */
    private static function stale_derivative_sources(array $diagnostics) {
        $stale = [];
        if (!$diagnostics) return $stale; // Backward-compatible for legacy/manual fixtures; live provider snapshots always carry diagnostics.
        foreach ($diagnostics as $row) {
            if (!is_array($row) || empty($row['success'])) continue;
            $name = sanitize_key((string) ($row['requested_input'] ?? ''));
            if (!isset(self::DERIVATIVE_MAX_AGE_MINUTES[$name])) continue;
            $age = $row['age_minutes'] ?? null;
            if (!is_numeric($age) || (int) $age > self::DERIVATIVE_MAX_AGE_MINUTES[$name]) $stale[] = $name;
        }
        return array_values(array_unique($stale));
    }
}

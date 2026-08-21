<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Quality_Gate {
    const MAX_AGE_MINUTES = 90;
    const MIN_COMPLETENESS_PCT = 80;
    const MAX_LEVEL_DISTANCE_PCT = 12;

    public static function check(array $data, array $evaluation) {
        $result = self::inspect($data, $evaluation);
        update_option('bitmomo_ai_latest_quality_gate', $result, false);
        if ($result['status'] === 'passed') return true;
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

        return [
            'status' => $errors ? 'blocked' : 'passed',
            'checked_at' => gmdate('c'),
            'checks' => $checks,
            'errors' => $errors,
            'summary' => $errors ? implode(' ', $errors) : sprintf('%d pemeriksaan lulus.', count($checks)),
        ];
    }
}

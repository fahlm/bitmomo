<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Performance {
    const OUTCOME_METHOD = 'observed-close-24h-v2';
    const OUTCOME_HOURS = 24;
    const ALIGNMENT_TOLERANCE_SECONDS = 120;
    const MEANINGFUL_MOVE_PCT = 0.5;

    /**
     * Settle prior signals whenever a new canonical edition has been recorded.
     * The new edition already carries a validated market snapshot whose last
     * closed 1H candle is exactly one day after the same session's prior
     * observation under normal operation. This avoids timezone/DST drift from
     * a fixed-clock settlement cron without making presentation code calculate
     * outcomes.
     */
    public static function settle_from_edition($edition, $record) {
        $data = is_array($record) && is_array($record['data'] ?? null) ? $record['data'] : [];
        if (!$data) return 0;
        $settled = self::settle($data);
        if (function_exists('update_option')) {
            update_option('bitmomo_ai_last_settlement', [
                'status' => 'success',
                'settled' => $settled,
                'trigger' => 'canonical_edition',
                'edition' => sanitize_key((string) $edition),
                'time' => gmdate('c'),
            ], false);
        }
        return $settled;
    }

    /**
     * Evaluate only an exact 24-hour forward window anchored to the signal's
     * original last-closed 1H candle. A later snapshot is never substituted:
     * if the exact target observation is missed, the record is labelled
     * window_missed rather than silently using a 23h/25h/26h endpoint.
     */
    public static function settle(array $market_data) {
        $window = (array) ($market_data['outcome_window'] ?? []);
        $current_price = (float) ($market_data['close'] ?? 0);
        $high = (float) ($window['high_24h'] ?? 0);
        $low = (float) ($window['low_24h'] ?? 0);
        $closed_candles = (int) ($window['closed_candles'] ?? 0);
        $outcome_observed_at = self::observation_timestamp($market_data);
        $outcome_provider = self::price_provider($market_data);
        if ($current_price <= 0 || $high <= 0 || $low <= 0 || $closed_candles !== self::OUTCOME_HOURS || !$outcome_observed_at) return 0;

        $posts = get_posts([
            'post_type' => Bitmomo_AI_Content_Types::SIGNAL,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => '_bm_outcome_status', 'value' => 'pending'],
                ['key' => '_bm_outcome_status', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        $settled = 0;
        foreach ($posts as $post_id) {
            $input = json_decode((string) get_post_meta($post_id, '_bm_input_snapshot', true), true);
            $input = is_array($input) ? $input : [];
            $entry_observed_at = self::observation_timestamp($input);
            if (!$entry_observed_at) continue; // Legacy/incomplete record: never invent an anchor.

            $target_observed_at = $entry_observed_at + (self::OUTCOME_HOURS * HOUR_IN_SECONDS);
            $alignment = $outcome_observed_at - $target_observed_at;
            if ($alignment < -self::ALIGNMENT_TOLERANCE_SECONDS) continue; // Not mature yet.

            $entry_provider = self::price_provider($input);
            if (abs($alignment) > self::ALIGNMENT_TOLERANCE_SECONDS) {
                self::mark_window_missed($post_id, 'exact_24h_snapshot_missed', $entry_observed_at, $target_observed_at, $outcome_observed_at, $entry_provider, $outcome_provider);
                continue;
            }
            if ($entry_provider === '' || $outcome_provider === '' || $entry_provider !== $outcome_provider) {
                self::mark_window_missed($post_id, 'price_provider_mismatch', $entry_observed_at, $target_observed_at, $outcome_observed_at, $entry_provider, $outcome_provider);
                continue;
            }

            $entry = (float) get_post_meta($post_id, '_bm_market_price', true);
            $bias = (string) get_post_meta($post_id, '_bm_direction', true);
            if ($entry <= 0 || !in_array($bias, ['bullish', 'neutral', 'bearish'], true)) continue;

            $return_pct = (($current_price - $entry) / $entry) * 100;
            if ($bias === 'bullish') {
                $direction_result = $return_pct >= self::MEANINGFUL_MOVE_PCT ? 'correct' : ($return_pct <= -self::MEANINGFUL_MOVE_PCT ? 'incorrect' : 'inconclusive');
            } elseif ($bias === 'bearish') {
                $direction_result = $return_pct <= -self::MEANINGFUL_MOVE_PCT ? 'correct' : ($return_pct >= self::MEANINGFUL_MOVE_PCT ? 'incorrect' : 'inconclusive');
            } else {
                $direction_result = abs($return_pct) < self::MEANINGFUL_MOVE_PCT ? 'correct' : 'incorrect';
            }

            $support_high = (float) get_post_meta($post_id, '_bm_support_high', true);
            $resistance_low = (float) get_post_meta($post_id, '_bm_resistance_low', true);
            $resistance_high = (float) get_post_meta($post_id, '_bm_resistance_high', true);
            $risk_level = (float) get_post_meta($post_id, '_bm_risk_level', true);
            $risk_triggered = ($bias === 'bullish' && $risk_level > 0 && $low < $risk_level)
                || ($bias === 'bearish' && $risk_level > 0 && $high > $risk_level);

            update_post_meta($post_id, '_bm_outcome_status', 'evaluated');
            update_post_meta($post_id, '_bm_outcome_methodology', self::OUTCOME_METHOD);
            update_post_meta($post_id, '_bm_outcome_evaluated_at', gmdate('c'));
            update_post_meta($post_id, '_bm_outcome_window_start', gmdate('c', $entry_observed_at));
            update_post_meta($post_id, '_bm_outcome_window_end', gmdate('c', $target_observed_at));
            update_post_meta($post_id, '_bm_outcome_alignment_seconds', (string) $alignment);
            update_post_meta($post_id, '_bm_outcome_provider', $outcome_provider);
            update_post_meta($post_id, '_bm_outcome_closed_candles', (string) $closed_candles);
            update_post_meta($post_id, '_bm_outcome_price_24h', (string) $current_price);
            update_post_meta($post_id, '_bm_outcome_high_24h', (string) $high);
            update_post_meta($post_id, '_bm_outcome_low_24h', (string) $low);
            update_post_meta($post_id, '_bm_outcome_return_pct', (string) round($return_pct, 4));
            update_post_meta($post_id, '_bm_outcome_direction', $direction_result);
            update_post_meta($post_id, '_bm_outcome_support_tested', $support_high > 0 && $low <= $support_high ? 'yes' : 'no');
            update_post_meta($post_id, '_bm_outcome_resistance_tested', $resistance_low > 0 && $high >= $resistance_low ? 'yes' : 'no');
            update_post_meta($post_id, '_bm_outcome_resistance_closed_above', $resistance_high > 0 && $current_price > $resistance_high ? 'yes' : 'no');
            update_post_meta($post_id, '_bm_outcome_risk_triggered', $risk_triggered ? 'yes' : 'no');
            $settled++;
        }
        return $settled;
    }

    private static function mark_window_missed($post_id, $reason, $entry_observed_at, $target_observed_at, $outcome_observed_at, $entry_provider, $outcome_provider) {
        update_post_meta($post_id, '_bm_outcome_status', 'window_missed');
        update_post_meta($post_id, '_bm_outcome_methodology', self::OUTCOME_METHOD);
        update_post_meta($post_id, '_bm_outcome_missed_reason', sanitize_key((string) $reason));
        update_post_meta($post_id, '_bm_outcome_evaluated_at', gmdate('c'));
        update_post_meta($post_id, '_bm_outcome_window_start', gmdate('c', $entry_observed_at));
        update_post_meta($post_id, '_bm_outcome_window_end', gmdate('c', $target_observed_at));
        update_post_meta($post_id, '_bm_outcome_observed_at', gmdate('c', $outcome_observed_at));
        update_post_meta($post_id, '_bm_outcome_provider', sanitize_key((string) $outcome_provider));
        update_post_meta($post_id, '_bm_outcome_entry_provider', sanitize_key((string) $entry_provider));
    }

    private static function observation_timestamp(array $data) {
        $quality = is_array($data['quality'] ?? null) ? $data['quality'] : [];
        $raw = trim((string) ($quality['last_closed_candle'] ?? ''));
        $timestamp = $raw !== '' ? strtotime($raw) : 0;
        return $timestamp && $timestamp > 0 ? $timestamp : 0;
    }

    /** Return the provider that produced the canonical 1H price candle. */
    private static function price_provider(array $data) {
        foreach ((array) ($data['source_diagnostics'] ?? []) as $row) {
            if (!is_array($row) || sanitize_key((string) ($row['requested_input'] ?? '')) !== 'candles_1h') continue;
            $provider = sanitize_key((string) ($row['provider'] ?? ''));
            return in_array($provider, ['binance_usdm', 'binance_spot'], true) ? $provider : '';
        }
        return '';
    }

    public static function corpus_diagnostics() {
        $ids = get_posts([
            'post_type' => Bitmomo_AI_Content_Types::SIGNAL,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
        $post_statuses = [];
        $settlement_states = ['pending' => 0, 'evaluated' => 0, 'window_missed' => 0, 'missing' => 0, 'other' => 0];
        $with_input_snapshot = $with_axis_snapshot = 0;
        $earliest_timestamp = $latest_timestamp = null;
        foreach ($ids as $post_id) {
            $post_status = (string) get_post_status($post_id);
            $post_statuses[$post_status] = ($post_statuses[$post_status] ?? 0) + 1;
            $record_timestamp = strtotime((string) get_post_meta($post_id, '_bm_generated_at', true)) ?: 0;
            if ($record_timestamp <= 0) $record_timestamp = (int) get_post_time('U', false, $post_id);
            if ($record_timestamp > 0) {
                if ($earliest_timestamp === null || $record_timestamp < $earliest_timestamp) $earliest_timestamp = $record_timestamp;
                if ($latest_timestamp === null || $record_timestamp > $latest_timestamp) $latest_timestamp = $record_timestamp;
            }
            $state = (string) get_post_meta($post_id, '_bm_outcome_status', true);
            $bucket = $state === '' ? 'missing' : (isset($settlement_states[$state]) ? $state : 'other');
            $settlement_states[$bucket]++;
            if ((string) get_post_meta($post_id, '_bm_input_snapshot', true) !== '') $with_input_snapshot++;
            if ((string) get_post_meta($post_id, '_bm_axis_snapshot', true) !== '') $with_axis_snapshot++;
        }
        ksort($post_statuses);
        return [
            'total' => count($ids),
            'post_statuses' => $post_statuses,
            'settlement_states' => $settlement_states,
            'with_input_snapshot' => $with_input_snapshot,
            'with_axis_snapshot' => $with_axis_snapshot,
            'earliest_record' => $earliest_timestamp === null ? '' : gmdate('c', $earliest_timestamp),
            'latest_record' => $latest_timestamp === null ? '' : gmdate('c', $latest_timestamp),
        ];
    }

    public static function summary() {
        $posts = get_posts([
            'post_type' => Bitmomo_AI_Content_Types::SIGNAL,
            'post_status' => 'any',
            'posts_per_page' => 30,
            'fields' => 'ids',
            'meta_key' => '_bm_outcome_status',
            'meta_value' => 'evaluated',
        ]);
        $correct = $incorrect = $inconclusive = $risk_triggered = 0;
        foreach ($posts as $post_id) {
            $result = get_post_meta($post_id, '_bm_outcome_direction', true);
            if ($result === 'correct') $correct++;
            elseif ($result === 'incorrect') $incorrect++;
            else $inconclusive++;
            if (get_post_meta($post_id, '_bm_outcome_risk_triggered', true) === 'yes') $risk_triggered++;
        }
        $conclusive = $correct + $incorrect;
        return [
            'total' => count($posts),
            'correct' => $correct,
            'incorrect' => $incorrect,
            'inconclusive' => $inconclusive,
            'accuracy_pct' => $conclusive ? round(($correct / $conclusive) * 100, 1) : null,
            'risk_triggered' => $risk_triggered,
            'outcome_methodology' => self::OUTCOME_METHOD,
        ];
    }
}

if (function_exists('add_action')) {
    add_action('bitmomo_ai_edition_recorded', ['Bitmomo_AI_Performance', 'settle_from_edition'], 20, 2);
}

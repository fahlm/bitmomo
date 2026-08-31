<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Performance {
    const MIN_EVALUATION_HOURS = 22;
    const MAX_EVALUATION_HOURS = 27;
    const MEANINGFUL_MOVE_PCT = 0.5;

    public static function settle(array $market_data) {
        $window = (array) ($market_data['outcome_window'] ?? []);
        $current_price = (float) ($market_data['close'] ?? 0);
        $high = (float) ($window['high_24h'] ?? 0);
        $low = (float) ($window['low_24h'] ?? 0);
        if ($current_price <= 0 || $high <= 0 || $low <= 0) return 0;

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
            $generated = strtotime((string) get_post_meta($post_id, '_bm_generated_at', true));
            if (!$generated) continue;
            $age_hours = (time() - $generated) / HOUR_IN_SECONDS;
            if ($age_hours < self::MIN_EVALUATION_HOURS) continue;
            if ($age_hours > self::MAX_EVALUATION_HOURS) {
                update_post_meta($post_id, '_bm_outcome_status', 'window_missed');
                update_post_meta($post_id, '_bm_outcome_evaluated_at', gmdate('c'));
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
            update_post_meta($post_id, '_bm_outcome_evaluated_at', gmdate('c'));
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
            $record_timestamp = (int) get_post_time('U', true, $post_id);
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
        ];
    }
}

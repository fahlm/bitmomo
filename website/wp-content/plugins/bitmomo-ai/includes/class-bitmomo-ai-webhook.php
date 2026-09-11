<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Webhook {
    const NAMESPACE = 'bitmomo-ai/v1';

    public static function register() {
        add_action('rest_api_init', function () {
            register_rest_route(self::NAMESPACE, '/tradingview/(?P<token>[A-Za-z0-9_-]{32,128})', [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'receive'],
                'permission_callback' => [__CLASS__, 'authorize'],
                'args' => ['token' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field']],
            ]);
        });
    }

    public static function authorize(WP_REST_Request $request) {
        if (!defined('BITMOMO_AI_WEBHOOK_TOKEN') || strlen(BITMOMO_AI_WEBHOOK_TOKEN) < 32) return false;
        return hash_equals((string) BITMOMO_AI_WEBHOOK_TOKEN, (string) $request['token']);
    }

    public static function receive(WP_REST_Request $request) {
        if (get_transient('bitmomo_ai_webhook_lock')) {
            self::record_webhook('rate_limited', 'A second payload arrived while another payload was being processed.');
            return new WP_Error('rate_limited', __('Webhook is busy.', 'bitmomo-ai'), ['status' => 429]);
        }
        set_transient('bitmomo_ai_webhook_lock', 1, 10);

        $payload = $request->get_json_params();
        $validated = self::validate_payload(is_array($payload) ? $payload : []);
        if (is_wp_error($validated)) {
            self::record_webhook('invalid', $validated->get_error_message());
            return $validated;
        }

        $evaluation = Bitmomo_AI_Signal_Engine::evaluate($validated);
        $gate = Bitmomo_AI_Quality_Gate::check($validated, $evaluation);
        $gate_result = (array) get_option('bitmomo_ai_latest_quality_gate', []);
        if (is_wp_error($gate)) {
            Bitmomo_AI_Runtime_State::record_attempt(Bitmomo_AI_Session_Intelligence::POST_CLOSE, 'blocked', $validated, $gate_result, $gate);
            self::record_webhook('blocked', $gate->get_error_message());
            return $gate;
        }

        $fingerprint = hash('sha256', wp_json_encode([$validated['symbol'], $validated['timeframe'], $validated['timestamp']]));
        if (get_transient('bitmomo_ai_seen_' . $fingerprint)) {
            self::record_webhook('duplicate', 'The TradingView payload had already been received.');
            return new WP_REST_Response(['status' => 'duplicate'], 200);
        }
        set_transient('bitmomo_ai_seen_' . $fingerprint, 1, DAY_IN_SECONDS * 7);

        $generated_at = gmdate('c');
        $record = Bitmomo_AI_Session_Intelligence::build_record(
            Bitmomo_AI_Session_Intelligence::POST_CLOSE,
            $validated,
            $evaluation,
            $gate_result,
            $generated_at
        );
        $record['valid_snapshot_lineage']['webhook_fingerprint'] = $fingerprint;
        Bitmomo_AI_Session_Intelligence::append_record($record);
        Bitmomo_AI_Runtime_State::record_valid_snapshot($record, $gate_result);
        Bitmomo_AI_Runtime_State::record_attempt(Bitmomo_AI_Session_Intelligence::POST_CLOSE, ($gate_result['status'] ?? '') === 'degraded' ? 'degraded' : 'success', $validated, $gate_result);
        $post_id = self::create_draft($validated, $evaluation, $fingerprint, Bitmomo_AI_Session_Intelligence::POST_CLOSE);
        if (is_wp_error($post_id)) {
            self::record_webhook('error', $post_id->get_error_message());
            return $post_id;
        }

        self::record_webhook('accepted', sprintf('Daily draft %d created or refreshed.', $post_id), $post_id);

        return new WP_REST_Response(['status' => 'accepted', 'draft_id' => $post_id, 'bias' => $evaluation['bias'], 'confidence' => $evaluation['confidence']], 202);
    }

    private static function record_webhook($status, $message, $post_id = 0) {
        update_option('bitmomo_ai_last_webhook', [
            'status' => sanitize_key($status),
            'message' => sanitize_text_field($message),
            'post_id' => absint($post_id),
            'time' => gmdate('c'),
        ], false);
    }

    private static function validate_payload(array $data) {
        foreach (['symbol', 'exchange', 'timeframe', 'timestamp', 'close', 'volatility', 'direction', 'carry', 'structure', 'crowding'] as $field) {
            if (!array_key_exists($field, $data)) return new WP_Error('invalid_payload', sprintf(__('Missing field: %s', 'bitmomo-ai'), $field), ['status' => 400]);
        }
        $timestamp = strtotime((string) $data['timestamp']);
        if (!$timestamp || $timestamp > time() + (5 * MINUTE_IN_SECONDS) || abs(time() - $timestamp) > DAY_IN_SECONDS) return new WP_Error('stale_payload', __('Timestamp is invalid or stale.', 'bitmomo-ai'), ['status' => 400]);
        foreach (['volatility', 'direction', 'carry', 'structure', 'crowding'] as $axis) if (!is_array($data[$axis])) return new WP_Error('invalid_axis', __('Axis data must be objects.', 'bitmomo-ai'), ['status' => 400]);
        $age_minutes = max(0, (int) floor((time() - $timestamp) / 60));
        $axis_count = count(array_filter(['volatility', 'direction', 'carry', 'structure', 'crowding'], function ($axis) use ($data) { return !empty($data[$axis]); }));
        $completeness = 60 + ($axis_count * 8);

        return [
            'symbol' => sanitize_text_field($data['symbol']),
            'exchange' => sanitize_text_field($data['exchange']),
            'timeframe' => sanitize_text_field($data['timeframe']),
            'timestamp' => gmdate('c', $timestamp),
            'close' => (float) $data['close'],
            'quality' => [
                'status' => $age_minutes <= Bitmomo_AI_Quality_Gate::MAX_AGE_MINUTES ? 'complete' : 'stale',
                'completeness_pct' => $completeness,
                'data_age_minutes' => $age_minutes,
                'source' => 'TradingView webhook',
                'optional_missing' => [],
            ],
            'volatility' => self::clean_axis($data['volatility']),
            'direction' => self::clean_axis($data['direction']),
            'carry' => self::clean_axis($data['carry']),
            'structure' => self::clean_axis($data['structure']),
            'crowding' => self::clean_axis($data['crowding']),
        ];
    }

    private static function clean_axis(array $axis) {
        $clean = [];
        foreach ($axis as $key => $value) $clean[sanitize_key($key)] = is_numeric($value) ? (float) $value : sanitize_text_field($value);
        return $clean;
    }

    public static function create_draft(array $data, array $evaluation, $fingerprint, $edition = 'us_post_close') {
        $session_type = Bitmomo_AI_Session_Intelligence::normalize_session_type($edition);
        $session = Bitmomo_AI_Session_Intelligence::session_context($session_type, $data['timestamp']);
        $title_direction = ($evaluation['bias'] ?? 'neutral') === 'bullish' ? 'Arah Masih Menguat' : (($evaluation['bias'] ?? 'neutral') === 'bearish' ? 'Arah Masih Turun' : 'Arah Belum Pasti');
        $title = sprintf('%s - %s - %s', $session['session_label'], wp_date('d F Y', strtotime($data['timestamp']), new DateTimeZone('Asia/Jakarta')), $title_direction);
        $content = Bitmomo_AI_Report::content($data, $evaluation);
        $timezone = new DateTimeZone('Asia/Jakarta');
        $analysis_date = wp_date('Y-m-d', strtotime($data['timestamp']), $timezone);
        $existing = get_posts([
            'post_type' => Bitmomo_AI_Content_Types::SIGNAL,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_bm_analysis_date', 'value' => $analysis_date],
                ['key' => '_bm_edition', 'value' => [$session_type, Bitmomo_AI_Session_Intelligence::legacy_edition($session_type)], 'compare' => 'IN'],
            ],
        ]);
        if (!$existing) {
            $recent = get_posts(['post_type' => Bitmomo_AI_Content_Types::SIGNAL, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC']);
            foreach ($recent as $candidate) {
                $generated = get_post_meta($candidate, '_bm_generated_at', true);
                $candidate_edition = (string) get_post_meta($candidate, '_bm_edition', true);
                if ($generated && wp_date('Y-m-d', strtotime($generated), $timezone) === $analysis_date
                    && in_array($candidate_edition, [$session_type, Bitmomo_AI_Session_Intelligence::legacy_edition($session_type)], true)) {
                    $existing = [(int) $candidate];
                    break;
                }
            }
        }
        if ($existing && get_post_status((int) $existing[0]) !== 'draft') return (int) $existing[0];
        $postarr = ['post_type' => Bitmomo_AI_Content_Types::SIGNAL, 'post_status' => 'draft', 'post_title' => $title, 'post_content' => $content];
        if ($existing) $postarr['ID'] = (int) $existing[0];
        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) return $post_id;
        update_post_meta($post_id, '_bm_direction', $evaluation['bias']);
        update_post_meta($post_id, '_bm_direction_strength', $evaluation['direction_strength']);
        update_post_meta($post_id, '_bm_confidence', $evaluation['confidence']);
        update_post_meta($post_id, '_bm_timeframe', $data['timeframe']);
        update_post_meta($post_id, '_bm_market_price', (string) $data['close']);
        update_post_meta($post_id, '_bm_generated_at', $data['timestamp']);
        update_post_meta($post_id, '_bm_analysis_date', $analysis_date);
        update_post_meta($post_id, '_bm_edition', $session_type);
        update_post_meta($post_id, '_bm_session_anchor', $session['session_anchor']);
        update_post_meta($post_id, '_bm_us_market_status', $session['us_market_status']);
        update_post_meta($post_id, '_bm_model', 'rules-mtf-v1');
        update_post_meta($post_id, '_bm_payload_hash', $fingerprint);
        update_post_meta($post_id, '_bm_axis_snapshot', wp_json_encode($evaluation['axes']));
        // Immutable-at-creation engine input for forward validation and later replay.
        update_post_meta($post_id, '_bm_input_snapshot', wp_json_encode($data));
        $risk = (array) ($evaluation['risk'] ?? []);
        update_post_meta($post_id, '_bm_support_low', (string) ($risk['support_zone_low'] ?? 0));
        update_post_meta($post_id, '_bm_support_high', (string) ($risk['support_zone_high'] ?? 0));
        update_post_meta($post_id, '_bm_resistance_low', (string) ($risk['resistance_zone_low'] ?? 0));
        update_post_meta($post_id, '_bm_resistance_high', (string) ($risk['resistance_zone_high'] ?? 0));
        update_post_meta($post_id, '_bm_risk_level', (string) ($risk['invalidation'] ?? 0));
        if (!get_post_meta($post_id, '_bm_outcome_status', true)) update_post_meta($post_id, '_bm_outcome_status', 'pending');
        $quality_gate = (array) get_option('bitmomo_ai_latest_quality_gate', []);
        update_post_meta($post_id, '_bm_quality_gate_status', (string) ($quality_gate['status'] ?? 'unknown'));
        update_post_meta($post_id, '_bm_quality_checked_at', (string) ($quality_gate['checked_at'] ?? gmdate('c')));
        update_post_meta($post_id, '_bm_editor_approved', 'no');
        update_post_meta($post_id, '_bm_auto_publish_eligible', 'no');
        return $post_id;
    }
}

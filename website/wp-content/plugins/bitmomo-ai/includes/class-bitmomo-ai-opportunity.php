<?php
if (!defined('ABSPATH')) exit;

/**
 * Append-only Opportunity V1 store.
 *
 * The latest accepted record is mirrored in a small option for fast reads;
 * the custom table remains the immutable evaluation ledger used for forward
 * validation and later outcome settlement.
 */
final class Bitmomo_AI_Opportunity_Store {
    const DB_VERSION = '1.0';
    const DB_VERSION_OPTION = 'bitmomo_ai_opportunity_db_version';
    const LATEST_OPTION = 'bitmomo_ai_latest_opportunity';
    const MAX_LATEST_AGE_SECONDS = 30 * MINUTE_IN_SECONDS;

    public static function table_name() {
        global $wpdb;
        return isset($wpdb) && isset($wpdb->prefix) ? $wpdb->prefix . 'bitmomo_opportunity_evaluations' : '';
    }

    public static function maybe_install() {
        if (!function_exists('get_option') || get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) return;
        self::install();
    }

    public static function install() {
        global $wpdb;
        if (!isset($wpdb) || !method_exists($wpdb, 'get_charset_collate')) return false;

        $table = self::table_name();
        if ($table === '') return false;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            methodology_version varchar(32) NOT NULL,
            evaluated_at datetime NOT NULL,
            knowledge_time datetime NOT NULL,
            state varchar(16) NOT NULL,
            range_60m_pct decimal(14,8) NOT NULL,
            activity_percentile decimal(8,4) NOT NULL,
            reference_observation_count smallint(5) unsigned NOT NULL,
            source varchar(64) NOT NULL,
            source_last_close_time datetime NOT NULL,
            previous_state varchar(16) NULL,
            state_changed tinyint(1) NOT NULL DEFAULT 0,
            payload longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY methodology_knowledge (methodology_version, knowledge_time),
            KEY state_knowledge (state, knowledge_time)
        ) {$charset};";

        if (!function_exists('dbDelta')) {
            $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (file_exists($upgrade)) require_once $upgrade;
        }
        if (!function_exists('dbDelta')) return false;
        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
        return true;
    }

    public static function append(array $record) {
        global $wpdb;
        if (!self::is_valid_record($record)) {
            return new WP_Error('bitmomo_opportunity_invalid_record', __('Opportunity record is invalid.', 'bitmomo-ai'));
        }
        self::maybe_install();
        $table = self::table_name();
        if ($table === '' || !isset($wpdb) || !method_exists($wpdb, 'insert')) {
            return new WP_Error('bitmomo_opportunity_store_unavailable', __('Opportunity storage is unavailable.', 'bitmomo-ai'));
        }

        $knowledge_mysql = self::mysql_time($record['knowledge_time']);
        $existing_id = method_exists($wpdb, 'get_var') && method_exists($wpdb, 'prepare')
            ? $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE methodology_version = %s AND knowledge_time = %s LIMIT 1",
                Bitmomo_AI_Opportunity::METHODOLOGY_VERSION,
                $knowledge_mysql
            ))
            : null;
        if ($existing_id) {
            $latest = self::latest_raw();
            return is_array($latest) && ($latest['knowledge_time'] ?? '') === ($record['knowledge_time'] ?? '') ? $latest : $record;
        }

        $previous = self::latest_raw();
        $previous_state = self::state_or_null($previous['state'] ?? null);
        $current_state = self::state_or_null($record['state'] ?? null);
        $record['previous_state'] = $previous_state;
        $record['changed'] = $previous_state !== null && $current_state !== null && $previous_state !== $current_state;
        $record['record_id'] = 'opportunity-v1:' . preg_replace('/[^0-9TZ]/', '', (string) $record['knowledge_time']);

        $inserted = $wpdb->insert($table, [
            'methodology_version' => Bitmomo_AI_Opportunity::METHODOLOGY_VERSION,
            'evaluated_at' => self::mysql_time($record['evaluated_at']),
            'knowledge_time' => $knowledge_mysql,
            'state' => $current_state,
            'range_60m_pct' => (float) $record['range_60m_pct'],
            'activity_percentile' => (float) $record['activity_percentile'],
            'reference_observation_count' => (int) $record['reference_observation_count'],
            'source' => sanitize_key((string) $record['source']),
            'source_last_close_time' => self::mysql_time($record['source_last_close_time']),
            'previous_state' => $previous_state,
            'state_changed' => !empty($record['changed']) ? 1 : 0,
            'payload' => wp_json_encode($record),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s']);

        if ($inserted === false) {
            return new WP_Error('bitmomo_opportunity_store_failed', __('Opportunity evaluation could not be persisted.', 'bitmomo-ai'));
        }

        update_option(self::LATEST_OPTION, $record, false);
        do_action('bitmomo_ai_opportunity_recorded', $record);
        if (!empty($record['changed'])) do_action('bitmomo_ai_opportunity_state_changed', $record, $previous);
        return $record;
    }

    public static function latest_raw() {
        $record = get_option(self::LATEST_OPTION, []);
        return is_array($record) ? $record : [];
    }

    public static function latest_valid($now = null) {
        $record = self::latest_raw();
        if (!self::is_valid_record($record)) return [];
        $now = null === $now ? time() : (int) $now;
        $knowledge = strtotime((string) ($record['knowledge_time'] ?? '')) ?: 0;
        if (!$knowledge || $knowledge > $now + MINUTE_IN_SECONDS) return [];
        if (($now - $knowledge) > self::MAX_LATEST_AGE_SECONDS) return [];
        return $record;
    }

    public static function public_latest($now = null) {
        $record = self::latest_valid($now);
        return $record ? self::public_record($record) : [
            'status' => 'unavailable',
            'methodology_version' => Bitmomo_AI_Opportunity::METHODOLOGY_VERSION,
        ];
    }

    public static function public_record(array $record) {
        $state = self::state_or_null($record['state'] ?? null);
        if ($state === null) return ['status' => 'unavailable', 'methodology_version' => Bitmomo_AI_Opportunity::METHODOLOGY_VERSION];
        return [
            'status' => 'available',
            'state' => $state,
            'methodology_version' => Bitmomo_AI_Opportunity::METHODOLOGY_VERSION,
            'evaluated_at' => sanitize_text_field((string) ($record['evaluated_at'] ?? '')),
            'knowledge_time' => sanitize_text_field((string) ($record['knowledge_time'] ?? '')),
            'range_60m_pct' => round((float) ($record['range_60m_pct'] ?? 0), 6),
            'activity_percentile' => round((float) ($record['activity_percentile'] ?? 0), 2),
            'reference_window_days' => Bitmomo_AI_Opportunity::REFERENCE_WINDOW_DAYS,
            'reference_observation_count' => (int) ($record['reference_observation_count'] ?? 0),
            'source' => Bitmomo_AI_Opportunity::SOURCE,
            'previous_state' => self::state_or_null($record['previous_state'] ?? null),
            'changed' => !empty($record['changed']),
        ];
    }

    /** Join the latest accepted Opportunity record into a canonical session snapshot without recalculation. */
    public static function attach_to_session_record(array $record) {
        $generated = strtotime((string) ($record['generated_at'] ?? $record['time'] ?? '')) ?: time();
        $opportunity = self::latest_valid($generated);
        if (!$opportunity) return $record;

        $public = self::public_record($opportunity);
        $record['opportunity'] = $public;
        if (!is_array($record['session_intelligence'] ?? null)) $record['session_intelligence'] = [];
        if (!is_array($record['session_intelligence']['current_setup'] ?? null)) $record['session_intelligence']['current_setup'] = [];
        $record['session_intelligence']['opportunity'] = $public;
        $record['session_intelligence']['current_setup']['opportunity_state'] = $public['state'];

        $comparison_id = (string) ($record['comparison_source_record_id'] ?? '');
        $prior_state = null;
        if ($comparison_id !== '' && class_exists('Bitmomo_AI_Session_Intelligence')) {
            $history = Bitmomo_AI_Session_Intelligence::history();
            $previous = is_array($history[$comparison_id] ?? null) ? $history[$comparison_id] : [];
            $prior_state = self::state_or_null($previous['session_intelligence']['opportunity']['state'] ?? ($previous['session_intelligence']['current_setup']['opportunity_state'] ?? null));
        }
        if ($prior_state !== null && $prior_state !== $public['state']) {
            $changes = is_array($record['session_intelligence']['what_changed'] ?? null) ? $record['session_intelligence']['what_changed'] : [];
            $exists = false;
            foreach ($changes as $change) if (is_array($change) && ($change['field'] ?? '') === 'opportunity_state') $exists = true;
            if (!$exists) $changes[] = ['field' => 'opportunity_state', 'from' => $prior_state, 'to' => $public['state']];
            $record['session_intelligence']['what_changed'] = $changes;
        }
        return $record;
    }

    private static function is_valid_record(array $record) {
        $state = self::state_or_null($record['state'] ?? null);
        return ($record['methodology_version'] ?? '') === Bitmomo_AI_Opportunity::METHODOLOGY_VERSION
            && ($record['source'] ?? '') === Bitmomo_AI_Opportunity::SOURCE
            && $state !== null
            && isset($record['range_60m_pct'], $record['activity_percentile'], $record['reference_observation_count'])
            && (int) $record['reference_observation_count'] === Bitmomo_AI_Opportunity::REFERENCE_OBSERVATIONS
            && strtotime((string) ($record['knowledge_time'] ?? ''))
            && strtotime((string) ($record['evaluated_at'] ?? ''));
    }

    private static function state_or_null($value) {
        $value = strtoupper(sanitize_key((string) $value));
        return in_array($value, ['HIGH', 'NORMAL', 'LOW'], true) ? $value : null;
    }

    private static function mysql_time($value) {
        $timestamp = strtotime((string) $value) ?: 0;
        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : '1970-01-01 00:00:00';
    }
}

/** Deterministic Opportunity V1 calculator + strict Binance USD-M source adapter. */
final class Bitmomo_AI_Opportunity {
    const METHODOLOGY_VERSION = 'opportunity-v1';
    const SOURCE = 'binance_usdm_btcusdt_5m';
    const BINANCE_BASE = 'https://fapi.binance.com';
    const INTERVAL_SECONDS = 300;
    const EVALUATION_SECONDS = 900;
    const REFERENCE_WINDOW_DAYS = 14;
    const REFERENCE_OBSERVATIONS = 1344;
    const TRAILING_CANDLES = 12;
    const REQUIRED_CANDLES = 4044;
    const HOOK = 'bitmomo_ai_opportunity_evaluation';
    const LAST_RUN_OPTION = 'bitmomo_ai_last_opportunity_run';

    public static function register() {
        if (!function_exists('add_action')) return;
        add_action(self::HOOK, [__CLASS__, 'run_scheduled']);
        add_action('init', [__CLASS__, 'ensure_schedule']);
        if (function_exists('register_activation_hook') && defined('BITMOMO_AI_FILE')) {
            register_activation_hook(BITMOMO_AI_FILE, [__CLASS__, 'activate']);
        }
        if (function_exists('register_deactivation_hook') && defined('BITMOMO_AI_FILE')) {
            register_deactivation_hook(BITMOMO_AI_FILE, [__CLASS__, 'unschedule']);
        }
    }

    public static function activate() {
        Bitmomo_AI_Opportunity_Store::install();
        self::ensure_schedule();
    }

    public static function ensure_schedule($now = null) {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) return;
        $expected = self::next_run_timestamp($now);
        $scheduled = wp_next_scheduled(self::HOOK);
        if ($scheduled && abs($scheduled - $expected) <= 30) return;
        if ($scheduled && function_exists('wp_clear_scheduled_hook')) wp_clear_scheduled_hook(self::HOOK);
        wp_schedule_single_event($expected, self::HOOK);
    }

    public static function unschedule() {
        if (function_exists('wp_clear_scheduled_hook')) wp_clear_scheduled_hook(self::HOOK);
    }

    public static function next_run_timestamp($now = null) {
        $now = null === $now ? time() : (int) $now;
        $current_cutoff = (int) floor($now / self::EVALUATION_SECONDS) * self::EVALUATION_SECONDS;
        $candidate = $current_cutoff + 60; // one minute after the quarter-hour boundary
        if ($candidate <= $now) $candidate += self::EVALUATION_SECONDS;
        return $candidate;
    }

    public static function run_scheduled() {
        try {
            $record = self::evaluate_latest();
            if (is_wp_error($record)) {
                self::record_run('error', $record->get_error_code(), $record->get_error_message());
                return $record;
            }
            $stored = Bitmomo_AI_Opportunity_Store::append($record);
            if (is_wp_error($stored)) {
                self::record_run('error', $stored->get_error_code(), $stored->get_error_message());
                return $stored;
            }
            self::record_run('success', '', 'Opportunity evaluation persisted.');
            return $stored;
        } finally {
            self::ensure_schedule();
        }
    }

    public static function evaluate_latest($now = null) {
        $now = null === $now ? time() : (int) $now;
        $cutoff = (int) floor($now / self::EVALUATION_SECONDS) * self::EVALUATION_SECONDS;
        if (($now - $cutoff) < 30) $cutoff -= self::EVALUATION_SECONDS;
        $rows = self::fetch_usdm_5m_history($cutoff);
        if (is_wp_error($rows)) return $rows;
        return self::evaluate_rows($rows, $cutoff, $now);
    }

    /** Pure calculator used by deterministic fixtures and replay parity checks. */
    public static function evaluate_rows(array $rows, $cutoff_timestamp, $evaluated_at = null) {
        $cutoff_timestamp = (int) $cutoff_timestamp;
        $evaluated_at = null === $evaluated_at ? $cutoff_timestamp : (int) $evaluated_at;
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) < 7) continue;
            $open_ms = (int) $row[0];
            $close_ms = (int) $row[6];
            if ($open_ms <= 0 || $close_ms <= 0 || $close_ms >= (($cutoff_timestamp + 1) * 1000)) continue;
            if ((float) $row[2] <= 0 || (float) $row[3] <= 0) continue;
            $normalized[$open_ms] = $row;
        }
        ksort($normalized, SORT_NUMERIC);
        $normalized = array_values($normalized);
        if (count($normalized) < self::REQUIRED_CANDLES) {
            return new WP_Error('bitmomo_opportunity_insufficient_history', __('Opportunity requires a complete 14-day reference baseline.', 'bitmomo-ai'));
        }
        $window = array_slice($normalized, -self::REQUIRED_CANDLES);
        $expected_first_open = ($cutoff_timestamp - (self::REQUIRED_CANDLES * self::INTERVAL_SECONDS)) * 1000;
        for ($i = 0; $i < self::REQUIRED_CANDLES; $i++) {
            $expected_open = $expected_first_open + ($i * self::INTERVAL_SECONDS * 1000);
            if ((int) $window[$i][0] !== $expected_open) {
                return new WP_Error('bitmomo_opportunity_source_gap', __('Opportunity source history contains a gap or misaligned candle.', 'bitmomo-ai'));
            }
        }
        $last = end($window);
        $last_close_ms = (int) $last[6];
        if ((int) $last[0] !== (($cutoff_timestamp - self::INTERVAL_SECONDS) * 1000) || $last_close_ms > ($cutoff_timestamp * 1000)) {
            return new WP_Error('bitmomo_opportunity_cutoff_mismatch', __('Opportunity source does not align with the evaluation cutoff.', 'bitmomo-ai'));
        }

        $last_index = count($window) - 1;
        $current = self::range_60m_pct($window, $last_index);
        $reference = [];
        for ($offset = 1; $offset <= self::REFERENCE_OBSERVATIONS; $offset++) {
            $end_index = $last_index - ($offset * 3);
            $reference[] = self::range_60m_pct($window, $end_index);
        }
        if (count($reference) !== self::REFERENCE_OBSERVATIONS) {
            return new WP_Error('bitmomo_opportunity_reference_failed', __('Opportunity reference window could not be constructed.', 'bitmomo-ai'));
        }

        $below_or_equal = 0;
        foreach ($reference as $value) if ($value <= $current) $below_or_equal++;
        $percentile = 100 * $below_or_equal / count($reference);
        $state = $percentile >= 75 ? 'HIGH' : ($percentile <= 25 ? 'LOW' : 'NORMAL');

        return [
            'methodology_version' => self::METHODOLOGY_VERSION,
            'evaluated_at' => gmdate('c', $evaluated_at),
            'knowledge_time' => gmdate('c', $cutoff_timestamp),
            'state' => $state,
            'range_60m_pct' => round($current, 8),
            'activity_percentile' => round($percentile, 4),
            'reference_window_days' => self::REFERENCE_WINDOW_DAYS,
            'reference_observation_count' => count($reference),
            'source' => self::SOURCE,
            'source_last_close_time' => gmdate('c', (int) floor($last_close_ms / 1000)),
            'source_age_seconds' => max(0, $evaluated_at - $cutoff_timestamp),
            'source_integrity' => 'complete',
        ];
    }

    private static function range_60m_pct(array $rows, $end_index) {
        $start = (int) $end_index - self::TRAILING_CANDLES + 1;
        if ($start < 0) return 0;
        $high = 0.0;
        $low = null;
        for ($i = $start; $i <= $end_index; $i++) {
            $high = max($high, (float) $rows[$i][2]);
            $row_low = (float) $rows[$i][3];
            $low = $low === null ? $row_low : min($low, $row_low);
        }
        return $low > 0 ? (($high - $low) / $low) * 100 : 0;
    }

    private static function fetch_usdm_5m_history($cutoff_timestamp) {
        $needed = self::REQUIRED_CANDLES;
        $rows = [];
        $end_ms = ((int) $cutoff_timestamp * 1000) - 1;
        for ($page = 0; $page < 4 && count($rows) < $needed; $page++) {
            $limit = min(1500, $needed - count($rows));
            $response = self::request_klines($end_ms, $limit);
            if (is_wp_error($response)) return $response;
            if (!$response) break;
            $rows = array_merge($response, $rows);
            $first = reset($response);
            $first_open = is_array($first) ? (int) ($first[0] ?? 0) : 0;
            if ($first_open <= 0) break;
            $end_ms = $first_open - 1;
        }
        if (count($rows) < $needed) {
            return new WP_Error('bitmomo_opportunity_fetch_incomplete', __('Binance USD-M returned insufficient Opportunity history.', 'bitmomo-ai'));
        }
        return $rows;
    }

    private static function request_klines($end_ms, $limit) {
        $url = add_query_arg([
            'symbol' => 'BTCUSDT',
            'interval' => '5m',
            'endTime' => (int) $end_ms,
            'limit' => (int) $limit,
        ], self::BINANCE_BASE . '/fapi/v1/klines');

        $last_error = new WP_Error('bitmomo_opportunity_transport', __('Binance USD-M Opportunity request failed.', 'bitmomo-ai'));
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_safe_remote_get($url, ['timeout' => 12, 'redirection' => 2, 'user-agent' => 'Bitmomo-AI/' . (defined('BITMOMO_AI_VERSION') ? BITMOMO_AI_VERSION : 'unknown')]);
            if (is_wp_error($response)) {
                $last_error = $response;
            } else {
                $status = (int) wp_remote_retrieve_response_code($response);
                if ($status !== 200) {
                    $last_error = new WP_Error('bitmomo_opportunity_http', __('Binance USD-M Opportunity request failed.', 'bitmomo-ai'), ['status' => $status]);
                } else {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($data)) return $data;
                    $last_error = new WP_Error('bitmomo_opportunity_payload', __('Binance USD-M returned invalid Opportunity data.', 'bitmomo-ai'));
                }
            }
            if ($attempt === 1) usleep(250000);
        }
        return $last_error;
    }

    private static function record_run($status, $error_code, $message) {
        update_option(self::LAST_RUN_OPTION, [
            'status' => sanitize_key((string) $status),
            'error_code' => sanitize_key((string) $error_code),
            'message' => sanitize_text_field((string) $message),
            'time' => gmdate('c'),
        ], false);
    }
}

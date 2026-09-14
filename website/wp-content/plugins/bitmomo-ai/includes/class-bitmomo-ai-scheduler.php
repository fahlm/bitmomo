<?php
if (!defined('ABSPATH')) exit;

final class Bitmomo_AI_Scheduler {
    const HOOK = 'bitmomo_ai_daily_generation';
    const MORNING_HOOK = 'bitmomo_ai_morning_generation';
    const SETTLEMENT_HOOK = 'bitmomo_ai_outcome_settlement';
    const PUBLISH_LOG_OPTION = 'bitmomo_ai_publish_log';

    public static function auto_publish_enabled() {
        return defined('BITMOMO_AI_AUTO_PUBLISH') && (bool) BITMOMO_AI_AUTO_PUBLISH;
    }

    public static function register() {
        add_action(self::HOOK, [__CLASS__, 'run_us_session']);
        add_action(self::MORNING_HOOK, [__CLASS__, 'run_morning']);
        add_action(self::SETTLEMENT_HOOK, [__CLASS__, 'run_settlement']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_bitmomo_ai_run_now', [__CLASS__, 'run_now']);
        add_action('admin_post_bitmomo_ai_create_preview', [__CLASS__, 'create_preview_page']);
        add_action('init', [__CLASS__, 'ensure_schedule']);
    }

    public static function ensure_schedule() {
        self::schedule_session(self::MORNING_HOOK, Bitmomo_AI_Session_Intelligence::PRE_OPEN);
        self::schedule_session(self::HOOK, Bitmomo_AI_Session_Intelligence::POST_CLOSE);
        if (!wp_next_scheduled(self::SETTLEMENT_HOOK)) self::schedule_settlement();
    }

    public static function schedule() {
        self::schedule_session(self::MORNING_HOOK, Bitmomo_AI_Session_Intelligence::PRE_OPEN);
        self::schedule_session(self::HOOK, Bitmomo_AI_Session_Intelligence::POST_CLOSE);
        self::schedule_settlement();
    }

    private static function schedule_session($hook, $session_type, DateTimeImmutable $now = null) {
        $expected = Bitmomo_AI_Session_Intelligence::next_anchor($session_type, $now)->getTimestamp();
        $scheduled = wp_next_scheduled($hook);
        if ($scheduled && abs($scheduled - $expected) <= 60) return;
        if ($scheduled) wp_clear_scheduled_hook($hook);
        wp_schedule_single_event($expected, $hook);
    }

    private static function schedule_settlement() {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $now = new DateTimeImmutable('now', $timezone);
        if (!wp_next_scheduled(self::SETTLEMENT_HOOK)) {
            $next_settlement = $now->setTime(8, 10);
            if ($next_settlement <= $now) $next_settlement = $next_settlement->modify('+1 day');
            wp_schedule_event($next_settlement->getTimestamp(), 'twicedaily', self::SETTLEMENT_HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::HOOK);
        wp_clear_scheduled_hook(self::MORNING_HOOK);
        wp_clear_scheduled_hook(self::SETTLEMENT_HOOK);
    }

    public static function run_morning() {
        try { return self::run(Bitmomo_AI_Session_Intelligence::PRE_OPEN); }
        finally { self::schedule_session(self::MORNING_HOOK, Bitmomo_AI_Session_Intelligence::PRE_OPEN); }
    }

    public static function run_us_session() {
        try { return self::run(Bitmomo_AI_Session_Intelligence::POST_CLOSE); }
        finally { self::schedule_session(self::HOOK, Bitmomo_AI_Session_Intelligence::POST_CLOSE); }
    }

    public static function run_settlement() {
        $data = Bitmomo_AI_Binance::snapshot();
        if (is_wp_error($data)) {
            update_option('bitmomo_ai_last_settlement', ['status' => 'error', 'message' => $data->get_error_message(), 'time' => gmdate('c')], false);
            return $data;
        }
        $settled = Bitmomo_AI_Performance::settle($data);
        do_action('bitmomo_ai_settlement_snapshot', $data);
        update_option('bitmomo_ai_last_settlement', ['status' => 'success', 'settled' => $settled, 'time' => gmdate('c'), 'trigger' => 'recovery_schedule'], false);
        return $settled;
    }

    public static function automation_health() {
        $timezone = new DateTimeZone(Bitmomo_AI_Session_Intelligence::MARKET_TIMEZONE);
        $now = new DateTimeImmutable('now', $timezone);
        $next = wp_next_scheduled(self::HOOK);
        $last = (array) get_option('bitmomo_ai_last_run', []);
        $last_timestamp = strtotime((string) ($last['time'] ?? '')) ?: 0;
        $last_local = $last_timestamp ? (new DateTimeImmutable('@' . $last_timestamp))->setTimezone($timezone) : null;
        $last_in_release_window = $last_local
            && $last_local->format('Y-m-d') === $now->format('Y-m-d')
            && (int) $last_local->format('Hi') >= 2000;
        $cutoff = $now->setTime(20, 30);
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;

        if (!$next) {
            $state = 'not_scheduled';
            $message = __('Jadwal harian tidak ditemukan.', 'bitmomo-ai');
        } elseif ($now > $cutoff && !$last_in_release_window) {
            $state = 'overdue';
            $message = __('US Post-Close Intelligence hari ini belum berjalan setelah batas 20:30 New York.', 'bitmomo-ai');
        } elseif ($last_in_release_window && in_array(($last['status'] ?? ''), ['success', 'published'], true)) {
            $state = 'healthy';
            $message = __('Eksekusi pada jendela rilis hari ini berhasil.', 'bitmomo-ai');
        } elseif ($last_in_release_window && in_array(($last['status'] ?? ''), ['error', 'blocked'], true)) {
            $state = 'failed';
            $message = __('Eksekusi pada jendela rilis hari ini gagal atau diblokir.', 'bitmomo-ai');
        } else {
            $state = 'scheduled';
            $message = __('Jadwal berikutnya mengikuti anchor sesi America/New_York.', 'bitmomo-ai');
        }

        return [
            'state' => $state,
            'message' => $message,
            'next_timestamp' => $next ?: 0,
            'next_label' => $next ? wp_date('d M Y, H:i:s T', $next, $timezone) : __('tidak terjadwal', 'bitmomo-ai'),
            'last_timestamp' => $last_timestamp,
            'last_label' => $last_local ? $last_local->format('d M Y, H:i:s T') : __('belum pernah', 'bitmomo-ai'),
            'last_status' => (string) ($last['status'] ?? 'unknown'),
            'wp_cron_disabled' => $wp_cron_disabled,
            'trigger_label' => $wp_cron_disabled
                ? __('WP-Cron internal dinonaktifkan; pemicu cron hosting wajib aktif.', 'bitmomo-ai')
                : __('WP-Cron internal aktif; cron hosting setiap 5 menit tetap disarankan untuk ketepatan waktu.', 'bitmomo-ai'),
        ];
    }

    public static function run($edition = 'us_post_close') {
        if (!Bitmomo_AI_Session_Intelligence::is_supported_session_type($edition)) {
            $error = new WP_Error(
                'bitmomo_invalid_session_type',
                __('Intelligence session type is unsupported; generation was blocked.', 'bitmomo-ai')
            );
            self::record('blocked', $error->get_error_message());
            return $error;
        }
        $edition = Bitmomo_AI_Session_Intelligence::normalize_session_type($edition);
        $data = Bitmomo_AI_Binance::snapshot();
        if (is_wp_error($data)) {
            Bitmomo_AI_Runtime_State::record_attempt($edition, 'error', [], [], $data);
            self::record('error', $data->get_error_message());
            return $data;
        }

        // Settle the matching edition from the prior day against this session
        // anchor before recording today's signal. Because PRE_OPEN and
        // POST_CLOSE each run at the same New York-local anchor every day,
        // this is the closest natural 24H endpoint available from the same
        // canonical market snapshot. The fixed WIB settlement hook remains a
        // recovery path if a session run is missed.
        $session_settled = Bitmomo_AI_Performance::settle($data);
        if ($session_settled > 0) {
            update_option('bitmomo_ai_last_settlement', [
                'status' => 'success',
                'settled' => $session_settled,
                'time' => gmdate('c'),
                'trigger' => 'session_anchor',
                'edition' => $edition,
            ], false);
        }

        $evaluation = Bitmomo_AI_Signal_Engine::evaluate($data);
        $gate = Bitmomo_AI_Quality_Gate::check($data, $evaluation);
        $gate_result = (array) get_option('bitmomo_ai_latest_quality_gate', []);
        if (is_wp_error($gate)) {
            Bitmomo_AI_Runtime_State::record_attempt($edition, 'blocked', $data, $gate_result, $gate);
            self::record('blocked', $gate->get_error_message());
            return $gate;
        }
        $generated_at = gmdate('c');
        $record = Bitmomo_AI_Session_Intelligence::build_record($edition, $data, $evaluation, $gate_result, $generated_at);
        $source_record_id = $record['source_record_id'];
        Bitmomo_AI_Session_Intelligence::append_record($record);
        Bitmomo_AI_Runtime_State::record_valid_snapshot($record, $gate_result);
        Bitmomo_AI_Runtime_State::record_attempt($edition, ($gate_result['status'] ?? '') === 'degraded' ? 'degraded' : 'success', $data, $gate_result);
        $fingerprint = hash('sha256', 'binance-public|' . $record['session_anchor'] . '|' . $edition);
        $post_id = Bitmomo_AI_Webhook::create_draft($data, $evaluation, $fingerprint, $edition);
        if (is_wp_error($post_id)) {
            self::record('error', $post_id->get_error_message());
            return $post_id;
        }

        // A non-draft record has already crossed the editorial/publication
        // boundary. Re-running the engine for the same date/edition may create
        // a new canonical session record, but it must never relabel or re-point
        // the historical published signal to a different engine/source id.
        if (get_post_status($post_id) !== 'draft') {
            do_action('bitmomo_ai_edition_recorded', $edition, $record);
            self::record('success', sprintf('Existing non-draft analysis %d was preserved byte-for-byte; new runtime intelligence was recorded separately.', $post_id), $post_id);
            self::record_publication('preserved', 'Existing non-draft intelligence metadata was left immutable on rerun.', $post_id);
            return $post_id;
        }

        update_post_meta($post_id, '_bm_model', Bitmomo_AI_Signal_Engine::MODEL_VERSION);
        update_post_meta($post_id, '_bm_edition', $edition);
        update_post_meta($post_id, '_bm_source_record_id', $source_record_id);
        update_post_meta($post_id, '_bm_comparison_source_record_id', $record['comparison_source_record_id']);
        do_action('bitmomo_ai_edition_recorded', $edition, $record);

        if (!self::auto_publish_enabled()) {
            self::record('success', sprintf('Daily draft %d created or refreshed; auto-publish is disabled by the safe default or kill switch.', $post_id), $post_id);
            self::record_publication('disabled', 'Auto-publish requires BITMOMO_AI_AUTO_PUBLISH to be explicitly set to true.', $post_id);
            return $post_id;
        }

        update_post_meta($post_id, '_bm_auto_publish_eligible', 'yes');
        $published_id = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
        if (is_wp_error($published_id)) {
            update_post_meta($post_id, '_bm_auto_publish_eligible', 'no');
            self::record('error', $published_id->get_error_message(), $post_id);
            self::record_publication('error', $published_id->get_error_message(), $post_id);
            return $published_id;
        }
        if (get_post_status($post_id) !== 'publish') {
            update_post_meta($post_id, '_bm_auto_publish_eligible', 'no');
            $error = new WP_Error('bitmomo_auto_publish_blocked', __('Auto-publish was blocked by the release gate.', 'bitmomo-ai'));
            self::record('blocked', $error->get_error_message(), $post_id);
            self::record_publication('blocked', $error->get_error_message(), $post_id);
            return $error;
        }

        update_post_meta($post_id, '_bm_auto_publish_eligible', 'no');
        update_post_meta($post_id, '_bm_auto_published_at', gmdate('c'));
        self::record('published', sprintf('Daily analysis %d passed the conditional gate and was published automatically.', $post_id), $post_id);
        self::record_publication('published', 'Conditional auto-publish completed.', $post_id);
        return $post_id;
    }

    private static function record($status, $message, $post_id = 0) {
        update_option('bitmomo_ai_last_run', ['status' => sanitize_key($status), 'message' => sanitize_text_field($message), 'post_id' => absint($post_id), 'time' => gmdate('c')], false);
    }

    private static function record_publication($status, $message, $post_id = 0) {
        $log = get_option(self::PUBLISH_LOG_OPTION, []);
        if (!is_array($log)) $log = [];
        array_unshift($log, [
            'status' => sanitize_key($status),
            'message' => sanitize_text_field($message),
            'post_id' => absint($post_id),
            'quality_status' => sanitize_key((string) (get_post_meta($post_id, '_bm_quality_gate_status', true) ?: 'unknown')),
            'time' => gmdate('c'),
        ]);
        update_option(self::PUBLISH_LOG_OPTION, array_slice($log, 0, 30), false);
    }

    public static function admin_menu() {
        add_management_page(__('Bitmomo AI Diagnostics', 'bitmomo-ai'), __('Bitmomo AI', 'bitmomo-ai'), 'manage_options', 'bitmomo-ai', [__CLASS__, 'admin_page']);
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) return;
        $last = get_option('bitmomo_ai_last_run', []);
        $last_webhook = get_option('bitmomo_ai_last_webhook', []);
        $preview = get_option('bitmomo_ai_latest_preview', []);
        $gate = get_option('bitmomo_ai_latest_quality_gate', []);
        $next = wp_next_scheduled(self::HOOK);
        $webhook_configured = defined('BITMOMO_AI_WEBHOOK_TOKEN') && strlen((string) BITMOMO_AI_WEBHOOK_TOKEN) >= 32;
        echo '<div class="wrap"><h1>' . esc_html__('Bitmomo AI Diagnostics', 'bitmomo-ai') . '</h1>';
        $automation = self::automation_health();
        echo '<h2>' . esc_html__('Kesehatan otomasi', 'bitmomo-ai') . '</h2>';
        echo '<p><strong>' . esc_html(ucfirst((string) $automation['state'])) . '</strong> — ' . esc_html($automation['message']) . '</p>';
        echo '<p><strong>' . esc_html__('Jadwal berikutnya:', 'bitmomo-ai') . '</strong> ' . esc_html($automation['next_label']) . '<br><strong>' . esc_html__('Eksekusi terakhir:', 'bitmomo-ai') . '</strong> ' . esc_html($automation['last_label'] . ' · ' . $automation['last_status']) . '<br><strong>' . esc_html__('Pemicu:', 'bitmomo-ai') . '</strong> ' . esc_html($automation['trigger_label']) . '</p>';
        echo '<p><strong>' . esc_html__('TradingView webhook:', 'bitmomo-ai') . '</strong> ' . esc_html($webhook_configured ? 'configured' : 'not configured') . '</p>';
        echo '<p><strong>' . esc_html__('Conditional auto-publish:', 'bitmomo-ai') . '</strong> ' . esc_html(self::auto_publish_enabled() ? 'enabled explicitly (minimum 6/7; critical failures still block)' : 'disabled by safe default or kill switch') . '</p>';
        if ($last_webhook) echo '<p><strong>' . esc_html__('Last TradingView payload:', 'bitmomo-ai') . '</strong> ' . esc_html(($last_webhook['status'] ?? '') . ' — ' . ($last_webhook['message'] ?? '') . ' — ' . ($last_webhook['time'] ?? '')) . '</p>';
        if ($last) echo '<p><strong>' . esc_html__('Last run:', 'bitmomo-ai') . '</strong> ' . esc_html(($last['status'] ?? '') . ' — ' . ($last['message'] ?? '') . ' — ' . ($last['time'] ?? '')) . '</p>';
        $release_status = Bitmomo_AI_Admin_Notices::latest_status();
        echo '<h2>' . esc_html__('Status rilis terbaru', 'bitmomo-ai') . '</h2>';
        echo '<p><strong>' . esc_html(ucfirst((string) ($release_status['state'] ?? 'empty'))) . '</strong> — ' . esc_html((string) ($release_status['message'] ?? __('Belum ada analisis.', 'bitmomo-ai'))) . '</p>';
        if (!empty($release_status['post_id'])) {
            $post_link_label = ($release_status['state'] ?? '') === 'published' ? __('Tinjau analisis terbaru', 'bitmomo-ai') : __('Tinjau draft terbaru', 'bitmomo-ai');
            echo '<p><a class="button" href="' . esc_url(get_edit_post_link($release_status['post_id'])) . '">' . esc_html($post_link_label) . '</a></p>';
        }
        if ($gate) {
            $passed = array_filter((array) ($gate['checks'] ?? []), function ($check) { return !empty($check['passed']); });
            $total = count((array) ($gate['checks'] ?? []));
            echo '<p><strong>' . esc_html__('Quality gate:', 'bitmomo-ai') . '</strong> ' . esc_html(sprintf('%s — %d/%d checks passed — %s', $gate['status'] ?? 'unknown', count($passed), $total, $gate['checked_at'] ?? '')) . '</p>';
            if (($gate['status'] ?? '') !== 'passed') {
                echo '<ul>';
                foreach ((array) ($gate['errors'] ?? []) as $error) echo '<li>' . esc_html($error) . '</li>';
                echo '</ul>';
            }
        }
        $publish_log = get_option(self::PUBLISH_LOG_OPTION, []);
        echo '<h2>' . esc_html__('Auto-publish audit log', 'bitmomo-ai') . '</h2>';
        if ($publish_log && is_array($publish_log)) {
            echo '<ol>';
            foreach (array_slice($publish_log, 0, 10) as $entry) {
                echo '<li>' . esc_html(sprintf('%s — %s — post %d — quality %s — %s', $entry['time'] ?? '', $entry['status'] ?? '', (int) ($entry['post_id'] ?? 0), $entry['quality_status'] ?? 'unknown', $entry['message'] ?? '')) . '</li>';
            }
            echo '</ol>';
        } else {
            echo '<p>' . esc_html__('Belum ada percobaan auto-publish.', 'bitmomo-ai') . '</p>';
        }
        $performance = Bitmomo_AI_Performance::summary();
        $corpus = Bitmomo_AI_Performance::corpus_diagnostics();
        echo '<h2>' . esc_html__('Signal corpus', 'bitmomo-ai') . '</h2>';
        echo '<p><strong>' . esc_html(sprintf('%d any-status records', $corpus['total'])) . '</strong> — ' . esc_html('post statuses: ' . wp_json_encode($corpus['post_statuses']) . ' — settlement: ' . wp_json_encode($corpus['settlement_states']) . ' — raw inputs: ' . $corpus['with_input_snapshot'] . ' — axes: ' . $corpus['with_axis_snapshot'] . ' — earliest: ' . ($corpus['earliest_record'] ?: 'none') . ' — latest: ' . ($corpus['latest_record'] ?: 'none')) . '</p>';
        echo '<h2>' . esc_html__('Forward validation', 'bitmomo-ai') . '</h2>';
        if ($performance['total'] > 0) {
            $accuracy = $performance['accuracy_pct'] === null ? 'belum tersedia' : number_format_i18n($performance['accuracy_pct'], 1) . '%';
            echo '<p>' . esc_html(sprintf('%d hasil sekitar 24 jam — %d benar — %d salah — %d belum meyakinkan — akurasi hasil yang sudah jelas: %s — level risiko tersentuh: %d', $performance['total'], $performance['correct'], $performance['incorrect'], $performance['inconclusive'], $accuracy, $performance['risk_triggered'])) . '</p>';
        } else {
            echo '<p>' . esc_html__('Belum ada hasil sekitar 24 jam. Analisis dievaluasi terlebih dahulu pada session anchor yang sama di hari berikutnya; settlement terjadwal tetap menjadi jalur recovery.', 'bitmomo-ai') . '</p>';
        }
        if (!empty($preview['data']) && !empty($preview['evaluation'])) {
            $summary = Bitmomo_AI_Report::summary($preview['data'], $preview['evaluation']);
            $quality = $preview['evaluation']['quality'] ?? [];
            echo '<h2>' . esc_html__('Latest five-axis preview', 'bitmomo-ai') . '</h2>';
            echo '<p><strong>' . esc_html($summary['headline']) . '</strong> ' . esc_html($summary['context']) . '</p><ul>';
            foreach ($preview['evaluation']['axes'] as $name => $axis) echo '<li><strong>' . esc_html(ucfirst($name)) . ':</strong> ' . esc_html((string) ($axis['score'] ?? 0)) . ' — ' . esc_html((string) ($axis['reason'] ?? '')) . '</li>';
            echo '</ul><p><strong>' . esc_html__('Data quality:', 'bitmomo-ai') . '</strong> ' . esc_html(sprintf('%s — %d%% complete — %d minutes old — %s', $quality['status'] ?? 'unknown', (int) ($quality['completeness_pct'] ?? 0), (int) ($quality['data_age_minutes'] ?? 0), $quality['source'] ?? 'unknown')) . '</p><p>' . esc_html($summary['invalidation']) . '</p>';
        }
        Bitmomo_AI_Scorecard_Repository::render_admin();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bitmomo_ai_run_now"><input type="hidden" name="edition" value="us_pre_open">';
        wp_nonce_field('bitmomo_ai_run_now');
        submit_button(__('Run US Pre-Open staging test now', 'bitmomo-ai'));
        echo '</form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bitmomo_ai_run_now"><input type="hidden" name="edition" value="us_post_close">';
        wp_nonce_field('bitmomo_ai_run_now');
        submit_button(__('Run US Post-Close staging test now', 'bitmomo-ai'), 'secondary');
        echo '</form><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="bitmomo_ai_create_preview">';
        wp_nonce_field('bitmomo_ai_create_preview');
        submit_button(__('Create or update public staging preview', 'bitmomo-ai'), 'secondary');
        echo '</form></div>';
    }

    public static function run_now() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'bitmomo-ai'));
        check_admin_referer('bitmomo_ai_run_now');
        $edition = isset($_POST['edition']) ? sanitize_key(wp_unslash($_POST['edition'])) : Bitmomo_AI_Session_Intelligence::POST_CLOSE;
        self::run($edition);
        wp_safe_redirect(admin_url('tools.php?page=bitmomo-ai'));
        exit;
    }

    public static function create_preview_page() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'bitmomo-ai'));
        check_admin_referer('bitmomo_ai_create_preview');
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        if ($host !== 'seagreen-snail-158456.hostingersite.com') wp_die(esc_html__('Preview page is restricted to the staging host.', 'bitmomo-ai'));
        $existing = get_page_by_path('bitmomo-ai-preview', OBJECT, 'page');
        $postarr = ['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Bitmomo AI Market Preview', 'post_name' => 'bitmomo-ai-preview', 'post_content' => '[bitmomo_ai_dashboard]'];
        if ($existing) $postarr['ID'] = $existing->ID;
        $page_id = wp_insert_post($postarr, true);
        if (is_wp_error($page_id)) wp_die(esc_html($page_id->get_error_message()));
        wp_safe_redirect(get_permalink($page_id));
        exit;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class Bitmomo_Regime_Scheduler {
    const HOOK = 'bitmomo_regime_daily_evaluation';
    const LAST_RUN_OPTION = 'bitmomo_regime_last_run';

    public static function register() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
        add_action( 'admin_post_bitmomo_regime_run_now', array( __CLASS__, 'run_now' ) );
    }

    public static function run_now() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to run this evaluation.', 'bitmomo-regime' ) );
        check_admin_referer( 'bitmomo_regime_run_now' );
        $result = self::run();
        $status = sanitize_key( (string) ( $result['status'] ?? 'unknown' ) );
        wp_safe_redirect( add_query_arg( 'bitmomo_regime_run', $status, admin_url( 'admin.php?page=bitmomo-regime-diagnostics' ) ) );
        exit;
    }

    public static function ensure_schedule() {
        if ( wp_next_scheduled( self::HOOK ) ) return;
        $timezone = new DateTimeZone( 'Asia/Jakarta' );
        $now = new DateTimeImmutable( 'now', $timezone );
        $next = $now->setTime( 19, 25 );
        if ( $next <= $now ) $next = $next->modify( '+1 day' );
        wp_schedule_event( $next->getTimestamp(), 'daily', self::HOOK );
    }

    public static function run() {
        $adapted = Bitmomo_Regime_Runtime_Adapter::current_input();
        if ( ! $adapted['success'] ) return self::record( 'blocked', implode( ' ', $adapted['errors'] ) );

        $input = $adapted['input'];
        $latest = Bitmomo_Regime_State_Store::instance()->get_latest();
        if ( is_array( $latest ) && ! empty( $input['source_record_id'] ) && $input['source_record_id'] === ( $latest['source_record_id'] ?? '' ) ) {
            return self::record( 'duplicate_blocked', 'The canonical source record was already evaluated.', $latest );
        }

        $as_of = ! empty( $input['as_of'] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $input['as_of'] ) ) : current_time( 'mysql' );
        $result = Bitmomo_Regime_State_Store::instance()->evaluate_and_record( $input, $as_of );
        if ( ! $result['success'] ) return self::record( 'blocked', implode( ' ', $result['errors'] ) );
        return self::record( 'success', 'A new append-only Regime state was recorded.', $result['record'] );
    }

    private static function record( $status, $message, $record = null ) {
        $result = array( 'status' => $status, 'message' => $message, 'time' => gmdate( 'c' ), 'record' => $record );
        update_option( self::LAST_RUN_OPTION, $result, false );
        return $result;
    }
}

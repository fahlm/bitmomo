<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks Telegram delivery to the existing canonical Bitmomo daily-generation
 * event without creating a second scheduler.
 *
 * The AI scheduler owns `bitmomo_ai_daily_generation` at its normal priority.
 * This publisher runs later (priority 20), and remains inert unless a separate
 * runtime autopost flag is explicitly enabled.
 */
final class Bitmomo_Btc_Telegram_Publisher {
	const CANONICAL_GENERATION_HOOK = 'bitmomo_ai_daily_generation';
	const PRIORITY                  = 20;
	const LAST_RESULT_OPTION        = 'bitmomo_btc_telegram_last_publish_result';

	public static function auto_publish_enabled() {
		return defined( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED' )
			&& true === BITMOMO_TELEGRAM_AUTOPOST_ENABLED;
	}

	public static function register() {
		add_action(
			self::CANONICAL_GENERATION_HOOK,
			array( __CLASS__, 'after_canonical_daily_generation' ),
			self::PRIORITY
		);
	}

	/**
	 * Runs after the existing canonical post-close generation callback.
	 * No market logic lives here; transport performs preflight and reads the
	 * public-safe canonical formatter only.
	 *
	 * @return array{ok:bool,status:string}
	 */
	public static function after_canonical_daily_generation() {
		if ( ! self::auto_publish_enabled() ) {
			return array( 'ok' => false, 'status' => 'autopost_disabled' );
		}

		if ( ! class_exists( 'Bitmomo_Btc_Telegram_Transport' ) ) {
			return self::record_result( array( 'ok' => false, 'status' => 'transport_unavailable' ) );
		}

		$result = Bitmomo_Btc_Telegram_Transport::send_current_brief();
		return self::record_result( $result );
	}

	private static function record_result( $result ) {
		$result = is_array( $result ) ? $result : array( 'ok' => false, 'status' => 'invalid_result' );
		$safe = array(
			'ok'      => ! empty( $result['ok'] ),
			'status'  => sanitize_key( (string) ( $result['status'] ?? 'unknown' ) ),
			'time'    => gmdate( 'c' ),
			'channel' => Bitmomo_Btc_Telegram_Transport::CHANNEL_USERNAME,
			'bot'     => Bitmomo_Btc_Telegram_Transport::BOT_USERNAME,
		);
		if ( ! empty( $result['message_hash'] ) ) {
			$safe['message_hash'] = sanitize_text_field( (string) $result['message_hash'] );
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_RESULT_OPTION, $safe, false );
		}
		return $safe;
	}
}

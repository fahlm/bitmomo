<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telegram delivery transport for the public BTC Decision Brief.
 *
 * Boundaries:
 * - destination is the canonical public Bitmomo channel @bitmomodaily;
 * - canonical posting bot identity is @Bitmomo_id_bot;
 * - intelligence text is produced only by Bitmomo_Btc_Telegram_Brief;
 * - bot token is a runtime secret and must never live in the repository;
 * - delivery is disabled unless BITMOMO_TELEGRAM_DELIVERY_ENABLED is true;
 * - every live send verifies the runtime token belongs to the canonical bot;
 * - no cron is registered here. Scheduling belongs to a separately reviewed
 *   operational slice after staging contract tests pass.
 */
final class Bitmomo_Btc_Telegram_Transport {
	const CHANNEL_USERNAME = '@bitmomodaily';
	const CHANNEL_URL      = 'https://t.me/bitmomodaily';
	const BOT_USERNAME     = 'Bitmomo_id_bot';
	const BOT_URL          = 'https://t.me/Bitmomo_id_bot';
	const API_BASE         = 'https://api.telegram.org';

	/**
	 * Transport can only become active through explicit runtime configuration.
	 */
	public static function enabled() {
		return defined( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED' )
			&& true === BITMOMO_TELEGRAM_DELIVERY_ENABLED;
	}

	/**
	 * Bot token must be supplied outside Git, normally via wp-config/runtime env.
	 */
	private static function token() {
		if ( ! defined( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ) {
			return '';
		}
		return trim( (string) BITMOMO_TELEGRAM_BOT_TOKEN );
	}

	public static function configured() {
		return self::enabled()
			&& '' !== self::token()
			&& class_exists( 'Bitmomo_Btc_Telegram_Brief' );
	}

	/**
	 * Verify the runtime token belongs to the one approved Bitmomo bot.
	 *
	 * This prevents a valid-but-wrong Telegram token from silently becoming the
	 * publishing identity for Bitmomo. The token itself is never returned.
	 *
	 * @return array{ok:bool,status:string,bot_id?:int,username?:string,http_code?:int,error?:string}
	 */
	public static function verify_bot_identity() {
		if ( ! self::enabled() ) {
			return array( 'ok' => false, 'status' => 'disabled' );
		}

		$token = self::token();
		if ( '' === $token ) {
			return array( 'ok' => false, 'status' => 'not_configured' );
		}

		if ( ! function_exists( 'wp_remote_get' ) ) {
			return array( 'ok' => false, 'status' => 'http_unavailable' );
		}

		$url = self::API_BASE . '/bot' . rawurlencode( $token ) . '/getMe';
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 'request_error',
				'error'  => sanitize_text_field( $response->get_error_code() ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['ok'] ) || ! is_array( $body['result'] ?? null ) ) {
			return array(
				'ok'        => false,
				'status'    => 'identity_unavailable',
				'http_code' => $code,
			);
		}

		$username = sanitize_text_field( (string) ( $body['result']['username'] ?? '' ) );
		if ( '' === $username || 0 !== strcasecmp( self::BOT_USERNAME, $username ) ) {
			return array(
				'ok'       => false,
				'status'   => 'bot_identity_mismatch',
				'username' => $username,
			);
		}

		return array(
			'ok'       => true,
			'status'   => 'verified',
			'bot_id'   => (int) ( $body['result']['id'] ?? 0 ),
			'username' => $username,
		);
	}

	/**
	 * Send the current canonical public-safe brief.
	 *
	 * @return array{ok:bool,status:string,http_code?:int,error?:string}
	 */
	public static function send_current_brief() {
		if ( ! self::enabled() ) {
			return array( 'ok' => false, 'status' => 'disabled' );
		}

		$token = self::token();
		if ( '' === $token ) {
			return array( 'ok' => false, 'status' => 'not_configured' );
		}

		if ( ! class_exists( 'Bitmomo_Btc_Telegram_Brief' ) ) {
			return array( 'ok' => false, 'status' => 'formatter_unavailable' );
		}

		$identity = self::verify_bot_identity();
		if ( empty( $identity['ok'] ) ) {
			return array(
				'ok'     => false,
				'status' => (string) ( $identity['status'] ?? 'bot_identity_unverified' ),
			);
		}

		$message = Bitmomo_Btc_Telegram_Brief::current();
		if ( ! is_string( $message ) || '' === trim( $message ) ) {
			return array( 'ok' => false, 'status' => 'brief_unavailable' );
		}

		if ( ! function_exists( 'wp_remote_post' ) ) {
			return array( 'ok' => false, 'status' => 'http_unavailable' );
		}

		$url = self::API_BASE . '/bot' . rawurlencode( $token ) . '/sendMessage';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 10,
				'body'    => array(
					'chat_id'                  => self::CHANNEL_USERNAME,
					'text'                     => $message,
					'disable_web_page_preview' => true,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 'request_error',
				'error'  => sanitize_text_field( $response->get_error_code() ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'ok'        => false,
				'status'    => 'telegram_rejected',
				'http_code' => $code,
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			return array(
				'ok'        => false,
				'status'    => 'invalid_response',
				'http_code' => $code,
			);
		}

		return array(
			'ok'        => true,
			'status'    => 'sent',
			'http_code' => $code,
		);
	}
}

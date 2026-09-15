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
 * - every live send verifies bot identity + channel posting access first;
 * - duplicate canonical briefs are suppressed by message fingerprint;
 * - no cron is registered here. Scheduling belongs to a separately reviewed
 *   operational slice after staging contract tests pass.
 */
final class Bitmomo_Btc_Telegram_Transport {
	const CHANNEL_USERNAME      = '@bitmomodaily';
	const CHANNEL_URL           = 'https://t.me/bitmomodaily';
	const BOT_USERNAME          = 'Bitmomo_id_bot';
	const BOT_URL               = 'https://t.me/Bitmomo_id_bot';
	const API_BASE              = 'https://api.telegram.org';
	const LAST_SENT_HASH_OPTION = 'bitmomo_btc_telegram_last_sent_hash';

	public static function enabled() {
		return defined( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED' )
			&& true === BITMOMO_TELEGRAM_DELIVERY_ENABLED;
	}

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

		$url      = self::API_BASE . '/bot' . rawurlencode( $token ) . '/getMe';
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
			return array( 'ok' => false, 'status' => 'identity_unavailable', 'http_code' => $code );
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
	 * Verify the canonical bot is actually allowed to publish to @bitmomodaily.
	 *
	 * @return array{ok:bool,status:string,member_status?:string,http_code?:int,error?:string}
	 */
	public static function verify_channel_access( $bot_id = 0 ) {
		if ( ! self::enabled() ) {
			return array( 'ok' => false, 'status' => 'disabled' );
		}

		$identity = $bot_id > 0
			? array( 'ok' => true, 'bot_id' => (int) $bot_id )
			: self::verify_bot_identity();
		if ( empty( $identity['ok'] ) || empty( $identity['bot_id'] ) ) {
			return array( 'ok' => false, 'status' => (string) ( $identity['status'] ?? 'bot_identity_unverified' ) );
		}

		$token = self::token();
		if ( '' === $token || ! function_exists( 'wp_remote_get' ) ) {
			return array( 'ok' => false, 'status' => '' === $token ? 'not_configured' : 'http_unavailable' );
		}

		$url = self::API_BASE . '/bot' . rawurlencode( $token ) . '/getChatMember'
			. '?chat_id=' . rawurlencode( self::CHANNEL_USERNAME )
			. '&user_id=' . rawurlencode( (string) (int) $identity['bot_id'] );
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
			return array( 'ok' => false, 'status' => 'channel_access_unavailable', 'http_code' => $code );
		}

		$member_status = sanitize_key( (string) ( $body['result']['status'] ?? '' ) );
		if ( 'creator' === $member_status ) {
			return array( 'ok' => true, 'status' => 'channel_verified', 'member_status' => $member_status );
		}

		if ( 'administrator' !== $member_status || empty( $body['result']['can_post_messages'] ) ) {
			return array(
				'ok'            => false,
				'status'        => 'channel_post_permission_missing',
				'member_status' => $member_status,
			);
		}

		return array( 'ok' => true, 'status' => 'channel_verified', 'member_status' => $member_status );
	}

	/**
	 * Read-only runtime gate. It does not post anything.
	 *
	 * @return array{ok:bool,status:string,message_hash?:string,bot_username?:string,member_status?:string}
	 */
	public static function preflight() {
		if ( ! self::configured() ) {
			return array( 'ok' => false, 'status' => self::enabled() ? 'not_configured' : 'disabled' );
		}

		$identity = self::verify_bot_identity();
		if ( empty( $identity['ok'] ) ) {
			return array( 'ok' => false, 'status' => (string) ( $identity['status'] ?? 'bot_identity_unverified' ) );
		}

		$channel = self::verify_channel_access( (int) ( $identity['bot_id'] ?? 0 ) );
		if ( empty( $channel['ok'] ) ) {
			return array( 'ok' => false, 'status' => (string) ( $channel['status'] ?? 'channel_unverified' ) );
		}

		$message = Bitmomo_Btc_Telegram_Brief::current();
		if ( ! is_string( $message ) || '' === trim( $message ) ) {
			return array( 'ok' => false, 'status' => 'brief_unavailable' );
		}

		return array(
			'ok'            => true,
			'status'        => 'ready',
			'message_hash'  => hash( 'sha256', $message ),
			'bot_username'  => (string) ( $identity['username'] ?? '' ),
			'member_status' => (string) ( $channel['member_status'] ?? '' ),
		);
	}

	/**
	 * Send the current canonical public-safe brief exactly once per fingerprint.
	 *
	 * @return array{ok:bool,status:string,http_code?:int,error?:string,message_hash?:string}
	 */
	public static function send_current_brief() {
		$preflight = self::preflight();
		if ( empty( $preflight['ok'] ) ) {
			return array( 'ok' => false, 'status' => (string) ( $preflight['status'] ?? 'preflight_failed' ) );
		}

		$message      = Bitmomo_Btc_Telegram_Brief::current();
		$message_hash = (string) ( $preflight['message_hash'] ?? hash( 'sha256', (string) $message ) );
		if ( function_exists( 'get_option' ) ) {
			$last_hash = (string) get_option( self::LAST_SENT_HASH_OPTION, '' );
			if ( '' !== $last_hash && hash_equals( $last_hash, $message_hash ) ) {
				return array( 'ok' => false, 'status' => 'duplicate_brief', 'message_hash' => $message_hash );
			}
		}

		if ( ! function_exists( 'wp_remote_post' ) ) {
			return array( 'ok' => false, 'status' => 'http_unavailable' );
		}

		$token = self::token();
		$url   = self::API_BASE . '/bot' . rawurlencode( $token ) . '/sendMessage';
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
			return array( 'ok' => false, 'status' => 'telegram_rejected', 'http_code' => $code );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			return array( 'ok' => false, 'status' => 'invalid_response', 'http_code' => $code );
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_SENT_HASH_OPTION, $message_hash, false );
		}

		return array(
			'ok'           => true,
			'status'       => 'sent',
			'http_code'    => $code,
			'message_hash' => $message_hash,
		);
	}
}

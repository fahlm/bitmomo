<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telegram delivery transport for the public BTC Decision Brief.
 *
 * Fail-closed boundaries:
 * - destination is the canonical public channel @bitmomodaily;
 * - canonical posting bot identity is @Bitmomo_id_bot;
 * - message content comes only from Bitmomo_Btc_Telegram_Brief;
 * - credentials are runtime-only;
 * - delivery and autopost are independent kill switches;
 * - every send verifies bot identity + channel posting access;
 * - duplicate canonical briefs are fingerprint-suppressed;
 * - no scheduler is created here.
 */
final class Bitmomo_Btc_Telegram_Transport {
	const CHANNEL_USERNAME      = '@bitmomodaily';
	const CHANNEL_URL           = 'https://t.me/bitmomodaily';
	const BOT_USERNAME          = 'Bitmomo_id_bot';
	const BOT_URL               = 'https://t.me/Bitmomo_id_bot';
	const API_BASE              = 'https://api.telegram.org';
	const LAST_SENT_HASH_OPTION = 'bitmomo_btc_telegram_last_sent_hash';
	const STAGING_GUARD_ERROR   = 'bitmomo_staging_outbound_write_disabled';

	public static function enabled() {
		return defined( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED' )
			&& true === BITMOMO_TELEGRAM_DELIVERY_ENABLED;
	}

	public static function staging_canary_enabled() {
		return defined( 'BITMOMO_STAGING_SIDE_EFFECTS_DISABLED' )
			&& true === BITMOMO_STAGING_SIDE_EFFECTS_DISABLED
			&& defined( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED' )
			&& true === BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED
			&& self::enabled();
	}

	/**
	 * Register the one narrow staging exception required for Telegram canary QA.
	 * The normal staging guard stays active for every other destination.
	 */
	public static function register_staging_canary_exception() {
		if ( ! self::staging_canary_enabled() || ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'pre_http_request', array( __CLASS__, 'allow_staging_canary_request' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Clear only Bitmomo's known staging-write guard for the canonical Telegram
	 * API/methods while the explicit staging-canary flag is ON.
	 *
	 * @param mixed  $preempt Existing pre_http_request result.
	 * @param array  $args    WordPress HTTP request args.
	 * @param string $url     Target URL.
	 * @return mixed
	 */
	public static function allow_staging_canary_request( $preempt, $args, $url ) {
		if ( ! self::staging_canary_enabled() || ! function_exists( 'is_wp_error' ) || ! is_wp_error( $preempt ) ) {
			return $preempt;
		}
		if ( self::STAGING_GUARD_ERROR !== $preempt->get_error_code() ) {
			return $preempt;
		}

		$token = self::token();
		if ( '' === $token ) {
			return $preempt;
		}

		$parts = parse_url( (string) $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || 'api.telegram.org' !== strtolower( (string) ( $parts['host'] ?? '' ) ) ) {
			return $preempt;
		}

		$path   = (string) ( $parts['path'] ?? '' );
		$prefix = '/bot' . $token . '/';
		if ( 0 !== strpos( $path, $prefix ) ) {
			return $preempt;
		}

		$method_name = substr( $path, strlen( $prefix ) );
		$http_method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
		$allowed = array(
			'getMe'         => 'GET',
			'getChatMember' => 'GET',
			'sendMessage'   => 'POST',
		);
		if ( ! isset( $allowed[ $method_name ] ) || $allowed[ $method_name ] !== $http_method ) {
			return $preempt;
		}

		// Returning false tells WP HTTP to continue with the real request.
		return false;
	}

	private static function token() {
		if ( ! defined( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ) {
			return '';
		}
		$token = trim( (string) BITMOMO_TELEGRAM_BOT_TOKEN );
		return preg_match( '/^\d+:[A-Za-z0-9_-]{20,}$/', $token ) ? $token : '';
	}

	public static function configured() {
		return self::enabled()
			&& '' !== self::token()
			&& class_exists( 'Bitmomo_Btc_Telegram_Brief' );
	}

	/** Verify the runtime token belongs to the approved Bitmomo bot. */
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

		$url      = self::API_BASE . '/bot' . $token . '/getMe';
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

	/** Verify the canonical bot is allowed to publish to @bitmomodaily. */
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

		$url = self::API_BASE . '/bot' . $token . '/getChatMember'
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

	/** Read-only runtime gate. It does not post anything. */
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

	/** Send the current canonical public-safe brief once per fingerprint. */
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
		$url   = self::API_BASE . '/bot' . $token . '/sendMessage';
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

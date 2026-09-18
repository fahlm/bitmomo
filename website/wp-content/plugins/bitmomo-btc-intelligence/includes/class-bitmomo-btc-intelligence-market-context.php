<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public, read-only market context contract for BTC Intelligence.
 *
 * This class owns market comparison data only. It never recomputes Bitmomo's
 * market thesis, never queries current protected Pro data, and fails closed
 * when an optional provider is unavailable.
 */
final class Bitmomo_Btc_Intelligence_Market_Context {
	const REST_NAMESPACE       = 'bitmomo-btc/v1';
	const REST_ROUTE           = '/market-context';
	const BINANCE_BASE         = 'https://data-api.binance.vision';
	const ALPHA_BASE           = 'https://www.alphavantage.co/query';
	const CACHE_CRYPTO         = 10 * MINUTE_IN_SECONDS;
	const CACHE_GOLD           = 6 * HOUR_IN_SECONDS;
	const CACHE_FAILURE_CRYPTO = 2 * MINUTE_IN_SECONDS;
	const CACHE_FAILURE_GOLD   = 15 * MINUTE_IN_SECONDS;

	private static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ), 30 );
	}

	public static function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_response' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'range' => array(
						'default'           => '30d',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	public static function maybe_enqueue_assets() {
		if ( ! is_page( 'btc-intelligence' ) ) {
			return;
		}

		$js_asset = 'assets/js/market-context-explorer.js';

		// CSS ships inside bitmomo-btc-public.bundle.css. Only the interaction
		// script remains a separate runtime asset.
		wp_enqueue_script(
			'bitmomo-btc-market-context',
			BITMOMO_BTC_INTELLIGENCE_URL . $js_asset,
			array(),
			self::asset_version( $js_asset ),
			true
		);
		wp_localize_script(
			'bitmomo-btc-market-context',
			'BitmomoMarketContext',
			array(
				'endpoint'     => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
				'defaultRange' => '30d',
				'ranges'       => self::allowed_ranges(),
			)
		);
	}

	private static function asset_version( $relative_path ) {
		$path = BITMOMO_BTC_INTELLIGENCE_DIR . ltrim( $relative_path, '/' );
		if ( is_readable( $path ) ) {
			$hash = hash_file( 'sha256', $path );
			if ( is_string( $hash ) && '' !== $hash ) {
				return substr( $hash, 0, 12 );
			}
		}
		return BITMOMO_BTC_INTELLIGENCE_VERSION;
	}

	public static function allowed_ranges() {
		return array(
			'7d'  => '7D',
			'30d' => '30D',
			'90d' => '90D',
			'ytd' => 'YTD',
			'1y'  => '1Y',
		);
	}

	public static function rest_response( $request ) {
		$ranges = self::allowed_ranges();
		$range = is_object( $request ) && method_exists( $request, 'get_param' )
			? sanitize_key( (string) $request->get_param( 'range' ) )
			: '30d';
		if ( ! isset( $ranges[ $range ] ) ) {
			$range = '30d';
		}
		return rest_ensure_response( self::build_contract( $range ) );
	}

	public static function build_contract( $range = '30d' ) {
		$ranges = self::allowed_ranges();
		$range = isset( $ranges[ $range ] ) ? $range : '30d';
		$start_ts = self::range_start_timestamp( $range );
		$series = array();
		foreach ( self::series_catalog() as $id => $definition ) {
			$series[] = 'gold' === $id
				? self::gold_series( $definition, $start_ts )
				: self::binance_series( $definition, $start_ts );
		}

		return array(
			'schema_version' => 1,
			'range'          => $range,
			'normalization'  => 'indexed_100',
			'generated_at'   => gmdate( 'c' ),
			'series'         => $series,
			'markers'        => self::public_markers( $start_ts ),
			'current'        => self::public_current(),
			'pro_overlays'   => array(
				array( 'id' => 'expected_range', 'label' => 'Expected Range', 'available' => false ),
				array( 'id' => 'scenario_map', 'label' => 'Scenario Map', 'available' => false ),
				array( 'id' => 'invalidation', 'label' => 'Invalidation', 'available' => false ),
			),
		);
	}

	private static function series_catalog() {
		return array(
			'btc' => array(
				'id'          => 'btc',
				'label'       => 'BTC',
				'asset_class' => 'crypto',
				'symbol'      => 'BTCUSDT',
				'unit'        => 'USD',
				'provider'    => 'Binance Spot',
				'cadence'     => 'daily close',
			),
			'eth' => array(
				'id'          => 'eth',
				'label'       => 'ETH',
				'asset_class' => 'crypto',
				'symbol'      => 'ETHUSDT',
				'unit'        => 'USD',
				'provider'    => 'Binance Spot',
				'cadence'     => 'daily close',
			),
			'sol' => array(
				'id'          => 'sol',
				'label'       => 'SOL',
				'asset_class' => 'crypto',
				'symbol'      => 'SOLUSDT',
				'unit'        => 'USD',
				'provider'    => 'Binance Spot',
				'cadence'     => 'daily close',
			),
			'gold' => array(
				'id'          => 'gold',
				'label'       => 'Gold',
				'asset_class' => 'commodity',
				'symbol'      => 'GOLD',
				'unit'        => 'USD/oz',
				'provider'    => 'Alpha Vantage',
				'cadence'     => 'daily',
			),
		);
	}

	private static function range_start_timestamp( $range ) {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$today = $now->setTime( 0, 0, 0 );
		switch ( $range ) {
			case '7d':
				return $today->modify( '-7 days' )->getTimestamp();
			case '90d':
				return $today->modify( '-90 days' )->getTimestamp();
			case 'ytd':
				return $today->setDate( (int) $today->format( 'Y' ), 1, 1 )->getTimestamp();
			case '1y':
				return $today->modify( '-1 year' )->getTimestamp();
			case '30d':
			default:
				return $today->modify( '-30 days' )->getTimestamp();
		}
	}

	private static function binance_series( $definition, $start_ts ) {
		$cache_key = 'bm_btc_ctx_' . sanitize_key( $definition['symbol'] ) . '_' . gmdate( 'Ymd', $start_ts );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'symbol'    => $definition['symbol'],
				'interval'  => '1d',
				'startTime' => $start_ts * 1000,
				'limit'     => 500,
			),
			self::BINANCE_BASE . '/api/v3/klines'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 8, 'redirection' => 2 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return self::cache_unavailable( $cache_key, $definition, 'provider_unavailable', self::CACHE_FAILURE_CRYPTO );
		}
		$rows = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $rows ) ) {
			return self::cache_unavailable( $cache_key, $definition, 'invalid_provider_response', self::CACHE_FAILURE_CRYPTO );
		}

		$points = array();
		$now_ms = time() * 1000;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[0], $row[4], $row[6] ) || ! is_numeric( $row[0] ) || ! is_numeric( $row[4] ) || ! is_numeric( $row[6] ) ) {
				continue;
			}
			$ts = (int) floor( (int) $row[0] / 1000 );
			$close_ms = (int) $row[6];
			$value = (float) $row[4];
			if ( $ts < $start_ts || $close_ms > $now_ms || $value <= 0 ) {
				continue;
			}
			$points[] = array( 't' => $ts, 'date' => gmdate( 'Y-m-d', $ts ), 'value' => $value );
		}
		if ( count( $points ) < 2 ) {
			return self::cache_unavailable( $cache_key, $definition, 'insufficient_closed_history', self::CACHE_FAILURE_CRYPTO );
		}

		$result = self::available_series( $definition, $points );
		set_transient( $cache_key, $result, self::CACHE_CRYPTO );
		return $result;
	}

	private static function gold_series( $definition, $start_ts ) {
		$key = self::alpha_vantage_key();
		if ( '' === $key ) {
			return self::unavailable_series( $definition, 'provider_not_configured' );
		}
		$cache_key = 'bm_btc_ctx_gold_' . gmdate( 'Ymd', $start_ts );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'function' => 'GOLD_SILVER_HISTORY',
				'symbol'   => 'GOLD',
				'interval' => 'daily',
				'apikey'   => $key,
			),
			self::ALPHA_BASE
		);
		$response = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 2 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return self::cache_unavailable( $cache_key, $definition, 'provider_unavailable', self::CACHE_FAILURE_GOLD );
		}
		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $payload ) || ! empty( $payload['Information'] ) || ! empty( $payload['Note'] ) || ! empty( $payload['Error Message'] ) ) {
			return self::cache_unavailable( $cache_key, $definition, 'provider_unavailable', self::CACHE_FAILURE_GOLD );
		}
		$rows = is_array( $payload['data'] ?? null ) ? $payload['data'] : array();
		$points = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$date = sanitize_text_field( (string) ( $row['date'] ?? '' ) );
			$value = $row['value'] ?? null;
			$ts = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? strtotime( $date . ' 00:00:00 UTC' ) : false;
			if ( ! $ts || $ts < $start_ts || ! is_numeric( $value ) || (float) $value <= 0 ) {
				continue;
			}
			$points[] = array( 't' => (int) $ts, 'date' => $date, 'value' => (float) $value );
		}
		usort( $points, static function( $a, $b ) { return $a['t'] <=> $b['t']; } );
		if ( count( $points ) < 2 ) {
			return self::cache_unavailable( $cache_key, $definition, 'insufficient_history', self::CACHE_FAILURE_GOLD );
		}

		$result = self::available_series( $definition, $points );
		set_transient( $cache_key, $result, self::CACHE_GOLD );
		return $result;
	}

	private static function alpha_vantage_key() {
		$key = defined( 'BITMOMO_ALPHA_VANTAGE_API_KEY' ) ? (string) BITMOMO_ALPHA_VANTAGE_API_KEY : '';
		$key = (string) apply_filters( 'bitmomo_market_context_alpha_vantage_api_key', $key );
		return trim( $key );
	}

	private static function available_series( $definition, $points ) {
		$last = end( $points );
		return array(
			'id'          => $definition['id'],
			'label'       => $definition['label'],
			'asset_class' => $definition['asset_class'],
			'unit'        => $definition['unit'],
			'provider'    => $definition['provider'],
			'cadence'     => $definition['cadence'],
			'status'      => 'available',
			'as_of'       => gmdate( 'c', (int) $last['t'] ),
			'points'      => array_values( $points ),
		);
	}

	private static function cache_unavailable( $cache_key, $definition, $reason, $ttl ) {
		$result = self::unavailable_series( $definition, $reason );
		set_transient( $cache_key, $result, max( 30, (int) $ttl ) );
		return $result;
	}

	private static function unavailable_series( $definition, $reason ) {
		return array(
			'id'          => $definition['id'],
			'label'       => $definition['label'],
			'asset_class' => $definition['asset_class'],
			'unit'        => $definition['unit'],
			'provider'    => $definition['provider'],
			'cadence'     => $definition['cadence'],
			'status'      => 'unavailable',
			'reason'      => sanitize_key( $reason ),
			'as_of'       => null,
			'points'      => array(),
		);
	}

	private static function public_current() {
		if ( ! class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) ) {
			return array( 'status' => 'unavailable' );
		}
		$snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		if ( ! is_array( $snapshot ) ) {
			return array( 'status' => 'unavailable' );
		}
		$session = is_array( $snapshot['session_intelligence'] ?? null ) ? $snapshot['session_intelligence'] : array();
		return array(
			'status'                 => 'available',
			'price'                  => is_numeric( $snapshot['btc_reference_price'] ?? null ) ? (float) $snapshot['btc_reference_price'] : null,
			'market_state'           => sanitize_key( (string) ( $snapshot['market_state'] ?? '' ) ),
			'market_state_certainty' => isset( $snapshot['market_state_certainty'] ) ? (int) $snapshot['market_state_certainty'] : null,
			'directional_bias'       => sanitize_key( (string) ( $snapshot['directional_bias'] ?? '' ) ),
			'direction_strength'     => sanitize_key( (string) ( $snapshot['direction_strength'] ?? '' ) ),
			'confidence'             => isset( $snapshot['confidence']['value'] ) ? (int) $snapshot['confidence']['value'] : null,
			'key_drivers'            => array_slice( array_values( (array) ( $snapshot['key_drivers'] ?? array() ) ), 0, 2 ),
			'what_changed'           => self::public_changes( $session ),
			'freshness'              => is_array( $snapshot['freshness'] ?? null ) ? $snapshot['freshness'] : array(),
		);
	}

	private static function public_changes( $session ) {
		$comparison = is_array( $session['comparison'] ?? null ) ? $session['comparison'] : array();
		$changes = is_array( $session['what_changed'] ?? null ) ? $session['what_changed'] : array();
		if ( 'compared' !== ( $comparison['status'] ?? '' ) ) {
			return array();
		}
		$out = array();
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) ) {
				continue;
			}
			$field = sanitize_key( (string) ( $change['field'] ?? '' ) );
			if ( ! in_array( $field, array( 'directional_bias', 'direction_strength', 'confidence', 'opportunity_state' ), true ) ) {
				continue;
			}
			$out[] = array(
				'field' => $field,
				'from'  => sanitize_text_field( (string) ( $change['from'] ?? '' ) ),
				'to'    => sanitize_text_field( (string) ( $change['to'] ?? '' ) ),
			);
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	private static function public_markers( $start_ts ) {
		$markers = array();
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) ) {
			$history = Bitmomo_Public_Intelligence_Adapter::history();
			$days = is_array( $history['days'] ?? null ) ? $history['days'] : array();
			$previous = null;
			foreach ( $days as $day ) {
				$date = sanitize_text_field( (string) ( $day['date'] ?? '' ) );
				$ts = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? strtotime( $date . ' 00:00:00 UTC' ) : false;
				if ( ! $ts || $ts < $start_ts ) {
					$previous = is_array( $day ) ? $day : $previous;
					continue;
				}
				$bias = sanitize_key( (string) ( $day['directional_bias'] ?? '' ) );
				$state = sanitize_key( (string) ( $day['market_state'] ?? '' ) );
				$changed = null !== $previous && (
					$bias !== sanitize_key( (string) ( $previous['directional_bias'] ?? '' ) ) ||
					$state !== sanitize_key( (string) ( $previous['market_state'] ?? '' ) )
				);
				if ( $changed ) {
					$markers[] = array(
						'type'         => 'state',
						't'            => (int) $ts,
						'date'         => $date,
						'label'        => 'Thesis state changed',
						'bias'         => $bias,
						'market_state' => $state,
					);
				}
				$previous = $day;
			}
		}

		if ( class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) ) {
			$ledger = Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 20 );
			foreach ( (array) ( $ledger['rows'] ?? array() ) as $row ) {
				$generated = sanitize_text_field( (string) ( $row['generated_at'] ?? '' ) );
				$ts = strtotime( $generated );
				if ( ! $ts || $ts < $start_ts ) {
					continue;
				}
				$markers[] = array(
					'type'       => 'decision',
					't'          => (int) $ts,
					'date'       => gmdate( 'Y-m-d', $ts ),
					'label'      => 'Decision Ledger',
					'bias'       => sanitize_key( (string) ( $row['direction'] ?? '' ) ),
					'confidence' => isset( $row['confidence'] ) ? (int) $row['confidence'] : null,
					'price'      => is_numeric( $row['reference_price'] ?? null ) ? (float) $row['reference_price'] : null,
					'verdict'    => sanitize_key( (string) ( $row['verdict'] ?? '' ) ),
				);
			}
		}

		usort( $markers, static function( $a, $b ) { return $a['t'] <=> $b['t']; } );
		return $markers;
	}
}

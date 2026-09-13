<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only accountability boundary for the public BTC Intelligence surface.
 *
 * This class deliberately owns the cross-plugin join between canonical live
 * Bitmomo AI records and the immutable/frozen Bitmomo Pro brief history. It
 * never computes a new market view, never changes an outcome, and never
 * exposes raw factors, private notes, source record IDs, current Pro content,
 * entitlement state, or user data.
 */
final class Bitmomo_Btc_Intelligence_Accountability {
	const LEDGER_LIMIT_DEFAULT = 12;
	const LEDGER_QUERY_LIMIT   = 80;
	const PROOF_LIMIT_DEFAULT  = 3;
	const PROOF_QUERY_LIMIT    = 30;
	const PROOF_DELAY_HOURS    = 48;

	private static $ledger_cache = array();
	private static $proof_cache  = array();

	/**
	 * Recent matured public decisions, newest first.
	 *
	 * Inclusion policy is result-neutral: rows are filtered by provenance and
	 * maturity only. Both evaluated and missed-settlement-window records remain
	 * visible so the public surface cannot cherry-pick only successful outcomes.
	 */
	public static function decision_ledger( $limit = self::LEDGER_LIMIT_DEFAULT ) {
		$limit = max( 1, min( 20, (int) $limit ) );
		if ( isset( self::$ledger_cache[ $limit ] ) ) {
			return self::$ledger_cache[ $limit ];
		}

		$contract = array(
			'provenance'       => 'recorded_live_matured_outcomes',
			'policy'           => 'RECENT_MATURED_NO_RESULT_FILTER',
			'evaluation_window'=> '+24h',
			'rows'             => array(),
			'evaluated_n'      => 0,
			'unscored_n'       => 0,
		);

		if ( ! class_exists( 'Bitmomo_AI_Content_Types' ) || ! post_type_exists( Bitmomo_AI_Content_Types::SIGNAL ) ) {
			self::$ledger_cache[ $limit ] = $contract;
			return $contract;
		}

		$regime_index = self::recorded_live_regime_index();
		if ( ! $regime_index ) {
			self::$ledger_cache[ $limit ] = $contract;
			return $contract;
		}

		$ids = get_posts(
			array(
				'post_type'      => Bitmomo_AI_Content_Types::SIGNAL,
				'post_status'    => 'any',
				'posts_per_page' => self::LEDGER_QUERY_LIMIT,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		foreach ( (array) $ids as $id ) {
			$status = sanitize_key( (string) get_post_meta( $id, '_bm_outcome_status', true ) );
			if ( ! in_array( $status, array( 'evaluated', 'window_missed' ), true ) ) {
				continue;
			}

			$source_id = self::normalize_source_id( get_post_meta( $id, '_bm_source_record_id', true ) );
			if ( '' === $source_id || ! isset( $regime_index[ $source_id ] ) ) {
				continue;
			}

			$generated_at = sanitize_text_field( (string) get_post_meta( $id, '_bm_generated_at', true ) );
			$generated_ts = strtotime( $generated_at );
			if ( ! $generated_ts || $generated_ts > time() + ( 5 * MINUTE_IN_SECONDS ) ) {
				continue;
			}

			$direction = sanitize_key( (string) get_post_meta( $id, '_bm_direction', true ) );
			if ( ! in_array( $direction, array( 'bullish', 'neutral', 'bearish' ), true ) ) {
				continue;
			}

			$outcome_direction = sanitize_key( (string) get_post_meta( $id, '_bm_outcome_direction', true ) );
			if ( 'evaluated' === $status && ! in_array( $outcome_direction, array( 'correct', 'incorrect', 'inconclusive' ), true ) ) {
				continue;
			}

			$entry_price = get_post_meta( $id, '_bm_market_price', true );
			$return_pct  = get_post_meta( $id, '_bm_outcome_return_pct', true );
			$high_24h    = get_post_meta( $id, '_bm_outcome_high_24h', true );
			$low_24h     = get_post_meta( $id, '_bm_outcome_low_24h', true );
			$confidence  = max( 0, min( 100, (int) get_post_meta( $id, '_bm_confidence', true ) ) );
			$edition     = sanitize_key( (string) get_post_meta( $id, '_bm_edition', true ) );
			$method      = sanitize_key( (string) get_post_meta( $id, '_bm_outcome_methodology', true ) );
			$regime      = $regime_index[ $source_id ];

			$row = array(
				'id'                 => substr( hash( 'sha256', $id . '|' . $generated_at ), 0, 12 ),
				'generated_at'       => gmdate( 'c', $generated_ts ),
				'direction'          => $direction,
				'confidence'         => $confidence,
				'market_state'       => $regime,
				'session'            => self::session_label( $edition ),
				'reference_price'    => is_numeric( $entry_price ) && (float) $entry_price > 0 ? (float) $entry_price : null,
				'outcome_status'     => $status,
				'outcome_direction'  => 'evaluated' === $status ? $outcome_direction : null,
				'verdict'            => self::verdict( $status, $outcome_direction ),
				'forward_return_pct' => 'evaluated' === $status && is_numeric( $return_pct ) ? (float) $return_pct : null,
				'high_24h'           => 'evaluated' === $status && is_numeric( $high_24h ) && (float) $high_24h > 0 ? (float) $high_24h : null,
				'low_24h'            => 'evaluated' === $status && is_numeric( $low_24h ) && (float) $low_24h > 0 ? (float) $low_24h : null,
				'outcome_methodology'=> '' !== $method ? $method : 'legacy-window-v1',
			);

			$contract['rows'][] = $row;
			if ( 'evaluated' === $status ) {
				$contract['evaluated_n']++;
			} else {
				$contract['unscored_n']++;
			}

			if ( count( $contract['rows'] ) >= $limit ) {
				break;
			}
		}

		self::$ledger_cache[ $limit ] = $contract;
		return $contract;
	}

	/**
	 * Public proof from historical Pro briefs.
	 *
	 * Only the immutable snapshot frozen at publish time is exposed. A brief
	 * must be published, at least 48 hours old, and have reached a matured
	 * settlement state. This intentionally prevents the current paid brief (or
	 * any recently paid edge) from leaking onto the free surface.
	 */
	public static function delayed_proof( $limit = self::PROOF_LIMIT_DEFAULT ) {
		$limit = max( 1, min( 6, (int) $limit ) );
		if ( isset( self::$proof_cache[ $limit ] ) ) {
			return self::$proof_cache[ $limit ];
		}

		$contract = array(
			'provenance'  => 'frozen_published_pro_briefs',
			'policy'      => 'DELAYED_PUBLIC_PROOF_V1',
			'delay_hours' => self::PROOF_DELAY_HOURS,
			'rows'        => array(),
		);

		if ( ! class_exists( 'Bitmomo_Pro_Briefs' ) || ! class_exists( 'Bitmomo_Pro_Performance' ) || ! post_type_exists( Bitmomo_Pro_Briefs::POST_TYPE ) ) {
			self::$proof_cache[ $limit ] = $contract;
			return $contract;
		}

		$ids = get_posts(
			array(
				'post_type'      => Bitmomo_Pro_Briefs::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::PROOF_QUERY_LIMIT,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		foreach ( (array) $ids as $id ) {
			$original = json_decode( (string) get_post_meta( $id, Bitmomo_Pro_Performance::ORIGINAL_META, true ), true );
			if ( ! is_array( $original ) ) {
				continue;
			}

			$published_at = sanitize_text_field( (string) ( $original['published_at'] ?? '' ) );
			$published_ts = strtotime( $published_at );
			if ( ! $published_ts || $published_ts > time() ) {
				continue;
			}
			$age_hours = ( time() - $published_ts ) / HOUR_IN_SECONDS;
			if ( $age_hours < self::PROOF_DELAY_HOURS ) {
				continue;
			}

			$evaluation_status = sanitize_key( (string) get_post_meta( $id, '_bitmomo_pro_evaluation_status', true ) );
			if ( ! in_array( $evaluation_status, array( 'evaluated', 'window_missed' ), true ) ) {
				continue;
			}

			$state = sanitize_key( (string) ( $original['market_state'] ?? '' ) );
			if ( ! in_array( $state, array( 'bullish', 'neutral', 'bearish' ), true ) ) {
				continue;
			}

			$reference = self::positive_number_or_null( $original['btc_reference_price'] ?? null );
			$range_low = self::positive_number_or_null( $original['expected_range_low'] ?? null );
			$range_high = self::positive_number_or_null( $original['expected_range_high'] ?? null );
			$base = sanitize_textarea_field( (string) ( $original['base_scenario'] ?? '' ) );
			$invalidation = sanitize_textarea_field( (string) ( $original['invalidation'] ?? '' ) );
			if ( null === $reference || null === $range_low || null === $range_high || $range_high < $range_low || '' === $base || '' === $invalidation ) {
				continue;
			}

			$outcome_result = sanitize_key( (string) get_post_meta( $id, '_bitmomo_pro_outcome_market_state', true ) );
			if ( 'evaluated' === $evaluation_status && ! in_array( $outcome_result, array( 'correct', 'incorrect', 'inconclusive' ), true ) ) {
				continue;
			}

			$return_pct = get_post_meta( $id, '_bitmomo_pro_outcome_return_pct', true );
			$outcome_price = get_post_meta( $id, '_bitmomo_pro_outcome_price_24h', true );
			$high_24h = get_post_meta( $id, '_bitmomo_pro_outcome_high_24h', true );
			$low_24h = get_post_meta( $id, '_bitmomo_pro_outcome_low_24h', true );
			$range_hit = sanitize_key( (string) get_post_meta( $id, '_bitmomo_pro_outcome_range_hit', true ) );
			$data_timestamp = sanitize_text_field( (string) ( $original['data_timestamp'] ?? '' ) );
			$data_ts = strtotime( $data_timestamp );

			$contract['rows'][] = array(
				'id'                     => substr( hash( 'sha256', 'pro|' . $id . '|' . $published_at ), 0, 12 ),
				'published_at'           => gmdate( 'c', $published_ts ),
				'data_timestamp'         => $data_ts ? gmdate( 'c', $data_ts ) : null,
				'age_hours'              => (int) floor( $age_hours ),
				'market_state'           => $state,
				'confidence'             => max( 0, min( 100, (int) ( $original['confidence'] ?? 0 ) ) ),
				'reference_price'        => $reference,
				'expected_range_low'     => $range_low,
				'expected_range_high'    => $range_high,
				'base_scenario'          => $base,
				'bull_scenario'          => sanitize_textarea_field( (string) ( $original['bull_scenario'] ?? '' ) ),
				'bear_scenario'          => sanitize_textarea_field( (string) ( $original['bear_scenario'] ?? '' ) ),
				'invalidation'           => $invalidation,
				'what_changed'           => sanitize_textarea_field( (string) ( $original['what_changed'] ?? '' ) ),
				'evaluation_status'      => $evaluation_status,
				'verdict'                => self::verdict( $evaluation_status, $outcome_result ),
				'outcome_return_pct'     => 'evaluated' === $evaluation_status && is_numeric( $return_pct ) ? (float) $return_pct : null,
				'outcome_price_24h'      => 'evaluated' === $evaluation_status ? self::positive_number_or_null( $outcome_price ) : null,
				'outcome_high_24h'       => 'evaluated' === $evaluation_status ? self::positive_number_or_null( $high_24h ) : null,
				'outcome_low_24h'        => 'evaluated' === $evaluation_status ? self::positive_number_or_null( $low_24h ) : null,
				'range_hit'              => 'evaluated' === $evaluation_status && in_array( $range_hit, array( 'yes', 'no' ), true ) ? $range_hit : null,
			);

			if ( count( $contract['rows'] ) >= $limit ) {
				break;
			}
		}

		self::$proof_cache[ $limit ] = $contract;
		return $contract;
	}

	private static function recorded_live_regime_index() {
		if ( ! post_type_exists( 'bm_regime_state' ) ) {
			return array();
		}

		$index = array();
		$ids = get_posts(
			array(
				'post_type'      => 'bm_regime_state',
				'post_status'    => 'publish',
				'posts_per_page' => self::LEDGER_QUERY_LIMIT * 2,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		foreach ( (array) $ids as $id ) {
			if ( 'recorded_live' !== (string) get_post_meta( $id, '_bitmomo_regime_provenance', true ) ) {
				continue;
			}
			$source = self::normalize_source_id( get_post_meta( $id, '_bitmomo_regime_source_record_id', true ) );
			$state = sanitize_key( (string) get_post_meta( $id, '_bitmomo_regime_regime', true ) );
			if ( '' !== $source && in_array( $state, array( 'accumulation', 'expansion', 'distribution', 'capitulation', 'transition' ), true ) ) {
				$index[ $source ] = $state;
			}
		}
		return $index;
	}

	private static function normalize_source_id( $value ) {
		$value = sanitize_text_field( (string) $value );
		return str_replace( 'bitmomo-ai:regime:', 'bitmomo-ai:', $value );
	}

	private static function positive_number_or_null( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	private static function session_label( $edition ) {
		$labels = array(
			'morning'       => 'Morning',
			'us_pre_open'   => 'US Pre-open',
			'us_session'    => 'US Session',
			'us_post_close' => 'US Post-close',
		);
		return $labels[ sanitize_key( (string) $edition ) ] ?? 'Market update';
	}

	private static function verdict( $status, $outcome ) {
		if ( 'window_missed' === $status ) {
			return 'unscored';
		}
		$map = array(
			'correct'      => 'aligned',
			'incorrect'    => 'missed',
			'inconclusive' => 'inconclusive',
		);
		return $map[ sanitize_key( (string) $outcome ) ] ?? 'unscored';
	}
}

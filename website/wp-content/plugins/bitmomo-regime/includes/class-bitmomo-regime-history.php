<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 30-day frontend-safe history projection for Product A (REGIME PR 3).
 * Missing history is never fabricated and multiple editions are collapsed
 * onto one canonical US market day, with the US Session record preferred.
 */
class Bitmomo_Regime_History {
	const ALLOWED_DAYS = array( 1, 7, 14, 30 );
	const DEFAULT_DAYS = 7;
	const TARGET_DAYS = 30;
	const MARKET_TIMEZONE = 'America/New_York';

	public static function for_frontend( array $records, $requested_days = self::DEFAULT_DAYS ) {
		$requested_days = in_array( $requested_days, self::ALLOWED_DAYS, true ) ? (int) $requested_days : self::DEFAULT_DAYS;

		$official = array();
		foreach ( $records as $record ) {
			$date_key = self::market_date( $record );
			if ( '' === $date_key ) continue;
			$edition = (string) ( $record['edition'] ?? '' );
			$current_edition = isset( $official[ $date_key ] ) ? (string) ( $official[ $date_key ]['edition'] ?? '' ) : '';
			$is_us_session = in_array( $edition, array( 'us_session', 'us_post_close' ), true );
			$current_is_us_session = in_array( $current_edition, array( 'us_session', 'us_post_close' ), true );
			if ( ! isset( $official[ $date_key ] ) || ( $is_us_session && ! $current_is_us_session ) ) {
				$record['_bitmomo_public_market_date'] = $date_key;
				$official[ $date_key ] = $record;
			}
		}
		krsort( $official );
		$official = array_slice( $official, 0, self::TARGET_DAYS, true );
		$chronological = array_reverse( array_values( $official ) );
		$available = count( $chronological );
		$slice = $requested_days >= $available ? $chronological : array_slice( $chronological, $available - $requested_days );

		$days = array();
		foreach ( $slice as $record ) $days[] = self::project( $record );

		return array(
			'requested_days' => $requested_days,
			'target_days' => self::TARGET_DAYS,
			'available_days' => $available,
			'days' => $days,
		);
	}

	/**
	 * Return the market day the edition belongs to, not the UTC date on which
	 * the post-close snapshot happened to be stored. Canonical v2 edition ids
	 * embed the America/New_York session anchor and are the strongest source.
	 */
	public static function market_date( $record ) {
		$source_id = (string) ( $record['source_record_id'] ?? '' );
		if ( preg_match( '/^bitmomo-ai:(\d{4})(\d{2})(\d{2})T/', $source_id, $match ) ) {
			return $match[1] . '-' . $match[2] . '-' . $match[3];
		}
		if ( preg_match( '/^bitmomo-ai:regime:(\d{4}-\d{2}-\d{2})(?::|$)/', $source_id, $match ) ) {
			return $match[1];
		}

		$anchor = trim( (string) ( $record['session_anchor'] ?? '' ) );
		if ( '' !== $anchor ) {
			try {
				return ( new DateTimeImmutable( $anchor ) )->setTimezone( new DateTimeZone( self::MARKET_TIMEZONE ) )->format( 'Y-m-d' );
			} catch ( Exception $exception ) {}
		}

		$raw = trim( (string) ( $record['as_of'] ?? ( $record['date'] ?? '' ) ) );
		if ( '' === $raw ) return '';
		try {
			// Current Regime scheduler persists as_of with gmdate(), so a value
			// without an offset is explicitly interpreted as UTC here.
			$timezone = preg_match( '/(?:Z|[+-]\d{2}:?\d{2})$/i', $raw ) ? null : new DateTimeZone( 'UTC' );
			$date = null === $timezone ? new DateTimeImmutable( $raw ) : new DateTimeImmutable( $raw, $timezone );
			return $date->setTimezone( new DateTimeZone( self::MARKET_TIMEZONE ) )->format( 'Y-m-d' );
		} catch ( Exception $exception ) {
			return '';
		}
	}

	private static function project( $record ) {
		$date = (string) ( $record['_bitmomo_public_market_date'] ?? self::market_date( $record ) );
		return array(
			'date' => $date,
			'regime' => isset( $record['regime'] ) ? $record['regime'] : null,
			'regime_label_id' => Bitmomo_Regime_Taxonomy::regime_label_id( isset( $record['regime'] ) ? $record['regime'] : '' ),
			'regime_label_en' => Bitmomo_Regime_Taxonomy::regime_label_en( isset( $record['regime'] ) ? $record['regime'] : '' ),
			'directional_bias' => isset( $record['directional_bias'] ) ? $record['directional_bias'] : null,
			'regime_confidence' => isset( $record['regime_confidence'] ) ? $record['regime_confidence'] : null,
		);
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized input contract for Product A: Bitmomo Market Regime.
 *
 * This class does not fetch or compute anything from raw market data —
 * that is Codex's runtime job (see the Codex handoff contract in this
 * PR's report). It only defines the shape Codex's feed must normalize
 * into, and validates/normalizes a raw associative array against that
 * shape. Pure function, no WordPress dependency beyond the ABSPATH guard,
 * no I/O, directly unit testable.
 *
 * Deliberately market/derivatives-structure-only for V1 — no ETF flows,
 * macro, or news inputs here (per the PR spec; those are Watchtower's
 * domain, Product B, not Product A's regime classifier).
 */
class Bitmomo_Regime_Input {

	/**
	 * Required — a record missing any of these fails validation outright.
	 * All numeric except structure_state and directional_bias.
	 */
	const REQUIRED_FIELDS = array(
		'return_1d',              // % return, 1 day.
		'return_7d',               // % return, 7 days.
		'return_30d',              // % return, 30 days.
		'volatility_percentile',   // 0-100: realized vol percentile vs trailing lookback.
		'volatility_change',       // percentage-point delta in that percentile, can be negative.
		'volume_percentile',       // 0-100: volume percentile vs trailing lookback.
		'momentum_score',          // -100..100: signed momentum indicator, caller-derived.
		'range_position_pct',      // 0-100: where price sits within its recent (e.g. 30D) range.
		'structure_state',         // one of STRUCTURE_STATES.
		'directional_bias',        // one of Bitmomo_Regime_Taxonomy::BIASES — passthrough only.
	);

	/**
	 * Optional — null (not fabricated, not defaulted to a non-null value)
	 * when the caller has no data for it yet. The classifier must degrade
	 * gracefully, never error, when any of these are null.
	 */
	const OPTIONAL_FIELDS = array(
		'open_interest_change_pct', // % change in open interest.
		'funding_rate_pct',         // e.g. 0.01 = 1% per funding period.
		'basis_pct',                 // futures basis, % annualized. Reserved for a future rule; not yet scored in V1.
		'liquidation_pressure',      // one of LIQUIDATION_PRESSURES.
		'crowding_score',            // 0-100: positioning-crowding indicator.
		'directional_confidence',    // 0-100: confidence of the passthrough directional_bias, if supplied.
		'source_record_id',          // string, for traceability once persisted (PR2).
		'as_of',                     // string timestamp, for traceability once persisted (PR2).
		'edition',
		'provenance',
	);

	const STRUCTURE_STATES = array( 'range', 'breakout_up', 'breakout_down', 'breakdown', 'unknown' );

	const LIQUIDATION_PRESSURES = array( 'none', 'elevated', 'extreme' );

	const PERCENTILE_FIELDS = array( 'volatility_percentile', 'volume_percentile', 'range_position_pct' );

	const NUMERIC_REQUIRED_FIELDS = array(
		'return_1d',
		'return_7d',
		'return_30d',
		'volatility_percentile',
		'volatility_change',
		'volume_percentile',
		'momentum_score',
		'range_position_pct',
	);

	const STRING_OPTIONAL_FIELDS = array( 'liquidation_pressure', 'source_record_id', 'as_of', 'edition', 'provenance' );

	/**
	 * Validates + normalizes a raw input array. Never fabricates a partial
	 * record — either every required field is present and well-formed and
	 * a fully normalized array is returned, or nothing is returned at all.
	 *
	 * @param mixed $raw
	 * @return array{valid:bool,errors:string[],input:?array}
	 */
	public static function validate( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$errors = array();

		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $raw ) || null === $raw[ $field ] || '' === $raw[ $field ] ) {
				$errors[] = "Missing required field: {$field}";
			}
		}

		foreach ( self::NUMERIC_REQUIRED_FIELDS as $field ) {
			if ( array_key_exists( $field, $raw ) && '' !== $raw[ $field ] && null !== $raw[ $field ] && ! is_numeric( $raw[ $field ] ) ) {
				$errors[] = "Field {$field} must be numeric.";
			}
		}

		foreach ( self::PERCENTILE_FIELDS as $field ) {
			if ( array_key_exists( $field, $raw ) && is_numeric( $raw[ $field ] ) ) {
				$value = (float) $raw[ $field ];
				if ( $value < 0 || $value > 100 ) {
					$errors[] = "Field {$field} must be within 0-100.";
				}
			}
		}

		if ( array_key_exists( 'structure_state', $raw ) && '' !== $raw['structure_state'] && ! in_array( $raw['structure_state'], self::STRUCTURE_STATES, true ) ) {
			$errors[] = 'Field structure_state has an invalid value.';
		}

		if ( array_key_exists( 'directional_bias', $raw ) && '' !== $raw['directional_bias'] && ! Bitmomo_Regime_Taxonomy::is_valid_bias( $raw['directional_bias'] ) ) {
			$errors[] = 'Field directional_bias has an invalid value.';
		}

		if ( array_key_exists( 'liquidation_pressure', $raw ) && null !== $raw['liquidation_pressure'] && '' !== $raw['liquidation_pressure'] && ! in_array( $raw['liquidation_pressure'], self::LIQUIDATION_PRESSURES, true ) ) {
			$errors[] = 'Field liquidation_pressure has an invalid value.';
		}

		foreach ( array( 'crowding_score', 'directional_confidence' ) as $field ) {
			if ( array_key_exists( $field, $raw ) && null !== $raw[ $field ] && '' !== $raw[ $field ] ) {
				if ( ! is_numeric( $raw[ $field ] ) || (float) $raw[ $field ] < 0 || (float) $raw[ $field ] > 100 ) {
					$errors[] = "Field {$field} must be numeric within 0-100.";
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'valid'  => false,
				'errors' => $errors,
				'input'  => null,
			);
		}

		$normalized = array();
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( in_array( $field, array( 'structure_state', 'directional_bias' ), true ) ) {
				$normalized[ $field ] = (string) $raw[ $field ];
			} else {
				$normalized[ $field ] = (float) $raw[ $field ];
			}
		}

		foreach ( self::OPTIONAL_FIELDS as $field ) {
			if ( array_key_exists( $field, $raw ) && null !== $raw[ $field ] && '' !== $raw[ $field ] ) {
				$normalized[ $field ] = in_array( $field, self::STRING_OPTIONAL_FIELDS, true ) ? (string) $raw[ $field ] : (float) $raw[ $field ];
			} else {
				$normalized[ $field ] = null; // Explicitly absent — never fabricated.
			}
		}

		return array(
			'valid'  => true,
			'errors' => array(),
			'input'  => $normalized,
		);
	}
}

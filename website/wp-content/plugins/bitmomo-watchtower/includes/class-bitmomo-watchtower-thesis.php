<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The current thesis/state model schema for Product B (WATCHTOWER PR 2).
 *
 * Pure PHP, no WordPress dependency, no I/O — same fail-closed
 * convention as Bitmomo_Watchtower_Event_Input and Bitmomo_Regime_Input:
 * validate() never returns a partial/fabricated record.
 *
 * This is Watchtower's own internal "what do we currently believe"
 * record: regime, directional bias, confidence, an expected price range,
 * what would invalidate the thesis, the major driver behind it, and
 * which source record produced it. WATCHTOWER PR 3's state transition
 * engine will compare a newly computed thesis against the current
 * persisted one (via Bitmomo_Watchtower_Thesis_Store::get_current()) to
 * decide NO_CHANGE / MATERIAL_CONTEXT_UPDATE / CONFIDENCE_CHANGE /
 * THESIS_CHANGE / THESIS_INVALIDATED / DATA_DEGRADED.
 *
 * Deliberately independent of Bitmomo_Regime_Taxonomy — see the
 * docblock on Bitmomo_Watchtower_Taxonomy's THESIS_REGIMES/THESIS_BIASES
 * constants for why this vocabulary is duplicated rather than imported.
 */
class Bitmomo_Watchtower_Thesis {

	const REQUIRED_FIELDS = array(
		'regime',
		'directional_bias',
		'confidence',
		'expected_range_low',
		'expected_range_high',
		'invalidation',
		'major_driver',
		'source_record_id',
	);

	/**
	 * @param mixed $raw
	 *
	 * @return array{valid:bool,errors:string[],input:?array}
	 */
	public static function validate( $raw ) {
		if ( ! is_array( $raw ) ) {
			return self::fail( array( 'Input must be an array.' ) );
		}

		$errors = array();
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $raw ) || null === $raw[ $field ] || '' === $raw[ $field ] ) {
				$errors[] = sprintf( 'Missing required field: %s', $field );
			}
		}
		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( ! Bitmomo_Watchtower_Taxonomy::is_valid_thesis_regime( $raw['regime'] ) ) {
			$errors[] = sprintf( 'Unknown regime: %s', (string) $raw['regime'] );
		}
		if ( ! Bitmomo_Watchtower_Taxonomy::is_valid_thesis_bias( $raw['directional_bias'] ) ) {
			$errors[] = sprintf( 'Unknown directional_bias: %s', (string) $raw['directional_bias'] );
		}
		if ( ! is_numeric( $raw['confidence'] ) || (float) $raw['confidence'] < 0 || (float) $raw['confidence'] > 100 ) {
			$errors[] = 'confidence must be numeric between 0 and 100.';
		}
		if ( ! is_numeric( $raw['expected_range_low'] ) ) {
			$errors[] = 'expected_range_low must be numeric.';
		}
		if ( ! is_numeric( $raw['expected_range_high'] ) ) {
			$errors[] = 'expected_range_high must be numeric.';
		}
		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		if ( (float) $raw['expected_range_high'] < (float) $raw['expected_range_low'] ) {
			$errors[] = 'expected_range_high must be greater than or equal to expected_range_low.';
		}
		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		$input = array(
			'regime'               => $raw['regime'],
			'directional_bias'     => $raw['directional_bias'],
			'confidence'           => (float) $raw['confidence'],
			'expected_range_low'   => (float) $raw['expected_range_low'],
			'expected_range_high'  => (float) $raw['expected_range_high'],
			'invalidation'         => (string) $raw['invalidation'],
			'major_driver'         => (string) $raw['major_driver'],
			'source_record_id'     => (string) $raw['source_record_id'],
		);

		return array(
			'valid'  => true,
			'errors' => array(),
			'input'  => $input,
		);
	}

	private static function fail( $errors ) {
		return array(
			'valid'  => false,
			'errors' => $errors,
			'input'  => null,
		);
	}
}

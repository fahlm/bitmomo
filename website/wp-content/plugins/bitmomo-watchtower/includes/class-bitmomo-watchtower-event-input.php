<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The canonical event candidate schema for Product B (WATCHTOWER PR 1) —
 * the normalized-input contract any future detector (live data fetchers,
 * news/sentiment adapters — none of which exist yet, see the plugin's
 * bootstrap docblock) must produce before an event candidate can be
 * scored by Bitmomo_Watchtower_Materiality_Engine.
 *
 * Same fail-closed convention as Bitmomo_Regime_Input in the regime
 * plugin: validate() never returns a partial/fabricated record. Any
 * validation failure returns `input: null` and a named list of errors —
 * callers must treat that as "do not score this," not "score it anyway
 * with defaults."
 */
class Bitmomo_Watchtower_Event_Input {

	/**
	 * Every field the materiality engine's six dimensions are computed
	 * from, plus the event's identity (event_type) and timestamp
	 * (occurred_at). All six *_score fields are 0-100 normalized inputs —
	 * how a detector arrives at a given score is that detector's
	 * responsibility, not this schema's.
	 */
	const REQUIRED_FIELDS = array(
		'event_type',
		'occurred_at',
		'magnitude_score',
		'novelty_score',
		'btc_relevance_score',
		'cross_confirmation_score',
		'thesis_impact_score',
		'source_quality_score',
	);

	const SCORE_FIELDS = array(
		'magnitude_score',
		'novelty_score',
		'btc_relevance_score',
		'cross_confirmation_score',
		'thesis_impact_score',
		'source_quality_score',
	);

	/**
	 * `domain`, if supplied, is cross-checked against the registry's
	 * owning domain for `event_type` (see
	 * Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_DOMAIN_MAP) rather than
	 * trusted outright — a caller cannot mislabel an event's domain. If
	 * omitted, it is derived automatically.
	 */
	const OPTIONAL_FIELDS = array(
		'domain',
		'headline',
		'source_id',
		'source_name',
		'description',
		'raw_metrics',
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

		if ( ! Bitmomo_Watchtower_Taxonomy::is_valid_event_type( $raw['event_type'] ) ) {
			$errors[] = sprintf( 'Unknown event_type: %s', (string) $raw['event_type'] );
		}

		foreach ( self::SCORE_FIELDS as $field ) {
			if ( ! is_numeric( $raw[ $field ] ) ) {
				$errors[] = sprintf( '%s must be numeric.', $field );
				continue;
			}
			$value = (float) $raw[ $field ];
			if ( $value < 0 || $value > 100 ) {
				$errors[] = sprintf( '%s must be between 0 and 100 (got %s).', $field, $value );
			}
		}
		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		$registry_domain = Bitmomo_Watchtower_Taxonomy::domain_for_event_type( $raw['event_type'] );
		if ( array_key_exists( 'domain', $raw ) && null !== $raw['domain'] && '' !== $raw['domain'] && $raw['domain'] !== $registry_domain ) {
			$errors[] = sprintf(
				'Supplied domain "%s" does not match the registry\'s owning domain "%s" for event_type %s.',
				(string) $raw['domain'],
				(string) $registry_domain,
				$raw['event_type']
			);
		}
		if ( ! empty( $errors ) ) {
			return self::fail( $errors );
		}

		$input = array(
			'event_type'               => $raw['event_type'],
			'domain'                   => $registry_domain,
			'occurred_at'               => (string) $raw['occurred_at'],
			'magnitude_score'          => (float) $raw['magnitude_score'],
			'novelty_score'            => (float) $raw['novelty_score'],
			'btc_relevance_score'      => (float) $raw['btc_relevance_score'],
			'cross_confirmation_score' => (float) $raw['cross_confirmation_score'],
			'thesis_impact_score'      => (float) $raw['thesis_impact_score'],
			'source_quality_score'     => (float) $raw['source_quality_score'],
			'headline'                 => isset( $raw['headline'] ) ? (string) $raw['headline'] : '',
			'source_id'                => isset( $raw['source_id'] ) ? (string) $raw['source_id'] : '',
			'source_name'              => isset( $raw['source_name'] ) ? (string) $raw['source_name'] : '',
			'description'              => isset( $raw['description'] ) ? (string) $raw['description'] : '',
			'raw_metrics'              => ( isset( $raw['raw_metrics'] ) && is_array( $raw['raw_metrics'] ) ) ? $raw['raw_metrics'] : array(),
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

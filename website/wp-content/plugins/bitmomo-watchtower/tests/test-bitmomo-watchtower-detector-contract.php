<?php
/**
 * Watchtower detector contract test (Integration Hardening Phase, Task 5).
 *
 * NOT a live detector. There is no polling, no exchange API call, no news
 * feed anywhere in this file. This is a pure contract test exercising the
 * REAL Bitmomo_Watchtower_Event_Input::validate() — the interface a
 * future Codex-built detector must satisfy, documented conceptually in
 * DETECTOR_INTERFACE.md — using payloads built the way a detector would
 * build them (see that document's field-mapping table).
 *
 * Run: php tests/test-bitmomo-watchtower-detector-contract.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-event-input.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

/**
 * A complete, valid detector-output payload, built in the same field
 * order DETECTOR_INTERFACE.md's mapping table uses:
 * event_type, domain, timestamp(occurred_at), magnitude, novelty,
 * btc_relevance, cross_confirmation, thesis_impact, source_quality,
 * evidence (headline/description/raw_metrics), source identifiers
 * (source_id/source_name).
 */
function detector_candidate( $overrides = array(), $remove = array() ) {
	$payload = array(
		'event_type'               => Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_VOLATILITY_SHOCK,
		'domain'                    => Bitmomo_Watchtower_Taxonomy::DOMAIN_MARKET,
		'occurred_at'               => '2026-08-25T10:00:00Z',
		'magnitude_score'          => 72,
		'novelty_score'            => 60,
		'btc_relevance_score'      => 95,
		'cross_confirmation_score' => 55,
		'thesis_impact_score'      => 58,
		'source_quality_score'     => 75,
		'headline'                  => '4-hour realized volatility percentile jumps from 40 to 88',
		'description'               => 'Detector-computed volatility percentile shock, no directional break yet observed.',
		'raw_metrics'               => array( 'vol_percentile_before' => 40, 'vol_percentile_after' => 88 ),
		'source_id'                 => 'detector-test:vol-monitor:2026-08-25T10:00:00Z',
		'source_name'               => 'Illustrative volatility detector (contract test only)',
	);
	foreach ( $remove as $field ) {
		unset( $payload[ $field ] );
	}
	return array_merge( $payload, $overrides );
}

function has_error_mentioning( $errors, $needle ) {
	foreach ( $errors as $e ) {
		if ( false !== strpos( $e, $needle ) ) {
			return true;
		}
	}
	return false;
}

function assert_rejected( $case_label, $payload, $expected_error_needle ) {
	$v = Bitmomo_Watchtower_Event_Input::validate( $payload );
	check( "{$case_label}: detector payload is rejected (valid === false)", false === $v['valid'] );
	check( "{$case_label}: rejected payload never returns a partial record (input === null)", null === $v['input'] );
	check(
		"{$case_label}: error list names the actual offending field/reason ('{$expected_error_needle}')",
		has_error_mentioning( $v['errors'], $expected_error_needle )
	);
	return $v;
}

// ---------------------------------------------------------------------
// Positive control.
// ---------------------------------------------------------------------
$control = Bitmomo_Watchtower_Event_Input::validate( detector_candidate() );
check( 'Positive control: fully valid detector payload validates', true === $control['valid'] );
check( 'Positive control: domain is preserved when it matches the registry', Bitmomo_Watchtower_Taxonomy::DOMAIN_MARKET === $control['input']['domain'] );

// Confirms the documented recommendation ("omit domain, let it derive"):
// omitting it entirely still produces the correct registry domain.
$control_no_domain = Bitmomo_Watchtower_Event_Input::validate( detector_candidate( array(), array( 'domain' ) ) );
check( 'Positive control: omitting domain still validates and derives the registry domain', true === $control_no_domain['valid'] && Bitmomo_Watchtower_Taxonomy::DOMAIN_MARKET === $control_no_domain['input']['domain'] );

// ---------------------------------------------------------------------
// Case A — missing required field (every REQUIRED_FIELDS entry).
// ---------------------------------------------------------------------
foreach ( Bitmomo_Watchtower_Event_Input::REQUIRED_FIELDS as $field ) {
	assert_rejected(
		"Case A (missing required field: {$field})",
		detector_candidate( array(), array( $field ) ),
		sprintf( 'Missing required field: %s', $field )
	);
}

// ---------------------------------------------------------------------
// Case B — unknown/unregistered event_type.
// ---------------------------------------------------------------------
assert_rejected(
	'Case B (event_type: unregistered string)',
	detector_candidate( array( 'event_type' => 'MYSTERY_EVENT' ) ),
	'Unknown event_type'
);

// ---------------------------------------------------------------------
// Case C — domain / event_type mismatch (a detector mislabeling its own
// domain rather than letting it derive).
// ---------------------------------------------------------------------
assert_rejected(
	'Case C (domain mismatched against the registry for this event_type)',
	detector_candidate( array( 'domain' => Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES ) ), // VOLATILITY_SHOCK is DOMAIN_MARKET
	'does not match the registry'
);

// ---------------------------------------------------------------------
// Case D — score field out of the 0-100 range (every SCORE_FIELDS entry,
// above and below the bound).
// ---------------------------------------------------------------------
foreach ( Bitmomo_Watchtower_Event_Input::SCORE_FIELDS as $field ) {
	assert_rejected(
		"Case D ({$field} above 100)",
		detector_candidate( array( $field => 150 ) ),
		"{$field} must be between 0 and 100"
	);
	assert_rejected(
		"Case D ({$field} below 0)",
		detector_candidate( array( $field => -5 ) ),
		"{$field} must be between 0 and 100"
	);
}

// ---------------------------------------------------------------------
// Case E — malformed (non-numeric) score input.
// ---------------------------------------------------------------------
foreach ( array( 'magnitude_score', 'thesis_impact_score' ) as $field ) {
	assert_rejected(
		"Case E (malformed numeric input: {$field} = 'high')",
		detector_candidate( array( $field => 'high' ) ),
		"{$field} must be numeric"
	);
}

// ---------------------------------------------------------------------
// Case F — unexpected null in a required field.
// ---------------------------------------------------------------------
foreach ( array( 'event_type', 'occurred_at', 'magnitude_score', 'source_quality_score' ) as $field ) {
	assert_rejected(
		"Case F (unexpected null in required field: {$field})",
		detector_candidate( array( $field => null ) ),
		sprintf( 'Missing required field: %s', $field )
	);
}

// ---------------------------------------------------------------------
// Case G — malformed input type entirely (not an array).
// ---------------------------------------------------------------------
$non_array_cases = array( 'a string', 42, null, false, 3.14 );
foreach ( $non_array_cases as $i => $bad_input ) {
	$v = Bitmomo_Watchtower_Event_Input::validate( $bad_input );
	check( "Case G (non-array input #{$i}, gettype=" . gettype( $bad_input ) . '): rejected', false === $v['valid'] );
	check( "Case G (non-array input #{$i}): input is null", null === $v['input'] );
}

// ---------------------------------------------------------------------
// Case H — KNOWN GAP (documented, not a suite failure): occurred_at has
// no format enforcement. A detector sending a garbage timestamp string
// currently passes validate() unchanged; DETECTOR_INTERFACE.md flags this
// as a real risk for Bitmomo_Watchtower_Deduplicator's strtotime()-based
// clustering, not something this schema itself will catch.
// ---------------------------------------------------------------------
$garbage_timestamp = Bitmomo_Watchtower_Event_Input::validate( detector_candidate( array( 'occurred_at' => 'not-a-real-timestamp' ) ) );
check(
	'Case H (KNOWN GAP, documented): occurred_at = "not-a-real-timestamp" currently PASSES validate() — a detector must supply a strtotime()-parseable, sortable timestamp itself',
	true === $garbage_timestamp['valid'] && 'not-a-real-timestamp' === $garbage_timestamp['input']['occurred_at']
);

// ---------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------
$pass_count = 0;
foreach ( $results as $r ) {
	printf( "[%s] %s\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'] );
	if ( $r['pass'] ) {
		$pass_count++;
	}
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );

exit( $pass_count === $total ? 0 : 1 );

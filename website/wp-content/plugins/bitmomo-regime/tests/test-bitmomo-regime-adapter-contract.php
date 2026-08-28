<?php
/**
 * Regime adapter contract test (Integration Hardening Phase, Task 2).
 *
 * NOT a live adapter. There is no new production class here — the
 * "contract" Codex's future runtime adapter must satisfy IS
 * Bitmomo_Regime_Input::validate(), which already exists and already
 * fails closed (see class-bitmomo-regime-input.php). This file's job is
 * to pin down, with an executable test and clear per-case labels, exactly
 * which malformed adapter payloads that contract rejects and why — so
 * Codex can build a real adapter against a precisely characterized
 * boundary instead of guessing at validate()'s behavior from reading code.
 *
 * Every case below builds a payload the way a real runtime adapter would
 * (a plain associative array of the shape described in FIELD_SEMANTICS.md
 * and CODEX_INTEGRATION_CONTRACT.md), and asserts three things for each
 * rejected payload: (1) validation fails, (2) the returned input is null
 * (never a partially-fabricated record), and (3) the error list names the
 * actual offending field, so a caller can produce a clear runtime error
 * instead of a generic "invalid input".
 *
 * Run: php tests/test-bitmomo-regime-adapter-contract.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-config.php';
require __DIR__ . '/../includes/class-bitmomo-regime-input.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

/**
 * A complete, valid adapter payload — every required field present and
 * well-formed, every optional field populated. Individual test cases
 * mutate a copy of this via overrides/removals; never invent a field name
 * not already in Bitmomo_Regime_Input::REQUIRED_FIELDS/OPTIONAL_FIELDS.
 */
function valid_adapter_payload( $overrides = array(), $remove = array() ) {
	$payload = array(
		'return_1d'                => -1.5,
		'return_7d'                 => 2.0,
		'return_30d'                => 5.0,
		'volatility_percentile'     => 40,
		'volatility_change'         => 3.0,
		'volume_percentile'         => 55,
		'momentum_score'            => 12,
		'range_position_pct'        => 48,
		'structure_state'           => 'range',
		'directional_bias'          => 'neutral',
		'open_interest_change_pct'  => 1.0,
		'funding_rate_pct'          => 0.01,
		'basis_pct'                 => 0.5,
		'liquidation_pressure'      => 'none',
		'crowding_score'            => 30,
		'directional_confidence'    => 50,
		'source_record_id'          => 'bitmomo-ai:btcusdt:2026-08-25T00:00:00Z',
		'as_of'                     => '2026-08-25T00:00:00Z',
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
	$v = Bitmomo_Regime_Input::validate( $payload );
	check( "{$case_label}: adapter payload is rejected (valid === false)", false === $v['valid'] );
	check( "{$case_label}: rejected payload never returns a partial record (input === null)", null === $v['input'] );
	check(
		"{$case_label}: error list names the actual offending field/reason ('{$expected_error_needle}')",
		has_error_mentioning( $v['errors'], $expected_error_needle )
	);
	return $v;
}

// ---------------------------------------------------------------------
// Positive control: a fully valid adapter payload must pass, so the
// negative cases below are testing the actual boundary, not a payload
// that was broken for some unrelated reason.
// ---------------------------------------------------------------------
$control = Bitmomo_Regime_Input::validate( valid_adapter_payload() );
check( 'Positive control: fully valid adapter payload validates', true === $control['valid'] );
check( 'Positive control: valid payload produces a non-null normalized input', null !== $control['input'] );

// ---------------------------------------------------------------------
// Case A — missing required field.
// Every REQUIRED_FIELDS entry is checked individually so a future field
// added to the contract without a corresponding check here is caught by
// the "each required field individually" loop, not missed by accident.
// ---------------------------------------------------------------------
foreach ( Bitmomo_Regime_Input::REQUIRED_FIELDS as $field ) {
	assert_rejected(
		"Case A (missing required field: {$field})",
		valid_adapter_payload( array(), array( $field ) ),
		"Missing required field: {$field}"
	);
}

// ---------------------------------------------------------------------
// Case B — wrong percentile range (each PERCENTILE_FIELDS entry, above
// and below the 0-100 bound).
// ---------------------------------------------------------------------
foreach ( Bitmomo_Regime_Input::PERCENTILE_FIELDS as $field ) {
	assert_rejected(
		"Case B (percentile field {$field} above 100)",
		valid_adapter_payload( array( $field => 150 ) ),
		"Field {$field} must be within 0-100"
	);
	assert_rejected(
		"Case B (percentile field {$field} below 0)",
		valid_adapter_payload( array( $field => -10 ) ),
		"Field {$field} must be within 0-100"
	);
}
// crowding_score / directional_confidence share the 0-100 enforcement
// through a different code path (not PERCENTILE_FIELDS) — covered
// separately so this suite exercises both enforcement branches.
foreach ( array( 'crowding_score', 'directional_confidence' ) as $field ) {
	assert_rejected(
		"Case B (optional 0-100 field {$field} above 100)",
		valid_adapter_payload( array( $field => 250 ) ),
		"Field {$field} must be numeric within 0-100"
	);
}

// ---------------------------------------------------------------------
// Case C — invalid structure_state.
// ---------------------------------------------------------------------
assert_rejected(
	'Case C (structure_state: unrecognized string)',
	valid_adapter_payload( array( 'structure_state' => 'sideways' ) ),
	'Field structure_state has an invalid value'
);
assert_rejected(
	'Case C (structure_state: numeric instead of enum string)',
	valid_adapter_payload( array( 'structure_state' => 1 ) ),
	'Field structure_state has an invalid value'
);

// ---------------------------------------------------------------------
// Case D — invalid directional_bias.
// ---------------------------------------------------------------------
assert_rejected(
	'Case D (directional_bias: unrecognized string)',
	valid_adapter_payload( array( 'directional_bias' => 'very_bullish' ) ),
	'Field directional_bias has an invalid value'
);
assert_rejected(
	'Case D (directional_bias: wrong case, "Bullish" not in enum)',
	valid_adapter_payload( array( 'directional_bias' => 'Bullish' ) ),
	'Field directional_bias has an invalid value'
);

// ---------------------------------------------------------------------
// Case E — malformed numeric input (non-numeric string in a numeric
// required field).
// ---------------------------------------------------------------------
foreach ( array( 'return_1d', 'volatility_percentile', 'momentum_score' ) as $field ) {
	assert_rejected(
		"Case E (malformed numeric input: {$field} = 'not-a-number')",
		valid_adapter_payload( array( $field => 'not-a-number' ) ),
		"Field {$field} must be numeric"
	);
}
assert_rejected(
	"Case E (malformed numeric input: an array where a number is expected)",
	valid_adapter_payload( array( 'return_1d' => array( 'oops' ) ) ),
	'Field return_1d must be numeric'
);

// ---------------------------------------------------------------------
// Case F — unexpected null in a required field.
// (A required field explicitly present but set to null is treated
// identically to a missing field, never coerced to 0/""/false.)
// ---------------------------------------------------------------------
foreach ( array( 'return_1d', 'volatility_percentile', 'structure_state', 'directional_bias' ) as $field ) {
	assert_rejected(
		"Case F (unexpected null in required field: {$field})",
		valid_adapter_payload( array( $field => null ) ),
		"Missing required field: {$field}"
	);
}

// ---------------------------------------------------------------------
// Case G — invalid liquidation_pressure (optional enum field; same
// enforcement family as Case C but for an optional field, confirming
// "optional" never means "unvalidated when present").
// ---------------------------------------------------------------------
assert_rejected(
	'Case G (liquidation_pressure: unrecognized string)',
	valid_adapter_payload( array( 'liquidation_pressure' => 'catastrophic' ) ),
	'Field liquidation_pressure has an invalid value'
);

// ---------------------------------------------------------------------
// Case H — a known, DOCUMENTED gap (not a failure of this suite): the
// class comment on momentum_score claims a -100..100 range, but
// Bitmomo_Regime_Input::validate() does not enforce it (momentum_score is
// not in PERCENTILE_FIELDS and has no other bound check). This case
// records CURRENT behavior deliberately, per FIELD_SEMANTICS.md's note —
// it asserts the gap exists so a future tightening of validate() is a
// conscious, visible change to this test, not a silent behavior shift.
// This is a canary, not a contract requirement: Codex's own adapter
// SHOULD still reject out-of-range momentum_score before calling
// Bitmomo_Regime_Input::validate(), since Product A will not catch it.
// ---------------------------------------------------------------------
$out_of_range_momentum = Bitmomo_Regime_Input::validate( valid_adapter_payload( array( 'momentum_score' => -500 ) ) );
check(
	'Case H (KNOWN GAP, documented): out-of-spec momentum_score (-500, outside documented -100..100) currently PASSES validate() — Codex must range-check momentum_score on the adapter side',
	true === $out_of_range_momentum['valid']
);
$out_of_range_vol_change = Bitmomo_Regime_Input::validate( valid_adapter_payload( array( 'volatility_change' => 999 ) ) );
check(
	'Case H (KNOWN GAP, documented): an implausible volatility_change (999 percentile-points) currently PASSES validate() — same adapter-side responsibility',
	true === $out_of_range_vol_change['valid']
);

// ---------------------------------------------------------------------
// Case I — funding_rate_pct unit-convention canary (does not fail
// validation — funding_rate_pct has no enforced range — but documents,
// executably, that a caller who mistakenly sends "1.0" meaning "1%"
// instead of the correct "0.01" gets a value that silently passes
// through unchanged. Paired with the unit-convention warning in
// FIELD_SEMANTICS.md.
// ---------------------------------------------------------------------
$funding_unit_mistake = Bitmomo_Regime_Input::validate( valid_adapter_payload( array( 'funding_rate_pct' => 1.0 ) ) );
check(
	'Case I (KNOWN GAP, documented): funding_rate_pct = 1.0 (a caller\'s "1%" mistakenly sent as 1.0 instead of 0.01) passes validate() unchanged and would score as an extreme 100% funding rate',
	true === $funding_unit_mistake['valid'] && 1.0 === $funding_unit_mistake['input']['funding_rate_pct']
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

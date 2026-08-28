<?php
/**
 * Standalone executable test for WATCHTOWER PR 3's state transition
 * engine (Bitmomo_Watchtower_State_Transition_Engine).
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-state-transition.php
 *
 * Every fixture's expected transition_type was traced by hand against
 * the documented priority order (DATA_DEGRADED > initial THESIS_CHANGE
 * > THESIS_INVALIDATED > regime/bias THESIS_CHANGE > CONFIDENCE_CHANGE
 * > MATERIAL_CONTEXT_UPDATE > NO_CHANGE) and the exact thresholds
 * (CONFIDENCE_CHANGE_THRESHOLD=15 points, EXPECTED_RANGE_CHANGE_PCT=5%)
 * before running.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-state-transition-engine.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function thesis( $overrides = array() ) {
	$base = array(
		'regime'              => Bitmomo_Watchtower_Taxonomy::THESIS_REGIME_ACCUMULATION,
		'directional_bias'    => Bitmomo_Watchtower_Taxonomy::THESIS_BIAS_NEUTRAL,
		'confidence'          => 60.0,
		'expected_range_low'  => 100000.0,
		'expected_range_high' => 110000.0,
		'invalidation'        => 'Daily close below 95000.',
		'major_driver'        => 'Low-volatility accumulation.',
		'source_record_id'    => 'src-1',
	);
	return array_merge( $base, $overrides );
}

$engine = new Bitmomo_Watchtower_State_Transition_Engine();

// ---------------------------------------------------------------------
// Case 1: no previous thesis -> THESIS_CHANGE ("initial"), diff.previous
// is null, changed_fields lists every comparable field.
// ---------------------------------------------------------------------
$result = $engine->evaluate( thesis(), null, array() );
check( 'Case 1: no previous thesis classifies as THESIS_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE === $result['transition_type'] );
check( 'Case 1: diff.previous is null for the initial thesis', null === $result['diff']['previous'] );
check( 'Case 1: all comparable fields are listed as changed for the initial thesis', Bitmomo_Watchtower_State_Transition_Engine::COMPARABLE_FIELDS === $result['diff']['changed_fields'] );

// ---------------------------------------------------------------------
// Case 2: identical thesis (all fields the same) -> NO_CHANGE.
// ---------------------------------------------------------------------
$previous = thesis();
$result   = $engine->evaluate( thesis(), $previous, array() );
check( 'Case 2: an identical thesis classifies as NO_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE === $result['transition_type'] );
check( 'Case 2: NO_CHANGE still reports an empty changed_fields list', array() === $result['diff']['changed_fields'] );

// ---------------------------------------------------------------------
// Case 3: a regime change (everything else identical) -> THESIS_CHANGE.
// ---------------------------------------------------------------------
$previous = thesis();
$new      = thesis( array( 'regime' => Bitmomo_Watchtower_Taxonomy::THESIS_REGIME_EXPANSION ) );
$result   = $engine->evaluate( $new, $previous, array() );
check( 'Case 3: a regime change classifies as THESIS_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE === $result['transition_type'] );
check( 'Case 3: changed_fields names exactly "regime"', array( 'regime' ) === $result['diff']['changed_fields'] );

// ---------------------------------------------------------------------
// Case 4: a directional_bias change (regime unchanged) -> THESIS_CHANGE.
// ---------------------------------------------------------------------
$new    = thesis( array( 'directional_bias' => Bitmomo_Watchtower_Taxonomy::THESIS_BIAS_BULLISH ) );
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 4: a directional_bias change classifies as THESIS_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 5: confidence delta of exactly 15 (the threshold, inclusive) with
// regime/bias unchanged -> CONFIDENCE_CHANGE.
// ---------------------------------------------------------------------
$new    = thesis( array( 'confidence' => 75.0 ) ); // 60 -> 75, delta = 15
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 5: a confidence delta of exactly 15 (threshold) classifies as CONFIDENCE_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_CONFIDENCE_CHANGE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 6: confidence delta of 14 (just under threshold), nothing else
// changed -> NO_CHANGE (falls through both CONFIDENCE_CHANGE and
// MATERIAL_CONTEXT_UPDATE).
// ---------------------------------------------------------------------
$new    = thesis( array( 'confidence' => 74.0 ) ); // 60 -> 74, delta = 14
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 6: a confidence delta of 14 (below threshold) with no other changes classifies as NO_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 7: expected_range_low moves by exactly 5% (the threshold,
// inclusive), regime/bias/confidence unchanged -> MATERIAL_CONTEXT_UPDATE.
// 100000 -> 105000 is exactly a 5.0% change.
// ---------------------------------------------------------------------
$new    = thesis( array( 'expected_range_low' => 105000.0 ) );
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 7: an expected_range_low change of exactly 5% classifies as MATERIAL_CONTEXT_UPDATE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 8: expected_range_low moves by 4.9% (just under threshold),
// nothing else changed -> NO_CHANGE.
// ---------------------------------------------------------------------
$new    = thesis( array( 'expected_range_low' => 104900.0 ) );
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 8: an expected_range_low change of 4.9% (below threshold) classifies as NO_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 9: invalidation text changes (regime/bias/confidence/range
// unchanged) -> MATERIAL_CONTEXT_UPDATE.
// ---------------------------------------------------------------------
$new    = thesis( array( 'invalidation' => 'Daily close below 90000.' ) );
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 9: an invalidation text change classifies as MATERIAL_CONTEXT_UPDATE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 10: major_driver text changes -> MATERIAL_CONTEXT_UPDATE.
// ---------------------------------------------------------------------
$new    = thesis( array( 'major_driver' => 'Renewed institutional inflows.' ) );
$result = $engine->evaluate( $new, thesis(), array() );
check( 'Case 10: a major_driver text change classifies as MATERIAL_CONTEXT_UPDATE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 11: invalidation_triggered=true takes priority over EVERYTHING
// except DATA_DEGRADED — even when the candidate thesis also proposes a
// regime change, the result is still THESIS_INVALIDATED, not
// THESIS_CHANGE.
// ---------------------------------------------------------------------
$new    = thesis( array( 'regime' => Bitmomo_Watchtower_Taxonomy::THESIS_REGIME_CAPITULATION ) );
$result = $engine->evaluate( $new, thesis(), array( 'invalidation_triggered' => true ) );
check( 'Case 11: invalidation_triggered=true classifies as THESIS_INVALIDATED even alongside a regime change', Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_INVALIDATED === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 12: data_quality_degraded=true takes priority over EVERYTHING,
// including having no previous thesis at all.
// ---------------------------------------------------------------------
$result = $engine->evaluate( thesis(), null, array( 'data_quality_degraded' => true ) );
check( 'Case 12: data_quality_degraded=true classifies as DATA_DEGRADED even with no previous thesis', Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED === $result['transition_type'] );

$result = $engine->evaluate( thesis( array( 'regime' => Bitmomo_Watchtower_Taxonomy::THESIS_REGIME_EXPANSION ) ), thesis(), array( 'data_quality_degraded' => true, 'invalidation_triggered' => true ) );
check( 'Case 12: data_quality_degraded=true outranks invalidation_triggered and a regime change together', Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED === $result['transition_type'] );

// ---------------------------------------------------------------------
// Case 13: changed_fields correctly names exactly the fields that
// differ when multiple fields change at once (regime AND confidence).
// ---------------------------------------------------------------------
$new    = thesis( array( 'regime' => Bitmomo_Watchtower_Taxonomy::THESIS_REGIME_DISTRIBUTION, 'confidence' => 80.0 ) );
$result = $engine->evaluate( $new, thesis(), array() );
sort( $result['diff']['changed_fields'] );
check( 'Case 13: changed_fields names exactly "confidence" and "regime" when both change', array( 'confidence', 'regime' ) === $result['diff']['changed_fields'] );

// ---------------------------------------------------------------------
// Case 14: idempotency.
// ---------------------------------------------------------------------
$new  = thesis( array( 'confidence' => 80.0 ) );
$prev = thesis();
$run1 = $engine->evaluate( $new, $prev, array() );
$run2 = $engine->evaluate( $new, $prev, array() );
check( 'Case 14: repeated evaluation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

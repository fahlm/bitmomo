<?php
/**
 * Standalone executable test for WATCHTOWER PR 1's deterministic
 * materiality engine (Bitmomo_Watchtower_Materiality_Engine).
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-materiality.php
 *
 * Every fixture below is hand-computed against the documented weights
 * (Magnitude 25 / Novelty 15 / BTC Relevance 20 / Cross Confirmation 15 /
 * Thesis Impact 15 / Source Quality 10 = 100) and band cut points
 * (0-39 noise / 40-59 interesting / 60-74 material candidate /
 * 75-100 critical candidate) BEFORE being run, specifically to avoid
 * writing tests that merely echo whatever the implementation happens to
 * do. A key property exploited throughout: when all six dimension scores
 * are set to the same value v, the weighted composite is exactly v
 * (since the weights sum to 100) — this makes exact policy-band boundary
 * testing possible without floating-point guesswork.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-materiality-engine.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function uniform_input( $v ) {
	return array(
		'magnitude_score'          => $v,
		'novelty_score'            => $v,
		'btc_relevance_score'      => $v,
		'cross_confirmation_score' => $v,
		'thesis_impact_score'      => $v,
		'source_quality_score'     => $v,
	);
}

// ---------------------------------------------------------------------
// Case 0: config sanity — the weights this whole test file relies on
// actually sum to 100, and the engine itself refuses to run otherwise.
// ---------------------------------------------------------------------
check( 'Case 0: Bitmomo_Watchtower_Config::TOTAL_WEIGHT sums to 100', 100 === Bitmomo_Watchtower_Config::TOTAL_WEIGHT );

$engine = new Bitmomo_Watchtower_Materiality_Engine();

// ---------------------------------------------------------------------
// Case 1: all-zero evidence -> composite 0 -> noise (bottom of range).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 0 ) );
check( 'Case 1: all-zero dimensions score 0', 0 === $result['score'] );
check( 'Case 1: score 0 bands as noise', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_NOISE === $result['band'] );

// ---------------------------------------------------------------------
// Case 2: uniform 39 -> score 39 -> noise (top boundary of noise).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 39 ) );
check( 'Case 2: uniform 39 scores exactly 39', 39 === $result['score'] );
check( 'Case 2: score 39 (top of noise band) bands as noise', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_NOISE === $result['band'] );

// ---------------------------------------------------------------------
// Case 3: uniform 40 -> score 40 -> interesting (bottom boundary).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 40 ) );
check( 'Case 3: uniform 40 scores exactly 40', 40 === $result['score'] );
check( 'Case 3: score 40 (bottom of interesting band) bands as interesting', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_INTERESTING === $result['band'] );

// ---------------------------------------------------------------------
// Case 4: uniform 59 -> score 59 -> interesting (top boundary).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 59 ) );
check( 'Case 4: uniform 59 scores exactly 59', 59 === $result['score'] );
check( 'Case 4: score 59 (top of interesting band) bands as interesting', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_INTERESTING === $result['band'] );

// ---------------------------------------------------------------------
// Case 5: uniform 60 -> score 60 -> material candidate (bottom boundary).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 60 ) );
check( 'Case 5: uniform 60 scores exactly 60', 60 === $result['score'] );
check( 'Case 5: score 60 (bottom of material candidate band) bands correctly', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_MATERIAL_CANDIDATE === $result['band'] );

// ---------------------------------------------------------------------
// Case 6: uniform 74 -> score 74 -> material candidate (top boundary).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 74 ) );
check( 'Case 6: uniform 74 scores exactly 74', 74 === $result['score'] );
check( 'Case 6: score 74 (top of material candidate band) bands correctly', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_MATERIAL_CANDIDATE === $result['band'] );

// ---------------------------------------------------------------------
// Case 7: uniform 75 -> score 75 -> critical candidate (bottom boundary).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 75 ) );
check( 'Case 7: uniform 75 scores exactly 75', 75 === $result['score'] );
check( 'Case 7: score 75 (bottom of critical candidate band) bands correctly', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_CRITICAL_CANDIDATE === $result['band'] );

// ---------------------------------------------------------------------
// Case 8: uniform 100 -> score 100 -> critical candidate (top of range).
// ---------------------------------------------------------------------
$result = $engine->evaluate( uniform_input( 100 ) );
check( 'Case 8: uniform 100 scores exactly 100', 100 === $result['score'] );
check( 'Case 8: score 100 bands as critical candidate', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_CRITICAL_CANDIDATE === $result['band'] );

// ---------------------------------------------------------------------
// Case 9: only Magnitude (weight 25) is nonzero, at 100, all else 0 ->
// composite = 100 * 25 / 100 = 25 -> noise. Proves weighting is actually
// per-dimension, not just echoing a single uniform input.
// ---------------------------------------------------------------------
$input = array(
	'magnitude_score'          => 100,
	'novelty_score'            => 0,
	'btc_relevance_score'      => 0,
	'cross_confirmation_score' => 0,
	'thesis_impact_score'      => 0,
	'source_quality_score'     => 0,
);
$result = $engine->evaluate( $input );
check( 'Case 9: magnitude-only at 100 contributes exactly 25 (its weight)', 25 === $result['score'] );
check( 'Case 9: magnitude-only at 100 bands as noise (25 <= 39)', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_NOISE === $result['band'] );

// ---------------------------------------------------------------------
// Case 10: Magnitude (25) + BTC Relevance (20) at 100, rest 0 ->
// composite = 25 + 20 = 45 -> interesting. Proves weighted contributions
// from multiple dimensions sum correctly.
// ---------------------------------------------------------------------
$input = array(
	'magnitude_score'          => 100,
	'novelty_score'            => 0,
	'btc_relevance_score'      => 100,
	'cross_confirmation_score' => 0,
	'thesis_impact_score'      => 0,
	'source_quality_score'     => 0,
);
$result = $engine->evaluate( $input );
check( 'Case 10: magnitude + btc_relevance at 100 sums to exactly 45', 45 === $result['score'] );
check( 'Case 10: composite 45 bands as interesting', Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_INTERESTING === $result['band'] );

// ---------------------------------------------------------------------
// Case 11: reason_breakdown structure — exactly 6 dimensions, in the
// documented fixed order, each with the correct label/weight, and the
// contributions from Case 10's input sum to the same composite (45).
// ---------------------------------------------------------------------
$breakdown = $result['reason_breakdown'];
check( 'Case 11: reason_breakdown has exactly 6 entries', 6 === count( $breakdown ) );
$expected_order = array( 'magnitude', 'novelty', 'btc_relevance', 'cross_confirmation', 'thesis_impact', 'source_quality' );
$actual_order   = array_column( $breakdown, 'dimension' );
check( 'Case 11: reason_breakdown is in the fixed documented order', $expected_order === $actual_order );
check( 'Case 11: magnitude entry has label "Magnitude" and weight 25', 'Magnitude' === $breakdown[0]['label'] && 25 === $breakdown[0]['weight_pct'] );
check( 'Case 11: magnitude entry contribution is 25.0 (100 * 25 / 100)', 25.0 === $breakdown[0]['contribution'] );
check( 'Case 11: btc_relevance entry contribution is 20.0 (100 * 20 / 100)', 20.0 === $breakdown[2]['contribution'] );
$contribution_sum = array_sum( array_column( $breakdown, 'contribution' ) );
check( 'Case 11: sum of all contributions equals the composite score (45)', abs( $contribution_sum - 45.0 ) < 0.001 );

// ---------------------------------------------------------------------
// Case 12: dimension_scores echoes the (clamped) raw inputs verbatim,
// keyed by the same dimension keys as reason_breakdown.
// ---------------------------------------------------------------------
check( 'Case 12: dimension_scores.magnitude echoes the raw input (100)', 100.0 === $result['dimension_scores']['magnitude'] );
check( 'Case 12: dimension_scores.novelty echoes the raw input (0)', 0.0 === $result['dimension_scores']['novelty'] );

// ---------------------------------------------------------------------
// Case 13: engine_version is present and matches the config constant —
// this is what lets a later PR's persisted event record attribute a
// score to the exact ruleset that produced it.
// ---------------------------------------------------------------------
check( 'Case 13: engine_version matches Bitmomo_Watchtower_Config::MATERIALITY_ENGINE_VERSION', Bitmomo_Watchtower_Config::MATERIALITY_ENGINE_VERSION === $result['engine_version'] );

// ---------------------------------------------------------------------
// Case 14: idempotency — identical input, identical output, every time.
// ---------------------------------------------------------------------
$input = uniform_input( 55 );
$run1  = $engine->evaluate( $input );
$run2  = $engine->evaluate( $input );
check( 'Case 14: repeated evaluation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

// ---------------------------------------------------------------------
// Case 15: out-of-range raw dimension inputs (e.g. a detector bug
// producing 150 or -20) are clamped to [0,100] rather than corrupting
// the composite or crashing — a defensive floor under
// Bitmomo_Watchtower_Event_Input's own 0-100 validation, in case this
// engine is ever called with unvalidated data.
// ---------------------------------------------------------------------
$input = array(
	'magnitude_score'          => 150, // clamps to 100
	'novelty_score'            => -20, // clamps to 0
	'btc_relevance_score'      => 0,
	'cross_confirmation_score' => 0,
	'thesis_impact_score'      => 0,
	'source_quality_score'     => 0,
);
$result = $engine->evaluate( $input );
check( 'Case 15: an over-range input (150) clamps to 100 in dimension_scores', 100.0 === $result['dimension_scores']['magnitude'] );
check( 'Case 15: an under-range input (-20) clamps to 0 in dimension_scores', 0.0 === $result['dimension_scores']['novelty'] );
check( 'Case 15: clamped magnitude (100) still contributes exactly its weight (25)', 25 === $result['score'] );

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

echo "\nNOTE: this PR scores an already-validated event candidate; it does\n";
echo "not decide what happens with the score (no alerting, no dedup, no\n";
echo "state transitions — see WATCHTOWER PR 2+).\n";

exit( $pass_count === $total ? 0 : 1 );

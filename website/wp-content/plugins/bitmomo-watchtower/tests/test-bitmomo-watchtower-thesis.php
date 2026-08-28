<?php
/**
 * Standalone executable test for WATCHTOWER PR 2's current thesis/state
 * model schema (Bitmomo_Watchtower_Thesis).
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-thesis.php
 *
 * Bitmomo_Watchtower_Thesis_Store (the persistence layer) requires a
 * real WordPress database and is not exercised here — same convention
 * as Bitmomo_Regime_State_Store; verify on staging.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-thesis.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function valid_thesis( $overrides = array() ) {
	$base = array(
		'regime'               => Bitmomo_Watchtower_Taxonomy::THESIS_REGIME_ACCUMULATION,
		'directional_bias'     => Bitmomo_Watchtower_Taxonomy::THESIS_BIAS_NEUTRAL,
		'confidence'           => 65,
		'expected_range_low'   => 58000,
		'expected_range_high'  => 64000,
		'invalidation'         => 'A daily close below 55000 invalidates this thesis.',
		'major_driver'         => 'Sustained low-volatility accumulation on declining exchange reserves.',
		'source_record_id'     => 'cluster-abc123',
	);
	return array_merge( $base, $overrides );
}

// ---------------------------------------------------------------------
// Case 1: a fully valid thesis normalizes correctly.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis() );
check( 'Case 1: fully-populated valid thesis validates', true === $result['valid'] );
check( 'Case 1: no errors on valid input', array() === $result['errors'] );
check( 'Case 1: confidence normalizes to float', 65.0 === $result['input']['confidence'] );
check( 'Case 1: expected_range_low/high normalize to float', 58000.0 === $result['input']['expected_range_low'] && 64000.0 === $result['input']['expected_range_high'] );

// ---------------------------------------------------------------------
// Case 2: missing a required field fails closed.
// ---------------------------------------------------------------------
$fixture = valid_thesis();
unset( $fixture['invalidation'] );
$result = Bitmomo_Watchtower_Thesis::validate( $fixture );
check( 'Case 2: missing required field fails validation', false === $result['valid'] );
check( 'Case 2: failed validation returns null input', null === $result['input'] );
check( 'Case 2: the missing field is named in the errors', false !== strpos( implode( ' ', $result['errors'] ), 'invalidation' ) );

// ---------------------------------------------------------------------
// Case 3: an invalid regime value is rejected — the vocabulary is
// closed, matching Bitmomo_Watchtower_Taxonomy::THESIS_REGIMES exactly.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis( array( 'regime' => 'bull_market' ) ) );
check( 'Case 3: an unregistered regime value fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 4: an invalid directional_bias value is rejected.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis( array( 'directional_bias' => 'very bullish' ) ) );
check( 'Case 4: an unregistered directional_bias value fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 5: confidence out of the 0-100 range is rejected.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis( array( 'confidence' => 150 ) ) );
check( 'Case 5: out-of-range confidence (150) fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 6: an inverted expected range (high < low) is rejected — a
// logical invariant beyond simple type-checking.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis( array( 'expected_range_low' => 70000, 'expected_range_high' => 60000 ) ) );
check( 'Case 6: an inverted expected range (high < low) fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 7: an expected range where high === low is valid (a degenerate
// but logically consistent "pinned" range is not an error).
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis( array( 'expected_range_low' => 60000, 'expected_range_high' => 60000 ) ) );
check( 'Case 7: an equal (degenerate) expected range validates', true === $result['valid'] );

// ---------------------------------------------------------------------
// Case 8: a non-numeric expected_range field is rejected.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Thesis::validate( valid_thesis( array( 'expected_range_high' => 'high' ) ) );
check( 'Case 8: a non-numeric expected_range_high fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 9: idempotency.
// ---------------------------------------------------------------------
$fixture = valid_thesis();
$run1    = Bitmomo_Watchtower_Thesis::validate( $fixture );
$run2    = Bitmomo_Watchtower_Thesis::validate( $fixture );
check( 'Case 9: repeated validation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

echo "\nNOTE: Bitmomo_Watchtower_Thesis_Store (persistence: bm_watchtower_thesis\n";
echo "CPT, append-only writes, get_current()/get_history()) requires a real\n";
echo "WordPress database and is not exercised by this stub — verify on staging.\n";

exit( $pass_count === $total ? 0 : 1 );

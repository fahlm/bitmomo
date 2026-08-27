<?php
/**
 * Standalone executable test for REGIME PR 1's deterministic classifier.
 *
 * Not a WordPress PHPUnit suite (no WP test scaffold is available in this
 * environment) — it loads the real domain classes against a minimal
 * ABSPATH stub and runs concrete scenarios against
 * Bitmomo_Regime_Classifier::evaluate() and Bitmomo_Regime_Input::validate().
 *
 * Every fixture below has its scoring hand-computed against the exact
 * thresholds in Bitmomo_Regime_Config in the PR description — this file
 * is the executable proof that computation matches intent.
 *
 * Run: php tests/test-bitmomo-regime-classifier.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-config.php';
require __DIR__ . '/../includes/class-bitmomo-regime-input.php';
require __DIR__ . '/../includes/class-bitmomo-regime-classifier.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function base_fields( $overrides = array() ) {
	return array_merge(
		array(
			'return_1d'                => 0,
			'return_7d'                => 0,
			'return_30d'               => 0,
			'volatility_percentile'    => 50,
			'volatility_change'        => 0,
			'volume_percentile'        => 50,
			'momentum_score'           => 0,
			'range_position_pct'       => 50,
			'structure_state'          => 'unknown',
			'directional_bias'         => 'neutral',
			'open_interest_change_pct' => null,
			'funding_rate_pct'         => null,
			'basis_pct'                => null,
			'liquidation_pressure'     => null,
			'crowding_score'           => null,
			'directional_confidence'   => null,
		),
		$overrides
	);
}

$classifier = new Bitmomo_Regime_Classifier();

function classify( $overrides ) {
	global $classifier;
	$validation = Bitmomo_Regime_Input::validate( base_fields( $overrides ) );
	if ( ! $validation['valid'] ) {
		return array( 'validation_failed' => true, 'errors' => $validation['errors'] );
	}
	return $classifier->evaluate( $validation['input'] );
}

// ---------------------------------------------------------------------
// Case 1: Accumulation — low vol, range-bound, low-in-range, subdued volume.
// ---------------------------------------------------------------------
$result = classify(
	array(
		'volatility_percentile' => 20,
		'return_30d'            => 3,
		'range_position_pct'    => 25,
		'structure_state'       => 'range',
		'volume_percentile'     => 30,
		'funding_rate_pct'      => 0.005,
		'return_1d'             => 0.2,
		'return_7d'             => 1,
		'momentum_score'        => 5,
		'volatility_change'     => -2,
	)
);
check( 'Case 1: clean accumulation evidence classifies as accumulation', Bitmomo_Regime_Taxonomy::REGIME_ACCUMULATION === $result['regime'] );
check( 'Case 1: accumulation confidence is high (>= 80)', $result['confidence'] >= 80 );
check( 'Case 1: accumulation has no conflicts at this margin', empty( $result['conflicts'] ) );

// ---------------------------------------------------------------------
// Case 2: Expansion — breakout up, strong momentum, high volume, OI up.
// ---------------------------------------------------------------------
$result = classify(
	array(
		'structure_state'          => 'breakout_up',
		'momentum_score'           => 55,
		'volume_percentile'        => 75,
		'return_7d'                => 12,
		'open_interest_change_pct' => 8,
		'range_position_pct'       => 82,
		'return_1d'                => 3,
		'return_30d'               => 25,
		'volatility_percentile'    => 60,
		'volatility_change'        => 5,
		'funding_rate_pct'         => 0.02,
		'directional_bias'         => 'bullish',
	)
);
check( 'Case 2: clean expansion evidence classifies as expansion', Bitmomo_Regime_Taxonomy::REGIME_EXPANSION === $result['regime'] );
check( 'Case 2: expansion confidence is high (>= 80)', $result['confidence'] >= 80 );
check( 'Case 2: directional_bias passes through unchanged', 'bullish' === $result['directional_bias'] );

// ---------------------------------------------------------------------
// Case 3: Distribution — near highs, decelerating momentum, crowded funding.
// ---------------------------------------------------------------------
$result = classify(
	array(
		'range_position_pct'   => 85,
		'momentum_score'       => 5,
		'volume_percentile'    => 70,
		'return_7d'            => 1,
		'funding_rate_pct'     => 0.07,
		'crowding_score'       => 80,
		'structure_state'      => 'range',
		'return_1d'            => 0.5,
		'return_30d'           => 15,
		'volatility_percentile' => 50,
		'volatility_change'    => 2,
		'liquidation_pressure' => 'none',
	)
);
check( 'Case 3: clean distribution evidence classifies as distribution', Bitmomo_Regime_Taxonomy::REGIME_DISTRIBUTION === $result['regime'] );
check( 'Case 3: distribution confidence is high (>= 80)', $result['confidence'] >= 80 );

// ---------------------------------------------------------------------
// Case 4: Capitulation — sharp drawdown, vol shock, extreme liquidations.
// ---------------------------------------------------------------------
$result = classify(
	array(
		'return_1d'                => -12,
		'return_7d'                => -22,
		'return_30d'               => -30,
		'volatility_percentile'    => 90,
		'volatility_change'        => 25,
		'volume_percentile'        => 80,
		'momentum_score'           => -55,
		'range_position_pct'       => 8,
		'structure_state'          => 'breakdown',
		'funding_rate_pct'         => -0.05,
		'liquidation_pressure'     => 'extreme',
		'crowding_score'           => 60,
		'directional_bias'         => 'bearish',
		'open_interest_change_pct' => -10,
	)
);
check( 'Case 4: clean capitulation evidence classifies as capitulation', Bitmomo_Regime_Taxonomy::REGIME_CAPITULATION === $result['regime'] );
check( 'Case 4: capitulation confidence is high (>= 80)', $result['confidence'] >= 80 );

// ---------------------------------------------------------------------
// Case 5: Transition — ambiguous mixed evidence (Distribution vs Capitulation).
// Hand-computed: Distribution=50, Capitulation=45, margin=5 < TRANSITION_MARGIN(15).
// ---------------------------------------------------------------------
$result = classify(
	array(
		'return_1d'             => -3,
		'return_7d'             => -5,
		'return_30d'            => -20,
		'volatility_percentile' => 85,
		'volatility_change'     => 20,
		'volume_percentile'     => 55,
		'momentum_score'        => 10,
		'range_position_pct'    => 75,
		'structure_state'       => 'range',
		'funding_rate_pct'      => 0.0,
		'liquidation_pressure'  => 'extreme',
	)
);
check( 'Case 5: ambiguous mixed evidence classifies as transition', Bitmomo_Regime_Taxonomy::REGIME_TRANSITION === $result['regime'] );
check( 'Case 5: transition confidence stays below the clear-regime range (< 70)', $result['confidence'] < 70 );
check( 'Case 5: both near-tied candidates appear in conflicts', count( $result['conflicts'] ) === 2 );
check(
	'Case 5: conflicts mention both Distribution and Capitulation',
	(bool) array_filter( $result['conflicts'], fn( $c ) => false !== strpos( $c, 'Distribution' ) )
	&& (bool) array_filter( $result['conflicts'], fn( $c ) => false !== strpos( $c, 'Capitulation' ) )
);

// ---------------------------------------------------------------------
// Case 6: Transition — low-confidence / insufficient evidence (all scores 0).
// ---------------------------------------------------------------------
$result = classify(
	array(
		'return_1d'             => 0,
		'return_7d'             => 1,
		'return_30d'            => 12,
		'volatility_percentile' => 50,
		'volatility_change'     => 0,
		'volume_percentile'     => 55,
		'momentum_score'        => 25,
		'range_position_pct'    => 55,
		'structure_state'       => 'unknown',
		'funding_rate_pct'      => 0.03,
		'liquidation_pressure'  => 'none',
		'crowding_score'        => 40,
		'open_interest_change_pct' => 0,
	)
);
check( 'Case 6: insufficient evidence (all scores below threshold) classifies as transition', Bitmomo_Regime_Taxonomy::REGIME_TRANSITION === $result['regime'] );
check( 'Case 6: insufficient-evidence transition has low confidence (<= 40)', $result['confidence'] <= 40 );
check( 'Case 6: insufficient-evidence transition has no conflicts (nothing cleared the threshold)', empty( $result['conflicts'] ) );

// ---------------------------------------------------------------------
// Case 7: Missing optional inputs — same accumulation fixture as Case 1
// but every optional field null. Must still classify, must not error.
// ---------------------------------------------------------------------
$result = classify(
	array(
		'volatility_percentile' => 20,
		'return_30d'            => 3,
		'range_position_pct'    => 25,
		'structure_state'       => 'range',
		'volume_percentile'     => 30,
		'return_1d'             => 0.2,
		'return_7d'             => 1,
		'momentum_score'        => 5,
		'volatility_change'     => -2,
		// funding_rate_pct, liquidation_pressure, crowding_score,
		// open_interest_change_pct, directional_confidence all omitted -> null.
	)
);
check( 'Case 7: missing optional inputs still classifies (accumulation)', Bitmomo_Regime_Taxonomy::REGIME_ACCUMULATION === $result['regime'] );
check( 'Case 7: missing optional inputs degrades gracefully (still confident, just lower)', $result['confidence'] > 0 && $result['confidence'] < 97 );

// ---------------------------------------------------------------------
// Case 8: Repeated evaluation idempotency — identical input, identical output.
// ---------------------------------------------------------------------
$input_for_idempotency = Bitmomo_Regime_Input::validate(
	base_fields(
		array(
			'structure_state'          => 'breakout_up',
			'momentum_score'           => 55,
			'volume_percentile'        => 75,
			'return_7d'                => 12,
			'open_interest_change_pct' => 8,
			'range_position_pct'       => 82,
		)
	)
)['input'];
$run_1 = $classifier->evaluate( $input_for_idempotency );
$run_2 = $classifier->evaluate( $input_for_idempotency );
check( 'Case 8: repeated evaluation is idempotent (identical serialized output)', wp_json_encode_stub( $run_1 ) === wp_json_encode_stub( $run_2 ) );

function wp_json_encode_stub( $value ) {
	return serialize( $value ); // phpcs:ignore -- test-only, no WP json_encode available in this stub context.
}

// ---------------------------------------------------------------------
// Case 9: Bitmomo_Regime_Input::validate() — valid input normalizes cleanly.
// ---------------------------------------------------------------------
$validation = Bitmomo_Regime_Input::validate( base_fields( array( 'structure_state' => 'range' ) ) );
check( 'Case 9: fully-populated valid input validates', true === $validation['valid'] );
check( 'Case 9: valid input normalizes required numeric fields to float', is_float( $validation['input']['return_1d'] ) );

// ---------------------------------------------------------------------
// Case 10: Bitmomo_Regime_Input::validate() — missing required field fails
// closed, never returns a partially-fabricated record.
// ---------------------------------------------------------------------
$incomplete = base_fields();
unset( $incomplete['structure_state'] );
$validation = Bitmomo_Regime_Input::validate( $incomplete );
check( 'Case 10: missing required field fails validation', false === $validation['valid'] );
check( 'Case 10: failed validation returns null input, never a partial record', null === $validation['input'] );
check(
	'Case 10: failed validation names the missing field',
	(bool) array_filter( $validation['errors'], fn( $e ) => false !== strpos( $e, 'structure_state' ) )
);

// ---------------------------------------------------------------------
// Case 11: Bitmomo_Regime_Input::validate() — out-of-range percentile fails.
// ---------------------------------------------------------------------
$validation = Bitmomo_Regime_Input::validate( base_fields( array( 'volatility_percentile' => 150 ) ) );
check( 'Case 11: out-of-range percentile (150) fails validation', false === $validation['valid'] );

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

echo "\nNOTE: hysteresis (regime stability across evaluations) and 30-day\n";
echo "history persistence/ordering are REGIME PR 2 scope — this classifier\n";
echo "is a pure, stateless function of a single evaluation's input and has\n";
echo "no notion of \"previous\" state yet.\n";

exit( $pass_count === $total ? 0 : 1 );

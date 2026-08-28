<?php
/**
 * Standalone executable test for REGIME PR 2's hysteresis logic.
 *
 * Not a WordPress PHPUnit suite — Bitmomo_Regime_Hysteresis is pure PHP
 * (no WordPress dependency), so this runs directly via
 * `php tests/test-bitmomo-regime-hysteresis.php` against a minimal
 * ABSPATH stub. Bitmomo_Regime_State_Store (the persistence layer) is
 * NOT covered here — it requires a real WordPress database and is
 * exercised on staging instead, same convention as bitmomo-pro's
 * WP-dependent classes.
 *
 * Run: php tests/test-bitmomo-regime-hysteresis.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-config.php';
require __DIR__ . '/../includes/class-bitmomo-regime-hysteresis.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function candidate( $regime, $confidence = 50 ) {
	return array(
		'regime'     => $regime,
		'confidence' => $confidence,
	);
}

function record( $regime, $pending_regime = null, $pending_streak = 0 ) {
	return array(
		'regime'         => $regime,
		'pending_regime' => $pending_regime,
		'pending_streak' => $pending_streak,
	);
}

$hysteresis = new Bitmomo_Regime_Hysteresis();

// ---------------------------------------------------------------------
// Case 1: no previous record -> accept as-is, 'initial'.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'accumulation', 60 ), null );
check( 'Case 1: no previous record accepts the candidate as-is', 'accumulation' === $decision['regime'] );
check( 'Case 1: transition_strength is initial', 'initial' === $decision['transition_strength'] );
check( 'Case 1: changed is true', true === $decision['changed'] );

// ---------------------------------------------------------------------
// Case 2: candidate matches the current official regime -> held, no change.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'expansion', 55 ), record( 'expansion' ) );
check( 'Case 2: matching candidate holds the official regime', 'expansion' === $decision['regime'] );
check( 'Case 2: transition_strength is held', 'held' === $decision['transition_strength'] );
check( 'Case 2: changed is false', false === $decision['changed'] );
check( 'Case 2: no pending regime tracked', null === $decision['pending_regime'] );

// ---------------------------------------------------------------------
// Case 3: candidate is transition -> reflected immediately regardless of
// confidence, since it is non-committal (no flip-flop risk).
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'transition', 30 ), record( 'accumulation' ) );
check( 'Case 3: transition candidate is reflected immediately', 'transition' === $decision['regime'] );
check( 'Case 3: transition_strength is transitioned', 'transitioned' === $decision['transition_strength'] );
check( 'Case 3: changed is true', true === $decision['changed'] );

// ---------------------------------------------------------------------
// Case 4: a new clear regime below the confirmation streak, first
// occurrence -> pending, official regime held, streak = 1. State is NOT
// hidden — pending_regime and pending_streak are both populated.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'expansion', 50 ), record( 'accumulation' ) );
check( 'Case 4: first occurrence of a new regime holds the official regime', 'accumulation' === $decision['regime'] );
check( 'Case 4: transition_strength is pending', 'pending' === $decision['transition_strength'] );
check( 'Case 4: pending_regime is exposed, not hidden', 'expansion' === $decision['pending_regime'] );
check( 'Case 4: pending_streak is 1', 1 === $decision['pending_streak'] );
check( 'Case 4: changed is false', false === $decision['changed'] );

// ---------------------------------------------------------------------
// Case 5: same new regime confirmed a second consecutive time (streak
// reaches HYSTERESIS_CONFIRMATION_STREAK = 2) -> official switch.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'expansion', 50 ), record( 'accumulation', 'expansion', 1 ) );
check( 'Case 5: second consecutive agreeing candidate confirms the switch', 'expansion' === $decision['regime'] );
check( 'Case 5: transition_strength is confirmed', 'confirmed' === $decision['transition_strength'] );
check( 'Case 5: pending state is cleared after confirming', null === $decision['pending_regime'] && 0 === $decision['pending_streak'] );
check( 'Case 5: changed is true', true === $decision['changed'] );

// ---------------------------------------------------------------------
// Case 6: candidate flips to a DIFFERENT new regime before confirming —
// the streak resets to 1 for the new candidate rather than accumulating
// toward the abandoned one. Guards against flip-flopping among candidates.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'distribution', 50 ), record( 'accumulation', 'expansion', 1 ) );
check( 'Case 6: switching candidates resets the streak (does not carry over)', 1 === $decision['pending_streak'] );
check( 'Case 6: the new candidate is what is now pending', 'distribution' === $decision['pending_regime'] );
check( 'Case 6: official regime is still held (not confirmed after only 1)', 'accumulation' === $decision['regime'] );

// ---------------------------------------------------------------------
// Case 7: overwhelming confidence bypasses the confirmation streak
// entirely, even on the very first occurrence — a severe move (e.g. a
// clear capitulation day) must not be artificially delayed.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'capitulation', 90 ), record( 'expansion' ) );
check( 'Case 7: high-confidence candidate overrides immediately', 'capitulation' === $decision['regime'] );
check( 'Case 7: transition_strength is overridden', 'overridden' === $decision['transition_strength'] );
check( 'Case 7: changed is true', true === $decision['changed'] );

// ---------------------------------------------------------------------
// Case 8: matching the official regime again clears an in-progress
// pending streak toward a different regime — the market walked back
// toward where it already officially was.
// ---------------------------------------------------------------------
$decision = $hysteresis->apply( candidate( 'accumulation', 50 ), record( 'accumulation', 'expansion', 1 ) );
check( 'Case 8: reverting to the official regime clears the pending streak', 0 === $decision['pending_streak'] && null === $decision['pending_regime'] );
check( 'Case 8: transition_strength is held', 'held' === $decision['transition_strength'] );

// ---------------------------------------------------------------------
// Case 9: idempotency — same inputs, same decision, every time.
// ---------------------------------------------------------------------
$c   = candidate( 'expansion', 50 );
$r   = record( 'accumulation', 'expansion', 1 );
$run1 = $hysteresis->apply( $c, $r );
$run2 = $hysteresis->apply( $c, $r );
check( 'Case 9: repeated evaluation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

echo "\nNOTE: Bitmomo_Regime_State_Store (persistence: bm_regime_state CPT,\n";
echo "append-only writes, get_latest()/get_recent()) requires a real\n";
echo "WordPress database and is not exercised by this stub — verify on\n";
echo "staging, and see REGIME PR 3 for the 30-day read API built on top\n";
echo "of get_recent().\n";

exit( $pass_count === $total ? 0 : 1 );

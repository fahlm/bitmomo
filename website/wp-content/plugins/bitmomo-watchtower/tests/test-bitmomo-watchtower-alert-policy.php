<?php
/**
 * Standalone executable test for WATCHTOWER PR 3's cooldown/alert policy
 * (Bitmomo_Watchtower_Alert_Policy). No alert is ever actually sent by
 * this class — these tests only assert on the should_alert/alert_class
 * decision.
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-alert-policy.php
 *
 * Cooldown boundary fixtures were hand-computed against
 * Bitmomo_Watchtower_Config::ALERT_COOLDOWN_MINUTES (60) using a fixed
 * "now" so elapsed-minutes math is exact and not a source of flakiness.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-state-transition-engine.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-alert-policy.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

$policy = new Bitmomo_Watchtower_Alert_Policy();
$NOW    = '2026-08-28 12:00:00';

// ---------------------------------------------------------------------
// Case 1: the transition -> alert class mapping is correct for all 5
// alerting transition types; NO_CHANGE maps to no alert class at all.
// ---------------------------------------------------------------------
$expected_map = array(
	Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE => Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE,
	Bitmomo_Watchtower_State_Transition_Engine::TYPE_CONFIDENCE_CHANGE      => Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_CONFIDENCE_SHIFT,
	Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE          => Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_THESIS_CHANGE,
	Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_INVALIDATED     => Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_THESIS_INVALIDATED,
	Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED          => Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_DATA_QUALITY,
);
$all_correct = true;
foreach ( $expected_map as $transition_type => $expected_class ) {
	$result = $policy->evaluate( $transition_type, null, $NOW );
	if ( $result['alert_class'] !== $expected_class || true !== $result['should_alert'] ) {
		$all_correct = false;
	}
}
check( 'Case 1: all 5 alerting transition types map to their documented alert class and alert when no prior alert exists', $all_correct );

$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE, null, $NOW );
check( 'Case 1: NO_CHANGE maps to no alert class and never alerts', null === $result['alert_class'] && false === $result['should_alert'] );

// ---------------------------------------------------------------------
// Case 2: no prior alert for the class -> always alerts (no cooldown to
// measure against).
// ---------------------------------------------------------------------
$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, null, $NOW );
check( 'Case 2: no prior alert for the class alerts immediately', true === $result['should_alert'] );

// ---------------------------------------------------------------------
// Case 3: exact cooldown boundary — a prior alert exactly 60 minutes ago
// (the cooldown length, inclusive) is OUTSIDE the cooldown and alerts;
// one minute short of that (59 minutes ago) is still within the
// cooldown and is suppressed.
// ---------------------------------------------------------------------
$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, '2026-08-28 11:00:00', $NOW ); // exactly 60 min ago
check( 'Case 3: a prior alert exactly 60 minutes ago (cooldown boundary) is allowed to alert again', true === $result['should_alert'] );

$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, '2026-08-28 11:01:00', $NOW ); // 59 min ago
check( 'Case 3: a prior alert 59 minutes ago (inside the cooldown) is suppressed', false === $result['should_alert'] );
check( 'Case 3: a suppressed alert still reports its alert_class (for transparency)', Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE === $result['alert_class'] );

// ---------------------------------------------------------------------
// Case 4: THESIS_INVALIDATED and DATA_QUALITY are cooldown-exempt — they
// alert even when the prior alert of the same class was 1 minute ago.
// ---------------------------------------------------------------------
$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_INVALIDATED, '2026-08-28 11:59:00', $NOW ); // 1 min ago
check( 'Case 4: THESIS_INVALIDATED bypasses cooldown even 1 minute after a prior alert', true === $result['should_alert'] );

$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED, '2026-08-28 11:59:00', $NOW ); // 1 min ago
check( 'Case 4: DATA_DEGRADED (DATA_QUALITY alert class) bypasses cooldown even 1 minute after a prior alert', true === $result['should_alert'] );

// ---------------------------------------------------------------------
// Case 5: THESIS_CHANGE and CONFIDENCE_SHIFT are NOT exempt — they
// respect the cooldown like MATERIAL_CHANGE does.
// ---------------------------------------------------------------------
$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE, '2026-08-28 11:59:00', $NOW ); // 1 min ago
check( 'Case 5: THESIS_CHANGE respects cooldown (not exempt)', false === $result['should_alert'] );

$result = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_CONFIDENCE_CHANGE, '2026-08-28 11:59:00', $NOW ); // 1 min ago
check( 'Case 5: CONFIDENCE_CHANGE respects cooldown (not exempt)', false === $result['should_alert'] );

// ---------------------------------------------------------------------
// Case 6: idempotency.
// ---------------------------------------------------------------------
$run1 = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, '2026-08-28 11:00:00', $NOW );
$run2 = $policy->evaluate( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, '2026-08-28 11:00:00', $NOW );
check( 'Case 6: repeated evaluation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

echo "\nNOTE: this class only decides SHOULD an alert be produced — it never\n";
echo "sends anything. See Bitmomo_Watchtower_Alert_Outbox (WordPress-\n";
echo "dependent, not covered by this stub) for the provider-neutral queue.\n";

exit( $pass_count === $total ? 0 : 1 );

<?php
/**
 * Alert policy safety review (Integration Hardening Phase, Task 7).
 *
 * Six explicit scenarios requested by the brief, each run against the
 * REAL Bitmomo_Watchtower_State_Transition_Engine and
 * Bitmomo_Watchtower_Alert_Policy (plus a minimal in-memory stand-in for
 * Bitmomo_Watchtower_Alert_Outbox::get_last_for_class(), since the real
 * outbox is WordPress-dependent — the stand-in below reproduces its exact
 * query shape: "most recent alert for this $alert_class", filtered ONLY
 * by alert_class, exactly like the real class's meta_query. See
 * ALERT_POLICY_SAFETY_REVIEW.md for the write-up these tests support.
 *
 * Per the brief: do NOT retune ALERT_COOLDOWN_MINUTES, CONFIDENCE_CHANGE_
 * THRESHOLD, EXPECTED_RANGE_CHANGE_PCT, or COOLDOWN_EXEMPT_ALERT_CLASSES
 * just because a scenario below looks arguably too strict or too loose —
 * this file tests and documents current behavior, it does not change it.
 *
 * Run: php tests/test-bitmomo-watchtower-alert-safety.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-thesis.php';
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

/**
 * Minimal in-memory stand-in for Bitmomo_Watchtower_Alert_Outbox's
 * get_last_for_class()/enqueue() pair, reproducing its real query shape
 * exactly: lookup is keyed ONLY by alert_class (see the real class's
 * meta_query, which has no domain clause at all) — never by domain. This
 * is not a mock of behavior we're guessing at; it mirrors the actual
 * `_bitmomo_watchtower_alert_alert_class`-only meta_query in
 * includes/class-bitmomo-watchtower-alert-outbox.php::get_last_for_class().
 */
class Fake_Alert_Ledger {
	private $last_by_class = array();

	public function get_last_for_class( $alert_class ) {
		return isset( $this->last_by_class[ $alert_class ] ) ? $this->last_by_class[ $alert_class ] : null;
	}

	public function record( $alert_class, $created_at ) {
		$this->last_by_class[ $alert_class ] = array( 'created_at' => $created_at );
	}
}

/**
 * Bitmomo_Watchtower_Alert_Policy::evaluate() expects the SECOND argument
 * to be a plain 'Y-m-d H:i:s' string (or null), not the outbox's full
 * hydrated record — every real caller (Bitmomo_Watchtower_Orchestrator)
 * extracts 'created_at' before passing it in. This helper does the same
 * extraction against our Fake_Alert_Ledger stand-in.
 */
function last_alert_ts( Fake_Alert_Ledger $ledger, $alert_class ) {
	$record = $ledger->get_last_for_class( $alert_class );
	return null !== $record ? $record['created_at'] : null;
}

function thesis( $overrides = array() ) {
	return array_merge(
		array(
			'regime'              => 'accumulation',
			'directional_bias'    => 'neutral',
			'confidence'          => 60.0,
			'expected_range_low'  => 100000.0,
			'expected_range_high' => 110000.0,
			'invalidation'        => 'Daily close below 95000.',
			'major_driver'        => 'Low-volatility accumulation.',
			'source_record_id'    => 'thesis-src',
		),
		$overrides
	);
}

$transition_engine = new Bitmomo_Watchtower_State_Transition_Engine();
$policy             = new Bitmomo_Watchtower_Alert_Policy();

// =======================================================================
// Scenario 1 — repeated same-class alert within cooldown.
// =======================================================================
$ledger = new Fake_Alert_Ledger();
$prev   = Bitmomo_Watchtower_Thesis::validate( thesis() )['input'];
$new1   = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'expected_range_high' => 116000.0 ) ) )['input']; // +5.45% -> MATERIAL_CONTEXT_UPDATE

$t1 = $transition_engine->evaluate( $new1, $prev, array() );
check( 'Scenario 1 setup: first evaluation classifies MATERIAL_CONTEXT_UPDATE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $t1['transition_type'] );

$a1 = $policy->evaluate( $t1['transition_type'], last_alert_ts( $ledger, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE ), '2026-08-28 10:00:00' );
check( 'Scenario 1: first MATERIAL_CHANGE alert (no prior) fires', true === $a1['should_alert'] );
$ledger->record( Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE, '2026-08-28 10:00:00' );

// Same underlying thesis re-evaluated again 20 minutes later (e.g. the
// same market condition simply persists) -> same transition_type again.
$t2 = $transition_engine->evaluate( $new1, $prev, array() );
$a2 = $policy->evaluate( $t2['transition_type'], last_alert_ts( $ledger, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE ), '2026-08-28 10:20:00' ); // 20 min later
check( 'Scenario 1: a REPEATED same-class alert 20 minutes later (inside the 60-minute cooldown) is suppressed', false === $a2['should_alert'] );
check( 'Scenario 1: the suppressed repeat still reports the correct alert_class for admin visibility', Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE === $a2['alert_class'] );

// =======================================================================
// Scenario 2 — a STRONGER second event of the SAME class during cooldown.
// Documents a real ambiguity: Bitmomo_Watchtower_Alert_Policy::evaluate()
// takes no magnitude/severity input at all — only transition_type and
// timing. A dramatically larger second MATERIAL_CONTEXT_UPDATE is
// suppressed identically to a marginal one, as long as both map to the
// same alert_class and both occur inside the cooldown window.
// =======================================================================
$ledger2 = new Fake_Alert_Ledger();
$weak_change   = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'expected_range_high' => 116000.0 ) ) )['input']; // +5.45%
$strong_change = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'expected_range_high' => 200000.0 ) ) )['input']; // +81.8% -- dramatically larger

$t_weak = $transition_engine->evaluate( $weak_change, $prev, array() );
check( 'Scenario 2 setup: the weak change classifies MATERIAL_CONTEXT_UPDATE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $t_weak['transition_type'] );
$a_weak = $policy->evaluate( $t_weak['transition_type'], null, '2026-08-28 10:00:00' );
check( 'Scenario 2: the first (weak) MATERIAL_CHANGE alert fires', true === $a_weak['should_alert'] );
$ledger2->record( Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE, '2026-08-28 10:00:00' );

$t_strong = $transition_engine->evaluate( $strong_change, $prev, array() );
check( 'Scenario 2 setup: the dramatically larger change ALSO classifies MATERIAL_CONTEXT_UPDATE (same class as the weak one, not something stronger like THESIS_CHANGE, because regime/bias/confidence are all still unchanged)', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $t_strong['transition_type'] );
$a_strong = $policy->evaluate( $t_strong['transition_type'], last_alert_ts( $ledger2, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE ), '2026-08-28 10:15:00' ); // 15 min later, well inside cooldown
check(
	'Scenario 2 (DOCUMENTED AMBIGUITY): a dramatically stronger second MATERIAL_CONTEXT_UPDATE, 15 minutes after the first, is STILL suppressed — Alert_Policy has no severity-based cooldown bypass for MATERIAL_CHANGE the way THESIS_INVALIDATED/DATA_QUALITY are exempt entirely',
	false === $a_strong['should_alert']
);

// Escalation path that DOES bypass this: if the second event is strong
// enough to also flip regime/bias (not just the expected range), it
// becomes a DIFFERENT alert_class (THESIS_CHANGE) with its own,
// independent cooldown clock -- which has never alerted yet, so it fires
// immediately even though MATERIAL_CHANGE is still in cooldown.
$regime_flip = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'regime' => 'expansion', 'directional_bias' => 'bullish' ) ) )['input'];
$t_escalated = $transition_engine->evaluate( $regime_flip, $prev, array() );
check( 'Scenario 2 escalation path: a regime/bias-flipping second event classifies as THESIS_CHANGE, a different alert_class', Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE === $t_escalated['transition_type'] );
$a_escalated = $policy->evaluate( $t_escalated['transition_type'], last_alert_ts( $ledger2, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_THESIS_CHANGE ), '2026-08-28 10:15:00' );
check( 'Scenario 2 escalation path: THESIS_CHANGE has its own independent cooldown clock and alerts immediately even while MATERIAL_CHANGE is in cooldown', true === $a_escalated['should_alert'] );

// =======================================================================
// Scenario 3 — invalidation during cooldown.
// =======================================================================
$ledger3 = new Fake_Alert_Ledger();
$ledger3->record( Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_THESIS_INVALIDATED, '2026-08-28 10:00:00' );
$invalidated_new = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'source_record_id' => 'thesis-src-2' ) ) )['input'];
$t_invalidated    = $transition_engine->evaluate( $invalidated_new, $prev, array( 'invalidation_triggered' => true ) );
check( 'Scenario 3 setup: invalidation_triggered context flag produces THESIS_INVALIDATED regardless of the thesis diff', Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_INVALIDATED === $t_invalidated['transition_type'] );
$a_invalidated = $policy->evaluate( $t_invalidated['transition_type'], last_alert_ts( $ledger3, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_THESIS_INVALIDATED ), '2026-08-28 10:05:00' ); // 5 min after a prior THESIS_INVALIDATED alert
check( 'Scenario 3: a SECOND invalidation just 5 minutes after a prior invalidation alert STILL fires — THESIS_INVALIDATED is cooldown-exempt', true === $a_invalidated['should_alert'] );

// =======================================================================
// Scenario 4 — data degradation during cooldown.
// =======================================================================
$ledger4 = new Fake_Alert_Ledger();
$ledger4->record( Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_DATA_QUALITY, '2026-08-28 10:00:00' );
$degraded_new = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'source_record_id' => 'thesis-src-3' ) ) )['input'];
$t_degraded    = $transition_engine->evaluate( $degraded_new, $prev, array( 'data_quality_degraded' => true ) );
check( 'Scenario 4 setup: data_quality_degraded context flag produces DATA_DEGRADED, overriding every other classification', Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED === $t_degraded['transition_type'] );
$a_degraded = $policy->evaluate( $t_degraded['transition_type'], last_alert_ts( $ledger4, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_DATA_QUALITY ), '2026-08-28 10:03:00' ); // 3 min after a prior DATA_QUALITY alert
check( 'Scenario 4: a SECOND data-degraded evaluation just 3 minutes after a prior DATA_QUALITY alert STILL fires — DATA_QUALITY is cooldown-exempt', true === $a_degraded['should_alert'] );

// =======================================================================
// Scenario 5 — multiple domains producing the SAME transition_type.
// DOCUMENTED AMBIGUITY: Bitmomo_Watchtower_Alert_Policy::evaluate() and
// Bitmomo_Watchtower_Alert_Outbox::get_last_for_class() both key the
// cooldown ONLY by alert_class — never by domain. A MATERIAL_CONTEXT_
// UPDATE in the 'market' domain and a completely unrelated MATERIAL_
// CONTEXT_UPDATE in the 'derivatives' domain SHARE one cooldown clock.
// =======================================================================
$ledger5 = new Fake_Alert_Ledger();
// Domain A ('market'): a range-shift MATERIAL_CONTEXT_UPDATE alerts.
$market_change = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'expected_range_high' => 116000.0 ) ) )['input'];
$t_market = $transition_engine->evaluate( $market_change, $prev, array( 'domain' => 'market' ) );
$a_market = $policy->evaluate( $t_market['transition_type'], last_alert_ts( $ledger5, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE ), '2026-08-28 10:00:00' );
check( 'Scenario 5 setup: the market-domain MATERIAL_CONTEXT_UPDATE alerts', true === $a_market['should_alert'] );
$ledger5->record( Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE, '2026-08-28 10:00:00' );

// Domain B ('derivatives'): a DIFFERENT, unrelated MATERIAL_CONTEXT_UPDATE
// (e.g. a major_driver text change from a derivatives-only event) 10
// minutes later -- still well inside the same 60-minute cooldown window.
$derivatives_change = Bitmomo_Watchtower_Thesis::validate( thesis( array( 'major_driver' => 'Funding rate flipped extreme negative on major venues.' ) ) )['input'];
$t_derivatives = $transition_engine->evaluate( $derivatives_change, $prev, array( 'domain' => 'derivatives' ) );
check( 'Scenario 5 setup: the derivatives-domain change also classifies MATERIAL_CONTEXT_UPDATE (different domain, same alert_class)', Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE === $t_derivatives['transition_type'] );
$a_derivatives = $policy->evaluate( $t_derivatives['transition_type'], last_alert_ts( $ledger5, Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE ), '2026-08-28 10:10:00' );
check(
	'Scenario 5 (DOCUMENTED AMBIGUITY): an unrelated derivatives-domain MATERIAL_CONTEXT_UPDATE 10 minutes after a market-domain one is SUPPRESSED — cooldown is tracked per alert_class GLOBALLY, not per domain, so two genuinely unrelated events of the same class within 60 minutes share one cooldown clock',
	false === $a_derivatives['should_alert']
);

// =======================================================================
// Scenario 6 — NO_CHANGE never alerts (irrespective of cooldown state,
// including when NO other class has ever alerted, so there is no
// cooldown to even suppress it -- it is unmapped, not "on cooldown").
// =======================================================================
$identical = Bitmomo_Watchtower_Thesis::validate( thesis() )['input'];
$t_no_change = $transition_engine->evaluate( $identical, $prev, array() );
check( 'Scenario 6 setup: an identical thesis classifies NO_CHANGE', Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE === $t_no_change['transition_type'] );
$a_no_change_1 = $policy->evaluate( $t_no_change['transition_type'], null, '2026-08-28 10:00:00' );
check( 'Scenario 6: NO_CHANGE never alerts even with no prior alert history at all', false === $a_no_change_1['should_alert'] && null === $a_no_change_1['alert_class'] );
$a_no_change_2 = $policy->evaluate( $t_no_change['transition_type'], '2026-08-28 09:00:00', '2026-08-28 10:00:00' ); // even with a (nonsensical) "prior alert" timestamp supplied
check( 'Scenario 6: NO_CHANGE never alerts even if a last_alert_at_for_class value is (incorrectly) supplied for it — unmapped transition types are always false, never dependent on timing', false === $a_no_change_2['should_alert'] );

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

echo "\nSee ALERT_POLICY_SAFETY_REVIEW.md for the write-up of the two\n";
echo "documented ambiguities this suite proves (Scenario 2: no severity-\n";
echo "based cooldown bypass; Scenario 5: cooldown is not domain-scoped).\n";
echo "Neither is fixed here, per the brief's 'do not retune' instruction.\n";

exit( $pass_count === $total ? 0 : 1 );

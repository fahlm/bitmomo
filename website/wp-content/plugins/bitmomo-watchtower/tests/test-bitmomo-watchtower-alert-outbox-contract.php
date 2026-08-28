<?php
/**
 * Alert Outbox enqueue() enum-validation contract (Integration Hardening
 * Phase, Task 11 — static security audit).
 *
 * Bitmomo_Watchtower_Alert_Outbox::enqueue() used to accept ANY non-empty
 * string for alert_class/transition_type — unlike Bitmomo_Watchtower_
 * Event_Input::validate() and Bitmomo_Watchtower_Thesis::validate(), which
 * both validate their enum-typed fields against real registered value
 * lists. That was the one concrete "unsafe meta write" defect this audit
 * found: a caller (including a future Codex-built one) that passed a
 * misspelled or unregistered alert_class/transition_type would have had
 * it silently persisted as post meta forever — no rejection, no signal.
 *
 * The fix (see includes/class-bitmomo-watchtower-alert-outbox.php)
 * validates alert_class against Bitmomo_Watchtower_Alert_Policy::
 * ALERT_CLASSES and transition_type against Bitmomo_Watchtower_State_
 * Transition_Engine::TYPES, both already-defined enums inside this same
 * plugin — no new dependency, no behavior change for any caller that was
 * already passing real values (which is every caller in this codebase
 * today; see class-bitmomo-watchtower-orchestrator.php).
 *
 * This file stubs the small set of WordPress functions
 * Bitmomo_Watchtower_Alert_Outbox touches (add_action at construction,
 * wp_insert_post/update_post_meta on the success path) so the contract
 * can be proven with plain `php`, no WordPress runtime required — exactly
 * the convention used by every other tests/test-*.php file in this repo.
 *
 * Run: php tests/test-bitmomo-watchtower-alert-outbox-contract.php
 */

require __DIR__ . '/wp-stubs.php';

// --- Minimal WP function stubs, only what Alert_Outbox's constructor and
// --- the success path of enqueue()/record() touch. None of the invalid
// --- cases below ever reach wp_insert_post()/update_post_meta() — that's
// --- the whole point of the fix — but a positive control case does.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// no-op: this test never fires 'init', so register_post_type()
		// (which calls the real register_post_type()) never runs.
	}
}

$GLOBALS['__fake_posts'] = array();
$GLOBALS['__fake_meta']  = array();

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		$id                              = count( $GLOBALS['__fake_posts'] ) + 1;
		$GLOBALS['__fake_posts'][ $id ]  = $postarr;
		return $id;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['__fake_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false; // the fake wp_insert_post() above never returns a WP_Error.
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return '2026-08-28 00:00:00';
	}
}

require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-event-input.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-materiality-engine.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-deduplicator.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-thesis.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-thesis-store.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-state-transition-engine.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-analyst-router.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-alert-policy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-alert-outbox.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function valid_alert() {
	return array(
		'alert_class'     => Bitmomo_Watchtower_Alert_Policy::ALERT_CLASS_MATERIAL_CHANGE,
		'transition_type' => Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE,
		'reason'          => 'expected range widened materially',
		'track'           => 'derivatives',
		'domain'          => 'market',
		'payload'         => array( 'diff' => array() ),
	);
}

$outbox = Bitmomo_Watchtower_Alert_Outbox::instance();

// --- Positive control: every real caller in this codebase passes real
// --- enum values today (see class-bitmomo-watchtower-orchestrator.php),
// --- so the fix must not change behavior for them.
$result = $outbox->enqueue( valid_alert() );
check( 'positive control: real alert_class + real transition_type still succeeds', true === $result['success'] );
check( 'positive control: record is returned with an id', isset( $result['record']['id'] ) && $result['record']['id'] > 0 );

// --- Case A: unregistered alert_class (typo / made up value).
$bad = valid_alert();
$bad['alert_class'] = 'MATERIAL_CHANGEE'; // typo
$result = $outbox->enqueue( $bad );
check( 'Case A: unregistered alert_class is rejected', false === $result['success'] );
check( 'Case A: rejection names ALERT_CLASSES', false !== strpos( $result['errors'][0], 'ALERT_CLASSES' ) );

// --- Case B: unregistered transition_type.
$bad = valid_alert();
$bad['transition_type'] = 'MATERIAL_UPDATE'; // not a real Bitmomo_Watchtower_State_Transition_Engine::TYPE
$result = $outbox->enqueue( $bad );
check( 'Case B: unregistered transition_type is rejected', false === $result['success'] );
check( 'Case B: rejection names TYPES', false !== strpos( $result['errors'][0], 'TYPES' ) );

// --- Case C: alert_class that IS a real string somewhere in the domain
// --- (a real transition_type value, MATERIAL_CONTEXT_UPDATE, which does
// --- not collide with any ALERT_CLASSES string — unlike THESIS_CHANGE/
// --- THESIS_INVALIDATED, which happen to share their literal string
// --- with both enums by design) but not a real alert_class — proves the
// --- two enums are checked independently, not just "is this any known
// --- string in the plugin."
$bad = valid_alert();
$bad['alert_class'] = Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE; // wrong enum
$result = $outbox->enqueue( $bad );
check( 'Case C: a real transition_type value is still rejected as alert_class', false === $result['success'] );

// --- Case D: every real ALERT_CLASSES value succeeds (no false positives
// --- from the new check — this proves the fix doesn't over-restrict).
$all_pass = true;
foreach ( Bitmomo_Watchtower_Alert_Policy::ALERT_CLASSES as $class ) {
	$alert                = valid_alert();
	$alert['alert_class'] = $class;
	$r                    = $outbox->enqueue( $alert );
	if ( true !== $r['success'] ) {
		$all_pass = false;
	}
}
check( 'Case D: every real ALERT_CLASSES value is accepted', $all_pass );

// --- Case E: every real TYPES value (including NO_CHANGE, which the
// --- orchestrator never actually passes to enqueue() in practice, but
// --- enqueue() itself has no reason to reject it structurally — it is a
// --- real, registered transition type) succeeds.
$all_pass = true;
foreach ( Bitmomo_Watchtower_State_Transition_Engine::TYPES as $type ) {
	$alert                    = valid_alert();
	$alert['transition_type'] = $type;
	$r                        = $outbox->enqueue( $alert );
	if ( true !== $r['success'] ) {
		$all_pass = false;
	}
}
check( 'Case E: every real State_Transition_Engine::TYPES value is accepted', $all_pass );

// --- Case F: the pre-existing non-empty check still fires first/still
// --- works (fix is additive, not a replacement).
$result = $outbox->enqueue( array( 'alert_class' => '', 'transition_type' => '' ) );
check( 'Case F: pre-existing empty-value rejection still works', false === $result['success'] );

$passed = 0;
$total  = count( $results );
foreach ( $results as $r ) {
	$status = $r['pass'] ? 'PASS' : 'FAIL';
	if ( $r['pass'] ) {
		++$passed;
	}
	echo "[{$status}] {$r['label']}\n";
}

echo "\n{$passed}/{$total} passed.\n";

exit( $passed === $total ? 0 : 1 );

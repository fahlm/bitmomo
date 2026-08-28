<?php
/**
 * Event contract fixture regression test (Integration Hardening Phase,
 * Task 4).
 *
 * Loads every tests/fixtures/watchtower-*.json fixture and re-runs the
 * REAL Bitmomo_Watchtower_Event_Input::validate(),
 * Bitmomo_Watchtower_Materiality_Engine::evaluate(),
 * Bitmomo_Watchtower_Deduplicator::cluster(),
 * Bitmomo_Watchtower_State_Transition_Engine::evaluate(),
 * Bitmomo_Watchtower_Alert_Policy::evaluate(), and
 * Bitmomo_Watchtower_Analyst_Router::route() against each fixture's
 * inputs, asserting the live result matches what the fixture documents.
 *
 * Same purpose as tests/test-bitmomo-regime-fixtures.php in the sibling
 * plugin: if a future change to any of these engines or their config
 * thresholds changes a fixture's outcome, this test fails loudly instead
 * of the fixture silently drifting out of sync with what Codex was told
 * to expect.
 *
 * Run: php tests/test-bitmomo-watchtower-fixtures.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-event-input.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-materiality-engine.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-deduplicator.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-thesis.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-state-transition-engine.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-alert-policy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-analyst-router.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function deep_matches( $expected, $actual ) {
	// json_decode(..., true) produces plain arrays; compare as normalized
	// JSON so int/float distinctions introduced by PHP's own type coercion
	// (e.g. 55 vs 55.0) don't produce false negatives.
	return json_encode( $expected ) === json_encode( $actual );
}

$engine             = new Bitmomo_Watchtower_Materiality_Engine();
$deduplicator       = new Bitmomo_Watchtower_Deduplicator();
$transition_engine  = new Bitmomo_Watchtower_State_Transition_Engine();
$alert_policy       = new Bitmomo_Watchtower_Alert_Policy();
$router             = new Bitmomo_Watchtower_Analyst_Router();

$fixtures_dir  = __DIR__ . '/fixtures';
$fixture_files = glob( $fixtures_dir . '/watchtower-*.json' );
sort( $fixture_files );

check( 'At least 6 Watchtower event fixtures exist', count( $fixture_files ) >= 6 );

$seen_event_types = array();

foreach ( $fixture_files as $path ) {
	$name    = basename( $path );
	$decoded = json_decode( file_get_contents( $path ), true );

	check( "{$name}: parses as valid JSON", null !== $decoded && JSON_ERROR_NONE === json_last_error() );
	if ( null === $decoded ) {
		continue;
	}

	foreach ( array( 'fixture_id', 'event_type', 'raw_event_candidate', 'expected_normalized_event', 'expected_materiality', 'expected_dedup_behavior', 'expected_alert_transition_outcome' ) as $key ) {
		check( "{$name}: has required top-level key '{$key}'", array_key_exists( $key, $decoded ) );
	}

	// --- Event validation + materiality -------------------------------
	$validated = Bitmomo_Watchtower_Event_Input::validate( $decoded['raw_event_candidate'] );
	check( "{$name}: raw_event_candidate passes Bitmomo_Watchtower_Event_Input::validate()", true === $validated['valid'] );
	if ( ! $validated['valid'] ) {
		continue;
	}
	check(
		"{$name}: normalized_event matches expected exactly",
		deep_matches( $decoded['expected_normalized_event'], $validated['input'] )
	);

	$materiality = $engine->evaluate( $validated['input'] );
	check(
		"{$name}: materiality result matches expected exactly (score {$materiality['score']}, band {$materiality['band']})",
		deep_matches( $decoded['expected_materiality'], $materiality )
	);

	// --- Dedup ----------------------------------------------------------
	$batch_summaries = $decoded['expected_dedup_behavior']['batch_inputs_summary'];
	$batch = array();
	foreach ( $batch_summaries as $i => $summary ) {
		$candidate = $validated['input'];
		$candidate['occurred_at'] = $summary['occurred_at'];
		$candidate['headline']    = $summary['headline'];
		$candidate['source_id']   = $validated['input']['source_id'] . ( 0 === $i ? '' : ( 1 === $i ? ':follow-up' : ':second-wave' ) );
		$candidate['score']       = $materiality['score'];
		$candidate['band']        = $materiality['band'];
		$batch[]                  = $candidate;
	}
	$clusters = $deduplicator->cluster( $batch );
	check(
		"{$name}: dedup cluster count matches expected ({$decoded['expected_dedup_behavior']['expected_cluster_count']})",
		count( $clusters ) === $decoded['expected_dedup_behavior']['expected_cluster_count']
	);
	check(
		"{$name}: dedup member counts match expected",
		array_map(
			function ( $c ) {
				return $c['member_count'];
			},
			$clusters
		) === $decoded['expected_dedup_behavior']['expected_member_counts']
	);

	// --- Illustrative / literal alert-transition outcome ----------------
	$outcome = $decoded['expected_alert_transition_outcome'];
	$prev_v  = Bitmomo_Watchtower_Thesis::validate( $outcome['previous_thesis'] );
	$new_v   = Bitmomo_Watchtower_Thesis::validate( $outcome['new_thesis'] );
	check( "{$name}: illustrative previous_thesis validates", true === $prev_v['valid'] );
	check( "{$name}: illustrative new_thesis validates", true === $new_v['valid'] );

	$transition = $transition_engine->evaluate( $new_v['input'], $prev_v['input'], $outcome['context'] );
	check(
		"{$name}: transition_type matches expected ({$outcome['expected_transition']['transition_type']})",
		$outcome['expected_transition']['transition_type'] === $transition['transition_type']
	);
	check(
		"{$name}: transition reason matches expected exactly",
		$outcome['expected_transition']['reason'] === $transition['reason']
	);

	$domain = isset( $outcome['context']['domain'] ) ? $outcome['context']['domain'] : null;
	$routing = $router->route( $transition['transition_type'], $domain );
	check(
		"{$name}: routing matches expected exactly",
		deep_matches( $outcome['expected_routing'], $routing )
	);

	foreach ( $outcome['expected_alert_scenarios'] as $scenario_key => $expected_alert ) {
		$scenario_input = $outcome['alert_scenario_inputs'][ $scenario_key ];
		$actual = $alert_policy->evaluate(
			$transition['transition_type'],
			$scenario_input['last_alert_at_for_class'],
			$scenario_input['now']
		);
		check(
			"{$name}: alert scenario '{$scenario_key}' matches expected exactly",
			deep_matches( $expected_alert, $actual )
		);
	}

	$seen_event_types[ $decoded['event_type'] ] = true;
}

foreach ( array( 'PRICE_RANGE_BREAK', 'VOLATILITY_SHOCK', 'OPEN_INTEREST_SHOCK', 'FUNDING_EXTREME', 'LIQUIDATION_CASCADE', 'DATA_DEGRADED' ) as $event_type ) {
	check( "A fixture exists for event_type '{$event_type}'", isset( $seen_event_types[ $event_type ] ) );
}

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

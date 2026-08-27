<?php
/**
 * Standalone executable test for PR #31's readiness/freshness logic.
 *
 * Not a WordPress PHPUnit suite (no WP test scaffold is available in this
 * environment) — it loads the real plugin classes against a minimal
 * function/DB stub (wp-stubs.php) and runs the exact test cases 1-9 from
 * the task. Case 10 (editor content preserved after a reverted publish)
 * needs a real save_post/wp_update_post transition against a real DB and
 * is explicitly left to Codex's staging verification — see PR body.
 *
 * Run: php tests/test-bitmomo-pro-brief-readiness.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-pro-briefs.php';
require __DIR__ . '/../includes/class-bitmomo-pro-brief-readiness.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

function now_minus_hours( $h ) {
	return date( 'Y-m-d H:i:s', $GLOBALS['__wp_stub_now'] - (int) ( $h * 3600 ) );
}

$readiness = Bitmomo_Pro_Brief_Readiness::instance();

function complete_fields( $overrides = array() ) {
	return array_merge(
		array(
			'market_state'           => 'bullish',
			'btc_reference_price'    => 78000,
			'confidence'             => 62,
			'confidence_explanation' => 'ETF inflows tetap positif.',
			'expected_range_low'     => 76000,
			'expected_range_high'    => 80000,
			'base_scenario'          => 'Konsolidasi di kisaran range.',
			'bull_scenario'          => 'Breakout di atas resistance.',
			'bear_scenario'          => 'Breakdown di bawah support.',
			'invalidation'           => 'Break di bawah $75,000 (4H close).',
			'what_changed'           => 'Funding rate turun ke netral.',
			'data_timestamp'         => now_minus_hours( 0 ),
			'data_freshness_status'  => 'fresh',
		),
		$overrides
	);
}

// Case 1: complete fresh brief -> eligible
$eval = $readiness->evaluate( complete_fields() );
check( 'Case 1: complete fresh brief is ready', true === $eval['ready'] );

// Case 2: missing Base Scenario -> blocked
$eval = $readiness->evaluate( complete_fields( array( 'base_scenario' => '' ) ) );
check( 'Case 2: missing Base Scenario blocks readiness', false === $eval['ready'] );
check( 'Case 2: issues mention Base Scenario', (bool) array_filter( $eval['issues'], fn( $i ) => str_contains( $i, 'Base Scenario' ) ) );

// Case 3: range high < low -> blocked
$eval = $readiness->evaluate( complete_fields( array( 'expected_range_low' => 80000, 'expected_range_high' => 76000 ) ) );
check( 'Case 3: expected_range_high < expected_range_low blocks readiness', false === $eval['ready'] );

// Case 4: invalid timestamp -> blocked
$eval = $readiness->evaluate( complete_fields( array( 'data_timestamp' => 'not-a-real-date' ) ) );
check( 'Case 4: unparseable data_timestamp blocks readiness', false === $eval['ready'] );

// Case 5: 2-hour-old brief -> fresh
$tier = $readiness->compute_freshness_tier( now_minus_hours( 2 ), 'fresh' );
check( 'Case 5: 2h-old brief is fresh tier', Bitmomo_Pro_Brief_Readiness::TIER_FRESH === $tier );

// Case 6: 8-hour-old brief -> delayed but renderable (readiness still passes)
$fields = complete_fields( array( 'data_timestamp' => now_minus_hours( 8 ) ) );
$eval = $readiness->evaluate( $fields );
$tier = $readiness->compute_freshness_tier( $fields['data_timestamp'], $fields['data_freshness_status'] );
check( 'Case 6: 8h-old otherwise-complete brief is still ready', true === $eval['ready'] );
check( 'Case 6: 8h-old brief tier is delayed', Bitmomo_Pro_Brief_Readiness::TIER_DELAYED === $tier );

// Case 7: >24h brief -> not rendered as current (tier unavailable)
$tier = $readiness->compute_freshness_tier( now_minus_hours( 30 ), 'fresh' );
check( 'Case 7: 30h-old brief tier is unavailable', Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier );

// Case 8: explicit unavailable freshness -> not rendered, even if recent
$tier = $readiness->compute_freshness_tier( now_minus_hours( 1 ), 'unavailable' );
check( 'Case 8: explicit data_freshness_status=unavailable overrides recent timestamp', Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier );

// Case 9: newer invalid brief + older valid-but-fresh brief -> safe
// unavailable state, NOT a silent fallback to the older post.
stub_insert_post( 1, 'publish', 'Older, valid, fresh brief' );
foreach ( complete_fields( array( 'data_timestamp' => now_minus_hours( 1 ) ) ) as $k => $v ) {
	update_post_meta( 1, '_bitmomo_pro_' . $k, $v );
}
stub_insert_post( 2, 'publish', 'Newer, invalid brief (missing Bull Scenario)' );
foreach ( complete_fields( array( 'bull_scenario' => '', 'data_timestamp' => now_minus_hours( 0 ) ) ) as $k => $v ) {
	update_post_meta( 2, '_bitmomo_pro_' . $k, $v );
}
$result = Bitmomo_Pro_Briefs::get_current_brief_for_display();
check( 'Case 9: newer invalid brief yields tier=unavailable', Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $result['tier'] );
check( 'Case 9: does NOT silently fall back to the older valid brief', null === $result['brief'] );

// Report
$pass_count = 0;
foreach ( $results as $r ) {
	printf( "[%s] %s\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'] );
	if ( $r['pass'] ) {
		$pass_count++;
	}
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );

echo "\nNOTE: Case 10 (editor content preserved after a blocked publish attempt)\n";
echo "requires a real WordPress save_post/wp_update_post transition against a\n";
echo "real database and is not exercised by this stub — verify on staging.\n";

exit( $pass_count === $total ? 0 : 1 );

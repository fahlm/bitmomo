<?php
/**
 * Standalone executable test for WATCHTOWER PR 3's selective analyst
 * router (Bitmomo_Watchtower_Analyst_Router) — a routing table lookup
 * only. No agent is invoked, no LLM is called; these tests only assert
 * on the returned track name/reason.
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-analyst-router.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-state-transition-engine.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-analyst-router.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

$router = new Bitmomo_Watchtower_Analyst_Router();

// ---------------------------------------------------------------------
// Case 1: DATA_DEGRADED always routes to the data quality track,
// regardless of domain (even one that would otherwise map elsewhere).
// ---------------------------------------------------------------------
$result = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED, Bitmomo_Watchtower_Taxonomy::DOMAIN_MACRO );
check( 'Case 1: DATA_DEGRADED routes to the data quality reviewer track regardless of domain', Bitmomo_Watchtower_Analyst_Router::TRACK_DATA_QUALITY === $result['track'] );

// ---------------------------------------------------------------------
// Case 2: NO_CHANGE never routes to any track (null).
// ---------------------------------------------------------------------
$result = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE, Bitmomo_Watchtower_Taxonomy::DOMAIN_MARKET );
check( 'Case 2: NO_CHANGE routes to no track (null)', null === $result['track'] );

// ---------------------------------------------------------------------
// Case 3: every domain maps to its documented track for a non-special
// transition type (THESIS_CHANGE used as the representative case).
// ---------------------------------------------------------------------
$expected_map = array(
	Bitmomo_Watchtower_Taxonomy::DOMAIN_MARKET       => Bitmomo_Watchtower_Analyst_Router::TRACK_MARKET_STRUCTURE,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES  => Bitmomo_Watchtower_Analyst_Router::TRACK_DERIVATIVES,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_MACRO        => Bitmomo_Watchtower_Analyst_Router::TRACK_MACRO,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_GEOPOLITICS  => Bitmomo_Watchtower_Analyst_Router::TRACK_GEOPOLITICAL,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_REGULATION   => Bitmomo_Watchtower_Analyst_Router::TRACK_REGULATORY,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_NEWS         => Bitmomo_Watchtower_Analyst_Router::TRACK_GENERAL,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_SENTIMENT    => Bitmomo_Watchtower_Analyst_Router::TRACK_SENTIMENT,
	Bitmomo_Watchtower_Taxonomy::DOMAIN_DATA_QUALITY => Bitmomo_Watchtower_Analyst_Router::TRACK_DATA_QUALITY,
);
$all_correct = true;
foreach ( $expected_map as $domain => $expected_track ) {
	$result = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE, $domain );
	if ( $result['track'] !== $expected_track ) {
		$all_correct = false;
	}
}
check( 'Case 3: every one of the 8 domains routes to its documented track', $all_correct );

// ---------------------------------------------------------------------
// Case 4: an unrecognized/null domain falls back to the general track.
// ---------------------------------------------------------------------
$result = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_CONFIDENCE_CHANGE, null );
check( 'Case 4: a null domain falls back to the general thesis reviewer track', Bitmomo_Watchtower_Analyst_Router::TRACK_GENERAL === $result['track'] );

$result = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_CONFIDENCE_CHANGE, 'not_a_real_domain' );
check( 'Case 4: an unrecognized domain string falls back to the general thesis reviewer track', Bitmomo_Watchtower_Analyst_Router::TRACK_GENERAL === $result['track'] );

// ---------------------------------------------------------------------
// Case 5: every documented track constant is a member of TRACKS.
// ---------------------------------------------------------------------
check( 'Case 5: TRACKS contains exactly 8 entries', 8 === count( Bitmomo_Watchtower_Analyst_Router::TRACKS ) );

// ---------------------------------------------------------------------
// Case 6: idempotency.
// ---------------------------------------------------------------------
$run1 = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES );
$run2 = $router->route( Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE, Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES );
check( 'Case 6: repeated routing is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

echo "\nNOTE: this is a routing table lookup ONLY — no analyst agent exists\n";
echo "behind any of these tracks yet, and no LLM is called anywhere in this\n";
echo "plugin.\n";

exit( $pass_count === $total ? 0 : 1 );

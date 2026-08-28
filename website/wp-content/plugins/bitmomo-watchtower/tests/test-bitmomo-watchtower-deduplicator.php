<?php
/**
 * Standalone executable test for WATCHTOWER PR 2's deduplication/
 * clustering engine (Bitmomo_Watchtower_Deduplicator).
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-deduplicator.php
 *
 * Every fixture's expected clustering was traced by hand against the
 * documented rule (same event_type + within CLUSTER_WINDOW_MINUTES of
 * the cluster's current last_seen, a ROLLING window) before running,
 * specifically to catch a mismatch between intent and implementation
 * rather than just echoing whatever the code happens to do.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-config.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-deduplicator.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function candidate( $event_type, $occurred_at, $score, $band, $source_id ) {
	return array(
		'event_type'  => $event_type,
		'domain'      => Bitmomo_Watchtower_Taxonomy::domain_for_event_type( $event_type ),
		'occurred_at' => $occurred_at,
		'score'       => $score,
		'band'        => $band,
		'source_id'   => $source_id,
		'headline'    => $source_id . ' headline',
	);
}

$dedup = new Bitmomo_Watchtower_Deduplicator();

// ---------------------------------------------------------------------
// Case 1: the core scenario — two same-type candidates 10 minutes apart
// (within the default 30-minute window) merge into one cluster; a
// different-type candidate at an overlapping time never merges; a
// same-type candidate 40 minutes after the cluster's last_seen opens a
// NEW cluster (rolling window exceeded). Uses
// Bitmomo_Watchtower_Config::CLUSTER_WINDOW_MINUTES (30) as-is.
// ---------------------------------------------------------------------
$candidates = array(
	candidate( 'LIQUIDATION_CASCADE', '2026-08-28 10:00:00', 70, 'material_candidate', 'src-A' ), // A
	candidate( 'VOLATILITY_SHOCK', '2026-08-28 10:05:00', 65, 'material_candidate', 'src-D' ),      // D (different type)
	candidate( 'LIQUIDATION_CASCADE', '2026-08-28 10:10:00', 85, 'critical_candidate', 'src-B' ),    // B (10 min after A -> merges)
	candidate( 'LIQUIDATION_CASCADE', '2026-08-28 10:50:00', 55, 'interesting', 'src-C' ),           // C (40 min after B's last_seen -> new cluster)
);
$clusters = $dedup->cluster( $candidates );

check( 'Case 1: no events are dropped or invented — member counts sum to the input count (4)', 4 === array_sum( array_column( $clusters, 'member_count' ) ) );
check( 'Case 1: exactly 3 clusters formed', 3 === count( $clusters ) );
check( 'Case 1: clusters are ordered by first_seen ascending', '2026-08-28 10:00:00' === $clusters[0]['first_seen'] && '2026-08-28 10:05:00' === $clusters[1]['first_seen'] && '2026-08-28 10:50:00' === $clusters[2]['first_seen'] );
check( 'Case 1: cluster 0 (A+B) has 2 members', 2 === $clusters[0]['member_count'] );
check( 'Case 1: cluster 0\'s representative is B (the higher-scoring member, 85 > 70)', 'src-B' === $clusters[0]['representative']['source_id'] );
check( 'Case 1: cluster 0\'s highest_score is 85', 85 === $clusters[0]['highest_score'] );
check( 'Case 1: cluster 0\'s highest_band follows the representative (critical_candidate)', 'critical_candidate' === $clusters[0]['highest_band'] );
check( 'Case 1: cluster 0\'s last_seen advances to B\'s timestamp', '2026-08-28 10:10:00' === $clusters[0]['last_seen'] );
check( 'Case 1: cluster 1 (D) is a separate cluster despite overlapping time, because event_type differs', 'VOLATILITY_SHOCK' === $clusters[1]['event_type'] && 1 === $clusters[1]['member_count'] );
check( 'Case 1: cluster 2 (C) is a separate cluster because it exceeds the rolling window from cluster 0\'s last_seen', 1 === $clusters[2]['member_count'] );

// ---------------------------------------------------------------------
// Case 2: exact window boundary — a candidate exactly
// CLUSTER_WINDOW_MINUTES (30 min = 1800s) after the cluster's last_seen
// DOES merge ("<=", not "<"); one second past that does NOT.
// ---------------------------------------------------------------------
$boundary_candidates = array(
	candidate( 'FUNDING_EXTREME', '2026-08-28 12:00:00', 50, 'interesting', 'src-E' ),  // E
	candidate( 'FUNDING_EXTREME', '2026-08-28 12:30:00', 50, 'interesting', 'src-F' ),  // F: exactly 1800s after E -> merges
	candidate( 'FUNDING_EXTREME', '2026-08-28 13:00:01', 50, 'interesting', 'src-G' ),  // G: 1801s after F's last_seen -> new cluster
);
$clusters = $dedup->cluster( $boundary_candidates );
check( 'Case 2: exact 30-minute boundary merges (inclusive)', 2 === count( $clusters ) );
check( 'Case 2: the first cluster contains E and F (2 members)', 2 === $clusters[0]['member_count'] );
check( 'Case 2: one second past the boundary opens a new cluster (G alone)', 1 === $clusters[1]['member_count'] );

// ---------------------------------------------------------------------
// Case 3: a custom, tighter window overrides the config default —
// candidates 10 minutes apart do NOT merge under a 5-minute window.
// ---------------------------------------------------------------------
$candidates = array(
	candidate( 'VOLUME_SHOCK', '2026-08-28 09:00:00', 60, 'material_candidate', 'src-H' ),
	candidate( 'VOLUME_SHOCK', '2026-08-28 09:10:00', 60, 'material_candidate', 'src-I' ),
);
$clusters = $dedup->cluster( $candidates, 5 );
check( 'Case 3: a tighter custom window (5 min) prevents a merge that the 30-min default would allow', 2 === count( $clusters ) );

// ---------------------------------------------------------------------
// Case 4: empty input -> empty output, no error.
// ---------------------------------------------------------------------
check( 'Case 4: empty candidate list returns an empty cluster list', array() === $dedup->cluster( array() ) );

// ---------------------------------------------------------------------
// Case 5: a single candidate forms a single cluster of size 1, and is
// its own representative.
// ---------------------------------------------------------------------
$single    = array( candidate( 'SENTIMENT_EXTREME', '2026-08-28 08:00:00', 90, 'critical_candidate', 'src-J' ) );
$clusters  = $dedup->cluster( $single );
check( 'Case 5: a single candidate forms exactly one cluster', 1 === count( $clusters ) );
check( 'Case 5: that cluster has member_count 1 and is its own representative', 1 === $clusters[0]['member_count'] && 'src-J' === $clusters[0]['representative']['source_id'] );

// ---------------------------------------------------------------------
// Case 6: input order does not matter — the SAME candidates, submitted
// in a scrambled (non-chronological) array order, produce an identical
// clustering result, since the deduplicator sorts internally.
// ---------------------------------------------------------------------
$ordered = array(
	candidate( 'OPEN_INTEREST_SHOCK', '2026-08-28 14:00:00', 40, 'interesting', 'src-K' ),
	candidate( 'OPEN_INTEREST_SHOCK', '2026-08-28 14:05:00', 60, 'material_candidate', 'src-L' ),
);
$scrambled = array( $ordered[1], $ordered[0] ); // reversed
check( 'Case 6: clustering is independent of input array order', serialize( $dedup->cluster( $ordered ) ) === serialize( $dedup->cluster( $scrambled ) ) );

// ---------------------------------------------------------------------
// Case 7: idempotency — running the same input twice produces identical
// output.
// ---------------------------------------------------------------------
$run1 = $dedup->cluster( $ordered );
$run2 = $dedup->cluster( $ordered );
check( 'Case 7: repeated clustering is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

echo "\nNOTE: this class only clusters already-scored candidates; it does\n";
echo "not decide whether a cluster is alert-worthy (no cooldown/alert\n";
echo "policy exists yet — see WATCHTOWER PR 3).\n";

exit( $pass_count === $total ? 0 : 1 );

<?php
/**
 * Standalone executable test for REGIME PR 3's frontend history
 * projection (Bitmomo_Regime_History).
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-regime-history.php
 *
 * Bitmomo_Regime_Shortcodes and Bitmomo_Regime_Admin_Diagnostics (the
 * WordPress-touching consumers of this projection) are NOT covered here
 * — they require a real WordPress environment and are verified on
 * staging, same convention as Bitmomo_Regime_State_Store.
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-history.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

/**
 * Builds a fixture record shaped like Bitmomo_Regime_State_Store::hydrate()'s
 * output, including diagnostic-only fields (evidence, conflicts,
 * source_record_id, input_hash) so the whitelist test can prove they
 * never leak into the frontend projection.
 */
function fixture_record( $as_of, $regime, $bias, $confidence, $edition = '' ) {
	$record = array(
		'id'                     => 0,
		'date'                   => $as_of,
		'as_of'                  => $as_of,
		'source_record_id'       => 'src-' . $as_of,
		'regime'                 => $regime,
		'candidate_regime'       => $regime,
		'directional_bias'       => $bias,
		'regime_confidence'      => $confidence,
		'directional_confidence' => 50,
		'evidence'               => array( 'evidence for ' . $as_of ),
		'conflicts'              => array(),
		'input_summary'          => array( 'return_1d' => 1.0 ),
		'input_hash'             => md5( $as_of ),
		'classifier_version'     => 'regime-v1',
		'transition_strength'    => 'held',
		'transition_reason'      => 'test fixture',
		'pending_regime'         => null,
		'pending_streak'         => 0,
	);
	if ( '' !== $edition ) $record['edition'] = $edition;
	return $record;
}

// Newest-first, matching Bitmomo_Regime_State_Store::get_recent()'s contract.
$records_5 = array(
	fixture_record( '2026-08-28', 'capitulation', 'bearish', 90 ),
	fixture_record( '2026-08-27', 'distribution', 'neutral', 55 ),
	fixture_record( '2026-08-26', 'expansion', 'bullish', 70 ),
	fixture_record( '2026-08-25', 'accumulation', 'neutral', 60 ),
	fixture_record( '2026-08-24', 'transition', 'neutral', 20 ),
);

// ---------------------------------------------------------------------
// Case 1: requesting the full target window (30) with only 5 available
// -> no fabrication: available_days=5, exactly 5 days returned, in
// chronological ascending order (oldest first).
// ---------------------------------------------------------------------
$result = Bitmomo_Regime_History::for_frontend( $records_5, 30 );
check( 'Case 1: requested_days echoes the valid requested value (30)', 30 === $result['requested_days'] );
check( 'Case 1: target_days is always 30', 30 === $result['target_days'] );
check( 'Case 1: available_days reports the true count (5), not 30', 5 === $result['available_days'] );
check( 'Case 1: no fabricated rows — exactly 5 days returned', 5 === count( $result['days'] ) );
check( 'Case 1: chronological ascending — first day is the oldest (08-24)', '2026-08-24' === $result['days'][0]['date'] );
check( 'Case 1: chronological ascending — last day is the newest (08-28)', '2026-08-28' === $result['days'][4]['date'] );

// ---------------------------------------------------------------------
// Case 2: requesting 1 day returns only the single most recent day.
// ---------------------------------------------------------------------
$result = Bitmomo_Regime_History::for_frontend( $records_5, 1 );
check( 'Case 2: requesting 1 day returns exactly 1 day', 1 === count( $result['days'] ) );
check( 'Case 2: the 1 day returned is the most recent (08-28, capitulation)', 'capitulation' === $result['days'][0]['regime'] );
check( 'Case 2: available_days is still the true total (5)', 5 === $result['available_days'] );

// ---------------------------------------------------------------------
// Case 3: requesting 7 days with only 5 available returns all 5 (never
// pads to 7) — requested_days still reports 7 since 7 is a valid value.
// ---------------------------------------------------------------------
$result = Bitmomo_Regime_History::for_frontend( $records_5, 7 );
check( 'Case 3: requested_days is 7 (valid, even though fewer exist)', 7 === $result['requested_days'] );
check( 'Case 3: only the 5 that actually exist are returned, not padded to 7', 5 === count( $result['days'] ) );

// ---------------------------------------------------------------------
// Case 4: an invalid day count (3, not in ALLOWED_DAYS) falls back to
// DEFAULT_DAYS (7), not to some ad-hoc reinterpretation of "3".
// ---------------------------------------------------------------------
$result = Bitmomo_Regime_History::for_frontend( $records_5, 3 );
check( 'Case 4: an invalid requested day count falls back to DEFAULT_DAYS (7)', Bitmomo_Regime_History::DEFAULT_DAYS === $result['requested_days'] );
check( 'Case 4: fallback still only returns what truly exists (5)', 5 === count( $result['days'] ) );

// ---------------------------------------------------------------------
// Case 5: empty history — no error, no fabrication, honest zero.
// ---------------------------------------------------------------------
$result = Bitmomo_Regime_History::for_frontend( array(), 30 );
check( 'Case 5: empty history reports available_days = 0', 0 === $result['available_days'] );
check( 'Case 5: empty history returns an empty days array, not an error', array() === $result['days'] );

// ---------------------------------------------------------------------
// Case 6: frontend-safe field whitelist — diagnostic-only fields
// (evidence, conflicts, source_record_id, input_hash, classifier_version,
// transition_strength/reason, pending_regime/streak) must NEVER appear
// in the projected output, even though the fixture records include them.
// ---------------------------------------------------------------------
$result       = Bitmomo_Regime_History::for_frontend( $records_5, 1 );
$day          = $result['days'][0];
$allowed_keys = array( 'date', 'regime', 'regime_label_id', 'regime_label_en', 'directional_bias', 'regime_confidence' );
sort( $allowed_keys );
$actual_keys = array_keys( $day );
sort( $actual_keys );
check( 'Case 6: projected day contains exactly the frontend-safe field whitelist, nothing more', $allowed_keys === $actual_keys );
check( 'Case 6: evidence does not leak into the projection', ! array_key_exists( 'evidence', $day ) );
check( 'Case 6: source_record_id does not leak into the projection', ! array_key_exists( 'source_record_id', $day ) );

// ---------------------------------------------------------------------
// Case 7: both regime labels are correctly attached (Indonesian +
// English), per the taxonomy — proves the projection doesn't just pass
// the raw slug through untranslated.
// ---------------------------------------------------------------------
check( 'Case 7: regime_label_id is the Indonesian label', 'Kapitulasi' === $day['regime_label_id'] );
check( 'Case 7: regime_label_en is the English label', 'Capitulation' === $day['regime_label_en'] );

// ---------------------------------------------------------------------
// Case 8: idempotency — identical input produces identical output.
// ---------------------------------------------------------------------
$run1 = Bitmomo_Regime_History::for_frontend( $records_5, 14 );
$run2 = Bitmomo_Regime_History::for_frontend( $records_5, 14 );
check( 'Case 8: repeated evaluation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

// Newest-first same-date records: an older US Session must never replace
// the already-selected newer US Session; Morning remains lower priority.
$same_date = array(
	fixture_record( '2026-08-29 19:10:00', 'expansion', 'bullish', 81, 'us_session' ),
	fixture_record( '2026-08-29 19:05:00', 'distribution', 'neutral', 62, 'us_session' ),
	fixture_record( '2026-08-29 07:10:00', 'accumulation', 'neutral', 55, 'morning' ),
);
$same_date_result = Bitmomo_Regime_History::for_frontend( $same_date, 30 );
check( 'Case 9: same date collapses to one official bar', 1 === count( $same_date_result['days'] ) );
check( 'Case 9: newest US Session wins over older US Session and Morning', 'expansion' === $same_date_result['days'][0]['regime'] );

$morning_then_us = array(
	fixture_record( '2026-08-30 07:10:00', 'accumulation', 'neutral', 70, 'morning' ),
	fixture_record( '2026-08-30 00:10:00', 'capitulation', 'bearish', 78, 'us_session' ),
);
$morning_then_us_result = Bitmomo_Regime_History::for_frontend( $morning_then_us, 30 );
check( 'Case 10: older US Session remains preferred over newer Morning', 'capitulation' === $morning_then_us_result['days'][0]['regime'] );

// ---------------------------------------------------------------------
// Case 9: a full 30-day window, requesting 14 — proves the slicing math
// picks the most recent 14 of 30, not the oldest 14 or a misaligned
// window. Fixture built programmatically: 30 consecutive days, newest
// first, oldest = 2026-07-30, newest = 2026-08-28.
// ---------------------------------------------------------------------
$records_30 = array();
for ( $i = 0; $i < 30; $i++ ) {
	// $i = 0 is the newest (2026-08-28), counting backwards.
	$timestamp = strtotime( '2026-08-28' ) - ( $i * 86400 );
	$date      = gmdate( 'Y-m-d', $timestamp );
	$records_30[] = fixture_record( $date, 'accumulation', 'neutral', 50 );
}
$result = Bitmomo_Regime_History::for_frontend( $records_30, 14 );
check( 'Case 9: available_days is the full 30', 30 === $result['available_days'] );
check( 'Case 9: exactly 14 days returned', 14 === count( $result['days'] ) );
check( 'Case 9: the 14-day slice starts at the correct oldest day (2026-08-15)', '2026-08-15' === $result['days'][0]['date'] );
check( 'Case 9: the 14-day slice ends at the newest day (2026-08-28)', '2026-08-28' === $result['days'][13]['date'] );

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

echo "\nNOTE: Bitmomo_Regime_Shortcodes and Bitmomo_Regime_Admin_Diagnostics\n";
echo "(the WordPress-rendering consumers of this projection) require a real\n";
echo "WordPress environment and are not exercised by this stub — verify on\n";
echo "staging.\n";

exit( $pass_count === $total ? 0 : 1 );

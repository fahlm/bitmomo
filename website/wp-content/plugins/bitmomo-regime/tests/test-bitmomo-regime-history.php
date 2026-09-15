<?php
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-history.php';

$results = array();
function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

function fixture_record( $as_of, $regime, $bias, $confidence, $edition = '', $source_id = '' ) {
	$record = array(
		'id' => 0,
		'date' => $as_of,
		'as_of' => $as_of,
		'source_record_id' => '' !== $source_id ? $source_id : 'src-' . $as_of,
		'regime' => $regime,
		'candidate_regime' => $regime,
		'directional_bias' => $bias,
		'regime_confidence' => $confidence,
		'directional_confidence' => 50,
		'evidence' => array( 'evidence for ' . $as_of ),
		'conflicts' => array(),
		'input_summary' => array( 'return_1d' => 1.0 ),
		'input_hash' => md5( $as_of . $source_id ),
		'classifier_version' => 'regime-v1',
		'transition_strength' => 'held',
		'transition_reason' => 'test fixture',
		'pending_regime' => null,
		'pending_streak' => 0,
		'provenance' => 'recorded_live',
	);
	if ( '' !== $edition ) $record['edition'] = $edition;
	return $record;
}

$records_5 = array(
	fixture_record( '2026-08-28', 'capitulation', 'bearish', 90 ),
	fixture_record( '2026-08-27', 'distribution', 'neutral', 55 ),
	fixture_record( '2026-08-26', 'expansion', 'bullish', 70 ),
	fixture_record( '2026-08-25', 'accumulation', 'neutral', 60 ),
	fixture_record( '2026-08-24', 'transition', 'neutral', 20 ),
);

$result = Bitmomo_Regime_History::for_frontend( $records_5, 30 );
check( 'valid 30-day request is preserved', 30 === $result['requested_days'] );
check( 'target window remains 30 days', 30 === $result['target_days'] );
check( 'available day count is factual', 5 === $result['available_days'] );
check( 'missing history is never fabricated', 5 === count( $result['days'] ) );
check( 'history is chronological oldest to newest', '2026-08-24' === $result['days'][0]['date'] && '2026-08-28' === $result['days'][4]['date'] );

$result = Bitmomo_Regime_History::for_frontend( $records_5, 1 );
check( 'one-day request returns only newest official day', 1 === count( $result['days'] ) && 'capitulation' === $result['days'][0]['regime'] );
check( 'available count remains total even for one-day request', 5 === $result['available_days'] );

$result = Bitmomo_Regime_History::for_frontend( $records_5, 7 );
check( 'seven-day request does not pad five real observations', 7 === $result['requested_days'] && 5 === count( $result['days'] ) );
$result = Bitmomo_Regime_History::for_frontend( $records_5, 3 );
check( 'unsupported day request falls back to seven', Bitmomo_Regime_History::DEFAULT_DAYS === $result['requested_days'] && 5 === count( $result['days'] ) );
$result = Bitmomo_Regime_History::for_frontend( array(), 30 );
check( 'empty history fails closed', 0 === $result['available_days'] && array() === $result['days'] );

$day = Bitmomo_Regime_History::for_frontend( $records_5, 1 )['days'][0];
$allowed_keys = array( 'date', 'regime', 'regime_label_id', 'regime_label_en', 'directional_bias', 'regime_confidence' );
$actual_keys = array_keys( $day );
sort( $allowed_keys ); sort( $actual_keys );
check( 'frontend projection is an explicit safe allowlist', $allowed_keys === $actual_keys );
check( 'diagnostic evidence never leaks', ! array_key_exists( 'evidence', $day ) );
check( 'source identity never leaks', ! array_key_exists( 'source_record_id', $day ) );
check( 'Indonesian regime label is projected', 'Kapitulasi' === $day['regime_label_id'] );
check( 'English regime label is projected', 'Capitulation' === $day['regime_label_en'] );
check( 'history projection is deterministic', serialize( Bitmomo_Regime_History::for_frontend( $records_5, 14 ) ) === serialize( Bitmomo_Regime_History::for_frontend( $records_5, 14 ) ) );

// Canonical edition IDs contain the America/New_York session anchor. Post-close
// can land on the next UTC date but MUST remain part of the same US market day.
$morning_id = 'bitmomo-ai:2026-09-12T081000-0400:us_pre_open:aaaa111111';
$post_close_id = 'bitmomo-ai:2026-09-12T201000-0400:us_post_close:bbbb222222';
check( 'canonical morning edition id resolves to its US market date', '2026-09-12' === Bitmomo_Regime_History::market_date( fixture_record( '2026-09-12 12:59:59', 'accumulation', 'neutral', 55, 'morning', $morning_id ) ) );
check( 'canonical post-close id stays on prior US date despite next-day UTC storage', '2026-09-12' === Bitmomo_Regime_History::market_date( fixture_record( '2026-09-13 00:59:59', 'expansion', 'bullish', 80, 'us_session', $post_close_id ) ) );

$cross_midnight = array(
	fixture_record( '2026-09-13 00:59:59', 'expansion', 'bullish', 80, 'us_session', $post_close_id ),
	fixture_record( '2026-09-12 12:59:59', 'accumulation', 'neutral', 55, 'morning', $morning_id ),
);
$cross_result = Bitmomo_Regime_History::for_frontend( $cross_midnight, 30 );
check( 'morning and post-close across UTC midnight collapse to one market-day bar', 1 === count( $cross_result['days'] ) && 1 === $cross_result['available_days'] );
check( 'post-close is the official bar for that market day', 'expansion' === $cross_result['days'][0]['regime'] && '2026-09-12' === $cross_result['days'][0]['date'] );

// No-hyphen canonical IDs remain supported defensively.
$compact_id = 'bitmomo-ai:20260912T201000-0400:us_post_close:cccc333333';
check( 'compact canonical edition id is also parsed safely', '2026-09-12' === Bitmomo_Regime_History::market_date( fixture_record( '2026-09-13 00:59:59', 'expansion', 'bullish', 80, 'us_session', $compact_id ) ) );

$legacy_id = 'bitmomo-ai:regime:2026-09-10:us_session';
check( 'legacy Regime source ids retain their embedded market date', '2026-09-10' === Bitmomo_Regime_History::market_date( fixture_record( '2026-09-11 00:10:00', 'distribution', 'bearish', 70, 'us_session', $legacy_id ) ) );

// The current Regime scheduler writes gmdate() without an offset. Its raw UTC
// datetime therefore needs NY conversion when no stronger canonical identity exists.
check( 'raw UTC post-close datetime falls back to the correct NY market date', '2026-09-12' === Bitmomo_Regime_History::market_date( fixture_record( '2026-09-13 00:10:00', 'distribution', 'bearish', 70, 'us_session' ) ) );
check( 'explicit date-only historical fixtures remain stable', '2026-08-28' === Bitmomo_Regime_History::market_date( fixture_record( '2026-08-28', 'capitulation', 'bearish', 90 ) ) );

$same_market_day = array(
	fixture_record( '2026-09-13 00:59:59', 'expansion', 'bullish', 81, 'us_session', 'bitmomo-ai:2026-09-12T201000-0400:us_post_close:newest' ),
	fixture_record( '2026-09-13 00:50:00', 'distribution', 'neutral', 62, 'us_session', 'bitmomo-ai:2026-09-12T200500-0400:us_post_close:older' ),
	fixture_record( '2026-09-12 12:59:59', 'accumulation', 'neutral', 55, 'morning', 'bitmomo-ai:2026-09-12T081000-0400:us_pre_open:morning' ),
);
$same_result = Bitmomo_Regime_History::for_frontend( $same_market_day, 30 );
check( 'multiple same-market-day editions collapse to one official record', 1 === count( $same_result['days'] ) );
check( 'newest-first official post-close record is not overwritten by older editions', 'expansion' === $same_result['days'][0]['regime'] );

$records_30 = array();
for ( $i = 0; $i < 30; $i++ ) {
	$timestamp = strtotime( '2026-08-28' ) - ( $i * 86400 );
	$records_30[] = fixture_record( gmdate( 'Y-m-d', $timestamp ), 'accumulation', 'neutral', 50 );
}
$result = Bitmomo_Regime_History::for_frontend( $records_30, 14 );
check( 'full window reports all 30 available days', 30 === $result['available_days'] );
check( '14-day request returns exactly 14 observations', 14 === count( $result['days'] ) );
check( '14-day slice selects the most recent 14', '2026-08-15' === $result['days'][0]['date'] && '2026-08-28' === $result['days'][13]['date'] );

$pass_count = 0;
foreach ( $results as $r ) {
	printf( "[%s] %s\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'] );
	if ( $r['pass'] ) $pass_count++;
}
printf( "\n%d/%d passed.\n", $pass_count, count( $results ) );
exit( $pass_count === count( $results ) ? 0 : 1 );

<?php
/**
 * Standalone executable test for WATCHTOWER PR 1's canonical event
 * candidate schema (Bitmomo_Watchtower_Event_Input) and the taxonomy it
 * validates against.
 *
 * Pure PHP, no WordPress dependency — run directly:
 *   php tests/test-bitmomo-watchtower-event-input.php
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-watchtower-event-input.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array(
		'label' => $label,
		'pass'  => (bool) $condition,
	);
}

function valid_fixture( $overrides = array() ) {
	$base = array(
		'event_type'                => Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_LIQUIDATION_CASCADE,
		'occurred_at'                => '2026-08-28 12:00:00',
		'magnitude_score'           => 80,
		'novelty_score'             => 60,
		'btc_relevance_score'       => 90,
		'cross_confirmation_score'  => 50,
		'thesis_impact_score'       => 40,
		'source_quality_score'      => 70,
		'headline'                  => 'Large long liquidation cascade on major exchanges',
		'source_id'                 => 'exchange-feed-1',
		'source_name'               => 'Exchange Aggregator',
	);
	return array_merge( $base, $overrides );
}

// ---------------------------------------------------------------------
// Case 1: taxonomy sanity — the registry contains exactly the 15
// spec-named types, each mapped to exactly one of the 8 domains.
// ---------------------------------------------------------------------
check( 'Case 1: taxonomy registers exactly 15 event types', 15 === count( Bitmomo_Watchtower_Taxonomy::EVENT_TYPES ) );
check( 'Case 1: taxonomy registers exactly 8 domains', 8 === count( Bitmomo_Watchtower_Taxonomy::DOMAINS ) );
check( 'Case 1: every event type has an owning domain in the map', count( Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_DOMAIN_MAP ) === count( Bitmomo_Watchtower_Taxonomy::EVENT_TYPES ) );
$all_domains_valid = true;
foreach ( Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_DOMAIN_MAP as $type => $domain ) {
	if ( ! Bitmomo_Watchtower_Taxonomy::is_valid_domain( $domain ) ) {
		$all_domains_valid = false;
	}
}
check( 'Case 1: every mapped domain is itself a registered domain', $all_domains_valid );

// ---------------------------------------------------------------------
// Case 2: a fully valid, fully populated event candidate normalizes
// correctly — numeric fields cast to float, domain auto-derived.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Event_Input::validate( valid_fixture() );
check( 'Case 2: fully-populated valid input validates', true === $result['valid'] );
check( 'Case 2: no errors on valid input', array() === $result['errors'] );
check( 'Case 2: domain is auto-derived from the registry (LIQUIDATION_CASCADE -> derivatives)', Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES === $result['input']['domain'] );
check( 'Case 2: numeric score fields normalize to float', is_float( $result['input']['magnitude_score'] ) );
check( 'Case 2: magnitude_score value preserved after normalization', 80.0 === $result['input']['magnitude_score'] );

// ---------------------------------------------------------------------
// Case 3: missing a required field fails closed — null input, named error.
// ---------------------------------------------------------------------
$fixture = valid_fixture();
unset( $fixture['thesis_impact_score'] );
$result = Bitmomo_Watchtower_Event_Input::validate( $fixture );
check( 'Case 3: missing required field fails validation', false === $result['valid'] );
check( 'Case 3: failed validation returns null input, never a partial record', null === $result['input'] );
check( 'Case 3: the specific missing field is named in the errors', false !== strpos( implode( ' ', $result['errors'] ), 'thesis_impact_score' ) );

// ---------------------------------------------------------------------
// Case 4: an unknown event_type is rejected — the registry is closed,
// not whatever string a caller happens to send.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Event_Input::validate( valid_fixture( array( 'event_type' => 'NOT_A_REAL_EVENT_TYPE' ) ) );
check( 'Case 4: unknown event_type fails validation', false === $result['valid'] );
check( 'Case 4: null input on unknown event_type', null === $result['input'] );

// ---------------------------------------------------------------------
// Case 5: an out-of-range score (150) is rejected.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Event_Input::validate( valid_fixture( array( 'magnitude_score' => 150 ) ) );
check( 'Case 5: out-of-range score (150) fails validation', false === $result['valid'] );
check( 'Case 5: out-of-range error names the offending field', false !== strpos( implode( ' ', $result['errors'] ), 'magnitude_score' ) );

// ---------------------------------------------------------------------
// Case 6: a non-numeric score is rejected.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Event_Input::validate( valid_fixture( array( 'novelty_score' => 'very high' ) ) );
check( 'Case 6: non-numeric score fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 7: a supplied domain that CONTRADICTS the registry mapping is
// rejected — a caller cannot mislabel an event's domain.
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Event_Input::validate( valid_fixture( array( 'domain' => Bitmomo_Watchtower_Taxonomy::DOMAIN_MACRO ) ) ); // LIQUIDATION_CASCADE is actually 'derivatives'
check( 'Case 7: a domain contradicting the registry mapping fails validation', false === $result['valid'] );

// ---------------------------------------------------------------------
// Case 8: a supplied domain that MATCHES the registry mapping is
// accepted (not just tolerated — actively agrees).
// ---------------------------------------------------------------------
$result = Bitmomo_Watchtower_Event_Input::validate( valid_fixture( array( 'domain' => Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES ) ) );
check( 'Case 8: a domain matching the registry mapping validates', true === $result['valid'] );

// ---------------------------------------------------------------------
// Case 9: optional fields default sensibly when omitted — empty string
// for text fields, empty array for raw_metrics — never null, never a
// fabricated value.
// ---------------------------------------------------------------------
$fixture = valid_fixture();
unset( $fixture['headline'], $fixture['source_id'], $fixture['source_name'] );
$result = Bitmomo_Watchtower_Event_Input::validate( $fixture );
check( 'Case 9: omitted optional fields still validate', true === $result['valid'] );
check( 'Case 9: omitted headline defaults to empty string, not null', '' === $result['input']['headline'] );
check( 'Case 9: omitted raw_metrics defaults to an empty array', array() === $result['input']['raw_metrics'] );

// ---------------------------------------------------------------------
// Case 10: idempotency — same input, same normalized output, every time.
// ---------------------------------------------------------------------
$fixture = valid_fixture();
$run1    = Bitmomo_Watchtower_Event_Input::validate( $fixture );
$run2    = Bitmomo_Watchtower_Event_Input::validate( $fixture );
check( 'Case 10: repeated validation is idempotent', serialize( $run1 ) === serialize( $run2 ) );

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

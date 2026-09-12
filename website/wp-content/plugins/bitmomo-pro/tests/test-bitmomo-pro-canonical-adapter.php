<?php
require __DIR__ . '/wp-stubs.php';

class Bitmomo_Pro_Brief_Prefill { const AVAILABILITY_FILTER = 'bitmomo_pro_available_source_payload'; }
class Bitmomo_AI_Intelligence {
	public static $free = array();
	public static $pro = array();
	public static function free_projection() { return self::$free; }
	public static function pro_projection() { return self::$pro; }
}

require __DIR__ . '/../includes/class-bitmomo-pro-canonical-adapter.php';

$checks = array();
function canonical_check( $label, $condition ) {
	global $checks;
	$checks[] = array( $label, (bool) $condition );
}

Bitmomo_AI_Intelligence::$free = array(
	'status' => 'fresh',
	'timestamp' => strtotime( '2026-09-12T20:10:00+00:00' ),
	'edition_id' => 'bitmomo-ai:canonical-edition-123',
	'price' => 65000,
	'bias' => 'bullish',
	'confidence' => 68,
);
Bitmomo_AI_Intelligence::$pro = array(
	'invalidation' => 62000,
	'support_zone' => array( 'low' => 63000, 'high' => 63500 ),
	'resistance_zone' => array( 'low' => 67000, 'high' => 67500 ),
);

$adapter = Bitmomo_Pro_Canonical_Adapter::instance();
$payload = $adapter->available_payload( null );
canonical_check( 'prefill preserves exact canonical edition id', 'bitmomo-ai:canonical-edition-123' === ( $payload['source_record_id'] ?? '' ) );
canonical_check( 'legacy Pro directional field still receives the directional bias enum', 'bullish' === ( $payload['market_state'] ?? '' ) );
canonical_check( 'canonical price confidence and freshness are preserved', 65000.0 === ( $payload['btc_reference_price'] ?? 0 ) && 68.0 === ( $payload['confidence'] ?? 0 ) && 'fresh' === ( $payload['data_freshness_status'] ?? '' ) );
canonical_check( 'support/resistance are never disguised as Expected Range', ! isset( $payload['expected_range_low'], $payload['expected_range_high'] ) );
canonical_check( 'valid canonical invalidation may still prefill editor draft', '62000' === ( $payload['invalidation'] ?? '' ) );

Bitmomo_AI_Intelligence::$free['edition_id'] = '';
canonical_check( 'missing stable canonical lineage fails closed', null === $adapter->available_payload( null ) );

Bitmomo_AI_Intelligence::$free = array( 'status' => 'unavailable' );
canonical_check( 'unavailable intelligence does not create a Pro prefill payload', null === $adapter->available_payload( null ) );

$passed = count( array_filter( $checks, function ( $row ) { return $row[1]; } ) );
foreach ( $checks as $row ) printf( "[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0] );
printf( "\n%d/%d passed.\n", $passed, count( $checks ) );
exit( $passed === count( $checks ) ? 0 : 1 );

<?php
define( 'ABSPATH', __DIR__ );

function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }

final class Bitmomo_AI_Intelligence {
	public static $projection = array();
	public static function free_projection() { return self::$projection; }
}

final class Bitmomo_AI_Opportunity_Store {
	public static $latest = array( 'status' => 'unavailable', 'methodology_version' => 'opportunity-v1' );
	public static function public_latest() { return self::$latest; }
}

require __DIR__ . '/../includes/class-bitmomo-public-intelligence-adapter.php';

$checks = array();
function surface_check( $label, $condition ) { global $checks; $checks[] = array( $label, (bool) $condition ); }

Bitmomo_AI_Intelligence::$projection = array(
	'status' => 'fresh',
	'source' => 'Binance public market data',
	'timestamp_iso' => '2026-09-12T06:20:00+00:00',
);
Bitmomo_AI_Opportunity_Store::$latest = array(
	'status' => 'available',
	'state' => 'HIGH',
	'methodology_version' => 'opportunity-v1',
);
$surface = Bitmomo_Public_Intelligence_Adapter::surface_context();
surface_check( 'full public surface exposes canonical Opportunity', ( $surface['opportunity']['state'] ?? '' ) === 'HIGH' );
surface_check( 'full public surface exposes safe provenance', ( $surface['provenance']['source'] ?? '' ) === 'Binance public market data' && ( $surface['provenance']['as_of'] ?? '' ) === '2026-09-12T06:20:00+00:00' );

Bitmomo_AI_Intelligence::$projection = array( 'status' => 'unavailable', 'private_note' => 'do-not-leak' );
Bitmomo_AI_Opportunity_Store::$latest = array( 'status' => 'unavailable', 'methodology_version' => 'opportunity-v1' );
$unavailable = Bitmomo_Public_Intelligence_Adapter::surface_context();
surface_check( 'snapshot remains strict', null === Bitmomo_Public_Intelligence_Adapter::snapshot() );
surface_check( 'unavailable surface has no invented provenance', null === $unavailable['provenance']['source'] && null === $unavailable['provenance']['as_of'] && 'Asia/Jakarta' === $unavailable['provenance']['timezone'] );

Bitmomo_AI_Opportunity_Store::$latest = array( 'status' => 'available', 'state' => 'LOW', 'methodology_version' => 'opportunity-v1' );
$partial = Bitmomo_Public_Intelligence_Adapter::surface_context();
surface_check( 'Opportunity remains independent of snapshot', ( $partial['opportunity']['state'] ?? '' ) === 'LOW' );

$encoded = json_encode( array( $surface, $unavailable, $partial ) );
foreach ( array( 'private_note', 'source_diagnostics', 'risk', 'axes', 'pro_projection', 'entitlement' ) as $forbidden ) {
	surface_check( "no {$forbidden} leak", false === strpos( $encoded, '"' . $forbidden . '"' ) );
}

$failed = array_filter( $checks, static function ( $row ) { return ! $row[1]; } );
foreach ( $checks as $row ) echo ( $row[1] ? 'PASS' : 'FAIL' ) . ': ' . $row[0] . PHP_EOL;
if ( $failed ) exit( 1 );
echo 'All ' . count( $checks ) . " public-surface checks passed.\n";

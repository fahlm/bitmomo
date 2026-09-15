<?php
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://bitmomo.test' . $path; } }

$GLOBALS['__prov_pass'] = 0;
$GLOBALS['__prov_fail'] = 0;
function prov_check( $label, $condition ) {
	if ( $condition ) { $GLOBALS['__prov_pass']++; echo "[PASS] {$label}\n"; }
	else { $GLOBALS['__prov_fail']++; echo "[FAIL] {$label}\n"; }
}

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot = array();
	public static $surface = array();
	public static function snapshot() { return self::$snapshot; }
	public static function surface_context() { return self::$surface; }
	public static function history() { return array( 'days' => array() ); }
	public static function evaluation_summary() { return array(); }
}

Bitmomo_Public_Intelligence_Adapter::$snapshot = array(
	'status' => 'fresh', 'btc_reference_price' => 65000,
	'opportunity' => array( 'status' => 'available', 'state' => 'high', 'knowledge_time' => '2026-09-12T06:30:00+00:00' ),
	'direction_strength' => 'bullish', 'directional_bias' => 'bullish',
	'confidence' => array( 'value' => 71, 'label' => 'high' ),
	'market_state' => 'accumulation', 'market_state_certainty' => 65,
	'key_drivers' => array( 'Directional consistency' ),
	'freshness' => array( 'state' => 'fresh', 'timestamp_iso' => '2026-09-12T06:20:00+00:00' ),
	'provenance' => array( 'source' => 'Binance public market data', 'as_of' => '2026-09-12T06:20:00+00:00', 'timezone' => 'Asia/Jakarta' ),
	'session' => array( 'label' => 'US POST-CLOSE' ), 'session_intelligence' => array(),
);
Bitmomo_Public_Intelligence_Adapter::$surface = array( 'opportunity' => Bitmomo_Public_Intelligence_Adapter::$snapshot['opportunity'] );

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
$instance = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod( 'render_page' );
$method->setAccessible( true );
$output = $method->invoke( $instance, array() );

prov_check( 'public page renders concise canonical source label', false !== strpos( $output, 'Sumber data: Binance' ) );
prov_check( 'public page renders explicit as-of timestamp in WIB', false !== strpos( $output, '12 Sep 2026 · 13:20 WIB' ) );
prov_check( 'public page preserves machine-readable canonical as-of timestamp', false !== strpos( $output, 'datetime="2026-09-12T06:20:00+00:00"' ) );
prov_check( 'provenance remains attached to the current reading', false !== strpos( $output, 'bm-bi__snapshot' ) && false !== strpos( $output, 'bm-bi__provenance' ) );
prov_check( 'independent Opportunity observation timestamp stays out of the visitor surface', false === strpos( $output, '13:30 WIB' ) && false === strpos( $output, '15m observation' ) );
prov_check( 'public page never exposes source diagnostics', false === strpos( $output, 'source_diagnostics' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot['provenance']['source'] = 'Binance public market data + Bybit derivatives fallback';
$instance = $reflection->newInstanceWithoutConstructor();
$fallback_output = $method->invoke( $instance, array() );
prov_check( 'fallback source is disclosed in compact visitor language', false !== strpos( $fallback_output, 'Sumber data: Binance + Bybit' ) );
prov_check( 'fallback implementation wording stays out of public copy', false === strpos( $fallback_output, 'derivatives fallback' ) );

if ( $GLOBALS['__prov_fail'] > 0 ) exit( 1 );
$total = $GLOBALS['__prov_pass'] + $GLOBALS['__prov_fail'];
echo $GLOBALS['__prov_pass'] . "/" . $total . " provenance checks passed.\n";

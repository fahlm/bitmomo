<?php
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://bitmomo.test' . $path; } }

$GLOBALS['__surface_pass'] = 0;
$GLOBALS['__surface_fail'] = 0;
function btc_surface_check( $label, $condition ) {
	$GLOBALS[ $condition ? '__surface_pass' : '__surface_fail' ]++;
	echo ( $condition ? '[PASS] ' : '[FAIL] ' ) . $label . PHP_EOL;
}

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot = null;
	public static $surface = array();
	public static function snapshot() { return self::$snapshot; }
	public static function surface_context() { return self::$surface; }
	public static function history() { return array( 'days' => array() ); }
	public static function evaluation_summary() { return array(); }
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
function render_surface_fixture( ReflectionClass $reflection ) {
	$instance = $reflection->newInstanceWithoutConstructor();
	$method = $reflection->getMethod( 'render_page' );
	$method->setAccessible( true );
	return $method->invoke( $instance, array() );
}

$opportunity = array(
	'status' => 'available', 'state' => 'high', 'previous_state' => 'normal', 'changed' => true,
	'knowledge_time' => '2026-09-12T06:30:00+00:00', 'activity_percentile' => 91, 'range_60m_pct' => 1.4,
);
Bitmomo_Public_Intelligence_Adapter::$snapshot = array(
	'status' => 'fresh', 'btc_reference_price' => 65000,
	'opportunity' => $opportunity,
	'direction_strength' => 'bullish', 'directional_bias' => 'bullish',
	'confidence' => array( 'value' => 74, 'label' => 'high' ),
	'market_state' => 'accumulation', 'market_state_certainty' => 68,
	'key_drivers' => array( 'Directional consistency' ),
	'freshness' => array( 'state' => 'fresh', 'timestamp_iso' => '2026-09-12T06:20:00+00:00' ),
	'provenance' => array( 'source' => 'Binance public market data', 'as_of' => '2026-09-12T06:20:00+00:00', 'timezone' => 'Asia/Jakarta' ),
	'session' => array( 'label' => 'US POST-CLOSE' ), 'session_intelligence' => array(),
);
Bitmomo_Public_Intelligence_Adapter::$surface = array( 'opportunity' => $opportunity );
$full = render_surface_fixture( $reflection );
btc_surface_check( 'full snapshot translates activity into visitor language', false !== strpos( $full, 'AKTIVITAS PASAR' ) && false !== strpos( $full, '>Tinggi<' ) );
btc_surface_check( 'full snapshot preserves useful direction and reason', false !== strpos( $full, '>Bullish<' ) && false !== strpos( $full, 'Directional consistency' ) );
btc_surface_check( 'Opportunity engine terminology and raw distribution metrics stay hidden', false === strpos( $full, '>OPPORTUNITY<' ) && false === strpos( $full, 'activity percentile' ) && false === strpos( $full, '60m range' ) );
btc_surface_check( 'Market State classifier output stays hidden', false === strpos( $full, 'Akumulasi' ) && false === strpos( $full, '68% certainty' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot = null;
Bitmomo_Public_Intelligence_Adapter::$surface = array( 'opportunity' => $opportunity );
$partial = render_surface_fixture( $reflection );
btc_surface_check( 'missing directional snapshot is explicit and fail-closed', false !== strpos( $partial, 'Analisis arah sedang ditahan karena Major Brief belum memenuhi standar kualitas Bitmomo.' ) );
btc_surface_check( 'missing directional snapshot does not invent direction or confidence', false === strpos( $partial, '74/100' ) && false === strpos( $partial, 'Directional consistency' ) );

Bitmomo_Public_Intelligence_Adapter::$surface['opportunity'] = array( 'status' => 'unavailable', 'methodology_version' => 'opportunity-v1' );
$unavailable = render_surface_fixture( $reflection );
btc_surface_check( 'fully unavailable state remains explicit', false !== strpos( $unavailable, 'Analisis arah sedang ditahan karena Major Brief belum memenuhi standar kualitas Bitmomo.' ) );

$encoded = $full . $partial . $unavailable;
foreach ( array( 'source_diagnostics', 'private_note', 'risk', 'axes', 'entitlement', 'monitoring_conditions', 'scenario_contract', 'engine-secret', 'classifier-secret' ) as $forbidden ) {
	btc_surface_check( "no {$forbidden} leak", false === strpos( $encoded, $forbidden ) );
}

if ( $GLOBALS['__surface_fail'] ) exit( 1 );
echo $GLOBALS['__surface_pass'] . " surface-renderer checks passed.\n";

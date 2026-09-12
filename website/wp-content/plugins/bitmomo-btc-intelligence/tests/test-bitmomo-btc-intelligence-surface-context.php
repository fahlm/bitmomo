<?php
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'shortcode_exists' ) ) { function shortcode_exists( $tag ) { return false; } }
if ( ! function_exists( 'do_shortcode' ) ) { function do_shortcode( $value ) { return $value; } }
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
	public static function evaluation_summary() { return null; }
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
Bitmomo_Public_Intelligence_Adapter::$surface = array(
	'opportunity' => $opportunity,
	'provenance' => Bitmomo_Public_Intelligence_Adapter::$snapshot['provenance'],
);
$full = render_surface_fixture( $reflection );
btc_surface_check( 'full snapshot renders Opportunity before directional intelligence', strpos( $full, 'OPPORTUNITY' ) < strpos( $full, '>BIAS<' ) );
btc_surface_check( 'full snapshot preserves live values', false !== strpos( $full, '>HIGH<' ) && false !== strpos( $full, 'Bullish' ) && false !== strpos( $full, 'Directional consistency' ) );
btc_surface_check( 'full snapshot exposes independent Market State certainty', false !== strpos( $full, '68% certainty' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot = null;
Bitmomo_Public_Intelligence_Adapter::$surface = array(
	'opportunity' => $opportunity,
	'provenance' => array( 'source' => null, 'as_of' => null, 'timezone' => 'Asia/Jakarta' ),
);
$partial = render_surface_fixture( $reflection );
btc_surface_check( 'Opportunity remains useful when slower directional snapshot is unavailable', false !== strpos( $partial, '>HIGH<' ) && false !== strpos( $partial, 'Directional snapshot sedang menunggu' ) );
btc_surface_check( 'null directional snapshot does not invent Bias Confidence or Market State values', false === strpos( $partial, '74/100' ) && false === strpos( $partial, '68% certainty' ) );

Bitmomo_Public_Intelligence_Adapter::$surface['opportunity'] = array( 'status' => 'unavailable', 'methodology_version' => 'opportunity-v1' );
$unavailable = render_surface_fixture( $reflection );
btc_surface_check( 'fully unavailable state is explicit and fail-closed', false !== strpos( $unavailable, 'OPPORTUNITY' ) && false !== strpos( $unavailable, 'Belum tersedia' ) && false !== strpos( $unavailable, 'Directional snapshot sedang menunggu' ) );

$encoded = $full . $partial . $unavailable;
foreach ( array( 'source_diagnostics', 'private_note', 'risk', 'axes', 'entitlement', 'monitoring_conditions', 'scenario_contract' ) as $forbidden ) {
	btc_surface_check( "no {$forbidden} leak", false === strpos( $encoded, $forbidden ) );
}

if ( $GLOBALS['__surface_fail'] ) exit( 1 );
echo $GLOBALS['__surface_pass'] . " surface-renderer checks passed.\n";

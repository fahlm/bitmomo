<?php
require __DIR__ . '/wp-stubs.php';

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
}

require dirname( __DIR__ ) . '/bitmomo-btc-intelligence.php';
$base = '<section class="bm-bi__section bm-bi__section--peak bm-bi__snapshot"><p>legacy</p></section>';

Bitmomo_Public_Intelligence_Adapter::$snapshot = array(
	'direction_strength' => 'bullish',
	'confidence' => array( 'label' => 'high' ),
	'market_state' => 'accumulation',
	'key_drivers' => array( 'Directional consistency' ),
);
Bitmomo_Public_Intelligence_Adapter::$surface = array(
	'opportunity' => array( 'status' => 'available', 'state' => 'HIGH', 'knowledge_time' => '2026-09-12T06:30:00+00:00' ),
	'provenance' => array( 'source' => 'Binance public market data', 'as_of' => '2026-09-12T06:20:00+00:00', 'timezone' => 'Asia/Jakarta' ),
);
$full = Bitmomo_Btc_Opportunity_UI::inject( $base, 'bitmomo_btc_intelligence', array(), array() );
btc_surface_check( 'full snapshot renders ordered intelligence frame', strpos( $full, 'Opportunity' ) < strpos( $full, 'Directional Bias' ) && strpos( $full, 'Directional Bias' ) < strpos( $full, 'Confidence' ) && strpos( $full, 'Confidence' ) < strpos( $full, 'Market State' ) && strpos( $full, 'Market State' ) < strpos( $full, 'Primary Drivers' ) );
btc_surface_check( 'full snapshot preserves real values', false !== strpos( $full, '>HIGH<' ) && false !== strpos( $full, 'Moderate Bullish' ) && false !== strpos( $full, 'Directional consistency' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot = null;
Bitmomo_Public_Intelligence_Adapter::$surface = array(
	'opportunity' => array( 'status' => 'unavailable', 'methodology_version' => 'opportunity-v1' ),
	'provenance' => array( 'source' => null, 'as_of' => null, 'timezone' => 'Asia/Jakarta' ),
);
$unavailable = Bitmomo_Btc_Opportunity_UI::inject( $base, 'bitmomo_btc_intelligence', array(), array() );
btc_surface_check( 'null snapshot renders explicit Opportunity unavailable', false !== strpos( $unavailable, 'Opportunity' ) && false !== strpos( $unavailable, 'Belum tersedia' ) );
btc_surface_check( 'null snapshot renders all unavailable layers', false !== strpos( $unavailable, 'Directional Bias' ) && false !== strpos( $unavailable, 'Confidence' ) && false !== strpos( $unavailable, 'Market State' ) && false !== strpos( $unavailable, 'Primary Drivers' ) );
btc_surface_check( 'unavailable provenance labels remain truthful', false !== strpos( $unavailable, 'SOURCE</strong> Belum tersedia' ) && false !== strpos( $unavailable, 'AS OF</strong> Belum tersedia · WIB' ) );
btc_surface_check( 'unavailable direction has no synthetic spectrum marker', false === strpos( $unavailable, 'bm-bi__spectrum-marker' ) );

Bitmomo_Public_Intelligence_Adapter::$surface['opportunity'] = array( 'status' => 'available', 'state' => 'LOW' );
$partial = Bitmomo_Btc_Opportunity_UI::inject( $base, 'bitmomo_btc_intelligence', array(), array() );
btc_surface_check( 'Opportunity renders independently from null snapshot', false !== strpos( $partial, '>LOW<' ) );

$encoded = $full . $unavailable . $partial;
foreach ( array( 'source_diagnostics', 'private_note', 'risk', 'axes', 'entitlement' ) as $forbidden ) {
	btc_surface_check( "no {$forbidden} leak", false === strpos( $encoded, $forbidden ) );
}

if ( $GLOBALS['__surface_fail'] ) exit( 1 );
echo $GLOBALS['__surface_pass'] . " surface-renderer checks passed.\n";

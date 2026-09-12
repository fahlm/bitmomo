<?php
require __DIR__ . '/wp-stubs.php';

$GLOBALS['__prov_pass'] = 0;
$GLOBALS['__prov_fail'] = 0;

function prov_check( $label, $condition ) {
	if ( $condition ) {
		$GLOBALS['__prov_pass']++;
		echo "[PASS] {$label}\n";
	} else {
		$GLOBALS['__prov_fail']++;
		echo "[FAIL] {$label}\n";
	}
}

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot = array();
	public static function snapshot() {
		return self::$snapshot;
	}
}

Bitmomo_Public_Intelligence_Adapter::$snapshot = array(
	'opportunity' => array(
		'status' => 'available',
		'state' => 'high',
		'previous_state' => 'normal',
		'changed' => true,
		'knowledge_time' => '2026-09-12T06:30:00+00:00',
	),
	'provenance' => array(
		'source' => 'Binance public market data',
		'as_of' => '2026-09-12T06:20:00+00:00',
		'timezone' => 'Asia/Jakarta',
	),
);

require dirname( __DIR__ ) . '/bitmomo-btc-intelligence.php';

$base = '<section class="bm-bi__section bm-bi__section--peak bm-bi__snapshot"><p>snapshot</p></section>';
$output = Bitmomo_Btc_Opportunity_UI::inject( $base, 'bitmomo_btc_intelligence', array(), array() );

prov_check( 'public page renders canonical source label', false !== strpos( $output, 'SOURCE</strong> Binance public market data' ) );
prov_check( 'public page renders explicit as-of timestamp in WIB', false !== strpos( $output, 'AS OF</strong> 12 Sep 2026 · 13:20 WIB' ) );
prov_check( 'public page preserves machine-readable canonical as-of timestamp', false !== strpos( $output, 'datetime="2026-09-12T06:20:00+00:00"' ) );
prov_check( 'public page renders provenance beside Opportunity in the live snapshot panel', false !== strpos( $output, 'bm-bi__opportunity' ) && false !== strpos( $output, 'bm-bi__snapshot-provenance' ) );
prov_check( 'public page never exposes source diagnostics', false === strpos( $output, 'source_diagnostics' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot['provenance']['source'] = 'Binance public market data + Bybit derivatives fallback';
$fallback_output = Bitmomo_Btc_Opportunity_UI::inject( $base, 'bitmomo_btc_intelligence', array(), array() );
prov_check( 'fallback provider is disclosed exactly as supplied by the public adapter', false !== strpos( $fallback_output, 'Binance public market data + Bybit derivatives fallback' ) );

$missing = Bitmomo_Public_Intelligence_Adapter::$snapshot;
unset( $missing['provenance']['source'] );
Bitmomo_Public_Intelligence_Adapter::$snapshot = $missing;
prov_check( 'missing public provenance fails closed at the presentation bridge', Bitmomo_Btc_Opportunity_UI::inject( $base, 'bitmomo_btc_intelligence', array(), array() ) === $base );

Bitmomo_Public_Intelligence_Adapter::$snapshot = array(
	'opportunity' => array( 'status' => 'unavailable' ),
	'provenance' => array(
		'source' => 'Binance public market data',
		'as_of' => '2026-09-12T06:20:00+00:00',
		'timezone' => 'Asia/Jakarta',
	),
);
prov_check( 'unrelated shortcodes are untouched', Bitmomo_Btc_Opportunity_UI::inject( $base, 'other_shortcode', array(), array() ) === $base );

if ( $GLOBALS['__prov_fail'] > 0 ) {
	exit( 1 );
}

$total = $GLOBALS['__prov_pass'] + $GLOBALS['__prov_fail'];
echo $GLOBALS['__prov_pass'] . "/" . $total . " provenance checks passed.\n";

<?php
/** Standalone public BTC Intelligence contract tests. */
require __DIR__ . '/wp-stubs.php';

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check( $label, $cond ) {
	if ( $cond ) { $GLOBALS['__pass']++; echo "[PASS] $label\n"; }
	else { $GLOBALS['__fail']++; echo "[FAIL] $label\n"; }
}

class Bitmomo_Regime_Taxonomy {
	public static function regime_label_id( $regime ) {
		$labels = array(
			'accumulation' => 'Akumulasi', 'expansion' => 'Ekspansi',
			'distribution' => 'Distribusi', 'capitulation' => 'Kapitulasi', 'transition' => 'Transisi',
		);
		return $labels[ $regime ] ?? $regime;
	}
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-setup.php';

$page = Bitmomo_Btc_Intelligence_Page::instance();
$html = $page->render_page( array() );
check( 'Page renders without adapter', strlen( $html ) > 500 );
check( 'Page has canonical wrapper', false !== strpos( $html, 'class="bm-bi"' ) );
check( 'Hero is concise current/history/track-record framing', false !== strpos( $html, 'Kondisi sekarang, riwayat 30 hari, dan track record' ) );
check( 'Unavailable snapshot fails closed', false !== strpos( $html, 'Menunggu data yang memenuhi standar kualitas Bitmomo.' ) );
check( 'No fabricated BTC reference without adapter', false === strpos( $html, '$65,000' ) && false === strpos( $html, '$65.000' ) );
check( 'Legacy explanation wall is not rendered', false === strpos( $html, 'LIMA INTELLIGENCE AXES' ) && false === strpos( $html, 'CARA MEMBACA BITMOMO INTELLIGENCE' ) );
check( 'Legacy standalone evaluation sections are not rendered', false === strpos( $html, 'HUBUNGAN CONFIDENCE DENGAN AKURASI' ) && false === strpos( $html, 'PERFORMA BERDASARKAN REGIME' ) );
check( 'Methodology uses progressive disclosure', false !== strpos( $html, '<summary>Cara kerja &amp; metodologi</summary>' ) );
check( 'Only one Pro conversion action is rendered', 1 === substr_count( $html, 'Lihat Bitmomo Pro' ) );

$class_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' );
check( 'Public page never reads private scorecard directly', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', $class_source ) );
check( 'Public page never reads raw Regime State Store directly', 0 === preg_match( '/Bitmomo_Regime_State_Store::/', $class_source ) );
check( 'Public page never calls Pro internals', 0 === preg_match( '/Bitmomo_Pro_[A-Za-z]+::/', $class_source ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
check( 'CSS keeps commercial-orange token', false !== strpos( $css, '--bmi-orange:#f4ad32' ) );
check( 'Pro CTA uses commercial-orange token', false !== strpos( $css, 'background:var(--bmi-orange)' ) );
check( 'Proof is collapsed via details styling', false !== strpos( $css, '.bm-bi__details' ) );

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot_fixture = null;
	public static $evaluation_fixture = null;
	public static function snapshot() { return self::$snapshot_fixture; }
	public static function evaluation_summary() { return self::$evaluation_fixture; }
}

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
function render_fresh_bmi( ReflectionClass $reflection ) {
	$instance = $reflection->newInstanceWithoutConstructor();
	$method = $reflection->getMethod( 'render_page' );
	$method->setAccessible( true );
	return $method->invoke( $instance, array() );
}

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = array(
	'status' => 'fresh',
	'btc_reference_price' => 65000.0,
	'market_state' => 'expansion',
	'directional_bias' => 'bullish',
	'direction_strength' => 'strong_bullish',
	'confidence' => array( 'value' => 82, 'label' => 'high' ),
	'freshness' => array( 'timestamp_iso' => '2026-09-03T00:10:07+00:00', 'label' => 'fresh' ),
	'key_drivers' => array( 'Funding elevated', 'Spot volume rising', 'Structure improving', 'Fourth driver should not render' ),
	'session' => array( 'label' => 'US POST-CLOSE' ),
);
Bitmomo_Public_Intelligence_Adapter::$evaluation_fixture = array(
	'directional_evaluation' => array(
		'engine-v1' => array(
			'all' => array( 'n' => 40, 'accuracy_pct' => 62.5, 'sample_status' => 'ADEQUATE' ),
			'rolling_30' => array( 'n' => 30, 'accuracy_pct' => 60.0, 'sample_status' => 'ADEQUATE' ),
			'by_direction' => array(
				'bullish' => array( 'n' => 18, 'accuracy_pct' => 66.7, 'sample_status' => 'EARLY SAMPLE' ),
				'bearish' => array( 'n' => 12, 'accuracy_pct' => 58.3, 'sample_status' => 'EARLY SAMPLE' ),
			),
			'confidence_buckets' => array(
				array( 'range' => 'High', 'n' => 15, 'accuracy_pct' => 73.3, 'sample_status' => 'EARLY SAMPLE' ),
			),
		),
	),
	'expected_range_evaluation' => array(
		'versions' => array(
			'range-v1' => array( 'n' => 30, 'range_hit_pct' => 70.0, 'low_breach_pct' => 13.3, 'high_breach_pct' => 16.7, 'sample_status' => 'ADEQUATE' ),
		),
	),
	'data_quality' => array( 'n' => 40, 'stale_rate_pct' => 2.5, 'missing_data_rate_pct' => 1.0, 'sample_status' => 'ADEQUATE' ),
);

$html = render_fresh_bmi( $reflection );
check( 'Snapshot renders BTC reference', false !== strpos( $html, '65,000' ) || false !== strpos( $html, '65.000' ) );
check( 'Snapshot renders state', false !== strpos( $html, 'Ekspansi' ) );
check( 'Snapshot renders directional strength', false !== strpos( $html, 'Strong Bullish' ) );
check( 'Confidence stays explicitly non-probabilistic', false !== strpos( $html, 'Confidence = kekuatan evidence, bukan probabilitas keberhasilan.' ) );
check( 'Driver list is capped at three', false !== strpos( $html, 'Structure improving' ) && false === strpos( $html, 'Fourth driver should not render' ) );
check( 'Freshness converts explicit timestamp to WIB', false !== strpos( $html, '03 Sep 2026 · 07:10 WIB' ) );
check( 'Direction marker position comes from strength zone', false !== strpos( $html, '--bm-zone:4' ) );
check( 'Main track record renders all-time and rolling-30 metrics', false !== strpos( $html, '62.5%' ) && false !== strpos( $html, '60.0%' ) );
check( 'Secondary proof stays behind one expandable control', false !== strpos( $html, '<summary>Evaluasi lainnya</summary>' ) );
check( 'Expected Range historical evaluation remains public proof only', false !== strpos( $html, '70.0%' ) );
check( 'No live Expected Range price is exposed', 0 === preg_match( '/\$[\d,.]+\s*[-–]\s*\$[\d,.]+/', $html ) );

$setup = Bitmomo_Btc_Intelligence_Setup::instance();
check( 'Setup class instantiates', $setup instanceof Bitmomo_Btc_Intelligence_Setup );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );

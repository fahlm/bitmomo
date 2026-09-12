<?php
/** Standalone public BTC Intelligence contract tests. */
require __DIR__ . '/wp-stubs.php';

if ( ! function_exists( 'shortcode_exists' ) ) { function shortcode_exists( $tag ) { return false; } }
if ( ! function_exists( 'do_shortcode' ) ) { function do_shortcode( $value ) { return $value; } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://bitmomo.test' . $path; } }

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
check( 'Hero explains the live-context value proposition', false !== strpos( $html, 'Aktivitas pasar, arah evidence, perubahan konteks' ) );
check( 'Unavailable directional snapshot fails closed', false !== strpos( $html, 'Directional snapshot sedang menunggu data yang memenuhi standar kualitas Bitmomo.' ) );
check( 'No fabricated BTC reference without adapter', false === strpos( $html, '$65,000' ) && false === strpos( $html, '$65.000' ) );
check( 'Methodology uses progressive disclosure', false !== strpos( $html, 'Cara kerja &amp; metodologi' ) || false !== strpos( $html, 'Cara kerja & metodologi' ) );
check( 'Only one Pro conversion action is rendered', 1 === substr_count( $html, 'Lihat Bitmomo Pro' ) );

$class_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' );
$plugin_source = file_get_contents( dirname( __DIR__ ) . '/bitmomo-btc-intelligence.php' );
check( 'Public page never reads private scorecard directly', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', $class_source ) );
check( 'Public page never reads raw Regime State Store directly', 0 === preg_match( '/Bitmomo_Regime_State_Store::/', $class_source ) );
check( 'Public page never calls Pro internals', 0 === preg_match( '/Bitmomo_Pro_[A-Za-z]+::/', $class_source ) );
check( 'Stale do_shortcode Opportunity bridge has been removed', false === strpos( $plugin_source, 'Bitmomo_Btc_Opportunity_UI' ) && false === strpos( $plugin_source, 'do_shortcode_tag' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
check( 'CSS keeps commercial-orange token', false !== strpos( $css, '--bmi-orange:#f4ad32' ) );
check( 'Pro CTA uses commercial-orange token', false !== strpos( $css, 'background:var(--bmi-orange)' ) );
check( 'Confidence has a proportional visual meter', false !== strpos( $css, 'width:var(--bm-confidence)' ) );
check( 'Proof is collapsed via details styling', false !== strpos( $css, '.bm-bi__details' ) );

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot_fixture = null;
	public static $surface_fixture = array();
	public static $evaluation_fixture = null;
	public static function snapshot() { return self::$snapshot_fixture; }
	public static function surface_context() { return self::$surface_fixture; }
	public static function evaluation_summary() { return self::$evaluation_fixture; }
}

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
function render_fresh_bmi( ReflectionClass $reflection ) {
	$instance = $reflection->newInstanceWithoutConstructor();
	$method = $reflection->getMethod( 'render_page' );
	$method->setAccessible( true );
	return $method->invoke( $instance, array() );
}

$opportunity = array(
	'status' => 'available', 'state' => 'HIGH', 'previous_state' => 'NORMAL', 'changed' => true,
	'knowledge_time' => '2026-09-03T00:15:00+00:00', 'activity_percentile' => 84.2, 'range_60m_pct' => 1.17,
	'methodology_version' => 'opportunity-v1',
);
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = array(
	'status' => 'fresh',
	'btc_reference_price' => 65000.0,
	'opportunity' => $opportunity,
	'market_state' => 'expansion',
	'market_state_certainty' => 76,
	'directional_bias' => 'bullish',
	'direction_strength' => 'strong_bullish',
	'confidence' => array( 'value' => 82, 'label' => 'high' ),
	'freshness' => array( 'state' => 'fresh', 'timestamp_iso' => '2026-09-03T00:10:07+00:00', 'label' => 'fresh' ),
	'provenance' => array( 'source' => 'Binance public market data', 'as_of' => '2026-09-03T00:10:07+00:00', 'timezone' => 'Asia/Jakarta' ),
	'key_drivers' => array( 'Funding elevated', 'Spot volume rising', 'Structure improving', 'Fourth driver should not render' ),
	'session' => array( 'label' => 'US POST-CLOSE' ),
	'session_intelligence' => array(
		'what_happened' => array(
			'btc_change_pct' => 2.25,
			'derivatives_context' => array( 'open_interest_change_24h_pct' => 3.5, 'funding_rate' => 0.0001, 'basis_pct' => 0.12 ),
		),
		'comparison' => array( 'status' => 'compared' ),
		'what_changed' => array(
			array( 'field' => 'directional_bias', 'from' => 'neutral', 'to' => 'bullish' ),
			array( 'field' => 'confidence', 'from' => 61, 'to' => 82 ),
			array( 'field' => 'strongest_driver', 'from' => 'Old driver', 'to' => 'Structure improving' ),
		),
	),
);
Bitmomo_Public_Intelligence_Adapter::$surface_fixture = array(
	'opportunity' => $opportunity,
	'provenance' => Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['provenance'],
);
$metric_a = array( 'n' => 40, 'conclusive_n' => 35, 'correct' => 22, 'incorrect' => 13, 'inconclusive' => 5, 'accuracy_pct' => 62.9, 'sample_status' => 'ADEQUATE' );
$metric_b = array( 'n' => 20, 'conclusive_n' => 14, 'correct' => 8, 'incorrect' => 6, 'inconclusive' => 6, 'accuracy_pct' => 57.1, 'sample_status' => 'EARLY SAMPLE' );
Bitmomo_Public_Intelligence_Adapter::$evaluation_fixture = array(
	'directional_evaluation' => array(
		'engine-v2 | classifier-v2' => array(
			'all' => $metric_a,
			'rolling_30' => array_merge( $metric_a, array( 'n' => 30, 'conclusive_n' => 27, 'accuracy_pct' => 63.0 ) ),
			'by_direction' => array( 'bullish' => $metric_a, 'bearish' => $metric_b, 'neutral' => $metric_b ),
			'confidence_buckets' => array( array_merge( $metric_b, array( 'range' => '70–100' ) ) ),
		),
		'engine-v1 | classifier-v1' => array(
			'all' => $metric_b, 'rolling_30' => $metric_b,
			'by_direction' => array( 'bullish' => $metric_b, 'bearish' => $metric_b, 'neutral' => $metric_b ),
			'confidence_buckets' => array( array_merge( $metric_b, array( 'range' => '40–69' ) ) ),
		),
	),
	'expected_range_evaluation' => array(
		'policy' => 'FROZEN_ORIGINAL_ONLY',
		'versions' => array(
			'range-v2' => array( 'n' => 30, 'range_hit_pct' => 70.0, 'low_breach_pct' => 13.3, 'high_breach_pct' => 16.7, 'sample_status' => 'ADEQUATE' ),
			'range-v1' => array( 'n' => 12, 'range_hit_pct' => 58.3, 'low_breach_pct' => 16.7, 'high_breach_pct' => 25.0, 'sample_status' => 'EARLY SAMPLE' ),
		),
	),
	'regime_performance' => array( 'versions' => array( 'classifier-v2' => array( 'expansion' => array( 'n' => 14, 'average_forward_return_pct' => 1.2, 'sample_status' => 'EARLY SAMPLE' ) ) ) ),
	'data_quality' => array( 'n' => 40, 'stale_rate_pct' => 2.5, 'blocked_degraded_rate_pct' => 5.0, 'missing_data_rate_pct' => 1.0, 'settlement_completeness_pct' => 92.5, 'sample_status' => 'ADEQUATE' ),
);

$html = render_fresh_bmi( $reflection );
check( 'Opportunity is rendered natively with live activity context', false !== strpos( $html, '>HIGH<' ) && false !== strpos( $html, '84' ) && false !== strpos( $html, '60m range' ) );
check( 'Snapshot renders BTC reference', false !== strpos( $html, '65,000' ) || false !== strpos( $html, '65.000' ) );
check( 'Snapshot renders state and canonical certainty', false !== strpos( $html, 'Ekspansi' ) && false !== strpos( $html, '76% certainty' ) );
check( 'Snapshot renders directional strength', false !== strpos( $html, 'Strong Bullish' ) );
check( 'Confidence renders exact value and stays explicitly non-probabilistic', false !== strpos( $html, '82/100' ) && false !== strpos( $html, 'bukan probabilitas' ) );
check( 'Driver list is capped at three', false !== strpos( $html, 'Structure improving' ) && false === strpos( $html, 'Fourth driver should not render' ) );
check( 'Freshness converts explicit timestamp to WIB', false !== strpos( $html, '03 Sep 2026 · 07:10 WIB' ) );
check( 'Direction marker position comes from strength zone', false !== strpos( $html, '--bm-zone:4' ) );
check( 'Observed 24H context renders current measurable data', false !== strpos( $html, 'OBSERVED 24H' ) && false !== strpos( $html, 'OI 24H' ) && false !== strpos( $html, 'FUNDING' ) );
check( 'What Changed renders only current-context changes', false !== strpos( $html, 'WHAT CHANGED' ) && false !== strpos( $html, 'Bias berubah' ) && false !== strpos( $html, 'Confidence: 61 → 82' ) );
check( 'Track-record accuracy shows the real conclusive denominator', false !== strpos( $html, '35 konklusif · 40 total' ) );
check( 'Neutral outcomes are not silently omitted from direction proof', false !== strpos( $html, '>Neutral<' ) );
check( 'Every incompatible directional version remains visible', false !== strpos( $html, 'engine-v2 | classifier-v2' ) && false !== strpos( $html, 'engine-v1 | classifier-v1' ) );
check( 'Every Expected Range version remains visible', false !== strpos( $html, 'range-v2' ) && false !== strpos( $html, 'range-v1' ) );
check( 'Data-quality proof includes settlement completeness', false !== strpos( $html, 'Settlement complete' ) && false !== strpos( $html, '92.5%' ) );
check( 'No live Expected Range price is exposed', 0 === preg_match( '/\$[\d,.]+\s*[-–]\s*\$[\d,.]+/', $html ) );
check( 'Free page does not render Pro monitoring/scenario fields', false === strpos( $html, 'what_to_watch' ) && false === strpos( $html, 'scenario_contract' ) && false === strpos( $html, 'monitoring_conditions' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['status'] = 'delayed';
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['freshness']['state'] = 'delayed';
$delayed = render_fresh_bmi( $reflection );
check( 'Delayed intelligence is explicitly labelled instead of relying on timestamp inference', false !== strpos( $delayed, 'DATA TERTUNDA' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['status'] = 'fresh';
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['freshness']['state'] = 'fresh';
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['market_state'] = null;
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['market_state_certainty'] = null;
$partial_state = render_fresh_bmi( $reflection );
check( 'Missing same-edition Market State stays explicitly pending while other intelligence remains usable', false !== strpos( $partial_state, 'classification pending' ) && false !== strpos( $partial_state, 'Strong Bullish' ) );

$setup = Bitmomo_Btc_Intelligence_Setup::instance();
check( 'Setup class instantiates', $setup instanceof Bitmomo_Btc_Intelligence_Setup );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );

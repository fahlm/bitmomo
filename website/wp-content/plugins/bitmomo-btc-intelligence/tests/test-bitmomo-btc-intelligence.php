<?php
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://bitmomo.test' . $path; } }

$GLOBALS['__pass'] = 0; $GLOBALS['__fail'] = 0;
function check( $label, $cond ) { if ( $cond ) { $GLOBALS['__pass']++; echo "[PASS] $label\n"; } else { $GLOBALS['__fail']++; echo "[FAIL] $label\n"; } }

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-setup.php';

$page = Bitmomo_Btc_Intelligence_Page::instance();
$html = $page->render_page( array() );
check( 'Page renders without public adapter', strlen( $html ) > 400 );
check( 'Page has canonical wrapper', false !== strpos( $html, 'class="bm-bi"' ) );
check( 'Hero is visitor-first rather than engine-first', false !== strpos( $html, 'tanpa tenggelam dalam data' ) );
check( 'Unavailable reading fails closed', false !== strpos( $html, 'Pembacaan arah sedang ditahan' ) );
check( 'No fabricated BTC reference without adapter', false === strpos( $html, '$65,000' ) && false === strpos( $html, '$65.000' ) );
check( 'Only one Pro conversion action is rendered', 1 === substr_count( $html, 'Lihat Bitmomo Pro' ) );

$class_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
check( 'Public page never reads private scorecard directly', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', $class_source ) );
check( 'Public page never reads raw Regime State Store directly', 0 === preg_match( '/Bitmomo_Regime_State_Store::/', $class_source ) );
check( 'Public page never calls Pro internals', 0 === preg_match( '/Bitmomo_Pro_[A-Za-z]+::/', $class_source ) );
check( 'Commercial-orange CTA token remains', false !== strpos( $css, '--bmi-orange:#f4ad32' ) && false !== strpos( $css, 'background:var(--bmi-orange)' ) );
check( 'Secondary methodology stays progressively disclosed', false !== strpos( $css, '.bm-bi__details' ) );
check( 'Mobile snapshot collapses to one column', false !== strpos( $css, '.bm-bi__snapshot-grid{grid-template-columns:1fr}' ) );

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot_fixture = null;
	public static $surface_fixture = array();
	public static $history_fixture = array( 'days' => array() );
	public static $evaluation_fixture = array();
	public static function snapshot() { return self::$snapshot_fixture; }
	public static function surface_context() { return self::$surface_fixture; }
	public static function history() { return self::$history_fixture; }
	public static function evaluation_summary() { return self::$evaluation_fixture; }
}

$opportunity = array(
	'status' => 'available', 'state' => 'high', 'previous_state' => 'normal', 'changed' => true,
	'knowledge_time' => '2026-09-12T20:15:00+00:00', 'activity_percentile' => 84.2,
	'range_60m_pct' => 1.17, 'methodology_version' => 'opportunity-v1',
);
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = array(
	'status' => 'fresh', 'btc_reference_price' => 65000.0, 'opportunity' => $opportunity,
	'market_state' => 'expansion', 'market_state_certainty' => 76,
	'directional_bias' => 'bullish', 'direction_strength' => 'strong_bullish',
	'confidence' => array( 'value' => 82, 'label' => 'high' ),
	'freshness' => array( 'state' => 'fresh', 'timestamp_iso' => '2026-09-12T20:10:07+00:00', 'label' => 'fresh' ),
	'provenance' => array( 'source' => 'Binance public market data + Bybit derivatives fallback', 'as_of' => '2026-09-12T20:10:07+00:00', 'timezone' => 'Asia/Jakarta' ),
	'key_drivers' => array( 'Momentum BTC menguat.', 'Volatilitas meningkat.', 'Driver ketiga tidak boleh tampil.' ),
	'session' => array( 'label' => 'US POST-CLOSE', 'edition_id' => 'internal-edition-id' ),
	'session_intelligence' => array(
		'what_happened' => array( 'btc_change_pct' => 2.25, 'derivatives_context' => array( 'open_interest_change_24h_pct' => 3.5, 'funding_rate' => 0.0001, 'basis_pct' => 0.12 ) ),
		'comparison' => array( 'status' => 'compared' ),
		'what_changed' => array(
			array( 'field' => 'directional_bias', 'from' => 'neutral', 'to' => 'bullish' ),
			array( 'field' => 'confidence', 'from' => 61, 'to' => 82 ),
			array( 'field' => 'strongest_driver', 'from' => 'old', 'to' => 'new' ),
		),
	),
	'versions' => array( 'engine' => 'engine-secret-v2', 'classifier' => 'classifier-secret-v2' ),
);
Bitmomo_Public_Intelligence_Adapter::$surface_fixture = array( 'opportunity' => $opportunity );
Bitmomo_Public_Intelligence_Adapter::$history_fixture = array(
	'target_days' => 30, 'available_days' => 4,
	'days' => array(
		array( 'date' => '2026-09-09', 'directional_bias' => 'bearish', 'market_state' => 'distribution', 'market_state_certainty' => 88, 'version_group' => 'classifier-secret-v1' ),
		array( 'date' => '2026-09-10', 'directional_bias' => 'neutral', 'market_state' => 'transition', 'market_state_certainty' => 44, 'version_group' => 'classifier-secret-v1' ),
		array( 'date' => '2026-09-11', 'directional_bias' => 'bullish', 'market_state' => 'accumulation', 'market_state_certainty' => 71, 'version_group' => 'classifier-secret-v2' ),
		array( 'date' => '2026-09-12', 'directional_bias' => 'bullish', 'market_state' => 'expansion', 'market_state_certainty' => 76, 'version_group' => 'classifier-secret-v2' ),
	),
);
$current_metric = array( 'n' => 40, 'conclusive_n' => 35, 'correct' => 22, 'incorrect' => 13, 'inconclusive' => 5, 'accuracy_pct' => 62.9, 'sample_status' => 'ADEQUATE' );
$rolling_metric = array_merge( $current_metric, array( 'n' => 30, 'conclusive_n' => 27, 'accuracy_pct' => 63.0 ) );
$legacy_metric = array( 'n' => 20, 'conclusive_n' => 18, 'accuracy_pct' => 11.1, 'sample_status' => 'EARLY SAMPLE' );
Bitmomo_Public_Intelligence_Adapter::$evaluation_fixture = array(
	'directional_evaluation' => array(
		'engine-v2 | classifier-v2 | observed-close-24h-v2' => array(
			'outcome_methodology' => 'observed-close-24h-v2', 'all' => $current_metric, 'rolling_30' => $rolling_metric,
			'by_direction' => array( 'bullish' => $current_metric, 'bearish' => array_merge( $current_metric, array( 'accuracy_pct' => 57.1 ) ), 'neutral' => $current_metric ),
			'confidence_buckets' => array( array_merge( $current_metric, array( 'range' => '70–100' ) ) ),
		),
		'engine-v1 | classifier-v1 | legacy-window-v1' => array( 'all' => $legacy_metric, 'rolling_30' => $legacy_metric, 'by_direction' => array( 'bullish' => $legacy_metric ) ),
	),
	'expected_range_evaluation' => array( 'versions' => array( 'range-v2' => array( 'n' => 30, 'range_hit_pct' => 70.0 ) ) ),
	'regime_performance' => array( 'versions' => array( 'classifier-v2' => array( 'expansion' => array( 'n' => 14, 'average_forward_return_pct' => 1.2 ) ) ) ),
	'data_quality' => array( 'n' => 40, 'stale_rate_pct' => 2.5, 'settlement_completeness_pct' => 92.5, 'sample_status' => 'ADEQUATE' ),
);

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
$instance = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod( 'render_page' );
$method->setAccessible( true );
$html = $method->invoke( $instance, array() );

check( 'Current reading exposes direction in human language', false !== strpos( $html, 'Bullish kuat' ) );
check( 'Confidence is exact but explicitly not a price probability', false !== strpos( $html, '82/100' ) && false !== strpos( $html, 'bukan probabilitas harga' ) );
check( 'Activity is translated into a visitor-facing state', false !== strpos( $html, 'AKTIVITAS PASAR' ) && false !== strpos( $html, '>Tinggi<' ) );
check( 'Opportunity internals are not displayed', false === strpos( $html, 'activity percentile' ) && false === strpos( $html, '60m range' ) && false === strpos( $html, '84.2' ) && false === strpos( $html, '1.17%' ) );
check( 'Market State taxonomy and classifier certainty stay out of public presentation', false === strpos( $html, 'Ekspansi' ) && false === strpos( $html, '76% certainty' ) && false === strpos( $html, 'MARKET STATE' ) );
check( 'Raw derivative kitchen metrics stay out of public presentation', false === strpos( $html, 'OI 24H' ) && false === strpos( $html, 'FUNDING' ) && false === strpos( $html, 'BASIS' ) );
check( 'Only two visitor-relevant reasons render', false !== strpos( $html, 'Momentum BTC menguat.' ) && false !== strpos( $html, 'Volatilitas meningkat.' ) && false === strpos( $html, 'Driver ketiga tidak boleh tampil.' ) );
check( 'What changed is capped and humanized', false !== strpos( $html, 'Arah berubah dari Netral menjadi Bullish.' ) && false !== strpos( $html, 'Keyakinan pembacaan berubah dari 61 menjadi 82.' ) && false === strpos( $html, 'strongest_driver' ) );
check( 'Public trust metadata is concise', false !== strpos( $html, '13 Sep 2026 · 03:10 WIB' ) && false !== strpos( $html, 'Sumber data: Binance + Bybit' ) );
check( '30-day context shows direction only', false !== strpos( $html, 'Bullish 2' ) && false !== strpos( $html, 'Netral 1' ) && false !== strpos( $html, 'Bearish 1' ) );
check( '30-day context does not expose regime or classifier internals', false === strpos( $html, 'Distribusi' ) && false === strpos( $html, 'Akumulasi' ) && false === strpos( $html, 'classifier-secret' ) );
check( 'Track record uses current methodology outcome only', false !== strpos( $html, '62.9%' ) && false !== strpos( $html, '63.0%' ) && false === strpos( $html, '11.1%' ) );
check( 'Track record hides engine and classifier version identifiers', false === strpos( $html, 'engine-v2' ) && false === strpos( $html, 'classifier-v2' ) && false === strpos( $html, 'legacy-window-v1' ) );
check( 'Track record exposes honest denominator', false !== strpos( $html, '35 outcome konklusif · 40 total' ) );
check( 'Track record describes exact +24h evaluation', false !== strpos( $html, 'tepat +24 jam' ) );
check( 'Legacy methodology is disclosed without dumping identifiers', false !== strpos( $html, 'metodologi sebelumnya tetap disimpan untuk audit' ) );
check( 'Internal evaluation diagnostics do not render', false === strpos( $html, 'Range hit' ) && false === strpos( $html, 'Settlement complete' ) && false === strpos( $html, 'Stale rate' ) && false === strpos( $html, 'Confidence vs akurasi' ) );
check( 'Free page does not leak Pro monitoring/scenario fields', false === strpos( $html, 'monitoring_conditions' ) && false === strpos( $html, 'scenario_contract' ) && false === strpos( $html, 'what_to_watch' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['status'] = 'delayed';
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['freshness']['state'] = 'delayed';
$delayed_instance = $reflection->newInstanceWithoutConstructor();
$delayed_html = $method->invoke( $delayed_instance, array() );
check( 'Delayed intelligence is explicitly labelled', false !== strpos( $delayed_html, 'DATA TERTUNDA' ) );

$setup = Bitmomo_Btc_Intelligence_Setup::instance();
check( 'Setup class instantiates', $setup instanceof Bitmomo_Btc_Intelligence_Setup );
printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );

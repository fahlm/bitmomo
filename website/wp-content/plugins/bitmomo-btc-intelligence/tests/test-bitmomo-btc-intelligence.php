<?php
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://bitmomo.test' . $path; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $timestamp = null ) { return date( $format, null === $timestamp ? time() : $timestamp ); } }

$GLOBALS['__pass'] = 0; $GLOBALS['__fail'] = 0;
function check( $label, $cond ) { if ( $cond ) { $GLOBALS['__pass']++; echo "[PASS] $label\n"; } else { $GLOBALS['__fail']++; echo "[FAIL] $label\n"; } }

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-setup.php';

$page = Bitmomo_Btc_Intelligence_Page::instance();
$html = $page->render_page( array() );
check( 'Page renders without public adapter', strlen( $html ) > 400 );
check( 'Page has canonical wrapper', false !== strpos( $html, 'class="bm-bi"' ) );
check( 'Hero explains the two-clock visitor model', false !== strpos( $html, 'Market Pulse menunjukkan aktivitas intraday' ) && false !== strpos( $html, 'Major Brief menunjukkan bias' ) );
check( 'Unavailable Major Brief fails closed', false !== strpos( $html, 'Analisis arah sedang ditahan karena Major Brief belum memenuhi standar kualitas Bitmomo.' ) );
check( 'No fabricated BTC reference without adapter', false === strpos( $html, '$65,000' ) && false === strpos( $html, '$65.000' ) );
check( 'Only one Pro conversion action is rendered', 1 === substr_count( $html, 'Lihat Bitmomo Pro' ) );
check( 'Page still renders accountability boundaries without data', false !== strpos( $html, 'Decision Ledger' ) && false !== strpos( $html, 'Arsip Pro' ) );

$class_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' );
$bootstrap_source = file_get_contents( dirname( __DIR__ ) . '/bitmomo-btc-intelligence.php' );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
check( 'Public page never reads private scorecard directly', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', $class_source ) );
check( 'Public page never reads raw Regime State Store directly', 0 === preg_match( '/Bitmomo_Regime_State_Store::/', $class_source ) );
check( 'Public page never calls Pro internals', 0 === preg_match( '/Bitmomo_Pro_[A-Za-z]+::/', $class_source ) );
check( 'Public page reaches row evidence only through accountability boundary', false !== strpos( $class_source, 'Bitmomo_Btc_Intelligence_Accountability::decision_ledger' ) && false !== strpos( $class_source, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof' ) );
check( 'Final public copy is renderer-owned rather than shortcode-rewritten', false === strpos( $bootstrap_source, 'do_shortcode_tag' ) && false === strpos( $bootstrap_source, 'strtr(' ) );
check( 'Commercial action consumes canonical amber token', false !== strpos( $css, '--bmi-action:var(--bm-action,#f4ad32)' ) && false !== strpos( $css, 'background:var(--bmi-action)' ) );
check( 'Secondary methodology stays progressively disclosed', false !== strpos( $css, '.bm-bi__details' ) );
check( 'Mobile snapshot collapses to one column', false !== strpos( $css, '.bm-bi__snapshot-grid{grid-template-columns:1fr}' ) );
check( 'Ledger overflow is contained instead of overflowing the page', false !== strpos( $css, '.bm-bi__ledger-wrap' ) && false !== strpos( $css, 'overflow-x:auto' ) );
check( 'Renderer explicitly separates stale Major Brief from Market Pulse', false !== strpos( $class_source, 'MAJOR BRIEF TERTUNDA' ) && false !== strpos( $class_source, 'Market Pulse tetap tampil terpisah' ) && false !== strpos( $class_source, 'render_market_pulse' ) );
check( 'Early sample copy explicitly cautions against inference', false !== strpos( $class_source, 'Sampel awal — belum layak disimpulkan' ) );
check( 'Insufficient sample accuracy is explicitly withheld', false !== strpos( $class_source, 'Akurasi ditahan sampai sampel minimum terpenuhi.' ) );

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

class Bitmomo_Btc_Intelligence_Accountability {
	public static $ledger_fixture = array( 'rows' => array() );
	public static $proof_fixture = array( 'delay_hours' => 48, 'rows' => array() );
	public static function decision_ledger( $limit = 12 ) { return self::$ledger_fixture; }
	public static function delayed_proof( $limit = 3 ) { return self::$proof_fixture; }
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
	'session' => array( 'type' => 'us_post_close', 'label' => 'US POST-CLOSE', 'edition_id' => 'internal-edition-id', 'anchor' => '2026-09-12T20:10:00-04:00' ),
	'session_intelligence' => array(
		'what_happened' => array( 'btc_change_pct' => 2.25, 'ending_directional_bias' => 'bullish', 'derivatives_context' => array( 'open_interest_change_24h_pct' => 3.5, 'funding_rate' => 0.0001, 'basis_pct' => 0.12 ) ),
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

Bitmomo_Btc_Intelligence_Accountability::$ledger_fixture = array(
	'provenance' => 'recorded_live_matured_outcomes', 'policy' => 'RECENT_MATURED_NO_RESULT_FILTER', 'evaluation_window' => '+24h',
	'rows' => array(
		array( 'generated_at' => '2026-09-10T02:00:00+00:00', 'direction' => 'bullish', 'confidence' => 74, 'session' => 'Morning', 'reference_price' => 64000, 'forward_return_pct' => 1.42, 'verdict' => 'aligned' ),
		array( 'generated_at' => '2026-09-09T13:00:00+00:00', 'direction' => 'bearish', 'confidence' => 69, 'session' => 'US Session', 'reference_price' => 63500, 'forward_return_pct' => 0.88, 'verdict' => 'missed' ),
		array( 'generated_at' => '2026-09-08T02:00:00+00:00', 'direction' => 'neutral', 'confidence' => 51, 'session' => 'Morning', 'reference_price' => 63100, 'forward_return_pct' => null, 'verdict' => 'unscored' ),
	),
);
Bitmomo_Btc_Intelligence_Accountability::$proof_fixture = array(
	'provenance' => 'frozen_published_pro_briefs', 'policy' => 'DELAYED_PUBLIC_PROOF_V1', 'delay_hours' => 48,
	'rows' => array(
		array(
			'published_at' => '2026-09-08T12:00:00+00:00', 'market_state' => 'bullish', 'confidence' => 77,
			'reference_price' => 63000, 'expected_range_low' => 62500, 'expected_range_high' => 64500,
			'base_scenario' => 'BTC bertahan di atas support dan menguji sisi atas range.',
			'bull_scenario' => 'Acceptance di atas resistance membuka ekspansi lanjutan.',
			'bear_scenario' => 'Kehilangan support menggeser fokus ke downside.',
			'invalidation' => 'Thesis batal jika support utama gagal dipertahankan.',
			'what_changed' => 'Momentum membaik sementara funding tetap terkendali.',
			'evaluation_status' => 'evaluated', 'verdict' => 'aligned', 'outcome_return_pct' => 1.11, 'range_hit' => 'yes',
		),
	),
);

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
$instance = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod( 'render_page' );
$method->setAccessible( true );
$html = $method->invoke( $instance, array() );

check( 'Current reading exposes direction in human language', false !== strpos( $html, 'Bullish kuat' ) );
check( 'Current terminal uses final institutional labels at source', false !== strpos( $html, '>BIAS<' ) && false !== strpos( $html, '>CONFIDENCE<' ) );
check( 'Confidence is exact but explicitly not a price probability', false !== strpos( $html, '82/100' ) && false !== strpos( $html, 'bukan probabilitas pergerakan harga' ) );
check( 'Market Pulse is translated into a visitor-facing intraday state', false !== strpos( $html, 'MARKET PULSE · INTRADAY' ) && false !== strpos( $html, '>Tinggi<' ) && false !== strpos( $html, 'Aktivitas pasar berada di atas kondisi normal 14 hari.' ) );
check( 'Market Pulse exposes its independent clock', false !== strpos( $html, 'evaluasi 15 menit dari candle 5 menit' ) );
check( 'Opportunity internals are not displayed', false === strpos( $html, 'activity percentile' ) && false === strpos( $html, '60m range' ) && false === strpos( $html, '84.2' ) && false === strpos( $html, '1.17%' ) );
check( 'Market State taxonomy and classifier certainty stay out of current public presentation', false === strpos( $html, 'Ekspansi' ) && false === strpos( $html, 'Distribusi' ) && false === strpos( $html, '76% certainty' ) && false === strpos( $html, 'MARKET STATE' ) );
check( 'Raw derivative kitchen metrics stay out of public presentation', false === strpos( $html, 'OI 24H' ) && false === strpos( $html, 'FUNDING' ) && false === strpos( $html, 'BASIS' ) );
check( 'Only two visitor-relevant reasons render', false !== strpos( $html, 'Momentum BTC menguat.' ) && false !== strpos( $html, 'Volatilitas meningkat.' ) && false === strpos( $html, 'Driver ketiga tidak boleh tampil.' ) );
check( 'What changed is capped and humanized', false !== strpos( $html, 'Bias berubah dari Netral menjadi Bullish.' ) && false !== strpos( $html, 'Confidence berubah dari 61 menjadi 82.' ) && false === strpos( $html, 'strongest_driver' ) );
check( 'Public trust metadata is concise', false !== strpos( $html, '13 Sep 2026 · 03:10 WIB' ) && false !== strpos( $html, 'Sumber data: Binance + Bybit' ) );
check( '30-day context shows direction only', false !== strpos( $html, 'Bullish 2' ) && false !== strpos( $html, 'Netral 1' ) && false !== strpos( $html, 'Bearish 1' ) );
check( '30-day context does not expose regime or classifier internals', false === strpos( $html, 'classifier-secret' ) );
check( 'Decision Ledger includes wins, misses and unscored records instead of success-only rows', false !== strpos( $html, 'TANPA PILIH-PILIH HASIL' ) && false !== strpos( $html, 'SESUAI' ) && false !== strpos( $html, 'TIDAK SESUAI' ) && false !== strpos( $html, 'BELUM DINILAI' ) );
check( 'Decision Ledger shows frozen-time view and forward outcome', false !== strpos( $html, '$64,000' ) && false !== strpos( $html, '+1.42%' ) );
check( 'Decision Ledger does not dump internal methodology IDs', false === strpos( $html, 'observed-close-24h-v2' ) );
check( 'Track record uses current methodology outcome only', false !== strpos( $html, 'Akurasi 62.9%' ) && false !== strpos( $html, 'Akurasi 63.0%' ) && false === strpos( $html, '11.1%' ) );
check( 'Track record hides engine and classifier version identifiers', false === strpos( $html, 'engine-v2' ) && false === strpos( $html, 'classifier-v2' ) && false === strpos( $html, 'legacy-window-v1' ) );
$sample_position = strpos( $html, '35 / 40' );
$accuracy_position = strpos( $html, 'Akurasi 62.9%' );
check( 'Track record is sample-first rather than percentage-first', false !== $sample_position && false !== $accuracy_position && $sample_position < $accuracy_position && false !== strpos( $html, 'hasil konklusif / total · Sampel memadai' ) );
check( 'Track record explains why sample comes first', false !== strpos( $html, 'Jumlah hasil konklusif ditampilkan lebih dulu' ) );
check( 'Track record describes exact +24h evaluation', false !== strpos( $html, 'Aturan hasil tepat +24 jam' ) );
check( 'Legacy methodology is disclosed without dumping identifiers', false !== strpos( $html, 'Versi lama dipertahankan untuk audit' ) );
check( 'Delayed Pro proof exposes a real historical decision contract', false !== strpos( $html, 'FROM THE PRO ARCHIVE' ) && false !== strpos( $html, 'BTC bertahan di atas support' ) && false !== strpos( $html, 'Thesis batal jika support utama gagal dipertahankan' ) );
check( 'Delayed Pro proof exposes actual settled outcome and range result', false !== strpos( $html, '+1.11%' ) && false !== strpos( $html, 'Range tercapai: YA' ) );
check( 'Delayed Pro proof is explicitly historical and time-delayed', false !== strpos( $html, 'TERTUNDA ≥ 48 JAM' ) && false !== strpos( $html, 'Arsip historis · bukan panduan saat ini' ) );
check( 'Internal evaluation diagnostics still do not render', false === strpos( $html, 'Settlement complete' ) && false === strpos( $html, 'Stale rate' ) && false === strpos( $html, 'Confidence vs akurasi' ) );
check( 'Free page does not leak current-Pro internal field identifiers', false === strpos( $html, 'monitoring_conditions' ) && false === strpos( $html, 'scenario_contract' ) && false === strpos( $html, 'what_to_watch' ) );

$insufficient_metric = array( 'n' => 8, 'conclusive_n' => 6, 'correct' => 5, 'incorrect' => 1, 'inconclusive' => 2, 'accuracy_pct' => 83.3, 'sample_status' => 'INSUFFICIENT SAMPLE' );
Bitmomo_Public_Intelligence_Adapter::$evaluation_fixture = array(
	'directional_evaluation' => array(
		'engine-v2 | classifier-v2 | observed-close-24h-v2' => array(
			'all' => $insufficient_metric,
			'rolling_30' => $insufficient_metric,
			'by_direction' => array( 'bullish' => $insufficient_metric, 'bearish' => $insufficient_metric ),
		),
	),
);
$insufficient_instance = $reflection->newInstanceWithoutConstructor();
$insufficient_html = $method->invoke( $insufficient_instance, array() );
check( 'Insufficient sample does not advertise a seductive accuracy percentage', false !== strpos( $insufficient_html, 'Akurasi ditahan sampai sampel minimum terpenuhi.' ) && false === strpos( $insufficient_html, 'Akurasi 83.3%' ) );

Bitmomo_Public_Intelligence_Adapter::$evaluation_fixture = array(
	'directional_evaluation' => array(
		'engine-v2 | classifier-v2 | observed-close-24h-v2' => array(
			'outcome_methodology' => 'observed-close-24h-v2', 'all' => $current_metric, 'rolling_30' => $rolling_metric,
			'by_direction' => array( 'bullish' => $current_metric, 'bearish' => array_merge( $current_metric, array( 'accuracy_pct' => 57.1 ) ), 'neutral' => $current_metric ),
		),
	),
);
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['status'] = 'delayed';
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['freshness']['state'] = 'delayed';
$delayed_instance = $reflection->newInstanceWithoutConstructor();
$delayed_html = $method->invoke( $delayed_instance, array() );
check( 'Delayed Major Brief is explicitly labelled', false !== strpos( $delayed_html, 'DATA TERTUNDA' ) && false !== strpos( $delayed_html, 'MAJOR BRIEF TERTUNDA' ) );
check( 'Delayed Major Brief withholds the directional view', false !== strpos( $delayed_html, 'Observasi brief terverifikasi terakhir: 13 Sep 2026 · 03:10 WIB.' ) );
check( 'Delayed Major Brief labels its reference as historical', false !== strpos( $delayed_html, 'BTC referensi brief terakhir: $65,000.' ) );
check( 'Delayed Major Brief does not show stale bias confidence or drivers as current', false === strpos( $delayed_html, 'Bullish kuat' ) && false === strpos( $delayed_html, '82/100' ) && false === strpos( $delayed_html, 'Momentum BTC menguat.' ) );
check( 'Fresh Market Pulse survives a stale Major Brief', false !== strpos( $delayed_html, 'MARKET PULSE · INTRADAY' ) && false !== strpos( $delayed_html, 'Aktivitas pasar berada di atas kondisi normal 14 hari.' ) );
check( 'Delayed intelligence still preserves public provenance', false !== strpos( $delayed_html, 'Sumber data: Binance + Bybit' ) );

$setup = Bitmomo_Btc_Intelligence_Setup::instance();
check( 'Setup class instantiates', $setup instanceof Bitmomo_Btc_Intelligence_Setup );
printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );

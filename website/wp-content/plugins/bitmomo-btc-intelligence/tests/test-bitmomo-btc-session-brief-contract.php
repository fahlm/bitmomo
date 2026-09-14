<?php
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '/' ) { return 'https://bitmomo.test' . $path; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $timestamp = null ) { return date( $format, null === $timestamp ? time() : $timestamp ); } }

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
function check_session_contract( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['__pass']++;
		echo "[PASS] $label\n";
	} else {
		$GLOBALS['__fail']++;
		echo "[FAIL] $label\n";
	}
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';

class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot_fixture = null;
	public static function snapshot() { return self::$snapshot_fixture; }
	public static function surface_context() { return array( 'opportunity' => self::$snapshot_fixture['opportunity'] ?? array() ); }
	public static function history() { return array( 'days' => array() ); }
	public static function evaluation_summary() { return array(); }
}

class Bitmomo_Btc_Intelligence_Accountability {
	public static function decision_ledger( $limit = 12 ) { return array( 'rows' => array() ); }
	public static function delayed_proof( $limit = 3 ) { return array( 'delay_hours' => 48, 'rows' => array() ); }
}

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = array(
	'status' => 'fresh',
	'btc_reference_price' => 65000,
	'directional_bias' => 'bullish',
	'direction_strength' => 'strong_bullish',
	'confidence' => array( 'value' => 82, 'label' => 'high' ),
	'freshness' => array(
		'state' => 'fresh',
		'timestamp_iso' => '2026-09-12T20:10:07+00:00',
	),
	'provenance' => array(
		'source' => 'Binance public market data + Bybit derivatives fallback',
		'as_of' => '2026-09-12T20:10:07+00:00',
	),
	'opportunity' => array(
		'status' => 'available',
		'state' => 'HIGH',
		'knowledge_time' => '2026-09-12T20:15:00+00:00',
		'activity_percentile' => 84.2,
		'range_60m_pct' => 1.17,
		'methodology_version' => 'opportunity-v1',
	),
	'key_drivers' => array(
		'Momentum BTC menguat.',
		'Volatilitas meningkat.',
		'Driver ketiga tidak boleh tampil.',
	),
	'session' => array(
		'type' => 'us_post_close',
		'label' => 'US POST-CLOSE',
		'anchor' => '2026-09-12T20:10:00-04:00',
		'us_market_status' => 'regular_session_day',
	),
	'session_intelligence' => array(
		'what_happened' => array(
			'observed_window' => 'trailing_24h',
			'btc_change_pct' => 2.25,
			'ending_directional_bias' => 'bullish',
			'derivatives_context' => array(
				'open_interest_change_24h_pct' => 3.5,
				'funding_rate' => 0.0001,
				'basis_pct' => 0.12,
			),
		),
		'comparison' => array( 'status' => 'compared' ),
		'what_changed' => array(
			array( 'field' => 'directional_bias', 'from' => 'neutral', 'to' => 'bullish' ),
			array( 'field' => 'confidence', 'from' => 61, 'to' => 82 ),
		),
		'why_it_matters' => array(
			'directional_context_changed',
			'evidence_strength_changed',
			'private_reason_must_fail_closed',
		),
		'what_to_watch' => array(
			'private_trigger_must_fail_closed',
			'directional_consistency',
			'structure_continuity',
		),
		'monitoring_conditions' => array( 'PRO_MONITORING_SENTINEL' ),
		'scenario_contract' => array( 'base' => 'PRO_SCENARIO_SENTINEL' ),
		'expected_range' => 'PRO_RANGE_SENTINEL',
		'invalidation' => 'PRO_INVALIDATION_SENTINEL',
	),
);

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );
$page = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod( 'render_page' );
$method->setAccessible( true );
$html = $method->invoke( $page, array() );

check_session_contract( 'Major Brief identifies canonical session', false !== strpos( $html, 'MAJOR BRIEF · US POST-CLOSE' ) );
check_session_contract( 'Session anchor is translated to WIB', false !== strpos( $html, 'Anchor sesi · 13 Sep · 07:10 WIB' ) );
check_session_contract( 'Post-close Free brief exposes concise observed 24h context', false !== strpos( $html, 'Dalam 24 jam menuju brief ini, BTC bergerak +2.25% dan pembacaan berakhir Bullish.' ) );
check_session_contract( 'Free brief keeps two public-safe drivers only', false !== strpos( $html, 'Momentum BTC menguat.' ) && false !== strpos( $html, 'Volatilitas meningkat.' ) && false === strpos( $html, 'Driver ketiga tidak boleh tampil.' ) );
check_session_contract( 'What Changed remains humanized', false !== strpos( $html, 'Bias berubah dari Netral menjadi Bullish.' ) && false !== strpos( $html, 'Confidence berubah dari 61 menjadi 82.' ) );
check_session_contract( 'Why It Matters translates deterministic codes into visitor language', false !== strpos( $html, 'Arah dominan pasar berubah' ) && false !== strpos( $html, 'Kekuatan bukti berubah' ) );
check_session_contract( 'Internal why code never leaks', false === strpos( $html, 'directional_context_changed' ) && false === strpos( $html, 'private_reason_must_fail_closed' ) );
check_session_contract( 'Exactly one allowlisted public watch is rendered', 1 === substr_count( $html, 'PANTAU BERIKUTNYA' ) && false !== strpos( $html, 'Apakah kekuatan arah tetap konsisten' ) && false === strpos( $html, 'Apakah struktur harga tetap mendukung bias saat ini.' ) );
check_session_contract( 'Unknown watch code fails closed', false === strpos( $html, 'private_trigger_must_fail_closed' ) );
check_session_contract( 'Fast layer states correct cadence', false !== strpos( $html, 'evaluasi 15 menit dari candle 5 menit' ) );
check_session_contract( 'Opportunity internals remain hidden', false === strpos( $html, '84.2' ) && false === strpos( $html, '1.17' ) && false === strpos( $html, 'opportunity-v1' ) );
check_session_contract( 'Raw derivative kitchen metrics remain hidden', false === strpos( $html, '3.5' ) && false === strpos( $html, '0.0001' ) && false === strpos( $html, '0.12' ) );
check_session_contract( 'Current Pro monitoring and scenario sentinels never render', false === strpos( $html, 'PRO_MONITORING_SENTINEL' ) && false === strpos( $html, 'PRO_SCENARIO_SENTINEL' ) && false === strpos( $html, 'PRO_RANGE_SENTINEL' ) && false === strpos( $html, 'PRO_INVALIDATION_SENTINEL' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['status'] = 'delayed';
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture['freshness']['state'] = 'delayed';
$delayed_page = $reflection->newInstanceWithoutConstructor();
$delayed_html = $method->invoke( $delayed_page, array() );
check_session_contract( 'Delayed snapshot still fails closed', false !== strpos( $delayed_html, 'PEMBACAAN SAAT INI DITAHAN' ) );
check_session_contract( 'Delayed snapshot withholds session analysis and public watch', false === strpos( $delayed_html, 'Dalam 24 jam menuju brief ini' ) && false === strpos( $delayed_html, 'Arah dominan pasar berubah' ) && false === strpos( $delayed_html, 'Apakah kekuatan arah tetap konsisten' ) && false === strpos( $delayed_html, 'Momentum BTC menguat.' ) );

printf( "\n%d/%d passed.\n", $GLOBALS['__pass'], $GLOBALS['__pass'] + $GLOBALS['__fail'] );
exit( $GLOBALS['__fail'] === 0 ? 0 : 1 );

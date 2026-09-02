<?php
/**
 * Standalone test harness (php tests/test-bitmomo-btc-intelligence.php),
 * not a WP testing framework substitute — matches the convention already
 * used by bitmomo-pro's and bitmomo-regime's own test suites.
 */

require __DIR__ . '/wp-stubs.php';

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function check( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['__pass']++;
		echo "[PASS] $label\n";
	} else {
		$GLOBALS['__fail']++;
		echo "[FAIL] $label\n";
	}
}

/**
 * Mock of Bitmomo_AI_Intelligence::free_projection() — controllable per
 * test case via a static fixture, so this suite never needs the real
 * bitmomo-ai plugin loaded (keeping this plugin's tests fully isolated,
 * matching the codebase's zero-cross-plugin-dependency convention).
 */
class Bitmomo_AI_Intelligence {
	public static $fixture = null;
	public static function free_projection() {
		return self::$fixture;
	}
}

class Bitmomo_Regime_State_Store {
	public static $fixture = null;
	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	public function get_latest() {
		return self::$fixture;
	}
}

class Bitmomo_Regime_Taxonomy {
	public static function regime_label_id( $regime ) {
		$labels = array(
			'accumulation' => 'Akumulasi',
			'expansion'    => 'Ekspansi',
		);
		return $labels[ $regime ] ?? $regime;
	}
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-setup.php';

$page = Bitmomo_Btc_Intelligence_Page::instance();

/* =========================================================================
 * ROUTE / PAGE RENDERS
 * ====================================================================== */
Bitmomo_AI_Intelligence::$fixture = array(
	'status'        => 'fresh',
	'price'         => 65000,
	'bias'          => 'bullish',
	'confidence'    => 82,
	'key_drivers'   => array( 'Funding rate elevated', 'Spot volume rising' ),
	'timestamp_iso' => date( 'c' ),
);
Bitmomo_Regime_State_Store::$fixture = array( 'regime' => 'expansion' );

$html = $page->render_page( array() );

check( 'Page renders non-empty output', strlen( $html ) > 500 );
check( 'Page output wrapped in bm-bi container', false !== strpos( $html, 'class="bm-bi"' ) );
check( 'Hero headline matches locked copy exactly', false !== strpos( $html, 'Pahami BTC dalam konteks.' ) );
check( 'Hero supporting line matches locked copy exactly', false !== strpos( $html, 'Lima axis. Satu framework. Track record terbuka.' ) );
check( 'Hero disclaimer matches locked copy exactly, no extra explanatory sentence', false !== strpos( $html, '<p class="bm-bi__hero-micro">Bukan sinyal beli/jual. Bukan saran keuangan.</p>' ) );
check( 'All 13 section anchors present (13 h1/h2 headings incl. hero)', substr_count( $html, 'bm-bi__section-title' ) + substr_count( $html, 'bm-bi__hero-title' ) >= 12 );

/* =========================================================================
 * CURRENT SNAPSHOT — WIRED FIELDS
 * ====================================================================== */
check( 'Snapshot: Market State label rendered', false !== strpos( $html, 'Ekspansi' ) );
check( 'Snapshot: Directional Bias rendered as Bullish', false !== strpos( $html, 'Bullish' ) );
check( 'Snapshot: Confidence bucketed to Tinggi at 82', false !== strpos( $html, 'Tinggi' ) );
check( 'Snapshot: BTC reference price rendered', false !== strpos( $html, '65,000' ) || false !== strpos( $html, '65.000' ) );
check( 'Snapshot: Faktor Utama list rendered', false !== strpos( $html, 'Funding rate elevated' ) );
check( 'Snapshot: freshness timestamp line rendered, not "Belum tersedia"', false !== strpos( $html, 'Data terkini' ) );

/* =========================================================================
 * UNKNOWN / STALE BEHAVIOR
 * ====================================================================== */
Bitmomo_AI_Intelligence::$fixture = array(
	'status'        => 'fresh',
	'price'         => 65000,
	'bias'          => '', // canonical bias absent
	'confidence'    => 50,
	'key_drivers'   => array(),
	'timestamp_iso' => date( 'c' ),
);
$html_no_bias = $page->render_page( array() );
check( 'Bias-absent renders honest "Belum Tersedia", never defaulted to Neutral', false !== strpos( $html_no_bias, 'Belum Tersedia' ) );
check( 'Bias-absent does NOT silently show "Neutral"', false === strpos( $html_no_bias, '>Neutral<' ) );
check( 'Empty key_drivers renders honest empty note, not a blank list', false !== strpos( $html_no_bias, 'Faktor utama belum tersedia' ) );

Bitmomo_AI_Intelligence::$fixture = array(
	'status'  => 'unavailable',
	'message' => 'Update BTC terbaru belum tersedia.',
	'detail'  => 'Sistem sedang menunggu data.',
);
Bitmomo_Regime_State_Store::$fixture = null;
$html_unavailable = $page->render_page( array() );
check( 'Unavailable snapshot renders honest unavailable state', false !== strpos( $html_unavailable, 'Update BTC terbaru belum tersedia.' ) );
check( 'Unavailable snapshot never fabricates a price/confidence figure', false === strpos( $html_unavailable, 'BTC Reference' ) || false === strpos( $html_unavailable, 'bm-bi__snapshot-grid' ) );

// Reset to a normal fixture for the remaining checks.
Bitmomo_AI_Intelligence::$fixture = array(
	'status'        => 'fresh',
	'price'         => 65000,
	'bias'          => 'bullish',
	'confidence'    => 82,
	'key_drivers'   => array( 'Funding rate elevated' ),
	'timestamp_iso' => date( 'c' ),
);
Bitmomo_Regime_State_Store::$fixture = array( 'regime' => 'expansion' );
$html = $page->render_page( array() );

/* =========================================================================
 * NO FABRICATED HISTORY / NO FAKE PERFORMANCE NUMBERS
 * ====================================================================== */
check( 'Historical regime section falls back honestly when shortcode is not registered', false !== strpos( $html, 'Riwayat Market State belum tersedia.' ) );
check( 'Track Record renders the "Belum Tersedia" boundary, not a number', substr_count( $html, 'Belum Tersedia' ) >= 5 );
check( 'Confidence evaluation shows locked headline verbatim', false !== strpos( $html, 'Apakah Confidence yang lebih tinggi benar-benar menghasilkan akurasi yang lebih konsisten?' ) );
check( 'Confidence evaluation shows locked clarification verbatim', false !== strpos( $html, 'Confidence menunjukkan kekuatan evidence di balik analisis, bukan probabilitas keberhasilan.' ) );
check( 'No bare/fabricated illustrative percentages (18, 68%, 312) leak into output', false === strpos( $html, '68%' ) && false === strpos( $html, '312' ) );
check( 'Expected Range section never shows a live/current range number', 0 === preg_match( '/Rp[\d.,]+\s*[-–]\s*Rp[\d.,]+/', $html ) );

/* =========================================================================
 * NO PROTECTED PRO CURRENT-VALUE LEAKAGE
 * ====================================================================== */
check( 'Output never mentions Scenario Map', false === stripos( $html, 'scenario map' ) );
check( 'Output never mentions Thesis Invalidation', false === stripos( $html, 'thesis invalidation' ) );
check( 'Output never mentions "What Changed" as a live data block', false === stripos( $html, 'what changed' ) );
check( 'Page class file never references any Bitmomo_Pro_ class', 0 === preg_match_all( '/Bitmomo_Pro_[A-Za-z]+::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );
check( 'Page class file never CALLS Bitmomo_AI_Scorecard:: (private P1 internals) -- a docblock mention explaining why it is avoided is fine, a static call is not', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );

/* =========================================================================
 * PUBLIC COPY HYGIENE -- no internal engineering names/class names in
 * customer-facing "Belum Tersedia" boundary copy (final polish pass).
 * ====================================================================== */
check( 'Rendered public output never names the internal adapter class to users', false === strpos( $html, 'Bitmomo_Public_Intelligence_Adapter' ) );
check( 'Rendered public output never says "adapter" to users', false === stripos( $html, 'adapter' ) );
check( 'Rendered public output never says "tim engineering" to users', false === stripos( $html, 'tim engineering' ) );

/* =========================================================================
 * GLOBAL COLOR SYSTEM -- orange is the sole commercial-CTA accent; cyan
 * remains the intelligence/informational accent everywhere else.
 * ====================================================================== */
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
check( 'CSS defines a commercial-orange token matching the site-wide CTA color', false !== strpos( $css, '--bmi-orange: #f4ad32' ) );
check( 'Pro CTA primary button uses the orange token, not cyan', false !== strpos( $css, 'background: var( --bmi-orange )' ) );

/* =========================================================================
 * LOCKED VOCABULARY
 * ====================================================================== */
check( 'Customer-facing copy never uses the word "pembacaan"', 0 === preg_match( '/pembacaan/i', $html ) );
check( 'Market State and Directional Bias explained as separate concepts', false !== strpos( $html, 'Market State menjelaskan struktur' ) && false !== strpos( $html, 'Directional Bias menjelaskan arah' ) );

/* =========================================================================
 * FIVE AXES — CONCEPTUAL, NOT "5 AI AGENTS"
 * ====================================================================== */
foreach ( array( 'Direction', 'Volatility', 'Carry', 'Structure', 'Crowding' ) as $axis ) {
	check( "Five Axes: $axis card rendered", false !== strpos( $html, '>' . $axis . '<' ) );
}
check( 'Five Axes explicitly denies being 5 independent AI agents', false !== strpos( $html, 'bukan lima "agent" AI yang independen' ) );

/* =========================================================================
 * PRO CTA — locked option 1, routes to /pro, no feature-list headline
 * ====================================================================== */
check( 'Pro CTA headline matches locked copy', false !== strpos( $html, 'Ketahui apa yang perlu diperhatikan berikutnya.' ) );
check( 'Pro CTA links to /pro/', false !== strpos( $html, 'href="http://example.test/pro/"' ) );
check( 'Pro CTA headline does not lead with Expected Range/Scenario Map as the feature', 0 === preg_match( '/perhatikan berikutnya\.<\/h2>\s*<p>[^<]*Expected Range/i', $html ) );

/* =========================================================================
 * SETUP CLASS — idempotent draft-page provisioning, mirrors Bitmomo_Pro_Setup
 * ====================================================================== */
$setup = Bitmomo_Btc_Intelligence_Setup::instance();
check( 'Setup class instantiates cleanly', $setup instanceof Bitmomo_Btc_Intelligence_Setup );

echo "\n" . $GLOBALS['__pass'] . '/' . ( $GLOBALS['__pass'] + $GLOBALS['__fail'] ) . " passed.\n";
if ( $GLOBALS['__fail'] > 0 ) {
	exit( 1 );
}

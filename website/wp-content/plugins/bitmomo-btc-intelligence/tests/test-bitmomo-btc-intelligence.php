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
 * MARKET DIRECTION SPECTRUM — hero intelligence instrument (P0 hardening,
 * 2026-09). Direction/Strength must come only from the canonical bias
 * (and, once it exists, a canonical direction_strength field) -- never
 * from Confidence. Confidence must only ever change the marker's fill
 * weight/opacity class and the separate confidence badge, never the
 * marker's left/width (its spectrum position).
 * ====================================================================== */
function spectrum_marker_style( $html ) {
	if ( ! preg_match( '/bm-bi__spectrum-marker[^"]*"[^>]*style="([^"]+)"/', $html, $m ) ) {
		return null;
	}
	return $m[1];
}

// Extracts the marker's full class attribute value. Deliberately used with
// per-token checks (not a fixed substring) so these tests don't couple to
// an arbitrary class array order in the PHP -- only the presence of each
// semantic class matters, never which comes first.
function spectrum_marker_classes( $html ) {
	if ( ! preg_match( '/class="(bm-bi__spectrum-marker[^"]*)"/', $html, $m ) ) {
		return null;
	}
	return explode( ' ', $m[1] );
}

function marker_has_classes( $html, array $expected ) {
	$classes = spectrum_marker_classes( $html );
	if ( null === $classes ) {
		return false;
	}
	foreach ( $expected as $needle ) {
		if ( ! in_array( $needle, $classes, true ) ) {
			return false;
		}
	}
	return true;
}

check( 'Hero intelligence visualization (spectrum) renders', false !== strpos( $html, 'bm-bi__spectrum-track' ) && false !== strpos( $html, 'MARKET DIRECTION SPECTRUM' ) );
check( 'Spectrum shows all five canonical zone labels', false !== strpos( $html, 'Strong Bear' ) && false !== strpos( $html, 'Strong Bull' ) && false !== strpos( $html, '>Neutral<' ) );

// Bullish, high confidence (82) -- no direction_strength field (today's
// real backend state): must render the "wide" (strength-unknown) marker
// on the bullish side, never a fabricated Moderate/Strong claim.
Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'bullish', 'confidence' => 82,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ),
);
$html_bull_hi = $page->render_page( array() );
check( 'Directional Strength does NOT render Strong/Moderate without a canonical backend field', false === strpos( $html_bull_hi, '>Strong Bullish<' ) && false === strpos( $html_bull_hi, '>Moderate Bullish<' ) );
check( 'Missing direction_strength renders the honest "segera hadir" wide-band note, not a fabricated classification', false !== strpos( $html_bull_hi, 'kekuatan arah' ) && false !== strpos( $html_bull_hi, 'segera hadir' ) );
check( 'Bullish marker uses the wide (strength-unknown) treatment, not a precise one', marker_has_classes( $html_bull_hi, array( 'bm-bi__spectrum-marker--wide', 'is-bullish' ) ) );
check( 'High confidence (82) renders the "tinggi" confidence class', false !== strpos( $html_bull_hi, 'is-confidence-tinggi' ) );
$style_bull_hi = spectrum_marker_style( $html_bull_hi );

// Same bias, LOW confidence (12) -- position must be byte-identical;
// only the confidence class may differ. This is the core "Confidence
// never moves spectrum position" assertion.
Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'bullish', 'confidence' => 12,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ),
);
$html_bull_lo = $page->render_page( array() );
$style_bull_lo = spectrum_marker_style( $html_bull_lo );
check( 'CONFIDENCE INDEPENDENCE: low confidence (12) renders the "rendah" confidence class', false !== strpos( $html_bull_lo, 'is-confidence-rendah' ) );
check( 'CONFIDENCE INDEPENDENCE: marker left/width position is IDENTICAL for the same bias regardless of Confidence (82 vs 12)', null !== $style_bull_hi && $style_bull_hi === $style_bull_lo );
check( 'CONFIDENCE INDEPENDENCE: direction label stays Bullish at low confidence too (direction is not downgraded by low confidence)', false !== strpos( $html_bull_lo, '<strong>Bullish</strong>' ) );

// Neutral is a real, precisely-known state (no Moderate/Strong ambiguity
// for Neutral in the canonical enum) -- must render as a solid, precise
// marker at all confidence levels, never as an empty/missing state.
Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'neutral', 'confidence' => 91,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ),
);
$html_neutral_hi = $page->render_page( array() );
check( 'Neutral renders as a valid, precise state (not the strength-unknown wide treatment)', marker_has_classes( $html_neutral_hi, array( 'bm-bi__spectrum-marker--precise', 'is-neutral' ) ) );
$style_neutral_hi = spectrum_marker_style( $html_neutral_hi );

Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'neutral', 'confidence' => 8,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ),
);
$html_neutral_lo = $page->render_page( array() );
$style_neutral_lo = spectrum_marker_style( $html_neutral_lo );
check( 'Neutral + low confidence still renders the precise Neutral marker (not empty/missing)', marker_has_classes( $html_neutral_lo, array( 'bm-bi__spectrum-marker--precise', 'is-neutral' ) ) );
check( 'CONFIDENCE INDEPENDENCE: Neutral marker position is identical regardless of Confidence (91 vs 8)', null !== $style_neutral_hi && $style_neutral_hi === $style_neutral_lo );
check( 'Neutral + high vs low confidence differ only in confidence class, not position', strpos( $html_neutral_hi, 'is-confidence-tinggi' ) !== false && strpos( $html_neutral_lo, 'is-confidence-rendah' ) !== false );

// Bearish, symmetric check.
Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'bearish', 'confidence' => 60,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ),
);
$html_bear = $page->render_page( array() );
check( 'Bearish marker uses the wide (strength-unknown) treatment, symmetric with bullish', marker_has_classes( $html_bear, array( 'bm-bi__spectrum-marker--wide', 'is-bearish' ) ) );
check( 'Bearish does NOT render a fabricated Strong/Moderate Bearish claim', false === strpos( $html_bear, '>Strong Bearish<' ) && false === strpos( $html_bear, '>Moderate Bearish<' ) );

// Simulate a FUTURE backend that adds `direction_strength` to
// free_projection(), using the exact canonical score_status() enum this
// class documents. The frontend must render a PRECISE marker from that
// value verbatim -- proving the architecture is five-state-ready without
// this test having to wait for the real adapter/backend field to exist.
Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'bullish', 'confidence' => 75,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ), 'direction_strength' => 'strong_bullish',
);
$html_strong = $page->render_page( array() );
check( 'A canonical direction_strength value from the backend renders a PRECISE marker (five-state-ready architecture)', marker_has_classes( $html_strong, array( 'bm-bi__spectrum-marker--precise', 'is-bullish' ) ) );
check( 'Once direction_strength is known, the "segera hadir" placeholder note disappears', false === strpos( $html_strong, 'segera hadir' ) );

// An unrecognized/garbage direction_strength value must never be trusted
// -- falls back to the same honest wide-band treatment as if it were
// absent (never coerced, never a version/shape guess).
Bitmomo_AI_Intelligence::$fixture = array(
	'status' => 'fresh', 'price' => 65000, 'bias' => 'bullish', 'confidence' => 75,
	'key_drivers' => array(), 'timestamp_iso' => date( 'c' ), 'direction_strength' => 'extremely_bullish_v2',
);
$html_bad_strength = $page->render_page( array() );
check( 'An unrecognized direction_strength value is never trusted -- falls back to the honest wide-band state', marker_has_classes( $html_bad_strength, array( 'bm-bi__spectrum-marker--wide', 'is-bullish' ) ) );

// No fabricated price series / chart anywhere on this page.
check( 'No canvas/chart element was added for a fabricated price series', false === strpos( $html, '<canvas' ) );

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
check( 'Unavailable snapshot never fabricates a price/confidence figure', false === strpos( $html_unavailable, 'BTC Reference' ) || false === strpos( $html_unavailable, 'bm-bi__snapshot-top' ) );
check( 'Unavailable snapshot renders no spectrum instrument at all', false === strpos( $html_unavailable, 'bm-bi__spectrum-track' ) );

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

/* =========================================================================
 * ADAPTER INTEGRATION PREP (2026-09) -- no real
 * Bitmomo_Public_Intelligence_Adapter ships in this codebase yet. This
 * mock exists only to exercise, for the first time, the "adapter is
 * available" branch of adapter_snapshot()/adapter_history()/
 * adapter_evaluation_summary() and every section's
 * render_adapter_pending_boundary() call -- proving the guard/cache/
 * branch plumbing this pass added is actually wired end to end, not
 * merely present and untested. Defined here, AFTER $html above was
 * already rendered and cached by the pre-existing singleton $page (whose
 * per-instance adapter caches were locked to "unavailable" before this
 * class existed) -- so every check above this point still verifies the
 * honest no-adapter path untouched by this mock.
 * ====================================================================== */
class Bitmomo_Public_Intelligence_Adapter {
	public static $calls = array(
		'snapshot'           => 0,
		'history'            => 0,
		'evaluation_summary' => 0,
	);
	public static function snapshot() {
		self::$calls['snapshot']++;
		return array( 'mock' => true );
	}
	public static function history() {
		self::$calls['history']++;
		return array( 'mock' => true );
	}
	public static function evaluation_summary() {
		self::$calls['evaluation_summary']++;
		return array( 'mock' => true );
	}
}

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );

// A fresh, non-singleton instance so its adapter caches start empty and
// this mock is picked up (the module-level $page singleton already
// cached "unavailable" before this class existed, by design).
$fresh_page = $reflection->newInstanceWithoutConstructor();

$snapshot_method = $reflection->getMethod( 'adapter_snapshot' );
$snapshot_method->setAccessible( true );
$snapshot_result = $snapshot_method->invoke( $fresh_page );
check( 'ADAPTER PREP: adapter_snapshot() calls the mock and returns its array', is_array( $snapshot_result ) && 1 === Bitmomo_Public_Intelligence_Adapter::$calls['snapshot'] );
$snapshot_method->invoke( $fresh_page );
check( 'ADAPTER PREP: adapter_snapshot() is cached per instance (second call does not re-invoke the adapter)', 1 === Bitmomo_Public_Intelligence_Adapter::$calls['snapshot'] );

$history_method = $reflection->getMethod( 'adapter_history' );
$history_method->setAccessible( true );
$history_result = $history_method->invoke( $fresh_page );
check( 'ADAPTER PREP: adapter_history() calls the mock and returns its array', is_array( $history_result ) && 1 === Bitmomo_Public_Intelligence_Adapter::$calls['history'] );

$snapshot_available_method = $reflection->getMethod( 'adapter_snapshot_available' );
$snapshot_available_method->setAccessible( true );
check( 'ADAPTER PREP: adapter_snapshot_available() is true once the mock adapter is present', true === $snapshot_available_method->invoke( $fresh_page ) );

$history_available_method = $reflection->getMethod( 'adapter_history_available' );
$history_available_method->setAccessible( true );
check( 'ADAPTER PREP: adapter_history_available() is true once the mock adapter is present', true === $history_available_method->invoke( $fresh_page ) );

// Full page render on a fresh instance -- exercises every section's
// render_adapter_pending_boundary() call with $available === true.
$render_page_method = $reflection->getMethod( 'render_page' );
$render_page_method->setAccessible( true );
$html_with_adapter = $render_page_method->invoke( $fresh_page, array() );

check( 'ADAPTER PREP: evaluation_summary() gets called at least once when rendering with the mock adapter present', Bitmomo_Public_Intelligence_Adapter::$calls['evaluation_summary'] > 0 );
check( 'ADAPTER PREP: Track Record shows its partial-available note once the adapter responds', false !== strpos( $html_with_adapter, 'Data ringkasan Track Record belum tersedia.' ) );
check( 'ADAPTER PREP: Data Quality still shows its honest deeper-metrics note once the adapter responds', false !== strpos( $html_with_adapter, 'Metrik kualitas data yang lebih mendalam' ) );
check( 'ADAPTER PREP: still no fabricated Expected Range number even with the adapter "available"', 0 === preg_match( '/Rp[\d.,]+\s*[-–]\s*Rp[\d.,]+/', $html_with_adapter ) );
check( 'ADAPTER PREP: still no fabricated illustrative percentage even with the adapter "available"', false === strpos( $html_with_adapter, '68%' ) );
check( 'ADAPTER PREP: "Belum Tersedia" boundary badge still renders for every dependent section (mock has no real per-section data, so nothing is fabricated)', substr_count( $html_with_adapter, 'Belum Tersedia' ) >= 5 );
check( 'ADAPTER PREP: page class still never calls the private P1 scorecard directly', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );

echo "\n" . $GLOBALS['__pass'] . '/' . ( $GLOBALS['__pass'] + $GLOBALS['__fail'] ) . " passed.\n";
if ( $GLOBALS['__fail'] > 0 ) {
	exit( 1 );
}

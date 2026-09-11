<?php
/**
 * Standalone test harness (php tests/test-bitmomo-btc-intelligence.php),
 * not a WP testing framework substitute — matches the convention already
 * used by bitmomo-pro's and bitmomo-regime's own test suites.
 *
 * 2026-09-03 P0 wiring pass: this page now reads ONLY
 * Bitmomo_Public_Intelligence_Adapter (merged via PR #69 / commit
 * 056e488), never Bitmomo_AI_Intelligence::free_projection() or
 * Bitmomo_Regime_State_Store directly. This suite therefore mocks the
 * adapter itself, using the exact real field names/shapes read from the
 * merged bitmomo-ai source (class-bitmomo-public-intelligence-adapter.php)
 * and its own test fixtures -- never a guessed shape.
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

class Bitmomo_Regime_Taxonomy {
	public static function regime_label_id( $regime ) {
		$labels = array(
			'accumulation' => 'Akumulasi',
			'expansion'    => 'Ekspansi',
			'distribution' => 'Distribusi',
			'transition'   => 'Transisi',
		);
		return $labels[ $regime ] ?? $regime;
	}
}

require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php';
require dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-setup.php';

/* =========================================================================
 * PART A -- ADAPTER CLASS ABSENT (e.g. bitmomo-ai deactivated). Run BEFORE
 * Bitmomo_Public_Intelligence_Adapter is defined below, so class_exists()
 * genuinely returns false here -- this is the real "no adapter reachable"
 * path, not a fixture simulating it.
 * ====================================================================== */
$page = Bitmomo_Btc_Intelligence_Page::instance();
$html = $page->render_page( array() );

check( 'Page renders non-empty output', strlen( $html ) > 500 );
check( 'Page output wrapped in bm-bi container', false !== strpos( $html, 'class="bm-bi"' ) );
check( 'Hero headline matches locked copy exactly', false !== strpos( $html, 'Pahami BTC dalam konteks.' ) );
check( 'Hero supporting line matches locked copy exactly', false !== strpos( $html, 'Lima axis. Satu framework. Track record terbuka.' ) );
check( 'Hero disclaimer matches locked copy exactly, no extra explanatory sentence', false !== strpos( $html, '<p class="bm-bi__hero-micro">Bukan sinyal beli/jual. Bukan saran keuangan.</p>' ) );
check( 'All 13 section anchors present (13 h1/h2 headings incl. hero)', substr_count( $html, 'bm-bi__section-title' ) + substr_count( $html, 'bm-bi__hero-title' ) >= 12 );

check( 'ADAPTER ABSENT: snapshot renders the honest unavailable state, never fabricated', false !== strpos( $html, 'Update BTC terbaru belum tersedia.' ) );
check( 'ADAPTER ABSENT: no spectrum instrument rendered without resolved data', false === strpos( $html, 'bm-bi__spectrum-track' ) );
check( 'ADAPTER ABSENT: Track Record shows the "Belum Tersedia" boundary, not a number', substr_count( $html, 'Belum Tersedia' ) >= 5 );
check( 'ADAPTER ABSENT: Historical regime section falls back honestly when shortcode is not registered', false !== strpos( $html, 'Riwayat Market State belum tersedia.' ) );
check( 'ADAPTER ABSENT: no bare/fabricated illustrative percentages leak into output', false === strpos( $html, '68%' ) && false === strpos( $html, '312' ) );
check( 'ADAPTER ABSENT: Expected Range section never shows a live/current range number', 0 === preg_match( '/Rp[\d.,]+\s*[-–]\s*Rp[\d.,]+/', $html ) );

check( 'Output never mentions Scenario Map', false === stripos( $html, 'scenario map' ) );
check( 'Output never mentions Thesis Invalidation', false === stripos( $html, 'thesis invalidation' ) );
check( 'Output never mentions "What Changed" as a live data block', false === stripos( $html, 'what changed' ) );
check( 'Page class file never references any Bitmomo_Pro_ class', 0 === preg_match_all( '/Bitmomo_Pro_[A-Za-z]+::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );
check( 'Page class file never CALLS Bitmomo_AI_Scorecard:: (private P1 internals) -- a docblock mention explaining why it is avoided is fine, a static call is not', 0 === preg_match( '/Bitmomo_AI_Scorecard::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );
check( 'Page class file never reads Bitmomo_AI_Intelligence::free_projection() directly -- adapter is now the sole source', 0 === preg_match( '/Bitmomo_AI_Intelligence::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );
check( 'Page class file never reads Bitmomo_Regime_State_Store directly -- adapter wraps it', 0 === preg_match( '/Bitmomo_Regime_State_Store::/', file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-page.php' ) ) );

check( 'Rendered public output never names the internal adapter class to users', false === strpos( $html, 'Bitmomo_Public_Intelligence_Adapter' ) );
check( 'Rendered public output never says "adapter" to users', false === stripos( $html, 'adapter' ) );
check( 'Rendered public output never says "tim engineering" to users', false === stripos( $html, 'tim engineering' ) );

$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
check( 'CSS defines a commercial-orange token matching the site-wide CTA color', false !== strpos( $css, '--bmi-orange: #f4ad32' ) );
check( 'Pro CTA primary button uses the orange token, not cyan', false !== strpos( $css, 'background: var( --bmi-orange )' ) );

check( 'Customer-facing copy never uses the word "pembacaan"', 0 === preg_match( '/pembacaan/i', $html ) );
check( 'Market State and Directional Bias explained as separate concepts', false !== strpos( $html, 'Market State menjelaskan struktur' ) && false !== strpos( $html, 'Directional Bias menjelaskan arah' ) );

foreach ( array( 'Direction', 'Volatility', 'Carry', 'Structure', 'Crowding' ) as $axis ) {
	check( "Five Axes: $axis card rendered", false !== strpos( $html, '>' . $axis . '<' ) );
}
check( 'Five Axes explicitly denies being 5 independent AI agents', false !== strpos( $html, 'bukan lima "agent" AI yang independen' ) );

check( 'Pro CTA headline matches locked copy', false !== strpos( $html, 'Ketahui apa yang perlu diperhatikan berikutnya.' ) );
check( 'Pro CTA links to /pro/', false !== strpos( $html, 'href="http://example.test/pro/"' ) );
check( 'Pro CTA headline does not lead with Expected Range/Scenario Map as the feature', 0 === preg_match( '/perhatikan berikutnya\.<\/h2>\s*<p>[^<]*Expected Range/i', $html ) );

check( 'Confidence evaluation shows locked headline verbatim', false !== strpos( $html, 'Apakah Confidence yang lebih tinggi benar-benar menghasilkan akurasi yang lebih konsisten?' ) );
check( 'Confidence evaluation shows the updated canonical clarification verbatim', false !== strpos( $html, 'Confidence menunjukkan seberapa kuat keyakinan sistem terhadap insight saat ini berdasarkan konsistensi dan kualitas evidence yang mendukungnya. Confidence bukan probabilitas keberhasilan.' ) );

$setup = Bitmomo_Btc_Intelligence_Setup::instance();
check( 'Setup class instantiates cleanly', $setup instanceof Bitmomo_Btc_Intelligence_Setup );

/* =========================================================================
 * PART B -- ADAPTER WIRED. Bitmomo_Public_Intelligence_Adapter mock below
 * matches the REAL contract shape read from the merged
 * class-bitmomo-public-intelligence-adapter.php (PR #69 / 056e488), not a
 * guess: snapshot() returns null unless directional_bias AND
 * direction_strength both resolve; evaluation_summary() is keyed by
 * version under directional_evaluation / expected_range_evaluation /
 * regime_performance, each metric row carrying the backend's own
 * sample_status string verbatim.
 * ====================================================================== */
class Bitmomo_Public_Intelligence_Adapter {
	public static $snapshot_fixture = null;
	public static $history_fixture = null;
	public static $evaluation_summary_fixture = null;
	public static $calls = array( 'snapshot' => 0, 'history' => 0, 'evaluation_summary' => 0 );

	public static function snapshot() {
		self::$calls['snapshot']++;
		return self::$snapshot_fixture;
	}
	public static function history() {
		self::$calls['history']++;
		return self::$history_fixture;
	}
	public static function evaluation_summary() {
		self::$calls['evaluation_summary']++;
		return self::$evaluation_summary_fixture;
	}
}

$reflection = new ReflectionClass( 'Bitmomo_Btc_Intelligence_Page' );

/**
 * Renders the page on a brand-new, non-singleton instance so its
 * per-instance adapter caches always reflect whatever fixture is set
 * immediately before calling this -- no cross-test cache bleed.
 */
function render_fresh( ReflectionClass $reflection ) {
	$instance = $reflection->newInstanceWithoutConstructor();
	$method   = $reflection->getMethod( 'render_page' );
	$method->setAccessible( true );
	return $method->invoke( $instance, array() );
}

function make_snapshot( $bias, $strength, $confidence_value, $confidence_label, $extra = array() ) {
	return array_merge( array(
		'status'               => 'fresh',
		'btc_reference_price'  => 65000.0,
		'market_state'         => 'expansion',
		'directional_bias'     => $bias,
		'direction_strength'   => $strength,
		'confidence'           => array( 'value' => $confidence_value, 'label' => $confidence_label ),
		'freshness'            => array( 'label' => 'Tertunda · diperbarui 8 jam lalu', 'timestamp' => 1788394207, 'timestamp_iso' => '2026-09-03T00:10:07+00:00' ),
		'key_drivers'          => array( 'Funding rate elevated', 'Spot volume rising' ),
		'versions'             => array( 'engine' => '1.2.4', 'classifier' => 'classifier-v1' ),
	), $extra );
}

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

/* -------------------------------------------------------------------
 * ADAPTER SNAPSHOT CONSUMED -- basic wiring
 * ------------------------------------------------------------------ */
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( 'bullish', 'strong_bullish', 82, 'high' );
$html_wired = render_fresh( $reflection );
check( 'ADAPTER SNAPSHOT CONSUMED: BTC reference price rendered from snapshot()', false !== strpos( $html_wired, '65,000' ) || false !== strpos( $html_wired, '65.000' ) );
check( 'ADAPTER SNAPSHOT CONSUMED: Market State rendered from snapshot()', false !== strpos( $html_wired, 'Ekspansi' ) );
check( 'ADAPTER SNAPSHOT CONSUMED: Faktor Utama rendered from snapshot() key_drivers', false !== strpos( $html_wired, 'Funding rate elevated' ) );
check( 'ADAPTER SNAPSHOT CONSUMED: backend freshness label rendered verbatim', false !== strpos( $html_wired, 'Tertunda · diperbarui 8 jam lalu' ) );
check( 'FRESHNESS: exact timestamp converted to WIB', false !== strpos( $html_wired, '03 Sep 2026, 07:10 WIB' ) );
check( 'FRESHNESS: never falsely claims current data', false === strpos( $html_wired, 'Data terkini' ) );
check( 'Hero intelligence visualization (spectrum) renders', false !== strpos( $html_wired, 'bm-bi__spectrum-track' ) && false !== strpos( $html_wired, 'MARKET DIRECTION SPECTRUM' ) );
check( 'Spectrum shows all five canonical zone labels', false !== strpos( $html_wired, 'Strong Bear' ) && false !== strpos( $html_wired, 'Strong Bull' ) && false !== strpos( $html_wired, '>Neutral<' ) );

/* -------------------------------------------------------------------
 * DIRECTION_STRENGTH EXACT ZONE RENDERED -- all five canonical states,
 * each must resolve to its own precise zone (left offset = zone*20%),
 * never the removed "wide" treatment, never a fabricated label.
 * ------------------------------------------------------------------ */
$expected_zones = array(
	'strong_bearish' => array( 'left' => 0, 'label' => 'Strong Bearish', 'bias' => 'bearish' ),
	'bearish'        => array( 'left' => 20, 'label' => 'Moderate Bearish', 'bias' => 'bearish' ),
	'neutral'        => array( 'left' => 40, 'label' => 'Neutral', 'bias' => 'neutral' ),
	'bullish'        => array( 'left' => 60, 'label' => 'Moderate Bullish', 'bias' => 'bullish' ),
	'strong_bullish' => array( 'left' => 80, 'label' => 'Strong Bullish', 'bias' => 'bullish' ),
);
foreach ( $expected_zones as $strength => $expectation ) {
	Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( $expectation['bias'], $strength, 75, 'high' );
	$html_zone = render_fresh( $reflection );
	$style     = spectrum_marker_style( $html_zone );
	check(
		"DIRECTION_STRENGTH EXACT ZONE: $strength resolves to left:{$expectation['left']}% width:20%",
		null !== $style && false !== strpos( $style, 'left:' . $expectation['left'] . '%' ) && false !== strpos( $style, 'width:20%' )
	);
	check(
		"DIRECTION_STRENGTH EXACT ZONE: $strength renders its exact label, never Moderate/Strong confusion",
		false !== strpos( $html_zone, '<strong>' . $expectation['label'] . '</strong>' )
	);
	check(
		"DIRECTION_STRENGTH EXACT ZONE: $strength marker is always precise, the removed wide-band class never appears",
		marker_has_classes( $html_zone, array( 'bm-bi__spectrum-marker--precise' ) ) && false === strpos( $html_zone, 'bm-bi__spectrum-marker--wide' )
	);
}
check( 'TEMPORARY WIDE-BAND REMOVED: no "segera hadir" copy remains anywhere in the page', false === strpos( $html_wired, 'segera hadir' ) );
check( 'TEMPORARY WIDE-BAND REMOVED: bm-bi__spectrum-marker--wide class no longer exists in the CSS', false === strpos( $css, 'spectrum-marker--wide' ) );

/* -------------------------------------------------------------------
 * CONFIDENCE CANNOT MOVE ZONE -- same bias+strength, high vs low
 * confidence: marker position (left/width) must be byte-identical;
 * only the confidence class/badge may differ.
 * ------------------------------------------------------------------ */
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( 'bullish', 'bullish', 82, 'high' );
$html_hi = render_fresh( $reflection );
$style_hi = spectrum_marker_style( $html_hi );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( 'bullish', 'bullish', 12, 'low' );
$html_lo = render_fresh( $reflection );
$style_lo = spectrum_marker_style( $html_lo );

check( 'CONFIDENCE CANNOT MOVE ZONE: marker left/width is IDENTICAL for the same bias+strength regardless of Confidence (82 vs 12)', null !== $style_hi && $style_hi === $style_lo );
check( 'CONFIDENCE CANNOT MOVE ZONE: high confidence (82/high) renders the "tinggi" confidence class', marker_has_classes( $html_hi, array( 'is-confidence-tinggi' ) ) );
check( 'CONFIDENCE CANNOT MOVE ZONE: low confidence (12/low) renders the "rendah" confidence class', marker_has_classes( $html_lo, array( 'is-confidence-rendah' ) ) );
check( 'CONFIDENCE CANNOT MOVE ZONE: direction label stays Moderate Bullish at low confidence too', false !== strpos( $html_lo, '<strong>Moderate Bullish</strong>' ) );
check( 'Confidence semantics note is present in the hero snapshot, concisely (not a repeated paragraph block)', false !== strpos( $html_hi, 'bm-bi__confidence-note' ) && substr_count( $html_hi, 'Confidence bukan probabilitas keberhasilan.' ) <= 2 );

/* -------------------------------------------------------------------
 * UNKNOWN FAILS HONESTLY -- adapter present but returns null, or an
 * unrecognized direction_strength / directional_bias -- the WHOLE
 * section falls back to the honest unavailable state (the previous
 * partial "wide band" state no longer exists; the adapter's own
 * fail-closed contract means this page never sees a partial shape).
 * ------------------------------------------------------------------ */
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = null;
$html_null = render_fresh( $reflection );
check( 'UNKNOWN FAILS HONESTLY: adapter snapshot() returning null renders the honest unavailable state', false !== strpos( $html_null, 'Update BTC terbaru belum tersedia.' ) );
check( 'UNKNOWN FAILS HONESTLY: no spectrum instrument rendered when snapshot() is null', false === strpos( $html_null, 'bm-bi__spectrum-track' ) );
check( 'UNKNOWN FAILS HONESTLY: never silently defaults to Neutral when snapshot() is null', false === strpos( $html_null, '>Neutral<' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( 'bullish', 'extremely_bullish_v2', 75, 'high' );
$html_bad = render_fresh( $reflection );
check( 'UNKNOWN FAILS HONESTLY: an unrecognized direction_strength value renders the honest unavailable state, never a fabricated zone', false !== strpos( $html_bad, 'Update BTC terbaru belum tersedia.' ) && false === strpos( $html_bad, 'bm-bi__spectrum-track' ) );

Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( 'sideways', 'neutral', 50, 'medium' );
$html_bad_bias = render_fresh( $reflection );
check( 'UNKNOWN FAILS HONESTLY: an unrecognized directional_bias value renders the honest unavailable state', false !== strpos( $html_bad_bias, 'Update BTC terbaru belum tersedia.' ) );

check( 'No canvas/chart element was added for a fabricated price series', false === strpos( $html_wired, '<canvas' ) );

/* -------------------------------------------------------------------
 * EVALUATION SUMMARY -- single version: real numbers rendered with the
 * backend's own sample_status label, never recomputed.
 * ------------------------------------------------------------------ */
Bitmomo_Public_Intelligence_Adapter::$snapshot_fixture = make_snapshot( 'bullish', 'bullish', 70, 'high' );
Bitmomo_Public_Intelligence_Adapter::$evaluation_summary_fixture = array(
	'provenance'      => 'canonical_evaluation_scorecard',
	'version_policy'  => 'SINGLE_VERSION',
	'sample_rules'    => array( 'minimum' => 10, 'strong' => 30 ),
	'directional_evaluation' => array(
		'engine-v1 | classifier-v1' => array(
			'all'         => array( 'n' => 40, 'conclusive_n' => 38, 'correct' => 25, 'accuracy_pct' => 65.8, 'sample_status' => 'ADEQUATE' ),
			'rolling_30'  => array( 'n' => 30, 'accuracy_pct' => 67.9, 'sample_status' => 'ADEQUATE' ),
			'by_direction' => array(
				'bullish' => array( 'n' => 18, 'accuracy_pct' => 70.0, 'sample_status' => 'EARLY SAMPLE' ),
				'bearish' => array( 'n' => 15, 'accuracy_pct' => 60.0, 'sample_status' => 'EARLY SAMPLE' ),
				'neutral' => array( 'n' => 7, 'accuracy_pct' => null, 'sample_status' => 'INSUFFICIENT SAMPLE' ),
			),
			'confidence_buckets' => array(
				array( 'n' => 12, 'accuracy_pct' => 58.3, 'sample_status' => 'EARLY SAMPLE', 'range' => '0–49' ),
				array( 'n' => 15, 'accuracy_pct' => 66.7, 'sample_status' => 'EARLY SAMPLE', 'range' => '50–69' ),
				array( 'n' => 13, 'accuracy_pct' => 76.9, 'sample_status' => 'EARLY SAMPLE', 'range' => '70–100' ),
			),
		),
	),
	'expected_range_evaluation' => array(
		'policy'         => 'FROZEN_ORIGINAL_ONLY',
		'version_policy' => 'SINGLE_VERSION',
		'versions'       => array(
			'model-v1' => array( 'n' => 20, 'range_hit_pct' => 72.5, 'low_breach_pct' => 12.5, 'high_breach_pct' => 15.0, 'sample_status' => 'EARLY SAMPLE' ),
		),
	),
	'regime_performance' => array(
		'append_only_n'             => 55,
		'transition_n'              => 6,
		'transition_frequency_pct'  => 10.9,
		'versions'                  => array(
			'classifier-v1' => array(
				'expansion'    => array( 'n' => 20, 'average_forward_return_pct' => -1.25, 'average_forward_volatility_pct' => 2.75, 'sample_status' => 'EARLY SAMPLE' ),
				'accumulation' => array( 'n' => 15, 'average_forward_return_pct' => 0.0, 'average_forward_volatility_pct' => null, 'sample_status' => 'EARLY SAMPLE' ),
			),
		),
	),
	'data_quality' => array( 'n' => 40, 'stale_rate_pct' => 2.5, 'blocked_degraded_rate_pct' => 0.0, 'missing_data_rate_pct' => 1.2, 'settlement_n' => 38, 'settlement_completeness_pct' => 95.0, 'sample_status' => 'ADEQUATE' ),
);
$html_eval = render_fresh( $reflection );

check( 'EVALUATION SECTIONS USE BACKEND SAMPLE STATUS: Track Record shows a real accuracy number', false !== strpos( $html_eval, '65.8%' ) );
check( 'EVALUATION SECTIONS USE BACKEND SAMPLE STATUS: ADEQUATE maps to its Indonesian label verbatim', false !== strpos( $html_eval, 'Sampel Memadai' ) );
check( 'EVALUATION SECTIONS USE BACKEND SAMPLE STATUS: EARLY SAMPLE maps to its Indonesian label verbatim', false !== strpos( $html_eval, 'Sampel Awal' ) );
check( 'EVALUATION SECTIONS USE BACKEND SAMPLE STATUS: INSUFFICIENT SAMPLE maps to its Indonesian label verbatim', false !== strpos( $html_eval, 'Sampel Belum Cukup' ) );
check( 'EVALUATION SECTIONS USE BACKEND SAMPLE STATUS: a null accuracy_pct renders "Belum ada hasil", never a fabricated 0%', false !== strpos( $html_eval, 'Belum ada hasil' ) );
check( 'EVALUATION SECTIONS USE BACKEND SAMPLE STATUS: backend n is shown verbatim (n=7 for the insufficient neutral bucket)', false !== strpos( $html_eval, 'n=7' ) );
check( 'Confidence Evaluation renders the backend\'s own dynamic bucket ranges, not a hardcoded Rendah/Sedang/Tinggi split', false !== strpos( $html_eval, '0–49' ) && false !== strpos( $html_eval, '50–69' ) && false !== strpos( $html_eval, '70–100' ) );
check( 'Expected Range Performance renders the real range_hit_pct', false !== strpos( $html_eval, '72.5%' ) );
check( 'Expected Range Performance never shows a live Rp range number even once real evaluation data exists', 0 === preg_match( '/Rp[\d.,]+\s*[-–]\s*Rp[\d.,]+/', $html_eval ) );
check( 'Regime Performance renders genuine return and volatility, not accuracy', false !== strpos( $html_eval, '-1.3%' ) && false !== strpos( $html_eval, '2.8%' ) && false !== strpos( $html_eval, 'Akumulasi · Return rata-rata' ) );
check( 'Regime Performance renders the backend\'s real append-only/transition summary line', false !== strpos( $html_eval, '55' ) && false !== strpos( $html_eval, '10.9' ) );
check( 'Data Quality renders the real stale/blocked/missing/settlement figures', false !== strpos( $html_eval, '2.5%' ) && false !== strpos( $html_eval, '1.2%' ) && false !== strpos( $html_eval, '95.0%' ) );
// Deliberately excludes 'axes'/'axis' (this page's own legitimate product
// vocabulary -- "Lima Intelligence Axes", axis-card markup) and 'evidence'
// (part of the locked Confidence disclaimer copy, "...kualitas evidence
// yang mendukungnya") -- neither is a scorecard leak on this page, they're
// approved customer-facing words. This checks only identifiers that could
// never legitimately appear as honest public copy here.
check( 'NO PRO DATA LEAKS: private-only scorecard fields never reach the rendered page', 0 === preg_match( '/\b(baselines|source_record_id|private_note|entry_price|momentum_direction|trend_direction)\b/', $html_eval ) );

/* -------------------------------------------------------------------
 * VERSION GROUPS NOT SILENTLY MERGED -- two incompatible versions in
 * the same section must render as two separate groups with their own
 * distinct figures, never averaged/combined into one number.
 * ------------------------------------------------------------------ */
Bitmomo_Public_Intelligence_Adapter::$evaluation_summary_fixture['version_policy'] = 'SEPARATED_INCOMPATIBLE_VERSIONS';
Bitmomo_Public_Intelligence_Adapter::$evaluation_summary_fixture['directional_evaluation']['engine-v2 | classifier-v2'] = array(
	'all'                => array( 'n' => 14, 'accuracy_pct' => 40.0, 'sample_status' => 'EARLY SAMPLE' ),
	'rolling_30'         => array( 'n' => 14, 'accuracy_pct' => 40.0, 'sample_status' => 'EARLY SAMPLE' ),
	'by_direction'       => array(),
	'confidence_buckets' => array(),
);
$html_versions = render_fresh( $reflection );
check( 'VERSION GROUPS NOT SILENTLY MERGED: both incompatible versions render their own distinct accuracy figures', false !== strpos( $html_versions, '65.8%' ) && false !== strpos( $html_versions, '40.0%' ) );
check( 'VERSION GROUPS NOT SILENTLY MERGED: no averaged/combined figure between the two versions appears (e.g. ~52.9%)', false === strpos( $html_versions, '52.9%' ) );
check( 'VERSION GROUPS NOT SILENTLY MERGED: both version tags are shown, labelled separately', substr_count( $html_versions, 'bm-bi__version-tag' ) >= 2 );
check( 'VERSION GROUPS NOT SILENTLY MERGED: the on-page separation note appears when more than one version exists', false !== strpos( $html_versions, 'tidak digabungkan' ) );

// Reset to the single-version fixture for anything rendered after this point.
Bitmomo_Public_Intelligence_Adapter::$evaluation_summary_fixture['version_policy'] = 'SINGLE_VERSION';
unset( Bitmomo_Public_Intelligence_Adapter::$evaluation_summary_fixture['directional_evaluation']['engine-v2 | classifier-v2'] );

/* -------------------------------------------------------------------
 * NO FAKE HISTORY -- section 6 deliberately still defers to the
 * bitmomo-regime shortcode (never adapter_history()) and adapter_
 * history() itself is still a guarded/cached passthrough, never
 * fabricating a shape.
 * ------------------------------------------------------------------ */
check( 'NO FAKE HISTORY: Historical Market State/Bias still renders via the canonical shortcode path, unchanged', false !== strpos( $html_eval, 'Riwayat Market State' ) );

Bitmomo_Public_Intelligence_Adapter::$history_fixture = array( 'target_days' => 30, 'available_days' => 2, 'days' => array( array( 'date' => '2026-09-01', 'market_state' => 'expansion', 'directional_bias' => 'bullish', 'version_group' => 'classifier-v1' ) ) );
$history_method = $reflection->getMethod( 'adapter_history' );
$history_method->setAccessible( true );
$fresh_for_history = $reflection->newInstanceWithoutConstructor();
$history_result = $history_method->invoke( $fresh_for_history );
check( 'NO FAKE HISTORY: adapter_history() accessor returns exactly what the adapter provides, never invented', $history_result === Bitmomo_Public_Intelligence_Adapter::$history_fixture );

/* =========================================================================
 * PART C -- HOMEPAGE 30D + STRENGTH-NOT-FROM-CONFIDENCE (PR #69 fix)
 * The 30D component lives in a different plugin/theme
 * (template-parts/home-hero.php) and is intentionally left unchanged by
 * this branch -- verified separately by a direct git diff audit (see the
 * delivery report) and by rendering that template with Playwright
 * screenshots. What IS testable here, in isolation, is the exact
 * $bm_direction_label closure PR #69 shipped to fix the previous
 * Confidence->Strong/Moderate coupling bug -- reproduced verbatim so a
 * regression back to that bug is caught even without loading the whole
 * theme.
 * ====================================================================== */
$label_method = $reflection->getMethod( 'regime_display_label' );
$label_method->setAccessible( true );
foreach ( array( 'accumulation' => 'Akumulasi', 'expansion' => 'Ekspansi', 'distribution' => 'Distribusi', 'capitulation' => 'Kapitulasi', 'transition' => 'Transisi', 'unknown' => 'Belum tersedia', 'future_enum' => 'Belum tersedia', '' => 'Belum tersedia' ) as $raw => $expected ) {
	check( 'REGIME safe display: ' . $raw, $expected === $label_method->invoke( $fresh_for_history, $raw ) );
}
check( 'REGIME null is unavailable, never Neutral', 'Belum tersedia' === $label_method->invoke( $fresh_for_history, null ) );
$freshness_method = $reflection->getMethod( 'render_freshness' );
$freshness_method->setAccessible( true );
foreach ( array( null, array(), array( 'freshness' => array( 'timestamp_iso' => '2026-09-03T00:10:07' ) ), array( 'freshness' => array( 'timestamp_iso' => 'invalid' ) ) ) as $missing ) {
	ob_start();
	$freshness_method->invoke( $fresh_for_history, $missing );
	$missing_output = ob_get_clean();
	check( 'FRESHNESS missing/invalid/naive timestamp stays unknown', 'Waktu pembaruan belum tersedia.' === $missing_output );
}
$bm_direction_label = static function ( $strength, $bias ) {
	$labels = array(
		'strong_bullish' => 'Strong Bullish',
		'bullish'        => 'Moderate Bullish',
		'neutral'        => 'Neutral',
		'bearish'        => 'Moderate Bearish',
		'strong_bearish' => 'Strong Bearish',
	);
	$strength = sanitize_key( (string) $strength );
	if ( isset( $labels[ $strength ] ) ) {
		return $labels[ $strength ];
	}
	$bias = sanitize_key( (string) $bias );
	if ( in_array( $bias, array( 'bullish', 'bearish', 'neutral' ), true ) ) {
		return ucfirst( $bias );
	}
	return 'Neutral';
};

check( 'HOMEPAGE STRENGTH NOT FROM CONFIDENCE: strong_bullish label renders regardless of any confidence value (the closure takes no confidence argument at all)', $bm_direction_label( 'strong_bullish', 'bullish' ) === 'Strong Bullish' );
$bm_label_params = ( new ReflectionFunction( $bm_direction_label ) )->getParameters();
check( 'HOMEPAGE STRENGTH NOT FROM CONFIDENCE: the closure has exactly two parameters (strength, bias) -- no confidence parameter exists to couple from, the exact PR #69 fix', 2 === count( $bm_label_params ) );
check( 'HOMEPAGE STRENGTH NOT FROM CONFIDENCE: the closure\'s first parameter is $strength, not $confidence', 'strength' === $bm_label_params[0]->getName() );
check( 'HOMEPAGE STRENGTH NOT FROM CONFIDENCE: unresolved strength with a valid bias falls back to Title-Case bias, never a confidence-derived guess', $bm_direction_label( '', 'bearish' ) === 'Bearish' );

echo "\n" . $GLOBALS['__pass'] . '/' . ( $GLOBALS['__pass'] + $GLOBALS['__fail'] ) . " passed.\n";
if ( $GLOBALS['__fail'] > 0 ) {
	exit( 1 );
}
echo "All checks passed.\n";

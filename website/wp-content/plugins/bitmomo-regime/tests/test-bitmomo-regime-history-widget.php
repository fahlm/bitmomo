<?php
/**
 * Standalone executable test for Bitmomo_Regime_Shortcodes::render_history().
 *
 * WHAT THIS PINS, AND WHY
 * -----------------------
 * This surface has two owners and neither can see the other:
 *
 *   - The CHART (.bmreg-trend-*) is built client-side by the theme's
 *     bitmomo-frontend.js, from the JSON in `.bmreg-history-data`. This class
 *     only emits that JSON. Drop the block and a working chart dies silently,
 *     with nothing in PHP to show why.
 *
 *   - The MODAL (.bmreg-history-modal) is server-rendered here, but its CSS
 *     and its delegated click handler live in style() and history_script()
 *     and act purely on class names and attributes. A rename on either side
 *     breaks it with no error anywhere.
 *
 * Both contracts are invisible to PHP and invisible to the browser until a
 * human clicks. That is what these assertions are for. An earlier draft of
 * this file asserted a server-rendered bar chart that never existed on the
 * live site — the bars, the axis and the per-bar payload were all my own
 * invention — so the checks below deliberately describe only what the two
 * real consumers read.
 *
 * Run: php tests/test-bitmomo-regime-history-widget.php
 */

require __DIR__ . '/wp-stubs.php';

// ---------------------------------------------------------------- WP stubs
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $v )  { return (string) $v; }
function esc_html__( $v, $d = null ) { return $v; }
function esc_attr__( $v, $d = null ) { return $v; }
function __( $v, $d = null ) { return $v; }
function esc_html_e( $v, $d = null ) { echo esc_html( $v ); }
function esc_attr_e( $v, $d = null ) { echo esc_attr( $v ); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function has_shortcode() { return false; }

require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-history.php';
require __DIR__ . '/../includes/class-bitmomo-regime-shortcodes.php';

// render_history() only ever calls get_recent().
class Bitmomo_Regime_State_Store {
	public static $records = array();
	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	public function get_recent( $limit = 30 ) { return array_slice( self::$records, 0, $limit ); }
}

$failures = 0;
function check( $label, $condition ) {
	global $failures;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
	if ( ! $condition ) { $failures++; }
}

/** Newest-first, as Bitmomo_Regime_State_Store::get_recent() returns. */
function bmreg_fixture( $days ) {
	$regimes = array( 'accumulation', 'expansion', 'distribution', 'capitulation', 'transition' );
	$biases  = array( 'bullish', 'bearish', 'neutral' );
	$out     = array();
	for ( $i = 0; $i < $days; $i++ ) {
		$out[] = array(
			'as_of'              => gmdate( 'Y-m-d', strtotime( '2026-09-09 -' . $i . ' day' ) ),
			'edition'            => 'us_session',
			'regime'             => $regimes[ $i % 5 ],
			'directional_bias'   => $biases[ $i % 3 ],
			'regime_confidence'  => 20 + ( ( $i * 7 ) % 75 ),
			// Diagnostic-only fields that must never reach the frontend.
			'evidence'           => array( 'internal only' ),
			'classifier_version' => 'regime-v1',
			'input_hash'         => 'deadbeef',
		);
	}
	return $out;
}

function bmreg_render( $atts = array() ) {
	return Bitmomo_Regime_Shortcodes::instance()->render_history( $atts );
}

// ==========================================================================
// 1. EMPTY STATE — never fabricate, and ship nothing that acts on nothing
// ==========================================================================
Bitmomo_Regime_State_Store::$records = array();
$html = bmreg_render();

check( 'EMPTY: shows the honest empty card', false !== strpos( $html, 'bmreg-empty' ) );
check( 'EMPTY: emits no data block, so the theme chart stays absent rather than empty',
	false === strpos( $html, 'bmreg-history-data' ) );
check( 'EMPTY: renders no modal', false === strpos( $html, 'class="bmreg-history-modal"' ) );
check( 'EMPTY: renders no "see all" trigger', false === strpos( $html, 'class="bmreg-history-open"' ) );
check( 'EMPTY: ships no click handler when there is nothing to click',
	false === strpos( $html, '<script>' ) );

// ==========================================================================
// 2. THE CHART CONTRACT — this class feeds bitmomo-frontend.js, nothing more
// ==========================================================================
Bitmomo_Regime_State_Store::$records = bmreg_fixture( 12 );
$html = bmreg_render( array( 'days' => 7 ) );

check( 'CHART: the JSON data block the theme script reads is present',
	false !== strpos( $html, '<script type="application/json" class="bmreg-history-data">' ) );
// The recovered stylesheet still carries .bmreg-history-bar* rules for a
// server chart that no longer exists, so this has to look at the MARKUP
// rather than the whole output -- otherwise it matches the CSS and fails
// for the wrong reason.
$markup_only = preg_replace( '#<style>.*?</style>|<script>.*?</script>#s', '', $html );
check( 'CHART: no server-rendered bars in the markup — the chart belongs to the theme script',
	false === strpos( $markup_only, 'bmreg-history-bar' ) );

preg_match( '#class="bmreg-history-data">(.*?)</script>#s', $html, $m );
$payload = json_decode( html_entity_decode( $m[1] ?? '', ENT_QUOTES, 'UTF-8' ), true );

check( 'CHART: payload is a valid JSON array', is_array( $payload ) );
check( 'CHART: payload carries every collected day, not the requested window',
	is_array( $payload ) && 12 === count( $payload ) );

$row  = is_array( $payload ) && isset( $payload[0] ) ? $payload[0] : array();
$read = array( 'date', 'regime', 'regime_label_id', 'directional_bias', 'regime_confidence' );
foreach ( $read as $key ) {
	check( "CHART: payload carries \"$key\" (read by bitmomo-frontend.js)", array_key_exists( $key, $row ) );
}
check( 'CHART: payload leaks no diagnostic-only field',
	! isset( $row['evidence'] ) && ! isset( $row['input_hash'] ) && ! isset( $row['classifier_version'] ) );
check( 'INTEGRITY: no diagnostic value appears anywhere in the output',
	false === strpos( $html, 'deadbeef' ) && false === strpos( $html, 'internal only' ) );

// ==========================================================================
// 3. THE MODAL CONTRACT — every selector the recovered handler uses
// ==========================================================================
preg_match( '/id="(bmreg-history-modal-\d+)"/', $html, $modal_match );
$modal_id = $modal_match[1] ?? '';

check( 'MODAL: trigger carries .bmreg-history-open', false !== strpos( $html, 'class="bmreg-history-open"' ) );
check( 'MODAL: trigger aria-controls points at the modal it opens',
	'' !== $modal_id && false !== strpos( $html, 'aria-controls="' . $modal_id . '"' ) );
check( 'MODAL: trigger starts aria-expanded="false"', false !== strpos( $html, 'aria-expanded="false"' ) );
check( 'MODAL: modal starts aria-hidden="true"',
	false !== strpos( $html, 'class="bmreg-history-modal" id="' . $modal_id . '" aria-hidden="true"' ) );
check( 'MODAL: backdrop is a close target ([data-bmreg-close])',
	false !== strpos( $html, 'class="bmreg-history-backdrop" data-bmreg-close' ) );
check( 'MODAL: close button is a close target ([data-bmreg-close])',
	false !== strpos( $html, 'class="bmreg-history-close" data-bmreg-close' ) );
check( 'MODAL: dialog is focusable, since the handler calls d.focus()',
	false !== strpos( $html, 'class="bmreg-history-dialog"' ) && false !== strpos( $html, 'tabindex="-1"' ) );
check( 'MODAL: lists every collected day', 12 === substr_count( $html, '<li>' ) );
check( 'MODAL: reports the true collected count', false !== strpos( $html, '12 dari 30 hari tercatat' ) );
check( 'MODAL: honest "collected so far" note is shown', false !== strpos( $html, 'Menampilkan 12 dari 30 hari' ) );
check( 'MODAL: Pro link is present and points at /pro/',
	false !== strpos( $html, 'bmreg-history-pro-link' ) && false !== strpos( $html, 'https://example.test/pro/' ) );

// ==========================================================================
// 4. UNKNOWN / OUT-OF-RANGE INPUT is degraded honestly, never guessed
// ==========================================================================
Bitmomo_Regime_State_Store::$records = array(
	array( 'as_of' => '2026-09-09 11:59:59', 'edition' => 'us_session', 'regime' => 'transition', 'directional_bias' => '',         'regime_confidence' => 250 ),
	array( 'as_of' => '2026-09-08 11:59:59', 'edition' => 'us_session', 'regime' => 'transition', 'directional_bias' => 'sideways', 'regime_confidence' => -40 ),
);
$html = bmreg_render();

check( 'DEGRADE: an unrecognised bias becomes .bmreg-bias-unknown, never a guessed direction',
	2 === substr_count( $html, 'bmreg-bias-unknown' ) );
check( 'DEGRADE: confidence above 100 is clamped, never printed raw', false === strpos( $html, '250%' ) );
check( 'DEGRADE: negative confidence is clamped to 0%', false !== strpos( $html, '>0%<' ) );
check( 'DEGRADE: a stored timestamp renders as a date, not a raw datetime',
	false !== strpos( $html, '09 Sep 2026' ) && false === strpos( $html, '2026-09-09 11:59:59<' ) );

// ==========================================================================
// 5. MULTIPLE INSTANCES — assets once, modal ids unique
// ==========================================================================
Bitmomo_Regime_State_Store::$records = bmreg_fixture( 5 );
$reflection = new ReflectionClass( 'Bitmomo_Regime_Shortcodes' );
foreach ( array( 'style_printed', 'history_script_printed' ) as $flag ) {
	$property = $reflection->getProperty( $flag );
	$property->setAccessible( true );
	$property->setValue( Bitmomo_Regime_Shortcodes::instance(), false );
}
$first  = bmreg_render();
$second = bmreg_render();

check( 'INSTANCES: <style> is printed only for the first instance',
	1 === substr_count( $first, '<style>' ) && 0 === substr_count( $second, '<style>' ) );
check( 'INSTANCES: <script> is printed only for the first instance',
	1 === substr_count( $first, '<script>' ) && 0 === substr_count( $second, '<script>' ) );
preg_match( '/id="(bmreg-history-modal-\d+)"/', $first, $a );
preg_match( '/id="(bmreg-history-modal-\d+)"/', $second, $b );
check( 'INSTANCES: each instance gets a unique modal id, so aria-controls stays unambiguous',
	! empty( $a[1] ) && ! empty( $b[1] ) && $a[1] !== $b[1] );

printf( "\n%s\n", $failures ? "$failures check(s) FAILED." : 'All checks passed.' );
exit( $failures ? 1 : 0 );

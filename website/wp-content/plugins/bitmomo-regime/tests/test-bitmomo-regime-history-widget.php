<?php
/**
 * Standalone executable test for the recovered 30-day history widget —
 * Bitmomo_Regime_Shortcodes::render_history().
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * The chart + "Lihat semua riwayat" modal were lost from source control and
 * survived only as CSS and a delegated click handler on the live staging
 * server; the PHP that emitted the markup they act on had been replaced with
 * a bare JSON payload. The markup was reconstructed against those two
 * surviving artefacts.
 *
 * That makes the markup/JS/CSS contract the fragile part — nothing else
 * enforces that a class name in the stylesheet, a selector in the handler
 * and an attribute in the markup still agree. Every assertion below pins one
 * clause of that contract, so a future edit to any of the three fails here
 * instead of silently producing a chart nobody can click.
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
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
	}
	return $out;
}

require __DIR__ . '/../includes/class-bitmomo-regime-taxonomy.php';
require __DIR__ . '/../includes/class-bitmomo-regime-history.php';
require __DIR__ . '/../includes/class-bitmomo-regime-shortcodes.php';

// A stand-in state store: render_history() only ever calls get_recent().
class Bitmomo_Regime_State_Store {
	public static $records = array();
	private static $instance = null;
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	public function get_recent( $limit = 30 ) { return array_slice( self::$records, 0, $limit ); }
}

// ---------------------------------------------------------------- harness
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
			'as_of'             => gmdate( 'Y-m-d', strtotime( '2026-09-09 -' . $i . ' day' ) ),
			'edition'           => 'us_session',
			'regime'            => $regimes[ $i % 5 ],
			'directional_bias'  => $biases[ $i % 3 ],
			'regime_confidence' => 20 + ( ( $i * 7 ) % 75 ),
			// Diagnostic-only fields that must never reach the frontend.
			'evidence'          => array( 'internal only' ),
			'classifier_version' => 'regime-v1',
			'input_hash'        => 'deadbeef',
		);
	}
	return $out;
}

// ==========================================================================
// 1. EMPTY STATE — never fabricate, never render a chart with no data
// ==========================================================================
Bitmomo_Regime_State_Store::$records = array();
$html = Bitmomo_Regime_Shortcodes::instance()->render_history( array() );
check( 'EMPTY: shows the honest empty card', false !== strpos( $html, 'bmreg-empty' ) );
check( 'EMPTY: renders no chart', false === strpos( $html, 'class="bmreg-history-bars"' ) );
check( 'EMPTY: renders no modal', false === strpos( $html, 'class="bmreg-history-modal"' ) );
check( 'EMPTY: renders no "see all" trigger', false === strpos( $html, 'class="bmreg-history-open"' ) );
check( 'EMPTY: ships no click handler when there is nothing to click', false === strpos( $html, '<script>' ) );

// ==========================================================================
// 2. PARTIAL HISTORY — 12 of 30 days collected
// ==========================================================================
Bitmomo_Regime_State_Store::$records = bmreg_fixture( 12 );
$html = Bitmomo_Regime_Shortcodes::instance()->render_history( array( 'days' => 7 ) );

check( 'PARTIAL: honest "collected so far" note is shown', false !== strpos( $html, 'Menampilkan 12 dari 30 hari' ) );
check( 'PARTIAL: chart honours days="7" — exactly 7 bars', 7 === substr_count( $html, 'class="bmreg-history-bar ' ) );
check( 'PARTIAL: modal lists ALL 12 collected days, not just the charted 7', 12 === substr_count( $html, '<li>' ) );
check( 'PARTIAL: modal note reports the true collected count', false !== strpos( $html, '12 dari 30 hari tercatat' ) );

// ==========================================================================
// 3. MARKUP <-> JS CONTRACT — every selector the recovered handler uses
// ==========================================================================
preg_match( '/id="(bmreg-history-modal-\d+)"/', $html, $modal_match );
$modal_id = $modal_match[1] ?? '';

check( 'JS CONTRACT: trigger carries .bmreg-history-open', false !== strpos( $html, 'class="bmreg-history-open"' ) );
check( 'JS CONTRACT: trigger aria-controls points at the modal id it opens',
	'' !== $modal_id && false !== strpos( $html, 'aria-controls="' . $modal_id . '"' ) );
check( 'JS CONTRACT: trigger starts aria-expanded="false"', false !== strpos( $html, 'aria-expanded="false"' ) );
check( 'JS CONTRACT: modal starts aria-hidden="true"', false !== strpos( $html, 'class="bmreg-history-modal" id="' . $modal_id . '" aria-hidden="true"' ) );
check( 'JS CONTRACT: backdrop is a close target ([data-bmreg-close])', false !== strpos( $html, 'class="bmreg-history-backdrop" data-bmreg-close' ) );
check( 'JS CONTRACT: close button is a close target ([data-bmreg-close])', false !== strpos( $html, 'class="bmreg-history-close" data-bmreg-close' ) );
check( 'JS CONTRACT: dialog is focusable (handler calls d.focus())', false !== strpos( $html, 'class="bmreg-history-dialog"' ) && false !== strpos( $html, 'tabindex="-1"' ) );
check( 'JS CONTRACT: .bmreg-history-selected exists inside .bmreg-history-chart',
	strpos( $html, 'bmreg-history-chart' ) < strpos( $html, 'bmreg-history-selected' ) );
check( 'JS CONTRACT: every bar carries a data-bmreg-detail payload', 7 === substr_count( $html, 'data-bmreg-detail=' ) );
check( 'JS CONTRACT: bar height is set through the --bmreg-bar-height custom property', false !== strpos( $html, '--bmreg-bar-height:' ) );

// The handler reads exactly these four keys off data-bmreg-detail.
preg_match( '/data-bmreg-detail="([^"]*)"/', $html, $detail_match );
$detail = json_decode( html_entity_decode( $detail_match[1] ?? '', ENT_QUOTES, 'UTF-8' ), true );
check( 'JS CONTRACT: detail payload is valid JSON', is_array( $detail ) );
foreach ( array( 'date', 'regime_label_id', 'directional_bias', 'regime_confidence' ) as $key ) {
	check( "JS CONTRACT: detail payload carries \"$key\" (read by the handler)", isset( $detail[ $key ] ) );
}
check( 'JS CONTRACT: detail payload leaks no diagnostic-only field',
	! isset( $detail['evidence'] ) && ! isset( $detail['input_hash'] ) && ! isset( $detail['classifier_version'] ) );

// ==========================================================================
// 4. CSS CONTRACT — bias modifier classes the stylesheet actually styles
// ==========================================================================
check( 'CSS CONTRACT: bearish bars get .bmreg-bias-bearish', false !== strpos( $html, 'bmreg-history-bar bmreg-bias-bearish' ) );
check( 'CSS CONTRACT: neutral bars get .bmreg-bias-neutral', false !== strpos( $html, 'bmreg-history-bar bmreg-bias-neutral' ) );
$axis_start = strpos( $html, 'class="bmreg-history-axis"' );
$axis_block = substr( $html, $axis_start, strpos( $html, '</div>', $axis_start ) - $axis_start );
check( 'CSS CONTRACT: axis has one cell per bar so it stays aligned',
	7 === substr_count( $axis_block, '<span>' ) );
check( 'CSS CONTRACT: Pro link block is present', false !== strpos( $html, 'bmreg-history-pro-link' ) );
check( 'PRO LINK: points at /pro/', false !== strpos( $html, 'https://example.test/pro/' ) );

// ==========================================================================
// 5. NO FABRICATION / NO LEAKAGE
// ==========================================================================
check( 'INTEGRITY: no diagnostic field leaks into the rendered HTML',
	false === strpos( $html, 'deadbeef' ) && false === strpos( $html, 'internal only' ) );
check( 'INTEGRITY: never pads to 30 bars when only 12 days exist',
	substr_count( $html, 'class="bmreg-history-bar ' ) <= 12 );

// ==========================================================================
// 6. UNKNOWN / OUT-OF-RANGE INPUT is degraded honestly, never guessed
// ==========================================================================
Bitmomo_Regime_State_Store::$records = array(
	array( 'as_of' => '2026-09-09', 'edition' => 'us_session', 'regime' => 'transition', 'directional_bias' => '', 'regime_confidence' => 250 ),
	array( 'as_of' => '2026-09-08', 'edition' => 'us_session', 'regime' => 'transition', 'directional_bias' => 'sideways', 'regime_confidence' => -40 ),
);
$html = Bitmomo_Regime_Shortcodes::instance()->render_history( array( 'days' => 7 ) );
check( 'DEGRADE: an empty/unrecognised bias becomes .bmreg-bias-unknown, not a guessed direction',
	2 === substr_count( $html, 'bmreg-history-bar bmreg-bias-unknown' ) );
check( 'DEGRADE: confidence above 100 is clamped, never printed raw', false === strpos( $html, '250%' ) );
check( 'DEGRADE: negative confidence is clamped to 0%', false !== strpos( $html, '>0%<' ) );
check( 'DEGRADE: a 0% day still renders a clickable bar (minimum visible height)', false !== strpos( $html, '--bmreg-bar-height:4%' ) );

// ==========================================================================
// 7. MULTIPLE INSTANCES — CSS and JS printed once, modal ids stay unique
// ==========================================================================
Bitmomo_Regime_State_Store::$records = bmreg_fixture( 5 );
$reflection = new ReflectionClass( 'Bitmomo_Regime_Shortcodes' );
foreach ( array( 'style_printed', 'history_script_printed' ) as $flag ) {
	$property = $reflection->getProperty( $flag );
	$property->setAccessible( true );
	$property->setValue( Bitmomo_Regime_Shortcodes::instance(), false );
}
$first  = Bitmomo_Regime_Shortcodes::instance()->render_history( array() );
$second = Bitmomo_Regime_Shortcodes::instance()->render_history( array() );
check( 'INSTANCES: <style> is printed only for the first instance',
	1 === substr_count( $first, '<style>' ) && 0 === substr_count( $second, '<style>' ) );
check( 'INSTANCES: <script> is printed only for the first instance',
	1 === substr_count( $first, '<script>' ) && 0 === substr_count( $second, '<script>' ) );
preg_match( '/id="(bmreg-history-modal-\d+)"/', $first, $a );
preg_match( '/id="(bmreg-history-modal-\d+)"/', $second, $b );
check( 'INSTANCES: each instance gets a unique modal id (aria-controls stays unambiguous)',
	! empty( $a[1] ) && ! empty( $b[1] ) && $a[1] !== $b[1] );

printf( "\n%s\n", $failures ? "$failures check(s) FAILED." : 'All checks passed.' );
exit( $failures ? 1 : 0 );

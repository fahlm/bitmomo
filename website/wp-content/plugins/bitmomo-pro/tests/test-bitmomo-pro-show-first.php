<?php
/** Standalone regression test for the delayed public Pro Decision View visualizer. */

define( 'ABSPATH', '/tmp/' );
require __DIR__ . '/wp-stubs.php';

$results = array();
function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

class Bitmomo_Btc_Intelligence_Accountability {
	public static $rows = array();
	public static function delayed_proof( $limit = 1 ) {
		return array( 'delay_hours' => 48, 'rows' => array_slice( self::$rows, 0, $limit ) );
	}
}

require __DIR__ . '/../includes/class-bitmomo-pro-show-first.php';
$enhancer = Bitmomo_Pro_Show_First::instance();
$base_output = '<section><article class="bm-pro-sales__decision-card"><div class="bm-pro-sales__decision-copy"><div>BASE OLD</div><div>INVALIDATION</div></div></article></section>';

Bitmomo_Btc_Intelligence_Accountability::$rows = array();
$unchanged = $enhancer->enhance_sales_shortcode( $base_output, 'bitmomo_pro_sales', array(), array() );
check( 'FAIL CLOSED: no matured delayed proof leaves sales markup unchanged', $unchanged === $base_output );

Bitmomo_Btc_Intelligence_Accountability::$rows = array(
	array(
		'expected_range_low'  => 62000,
		'expected_range_high' => 65000,
		'reference_price'     => 63000,
		'outcome_price_24h'   => 64600,
		'base_scenario'       => 'BTC bertahan di dalam range sambil menunggu konfirmasi berikutnya.',
		'bull_scenario'       => 'Acceptance di atas resistance membuka ekspansi lanjutan.',
		'bear_scenario'       => 'Kehilangan support menggeser fokus ke downside.',
	)
);
$enhanced = $enhancer->enhance_sales_shortcode( $base_output, 'bitmomo_pro_sales', array(), array() );
check( 'VISUAL: Decision card is explicitly enhanced', false !== strpos( $enhanced, 'is-show-first-enhanced' ) );
check( 'VISUAL: real Expected Range becomes a range rail', false !== strpos( $enhanced, 'bm-pro-sales__range-track' ) && false !== strpos( $enhanced, '$62,000 – $65,000' ) );
check( 'VISUAL: reference and settled +24h observations are both plotted', false !== strpos( $enhanced, 'REF $63,000' ) && false !== strpos( $enhanced, '+24H $64,600' ) );
check( 'VISUAL: Base/Bull/Bear are shown as actual scenario lanes', false !== strpos( $enhanced, '>BASE<' ) && false !== strpos( $enhanced, '>BULL<' ) && false !== strpos( $enhanced, '>BEAR<' ) );
check( 'TRUTH: visualizer reads delayed public proof only', false !== strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-show-first.php' ), 'delayed_proof( 1 )' ) );
check( 'TRUTH: visualizer never calls current protected Pro projection', false === strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-show-first.php' ), 'pro_projection' ) && false === strpos( file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-show-first.php' ), 'get_current_brief_for_display' ) );
check( 'SCOPE: unrelated shortcodes are untouched', $base_output === $enhancer->enhance_sales_shortcode( $base_output, 'other_shortcode', array(), array() ) );

$failed = array_filter( $results, static function ( $result ) { return ! $result['pass']; } );
foreach ( $results as $result ) {
	echo sprintf( "[%s] %s\n", $result['pass'] ? 'PASS' : 'FAIL', $result['label'] );
}
printf( "\n%d/%d passed.\n", count( $results ) - count( $failed ), count( $results ) );
exit( $failed ? 1 : 0 );

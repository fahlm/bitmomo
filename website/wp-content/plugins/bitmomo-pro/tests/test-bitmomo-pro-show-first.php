<?php
/** Standalone regression test for Bitmomo Pro Show-First presentation logic. */

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
$unchanged = $enhancer->enhance_shortcode( $base_output, 'bitmomo_pro_sales', array(), array() );
check( 'PUBLIC FAIL CLOSED: no matured delayed proof leaves sales markup unchanged', $unchanged === $base_output );

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
$enhanced = $enhancer->enhance_shortcode( $base_output, 'bitmomo_pro_sales', array(), array() );
check( 'PUBLIC VISUAL: Decision card is explicitly enhanced', false !== strpos( $enhanced, 'is-show-first-enhanced' ) );
check( 'PUBLIC VISUAL: real Expected Range becomes a range rail', false !== strpos( $enhanced, 'bm-pro-sales__range-track' ) && false !== strpos( $enhanced, '$62,000 – $65,000' ) );
check( 'PUBLIC VISUAL: reference and settled +24h observations are both plotted', false !== strpos( $enhanced, 'REF $63,000' ) && false !== strpos( $enhanced, '+24H $64,600' ) );
check( 'PUBLIC VISUAL: Base/Bull/Bear are shown as actual scenario lanes', false !== strpos( $enhanced, '>BASE<' ) && false !== strpos( $enhanced, '>BULL<' ) && false !== strpos( $enhanced, '>BEAR<' ) );

$source = file_get_contents( __DIR__ . '/../includes/class-bitmomo-pro-show-first.php' );
$sales_css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-show-first.css' );
$dashboard_css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-dashboard-show-first.css' );
check( 'PUBLIC TRUTH: sales visualizer uses delayed frozen public proof', false !== strpos( $source, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )' ) );
check( 'PUBLIC TRUTH: no current AI Pro projection is queried', false === strpos( $source, 'pro_projection' ) );

$login_gate = strpos( $source, 'is_user_logged_in()' );
$access_gate = strpos( $source, 'bitmomo_user_has_pro_access( $user_id )' );
$current_read = strpos( $source, 'Bitmomo_Pro_Briefs::get_current_brief_for_display()' );
check( 'PROTECTED SAFETY: login gate exists before current paid brief read', false !== $login_gate && false !== $current_read && $login_gate < $current_read );
check( 'PROTECTED SAFETY: entitlement gate exists before current paid brief read', false !== $access_gate && false !== $current_read && $access_gate < $current_read );
check( 'PROTECTED VISUAL: current range visualization is distinct from public historical proof', false !== strpos( $source, 'bm-pro__range-visual' ) && false !== strpos( $source, 'BTC REF' ) );

check( 'SALES HIERARCHY: product proof precedes comparison and conversion visually', false !== strpos( $sales_css, '.bm-pro-sales__product-proof') && false !== strpos( $sales_css, 'order: 2') && false !== strpos( $sales_css, '.bm-pro-sales__comparison') && false !== strpos( $sales_css, 'order: 3') && false !== strpos( $sales_css, '.bm-pro-sales__climax { order: 5; }' ) );
check( 'SALES COGNITION: feature catalogue is removed from default visual flow', false !== strpos( $sales_css, '.bm-pro-sales__today') && false !== strpos( $sales_css, 'display: none' ) );
check( 'DASHBOARD HIERARCHY: protected Decision View uses wider institutional shell', false !== strpos( $dashboard_css, 'max-width: 980px' ) );
check( 'DASHBOARD VISUAL: protected Expected Range has a real rail and marker', false !== strpos( $dashboard_css, '.bm-pro__range-track') && false !== strpos( $dashboard_css, '.bm-pro__range-marker') );
check( 'DASHBOARD MOBILE: scenarios collapse to one column', false !== strpos( $dashboard_css, '@media (max-width: 720px)') && false !== strpos( $dashboard_css, 'grid-template-columns: 1fr' ) );

check( 'SCOPE: unrelated shortcodes are untouched', $base_output === $enhancer->enhance_shortcode( $base_output, 'other_shortcode', array(), array() ) );

$failed = array_filter( $results, static function ( $result ) { return ! $result['pass']; } );
foreach ( $results as $result ) {
	echo sprintf( "[%s] %s\n", $result['pass'] ? 'PASS' : 'FAIL', $result['label'] );
}
printf( "\n%d/%d passed.\n", count( $results ) - count( $failed ), count( $results ) );
exit( $failed ? 1 : 0 );

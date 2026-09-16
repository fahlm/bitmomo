<?php
$root = dirname( __DIR__ );
$sales = file_get_contents( $root . '/includes/class-bitmomo-pro-sales.php' );
$css   = file_get_contents( $root . '/assets/css/bitmomo-pro-sales.css' );

$failures = array();
$check = static function ( $label, $condition ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $label;
	}
	echo '[' . ( $condition ? 'PASS' : 'FAIL' ) . '] ' . $label . PHP_EOL;
};

$render_sales_start = strpos( $sales, 'public function render_sales' );
$product_proof_call = strpos( $sales, '$this->render_product_proof();', $render_sales_start );
$product_catalog_call = strpos( $sales, '$this->render_what_exists_today();', $render_sales_start );

$check( 'Public Pro is proof-first before the feature catalogue', false !== $product_proof_call && false !== $product_catalog_call && $product_proof_call < $product_catalog_call );
$check( 'Public proof reads only delayed accountability proof', false !== strpos( $sales, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )' ) );
$check( 'Public sales never reads current protected Pro brief', false === strpos( $sales, 'get_current_brief_for_display' ) );
$check( 'Expected Range visualization is canonical renderer-owned', false !== strpos( $sales, 'bm-pro-sales__range-track' ) && false !== strpos( $sales, "expected_range_low" ) && false !== strpos( $sales, "expected_range_high" ) );
$check( 'Reference and settled +24h markers use frozen proof fields', false !== strpos( $sales, "reference_price" ) && false !== strpos( $sales, "outcome_price_24h" ) && false !== strpos( $sales, 'is-reference' ) && false !== strpos( $sales, 'is-outcome' ) );
$check( 'Bear Base Bull scenarios come from the same proof row', false !== strpos( $sales, "bear_scenario" ) && false !== strpos( $sales, "base_scenario" ) && false !== strpos( $sales, "bull_scenario" ) && false !== strpos( $sales, 'bm-pro-sales__scenario-map' ) );
$check( 'Thesis invalidation remains explicit', false !== strpos( $sales, "invalidation" ) && false !== strpos( $sales, 'INVALIDASI TESIS' ) );
$check( 'Unavailable historical proof fails closed without fabricated demo', false !== strpos( $sales, 'Belum ada analisis historis yang memenuhi syarat publikasi dan evaluasi.' ) && false !== strpos( $sales, 'Bitmomo tidak membuat harga, confidence, skenario, atau hasil contoh' ) );
$check( 'Legacy shortcode-output Show First rewriter is absent from canonical renderer', false === strpos( $sales, 'Bitmomo_Pro_Show_First' ) && false === strpos( $sales, 'do_shortcode_tag' ) );
$check( 'Range and scenarios are responsive below tablet/mobile widths', false !== strpos( $css, '.bm-pro-sales__range-track' ) && false !== strpos( $css, '.bm-pro-sales__scenario-map' ) && false !== strpos( $css, '@media(max-width:768px)' ) && false !== strpos( $css, '@media(max-width:420px)' ) );
$check( 'Scenario text has hard wrapping protection', false !== strpos( $css, '.bm-pro-sales__scenario-map p' ) && false !== strpos( $css, 'overflow-wrap:anywhere' ) );
$check( 'Mobile scenario map collapses to one column', preg_match( '/@media\(max-width:900px\).*?\.bm-pro-sales__scenario-map\{grid-template-columns:1fr\}/s', $css ) === 1 );
$check( 'Range marker labels wrap inside bounded widths instead of clipping', false !== strpos( $css, '.bm-pro-sales__range-marker small' ) && false !== strpos( $css, 'white-space:normal' ) && false !== strpos( $css, 'overflow-wrap:anywhere' ) );
$check( 'Reference and outcome labels are vertically staggered', false !== strpos( $css, '.bm-pro-sales__range-marker.is-outcome small{top:56px' ) );

if ( $failures ) {
	fwrite( STDERR, 'Pro proof-first contract failed with ' . count( $failures ) . ' issue(s):' . PHP_EOL );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '- ' . $failure . PHP_EOL );
	}
	exit( 1 );
}

echo 'PASS Pro proof-first remediation contract.' . PHP_EOL;

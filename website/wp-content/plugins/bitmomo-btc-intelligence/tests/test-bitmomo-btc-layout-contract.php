<?php
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/bitmomo-btc-intelligence.css' );
$pass = 0;
$fail = 0;
function layout_check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

layout_check( 'Desktop rail offset matches 72px sticky site header', false !== strpos( $css, '--bmi-sticky-offset:72px' ) );
layout_check( 'Tablet rail offset follows 68px site header', preg_match( '/@media\(max-width:1000px\)\{\.bm-bi\{--bmi-sticky-offset:68px\}\}/', $css ) === 1 );
layout_check( 'Mobile rail offset follows 60px site header', preg_match( '/@media\(max-width:768px\)\{\.bm-bi\{--bmi-sticky-offset:60px\}\}/', $css ) === 1 );
layout_check( 'Sticky rail uses the shared header offset instead of top zero', false !== strpos( $css, '.bm-bi__rail{position:sticky;top:var(--bmi-sticky-offset)' ) && false === strpos( $css, '.bm-bi__rail{position:sticky;top:0' ) );
layout_check( 'Anchor scroll margin clears both site header and section rail', substr_count( $css, 'scroll-margin-top:calc(var(--bmi-sticky-offset) + 64px)' ) >= 2 );
layout_check( 'Ledger table overflow is contained in its own region', false !== strpos( $css, '.bm-bi__ledger-wrap' ) && false !== strpos( $css, 'overflow-x:auto' ) && false !== strpos( $css, '.bm-bi__ledger-table{width:100%;min-width:760px' ) );
layout_check( 'Primary snapshot collapses to one column on narrow screens', false !== strpos( $css, '.bm-bi__snapshot-grid{grid-template-columns:1fr}' ) );

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );

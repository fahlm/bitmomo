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

layout_check( 'Desktop/tablet rail offset matches canonical 64px site header', false !== strpos( $css, '--bmi-sticky-header:64px' ) );
layout_check( 'Rail height has one explicit clearance token', false !== strpos( $css, '--bmi-sticky-rail:63px' ) );
layout_check( 'Mobile rail offset follows 60px site header', preg_match( '/@media\(max-width:768px\)\{\.bm-bi\{--bmi-sticky-header:60px\}\}/', $css ) === 1 );
layout_check( 'Sticky rail uses canonical header token instead of top zero', false !== strpos( $css, '.bm-bi__rail{position:sticky;top:var(--bmi-sticky-header)' ) && false === strpos( $css, '.bm-bi__rail{position:sticky;top:0' ) );
layout_check( 'Anchor scroll margin clears both site header and section rail', substr_count( $css, 'scroll-margin-top:calc(var(--bmi-sticky-header) + var(--bmi-sticky-rail) + 12px)' ) >= 2 );
layout_check( 'Legacy 72/68px rail offsets cannot return', false === strpos( $css, '--bmi-sticky-offset:72px' ) && false === strpos( $css, '--bmi-sticky-offset:68px' ) );
layout_check( 'Ledger table overflow is contained in its own region', false !== strpos( $css, '.bm-bi__ledger-wrap' ) && false !== strpos( $css, 'overflow-x:auto' ) && false !== strpos( $css, '.bm-bi__ledger-table{width:100%;min-width:760px' ) );
layout_check( 'Primary snapshot collapses to one column on narrow screens', false !== strpos( $css, '.bm-bi__snapshot-grid{grid-template-columns:1fr}' ) );

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );

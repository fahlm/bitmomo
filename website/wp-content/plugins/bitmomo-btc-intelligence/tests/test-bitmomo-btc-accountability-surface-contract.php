<?php
$root      = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/bitmomo-btc-intelligence.php' );
$css       = file_get_contents( $root . '/assets/css/accountability-surface.css' );

$pass = 0;
$fail = 0;
function accountability_surface_check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

accountability_surface_check(
	'Accountability presentation is a dedicated first-party asset with deterministic cache invalidation',
	false !== strpos( $bootstrap, "assets/css/accountability-surface.css" ) &&
	false !== strpos( $bootstrap, "array( 'bitmomo-btc-intelligence' )" ) &&
	false !== strpos( $bootstrap, "hash_file( 'sha256', \$asset )" )
);
accountability_surface_check(
	'History Ledger and Track Record are visually stitched into one compound evidence surface',
	false !== strpos( $css, '.bm-bi__history{' ) &&
	false !== strpos( $css, '.bm-bi__ledger{' ) &&
	false !== strpos( $css, '.bm-bi__track-record{' ) &&
	false !== strpos( $css, 'border-radius:10px 10px 0 0' ) &&
	false !== strpos( $css, 'border-radius:0 0 10px 10px' )
);
accountability_surface_check(
	'30D context remains visible as a compact state tape',
	false !== strpos( $css, '.bm-bi__history-bars{' ) &&
	false !== strpos( $css, 'height:38px' ) &&
	false !== strpos( $css, '.bm-bi__history-summary' )
);
accountability_surface_check(
	'Decision Ledger is a bounded inspectable viewport',
	false !== strpos( $css, '.bm-bi__ledger-wrap{' ) &&
	false !== strpos( $css, 'max-height:292px' ) &&
	false !== strpos( $css, 'overflow:auto' )
);
accountability_surface_check(
	'Accountability CSS never hides Decision Ledger body rows',
	false === strpos( $css, 'tbody{display:none' ) &&
	false === strpos( $css, 'tbody tr{display:none' ) &&
	false === strpos( $css, 'tbody>tr{display:none' )
);
accountability_surface_check(
	'Ledger header stays readable while the full result-neutral record set scrolls',
	false !== strpos( $css, '.bm-bi__ledger-table th{' ) &&
	false !== strpos( $css, 'position:sticky' ) &&
	false !== strpos( $css, 'top:0' ) &&
	false !== strpos( $css, 'ALL MATURED OUTCOMES' )
);
accountability_surface_check(
	'Track Record is a dense KPI strip instead of four oversized cards',
	false !== strpos( $css, '.bm-bi__track-record .bm-bi__proof-grid{' ) &&
	false !== strpos( $css, 'grid-template-columns:repeat(4,minmax(0,1fr))' ) &&
	false !== strpos( $css, '.bm-bi__track-record .bm-bi__proof-metric{' ) &&
	false !== strpos( $css, 'padding:10px 11px' )
);
accountability_surface_check(
	'Mobile Track Record preserves information density with horizontal inspection instead of a tall one-column stack',
	false !== strpos( $css, 'display:flex;' ) &&
	false !== strpos( $css, 'overflow-x:auto' ) &&
	false !== strpos( $css, 'scroll-snap-type:x proximity' ) &&
	false === strpos( $css, '.bm-bi__track-record .bm-bi__proof-grid{grid-template-columns:1fr}' )
);
accountability_surface_check(
	'Accountability styling remains accessible under reduced motion',
	false !== strpos( $css, '@media(prefers-reduced-motion:reduce)' ) &&
	false !== strpos( $css, 'scroll-behavior:auto' )
);

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );

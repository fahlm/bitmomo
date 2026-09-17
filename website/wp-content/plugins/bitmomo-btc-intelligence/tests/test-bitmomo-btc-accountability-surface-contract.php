<?php
$root      = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/bitmomo-btc-intelligence.php' );
$css       = file_get_contents( $root . '/assets/css/accountability-surface.css' );
$polish    = file_get_contents( $root . '/assets/css/launch-trust-polish.css' );
$integrity = file_get_contents( $root . '/assets/js/accountability-integrity.js' );
$page      = file_get_contents( $root . '/includes/class-bitmomo-btc-intelligence-page.php' );

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
	false !== strpos( $bootstrap, 'assets/css/accountability-surface.css' ) &&
	false !== strpos( $bootstrap, "hash_file( 'sha256', \$asset )" )
);
accountability_surface_check(
	'Accountability population provenance is localized from the canonical server contracts',
	false !== strpos( $bootstrap, 'assets/js/accountability-integrity.js' ) &&
	false !== strpos( $bootstrap, 'Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 12 )' ) &&
	false !== strpos( $bootstrap, 'Bitmomo_Public_Intelligence_Adapter::evaluation_summary()' ) &&
	false !== strpos( $bootstrap, "'scorecardMethodology'" ) &&
	false !== strpos( $bootstrap, "'ledgerEvaluated'" )
);
accountability_surface_check(
	'Ledger and Scorecard disclose that their populations are intentionally different',
	false !== strpos( $integrity, 'Populasi Ledger:' ) &&
	false !== strpos( $integrity, 'Populasi Scorecard:' ) &&
	false !== strpos( $integrity, 'jumlah baris keduanya tidak harus sama' ) &&
	false !== strpos( $integrity, 'Tidak ada filter berdasarkan hasil')
);
accountability_surface_check(
	'Ledger exposes outcome methodology per visible record without rewriting verdicts',
	false !== strpos( $integrity, 'bm-bi__ledger-method' ) &&
	false !== strpos( $integrity, 'observed-close-24h-v2' ) &&
	false === strpos( $integrity, 'is-aligned') &&
	false === strpos( $integrity, 'is-missed')
);
accountability_surface_check(
	'Legacy session labels are normalized only at the public presentation boundary',
	false !== strpos( $integrity, "US POST-CLOSE · legacy" ) &&
	false !== strpos( $integrity, "US PRE-OPEN · legacy" ) &&
	false !== strpos( $integrity, "value === 'us session'" )
);
accountability_surface_check(
	'Historical publication gaps are disclosed and never fabricated into decision rows',
	false !== strpos( $integrity, 'Jadwal yang tidak menghasilkan record tidak diisi ulang secara retrospektif') &&
	false !== strpos( $integrity, 'gap data, bukan keputusan pasar')
);
accountability_surface_check(
	'Neutral outcome rule and directional inconclusive band are explicit',
	false !== strpos( $integrity, 'Netral dinilai sesuai hanya bila perubahan tetap di dalam rentang') &&
	false !== strpos( $integrity, '−0,5% sampai +0,5%')
);
accountability_surface_check(
	'Launch polish hides unavailable optional comparison providers and raises public text floor',
	false !== strpos( $polish, '.bm-bi .bm-mc__series-button:disabled:not(.is-btc)' ) &&
	false !== strpos( $polish, 'display: none' ) &&
	false !== strpos( $polish, 'font-size: 11px')
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
	'Mobile Track Record scroll region is keyboard reachable and has a visible focus indicator',
	false !== strpos( $page, 'class="bm-bi__proof-grid" tabindex="0" role="region"' ) &&
	false !== strpos( $page, 'Ringkasan track record BTC' ) &&
	false !== strpos( $css, '.bm-bi__track-record .bm-bi__proof-grid:focus-visible{' ) &&
	false !== strpos( $css, 'outline:var(--bm-focus-ring,2px solid var(--bmi-accent))' )
);
accountability_surface_check(
	'Accountability styling remains accessible under reduced motion',
	false !== strpos( $css, '@media(prefers-reduced-motion:reduce)' ) &&
	false !== strpos( $css, 'scroll-behavior:auto' )
);

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );

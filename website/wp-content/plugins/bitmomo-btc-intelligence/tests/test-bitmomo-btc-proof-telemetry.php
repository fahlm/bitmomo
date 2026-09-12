<?php
$theme_js = dirname( __DIR__, 3 ) . '/themes/bitmomo-child-v3/assets/js/bitmomo-retention.js';
$source = file_get_contents( $theme_js );
$pass = 0;
$fail = 0;
function telemetry_check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

telemetry_check( 'Decision Ledger view is measurable', false !== strpos( $source, "'btc_decision_ledger_view'" ) && false !== strpos( $source, "'#decision-ledger'" ) );
telemetry_check( 'Delayed Pro archive view is measurable', false !== strpos( $source, "'btc_pro_archive_view'" ) && false !== strpos( $source, "'#pro-archive'" ) );
telemetry_check( 'Delayed Pro archive expansion is measurable', false !== strpos( $source, "'btc_pro_archive_expand'" ) && false !== strpos( $source, "'.bm-bi__archive-details'" ) );
telemetry_check( 'Internal section navigation is measurable', false !== strpos( $source, "'btc_intelligence_section_nav'" ) );
telemetry_check( 'Proof view fires only after meaningful viewport exposure', false !== strpos( $source, 'entry.intersectionRatio < 0.35' ) && false !== strpos( $source, 'observer.disconnect()' ) );
telemetry_check( 'Proof telemetry distinguishes evidence from honest empty states', false !== strpos( $source, 'evidence_state' ) && false !== strpos( $source, "'available' : 'empty'" ) );
telemetry_check( 'Telemetry remains provider-neutral', false !== strpos( $source, "CustomEvent('bitmomo:analytics'" ) && false !== strpos( $source, 'Array.isArray(window.dataLayer)' ) && false === strpos( $source, 'gtag(' ) );
telemetry_check( 'Telemetry does not introduce identity collection', 0 === preg_match( '/email|whatsapp|first_name|user_id|client_id|device_id|fingerprint|document\.cookie/i', $source ) );

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );

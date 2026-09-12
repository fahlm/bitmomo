<?php
$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-btc-intelligence-accountability.php' );
$pass = 0;
$fail = 0;
function accountability_check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "[PASS] {$label}\n";
	} else {
		$fail++;
		echo "[FAIL] {$label}\n";
	}
}

accountability_check( 'Decision Ledger requires recorded-live provenance', false !== strpos( $source, "'recorded_live'" ) && false !== strpos( $source, '_bitmomo_regime_provenance' ) );
accountability_check( 'Decision Ledger only exposes matured evaluated or missed-window records', false !== strpos( $source, "array( 'evaluated', 'window_missed' )" ) && false !== strpos( $source, '_bm_outcome_status' ) );
accountability_check( 'Decision Ledger explicitly includes unscored missed windows', false !== strpos( $source, "'window_missed' === \$status" ) && false !== strpos( $source, "return 'unscored'" ) );
accountability_check( 'Decision Ledger policy is result-neutral', false !== strpos( $source, 'RECENT_MATURED_NO_RESULT_FILTER' ) );
accountability_check( 'Delayed Pro proof has a hard 48-hour minimum', preg_match( '/const PROOF_DELAY_HOURS\s*=\s*48\s*;/', $source ) === 1 );
accountability_check( 'Delayed Pro proof reads immutable publish snapshot', false !== strpos( $source, 'Bitmomo_Pro_Performance::ORIGINAL_META' ) && false !== strpos( $source, "['published_at']" ) );
accountability_check( 'Delayed Pro proof never calls the current paid display getter', false === strpos( $source, 'get_current_brief_for_display' ) && false === strpos( $source, 'get_latest_brief' ) );
accountability_check( 'Delayed Pro proof requires matured settlement', substr_count( $source, "array( 'evaluated', 'window_missed' )" ) >= 2 );
accountability_check( 'Public contracts never return canonical source record IDs', 0 === preg_match( "/['\"]source_record_id['\"]\s*=>/", $source ) );
accountability_check( 'Public contracts contain no user identity fields', 0 === preg_match( '/email|whatsapp|first_name|user_id|phone|nonce/i', $source ) );
accountability_check( 'Public proof remains read-only', 0 === preg_match( '/update_post_meta|delete_post_meta|wp_update_post|wp_insert_post|wp_delete_post/', $source ) );

printf( "\n%d/%d passed.\n", $pass, $pass + $fail );
exit( 0 === $fail ? 0 : 1 );

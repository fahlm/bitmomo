<?php
/**
 * Standalone source-contract regression for the canonical Pro Decision View.
 *
 * This test intentionally does not bootstrap WordPress. It protects the product
 * and trust boundaries that can regress during later renderer/CSS refactors.
 */

$root       = dirname( __DIR__ );
$sales      = file_get_contents( $root . '/includes/class-bitmomo-pro-sales.php' );
$dashboard  = file_get_contents( $root . '/includes/class-bitmomo-pro-shortcodes.php' );
$decision_css = file_get_contents( $root . '/assets/css/bitmomo-pro-decision-view.css' );

$results = array();
function check_contract( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

$proof_call      = strpos( $sales, '$this->render_product_proof();' );
$comparison_call = strpos( $sales, '$this->render_free_vs_pro();' );
$pricing_call    = strpos( $sales, "echo '<section id=\"pro-pricing\"" );

check_contract(
	'PUBLIC HIERARCHY: real product proof is rendered before explanation/comparison and offer',
	false !== $proof_call && false !== $comparison_call && false !== $pricing_call && $proof_call < $comparison_call && $comparison_call < $pricing_call
);
check_contract(
	'PUBLIC COGNITION: legacy five-card product catalogue is no longer rendered',
	false === strpos( $sales, '$this->render_what_exists_today();' )
);
check_contract(
	'PUBLIC TRUTH: sales surface reads only delayed/frozen Pro proof',
	false !== strpos( $sales, 'Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )' ) &&
	false === strpos( $sales, 'get_current_brief_for_display()' ) &&
	false === strpos( $sales, 'pro_projection' )
);
check_contract(
	'PUBLIC PROOF: historical state is unmistakable',
	false !== strpos( $sales, 'HISTORICAL · DELAYED ≥48H' ) &&
	false !== strpos( $sales, 'Contoh historis tertunda · bukan guidance saat ini' )
);
check_contract(
	'PUBLIC PROOF: range, scenario map and thesis invalidation are first-class',
	false !== strpos( $sales, 'bm-pro-sales__range-track' ) &&
	false !== strpos( $sales, 'bm-pro-sales__scenario-map' ) &&
	false !== strpos( $sales, 'bm-pro-sales__risk-boundary' )
);
check_contract(
	'PUBLIC PROOF: historical reference and settled +24h marker use the same frozen row',
	false !== strpos( $sales, "'REF ' . $this->format_price( $reference )" ) &&
	false !== strpos( $sales, "'+24H ' . $this->format_price( $outcome )" )
);
check_contract(
	'ACCOUNTABILITY: no unsupported accuracy percentage is rendered',
	false !== strpos( $sales, 'INSUFFICIENT SAMPLE FOR ACCURACY %%' ) &&
	false === strpos( $sales, 'win-rate:' )
);
check_contract(
	'CONVERSION: canonical founding prices and cap remain intact',
	false !== strpos( $sales, 'Rp149.000' ) &&
	false !== strpos( $sales, 'Rp1.490.000' ) &&
	false !== strpos( $sales, 'const SEAT_CAP    = 149;' )
);
check_contract(
	'CONVERSION: checkout-off path remains whitelist-first and no fake remaining-seat counter exists',
	false !== strpos( $sales, 'href="#bm-pro-whitelist"' ) &&
	false === stripos( $sales, 'remaining seats' ) &&
	false === stripos( $sales, 'kursi tersisa' )
);
check_contract(
	'PUBLIC IA: row-ledger anchor is removed from the default Pro sales journey',
	false === strpos( $sales, '#decision-ledger' ) && false === strpos( $sales, '#pro-archive' )
);

$login_gate   = strpos( $dashboard, '! is_user_logged_in()' );
$access_gate  = strpos( $dashboard, '! bitmomo_user_has_pro_access( get_current_user_id() )' );
$current_read = strpos( $dashboard, 'Bitmomo_Pro_Briefs::get_current_brief_for_display();' );
check_contract(
	'PROTECTED SAFETY: login and entitlement gates remain before current brief rendering',
	false !== $login_gate && false !== $access_gate && false !== $current_read && $login_gate < $current_read && $access_gate < $current_read
);
check_contract(
	'PROTECTED SAFETY: current paid brief is read only once after canonical gates',
	1 === substr_count( $dashboard, 'Bitmomo_Pro_Briefs::get_current_brief_for_display();' )
);
check_contract(
	'PROTECTED TRUTH: renderer does not query AI Pro projection or fabricate a fallback range',
	false === strpos( $dashboard, 'pro_projection' ) &&
	false !== strpos( $dashboard, 'null === $low || null === $high || $high < $low' )
);
check_contract(
	'PROTECTED HIERARCHY: range rail, scenario map, invalidation and change are canonical markup',
	false !== strpos( $dashboard, 'bm-pro__range-visual' ) &&
	false !== strpos( $dashboard, 'bm-pro__scenarios' ) &&
	false !== strpos( $dashboard, 'Apa yang membuat tesis ini tidak lagi berlaku?' ) &&
	false !== strpos( $dashboard, 'What Changed' )
);
check_contract(
	'PROTECTED COGNITION: confidence explanation is supporting context after What Changed',
	strpos( $dashboard, "if ( ! empty( $brief['what_changed'] ) )" ) < strpos( $dashboard, "if ( ! empty( $brief['confidence_explanation'] ) )" )
);
check_contract(
	'PROTECTED CONVERSION: checkout-off state routes to Founding Whitelist',
	false !== strpos( $dashboard, "home_url( '/pro/#bm-pro-whitelist' )" )
);

check_contract(
	'STYLE OWNERSHIP: both canonical renderers enqueue one shared Decision View component stylesheet',
	false !== strpos( $sales, "'bitmomo-pro-decision-view'" ) &&
	false !== strpos( $dashboard, "'bitmomo-pro-decision-view'" )
);
check_contract(
	'DESKTOP UX: protected decision shell uses the intended 980px institutional width',
	false !== strpos( $decision_css, 'max-width: 980px' )
);
check_contract(
	'MOBILE UX: public metrics collapse and scenarios become one column',
	false !== strpos( $decision_css, '@media (max-width: 760px)' ) &&
	false !== strpos( $decision_css, 'grid-template-columns: repeat(2, minmax(0, 1fr))' ) &&
	false !== strpos( $decision_css, '.bm-pro-sales__scenario-map' ) &&
	false !== strpos( $decision_css, 'grid-template-columns: 1fr;' )
);
check_contract(
	'ACCESSIBILITY: primary mobile actions keep 48px targets and focus-visible is explicit',
	false !== strpos( $decision_css, 'min-height: 48px' ) &&
	false !== strpos( $decision_css, ':focus-visible' )
);
check_contract(
	'ARCHITECTURE: no experimental shortcode enhancer is required by the canonical renderers',
	false === strpos( $sales, 'Bitmomo_Pro_Show_First' ) &&
	false === strpos( $dashboard, 'Bitmomo_Pro_Show_First' ) &&
	false === strpos( $sales, 'do_shortcode_tag' ) &&
	false === strpos( $dashboard, 'do_shortcode_tag' )
);

$failed = array_filter( $results, static function ( $result ) { return ! $result['pass']; } );
foreach ( $results as $result ) {
	echo sprintf( "[%s] %s\n", $result['pass'] ? 'PASS' : 'FAIL', $result['label'] );
}
printf( "\n%d/%d passed.\n", count( $results ) - count( $failed ), count( $results ) );
exit( $failed ? 1 : 0 );

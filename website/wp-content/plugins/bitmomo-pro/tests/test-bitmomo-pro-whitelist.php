<?php
/**
 * Standalone executable test for "Founding Membership Whitelist V1"
 * (class-bitmomo-pro-whitelist.php + its Sales/Email-Service integration
 * points).
 *
 * Not a WordPress PHPUnit suite — loads the real plugin files against the
 * minimal stub in wp-stubs.php, following the same convention as
 * test-bitmomo-pro-activation.php.
 *
 * Run: php tests/test-bitmomo-pro-whitelist.php
 */

define( 'ABSPATH', '/tmp/' ); // Must be set before bitmomo-pro.php's own guard runs.

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

function reset_whitelist_state() {
	$GLOBALS['__wp_stub_posts']    = array();
	$GLOBALS['__wp_stub_postmeta'] = array();
	$GLOBALS['__wp_stub_mail_log'] = array();
	$GLOBALS['__wp_stub_options']  = array();
}

function base_signup_args( $overrides = array() ) {
	return array_merge(
		array(
			'email'   => 'newsignup@example.com',
			'consent' => true,
			'source'  => 'pro_page',
		),
		$overrides
	);
}

$whitelist = Bitmomo_Pro_Whitelist::instance();

// ==========================================================================
// NEW EMAIL -> one whitelist record created
// ==========================================================================

reset_whitelist_state();

$result = $whitelist->submit_entry( base_signup_args() );
check( 'NEW EMAIL: submit_entry() reports ok', true === $result['ok'] );
check( 'NEW EMAIL: status is "created"', 'created' === $result['status'] );
check( 'NEW EMAIL: a post id was returned', $result['post_id'] > 0 );
check( 'NEW EMAIL: exactly one bm_pro_whitelist record exists', 1 === $whitelist->count_total() );
check( 'NEW EMAIL: record status defaults to "waiting"', Bitmomo_Pro_Whitelist::STATUS_WAITING === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'NEW EMAIL: record validation_class defaults to "unclassified" (never auto-classified)', 'unclassified' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );
check( 'NEW EMAIL: normalized email stored lowercase/trimmed', 'newsignup@example.com' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_EMAIL_NORMALIZED, true ) );
check( 'NEW EMAIL: confirmation email was sent (best-effort)', 1 === count( $GLOBALS['__wp_stub_mail_log'] ) && 'newsignup@example.com' === $GLOBALS['__wp_stub_mail_log'][0]['to'] );
check( 'NEW EMAIL: confirmation email subject matches brief verbatim', 'Kamu sudah masuk whitelist Bitmomo Pro' === $GLOBALS['__wp_stub_mail_log'][0]['subject'] );
check( 'NEW EMAIL: confirmation email never promises an unlocked launch date', false === stripos( $GLOBALS['__wp_stub_mail_log'][0]['body'], 'tanggal' ) );

// ==========================================================================
// DUPLICATE EMAIL -> no duplicate created, honest "already registered" state
// ==========================================================================

reset_whitelist_state();

$first  = $whitelist->submit_entry( base_signup_args( array( 'email' => 'DUPE@Example.com' ) ) );
$second = $whitelist->submit_entry( base_signup_args( array( 'email' => 'dupe@example.com   ' ) ) ); // same email, different case/whitespace

check( 'DUPLICATE EMAIL: first submission created', 'created' === $first['status'] );
check( 'DUPLICATE EMAIL: second submission (different case/whitespace) recognized as duplicate', true === $second['ok'] && 'duplicate' === $second['status'] );
check( 'DUPLICATE EMAIL: duplicate resolves to the SAME post id (normalization dedupes case/whitespace)', $first['post_id'] === $second['post_id'] );
check( 'DUPLICATE EMAIL: exactly one record exists, not two', 1 === $whitelist->count_total() );
check( 'DUPLICATE EMAIL: only ONE confirmation email was ever sent (not resent on duplicate)', 1 === count( $GLOBALS['__wp_stub_mail_log'] ) );

// ==========================================================================
// INVALID EMAIL -> rejected
// ==========================================================================

reset_whitelist_state();

$result = $whitelist->submit_entry( base_signup_args( array( 'email' => 'not-an-email' ) ) );
check( 'INVALID EMAIL: rejected (not ok)', false === $result['ok'] );
check( 'INVALID EMAIL: error code is invalid_email', 'invalid_email' === $result['error'] );
check( 'INVALID EMAIL: no record created', 0 === $whitelist->count_total() );

$result = $whitelist->submit_entry( base_signup_args( array( 'email' => '' ) ) );
check( 'INVALID EMAIL: empty email rejected', false === $result['ok'] && 'invalid_email' === $result['error'] );

// ==========================================================================
// NO CONSENT -> rejected
// ==========================================================================

reset_whitelist_state();

$result = $whitelist->submit_entry( base_signup_args( array( 'consent' => false ) ) );
check( 'NO CONSENT: rejected (not ok)', false === $result['ok'] );
check( 'NO CONSENT: error code is consent_required', 'consent_required' === $result['error'] );
check( 'NO CONSENT: no record created', 0 === $whitelist->count_total() );
check( 'NO CONSENT: no confirmation email sent', 0 === count( $GLOBALS['__wp_stub_mail_log'] ) );

$result = $whitelist->submit_entry( array( 'email' => 'noconsentkey@example.com' ) ); // consent key entirely absent
check( 'NO CONSENT: missing consent key (not just false) also rejected', false === $result['ok'] && 'consent_required' === $result['error'] );

// ==========================================================================
// VALID CONSENT -> timestamp stored
// ==========================================================================

reset_whitelist_state();

$GLOBALS['__wp_stub_now'] = strtotime( '2026-08-31 09:15:00' );
$result = $whitelist->submit_entry( base_signup_args( array( 'email' => 'consenttimestamp@example.com' ) ) );
check( 'VALID CONSENT: consent_at timestamp is stored', '' !== get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) );
check( 'VALID CONSENT: consent_at matches the moment of submission', '2026-08-31 09:15:00' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) );
check( 'VALID CONSENT: created_at timestamp is also stored', '2026-08-31 09:15:00' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_CREATED_AT, true ) );
$GLOBALS['__wp_stub_now'] = time(); // restore

// ==========================================================================
// SOURCE / UTM -> stored safely
// ==========================================================================

reset_whitelist_state();

$result = $whitelist->submit_entry(
	base_signup_args(
		array(
			'email'         => 'utmcheck@example.com',
			'source'        => 'pro_page',
			'landing_page'  => 'https://bitmomo.test/pro?utm_source=twitter',
			'utm_source'    => 'twitter',
			'utm_medium'    => 'social',
			'utm_campaign'  => 'founding_launch',
			'referrer'      => 'https://twitter.com/',
		)
	)
);
check( 'SOURCE/UTM: source stored', 'pro_page' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_SOURCE, true ) );
check( 'SOURCE/UTM: utm_source stored', 'twitter' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_UTM_SOURCE, true ) );
check( 'SOURCE/UTM: utm_medium stored', 'social' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_UTM_MEDIUM, true ) );
check( 'SOURCE/UTM: utm_campaign stored', 'founding_launch' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN, true ) );
check( 'SOURCE/UTM: landing_page stored', 'https://bitmomo.test/pro?utm_source=twitter' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_LANDING_PAGE, true ) );
check( 'SOURCE/UTM: referrer stored', 'https://twitter.com/' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_REFERRER, true ) );

// Oversized / hostile input is capped and text-sanitized rather than stored raw.
$huge_source = str_repeat( 'x', 500 );
$result2     = $whitelist->submit_entry( base_signup_args( array( 'email' => 'utmoversized@example.com', 'utm_campaign' => $huge_source ) ) );
check( 'SOURCE/UTM: oversized UTM value is length-capped, not stored raw', strlen( get_post_meta( $result2['post_id'], Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN, true ) ) <= 100 );

// ==========================================================================
// CHECKOUT UNAVAILABLE -> whitelist form shown / CHECKOUT AVAILABLE -> purchase CTA takes priority
// ==========================================================================

reset_whitelist_state();

update_option( 'bitmomo_pro_checkout_url', '' ); // fail-closed: checkout not configured
$html_unavailable = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT UNAVAILABLE: whitelist widget is rendered on the Pro sales page', false !== strpos( $html_unavailable, 'id="bm-pro-whitelist"' ) );
check( 'CHECKOUT UNAVAILABLE: whitelist CTA copy "GABUNG WHITELIST" is shown', false !== strpos( $html_unavailable, 'GABUNG WHITELIST' ) );
check( 'CHECKOUT UNAVAILABLE: founding price copy is present', false !== strpos( $html_unavailable, 'Rp149.000 / bulan' ) && false !== strpos( $html_unavailable, 'Rp1.490.000 / tahun' ) );
check( 'CHECKOUT UNAVAILABLE: 149 cap / batch-of-25 copy is present', false !== strpos( $html_unavailable, '149 Founding Members' ) && false !== strpos( $html_unavailable, 'Batch pertama: 25 anggota' ) );
check( 'CHECKOUT UNAVAILABLE: quiet no-guarantee clarification is present', false !== strpos( $html_unavailable, 'Masuk whitelist tidak menjamin tempat.' ) );
check( 'CHECKOUT UNAVAILABLE: no fake urgency / seat-reserved language leaks in', false === stripos( $html_unavailable, 'tinggal' ) && false === stripos( $html_unavailable, 'seat reserved' ) && false === stripos( $html_unavailable, 'amankan tempat' ) );
check( 'CHECKOUT UNAVAILABLE: the real purchase CTA is NOT shown', false === strpos( $html_unavailable, 'Kunci Harga Founding Beta' ) );

update_option( 'bitmomo_pro_checkout_url', 'https://pay.example.com/bitmomo-pro' );
$html_available = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT AVAILABLE: real purchase CTA is shown', false !== strpos( $html_available, 'Kunci Harga Founding Beta' ) && false !== strpos( $html_available, 'https://pay.example.com/bitmomo-pro' ) );
check( 'CHECKOUT AVAILABLE: whitelist widget is NOT shown (purchase CTA takes priority, not "also" shown)', false === strpos( $html_available, 'id="bm-pro-whitelist"' ) );
check( 'CHECKOUT AVAILABLE: "GABUNG WHITELIST" copy is not present', false === strpos( $html_available, 'GABUNG WHITELIST' ) );

update_option( 'bitmomo_pro_checkout_url', '' ); // restore fail-closed default for subsequent tests

// ==========================================================================
// PUBLIC ACCESS -> whitelist database cannot be enumerated
// ==========================================================================

$whitelist->register_post_type();
$cpt_args = $GLOBALS['__wp_stub_registered_post_types'][ Bitmomo_Pro_Whitelist::POST_TYPE ] ?? array();
check( 'PUBLIC ACCESS: CPT registered as public=false', isset( $cpt_args['public'] ) && false === $cpt_args['public'] );
check( 'PUBLIC ACCESS: CPT registered as publicly_queryable=false', isset( $cpt_args['publicly_queryable'] ) && false === $cpt_args['publicly_queryable'] );
check( 'PUBLIC ACCESS: CPT registered as show_in_rest=false (no REST route)', isset( $cpt_args['show_in_rest'] ) && false === $cpt_args['show_in_rest'] );
check( 'PUBLIC ACCESS: CPT registered as exclude_from_search=true', isset( $cpt_args['exclude_from_search'] ) && true === $cpt_args['exclude_from_search'] );
check( 'PUBLIC ACCESS: CPT registered with rewrite=false (no public URL/permalink)', isset( $cpt_args['rewrite'] ) && false === $cpt_args['rewrite'] );
check( 'PUBLIC ACCESS: CPT registered with query_var=false (not reachable via ?query_var=)', isset( $cpt_args['query_var'] ) && false === $cpt_args['query_var'] );
check( 'PUBLIC ACCESS: CPT has no public archive', isset( $cpt_args['has_archive'] ) && false === $cpt_args['has_archive'] );

// ==========================================================================
// ADMIN -> classification/status editable
// ==========================================================================

reset_whitelist_state();

$entry  = $whitelist->submit_entry( base_signup_args( array( 'email' => 'adminedit@example.com' ) ) );
$post_id = $entry['post_id'];

check( 'ADMIN: before edit, status is "waiting"', 'waiting' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'ADMIN: before edit, validation_class is "unclassified"', 'unclassified' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );

$_POST['bitmomo_pro_whitelist_admin_nonce'] = wp_create_nonce( 'bitmomo_pro_whitelist_admin_save' );
$_POST['bm_wl_status']            = 'invited';
$_POST['bm_wl_validation_class']  = 'warm';
$whitelist->save_admin_meta_box( $post_id );

check( 'ADMIN: status is editable by an admin', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'ADMIN: validation_class is editable by an admin', 'warm' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );

// A garbage value outside the allowed enum is rejected, not stored.
$_POST['bitmomo_pro_whitelist_admin_nonce'] = wp_create_nonce( 'bitmomo_pro_whitelist_admin_save' );
$_POST['bm_wl_status']           = 'not_a_real_status';
$_POST['bm_wl_validation_class'] = 'not_a_real_class';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN: an invalid status value is rejected (previous valid value kept)', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'ADMIN: an invalid validation_class value is rejected (previous valid value kept)', 'warm' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );

// A missing/bad nonce blocks the edit entirely.
unset( $_POST['bitmomo_pro_whitelist_admin_nonce'] );
$_POST['bm_wl_status'] = 'converted';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN: edit without a valid nonce is blocked', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
unset( $_POST['bm_wl_status'], $_POST['bm_wl_validation_class'], $_POST['bitmomo_pro_whitelist_admin_nonce'] );

// Admin summary counts reflect classification/status correctly.
reset_whitelist_state();
$whitelist->submit_entry( base_signup_args( array( 'email' => 'a@example.com' ) ) );
$whitelist->submit_entry( base_signup_args( array( 'email' => 'b@example.com' ) ) );
check( 'ADMIN: summary total count reflects signups', 2 === $whitelist->count_total() );
check( 'ADMIN: summary waiting count reflects default status', 2 === $whitelist->count_by_status( Bitmomo_Pro_Whitelist::STATUS_WAITING ) );
check( 'ADMIN: summary unclassified count reflects default validation_class', 2 === $whitelist->counts_by_validation_class()['unclassified'] );

// ==========================================================================
// CONVERTED STATE -> marked converted when the same email becomes a paid member
// ==========================================================================

reset_whitelist_state();
stub_reset_users();

$entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'converts@example.com' ) ) );
check( 'CONVERTED: starts as "waiting"', 'waiting' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );

check( 'CONVERTED: mark_converted_by_email() flips status to converted', true === $whitelist->mark_converted_by_email( 'converts@example.com' ) );
check( 'CONVERTED: status is now "converted"', 'converted' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'CONVERTED: marking a non-whitelisted email is a safe no-op', false === $whitelist->mark_converted_by_email( 'never-whitelisted@example.com' ) );

// on_pro_activated() listener (the existing bitmomo_pro_activated hook) reaches the same result.
reset_whitelist_state();
$entry2   = $whitelist->submit_entry( base_signup_args( array( 'email' => 'hookconvert@example.com' ) ) );
$user_id  = wp_insert_user( array( 'user_login' => 'hookconvert', 'user_email' => 'hookconvert@example.com' ) );
$whitelist->on_pro_activated( $user_id, array() );
check( 'CONVERTED: on_pro_activated() listener marks the matching whitelist record converted', 'converted' === get_post_meta( $entry2['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );

// ==========================================================================
// MOBILE 360 / 390 / 412 -> no horizontal overflow (static CSS review; this
// stub harness has no real browser/viewport, so true rendered-pixel QA is
// out of scope here — see the OUTPUT report for the honest caveat).
// ==========================================================================

$css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-whitelist.css' );
check( 'MOBILE: widget container uses max-width (fluid), not a fixed width', false !== strpos( $css, 'max-width: 480px' ) && 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*[3-9]\d{2,}px/', $css ) );
check( 'MOBILE: no fixed pixel widths above 360px anywhere in the stylesheet (would force horizontal scroll at 360px)', 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*(\d+)px/', $css, $m ) || ( isset( $m[1] ) && (int) $m[1] <= 360 ) );
check( 'MOBILE: a sub-400px breakpoint exists for compact padding', false !== strpos( $css, '@media ( max-width: 400px )' ) );

// ==========================================================================
// Report
// ==========================================================================

$pass_count = 0;
foreach ( $results as $r ) {
	printf( "[%s] %s\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'] );
	if ( $r['pass'] ) {
		$pass_count++;
	}
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );

exit( $pass_count === $total ? 0 : 1 );

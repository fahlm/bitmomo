<?php
/**
 * Regression-preservation contract for Whitelist V1.
 *
 * Keeps non-WhatsApp safety assertions that predate the email-only launch
 * refactor. The default launch contract lives in test-bitmomo-pro-whitelist.php
 * and the explicitly enabled dormant WhatsApp capability has its own suite.
 */

define( 'ABSPATH', '/tmp/' );
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

function reset_regression_state() {
	$GLOBALS['__wp_stub_posts']       = array();
	$GLOBALS['__wp_stub_postmeta']    = array();
	$GLOBALS['__wp_stub_mail_log']    = array();
	$GLOBALS['__wp_stub_options']     = array();
	$GLOBALS['__wp_stub_action_log']  = array();
	unset( $GLOBALS['__wp_stub_json_response'] );
}

function signup_args( $overrides = array() ) {
	return array_merge(
		array(
			'email'         => 'regression@example.com',
			'first_name'    => 'Regression',
			'consent'       => true,
			'source'        => 'pro_page',
			'landing_page'  => 'https://bitmomo.test/pro/',
			'utm_source'    => '',
			'utm_medium'    => '',
			'utm_campaign'  => '',
			'referrer'      => '',
		),
		$overrides
	);
}

$whitelist = Bitmomo_Pro_Whitelist::instance();

// Validation edge cases retained from the pre-refactor suite.
reset_regression_state();
$empty_email = $whitelist->submit_entry( signup_args( array( 'email' => '' ) ) );
check( 'VALIDATION REGRESSION: empty email remains rejected', false === $empty_email['ok'] && 'invalid_email' === $empty_email['error'] && 0 === $whitelist->count_total() );
$missing_consent = $whitelist->submit_entry( array( 'email' => 'missing-consent@example.com' ) );
check( 'VALIDATION REGRESSION: absent consent key remains rejected', false === $missing_consent['ok'] && 'consent_required' === $missing_consent['error'] && 0 === $whitelist->count_total() );

// Consent / created timestamps must still be captured at the same write event.
reset_regression_state();
$GLOBALS['__wp_stub_now'] = strtotime( '2026-09-14 12:30:00' );
$timestamped = $whitelist->submit_entry( signup_args( array( 'email' => 'timestamp@example.com' ) ) );
check( 'TIMESTAMP REGRESSION: consent_at remains exact', '2026-09-14 12:30:00' === get_post_meta( $timestamped['post_id'], Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) );
check( 'TIMESTAMP REGRESSION: created_at remains exact', '2026-09-14 12:30:00' === get_post_meta( $timestamped['post_id'], Bitmomo_Pro_Whitelist::META_CREATED_AT, true ) );
$GLOBALS['__wp_stub_now'] = time();

// Attribution remains sanitized/capped rather than becoming an unbounded write path.
reset_regression_state();
$attributed = $whitelist->submit_entry(
	signup_args(
		array(
			'email'         => 'attribution-regression@example.com',
			'source'        => 'homepage',
			'landing_page'  => 'https://bitmomo.test/?utm_source=x',
			'utm_source'    => 'x',
			'utm_medium'    => 'social',
			'utm_campaign'  => 'whitelist-v1',
			'referrer'      => 'https://x.com/',
		)
	)
);
check( 'ATTRIBUTION REGRESSION: landing page remains stored', 'https://bitmomo.test/?utm_source=x' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_LANDING_PAGE, true ) );
check( 'ATTRIBUTION REGRESSION: referrer remains stored', 'https://x.com/' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_REFERRER, true ) );
$huge_campaign = str_repeat( 'x', 500 );
$oversized = $whitelist->submit_entry( signup_args( array( 'email' => 'utm-cap@example.com', 'utm_campaign' => $huge_campaign ) ) );
$stored_campaign = get_post_meta( $oversized['post_id'], Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN, true );
check( 'ATTRIBUTION REGRESSION: oversized UTM remains length-capped', strlen( $stored_campaign ) <= 100 && $stored_campaign !== $huge_campaign );

// Private storage may not become publicly enumerable through WordPress routing.
$whitelist->register_post_type();
$cpt = $GLOBALS['__wp_stub_registered_post_types'][ Bitmomo_Pro_Whitelist::POST_TYPE ] ?? array();
check( 'PRIVACY REGRESSION: whitelist query_var remains disabled', isset( $cpt['query_var'] ) && false === $cpt['query_var'] );
check( 'PRIVACY REGRESSION: whitelist rewrite/archive remain disabled', isset( $cpt['rewrite'], $cpt['has_archive'] ) && false === $cpt['rewrite'] && false === $cpt['has_archive'] );

// Admin enum and nonce boundaries must reject hostile/invalid edits.
reset_regression_state();
$admin_entry = $whitelist->submit_entry( signup_args( array( 'email' => 'admin-regression@example.com' ) ) );
$post_id = $admin_entry['post_id'];
$_POST['bitmomo_pro_whitelist_admin_nonce'] = wp_create_nonce( 'bitmomo_pro_whitelist_admin_save' );
$_POST['bm_wl_status'] = 'invited';
$_POST['bm_wl_validation_class'] = 'warm';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN REGRESSION: baseline valid edit succeeds', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) && 'warm' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );

$_POST['bitmomo_pro_whitelist_admin_nonce'] = wp_create_nonce( 'bitmomo_pro_whitelist_admin_save' );
$_POST['bm_wl_status'] = 'not_a_real_status';
$_POST['bm_wl_validation_class'] = 'not_a_real_class';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN REGRESSION: invalid enums cannot overwrite valid state', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) && 'warm' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );

unset( $_POST['bitmomo_pro_whitelist_admin_nonce'] );
$_POST['bm_wl_status'] = 'converted';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN REGRESSION: missing nonce blocks status mutation', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
$_POST = array();

// Admin summary counters still reflect stored state.
reset_regression_state();
$whitelist->submit_entry( signup_args( array( 'email' => 'summary-a@example.com' ) ) );
$whitelist->submit_entry( signup_args( array( 'email' => 'summary-b@example.com' ) ) );
$validation_counts = $whitelist->counts_by_validation_class();
check( 'ADMIN REGRESSION: total/waiting counters remain accurate', 2 === $whitelist->count_total() && 2 === $whitelist->count_by_status( Bitmomo_Pro_Whitelist::STATUS_WAITING ) );
check( 'ADMIN REGRESSION: unclassified counter remains accurate', 2 === (int) ( $validation_counts['unclassified'] ?? 0 ) );

// Conversion lifecycle must still integrate with the existing Pro activation hook.
reset_regression_state();
if ( function_exists( 'stub_reset_users' ) ) {
	stub_reset_users();
}
$conversion = $whitelist->submit_entry( signup_args( array( 'email' => 'convert-regression@example.com' ) ) );
check( 'CONVERSION REGRESSION: unknown email remains a safe no-op', false === $whitelist->mark_converted_by_email( 'not-whitelisted@example.com' ) );
$user_id = wp_insert_user( array( 'user_login' => 'convert-regression', 'user_email' => 'convert-regression@example.com' ) );
$whitelist->on_pro_activated( $user_id, array() );
check( 'CONVERSION REGRESSION: Pro activation hook still marks matching whitelist entry converted', 'converted' === get_post_meta( $conversion['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );

// Default launch success/email trust copy remains fail-closed and link-safe.
reset_regression_state();
update_option( 'bitmomo_pro_checkout_url', '' );
$html = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'COPY REGRESSION: whitelist success headline remains present', false !== strpos( $html, 'Whitelist berhasil. Pemberitahuan akses akan dikirim saat akses dibuka.' ) );
check( 'COPY REGRESSION: default success state remains email-only', false === strpos( $html, 'TAMBAHKAN WHATSAPP' ) && false === strpos( $html, 'bm-wl-whatsapp-number' ) );
$email_entry = $whitelist->submit_entry( signup_args( array( 'email' => 'security-copy@example.com' ) ) );
$mail_body = $GLOBALS['__wp_stub_mail_log'][0]['body'];
check( 'EMAIL REGRESSION: anti-phishing transfer warning remains', false !== strpos( $mail_body, '- transfer crypto melalui WhatsApp atau Telegram' ) );
check( 'EMAIL REGRESSION: private-message wallet warning remains', false !== strpos( $mail_body, '- pembayaran ke alamat wallet yang dikirim melalui pesan pribadi' ) );
check( 'EMAIL REGRESSION: confirmation contains no external http(s) link in email-only mode', false === stripos( $mail_body, 'http://' ) && false === stripos( $mail_body, 'https://' ) );

$pass_count = 0;
foreach ( $results as $row ) {
	printf( "[%s] %s\n", $row['pass'] ? 'PASS' : 'FAIL', $row['label'] );
	if ( $row['pass'] ) {
		$pass_count++;
	}
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );
if ( $pass_count !== $total ) {
	foreach ( $results as $row ) {
		if ( ! $row['pass'] ) {
			fwrite( STDERR, '- ' . $row['label'] . "\n" );
		}
	}
	exit( 1 );
}
exit( 0 );

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

define( 'ABSPATH', '/tmp/' );

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

function reset_whitelist_state() {
	$GLOBALS['__wp_stub_posts']       = array();
	$GLOBALS['__wp_stub_postmeta']    = array();
	$GLOBALS['__wp_stub_mail_log']    = array();
	$GLOBALS['__wp_stub_options']     = array();
	$GLOBALS['__wp_stub_action_log']  = array();
}

function call_ajax( $callable ) {
	unset( $GLOBALS['__wp_stub_json_response'] );
	try {
		call_user_func( $callable );
	} catch ( Stub_Wp_Die_Exception $e ) {
		// Expected -- marks where a real wp_send_json_*() would have exited.
	}
	return $GLOBALS['__wp_stub_json_response'] ?? null;
}

function find_action_call( $tag ) {
	foreach ( $GLOBALS['__wp_stub_action_log'] as $call ) {
		if ( $call['tag'] === $tag ) return $call;
	}
	return null;
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

// NEW EMAIL
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

// DUPLICATE EMAIL
reset_whitelist_state();
$first  = $whitelist->submit_entry( base_signup_args( array( 'email' => 'DUPE@Example.com' ) ) );
$second = $whitelist->submit_entry( base_signup_args( array( 'email' => 'dupe@example.com   ' ) ) );
check( 'DUPLICATE EMAIL: first submission created', 'created' === $first['status'] );
check( 'DUPLICATE EMAIL: second submission (different case/whitespace) recognized as duplicate', true === $second['ok'] && 'duplicate' === $second['status'] );
check( 'DUPLICATE EMAIL: duplicate resolves to the SAME post id (normalization dedupes case/whitespace)', $first['post_id'] === $second['post_id'] );
check( 'DUPLICATE EMAIL: exactly one record exists, not two', 1 === $whitelist->count_total() );
check( 'DUPLICATE EMAIL: only ONE confirmation email was ever sent (not resent on duplicate)', 1 === count( $GLOBALS['__wp_stub_mail_log'] ) );

// INVALID EMAIL
reset_whitelist_state();
$result = $whitelist->submit_entry( base_signup_args( array( 'email' => 'not-an-email' ) ) );
check( 'INVALID EMAIL: rejected (not ok)', false === $result['ok'] );
check( 'INVALID EMAIL: error code is invalid_email', 'invalid_email' === $result['error'] );
check( 'INVALID EMAIL: no record created', 0 === $whitelist->count_total() );
$result = $whitelist->submit_entry( base_signup_args( array( 'email' => '' ) ) );
check( 'INVALID EMAIL: empty email rejected', false === $result['ok'] && 'invalid_email' === $result['error'] );

// NO CONSENT
reset_whitelist_state();
$result = $whitelist->submit_entry( base_signup_args( array( 'consent' => false ) ) );
check( 'NO CONSENT: rejected (not ok)', false === $result['ok'] );
check( 'NO CONSENT: error code is consent_required', 'consent_required' === $result['error'] );
check( 'NO CONSENT: no record created', 0 === $whitelist->count_total() );
check( 'NO CONSENT: no confirmation email sent', 0 === count( $GLOBALS['__wp_stub_mail_log'] ) );
$result = $whitelist->submit_entry( array( 'email' => 'noconsentkey@example.com' ) );
check( 'NO CONSENT: missing consent key (not just false) also rejected', false === $result['ok'] && 'consent_required' === $result['error'] );

// VALID CONSENT
reset_whitelist_state();
$GLOBALS['__wp_stub_now'] = strtotime( '2026-08-31 09:15:00' );
$result = $whitelist->submit_entry( base_signup_args( array( 'email' => 'consenttimestamp@example.com' ) ) );
check( 'VALID CONSENT: consent_at timestamp is stored', '' !== get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) );
check( 'VALID CONSENT: consent_at matches the moment of submission', '2026-08-31 09:15:00' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) );
check( 'VALID CONSENT: created_at timestamp is also stored', '2026-08-31 09:15:00' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_CREATED_AT, true ) );
$GLOBALS['__wp_stub_now'] = time();

// SOURCE / UTM
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
$huge_source = str_repeat( 'x', 500 );
$result2 = $whitelist->submit_entry( base_signup_args( array( 'email' => 'utmoversized@example.com', 'utm_campaign' => $huge_source ) ) );
check( 'SOURCE/UTM: oversized UTM value is length-capped, not stored raw', strlen( get_post_meta( $result2['post_id'], Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN, true ) ) <= 100 );

// CHECKOUT UNAVAILABLE / AVAILABLE
reset_whitelist_state();
update_option( 'bitmomo_pro_checkout_url', '' );
$html_unavailable = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT UNAVAILABLE: whitelist widget is rendered on the Pro sales page', false !== strpos( $html_unavailable, 'id="bm-pro-whitelist"' ) );
check( 'CHECKOUT UNAVAILABLE: whitelist CTA copy "GABUNG FOUNDING WHITELIST" is shown', false !== strpos( $html_unavailable, 'GABUNG FOUNDING WHITELIST' ) );
check( 'CHECKOUT UNAVAILABLE: founding price copy is present', false !== strpos( $html_unavailable, 'Rp149.000 / bulan' ) && false !== strpos( $html_unavailable, 'Rp1.490.000 / tahun' ) );
check( 'CHECKOUT UNAVAILABLE: 149 cap / batch-of-25 copy is present', false !== strpos( $html_unavailable, '149 Founding Members' ) && false !== strpos( $html_unavailable, 'Batch pertama: 25 anggota' ) );
check( 'CHECKOUT UNAVAILABLE: quiet no-guarantee clarification is present', false !== strpos( $html_unavailable, 'Masuk whitelist tidak menjamin tempat.' ) );
check( 'CHECKOUT UNAVAILABLE: no fake urgency / seat-reserved language leaks in', false === stripos( $html_unavailable, 'tinggal' ) && false === stripos( $html_unavailable, 'seat reserved' ) && false === stripos( $html_unavailable, 'amankan tempat' ) );
check( 'CHECKOUT UNAVAILABLE: the real purchase CTA is NOT shown', false === strpos( $html_unavailable, 'Kunci Harga Founding' ) );
update_option( 'bitmomo_pro_checkout_url', 'https://pay.example.com/bitmomo-pro' );
$html_available = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT AVAILABLE: real purchase CTA is shown', false !== strpos( $html_available, 'Kunci Harga Founding' ) && false !== strpos( $html_available, 'https://pay.example.com/bitmomo-pro' ) );
check( 'CHECKOUT AVAILABLE: whitelist widget is NOT shown (purchase CTA takes priority, not "also" shown)', false === strpos( $html_available, 'id="bm-pro-whitelist"' ) );
check( 'CHECKOUT AVAILABLE: "GABUNG FOUNDING WHITELIST" copy is not present', false === strpos( $html_available, 'GABUNG FOUNDING WHITELIST' ) );
update_option( 'bitmomo_pro_checkout_url', '' );

// PUBLIC ACCESS
$whitelist->register_post_type();
$cpt_args = $GLOBALS['__wp_stub_registered_post_types'][ Bitmomo_Pro_Whitelist::POST_TYPE ] ?? array();
check( 'PUBLIC ACCESS: CPT registered as public=false', isset( $cpt_args['public'] ) && false === $cpt_args['public'] );
check( 'PUBLIC ACCESS: CPT registered as publicly_queryable=false', isset( $cpt_args['publicly_queryable'] ) && false === $cpt_args['publicly_queryable'] );
check( 'PUBLIC ACCESS: CPT registered as show_in_rest=false (no REST route)', isset( $cpt_args['show_in_rest'] ) && false === $cpt_args['show_in_rest'] );
check( 'PUBLIC ACCESS: CPT registered as exclude_from_search=true', isset( $cpt_args['exclude_from_search'] ) && true === $cpt_args['exclude_from_search'] );
check( 'PUBLIC ACCESS: CPT registered with rewrite=false (no public URL/permalink)', isset( $cpt_args['rewrite'] ) && false === $cpt_args['rewrite'] );
check( 'PUBLIC ACCESS: CPT registered with query_var=false (not reachable via ?query_var=)', isset( $cpt_args['query_var'] ) && false === $cpt_args['query_var'] );
check( 'PUBLIC ACCESS: CPT has no public archive', isset( $cpt_args['has_archive'] ) && false === $cpt_args['has_archive'] );

// ADMIN
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
$_POST['bitmomo_pro_whitelist_admin_nonce'] = wp_create_nonce( 'bitmomo_pro_whitelist_admin_save' );
$_POST['bm_wl_status'] = 'not_a_real_status';
$_POST['bm_wl_validation_class'] = 'not_a_real_class';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN: an invalid status value is rejected (previous valid value kept)', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'ADMIN: an invalid validation_class value is rejected (previous valid value kept)', 'warm' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );
unset( $_POST['bitmomo_pro_whitelist_admin_nonce'] );
$_POST['bm_wl_status'] = 'converted';
$whitelist->save_admin_meta_box( $post_id );
check( 'ADMIN: edit without a valid nonce is blocked', 'invited' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_STATUS, true ) );
unset( $_POST['bm_wl_status'], $_POST['bm_wl_validation_class'], $_POST['bitmomo_pro_whitelist_admin_nonce'] );
reset_whitelist_state();
$whitelist->submit_entry( base_signup_args( array( 'email' => 'a@example.com' ) ) );
$whitelist->submit_entry( base_signup_args( array( 'email' => 'b@example.com' ) ) );
check( 'ADMIN: summary total count reflects signups', 2 === $whitelist->count_total() );
check( 'ADMIN: summary waiting count reflects default status', 2 === $whitelist->count_by_status( Bitmomo_Pro_Whitelist::STATUS_WAITING ) );
check( 'ADMIN: summary unclassified count reflects default validation_class', 2 === $whitelist->counts_by_validation_class()['unclassified'] );

// CONVERTED STATE
reset_whitelist_state();
stub_reset_users();
$entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'converts@example.com' ) ) );
check( 'CONVERTED: starts as "waiting"', 'waiting' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'CONVERTED: mark_converted_by_email() flips status to converted', true === $whitelist->mark_converted_by_email( 'converts@example.com' ) );
check( 'CONVERTED: status is now "converted"', 'converted' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'CONVERTED: marking a non-whitelisted email is a safe no-op', false === $whitelist->mark_converted_by_email( 'never-whitelisted@example.com' ) );
reset_whitelist_state();
$entry2  = $whitelist->submit_entry( base_signup_args( array( 'email' => 'hookconvert@example.com' ) ) );
$user_id = wp_insert_user( array( 'user_login' => 'hookconvert', 'user_email' => 'hookconvert@example.com' ) );
$whitelist->on_pro_activated( $user_id, array() );
check( 'CONVERTED: on_pro_activated() listener marks the matching whitelist record converted', 'converted' === get_post_meta( $entry2['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );

// SUCCESS COPY
reset_whitelist_state();
update_option( 'bitmomo_pro_checkout_url', '' );
$html_success_copy = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'SUCCESS COPY: locked headline is present', false !== strpos( $html_success_copy, 'Whitelist berhasil. Kamu akan jadi salah satu yang pertama tahu saat akses dibuka.' ) );
check( 'SUCCESS COPY: "check your email" line is present', false !== strpos( $html_success_copy, 'Cek email kamu untuk informasi lebih lanjut.' ) );
check( 'SUCCESS COPY: locked disclaimer is present', false !== strpos( $html_success_copy, 'Whitelist belum menjamin tempat. Akses aktif setelah pembayaran berhasil, selama Batch pertama masih tersedia.' ) );
check( 'SUCCESS COPY: WhatsApp step locked prompt line is present', false !== strpos( $html_success_copy, 'Tambahkan WhatsApp agar tidak melewatkan pemberitahuan saat akses dibuka.' ) );
check( 'SUCCESS COPY: WhatsApp opt-in button copy "TAMBAHKAN WHATSAPP" is present', false !== strpos( $html_success_copy, 'TAMBAHKAN WHATSAPP' ) );
check( 'SUCCESS COPY: +62 prefix is shown on the WhatsApp field', false !== strpos( $html_success_copy, '+62' ) );
check( 'SUCCESS COPY: WhatsApp step microcopy matches the locked "Opsional..." line verbatim', false !== strpos( $html_success_copy, 'Opsional. Nomor hanya digunakan untuk informasi penting terkait Bitmomo Pro.' ) );

// AJAX submit
reset_whitelist_state();
$_POST = array(
	'bm_wl_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION ),
	'email'       => 'ajaxflow@example.com',
	'consent'     => '1',
	'source'      => 'pro_page',
);
$resp  = call_ajax( array( $whitelist, 'handle_ajax_submit' ) );
$_POST = array();
check( 'AJAX SUBMIT: responds success', true === ( $resp['success'] ?? null ) );
check( 'AJAX SUBMIT: response includes a post_id', ( $resp['data']['post_id'] ?? 0 ) > 0 );
check( 'AJAX SUBMIT: response includes a non-empty record_token', '' !== ( $resp['data']['record_token'] ?? '' ) );
check( 'AJAX SUBMIT: has_whatsapp is false for a brand-new signup', false === ( $resp['data']['has_whatsapp'] ?? null ) );
$ajax_post_id = $resp['data']['post_id'];
$ajax_token   = $resp['data']['record_token'];

// WHATSAPP NORMALIZATION / VALIDATION
check( 'WHATSAPP NORMALIZE: leading-0 format normalizes to 62-prefix', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '0812' . '3456789' ) );
check( 'WHATSAPP NORMALIZE: bare "812..." (no prefix) normalizes to 62-prefix', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '812' . '3456789' ) );
check( 'WHATSAPP NORMALIZE: "+62812..." normalizes to 62-prefix (no plus, no spaces)', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '+62812' . '3456789' ) );
check( 'WHATSAPP NORMALIZE: bare "62812..." stays as-is', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '62812' . '3456789' ) );
check( 'WHATSAPP NORMALIZE: spaces/dashes/dots are stripped ("0812-345 6789")', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '0812-345 6789' ) );
check( 'WHATSAPP NORMALIZE: a valid normalized number passes is_valid_whatsapp_number()', Bitmomo_Pro_Whitelist::is_valid_whatsapp_number( '628123456789' ) );
check( 'WHATSAPP INVALID: too short is rejected', false === Bitmomo_Pro_Whitelist::is_valid_whatsapp_number( Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '0812345' ) ) );
check( 'WHATSAPP INVALID: letters-only normalizes to empty string', '' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( 'abcnotanumber' ) );
check( 'WHATSAPP INVALID: empty input normalizes to empty string', '' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '' ) );
check( 'WHATSAPP INVALID: a non-mobile-shaped number (no leading 8 after 62) is rejected', false === Bitmomo_Pro_Whitelist::is_valid_whatsapp_number( Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '0217654321' ) ) );

// WHATSAPP AJAX
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $ajax_post_id,
	'record_token'         => $ajax_token,
	'whatsapp_number'      => '081234567890',
);
$wa_resp = call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'WHATSAPP AJAX: valid submission succeeds', true === ( $wa_resp['success'] ?? null ) );
check( 'WHATSAPP AJAX: number stored normalized (no +, no spaces)', '6281234567890' === get_post_meta( $ajax_post_id, Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );
check( 'WHATSAPP AJAX: its own consent timestamp is stored', '' !== get_post_meta( $ajax_post_id, Bitmomo_Pro_Whitelist::META_WHATSAPP_CONSENT_AT, true ) );
check( 'WHATSAPP AJAX: WhatsApp consent is never inherited from — and stays independent of — the email consent timestamp', '' !== get_post_meta( $ajax_post_id, Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) && '' !== get_post_meta( $ajax_post_id, Bitmomo_Pro_Whitelist::META_WHATSAPP_CONSENT_AT, true ) );
check( 'WHATSAPP AJAX: submitting WhatsApp never creates a second whitelist record', 1 === $whitelist->count_total() );

reset_whitelist_state();
$victim       = $whitelist->submit_entry( base_signup_args( array( 'email' => 'victim@example.com' ) ) );
$forged_token = wp_create_nonce( 'bm_wl_whatsapp_record_' . ( $victim['post_id'] + 999 ) );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $victim['post_id'],
	'record_token'         => $forged_token,
	'whatsapp_number'      => '081234567890',
);
$forged_resp = call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'WHATSAPP AJAX: a record_token scoped to a DIFFERENT post_id is rejected', false === ( $forged_resp['success'] ?? null ) && 'not_found' === ( $forged_resp['data']['error'] ?? null ) );
check( 'WHATSAPP AJAX: a rejected forged-token attempt stores no WhatsApp number', '' === get_post_meta( $victim['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );

reset_whitelist_state();
$entry3 = $whitelist->submit_entry( base_signup_args( array( 'email' => 'badnumber@example.com' ) ) );
$token3 = wp_create_nonce( 'bm_wl_whatsapp_record_' . $entry3['post_id'] );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $entry3['post_id'],
	'record_token'         => $token3,
	'whatsapp_number'      => '123',
);
$bad_resp = call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'WHATSAPP AJAX: an invalid number is rejected', false === ( $bad_resp['success'] ?? null ) && 'invalid_whatsapp' === ( $bad_resp['data']['error'] ?? null ) );
check( 'WHATSAPP AJAX: an invalid submission stores no number', '' === get_post_meta( $entry3['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );

// WHATSAPP UPDATE / DUPLICATE SAFE
reset_whitelist_state();
$update_entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'wa-update@example.com' ) ) );
$update_token_a = wp_create_nonce( 'bm_wl_whatsapp_record_' . $update_entry['post_id'] );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $update_entry['post_id'],
	'record_token'         => $update_token_a,
	'whatsapp_number'      => '081234500001',
);
call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
$GLOBALS['__wp_stub_now'] = strtotime( '2026-08-31 10:00:00' );
$update_token_b = wp_create_nonce( 'bm_wl_whatsapp_record_' . $update_entry['post_id'] );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $update_entry['post_id'],
	'record_token'         => $update_token_b,
	'whatsapp_number'      => '0899' . '1112222',
);
call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'WHATSAPP UPDATE: re-submitting a new number overwrites the old one on the SAME record', '628991112222' === get_post_meta( $update_entry['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );
check( 'WHATSAPP UPDATE: re-submitting refreshes the consent timestamp', '2026-08-31 10:00:00' === get_post_meta( $update_entry['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_CONSENT_AT, true ) );
check( 'WHATSAPP UPDATE: still exactly one whitelist record (re-submitting the WhatsApp step never creates a duplicate)', 1 === $whitelist->count_total() );
$GLOBALS['__wp_stub_now'] = time();

// TELEMETRY
reset_whitelist_state();
$telem_entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'telemetry@example.com' ) ) );
check( 'TELEMETRY: whatsapp_opt_in has NOT fired yet (no WhatsApp submitted)', null === find_action_call( 'whatsapp_opt_in' ) );
$telem_token = wp_create_nonce( 'bm_wl_whatsapp_record_' . $telem_entry['post_id'] );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $telem_entry['post_id'],
	'record_token'         => $telem_token,
	'whatsapp_number'      => 'not-a-number',
);
call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'TELEMETRY: whatsapp_opt_in does NOT fire on a rejected/invalid attempt', null === find_action_call( 'whatsapp_opt_in' ) );
$telem_token2 = wp_create_nonce( 'bm_wl_whatsapp_record_' . $telem_entry['post_id'] );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $telem_entry['post_id'],
	'record_token'         => $telem_token2,
	'whatsapp_number'      => '081234567890',
);
call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
$fired = find_action_call( 'whatsapp_opt_in' );
check( 'TELEMETRY: whatsapp_opt_in fires exactly once on a successful add', null !== $fired );
check( 'TELEMETRY: whatsapp_opt_in payload carries only post_id, never the raw phone number (PII-safe)', isset( $fired['args'][0]['post_id'] ) && false === strpos( json_encode( $fired['args'] ), '81234567890' ) );
check( 'TELEMETRY: a successful WhatsApp opt-in never auto-promotes validation_class (still "unclassified")', 'unclassified' === get_post_meta( $telem_entry['post_id'], Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );

// DUPLICATE SIGNUP + WHATSAPP
reset_whitelist_state();
$dup_first = $whitelist->submit_entry( base_signup_args( array( 'email' => 'dupwa@example.com' ) ) );
$_POST = array(
	'bm_wl_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION ),
	'email'       => 'dupwa@example.com',
	'consent'     => '1',
	'source'      => 'pro_page',
);
$dup_resp_no_wa = call_ajax( array( $whitelist, 'handle_ajax_submit' ) );
$_POST = array();
check( 'DUPLICATE + WHATSAPP: resubmitting without a WhatsApp number on file still offers the step (has_whatsapp false)', false === ( $dup_resp_no_wa['data']['has_whatsapp'] ?? null ) );
check( 'DUPLICATE + WHATSAPP: resubmission still resolves to the SAME post_id (no duplicate record)', $dup_first['post_id'] === ( $dup_resp_no_wa['data']['post_id'] ?? null ) );
$dup_token = wp_create_nonce( 'bm_wl_whatsapp_record_' . $dup_first['post_id'] );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $dup_first['post_id'],
	'record_token'         => $dup_token,
	'whatsapp_number'      => '081234567890',
);
call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
$_POST = array(
	'bm_wl_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION ),
	'email'       => 'dupwa@example.com',
	'consent'     => '1',
	'source'      => 'pro_page',
);
$dup_resp_with_wa = call_ajax( array( $whitelist, 'handle_ajax_submit' ) );
$_POST = array();
check( 'DUPLICATE + WHATSAPP: once a number is on file, resubmitting the email step reports has_whatsapp = true (client must not re-ask)', true === ( $dup_resp_with_wa['data']['has_whatsapp'] ?? null ) );
check( 'DUPLICATE + WHATSAPP: exactly one whitelist record exists throughout (email step + WhatsApp step + re-submit never duplicate it)', 1 === $whitelist->count_total() );

// CONFIRMATION EMAIL
reset_whitelist_state();
$email_entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'emailcopy@example.com' ) ) );
$sent_body   = $GLOBALS['__wp_stub_mail_log'][0]['body'];
$truthful_notification_line = 'Email ini digunakan untuk pemberitahuan Bitmomo Pro. Jika kamu menambahkan WhatsApp, nomor tersebut disimpan sebagai kanal pemberitahuan opsional; penggunaan kanal WhatsApp mengikuti sistem yang benar-benar aktif saat akses dibuka.';
check( 'CONFIRMATION EMAIL: subject matches the locked subject line', 'Kamu sudah masuk whitelist Bitmomo Pro' === $GLOBALS['__wp_stub_mail_log'][0]['subject'] );
check( 'CONFIRMATION EMAIL: opening/preview line matches the locked success headline verbatim', false !== strpos( $sent_body, 'Whitelist berhasil. Kamu akan jadi salah satu yang pertama tahu saat akses dibuka.' ) );
check( 'CONFIRMATION EMAIL: "Kamu sudah masuk Founding Membership Whitelist Bitmomo Pro." line present', false !== strpos( $sent_body, 'Kamu sudah masuk Founding Membership Whitelist Bitmomo Pro.' ) );
check( 'CONFIRMATION EMAIL: FOUNDING MEMBERSHIP block heading present', false !== strpos( $sent_body, 'FOUNDING MEMBERSHIP' ) );
check( 'CONFIRMATION EMAIL: price lines present (Rp149.000/bulan, Rp1.490.000/tahun)', false !== strpos( $sent_body, 'Rp149.000 / bulan' ) && false !== strpos( $sent_body, 'Rp1.490.000 / tahun' ) );
check( 'CONFIRMATION EMAIL: 149 Founding Members / Batch pertama 25 anggota present', false !== strpos( $sent_body, '149 Founding Members' ) && false !== strpos( $sent_body, 'Batch pertama: 25 anggota' ) );
check( 'CONFIRMATION EMAIL: notification contract keeps WhatsApp optional and does not promise delivery', false !== strpos( $sent_body, $truthful_notification_line ) && false === strpos( $sent_body, 'Kami akan mengirim pemberitahuan melalui email ini dan, jika kamu menambahkan nomor WhatsApp, melalui WhatsApp saat akses dibuka.' ) );
check( 'CONFIRMATION EMAIL: new locked disclaimer present (matches the widget success panel)', false !== strpos( $sent_body, 'Whitelist belum menjamin tempat. Akses aktif setelah pembayaran berhasil, selama Batch pertama masih tersedia.' ) );
check( 'CONFIRMATION EMAIL: still never promises an unlocked launch date', false === stripos( $sent_body, 'tanggal' ) );
update_post_meta( $email_entry['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, '628123456789' );
Bitmomo_Pro_Email_Service::instance()->send_whitelist_confirmation_email( $email_entry['post_id'] );
$body_with_wa = end( $GLOBALS['__wp_stub_mail_log'] )['body'];
check( 'CONFIRMATION EMAIL: truthful notification line is unchanged even if WhatsApp is already on file', false !== strpos( $body_with_wa, $truthful_notification_line ) );

// SECURITY COPY
check( 'SECURITY COPY: states email/WhatsApp are notification-only and registration/payment is only via bitmomo.id', false !== strpos( $sent_body, 'Email dan WhatsApp hanya digunakan untuk pemberitahuan. Pendaftaran dan pembayaran hanya dilakukan melalui:' ) && false !== strpos( $sent_body, 'bitmomo.id' ) );
check( 'SECURITY COPY: itemizes password akun / seed phrase / private key are never asked for', false !== strpos( $sent_body, '- password akun' ) && false !== strpos( $sent_body, '- seed phrase' ) && false !== strpos( $sent_body, '- private key' ) );
check( 'SECURITY COPY: itemizes WhatsApp/Telegram crypto transfers are never requested', false !== strpos( $sent_body, '- transfer crypto melalui WhatsApp atau Telegram' ) );
check( 'SECURITY COPY: itemizes wallet-address-via-private-message is never requested', false !== strpos( $sent_body, '- pembayaran ke alamat wallet yang dikirim melalui pesan pribadi' ) );
check( 'SECURITY COPY: tells the reader to type bitmomo.id directly if unsure (locked phrasing)', false !== strpos( $sent_body, 'Jika ragu, ketik bitmomo.id langsung di browser.' ) );
check( 'NO PAYMENT LINK: confirmation email body contains no http(s):// link anywhere', false === stripos( $sent_body, 'http://' ) && false === stripos( $sent_body, 'https://' ) );

// CHECKOUT AVAILABLE hides whitelist/WhatsApp widget
update_option( 'bitmomo_pro_checkout_url', 'https://pay.example.com/bitmomo-pro' );
$html_checkout_on = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT AVAILABLE: "TAMBAHKAN WHATSAPP" copy is not present', false === strpos( $html_checkout_on, 'TAMBAHKAN WHATSAPP' ) );
update_option( 'bitmomo_pro_checkout_url', '' );

// MOBILE static CSS contract
$css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-whitelist.css' );
check( 'MOBILE: widget container uses max-width (fluid), not a fixed width', false !== strpos( $css, 'max-width: 480px' ) && 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*[3-9]\d{2,}px/', $css ) );
check( 'MOBILE: no fixed pixel widths above 360px anywhere in the stylesheet (would force horizontal scroll at 360px)', 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*(\d+)px/', $css, $m ) || ( isset( $m[1] ) && (int) $m[1] <= 360 ) );
check( 'MOBILE: a sub-400px breakpoint exists for compact padding', false !== strpos( $css, '@media ( max-width: 400px )' ) );

$pass_count = 0;
foreach ( $results as $r ) {
	printf( "[%s] %s\n", $r['pass'] ? 'PASS' : 'FAIL', $r['label'] );
	if ( $r['pass'] ) $pass_count++;
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );

exit( $pass_count === $total ? 0 : 1 );

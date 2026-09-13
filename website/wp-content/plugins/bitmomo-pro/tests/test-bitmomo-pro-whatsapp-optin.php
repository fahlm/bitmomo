<?php
/** Standalone contract test for the dormant WhatsApp opt-in capability. */

define( 'ABSPATH', '/tmp/' );
define( 'BITMOMO_PRO_WHATSAPP_OPT_IN_ENABLED', true );
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();
function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}
function reset_wa_state() {
	$GLOBALS['__wp_stub_posts']       = array();
	$GLOBALS['__wp_stub_postmeta']    = array();
	$GLOBALS['__wp_stub_mail_log']    = array();
	$GLOBALS['__wp_stub_options']     = array();
	$GLOBALS['__wp_stub_action_log']  = array();
	unset( $GLOBALS['__wp_stub_json_response'] );
}
function call_ajax( $callable ) {
	unset( $GLOBALS['__wp_stub_json_response'] );
	try { call_user_func( $callable ); } catch ( Stub_Wp_Die_Exception $e ) {}
	return $GLOBALS['__wp_stub_json_response'] ?? null;
}
function find_action_call( $tag ) {
	foreach ( $GLOBALS['__wp_stub_action_log'] as $call ) if ( $call['tag'] === $tag ) return $call;
	return null;
}
function signup_args( $email ) {
	return array( 'email' => $email, 'first_name' => 'WA', 'consent' => true, 'source' => 'pro_page' );
}

$whitelist = Bitmomo_Pro_Whitelist::instance();
check( 'FLAG: WhatsApp opt-in activates only when explicitly enabled at boot', true === Bitmomo_Pro_Whitelist::whatsapp_opt_in_enabled() );

reset_wa_state();
update_option( 'bitmomo_pro_checkout_url', '' );
$html = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'UI: enabled mode renders WhatsApp prompt', false !== strpos( $html, 'Tambahkan WhatsApp agar tidak melewatkan pemberitahuan' ) );
check( 'UI: enabled mode renders WhatsApp button and +62 prefix', false !== strpos( $html, 'TAMBAHKAN WHATSAPP' ) && false !== strpos( $html, '+62' ) );

// Signup AJAX exposes a capability only in enabled mode.
$_POST = array(
	'bm_wl_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION ),
	'email'       => 'wa-ajax@example.com',
	'consent'     => '1',
	'source'      => 'pro_page',
);
$signup = call_ajax( array( $whitelist, 'handle_ajax_submit' ) );
$_POST = array();
check( 'AJAX: signup succeeds in enabled mode', true === ( $signup['success'] ?? null ) );
check( 'AJAX: response declares WhatsApp enabled', true === ( $signup['data']['whatsapp_enabled'] ?? null ) );
check( 'AJAX: enabled mode returns scoped post id and record token', ( $signup['data']['post_id'] ?? 0 ) > 0 && '' !== (string) ( $signup['data']['record_token'] ?? '' ) );
check( 'AJAX: new enabled signup has no WhatsApp stored yet', false === ( $signup['data']['has_whatsapp'] ?? null ) );
$post_id = (int) $signup['data']['post_id'];
$token = (string) $signup['data']['record_token'];

// Normalization/validation remains deterministic.
check( 'NORMALIZE: 0812 format becomes 62-prefixed digits', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '0812-345 6789' ) );
check( 'NORMALIZE: +62 input becomes bare 62-prefixed digits', '628123456789' === Bitmomo_Pro_Whitelist::normalize_whatsapp_number( '+62 812 345 6789' ) );
check( 'VALIDATE: canonical Indonesian mobile shape accepted', Bitmomo_Pro_Whitelist::is_valid_whatsapp_number( '628123456789' ) );
check( 'VALIDATE: short/non-mobile values rejected', false === Bitmomo_Pro_Whitelist::is_valid_whatsapp_number( '62812' ) && false === Bitmomo_Pro_Whitelist::is_valid_whatsapp_number( '62217654321' ) );

// Valid scoped write.
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $post_id,
	'record_token'         => $token,
	'whatsapp_number'      => '081234567890',
);
$wa = call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'WRITE: valid scoped WhatsApp submission succeeds', true === ( $wa['success'] ?? null ) );
check( 'WRITE: normalized number stored on same record', '6281234567890' === get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );
check( 'WRITE: separate WhatsApp consent timestamp stored', '' !== get_post_meta( $post_id, Bitmomo_Pro_Whitelist::META_WHATSAPP_CONSENT_AT, true ) );
check( 'WRITE: WhatsApp step never creates a second whitelist record', 1 === $whitelist->count_total() );
$fired = find_action_call( 'whatsapp_opt_in' );
check( 'TELEMETRY: successful opt-in emits channel event', null !== $fired );
check( 'TELEMETRY: event contains post id but not raw phone number', isset( $fired['args'][0]['post_id'] ) && false === strpos( json_encode( $fired['args'] ), '081234567890' ) );

// IDOR-style token mismatch must fail and preserve victim data.
reset_wa_state();
$victim = $whitelist->submit_entry( signup_args( 'victim@example.com' ) );
$forged = wp_create_nonce( Bitmomo_Pro_Whitelist::whatsapp_record_action( $victim['post_id'] + 999 ) );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $victim['post_id'],
	'record_token'         => $forged,
	'whatsapp_number'      => '081234567890',
);
$forged_resp = call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'SECURITY: token scoped to another record is rejected', false === ( $forged_resp['success'] ?? null ) && 'not_found' === ( $forged_resp['data']['error'] ?? null ) );
check( 'SECURITY: rejected forged token writes no phone number', '' === get_post_meta( $victim['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );

// Invalid input must fail without telemetry/write.
reset_wa_state();
$invalid_entry = $whitelist->submit_entry( signup_args( 'invalid-wa@example.com' ) );
$invalid_token = wp_create_nonce( Bitmomo_Pro_Whitelist::whatsapp_record_action( $invalid_entry['post_id'] ) );
$_POST = array(
	'bm_wl_whatsapp_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION_WHATSAPP ),
	'post_id'              => $invalid_entry['post_id'],
	'record_token'         => $invalid_token,
	'whatsapp_number'      => '123',
);
$invalid_resp = call_ajax( array( $whitelist, 'handle_ajax_whatsapp_submit' ) );
$_POST = array();
check( 'VALIDATION: invalid WhatsApp number rejected', false === ( $invalid_resp['success'] ?? null ) && 'invalid_whatsapp' === ( $invalid_resp['data']['error'] ?? null ) );
check( 'VALIDATION: invalid attempt stores no number and emits no opt-in event', '' === get_post_meta( $invalid_entry['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) && null === find_action_call( 'whatsapp_opt_in' ) );

// Update remains single-record and refreshes consent.
reset_wa_state();
$update_entry = $whitelist->submit_entry( signup_args( 'wa-update@example.com' ) );
$token_a = wp_create_nonce( Bitmomo_Pro_Whitelist::whatsapp_record_action( $update_entry['post_id'] ) );
$first = $whitelist->submit_whatsapp( array( 'post_id' => $update_entry['post_id'], 'whatsapp_number' => '081234500001' ) );
$GLOBALS['__wp_stub_now'] = strtotime( '2026-09-14 11:00:00' );
$second = $whitelist->submit_whatsapp( array( 'post_id' => $update_entry['post_id'], 'whatsapp_number' => '08991112222' ) );
check( 'UPDATE: repeated opt-in updates same record successfully', true === $first['ok'] && true === $second['ok'] && 1 === $whitelist->count_total() );
check( 'UPDATE: latest normalized number replaces prior value', '628991112222' === get_post_meta( $update_entry['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );
check( 'UPDATE: fresh consent timestamp recorded', '2026-09-14 11:00:00' === get_post_meta( $update_entry['post_id'], Bitmomo_Pro_Whitelist::META_WHATSAPP_CONSENT_AT, true ) );
$GLOBALS['__wp_stub_now'] = time();

// Duplicate email after a number exists tells the UI not to re-ask.
$_POST = array(
	'bm_wl_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION ),
	'email'       => 'wa-update@example.com',
	'consent'     => '1',
	'source'      => 'pro_page',
);
$dup = call_ajax( array( $whitelist, 'handle_ajax_submit' ) );
$_POST = array();
check( 'DEDUPE: repeat signup remains same canonical record', true === ( $dup['success'] ?? null ) && 'duplicate' === ( $dup['data']['status'] ?? '' ) && $update_entry['post_id'] === (int) ( $dup['data']['post_id'] ?? 0 ) );
check( 'DEDUPE: existing WhatsApp is reported so UI does not re-ask', true === ( $dup['data']['has_whatsapp'] ?? null ) );

// Channel-aware confirmation may mention WhatsApp only in explicitly enabled mode.
reset_wa_state();
$email_entry = $whitelist->submit_entry( signup_args( 'wa-email@example.com' ) );
$body = $GLOBALS['__wp_stub_mail_log'][0]['body'];
check( 'EMAIL: enabled mode may announce email + optional WhatsApp channel', false !== strpos( $body, 'Kami akan mengirim pemberitahuan melalui email ini dan, jika kamu memilih menambahkan nomor WhatsApp, melalui WhatsApp saat akses dibuka.' ) );
check( 'EMAIL: enabled mode includes an optional WhatsApp continuation link on bitmomo site', false !== strpos( $body, 'Tambahkan nomor WhatsApp (opsional):' ) && false !== strpos( $body, 'bm_wl_post=' ) && false !== strpos( $body, 'bm_wl_token=' ) );
check( 'EMAIL: payment still points only to bitmomo.id, never a wallet/private-message instruction', false !== strpos( $body, 'Pendaftaran dan pembayaran hanya dilakukan melalui:' ) && false !== strpos( $body, 'bitmomo.id' ) );

// A live checkout always supersedes the whitelist acquisition surface.
update_option( 'bitmomo_pro_checkout_url', 'https://pay.example.com/bitmomo-pro' );
$html_checkout = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT: enabled checkout hides all whitelist/WhatsApp acquisition markup', false !== strpos( $html_checkout, 'Kunci Harga Founding' ) && false === strpos( $html_checkout, 'TAMBAHKAN WHATSAPP' ) && false === strpos( $html_checkout, 'id="bm-pro-whitelist"' ) );

$pass_count = 0;
foreach ( $results as $row ) {
	printf( "[%s] %s\n", $row['pass'] ? 'PASS' : 'FAIL', $row['label'] );
	if ( $row['pass'] ) $pass_count++;
}
$total = count( $results );
printf( "\n%d/%d passed.\n", $pass_count, $total );
if ( $pass_count !== $total ) {
	foreach ( $results as $row ) if ( ! $row['pass'] ) fwrite( STDERR, '- ' . $row['label'] . "\n" );
	exit( 1 );
}
exit( 0 );

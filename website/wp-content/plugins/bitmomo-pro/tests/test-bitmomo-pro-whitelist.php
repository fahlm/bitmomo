<?php
/** Standalone contract test for the default Whitelist V1 launch mode. */

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
	unset( $GLOBALS['__wp_stub_json_response'] );
}

function call_ajax( $callable ) {
	unset( $GLOBALS['__wp_stub_json_response'] );
	try {
		call_user_func( $callable );
	} catch ( Stub_Wp_Die_Exception $e ) {
		// Expected: wp_send_json_* exits in WordPress.
	}
	return $GLOBALS['__wp_stub_json_response'] ?? null;
}

function base_signup_args( $overrides = array() ) {
	return array_merge(
		array(
			'email'         => 'newsignup@example.com',
			'first_name'    => 'New',
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

// ==========================================================================
// LAUNCH CHANNEL BOUNDARY — Whitelist V1 is email-only by default.
// ==========================================================================

check( 'CHANNELS: WhatsApp opt-in is disabled by default', false === Bitmomo_Pro_Whitelist::whatsapp_opt_in_enabled() );

reset_whitelist_state();
update_option( 'bitmomo_pro_checkout_url', '' );
$html = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHANNELS: default whitelist markup contains no WhatsApp prompt', false === strpos( $html, 'Tambahkan WhatsApp agar tidak melewatkan pemberitahuan' ) );
check( 'CHANNELS: default whitelist markup contains no WhatsApp button', false === strpos( $html, 'TAMBAHKAN WHATSAPP' ) );
check( 'CHANNELS: default whitelist markup contains no WhatsApp phone field', false === strpos( $html, 'id="bm-wl-whatsapp-number"' ) );
check( 'PRIVACY: email consent links directly to canonical Privacy page', false !== strpos( $html, '/kebijakan-privasi/' ) && false !== strpos( $html, 'Kebijakan Privasi' ) );

// ==========================================================================
// CORE WRITE PATH — create, dedupe, validation, consent, attribution.
// ==========================================================================

reset_whitelist_state();
$result = $whitelist->submit_entry( base_signup_args() );
check( 'NEW EMAIL: submit_entry reports created', true === $result['ok'] && 'created' === $result['status'] && $result['post_id'] > 0 );
check( 'NEW EMAIL: exactly one private whitelist record exists', 1 === $whitelist->count_total() );
check( 'NEW EMAIL: status defaults to waiting', Bitmomo_Pro_Whitelist::STATUS_WAITING === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );
check( 'NEW EMAIL: validation defaults to unclassified', 'unclassified' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );
check( 'NEW EMAIL: normalized email stored', 'newsignup@example.com' === get_post_meta( $result['post_id'], Bitmomo_Pro_Whitelist::META_EMAIL_NORMALIZED, true ) );
check( 'NEW EMAIL: confirmation generated exactly once', 1 === count( $GLOBALS['__wp_stub_mail_log'] ) && 'newsignup@example.com' === $GLOBALS['__wp_stub_mail_log'][0]['to'] );

$duplicate = $whitelist->submit_entry( base_signup_args( array( 'email' => ' NEWSIGNUP@EXAMPLE.COM ' ) ) );
check( 'DUPLICATE: normalized repeat resolves to same record', true === $duplicate['ok'] && 'duplicate' === $duplicate['status'] && $result['post_id'] === $duplicate['post_id'] );
check( 'DUPLICATE: repeat creates no second record', 1 === $whitelist->count_total() );
check( 'DUPLICATE: confirmation is not resent', 1 === count( $GLOBALS['__wp_stub_mail_log'] ) );

reset_whitelist_state();
$invalid = $whitelist->submit_entry( base_signup_args( array( 'email' => 'not-an-email' ) ) );
check( 'VALIDATION: invalid email rejected without a record', false === $invalid['ok'] && 'invalid_email' === $invalid['error'] && 0 === $whitelist->count_total() );
$no_consent = $whitelist->submit_entry( base_signup_args( array( 'email' => 'noconsent@example.com', 'consent' => false ) ) );
check( 'VALIDATION: missing consent rejected without a record', false === $no_consent['ok'] && 'consent_required' === $no_consent['error'] && 0 === $whitelist->count_total() );
check( 'VALIDATION: rejected submissions generate no email', 0 === count( $GLOBALS['__wp_stub_mail_log'] ) );

reset_whitelist_state();
$GLOBALS['__wp_stub_now'] = strtotime( '2026-09-14 10:15:00' );
$attributed = $whitelist->submit_entry(
	base_signup_args(
		array(
			'email'        => 'attribution@example.com',
			'source'       => 'homepage',
			'landing_page' => 'https://bitmomo.test/',
			'utm_source'   => 'x',
			'utm_medium'   => 'social',
			'utm_campaign' => 'whitelist-v1',
			'referrer'     => 'https://x.com/',
		)
	)
);
check( 'CONSENT: consent timestamp recorded at submission', '2026-09-14 10:15:00' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_CONSENT_AT, true ) );
check( 'ATTRIBUTION: source stored', 'homepage' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_SOURCE, true ) );
check( 'ATTRIBUTION: UTM stored', 'x' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_UTM_SOURCE, true ) && 'social' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_UTM_MEDIUM, true ) && 'whitelist-v1' === get_post_meta( $attributed['post_id'], Bitmomo_Pro_Whitelist::META_UTM_CAMPAIGN, true ) );
$GLOBALS['__wp_stub_now'] = time();

// ==========================================================================
// DEFAULT AJAX RESPONSE — no dormant channel capability leaks to browser.
// ==========================================================================

reset_whitelist_state();
$_POST = array(
	'bm_wl_nonce' => wp_create_nonce( Bitmomo_Pro_Whitelist::NONCE_ACTION ),
	'email'       => 'ajax-default@example.com',
	'consent'     => '1',
	'source'      => 'pro_page',
);
$resp = call_ajax( array( $whitelist, 'handle_ajax_submit' ) );
$_POST = array();
check( 'AJAX DEFAULT: signup succeeds', true === ( $resp['success'] ?? null ) );
check( 'AJAX DEFAULT: response declares WhatsApp disabled', false === ( $resp['data']['whatsapp_enabled'] ?? null ) );
check( 'AJAX DEFAULT: no private post id is exposed when WhatsApp is disabled', 0 === (int) ( $resp['data']['post_id'] ?? -1 ) );
check( 'AJAX DEFAULT: no record token is exposed when WhatsApp is disabled', '' === (string) ( $resp['data']['record_token'] ?? 'unexpected' ) );
check( 'AJAX DEFAULT: has_whatsapp remains false', false === ( $resp['data']['has_whatsapp'] ?? null ) );
check( 'AJAX DEFAULT: canonical record still persisted server-side', 1 === $whitelist->count_total() && $whitelist->find_post_id_by_email( 'ajax-default@example.com' ) > 0 );

// Direct dormant-channel calls also fail closed while disabled.
$stored_id = $whitelist->find_post_id_by_email( 'ajax-default@example.com' );
$wa_direct = $whitelist->submit_whatsapp( array( 'post_id' => $stored_id, 'whatsapp_number' => '081234567890' ) );
check( 'CHANNELS: direct WhatsApp write fails closed while disabled', false === $wa_direct['ok'] && 'not_found' === $wa_direct['error'] );
check( 'CHANNELS: disabled WhatsApp call stores no phone data', '' === get_post_meta( $stored_id, Bitmomo_Pro_Whitelist::META_WHATSAPP_NUMBER, true ) );

// ==========================================================================
// CONFIRMATION EMAIL — email-only promise in default launch mode.
// ==========================================================================

reset_whitelist_state();
$email_entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'emailcopy@example.com' ) ) );
$mail = $GLOBALS['__wp_stub_mail_log'][0];
$sent_body = $mail['body'];
check( 'EMAIL: canonical subject preserved', 'Kamu sudah masuk whitelist Bitmomo Pro' === $mail['subject'] );
check( 'EMAIL: opening mirrors success state', false !== strpos( $sent_body, 'Whitelist berhasil. Kamu akan jadi salah satu yang pertama tahu saat akses dibuka.' ) );
check( 'EMAIL: Founding price and cap are explicit', false !== strpos( $sent_body, 'Rp149.000 / bulan' ) && false !== strpos( $sent_body, 'Rp1.490.000 / tahun' ) && false !== strpos( $sent_body, '149 Founding Members' ) && false !== strpos( $sent_body, 'Batch pertama: 25 anggota' ) );
check( 'EMAIL: default notification promise is email-only', false !== strpos( $sent_body, 'Kami akan mengirim pemberitahuan melalui email ini saat akses dibuka.' ) );
check( 'EMAIL: default message does not invite WhatsApp enrollment', false === strpos( $sent_body, 'Tambahkan nomor WhatsApp' ) && false === strpos( $sent_body, 'jika kamu memilih menambahkan nomor WhatsApp' ) );
check( 'EMAIL: no seat guarantee is made', false !== strpos( $sent_body, 'Whitelist belum menjamin tempat.' ) );
check( 'EMAIL: payment remains constrained to bitmomo.id', false !== strpos( $sent_body, 'Pendaftaran dan pembayaran hanya dilakukan melalui:' ) && false !== strpos( $sent_body, 'bitmomo.id' ) );
check( 'EMAIL: anti-phishing secrets warning remains', false !== strpos( $sent_body, '- password akun' ) && false !== strpos( $sent_body, '- seed phrase' ) && false !== strpos( $sent_body, '- private key' ) );
check( 'EMAIL: confirmation does not promise a launch date', false === stripos( $sent_body, 'tanggal' ) );

// ==========================================================================
// CHECKOUT PRIORITY / CPT PRIVACY / ADMIN / CONVERSION LIFECYCLE.
// ==========================================================================

reset_whitelist_state();
update_option( 'bitmomo_pro_checkout_url', '' );
$html_unavailable = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT OFF: whitelist rendered', false !== strpos( $html_unavailable, 'id="bm-pro-whitelist"' ) && false !== strpos( $html_unavailable, 'GABUNG FOUNDING WHITELIST' ) );
check( 'CHECKOUT OFF: no fake remaining-seat urgency', false === stripos( $html_unavailable, 'tersisa' ) && false === stripos( $html_unavailable, 'seat reserved' ) );
update_option( 'bitmomo_pro_checkout_url', 'https://pay.example.com/bitmomo-pro' );
$html_available = Bitmomo_Pro_Sales::instance()->render_sales( array() );
check( 'CHECKOUT ON: purchase CTA replaces whitelist', false !== strpos( $html_available, 'Kunci Harga Founding' ) && false === strpos( $html_available, 'id="bm-pro-whitelist"' ) );
update_option( 'bitmomo_pro_checkout_url', '' );

$whitelist->register_post_type();
$cpt_args = $GLOBALS['__wp_stub_registered_post_types'][ Bitmomo_Pro_Whitelist::POST_TYPE ] ?? array();
check( 'CPT: private and non-queryable', false === ( $cpt_args['public'] ?? true ) && false === ( $cpt_args['publicly_queryable'] ?? true ) );
check( 'CPT: no REST/search/rewrite/archive exposure', false === ( $cpt_args['show_in_rest'] ?? true ) && true === ( $cpt_args['exclude_from_search'] ?? false ) && false === ( $cpt_args['rewrite'] ?? true ) && false === ( $cpt_args['has_archive'] ?? true ) );

reset_whitelist_state();
$entry = $whitelist->submit_entry( base_signup_args( array( 'email' => 'adminedit@example.com' ) ) );
$_POST['bitmomo_pro_whitelist_admin_nonce'] = wp_create_nonce( 'bitmomo_pro_whitelist_admin_save' );
$_POST['bm_wl_status'] = 'invited';
$_POST['bm_wl_validation_class'] = 'cold';
$whitelist->save_admin_meta_box( $entry['post_id'] );
check( 'ADMIN: status and validation class can be reviewed explicitly', 'invited' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) && 'cold' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_VALIDATION_CLASS, true ) );
$_POST = array();
check( 'CONVERSION: whitelisted email can be marked converted', true === $whitelist->mark_converted_by_email( 'adminedit@example.com' ) && 'converted' === get_post_meta( $entry['post_id'], Bitmomo_Pro_Whitelist::META_STATUS, true ) );

// ==========================================================================
// STATIC MOBILE CONTRACT.
// ==========================================================================

$css = file_get_contents( __DIR__ . '/../assets/css/bitmomo-pro-whitelist.css' );
check( 'MOBILE: widget is fluid rather than fixed-width', false !== strpos( $css, 'max-width: 480px' ) && 0 === preg_match( '/(?<!max-)(?<!min-)\bwidth:\s*[3-9]\d{2,}px/', $css ) );
check( 'MOBILE: compact sub-400px breakpoint exists', false !== strpos( $css, '@media ( max-width: 400px )' ) );

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

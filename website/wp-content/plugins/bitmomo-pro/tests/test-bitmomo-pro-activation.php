<?php
/**
 * Standalone executable test for the P0 Pro revenue activation slice:
 *
 * - bitmomo_pro_get_checkout_url() / bitmomo_pro_is_valid_checkout_url()
 *   fail-closed behavior (requirement 1).
 * - Bitmomo_Pro_Entitlement_Service::has_founding_capacity() (requirement 3
 *   building block).
 * - Bitmomo_Pro_Activation::activate_member() edge case fix: a founding
 *   activation for a brand-new email never creates a WordPress account
 *   when the founding cap is full; existing-user reuse stays safe;
 *   duplicate activation never creates a duplicate account or double-counts
 *   the cap (requirement 3).
 *
 * Not a WordPress PHPUnit suite — loads the real plugin files against the
 * minimal stub in wp-stubs.php, following the same convention as
 * test-bitmomo-pro-brief-readiness.php.
 *
 * Run: php tests/test-bitmomo-pro-activation.php
 */

define( 'ABSPATH', '/tmp/' ); // Must be set before bitmomo-pro.php's own guard runs.

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../bitmomo-pro.php';

$results = array();

function check( $label, $condition ) {
	global $results;
	$results[] = array( 'label' => $label, 'pass' => (bool) $condition );
}

// ==========================================================================
// Part 1: checkout URL — fail-closed validation (requirement 1)
// ==========================================================================

// Case 1: no URL configured anywhere -> '' (fail closed, matches the
// pre-existing "belum tersedia" state).
$GLOBALS['__wp_stub_options'] = array();
check( 'Checkout: unset option -> empty (fail closed)', '' === bitmomo_pro_get_checkout_url() );

// Case 2: a well-formed https URL configured via the option -> passed through.
update_option( 'bitmomo_pro_checkout_url', 'https://pay.example.com/bitmomo-pro' );
check( 'Checkout: valid https option value is returned as-is', 'https://pay.example.com/bitmomo-pro' === bitmomo_pro_get_checkout_url() );

// Case 3: malformed value (bare word, no scheme/host) -> '' , never a dead
// link with the raw garbage value in href.
update_option( 'bitmomo_pro_checkout_url', 'segera-dibuka' );
check( 'Checkout: malformed value -> empty (fail closed), not the raw string', '' === bitmomo_pro_get_checkout_url() );
check( 'Checkout: raw getter still exposes the malformed value for diagnostics', 'segera-dibuka' === bitmomo_pro_get_checkout_url_raw() );

// Case 4: non-http(s) scheme (e.g. javascript:) -> ''.
update_option( 'bitmomo_pro_checkout_url', 'javascript:alert(1)' );
check( 'Checkout: non-http(s) scheme -> empty', '' === bitmomo_pro_get_checkout_url() );

// Case 5: whitespace-only value -> ''.
update_option( 'bitmomo_pro_checkout_url', '   ' );
check( 'Checkout: whitespace-only value -> empty', '' === bitmomo_pro_get_checkout_url() );

// Case 6: direct validator unit checks.
check( 'bitmomo_pro_is_valid_checkout_url: valid https URL', true === bitmomo_pro_is_valid_checkout_url( 'https://checkout.example.com/x' ) );
check( 'bitmomo_pro_is_valid_checkout_url: valid http URL', true === bitmomo_pro_is_valid_checkout_url( 'http://checkout.example.com/x' ) );
check( 'bitmomo_pro_is_valid_checkout_url: empty string', false === bitmomo_pro_is_valid_checkout_url( '' ) );
check( 'bitmomo_pro_is_valid_checkout_url: bare word', false === bitmomo_pro_is_valid_checkout_url( 'notaurl' ) );

update_option( 'bitmomo_pro_checkout_url', '' ); // reset for later sections

// ==========================================================================
// Part 2: Entitlement_Service::has_founding_capacity()
// ==========================================================================

stub_reset_users();
$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();

check( 'has_founding_capacity: true with zero founding members', true === $entitlement_service->has_founding_capacity( 0 ) );

// Fill the cap with FOUNDING_SEAT_CAP active founding members.
$cap = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;
for ( $i = 0; $i < $cap; $i++ ) {
	$uid = wp_insert_user( array( 'user_login' => 'seed' . $i, 'user_email' => "seed{$i}@example.com" ) );
	$entitlement_service->grant_access( $uid, array( 'source' => Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING ) );
}
check( 'has_founding_capacity: false once the cap is full (new account)', false === $entitlement_service->has_founding_capacity( 0 ) );

// An already-active founding member is exempt (re-grant never double counts).
$existing_founder_id = 1; // first seeded user above
check( 'has_founding_capacity: true for an existing active founding member (exempt)', true === $entitlement_service->has_founding_capacity( $existing_founder_id ) );

// A non-founding user is not exempt.
$plain_uid = wp_insert_user( array( 'user_login' => 'plainuser', 'user_email' => 'plain@example.com' ) );
check( 'has_founding_capacity: false for a non-founding existing user once cap is full', false === $entitlement_service->has_founding_capacity( $plain_uid ) );

// grant_access() itself refuses once the cap is full (still the sole final
// authority — has_founding_capacity() is a pre-check, not a replacement).
$overflow_uid = wp_insert_user( array( 'user_login' => 'overflow', 'user_email' => 'overflow@example.com' ) );
$granted = $entitlement_service->grant_access( $overflow_uid, array( 'source' => Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING ) );
check( 'grant_access: refuses a founding grant once the cap is full', false === $granted );
check( 'grant_access: refusal leaves the account without an active status', 'active' !== $entitlement_service->get_status( $overflow_uid ) );

// ==========================================================================
// Part 3: Bitmomo_Pro_Activation::activate_member() — the edge-case fix
// ==========================================================================

function base_activation_args( $overrides = array() ) {
	return array_merge(
		array(
			'email'               => 'newcustomer@example.com',
			'display_name'        => 'New Customer',
			'validation_class'    => 'warm',
			'acquisition_channel' => 'twitter',
			'membership_type'     => Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING,
			'started_at'          => '',
			'expires_at'          => '',
			'billing_period'      => Bitmomo_Pro_Entitlement_Service::BILLING_MONTHLY,
			'note'                => '',
			'send_welcome'        => false,
		),
		$overrides
	);
}

$activation = Bitmomo_Pro_Activation::instance();

// --- Scenario A: new user + available founding seat -> entitlement granted.
stub_reset_users();
$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();

$result = $activation->activate_member( base_activation_args( array( 'email' => 'firstcustomer@example.com' ) ) );
check( 'activate_member: new user + available seat -> ok', true === $result['ok'] );
check( 'activate_member: new user + available seat -> user_action=created', 'created' === $result['user_action'] );
check( 'activate_member: new user + available seat -> entitlement granted', true === $result['entitlement'] );
check( 'activate_member: new user + available seat -> not orphaned', false === $result['orphaned'] );
check( 'activate_member: new user + available seat -> is now Pro-active', 'active' === $entitlement_service->get_status( $result['user_id'] ) );
check( 'activate_member: new user + available seat -> no orphan flag set', '' === get_user_meta( $result['user_id'], Bitmomo_Pro_Entitlements::META_ACTIVATION_ORPHANED_AT, true ) );

// --- Scenario B: existing user reused -> granted safely, no duplicate user.
$existing_id = wp_insert_user( array( 'user_login' => 'existingcustomer', 'user_email' => 'existing@example.com' ) );
$users_before = count( $GLOBALS['__wp_stub_users'] );
$result = $activation->activate_member( base_activation_args( array( 'email' => 'existing@example.com' ) ) );
check( 'activate_member: existing user -> ok', true === $result['ok'] );
check( 'activate_member: existing user -> user_action=reused', 'reused' === $result['user_action'] );
check( 'activate_member: existing user -> same user_id reused, no new account', $existing_id === $result['user_id'] && count( $GLOBALS['__wp_stub_users'] ) === $users_before );
check( 'activate_member: existing user -> entitlement granted', true === $result['entitlement'] );
check( 'activate_member: existing user -> never orphaned (pre-existing account)', false === $result['orphaned'] );

// --- Scenario C: founding cap reached + brand-new email -> no account
// created at all, clean error, no entitlement. This is the core edge case
// fix: the old behavior created the WordPress user first and only then
// discovered the cap was full via grant_access()'s own check.
stub_reset_users();
$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();
$cap = Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP;
for ( $i = 0; $i < $cap; $i++ ) {
	$uid = wp_insert_user( array( 'user_login' => 'cap' . $i, 'user_email' => "cap{$i}@example.com" ) );
	$entitlement_service->grant_access( $uid, array( 'source' => Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING ) );
}
$users_before = count( $GLOBALS['__wp_stub_users'] );

$result = $activation->activate_member( base_activation_args( array( 'email' => 'toolate@example.com' ) ) );
check( 'activate_member: cap full + new email -> not ok', false === $result['ok'] );
check( 'activate_member: cap full + new email -> NO WordPress account created', count( $GLOBALS['__wp_stub_users'] ) === $users_before );
check( 'activate_member: cap full + new email -> user_action stays empty (never reached user creation)', '' === $result['user_action'] );
check( 'activate_member: cap full + new email -> not flagged orphaned (nothing to flag, no account exists)', false === $result['orphaned'] );
check( 'activate_member: cap full + new email -> clean, specific error message', false !== strpos( $result['error'], 'Founding cap reached' ) );
check( 'activate_member: cap full + new email -> get_user_by finds nothing for that email', false === get_user_by( 'email', 'toolate@example.com' ) );

// --- Scenario D: backstop path — an account that already exists (e.g. a
// non-founding signup) upgraded to founding after the cap is full. Cannot
// hit the pre-check (that only guards NEW accounts), and reuse never
// orphans an existing account by design — verifies that guarantee holds
// even when the grant itself fails for a pre-existing user.
$preexisting_id = wp_insert_user( array( 'user_login' => 'preexisting', 'user_email' => 'preexisting@example.com' ) );
$result = $activation->activate_member( base_activation_args( array( 'email' => 'preexisting@example.com' ) ) );
check( 'activate_member: cap full + existing non-founding user -> not ok', false === $result['ok'] );
check( 'activate_member: cap full + existing user -> user_action=reused (never orphaned, pre-existing account)', 'reused' === $result['user_action'] );
check( 'activate_member: cap full + existing user -> never flagged orphaned', false === $result['orphaned'] );
check( 'activate_member: cap full + existing user -> no orphan meta written', '' === get_user_meta( $preexisting_id, Bitmomo_Pro_Entitlements::META_ACTIVATION_ORPHANED_AT, true ) );

// --- Scenario E: duplicate activation — calling activate_member() twice
// with the same email never creates a duplicate account and never
// double-counts the founding cap (has_founding_capacity() exempts an
// already-founding user on the second call).
stub_reset_users();
$entitlement_service = Bitmomo_Pro_Entitlement_Service::instance();

$args = base_activation_args( array( 'email' => 'dupe@example.com' ) );
$first  = $activation->activate_member( $args );
$second = $activation->activate_member( $args );
check( 'activate_member: duplicate activation -> both calls ok', true === $first['ok'] && true === $second['ok'] );
check( 'activate_member: duplicate activation -> second call reuses the same user', 'created' === $first['user_action'] && 'reused' === $second['user_action'] && $first['user_id'] === $second['user_id'] );
check( 'activate_member: duplicate activation -> exactly one WordPress account exists', 1 === count( $GLOBALS['__wp_stub_users'] ) );
check( 'activate_member: duplicate activation -> founding count is 1, not double-counted', 1 === $entitlement_service->count_active_founding_members() );

// --- Scenario F: invalid email -> rejected before any user lookup/creation.
stub_reset_users();
$result = $activation->activate_member( base_activation_args( array( 'email' => 'not-an-email' ) ) );
check( 'activate_member: invalid email -> not ok, no account created', false === $result['ok'] && 0 === count( $GLOBALS['__wp_stub_users'] ) );

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

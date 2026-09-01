<?php
/**
 * Plugin Name: Bitmomo Pro
 * Plugin URI: https://bitmomo.id
 * Description: Paid-product access layer for Bitmomo Pro. Packages, protects, and delivers the daily Pro brief to entitled subscribers. Does not generate market intelligence — see the bitmomo-ai plugin for that.
 * Version: 0.12.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_PRO_VERSION', '0.12.2' );
define( 'BITMOMO_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_PRO_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-entitlement-service.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-entitlements.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-briefs.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-brief-readiness.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-brief-prefill.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-canonical-adapter.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-shortcodes.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-help-center.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-sales.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-cache.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-setup.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-users-list.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-email-service.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-usage.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-activation.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-account.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-daily.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-launch-readiness.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-performance.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-whitelist.php';

/**
 * Bootstraps the plugin's responsibilities:
 * - Entitlement Service: the canonical WRITE boundary (grant/extend/revoke/
 *   expire), the source of the bitmomo_pro_activated/extended/revoked/
 *   expired lifecycle hooks, and the live get_status() used everywhere.
 * - Entitlements: admin profile UI that collects founder input and hands
 *   it to the service. Also still hosts bitmomo_user_has_pro_access(), the
 *   canonical READ gate.
 * - Briefs: the versioned, private daily Pro decision-view content type.
 * - Brief Prefill: the receiving-side boundary that turns a normalized
 *   canonical-source payload into a new Pro brief DRAFT. Does not fetch
 *   anything and does not call bitmomo-ai — see that file's docblock for
 *   the full Codex handoff contract.
 * - Shortcodes: the protected dashboard rendering surface, gated server-side.
 * - Sales: the public, unprotected sales surface ([bitmomo_pro_sales]).
 * - Cache: application-level no-cache signaling for the protected route.
 * - Setup: optional, idempotent draft-page provisioning for founders/Codex.
 * - Users List: operational Pro columns + filter on the normal Users screen.
 * - Usage: PMF/usage signals (validation class, acquisition channel,
 *   lightweight per-user Pro-view tracking) for the first ~25 customers.
 *   Not anonymous analytics — nothing is recorded for logged-out or
 *   non-entitled visitors. See that file's docblock for the privacy
 *   contract and the exact "Activated" definition.
 * - Activation: founder-only "Activate Pro Member" console (PR #34) —
 *   manual post-payment activation for the first ~25 customers. Not a
 *   CRM, not a payment integration. Orchestrates the existing
 *   Entitlement Service / Usage setters / Email Service rather than
 *   duplicating their logic.
 * - Account: the customer-facing [bitmomo_pro_account] status page —
 *   always the current logged-in user only, never any other user's data.
 * - Daily: the "Daily Pro" founder/editor orchestration screen (PR #35)
 *   — one-click prefill via the existing Brief Prefill service, a
 *   deterministic (non-LLM) comparison against the previous published
 *   brief, an editorial "What Changed" suggestion the founder must
 *   explicitly apply, and a workflow-stage overview. Reads existing
 *   state only; never bypasses Brief Readiness and never auto-sends
 *   email.
 * - Launch Readiness: the "Bitmomo Pro Launch Readiness" founder/admin
 *   screen (PR #36) — a deployment/integration checklist generated from
 *   actual WordPress/plugin state. Never fabricates a PASS for anything
 *   it cannot verify from inside the plugin (real mail deliverability,
 *   hosting cache behavior, SSL, payment success, or Codex's live
 *   canonical adapter output all show explicitly as "RUNTIME
 *   VERIFICATION REQUIRED"), and its overall status is only ever "NOT
 *   READY" or "STAGING-READY CANDIDATE" — never "PRODUCTION READY".
 * - Whitelist: the Founding Membership Whitelist (V1) — pre-checkout
 *   demand capture on the Sales page's CTA slot, shown automatically
 *   whenever bitmomo_pro_get_checkout_url() is empty and replaced
 *   automatically by the real purchase CTA once a checkout URL is
 *   configured. One canonical, private bm_pro_whitelist record per email;
 *   not a seat reservation, not a second subscriber database, not a CRM.
 *
 * This plugin is the paid-product/access layer, not the intelligence engine.
 * bitmomo-ai creates/validates intelligence; bitmomo-pro packages, protects,
 * and delivers it. Kept as a separate, independently activatable plugin so
 * it never needs to touch bitmomo-ai's files or the active theme.
 */
function bitmomo_pro_init() {
	Bitmomo_Pro_Entitlement_Service::instance();
	Bitmomo_Pro_Entitlements::instance();
	Bitmomo_Pro_Briefs::instance();
	Bitmomo_Pro_Brief_Readiness::instance();
	Bitmomo_Pro_Brief_Prefill::instance();
	Bitmomo_Pro_Canonical_Adapter::instance();
	Bitmomo_Pro_Shortcodes::instance();
	Bitmomo_Pro_Help_Center::instance();
	Bitmomo_Pro_Sales::instance();
	Bitmomo_Pro_Cache::instance();
	Bitmomo_Pro_Setup::instance();
	Bitmomo_Pro_Users_List::instance();
	Bitmomo_Pro_Email_Service::instance();
	Bitmomo_Pro_Usage::instance();
	Bitmomo_Pro_Activation::instance();
	Bitmomo_Pro_Account::instance();
	Bitmomo_Pro_Daily::instance();
	Bitmomo_Pro_Launch_Readiness::instance();
	Bitmomo_Pro_Performance::instance();
	Bitmomo_Pro_Whitelist::instance();
}
add_action( 'plugins_loaded', 'bitmomo_pro_init' );

function bitmomo_pro_activate() {
	// Registers the bm_pro_brief post type so rewrite rules are current on activation.
	Bitmomo_Pro_Briefs::instance()->register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bitmomo_pro_activate' );

function bitmomo_pro_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bitmomo_pro_deactivate' );

/**
 * Provider-neutral checkout destination. No payment provider is integrated yet.
 *
 * Priority: BITMOMO_PRO_CHECKOUT_URL constant (wp-config.php) > the
 * bitmomo_pro_checkout_url option (Settings > General) > empty string.
 * Filterable via 'bitmomo_pro_checkout_url' so a future payment integration
 * can override this without touching template code — including a real
 * provider's hosted checkout link, once one is chosen; nothing about this
 * function is provider-specific.
 *
 * Fail-closed by construction: the return value is only ever a well-formed
 * absolute http(s) URL, or '' — see bitmomo_pro_is_valid_checkout_url()
 * below. A configured-but-malformed value (a bare word, a non-http scheme,
 * stray whitespace) is treated identically to "not configured", never
 * rendered as a link. This is the single place that decision is made; every
 * Pro CTA (Bitmomo_Pro_Sales::render_cta(), Bitmomo_Pro_Shortcodes::checkout_cta(),
 * Bitmomo_Pro_Account::checkout_cta()) calls this one function and must keep
 * failing gracefully to the existing "belum tersedia" / manual-activation
 * copy when it returns ''. Do not duplicate this check elsewhere.
 */
function bitmomo_pro_get_checkout_url() {
	$url = bitmomo_pro_get_checkout_url_raw();

	return bitmomo_pro_is_valid_checkout_url( $url ) ? $url : '';
}

/**
 * The same constant > option priority chain as bitmomo_pro_get_checkout_url(),
 * but without the validity gate — i.e. whatever is actually configured,
 * valid or not. Not for rendering a link with. Exists only so an admin
 * diagnostic (Bitmomo_Pro_Launch_Readiness) can tell "nothing configured
 * yet" apart from "something is configured but malformed" instead of both
 * collapsing into the same blank state. Everything that decides whether to
 * show a real CTA must keep calling bitmomo_pro_get_checkout_url(), never
 * this function.
 */
function bitmomo_pro_get_checkout_url_raw() {
	if ( defined( 'BITMOMO_PRO_CHECKOUT_URL' ) && BITMOMO_PRO_CHECKOUT_URL ) {
		$url = BITMOMO_PRO_CHECKOUT_URL;
	} else {
		$url = get_option( 'bitmomo_pro_checkout_url', '' );
	}

	return apply_filters( 'bitmomo_pro_checkout_url', $url );
}

/**
 * True only for a non-empty, well-formed absolute http(s) URL. Wraps core
 * WordPress's own wp_http_validate_url() rather than inventing a second
 * validation rule — deliberately strict (rejects non-http(s) schemes,
 * missing host, etc.) because this gate decides whether a real, clickable
 * payment destination is shown to a stranger.
 */
function bitmomo_pro_is_valid_checkout_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}
	return false !== wp_http_validate_url( $url );
}

/**
 * Registers a single Settings > General field so the founder can set or
 * change the checkout URL without touching code, once a payment path is
 * chosen. No custom admin page — deliberately the smallest possible UI.
 */
function bitmomo_pro_register_settings() {
	register_setting(
		'general',
		'bitmomo_pro_checkout_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	add_settings_field(
		'bitmomo_pro_checkout_url',
		__( 'Bitmomo Pro Checkout URL', 'bitmomo-pro' ),
		'bitmomo_pro_render_checkout_url_field',
		'general'
	);
}
add_action( 'admin_init', 'bitmomo_pro_register_settings' );

function bitmomo_pro_render_checkout_url_field() {
	$value = get_option( 'bitmomo_pro_checkout_url', '' );
	printf(
		'<input type="url" class="regular-text" name="bitmomo_pro_checkout_url" value="%s" placeholder="https://..." />
		<p class="description">%s</p>',
		esc_attr( $value ),
		esc_html__( 'Where the Bitmomo Pro CTA points. Leave blank until a payment path is finalized — the CTA hides gracefully and shows a manual/coming-soon note instead.', 'bitmomo-pro' )
	);
}

/**
 * The Pro dashboard URL used in welcome/daily-brief emails.
 *
 * Priority: BITMOMO_PRO_DASHBOARD_URL constant > bitmomo_pro_dashboard_url
 * option (Settings > General) > auto-detected Page with slug
 * "pro-dashboard" (the slug Bitmomo_Pro_Setup offers to create) > empty
 * string. Filterable via 'bitmomo_pro_dashboard_url'. Callers must fail
 * gracefully (omit the line) when this is empty rather than emailing a
 * broken link — see Bitmomo_Pro_Email_Service.
 */
function bitmomo_pro_get_dashboard_url() {
	if ( defined( 'BITMOMO_PRO_DASHBOARD_URL' ) && BITMOMO_PRO_DASHBOARD_URL ) {
		$url = BITMOMO_PRO_DASHBOARD_URL;
	} else {
		$url = get_option( 'bitmomo_pro_dashboard_url', '' );
	}

	if ( empty( $url ) ) {
		$page = get_page_by_path( 'pro-dashboard', OBJECT, 'page' );
		if ( $page && 'publish' === $page->post_status ) {
			$url = get_permalink( $page );
		}
	}

	return apply_filters( 'bitmomo_pro_dashboard_url', $url );
}

function bitmomo_pro_register_dashboard_url_setting() {
	register_setting(
		'general',
		'bitmomo_pro_dashboard_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	add_settings_field(
		'bitmomo_pro_dashboard_url',
		__( 'Bitmomo Pro Dashboard URL', 'bitmomo-pro' ),
		'bitmomo_pro_render_dashboard_url_field',
		'general'
	);
}
add_action( 'admin_init', 'bitmomo_pro_register_dashboard_url_setting' );

function bitmomo_pro_render_dashboard_url_field() {
	$value = get_option( 'bitmomo_pro_dashboard_url', '' );
	printf(
		'<input type="url" class="regular-text" name="bitmomo_pro_dashboard_url" value="%s" placeholder="https://.../pro-dashboard" />
		<p class="description">%s</p>',
		esc_attr( $value ),
		esc_html__( 'Used in welcome/daily-brief emails. Leave blank to auto-detect a Page at the "pro-dashboard" slug.', 'bitmomo-pro' )
	);
}

<?php
/**
 * Plugin Name: Bitmomo Pro
 * Plugin URI: https://bitmomo.id
 * Description: Paid-product access layer for Bitmomo Pro. Packages, protects, and delivers the daily Pro brief to entitled subscribers. Does not generate market intelligence — see the bitmomo-ai plugin for that.
 * Version: 0.12.8
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_PRO_VERSION', '0.12.8' );
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
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-public-copy.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-sales.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-show-first.php';
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

/** Bootstrap the paid-product/access responsibilities. */
function bitmomo_pro_init() {
	Bitmomo_Pro_Entitlement_Service::instance();
	Bitmomo_Pro_Entitlements::instance();
	Bitmomo_Pro_Briefs::instance();
	Bitmomo_Pro_Brief_Readiness::instance();
	Bitmomo_Pro_Brief_Prefill::instance();
	Bitmomo_Pro_Canonical_Adapter::instance();
	Bitmomo_Pro_Shortcodes::instance();
	Bitmomo_Pro_Public_Copy::init();
	Bitmomo_Pro_Help_Center::instance();

	// Public SEO is owned once by the active Bitmomo public layer. The Sales
	// class retains its legacy methods for backwards compatibility, but their
	// hooks are detached here so two components can never emit competing Pro
	// descriptions or duplicate fallback <meta name="description"> tags.
	$pro_sales = Bitmomo_Pro_Sales::instance();
	remove_filter( 'rank_math/frontend/description', array( $pro_sales, 'filter_meta_description' ), 10 );
	remove_action( 'wp_head', array( $pro_sales, 'render_meta_description' ), 10 );
	Bitmomo_Pro_Show_First::instance();

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

/**
 * Show-First V1 is a reversible presentation layer for the public Pro sales
 * shortcode. It intentionally loads after the canonical sales stylesheet and
 * changes only visual hierarchy/cognitive density; protected data, product
 * contracts, pricing, entitlement, whitelist and checkout behavior stay owned
 * by their canonical classes.
 */
function bitmomo_pro_show_first_assets() {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_pro_sales' ) ) {
		return;
	}

	wp_enqueue_style(
		'bitmomo-pro-show-first',
		BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-show-first.css',
		array( 'bitmomo-pro-sales' ),
		BITMOMO_PRO_VERSION . '-show-first-v1'
	);
}
add_action( 'wp_enqueue_scripts', 'bitmomo_pro_show_first_assets', 30 );

function bitmomo_pro_activate() {
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
 * Fail-closed: only a valid absolute HTTP(S) URL is ever returned.
 */
function bitmomo_pro_get_checkout_url() {
	$url = bitmomo_pro_get_checkout_url_raw();
	return bitmomo_pro_is_valid_checkout_url( $url ) ? $url : '';
}

/** Raw configured value for admin diagnostics only; never render directly. */
function bitmomo_pro_get_checkout_url_raw() {
	if ( defined( 'BITMOMO_PRO_CHECKOUT_URL' ) && BITMOMO_PRO_CHECKOUT_URL ) {
		$url = BITMOMO_PRO_CHECKOUT_URL;
	} else {
		$url = get_option( 'bitmomo_pro_checkout_url', '' );
	}

	return apply_filters( 'bitmomo_pro_checkout_url', $url );
}

function bitmomo_pro_is_valid_checkout_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) return false;
	return false !== wp_http_validate_url( $url );
}

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
		esc_html__( 'Where the Bitmomo Pro CTA points. Leave blank until a payment path is finalized — the whitelist remains the public conversion path.', 'bitmomo-pro' )
	);
}

/** The protected Pro dashboard destination used in account/email surfaces. */
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

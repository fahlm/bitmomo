<?php
/**
 * Plugin Name: Bitmomo Pro
 * Plugin URI: https://bitmomo.id
 * Description: Paid-product access layer for Bitmomo Pro. Packages, protects, and delivers the daily Pro brief to entitled subscribers. Does not generate market intelligence — see the bitmomo-ai plugin for that.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_PRO_VERSION', '0.2.0' );
define( 'BITMOMO_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_PRO_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-entitlements.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-briefs.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-shortcodes.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-sales.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-cache.php';
require_once BITMOMO_PRO_DIR . 'includes/class-bitmomo-pro-setup.php';

/**
 * Bootstraps the plugin's responsibilities:
 * - Entitlements: who has paid access, granted/revoked manually for now.
 * - Briefs: the versioned, private daily Pro decision-view content type.
 * - Shortcodes: the protected dashboard rendering surface, gated server-side.
 * - Sales: the public, unprotected sales surface ([bitmomo_pro_sales]).
 * - Cache: application-level no-cache signaling for the protected route.
 * - Setup: optional, idempotent draft-page provisioning for founders/Codex.
 *
 * This plugin is the paid-product/access layer, not the intelligence engine.
 * bitmomo-ai creates/validates intelligence; bitmomo-pro packages, protects,
 * and delivers it. Kept as a separate, independently activatable plugin so
 * it never needs to touch bitmomo-ai's files or the active theme.
 */
function bitmomo_pro_init() {
	Bitmomo_Pro_Entitlements::instance();
	Bitmomo_Pro_Briefs::instance();
	Bitmomo_Pro_Shortcodes::instance();
	Bitmomo_Pro_Sales::instance();
	Bitmomo_Pro_Cache::instance();
	Bitmomo_Pro_Setup::instance();
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
 * can override this without touching template code. When empty, callers
 * must fail gracefully rather than render a dead link — see
 * Bitmomo_Pro_Shortcodes::checkout_cta() and Bitmomo_Pro_Sales::render_cta().
 */
function bitmomo_pro_get_checkout_url() {
	if ( defined( 'BITMOMO_PRO_CHECKOUT_URL' ) && BITMOMO_PRO_CHECKOUT_URL ) {
		$url = BITMOMO_PRO_CHECKOUT_URL;
	} else {
		$url = get_option( 'bitmomo_pro_checkout_url', '' );
	}
	return apply_filters( 'bitmomo_pro_checkout_url', $url );
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

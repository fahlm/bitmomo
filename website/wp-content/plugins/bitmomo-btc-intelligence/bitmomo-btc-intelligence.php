<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: Public /btc-intelligence/ product surface for current BTC context, session briefs, history, Decision Ledger, delayed Pro proof, and accountable evaluation. Presentation-only: consumes public-safe/read-only boundaries and never recalculates engine logic or exposes current protected Bitmomo Pro fields.
 * Version: 0.3.8
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.3.8' );
define( 'BITMOMO_BTC_INTELLIGENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_BTC_INTELLIGENCE_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-accountability.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-page.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-setup.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-market-context.php';

/**
 * One renderer owns the public product surface. Public SEO metadata remains
 * owned once by the Bitmomo theme layer; the plugin owns product rendering,
 * accountability proof, market context and conversion only.
 */
function bitmomo_btc_intelligence_init() {
	$page = Bitmomo_Btc_Intelligence_Page::instance();
	remove_filter( 'rank_math/frontend/description', array( $page, 'filter_meta_description' ), 10 );
	remove_action( 'wp_head', array( $page, 'render_meta_description' ), 10 );
	Bitmomo_Btc_Intelligence_Setup::instance();
	Bitmomo_Btc_Intelligence_Market_Context::init();
}
add_action( 'plugins_loaded', 'bitmomo_btc_intelligence_init' );

/**
 * Accountability has its own presentation owner so compact History / Ledger /
 * Track Record geometry does not leak into the canonical design foundation.
 * File hashing keeps browser caches deterministic without bumping the plugin
 * version for presentation-only changes.
 */
function bitmomo_btc_intelligence_accountability_assets() {
	if ( ! is_page( 'btc-intelligence' ) ) {
		return;
	}
	$asset = BITMOMO_BTC_INTELLIGENCE_DIR . 'assets/css/accountability-surface.css';
	if ( ! is_readable( $asset ) ) {
		return;
	}
	$hash = hash_file( 'sha256', $asset );
	wp_enqueue_style(
		'bitmomo-btc-accountability',
		BITMOMO_BTC_INTELLIGENCE_URL . 'assets/css/accountability-surface.css',
		array( 'bitmomo-btc-intelligence' ),
		$hash ? substr( $hash, 0, 16 ) : BITMOMO_BTC_INTELLIGENCE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'bitmomo_btc_intelligence_accountability_assets', 20 );

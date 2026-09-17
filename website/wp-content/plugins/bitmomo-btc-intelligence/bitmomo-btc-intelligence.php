<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: Public /btc-intelligence/ product surface for current BTC context, session briefs, history, Decision Ledger, delayed Pro proof, accountable evaluation, and public-safe Telegram distribution.
 * Version: 0.3.9
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.3.9' );
define( 'BITMOMO_BTC_INTELLIGENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_BTC_INTELLIGENCE_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-accountability.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-page.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-setup.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-market-context.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-telegram-brief.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-telegram-transport.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-telegram-publisher.php';

/**
 * One renderer owns the public product surface. Public SEO metadata remains
 * owned once by the Bitmomo theme layer; the plugin owns product rendering,
 * accountability proof, market context and conversion only.
 *
 * Telegram distribution remains inert unless runtime flags explicitly enable
 * delivery/autopost. It consumes the same public-safe intelligence boundary and
 * creates no second scheduler.
 */
function bitmomo_btc_intelligence_init() {
	$page = Bitmomo_Btc_Intelligence_Page::instance();
	remove_filter( 'rank_math/frontend/description', array( $page, 'filter_meta_description' ), 10 );
	remove_action( 'wp_head', array( $page, 'render_meta_description' ), 10 );
	Bitmomo_Btc_Intelligence_Setup::instance();
	Bitmomo_Btc_Intelligence_Market_Context::init();
	Bitmomo_Btc_Telegram_Transport::register_staging_canary_exception();
	Bitmomo_Btc_Telegram_Publisher::register();
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

	$assets = array(
		'bitmomo-btc-accountability' => array(
			'path' => 'assets/css/accountability-surface.css',
			'deps' => array( 'bitmomo-btc-intelligence' ),
		),
		'bitmomo-btc-launch-trust-polish' => array(
			'path' => 'assets/css/launch-trust-polish.css',
			'deps' => array( 'bitmomo-btc-accountability', 'bitmomo-btc-market-context' ),
		),
	);

	foreach ( $assets as $handle => $definition ) {
		$asset = BITMOMO_BTC_INTELLIGENCE_DIR . $definition['path'];
		if ( ! is_readable( $asset ) ) {
			continue;
		}
		$hash = hash_file( 'sha256', $asset );
		wp_enqueue_style(
			$handle,
			BITMOMO_BTC_INTELLIGENCE_URL . $definition['path'],
			$definition['deps'],
			$hash ? substr( $hash, 0, 16 ) : BITMOMO_BTC_INTELLIGENCE_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'bitmomo_btc_intelligence_accountability_assets', 40 );

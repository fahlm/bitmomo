<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: Public /btc-intelligence/ product surface for current BTC context, history, Decision Ledger, delayed Pro proof, and accountable evaluation. Presentation-only: consumes public-safe/read-only boundaries and never recalculates engine logic or exposes current protected Bitmomo Pro fields.
 * Version: 0.3.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.3.1' );
define( 'BITMOMO_BTC_INTELLIGENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_BTC_INTELLIGENCE_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-accountability.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-page.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-setup.php';

/**
 * One renderer owns the public product surface. Opportunity, provenance,
 * snapshot, history, accountability proof and conversion are composed by the
 * page class; no do_shortcode output mutation or selector-coupled bridge is
 * allowed here.
 */
function bitmomo_btc_intelligence_init() {
	Bitmomo_Btc_Intelligence_Page::instance();
	Bitmomo_Btc_Intelligence_Setup::instance();
}
add_action( 'plugins_loaded', 'bitmomo_btc_intelligence_init' );

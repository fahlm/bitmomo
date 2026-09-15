<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: Public /btc-intelligence/ product surface for current BTC context, session briefs, history, Decision Ledger, delayed Pro proof, and accountable evaluation. Presentation-only: consumes public-safe/read-only boundaries and never recalculates engine logic or exposes current protected Bitmomo Pro fields.
 * Version: 0.3.11
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.3.11' );
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
 * Telegram capability remains fail-closed:
 * - Bitmomo_Btc_Telegram_Brief owns public-safe Telegram text;
 * - Bitmomo_Btc_Telegram_Transport owns verified delivery to @bitmomodaily;
 * - Bitmomo_Btc_Telegram_Publisher subscribes to the existing canonical daily
 *   generation hook at a later priority; it creates no second scheduler;
 * - live posting additionally requires explicit runtime delivery + autopost
 *   flags and the canonical @Bitmomo_id_bot token outside the repository.
 */
function bitmomo_btc_intelligence_init() {
	$page = Bitmomo_Btc_Intelligence_Page::instance();
	remove_filter( 'rank_math/frontend/description', array( $page, 'filter_meta_description' ), 10 );
	remove_action( 'wp_head', array( $page, 'render_meta_description' ), 10 );
	Bitmomo_Btc_Intelligence_Setup::instance();
	Bitmomo_Btc_Intelligence_Market_Context::init();
	Bitmomo_Btc_Telegram_Publisher::register();
}
add_action( 'plugins_loaded', 'bitmomo_btc_intelligence_init' );

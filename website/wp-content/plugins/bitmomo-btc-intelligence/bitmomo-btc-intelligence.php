<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: The public /btc-intelligence/ proof-and-methodology page — how Bitmomo reads BTC, and its track record. Pure presentation/consumption layer: reads the existing public projection (bitmomo-ai) and regime history (bitmomo-regime) shortcode, never recalculates engine logic, never exposes protected Bitmomo Pro values.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.1.0' );
define( 'BITMOMO_BTC_INTELLIGENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_BTC_INTELLIGENCE_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-page.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-setup.php';

/**
 * Bootstraps the plugin's two responsibilities:
 * - Page: the public [bitmomo_btc_intelligence] shortcode, the single
 *   canonical source of truth for the /btc-intelligence/ route's content.
 *   Consumes only already-public data sources (Bitmomo_AI_Intelligence's
 *   free_projection(), and the bitmomo-regime plugin's own
 *   [bitmomo_market_regime_history] shortcode) — never reaches into
 *   Bitmomo_AI_Scorecard (the private P1 evaluation internals) or any
 *   protected Bitmomo Pro class. Sections that depend on a public
 *   evaluation-summary adapter that does not exist yet render an honest
 *   "belum tersedia" boundary state instead of fabricated numbers — see
 *   the class docblock for the full list.
 * - Setup: optional, idempotent draft-page provisioning at the
 *   "btc-intelligence" slug, mirroring Bitmomo_Pro_Setup's pattern
 *   exactly (never overwrites an existing Page, never auto-publishes).
 *
 * This plugin has zero dependency in the other direction: bitmomo-pro,
 * bitmomo-ai, bitmomo-regime, and the theme never require anything from
 * here. It is a pure presentation consumer sitting at the end of the
 * DATA/EVENTS -> intelligence engines -> canonical state -> presentation
 * chain, matching the architecture already established by bitmomo-regime
 * and bitmomo-watchtower.
 */
function bitmomo_btc_intelligence_init() {
	Bitmomo_Btc_Intelligence_Page::instance();
	Bitmomo_Btc_Intelligence_Setup::instance();
}
add_action( 'plugins_loaded', 'bitmomo_btc_intelligence_init' );

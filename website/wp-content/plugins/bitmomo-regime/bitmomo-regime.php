<?php
/**
 * Plugin Name: Bitmomo Market Regime
 * Plugin URI: https://bitmomo.id
 * Description: Deterministic BTC market regime classification engine (Product A). Normalized market inputs in; a regime/confidence/evidence-backed classification and persisted daily history out. Isolated from bitmomo-pro, bitmomo-ai, and the active theme — see the architecture note in includes/class-bitmomo-regime-classifier.php.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-regime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_REGIME_VERSION', '0.2.0' );
define( 'BITMOMO_REGIME_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_REGIME_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-taxonomy.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-config.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-input.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-classifier.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-hysteresis.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-state-store.php';

/**
 * Bootstraps the plugin's responsibilities:
 * - Taxonomy (PR1): the five V1 regimes plus the separate bullish/
 *   neutral/bearish directional-bias axis. Regime is never inferred from
 *   bias or vice versa.
 * - Config (PR1, extended PR2): every classifier AND hysteresis
 *   threshold/weight in one inspectable place.
 * - Input (PR1): the normalized-input contract Codex's runtime feed must
 *   produce, plus validation that never fabricates a partial record.
 * - Classifier (PR1): deterministic, evidence-producing, no LLM, no I/O.
 * - Hysteresis (PR2): prevents regime flip-flopping on tiny daily
 *   fluctuations — pure decision logic, no I/O, no WordPress dependency.
 * - State Store (PR2): the ONLY class here that touches WordPress state.
 *   A private `bm_regime_state` custom post type, one post per
 *   evaluation, APPEND-ONLY (a later evaluation never overwrites an
 *   earlier record's classification) — this is the versioned history
 *   REGIME PR 3's 30-day frontend projection will read from.
 *
 * Deliberately NOT in this PR (see PR3 in the same stack): no
 * shortcodes, no admin diagnostics screen, no 30-day read API shaped for
 * a frontend, and no cron of any kind — evaluate_and_record() exists for
 * a future scheduled job (Codex's runtime work) to call; nothing here
 * fetches market data or runs automatically.
 *
 * No dependency on bitmomo-pro, and none should ever be added —
 * DATA/EVENTS -> intelligence engines (here) -> canonical state ->
 * presentation/Pro consumers, never the reverse.
 */
function bitmomo_regime_init() {
	Bitmomo_Regime_State_Store::instance();
}
add_action( 'plugins_loaded', 'bitmomo_regime_init' );

function bitmomo_regime_activate() {
	// Registers bm_regime_state immediately so it's queryable even before
	// the next plugins_loaded cycle. rewrite is false for this post type
	// so this flush is precautionary, not load-bearing.
	Bitmomo_Regime_State_Store::instance()->register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bitmomo_regime_activate' );

function bitmomo_regime_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bitmomo_regime_deactivate' );

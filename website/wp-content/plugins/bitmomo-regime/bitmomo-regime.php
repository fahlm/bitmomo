<?php
/**
 * Plugin Name: Bitmomo Market Regime
 * Plugin URI: https://bitmomo.id
 * Description: Deterministic BTC market regime classification engine (Product A). Normalized market inputs in; a regime/confidence/evidence-backed classification out. Isolated from bitmomo-pro, bitmomo-ai, and the active theme — see the architecture note in includes/class-bitmomo-regime-classifier.php.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-regime
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_REGIME_VERSION', '0.1.0' );
define( 'BITMOMO_REGIME_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_REGIME_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-taxonomy.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-config.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-input.php';
require_once BITMOMO_REGIME_DIR . 'includes/class-bitmomo-regime-classifier.php';

/**
 * REGIME PR 1 scope: taxonomy + normalized input contract + deterministic
 * classifier only.
 *
 * - Taxonomy: the five V1 regimes (accumulation/expansion/distribution/
 *   capitulation/transition) plus the separate bullish/neutral/bearish
 *   directional-bias axis. Regime is never inferred from bias or vice
 *   versa.
 * - Config: every classifier threshold/weight in one inspectable place.
 * - Input: the normalized-input contract Codex's runtime feed must
 *   produce, plus validation that never fabricates a partial record.
 * - Classifier: deterministic, evidence-producing, no LLM, no I/O.
 *
 * Deliberately NOT in this PR (see PR2/PR3 in the same stack):
 * - No persistence / daily history storage.
 * - No hysteresis between evaluations (nothing here remembers "yesterday").
 * - No shortcodes, no admin diagnostics screen, no WordPress hooks at all.
 *
 * These are pure PHP domain classes, directly unit testable without a WP
 * install (see tests/) — this bootstrap only makes them loadable as a
 * plugin. No dependency on bitmomo-pro, and none should ever be added —
 * DATA/EVENTS -> intelligence engines (here) -> canonical state ->
 * presentation/Pro consumers, never the reverse.
 */

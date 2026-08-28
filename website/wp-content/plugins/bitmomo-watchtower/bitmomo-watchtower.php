<?php
/**
 * Plugin Name: Bitmomo Watchtower
 * Plugin URI: https://bitmomo.id
 * Description: Deterministic 24/7 material-change detection engine (Product B). Canonical event candidate schema and a deterministic, no-LLM materiality engine in this PR; dedup/clustering, current-thesis state, state transitions, a selective analyst router, and a provider-neutral alert outbox follow in later PRs. Isolated from bitmomo-regime, bitmomo-pro, bitmomo-ai, and the active theme.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-watchtower
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'BITMOMO_WATCHTOWER_VERSION', '0.1.0' );
define( 'BITMOMO_WATCHTOWER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_WATCHTOWER_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_WATCHTOWER_DIR . 'includes/class-bitmomo-watchtower-taxonomy.php';
require_once BITMOMO_WATCHTOWER_DIR . 'includes/class-bitmomo-watchtower-config.php';
require_once BITMOMO_WATCHTOWER_DIR . 'includes/class-bitmomo-watchtower-event-input.php';
require_once BITMOMO_WATCHTOWER_DIR . 'includes/class-bitmomo-watchtower-materiality-engine.php';

/**
 * WATCHTOWER PR 1 scope, exactly:
 * - Taxonomy: the 8 domains (MARKET/DERIVATIVES/MACRO/GEOPOLITICS/
 *   REGULATION/NEWS/SENTIMENT/DATA_QUALITY), the controlled 15-type event
 *   registry, and the 4 materiality policy bands.
 * - Event Input: the canonical, normalized event candidate schema any
 *   future detector must produce — fails closed, never fabricates a
 *   partial record, cross-checks a supplied domain against the registry.
 * - Materiality Engine: deterministic, no-LLM, no I/O. Six named,
 *   independently-weighted dimensions (weights enforced to sum to 100)
 *   combine into a 0-100 score, a full reason breakdown, and one of four
 *   policy bands.
 *
 * Deliberately NOT in this PR (see the suggested WATCHTOWER PR 2-4
 * structure): no live data fetchers of any kind (no market/derivatives/
 * macro/news/sentiment feed, no cron), no deduplication/clustering, no
 * current-thesis/state model, no state transition engine, no selective
 * analyst router (and no LLM call anywhere), no alert outbox, no
 * Telegram, no admin UI. Every class here is pure PHP with nothing to
 * hook into WordPress yet — bitmomo_watchtower_init() exists as the
 * single place later PRs' WordPress-touching classes (a state store,
 * analogous to Bitmomo_Regime_State_Store) will register themselves.
 *
 * No dependency on bitmomo-regime, bitmomo-pro, or bitmomo-ai, and none
 * should ever be added. This plugin is a fully isolated intelligence
 * engine — Bitmomo Pro and any future consumer read its output, they
 * never feed into it.
 */
function bitmomo_watchtower_init() {
	// Intentionally empty in this PR — see the docblock above.
}
add_action( 'plugins_loaded', 'bitmomo_watchtower_init' );

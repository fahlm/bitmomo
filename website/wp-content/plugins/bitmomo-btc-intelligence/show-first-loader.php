<?php
/**
 * Temporary isolated loader for Show-First V1 development branch.
 *
 * Loaded from the plugin bootstrap in this branch only. Keeping the feature in
 * its own file makes the presentation experiment easy to review or revert
 * without touching intelligence generation or public adapter boundaries.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-show-first.php';
Bitmomo_Btc_Intelligence_Show_First::instance();

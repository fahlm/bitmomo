<?php
/**
 * Plugin Name: Bitmomo BTC Intelligence
 * Plugin URI: https://bitmomo.id
 * Description: Public /btc-intelligence/ product surface for current BTC context, history, Decision Ledger, delayed Pro proof, and accountable evaluation. Presentation-only: consumes public-safe/read-only boundaries and never recalculates engine logic or exposes current protected Bitmomo Pro fields.
 * Version: 0.3.4
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bitmomo
 * Text Domain: bitmomo-btc-intelligence
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITMOMO_BTC_INTELLIGENCE_VERSION', '0.3.4' );
define( 'BITMOMO_BTC_INTELLIGENCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITMOMO_BTC_INTELLIGENCE_URL', plugin_dir_url( __FILE__ ) );

require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-accountability.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-page.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-setup.php';
require_once BITMOMO_BTC_INTELLIGENCE_DIR . 'includes/class-bitmomo-btc-intelligence-market-context.php';

/**
 * Keep the public terminal on the same institutional terminology as the
 * homepage without touching engine/data contracts. This filter is scoped to
 * the BTC Intelligence shortcode output only.
 */
function bitmomo_btc_intelligence_public_copy( $output, $tag, $attr, $m ) {
	if ( 'bitmomo_btc_intelligence' !== $tag ) {
		return $output;
	}

	return strtr(
		$output,
		array(
			'>ARAH<' => '>BIAS<',
			'>KEYAKINAN<' => '>CONFIDENCE<',
			'MELESET' => 'TIDAK SESUAI',
			'TAK DINILAI' => 'BELUM DINILAI',
			'Pembacaan arah sedang ditahan sampai data memenuhi standar kualitas Bitmomo.' => 'Analisis arah belum dipublikasikan karena data belum memenuhi standar kualitas Bitmomo.',
			'condongnya bukti pasar' => 'arah dominan data pasar',
			'konsistensi bukti, bukan probabilitas harga' => 'konsistensi bukti; bukan probabilitas pergerakan harga',
			'Aktivitas pasar sedang menunggu data yang layak.' => 'Data aktivitas pasar belum memenuhi standar publikasi.',
			'Lebih aktif dari kondisi normal 14 hari.' => 'Aktivitas pasar berada di atas kondisi normal 14 hari.',
			'Di sekitar kondisi normal 14 hari.' => 'Aktivitas pasar berada di sekitar kondisi normal 14 hari.',
			'Lebih tenang dari kondisi normal 14 hari.' => 'Aktivitas pasar berada di bawah kondisi normal 14 hari.',
		)
	);
}
add_filter( 'do_shortcode_tag', 'bitmomo_btc_intelligence_public_copy', 20, 4 );

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

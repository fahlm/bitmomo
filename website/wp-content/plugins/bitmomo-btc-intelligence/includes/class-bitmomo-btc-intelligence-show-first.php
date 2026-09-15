<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Presentation-only Show-First layer for the public BTC Intelligence page.
 *
 * This intentionally does not read or transform intelligence data. It only
 * loads a visual hierarchy override after the canonical terminal stylesheet.
 */
final class Bitmomo_Btc_Intelligence_Show_First {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 30 );
	}

	public function enqueue() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_btc_intelligence' ) ) {
			return;
		}

		wp_enqueue_style(
			'bitmomo-btc-intelligence-show-first',
			BITMOMO_BTC_INTELLIGENCE_URL . 'assets/css/bitmomo-btc-intelligence-show-first.css',
			array( 'bitmomo-btc-intelligence' ),
			BITMOMO_BTC_INTELLIGENCE_VERSION . '-show-first-v1'
		);
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache hardening for visitor-dependent Bitmomo Pro routes.
 *
 * Protected dashboard/account routes vary by identity and entitlement and must
 * never be served from a shared page cache. During the founding-whitelist
 * phase, public conversion surfaces also carry WordPress nonces in localized
 * JS and form markup. A cached whitelist page can therefore outlive its nonce
 * and fail at submit time, so those surfaces are non-cacheable while checkout
 * is unavailable. Once checkout is configured and the whitelist widget stops
 * rendering, the public sales surface can return to normal cache behaviour.
 */
class Bitmomo_Pro_Cache {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Priority 1: run before most caching plugins act on template_redirect.
		add_action( 'template_redirect', array( $this, 'maybe_prevent_caching' ), 1 );
	}

	public function maybe_prevent_caching() {
		if ( $this->is_whitelist_conversion_surface() ) {
			$this->send_no_cache_signals();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$protected_shortcodes = array( 'bitmomo_pro_dashboard', 'bitmomo_pro_account' );
		foreach ( $protected_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$this->send_no_cache_signals();
				return;
			}
		}
	}

	/**
	 * Public pages are cache-safe only after checkout replaces the whitelist.
	 * Home/front-page is included because the founding widget is rendered there
	 * without requiring an explicit shortcode in the page body.
	 */
	private function is_whitelist_conversion_surface() {
		if ( ! function_exists( 'bitmomo_pro_get_checkout_url' ) || ! empty( bitmomo_pro_get_checkout_url() ) ) {
			return false;
		}

		if ( is_front_page() || is_home() ) {
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'bitmomo_pro_sales' )
			|| has_shortcode( $post->post_content, 'bitmomo_pro_whitelist' );
	}

	/**
	 * Sends application- and LiteSpeed-level no-cache signals.
	 */
	private function send_no_cache_signals() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		// LiteSpeed also consumes this hook before response headers are finalized.
		do_action( 'litespeed_control_set_nocache', 'Bitmomo visitor-specific or nonce-bearing surface' );

		if ( ! headers_sent() ) {
			header( 'Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0' );
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
	}
}

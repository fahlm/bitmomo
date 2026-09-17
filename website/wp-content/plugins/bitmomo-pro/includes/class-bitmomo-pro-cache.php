<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache hardening for user-dependent Pro routes and the Founding Whitelist.
 *
 * Protected account/dashboard surfaces must never be shared between visitors.
 * Whitelist surfaces are also non-cacheable while checkout is not configured,
 * because the rendered form contains a WordPress nonce. Serving a stale cached
 * nonce can make launch traffic fail even though the form itself is healthy.
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
		// Homepage renders the Founding Whitelist directly from the theme while
		// checkout is unavailable, so it must not serve a cached nonce.
		if ( is_front_page() && function_exists( 'bitmomo_pro_get_checkout_url' ) && '' === bitmomo_pro_get_checkout_url() ) {
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

		// Pro/whitelist acquisition pages are safe to cache only after the live
		// whitelist form disappears. Until checkout exists, their HTML carries a
		// nonce and therefore must be generated per request.
		$whitelist_shortcodes = array( 'bitmomo_pro_sales', 'bitmomo_pro_whitelist' );
		if ( function_exists( 'bitmomo_pro_get_checkout_url' ) && '' === bitmomo_pro_get_checkout_url() ) {
			foreach ( $whitelist_shortcodes as $shortcode ) {
				if ( has_shortcode( $post->post_content, $shortcode ) ) {
					$this->send_no_cache_signals();
					return;
				}
			}
		}
	}

	/** Send cache-layer opt-out signals without assuming one vendor. */
	private function send_no_cache_signals() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( ! headers_sent() ) {
			header( 'Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0' );
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
	}
}

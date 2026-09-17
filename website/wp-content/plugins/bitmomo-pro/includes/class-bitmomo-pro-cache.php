<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page-cache hardening for user-dependent and nonce-bearing Pro surfaces.
 *
 * Protected account/dashboard routes vary by visitor identity and entitlement.
 * Founding Whitelist surfaces are public, but they render WordPress nonces into
 * HTML. A cached copy can therefore outlive the nonce and make a valid signup
 * fail at launch. Any page that can render the whitelist form must be treated
 * as non-cacheable while the whitelist path is active.
 *
 * This class only sends application-level no-cache signals. Exact staging and
 * production acceptance must still prove that the upstream LiteSpeed/Hostinger
 * layer honors them.
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
		if ( ! is_singular() && ! is_front_page() ) {
			return;
		}

		// The homepage directly renders Bitmomo_Pro_Whitelist::render_widget()
		// while checkout is unavailable, so there is no shortcode in post_content
		// for a cache layer to detect.
		if ( is_front_page() && $this->whitelist_is_live() ) {
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

		$protected_shortcodes = array(
			'bitmomo_pro_dashboard',
			'bitmomo_pro_account',
			'bitmomo_pro_whitelist',
		);

		foreach ( $protected_shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				$this->send_no_cache_signals();
				return;
			}
		}

		// [bitmomo_pro_sales] renders the whitelist widget when checkout is not
		// configured. During that state its HTML contains a nonce and must not be
		// shared from page cache. Once checkout is configured, the page can return
		// to its ordinary public-cache policy.
		if ( has_shortcode( $post->post_content, 'bitmomo_pro_sales' ) && $this->whitelist_is_live() ) {
			$this->send_no_cache_signals();
		}
	}

	private function whitelist_is_live() {
		if ( ! class_exists( 'Bitmomo_Pro_Whitelist' ) ) {
			return false;
		}

		$checkout_url = function_exists( 'bitmomo_pro_get_checkout_url' )
			? trim( (string) bitmomo_pro_get_checkout_url() )
			: '';

		return '' === $checkout_url;
	}

	/**
	 * Send every no-cache signal that is safe at application level.
	 */
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

		// Native LiteSpeed hook, harmless when the plugin is absent.
		do_action( 'litespeed_control_set_nocache', 'Bitmomo nonce-bearing whitelist surface' );
	}
}

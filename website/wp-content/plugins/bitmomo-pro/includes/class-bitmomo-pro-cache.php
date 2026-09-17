<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Launch runtime hardening for cache-sensitive and non-production surfaces.
 *
 * Protected account/dashboard routes vary by visitor identity and entitlement.
 * Founding Whitelist surfaces are public, but they render WordPress nonces into
 * HTML. A cached copy can therefore outlive the nonce and make a valid signup
 * fail at launch. Any page that can render the whitelist form must be treated
 * as non-cacheable while the whitelist path is active.
 *
 * The same class owns the release-critical indexing boundary: only requests to
 * bitmomo.id / www.bitmomo.id may be indexable. Any staging/preview host gets
 * an application-level robots noindex policy plus X-Robots-Tag. This uses the
 * actual request host rather than the WordPress Site URL so a staging DB copied
 * from production cannot accidentally make a preview hostname indexable.
 *
 * Exact staging and production acceptance must still prove upstream behavior.
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

		// Defense in depth for staging/preview search-engine isolation.
		add_filter( 'wp_robots', array( $this, 'nonproduction_wp_robots' ), PHP_INT_MAX );
		add_filter( 'rank_math/frontend/robots', array( $this, 'nonproduction_rank_math_robots' ), PHP_INT_MAX );
		add_filter( 'robots_txt', array( $this, 'nonproduction_robots_txt' ), PHP_INT_MAX, 2 );
		add_action( 'send_headers', array( $this, 'send_nonproduction_robots_header' ), PHP_INT_MAX );
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

	/** Return true only for the canonical public production hosts. */
	private function is_production_request_host() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		if ( '' === trim( $host ) ) {
			$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		}
		$host = strtolower( trim( preg_replace( '/:\d+$/', '', $host ) ) );
		return in_array( $host, array( 'bitmomo.id', 'www.bitmomo.id' ), true );
	}

	public function nonproduction_wp_robots( $robots ) {
		if ( $this->is_production_request_host() ) return $robots;
		$robots = is_array( $robots ) ? $robots : array();
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
		$robots['noarchive'] = true;
		return $robots;
	}

	public function nonproduction_rank_math_robots( $robots ) {
		if ( $this->is_production_request_host() ) return $robots;
		$robots = is_array( $robots ) ? $robots : array();
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex'] = 'noindex';
		$robots['nofollow'] = 'nofollow';
		$robots['noarchive'] = 'noarchive';
		return $robots;
	}

	public function nonproduction_robots_txt( $output, $public ) {
		if ( $this->is_production_request_host() ) return $output;
		return "User-agent: *\nDisallow: /\n";
	}

	public function send_nonproduction_robots_header() {
		if ( $this->is_production_request_host() || headers_sent() ) return;
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	/** Send every no-cache signal that is safe at application level. */
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

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small public trust-boundary controls required by the Whitelist V1 launch.
 *
 * - pages that render the pre-checkout whitelist are never page-cached, so a
 *   WordPress nonce cannot be served from an old LiteSpeed page cache;
 * - unauthenticated visitors cannot enumerate WordPress users through the
 *   core /wp/v2/users REST routes.
 *
 * This class does not alter authenticated admin/editor REST access and stops
 * disabling cache automatically once a real checkout URL replaces whitelist.
 */
final class Bitmomo_Pro_Public_Security {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'disable_cache_for_whitelist_surface' ), PHP_INT_MIN );
		add_filter( 'rest_endpoints', array( $this, 'hide_public_user_routes' ), PHP_INT_MAX );
	}

	public function disable_cache_for_whitelist_surface() {
		if ( ! function_exists( 'bitmomo_pro_get_checkout_url' ) || '' !== bitmomo_pro_get_checkout_url() ) {
			return;
		}

		global $post;
		$has_whitelist = is_front_page() || is_home();
		if ( ! $has_whitelist && is_a( $post, 'WP_Post' ) ) {
			$content = (string) $post->post_content;
			$has_whitelist = has_shortcode( $content, 'bitmomo_pro_sales' ) || has_shortcode( $content, 'bitmomo_pro_whitelist' );
		}

		if ( ! $has_whitelist ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}

		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'Bitmomo whitelist nonce freshness' );
	}

	public function hide_public_user_routes( $endpoints ) {
		if ( is_user_logged_in() || ! is_array( $endpoints ) ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( '/wp/v2/users' === $route || 0 === strpos( $route, '/wp/v2/users/' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}
}

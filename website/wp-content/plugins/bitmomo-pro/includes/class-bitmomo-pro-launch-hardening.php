<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Launch-critical public hardening shared by the whitelist acquisition surface.
 *
 * This class deliberately owns safeguards that must not depend on a Hostinger
 * dashboard toggle: stale-nonce cache prevention, staging indexing isolation,
 * REST user-enumeration protection, and canonical public site identity.
 */
final class Bitmomo_Pro_Launch_Hardening {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'prevent_whitelist_page_cache' ), 0 );
		add_action( 'send_headers', array( $this, 'send_nonproduction_robots_header' ), 0 );
		add_filter( 'wp_robots', array( $this, 'filter_nonproduction_robots' ), 99 );
		add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rank_math_nonproduction_robots' ), 99 );
		add_filter( 'rest_endpoints', array( $this, 'restrict_public_user_routes' ), 99 );
		add_filter( 'document_title_parts', array( $this, 'canonicalize_site_title' ), 99 );
	}

	/**
	 * Whitelist HTML embeds a WordPress nonce. Page caching that HTML can serve
	 * an expired nonce to a later visitor, so whitelist acquisition pages must
	 * not be cached while checkout is intentionally unavailable.
	 */
	public function prevent_whitelist_page_cache() {
		if ( function_exists( 'bitmomo_pro_get_checkout_url' ) && '' !== bitmomo_pro_get_checkout_url() ) {
			return;
		}
		if ( ! is_front_page() && ! is_page( 'pro' ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'Bitmomo founding whitelist nonce safety' );
	}

	/** Never let a staging hostname become indexable through theme/SEO drift. */
	public function send_nonproduction_robots_header() {
		if ( self::is_production_host() || headers_sent() ) {
			return;
		}
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	public function filter_nonproduction_robots( $robots ) {
		if ( self::is_production_host() ) {
			return $robots;
		}
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
		$robots['noarchive'] = true;
		return $robots;
	}

	public function filter_rank_math_nonproduction_robots( $robots ) {
		if ( self::is_production_host() ) {
			return $robots;
		}
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex'] = 'noindex';
		$robots['nofollow'] = 'nofollow';
		$robots['noarchive'] = 'noarchive';
		return $robots;
	}

	/** Anonymous visitors do not need WordPress author-enumeration routes. */
	public function restrict_public_user_routes( $endpoints ) {
		if ( current_user_can( 'list_users' ) ) {
			return $endpoints;
		}
		foreach ( array_keys( (array) $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users(?:/|$)#', (string) $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/** Generic pages must never expose a staging hostname as the public brand. */
	public function canonicalize_site_title( $parts ) {
		$parts = is_array( $parts ) ? $parts : array();
		$parts['site'] = 'Bitmomo';
		return $parts;
	}

	private static function is_production_host() {
		$host = strtolower( trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) );
		$allowed = array( 'bitmomo.id', 'www.bitmomo.id' );
		$allowed = (array) apply_filters( 'bitmomo_pro_production_hosts', $allowed );
		return in_array( $host, array_map( 'strtolower', array_map( 'strval', $allowed ) ), true );
	}
}

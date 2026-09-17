<?php
/**
 * Bitmomo staging-only side-effect and indexing guard.
 *
 * Install as wp-content/mu-plugins/bitmomo-staging-safety.php. This file is
 * deliberately outside the managed production artifact.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITMOMO_STAGING_SIDE_EFFECTS_DISABLED', true );

if ( ! defined( 'BITMOMO_AI_AUTO_PUBLISH' ) ) {
	define( 'BITMOMO_AI_AUTO_PUBLISH', false );
}

/*
 * Staging must never become a search-index candidate, regardless of Rank Math,
 * individual post meta, or a copied WordPress "discourage search engines"
 * option. This guard is staging-only and is never packaged into production.
 */
add_action( 'send_headers', static function () {
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
}, PHP_INT_MIN );

add_filter( 'wp_robots', static function ( $robots ) {
	$robots['noindex']   = true;
	$robots['nofollow']  = true;
	$robots['noarchive'] = true;
	unset( $robots['index'], $robots['follow'] );
	return $robots;
}, PHP_INT_MAX );

add_filter( 'robots_txt', static function () {
	return "User-agent: *\nDisallow: /\n";
}, PHP_INT_MAX );

add_filter( 'pre_wp_mail', static function () {
	return false;
}, PHP_INT_MIN );

add_filter( 'pre_http_request', static function ( $preempt, $args ) {
	$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );
	if ( in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return $preempt;
	}

	return new WP_Error( 'bitmomo_staging_outbound_write_disabled', 'Outbound HTTP writes are disabled on Bitmomo staging.' );
}, PHP_INT_MIN, 2 );

add_filter( 'rest_pre_dispatch', static function ( $result, $server, $request ) {
	$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
	if ( 0 === strpos( $route, '/bitmomo-ai/v1/tradingview/' ) ) {
		return new WP_Error( 'bitmomo_staging_webhook_disabled', 'The AI write webhook is disabled on Bitmomo staging.', array( 'status' => 503 ) );
	}

	return $result;
}, PHP_INT_MIN, 3 );

add_filter( 'bitmomo_pro_checkout_url', '__return_empty_string', PHP_INT_MAX );

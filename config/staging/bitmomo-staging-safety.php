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

// Market Pulse is a launch-critical read-only capability. Staging must exercise
// the same 15-minute evaluation path visitors will rely on at launch. The
// outbound-write guard below still blocks non-GET/HEAD side effects.
if ( ! defined( 'BITMOMO_AI_OPPORTUNITY_ENABLED' ) ) {
	define( 'BITMOMO_AI_OPPORTUNITY_ENABLED', true );
}

// Staging is never an indexable publishing origin. Enforce this independently
// of theme/SEO-plugin settings so a qualified Research article cannot
// accidentally override staging's noindex boundary.
add_filter( 'wp_robots', static function ( $robots ) {
	unset( $robots['index'] );
	$robots['noindex'] = true;
	$robots['nofollow'] = true;
	return $robots;
}, PHP_INT_MAX );

add_filter( 'rank_math/frontend/robots', static function () {
	return array( 'noindex', 'nofollow', 'noarchive' );
}, PHP_INT_MAX );

add_filter( 'robots_txt', static function () {
	return "User-agent: *\nDisallow: /\n";
}, PHP_INT_MAX, 2 );

add_action( 'send_headers', static function () {
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}
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

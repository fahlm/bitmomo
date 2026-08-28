<?php
/**
 * Minimal WordPress function/constant stubs — just enough to load and
 * exercise the pure logic in class-bitmomo-pro-brief-readiness.php and
 * class-bitmomo-pro-briefs.php outside a real WordPress install.
 *
 * NOT a WP testing framework substitute. Real save_post/wp_update_post
 * DB-transition behavior (test cases 9 and 10, which need an actual post
 * table and a real publish request) cannot be verified this way — those
 * are called out explicitly as staging-only in the PR body. Everything
 * stubbed here is either a pure PHP-native check (in_array, is_numeric)
 * or a trivial i18n passthrough.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

// Fixed "now" for deterministic tests, overridable per-test.
$GLOBALS['__wp_stub_now'] = time();

function current_time( $type = 'timestamp' ) {
	return $GLOBALS['__wp_stub_now'];
}

function __( $text, $domain = 'default' ) {
	return $text;
}
function _n( $single, $plural, $number, $domain = 'default' ) {
	return 1 === (int) $number ? $single : $plural;
}
function esc_html__( $text, $domain = 'default' ) {
	return $text;
}
function esc_html_e( $text, $domain = 'default' ) {
	echo $text;
}
function esc_html( $text ) {
	return $text;
}
function esc_attr( $text ) {
	return $text;
}
function esc_url( $url ) {
	return $url;
}
function esc_url_raw( $url ) {
	return $url;
}
function esc_textarea( $text ) {
	return $text;
}
function selected( $a, $b, $echo = true ) {
	return $a === $b ? 'selected' : '';
}
function sanitize_text_field( $v ) {
	return trim( (string) $v );
}
function sanitize_textarea_field( $v ) {
	return trim( (string) $v );
}
function sanitize_key( $v ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $v ) );
}
function wp_unslash( $v ) {
	return $v;
}

// No-op action/filter/meta-box registration — none of it runs at
// `new` time in a way that matters for these pure-logic tests, since we
// never fire save_post/admin hooks here.
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function remove_action( ...$args ) {}
function add_meta_box( ...$args ) {}
function register_post_type( ...$args ) {}

// Post-meta store stub, keyed by [post_id][meta_key] => value, for the
// evaluate_post()/admin_state_for_post()/get_current_brief_for_display()
// integration-style tests (still no real DB — just an in-memory array
// standing in for it).
$GLOBALS['__wp_stub_postmeta'] = array();
$GLOBALS['__wp_stub_posts']    = array(); // id => array( 'post_status' => ..., 'post_title' => ... )

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['__wp_stub_postmeta'][ $post_id ][ $key ] = $value;
	return true;
}
function get_post_meta( $post_id, $key, $single = false ) {
	return $GLOBALS['__wp_stub_postmeta'][ $post_id ][ $key ] ?? '';
}
function get_the_title( $post ) {
	return $GLOBALS['__wp_stub_posts'][ $post->ID ]['post_title'] ?? '';
}
function get_the_date( $format, $post ) {
	return '';
}
function get_posts( $args ) {
	// Returns fake WP_Post-like objects for posts matching post_status in
	// $args['post_status'], sorted by our stub's insertion "date" (we just
	// use descending post_id as a stand-in for "most recently published"
	// since these tests insert in chronological order).
	$status_filter = is_array( $args['post_status'] ) ? $args['post_status'] : array( $args['post_status'] );
	$matches = array();
	foreach ( $GLOBALS['__wp_stub_posts'] as $id => $post ) {
		if ( in_array( $post['post_status'], $status_filter, true ) ) {
			$matches[] = $id;
		}
	}
	rsort( $matches );
	$limit = isset( $args['posts_per_page'] ) ? $args['posts_per_page'] : -1;
	if ( $limit > 0 ) {
		$matches = array_slice( $matches, 0, $limit );
	}

	if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
		return $matches;
	}

	$out = array();
	foreach ( $matches as $id ) {
		$out[] = (object) array( 'ID' => $id );
	}
	return $out;
}

function stub_insert_post( $id, $status, $title = '' ) {
	$GLOBALS['__wp_stub_posts'][ $id ] = array( 'post_status' => $status, 'post_title' => $title );
}


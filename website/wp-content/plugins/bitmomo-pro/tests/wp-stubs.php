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
	if ( 'mysql' === $type ) {
		return date( 'Y-m-d H:i:s', $GLOBALS['__wp_stub_now'] );
	}
	if ( 'timestamp' === $type ) {
		return $GLOBALS['__wp_stub_now'];
	}
	// Anything else (e.g. 'Y-m-d') is treated as a date() format string,
	// matching WordPress's own current_time() contract.
	return date( $type, $GLOBALS['__wp_stub_now'] );
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

/**
 * ==========================================================================
 * Added for PR "pro-revenue-activation-p0": stubs for
 * class-bitmomo-pro-activation.php, class-bitmomo-pro-entitlement-service.php,
 * class-bitmomo-pro-entitlements.php and bitmomo-pro.php's checkout-URL
 * helpers. Purely additive — nothing above this point was changed, so the
 * existing brief-readiness test is unaffected.
 * ==========================================================================
 */

// --- Options -----------------------------------------------------------

$GLOBALS['__wp_stub_options'] = array();

function get_option( $name, $default = false ) {
	return $GLOBALS['__wp_stub_options'][ $name ] ?? $default;
}
function update_option( $name, $value ) {
	$GLOBALS['__wp_stub_options'][ $name ] = $value;
	return true;
}

// --- Filters / actions ---------------------------------------------------
// No filters are ever registered in these tests, so apply_filters is a
// pure passthrough of $value; do_action stays a no-op like add_action.

function apply_filters( $tag, $value, ...$args ) {
	return $value;
}
function do_action( ...$args ) {}

// --- URL validation ------------------------------------------------------
// Stand-in for WordPress core's wp_http_validate_url(): true only for a
// well-formed absolute http(s) URL with a host. Not exhaustive, but
// matches the cases bitmomo_pro_is_valid_checkout_url() actually gates on.

function wp_http_validate_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}
	$parts = parse_url( $url );
	if ( false === $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}
	return $url;
}

// --- Email / user validation ---------------------------------------------

function is_email( $email ) {
	return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
}
function sanitize_email( $email ) {
	return sanitize_text_field( (string) $email );
}
function sanitize_user( $username, $strict = false ) {
	return preg_replace( '/[^a-zA-Z0-9_\.\-@]/', '', (string) $username );
}

// --- WP_Error --------------------------------------------------------------

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function get_error_code() {
		return $this->code;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

// --- User store ------------------------------------------------------------
// In-memory stand-in for wp_users + usermeta, keyed by integer user ID.

$GLOBALS['__wp_stub_users']      = array(); // id => array( ID, user_login, user_email, display_name )
$GLOBALS['__wp_stub_usermeta']   = array(); // id => array( key => value )
$GLOBALS['__wp_stub_next_user_id'] = 1;

function stub_reset_users() {
	$GLOBALS['__wp_stub_users']        = array();
	$GLOBALS['__wp_stub_usermeta']     = array();
	$GLOBALS['__wp_stub_next_user_id'] = 1;
}

function username_exists( $username ) {
	foreach ( $GLOBALS['__wp_stub_users'] as $user ) {
		if ( $user['user_login'] === $username ) {
			return $user['ID'];
		}
	}
	return false;
}

function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	return substr( bin2hex( random_bytes( max( 1, (int) ceil( $length / 2 ) ) ) ), 0, $length );
}

/**
 * @return int|WP_Error
 */
function wp_insert_user( $args ) {
	$email = isset( $args['user_email'] ) ? $args['user_email'] : '';
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', 'Invalid email.' );
	}
	foreach ( $GLOBALS['__wp_stub_users'] as $user ) {
		if ( $user['user_email'] === $email ) {
			return new WP_Error( 'existing_user_email', 'A user with that email already exists.' );
		}
	}

	$id = $GLOBALS['__wp_stub_next_user_id']++;
	$GLOBALS['__wp_stub_users'][ $id ] = array(
		'ID'           => $id,
		'user_login'   => isset( $args['user_login'] ) ? $args['user_login'] : ( 'user' . $id ),
		'user_email'   => $email,
		'display_name' => isset( $args['display_name'] ) ? $args['display_name'] : '',
	);
	$GLOBALS['__wp_stub_usermeta'][ $id ] = array();

	return $id;
}

function stub_user_object( $id ) {
	if ( ! isset( $GLOBALS['__wp_stub_users'][ $id ] ) ) {
		return false;
	}
	return (object) $GLOBALS['__wp_stub_users'][ $id ];
}

/**
 * @param string $field 'id' | 'ID' | 'email' | 'login'
 */
function get_user_by( $field, $value ) {
	if ( in_array( strtolower( $field ), array( 'id' ), true ) ) {
		return stub_user_object( (int) $value );
	}
	$key = 'email' === $field ? 'user_email' : ( 'login' === $field ? 'user_login' : null );
	if ( null === $key ) {
		return false;
	}
	foreach ( $GLOBALS['__wp_stub_users'] as $user ) {
		if ( $user[ $key ] === $value ) {
			return (object) $user;
		}
	}
	return false;
}

function get_userdata( $user_id ) {
	return stub_user_object( (int) $user_id );
}

/**
 * @param array $args Supports the subset used by this plugin:
 *                     meta_key/meta_value and fields=ID (default: objects).
 */
function get_users( $args = array() ) {
	$meta_key   = $args['meta_key'] ?? null;
	$meta_value = $args['meta_value'] ?? null;
	$fields     = $args['fields'] ?? '';

	$matches = array();
	foreach ( $GLOBALS['__wp_stub_users'] as $id => $user ) {
		if ( null !== $meta_key ) {
			$actual = $GLOBALS['__wp_stub_usermeta'][ $id ][ $meta_key ] ?? '';
			if ( (string) $actual !== (string) $meta_value ) {
				continue;
			}
		}
		$matches[] = 'ID' === $fields ? $id : (object) $user;
	}
	return $matches;
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['__wp_stub_usermeta'][ $user_id ][ $key ] = $value;
	return true;
}
function get_user_meta( $user_id, $key, $single = false ) {
	$value = $GLOBALS['__wp_stub_usermeta'][ $user_id ][ $key ] ?? '';
	return $single ? $value : ( '' === $value ? array() : array( $value ) );
}
function delete_user_meta( $user_id, $key ) {
	unset( $GLOBALS['__wp_stub_usermeta'][ $user_id ][ $key ] );
	return true;
}

// --- Mail --------------------------------------------------------------

$GLOBALS['__wp_stub_mail_log'] = array();

function wp_mail( $to, $subject, $body ) {
	$GLOBALS['__wp_stub_mail_log'][] = compact( 'to', 'subject', 'body' );
	return true;
}

// --- Misc no-ops used by classes required for the activation tests -----

function current_user_can( $cap ) {
	return true;
}
function get_current_user_id() {
	return 0;
}
function wp_nonce_field( ...$args ) {}
function submit_button( ...$args ) {}
function add_submenu_page( ...$args ) {}
function admin_url( $path = '' ) {
	return 'http://example.test/wp-admin/' . $path;
}
function set_transient( ...$args ) {}
function get_transient( ...$args ) {
	return false;
}
function delete_transient( ...$args ) {}
function add_query_arg( ...$args ) {
	return '';
}
function wp_safe_redirect( ...$args ) {}

// --- Plugin bootstrap no-ops --------------------------------------------
// Let bitmomo-pro.php itself be require()'d (rather than re-declaring its
// checkout-URL functions here) so the test exercises the real code. None
// of these run anything at require-time beyond definition/registration —
// bitmomo_pro_init() only ever fires on the no-op 'plugins_loaded' hook.

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/' ) . '/';
}
function plugin_dir_url( $file ) {
	return 'http://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}
function register_activation_hook( ...$args ) {}
function register_deactivation_hook( ...$args ) {}
function flush_rewrite_rules( ...$args ) {}
function register_setting( ...$args ) {}
function add_settings_field( ...$args ) {}
function add_shortcode( ...$args ) {}
function shortcode_atts( $defaults, $atts ) {
	return array_merge( $defaults, (array) $atts );
}
function wp_enqueue_style( ...$args ) {}
function wp_enqueue_script( ...$args ) {}
function is_user_logged_in() {
	return false;
}
function wp_login_url( $redirect = '' ) {
	return 'http://example.test/wp-login.php';
}

// Pre-existing gap (not introduced by this PR): parse_data_timestamp() in
// class-bitmomo-pro-brief-readiness.php calls core wp_timezone() for any
// timezone-less date string, which was never stubbed here — that made
// test-bitmomo-pro-brief-readiness.php's own Cases 5-8 uncallable under
// plain `php` CLI. Defaults to UTC, matching a fresh WP install with no
// timezone configured (Settings > General > Timezone unset).
function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}


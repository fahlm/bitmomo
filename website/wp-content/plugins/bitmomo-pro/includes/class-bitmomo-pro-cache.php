<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache hardening for the protected Pro dashboard route.
 *
 * PR #27 flagged that full-page caching in front of a page rendering
 * [bitmomo_pro_dashboard] could serve one visitor's authenticated/paid
 * render to a later, different visitor — that route's output is
 * user-dependent, so it must never be treated as a static, shareable
 * cache artifact. This class signals that at the application level. It
 * cannot verify that Hostinger/LiteSpeed actually honors the signal in
 * production — that verification is Codex's runtime QA job (see PR body).
 *
 * Deliberately scoped to pages/posts containing [bitmomo_pro_dashboard]
 * only. [bitmomo_pro_sales] renders identical generic content for every
 * visitor and is safe to cache, so it is left untouched.
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
		if ( ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( ! has_shortcode( $post->post_content, 'bitmomo_pro_dashboard' ) ) {
			return;
		}

		$this->send_no_cache_signals();
	}

	/**
	 * Sends every no-cache signal that is safe to send from a plugin
	 * without assuming a specific cache product is installed:
	 * - DONOTCACHEPAGE: a page-level cache opt-out constant honored by
	 *   WP Super Cache, W3 Total Cache, and several other cache plugins.
	 * - nocache_headers(): WordPress core helper that sends the standard
	 *   Cache-Control/Pragma/Expires headers marking a response
	 *   non-cacheable and non-storable.
	 * - Cache-Control: private — an explicit signal, in addition to
	 *   nocache_headers(), that this response varies per visitor and must
	 *   never be reused for a different one.
	 * - X-LiteSpeed-Cache-Control: no-cache — LiteSpeed Cache (the layer
	 *   Hostinger commonly runs) recognizes this response header
	 *   directly; harmless to send if LiteSpeed Cache isn't active.
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
	}
}


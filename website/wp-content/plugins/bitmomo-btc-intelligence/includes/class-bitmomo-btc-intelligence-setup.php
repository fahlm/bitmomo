<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional, idempotent page provisioning for the [bitmomo_btc_intelligence]
 * shortcode -- mirrors Bitmomo_Pro_Setup's pattern exactly (same repo
 * convention already used for /pro, /pro-dashboard, /pro/account, /help).
 *
 * Never overwrites an existing Page, never publishes anything
 * automatically. Only offers, from a manual admin screen, to create a
 * *draft* Page at "btc-intelligence" if nothing already occupies that
 * slug. A founder/Codex still reviews, edits, publishes, and adds it to
 * navigation. Nothing here runs on activation.
 */
class Bitmomo_Btc_Intelligence_Setup {

	const NONCE_ACTION = 'bitmomo_btc_intelligence_setup_pages';
	const NONCE_FIELD  = 'bitmomo_btc_intelligence_setup_nonce';
	const SLUG         = 'btc-intelligence';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_bitmomo_btc_intelligence_setup_pages', array( $this, 'handle_create_page' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'BTC Intelligence Setup', 'bitmomo-btc-intelligence' ),
			__( 'BTC Intelligence Setup', 'bitmomo-btc-intelligence' ),
			'manage_options',
			'bitmomo-btc-intelligence-setup',
			array( $this, 'render_setup_page' ),
			'dashicons-analytics'
		);
	}

	public function render_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Bitmomo BTC Intelligence — Setup', 'bitmomo-btc-intelligence' ) . '</h1>';
		echo '<p>' . esc_html__( 'Creates a draft Page containing [bitmomo_btc_intelligence] at /btc-intelligence/ if the slug is free. Never overwrites an existing Page. You still need to review content, publish, and add the Page to navigation yourself.', 'bitmomo-btc-intelligence' ) . '</p>';

		$existing = get_page_by_path( self::SLUG, OBJECT, 'page' );

		echo '<table class="widefat" style="max-width:640px;"><thead><tr><th>' . esc_html__( 'Slug', 'bitmomo-btc-intelligence' ) . '</th><th>' . esc_html__( 'Status', 'bitmomo-btc-intelligence' ) . '</th><th></th></tr></thead><tbody>';
		echo '<tr><td><code>/' . esc_html( self::SLUG ) . '</code></td><td>';

		if ( $existing ) {
			printf(
				'%s (%s) — <a href="%s">%s</a>',
				esc_html__( 'Already exists', 'bitmomo-btc-intelligence' ),
				esc_html( $existing->post_status ),
				esc_url( (string) get_edit_post_link( $existing->ID, '' ) ),
				esc_html__( 'edit', 'bitmomo-btc-intelligence' )
			);
		} else {
			esc_html_e( 'Not created yet', 'bitmomo-btc-intelligence' );
		}
		echo '</td><td>';

		if ( ! $existing ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			echo '<input type="hidden" name="action" value="bitmomo_btc_intelligence_setup_pages" />';
			echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Create draft page', 'bitmomo-btc-intelligence' ) . '</button>';
			echo '</form>';
		}
		echo '</td></tr></tbody></table></div>';
	}

	public function handle_create_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-btc-intelligence' ) );
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-btc-intelligence' ) );
		}

		// Idempotency: never overwrite or duplicate an existing path.
		$existing = get_page_by_path( self::SLUG, OBJECT, 'page' );
		if ( ! $existing ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => __( 'BTC Intelligence', 'bitmomo-btc-intelligence' ),
					'post_name'    => self::SLUG,
					'post_content' => '[bitmomo_btc_intelligence]',
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bitmomo-btc-intelligence-setup' ) );
		exit;
	}
}

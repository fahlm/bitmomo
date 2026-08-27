<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional, idempotent page provisioning for the sales and dashboard
 * shortcodes.
 *
 * This never overwrites an existing Page and never publishes anything
 * automatically — it only offers, from a manual admin screen, to create a
 * *draft* Page at a given slug if nothing already occupies that slug.
 * Codex/founder still review, edit, and publish. Nothing here runs on
 * activation; it is opt-in, one click at a time, and safe to run more
 * than once (re-running does nothing for a slug that already exists).
 */
class Bitmomo_Pro_Setup {

	const NONCE_ACTION = 'bitmomo_pro_setup_pages';
	const NONCE_FIELD  = 'bitmomo_pro_setup_nonce';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_bitmomo_pro_setup_pages', array( $this, 'handle_create_pages' ) );
	}

	private function target_pages() {
		return array(
			'pro'           => array(
				'title'     => __( 'Bitmomo Pro', 'bitmomo-pro' ),
				'shortcode' => '[bitmomo_pro_sales]',
			),
			'pro-dashboard' => array(
				'title'     => __( 'Bitmomo Pro Dashboard', 'bitmomo-pro' ),
				'shortcode' => '[bitmomo_pro_dashboard]',
			),
		);
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE,
			__( 'Bitmomo Pro Setup', 'bitmomo-pro' ),
			__( 'Setup', 'bitmomo-pro' ),
			'manage_options',
			'bitmomo-pro-setup',
			array( $this, 'render_setup_page' )
		);
	}

	public function render_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Bitmomo Pro — Setup', 'bitmomo-pro' ) . '</h1>';
		echo '<p>' . esc_html__( 'Creates a draft Page containing the sales/dashboard shortcode if the slug is free. Never overwrites an existing Page. You still need to review content, publish, and add the Page to navigation yourself.', 'bitmomo-pro' ) . '</p>';

		echo '<table class="widefat" style="max-width:640px;"><thead><tr><th>' . esc_html__( 'Slug', 'bitmomo-pro' ) . '</th><th>' . esc_html__( 'Status', 'bitmomo-pro' ) . '</th><th></th></tr></thead><tbody>';

		foreach ( $this->target_pages() as $slug => $info ) {
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			echo '<tr><td><code>/' . esc_html( $slug ) . '</code></td><td>';
			if ( $existing ) {
				printf(
					'%s (%s) — <a href="%s">%s</a>',
					esc_html__( 'Already exists', 'bitmomo-pro' ),
					esc_html( $existing->post_status ),
					esc_url( (string) get_edit_post_link( $existing->ID, '' ) ),
					esc_html__( 'edit', 'bitmomo-pro' )
				);
			} else {
				esc_html_e( 'Not created yet', 'bitmomo-pro' );
			}
			echo '</td><td>';
			if ( ! $existing ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
				echo '<input type="hidden" name="action" value="bitmomo_pro_setup_pages" />';
				echo '<input type="hidden" name="slug" value="' . esc_attr( $slug ) . '" />';
				echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Create draft page', 'bitmomo-pro' ) . '</button>';
				echo '</form>';
			}
			echo '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	public function handle_create_pages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$slug  = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$pages = $this->target_pages();

		if ( $slug && isset( $pages[ $slug ] ) ) {
			// Idempotency: never overwrite or duplicate an existing slug.
			$existing = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $existing ) {
				wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'draft',
						'post_title'   => $pages[ $slug ]['title'],
						'post_name'    => $slug,
						'post_content' => $pages[ $slug ]['shortcode'],
					)
				);
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE . '&page=bitmomo-pro-setup' ) );
		exit;
	}
}

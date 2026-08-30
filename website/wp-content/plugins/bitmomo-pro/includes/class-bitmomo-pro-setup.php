<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional, idempotent page provisioning for the sales, dashboard, and
 * account shortcodes.
 *
 * This never overwrites an existing Page and never publishes anything
 * automatically — it only offers, from a manual admin screen, to create a
 * *draft* Page at a given slug if nothing already occupies that slug.
 * Codex/founder still review, edit, and publish. Nothing here runs on
 * activation; it is opt-in, one click at a time, and safe to run more
 * than once (re-running does nothing for a slug that already exists).
 *
 * As of PR #34: /pro/account is provisioned as a CHILD page of /pro (not
 * a flat top-level slug), matching the task's requested hierarchical URL.
 * Creating it requires the /pro parent page to already exist — if it
 * doesn't, the row shows a note instead of a create button rather than
 * creating an orphaned/unnested page.
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

	/**
	 * Keyed by a stable, slash-free identifier (safe to round-trip through
	 * a hidden form field via sanitize_key()). 'path' is what
	 * get_page_by_path() checks (WordPress accepts a hierarchical
	 * slash-separated path there). 'parent' is the identifier (not path)
	 * of another entry in this array, or '' for a top-level page.
	 */
	private function target_pages() {
		return array(
			'help'          => array(
				'path'      => 'help',
				'post_name' => 'help',
				'parent'    => '',
				'title'     => __( 'Help Center Bitmomo', 'bitmomo-pro' ),
				'shortcode' => '[bitmomo_help_center]',
			),
			'pro'           => array(
				'path'      => 'pro',
				'post_name' => 'pro',
				'parent'    => '',
				'title'     => __( 'Bitmomo Pro', 'bitmomo-pro' ),
				'shortcode' => '[bitmomo_pro_sales]',
			),
			'pro-dashboard' => array(
				'path'      => 'pro-dashboard',
				'post_name' => 'pro-dashboard',
				'parent'    => '',
				'title'     => __( 'Bitmomo Pro Dashboard', 'bitmomo-pro' ),
				'shortcode' => '[bitmomo_pro_dashboard]',
			),
			'pro-account'   => array(
				'path'      => 'pro/account',
				'post_name' => 'account',
				'parent'    => 'pro',
				'title'     => __( 'Akun Bitmomo Pro Saya', 'bitmomo-pro' ),
				'shortcode' => '[bitmomo_pro_account]',
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
		echo '<p>' . esc_html__( 'Creates a draft Page containing the relevant shortcode if the slug is free. Never overwrites an existing Page. You still need to review content, publish, and add the Page to navigation yourself.', 'bitmomo-pro' ) . '</p>';

		echo '<table class="widefat" style="max-width:640px;"><thead><tr><th>' . esc_html__( 'Slug', 'bitmomo-pro' ) . '</th><th>' . esc_html__( 'Status', 'bitmomo-pro' ) . '</th><th></th></tr></thead><tbody>';

		$pages = $this->target_pages();

		foreach ( $pages as $key => $info ) {
			$existing = get_page_by_path( $info['path'], OBJECT, 'page' );
			echo '<tr><td><code>/' . esc_html( $info['path'] ) . '</code></td><td>';
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
				$parent_missing = false;
				if ( '' !== $info['parent'] ) {
					$parent_page = get_page_by_path( $pages[ $info['parent'] ]['path'], OBJECT, 'page' );
					if ( ! $parent_page ) {
						$parent_missing = true;
					}
				}

				if ( $parent_missing ) {
					printf(
						/* translators: %s: parent page path, e.g. "pro" */
						esc_html__( 'Create /%s first.', 'bitmomo-pro' ),
						esc_html( $pages[ $info['parent'] ]['path'] )
					);
				} else {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
					wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
					echo '<input type="hidden" name="action" value="bitmomo_pro_setup_pages" />';
					echo '<input type="hidden" name="key" value="' . esc_attr( $key ) . '" />';
					echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Create draft page', 'bitmomo-pro' ) . '</button>';
					echo '</form>';
				}
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

		$key   = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$pages = $this->target_pages();

		if ( $key && isset( $pages[ $key ] ) ) {
			$info = $pages[ $key ];

			// Idempotency: never overwrite or duplicate an existing path.
			$existing = get_page_by_path( $info['path'], OBJECT, 'page' );
			if ( ! $existing ) {
				$post_parent = 0;
				$can_create  = true;

				if ( '' !== $info['parent'] ) {
					$parent_page = get_page_by_path( $pages[ $info['parent'] ]['path'], OBJECT, 'page' );
					if ( $parent_page ) {
						$post_parent = $parent_page->ID;
					} else {
						// Parent doesn't exist yet — refuse to create an
						// orphaned/unnested page rather than guessing.
						$can_create = false;
					}
				}

				if ( $can_create ) {
					wp_insert_post(
						array(
							'post_type'    => 'page',
							'post_status'  => 'draft',
							'post_title'   => $info['title'],
							'post_name'    => $info['post_name'],
							'post_parent'  => $post_parent,
							'post_content' => $info['shortcode'],
						)
					);
				}
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE . '&page=bitmomo-pro-setup' ) );
		exit;
	}
}

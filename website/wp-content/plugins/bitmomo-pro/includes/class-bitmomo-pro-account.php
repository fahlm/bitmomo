<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The customer-facing account/status page: [bitmomo_pro_account]
 *
 * Purpose: give an existing Pro subscriber a simple place to check their
 * own membership status — separate from the paid daily brief itself
 * ([bitmomo_pro_dashboard]) and from any admin surface.
 *
 * Security: this shortcode ONLY ever reads the CURRENT logged-in user
 * (get_current_user_id()). There is no user_id query parameter or request
 * input of any kind that selects whose data is shown — one subscriber can
 * never inspect another subscriber's membership status through this page.
 *
 * Content boundary: this page shows only customer-relevant membership
 * facts (status, Founding Member flag, access start/expiry, dashboard
 * link, support contact, cancellation/refund instructions). It never
 * exposes validation class, acquisition channel, internal notes, usage
 * counters, or any other admin/PMF metadata from Bitmomo_Pro_Usage or the
 * internal note field on Bitmomo_Pro_Entitlements — those are for the
 * founder's eyes only, on the wp-admin User Edit screen.
 */
class Bitmomo_Pro_Account {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bitmomo_pro_account', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_account' ) ) {
			wp_enqueue_style( 'bitmomo-pro-account', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-account.css', array(), BITMOMO_PRO_VERSION );
		}
	}

	public function render( $atts ) {
		ob_start();
		echo '<div class="bm-pro-account">';

		if ( ! is_user_logged_in() ) {
			$this->render_logged_out();
		} else {
			// Always the current user — never derived from any request input.
			$user_id = get_current_user_id();
			if ( ! bitmomo_user_has_pro_access( $user_id ) ) {
				$this->render_inactive();
			} else {
				$this->render_active( $user_id );
			}
		}

		echo '</div>';
		return ob_get_clean();
	}

	private function current_url() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}
		return esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	private function checkout_cta( $label ) {
		$url = bitmomo_pro_get_checkout_url();
		if ( empty( $url ) ) {
			echo '<p class="bm-pro-account__contact-note">' . esc_html__( 'Aktivasi Bitmomo Pro saat ini dilakukan secara manual. Hubungi tim Bitmomo untuk bergabung.', 'bitmomo-pro' ) . '</p>';
			return;
		}
		printf(
			'<a class="bm-pro-account__cta" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private function support_email() {
		return apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) );
	}

	private function render_logged_out() {
		echo '<div class="bm-pro-account__gate">';
		echo '<h3 class="bm-pro-account__gate-title">' . esc_html__( 'Masuk untuk melihat status akun Bitmomo Pro kamu', 'bitmomo-pro' ) . '</h3>';
		echo '<div class="bm-pro-account__login-form">';
		wp_login_form( array( 'redirect' => $this->current_url() ) );
		echo '</div>';
		$this->checkout_cta( __( 'Lihat Bitmomo Pro', 'bitmomo-pro' ) );
		echo '</div>';
	}

	private function render_inactive() {
		echo '<div class="bm-pro-account__gate">';
		echo '<h3 class="bm-pro-account__gate-title">' . esc_html__( 'Bitmomo Pro tidak aktif', 'bitmomo-pro' ) . '</h3>';
		echo '<p class="bm-pro-account__gate-text">' . esc_html__( 'Akun kamu belum memiliki akses Bitmomo Pro yang aktif.', 'bitmomo-pro' ) . '</p>';
		$this->checkout_cta( __( 'Aktifkan Bitmomo Pro', 'bitmomo-pro' ) );
		printf(
			'<p class="bm-pro-account__support">%s <a href="mailto:%s">%s</a></p>',
			esc_html__( 'Butuh bantuan?', 'bitmomo-pro' ),
			esc_attr( $this->support_email() ),
			esc_html( $this->support_email() )
		);
		echo '</div>';
	}

	/**
	 * Only customer-relevant facts — no validation class, acquisition
	 * channel, internal note, or usage counters. Reads only
	 * Bitmomo_Pro_Entitlements meta (status/source/started/expires) and
	 * the plugin's public helper functions.
	 */
	private function render_active( $user_id ) {
		$source     = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_SOURCE, true );
		$started_at = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STARTED_AT, true );
		$expires_at = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
		$is_founding = ( Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING === $source );
		$dashboard_url = function_exists( 'bitmomo_pro_get_dashboard_url' ) ? bitmomo_pro_get_dashboard_url() : '';
		?>
		<div class="bm-pro-account__status">
			<p class="bm-pro-account__status-line">
				<strong><?php esc_html_e( 'Status Bitmomo Pro:', 'bitmomo-pro' ); ?></strong>
				<span class="bm-pro-account__badge bm-pro-account__badge--active"><?php esc_html_e( 'Aktif', 'bitmomo-pro' ); ?></span>
			</p>

			<?php if ( $is_founding ) : ?>
				<p class="bm-pro-account__status-line">
					<span class="bm-pro-account__badge bm-pro-account__badge--founding"><?php esc_html_e( 'Founding Member', 'bitmomo-pro' ); ?></span>
				</p>
			<?php endif; ?>

			<p class="bm-pro-account__status-line">
				<?php echo esc_html( sprintf( __( 'Akses dimulai: %s', 'bitmomo-pro' ), $started_at ? $started_at : '—' ) ); ?>
			</p>
			<p class="bm-pro-account__status-line">
				<?php echo esc_html( $expires_at ? sprintf( __( 'Akses berlaku sampai: %s', 'bitmomo-pro' ), $expires_at ) : __( 'Tidak ada tanggal kedaluwarsa.', 'bitmomo-pro' ) ); ?>
			</p>

			<?php if ( ! empty( $dashboard_url ) ) : ?>
				<p class="bm-pro-account__status-line">
					<a class="bm-pro-account__cta" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Buka Dashboard Pro', 'bitmomo-pro' ); ?></a>
				</p>
			<?php endif; ?>

			<p class="bm-pro-account__support">
				<?php echo esc_html__( 'Butuh bantuan?', 'bitmomo-pro' ); ?>
				<a href="mailto:<?php echo esc_attr( $this->support_email() ); ?>"><?php echo esc_html( $this->support_email() ); ?></a>
			</p>
			<p class="bm-pro-account__terms">
				<?php esc_html_e( 'Selama Founding Beta, pembatalan dan refund dapat diproses melalui email.', 'bitmomo-pro' ); ?>
			</p>
		</div>
		<?php
	}
}

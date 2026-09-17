<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Customer-facing account/status page: [bitmomo_pro_account]. */
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
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	public function add_body_class( $classes ) {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_account' ) ) {
			$classes[] = 'bitmomo-pro-account-page';
		}
		return $classes;
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
		echo '<header class="bm-pro-account__head">';
		echo '<p class="bm-pro-account__eyebrow">BITMOMO PRO</p>';
		echo '<h1 class="bm-pro-account__title">' . esc_html__( 'Akun Bitmomo Pro', 'bitmomo-pro' ) . '</h1>';
		echo '</header>';

		if ( ! is_user_logged_in() ) {
			$this->render_logged_out();
		} else {
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
			printf(
				'<a class="bm-pro-account__cta bm-pro-account__cta--secondary" href="%1$s">%2$s</a><p class="bm-pro-account__contact-note">%3$s</p>',
				esc_url( home_url( '/pro/#bm-pro-whitelist' ) ),
				esc_html__( 'Gabung Founding Whitelist', 'bitmomo-pro' ),
				esc_html__( 'Checkout belum dibuka. Whitelist adalah jalur resmi untuk menerima pemberitahuan saat akses batch berikutnya tersedia.', 'bitmomo-pro' )
			);
			return;
		}
		printf(
			'<a class="bm-pro-account__cta" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private function support_email() {
		return sanitize_email( (string) apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) ) );
	}

	private function format_access_date( $date ) {
		$date = trim( (string) $date );
		if ( '' === $date ) return '';
		$timestamp = strtotime( $date . ' 12:00:00' );
		if ( false === $timestamp ) return $date;
		return wp_date( get_option( 'date_format' ), $timestamp, wp_timezone() );
	}

	private function render_logged_out() {
		echo '<div class="bm-pro-account__gate">';
		echo '<h2 class="bm-pro-account__gate-title">' . esc_html__( 'Masuk untuk melihat status akses Bitmomo Pro', 'bitmomo-pro' ) . '</h2>';
		echo '<div class="bm-pro-account__login-form">';
		wp_login_form(
			array(
				'redirect'     => $this->current_url(),
				'label_log_in' => __( 'Masuk', 'bitmomo-pro' ),
			)
		);
		echo '</div>';
		printf(
			'<p class="bm-pro-account__support"><a href="%1$s">%2$s</a></p>',
			esc_url( wp_lostpassword_url( $this->current_url() ) ),
			esc_html__( 'Lupa kata sandi?', 'bitmomo-pro' )
		);
		$this->checkout_cta( __( 'Lihat Bitmomo Pro', 'bitmomo-pro' ) );
		echo '</div>';
	}

	private function render_inactive() {
		echo '<div class="bm-pro-account__gate">';
		echo '<h2 class="bm-pro-account__gate-title">' . esc_html__( 'Bitmomo Pro tidak aktif', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="bm-pro-account__gate-text">' . esc_html__( 'Akun ini belum memiliki akses Bitmomo Pro yang aktif.', 'bitmomo-pro' ) . '</p>';
		$this->checkout_cta( __( 'Aktifkan Bitmomo Pro', 'bitmomo-pro' ) );
		$this->render_support();
		echo '</div>';
	}

	private function render_support() {
		$email = $this->support_email();
		if ( '' === $email ) return;
		printf(
			'<p class="bm-pro-account__support">%1$s <a href="mailto:%2$s">%3$s</a></p>',
			esc_html__( 'Butuh bantuan?', 'bitmomo-pro' ),
			esc_attr( $email ),
			esc_html( $email )
		);
	}

	private function render_active( $user_id ) {
		$source        = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_SOURCE, true );
		$started_at    = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_STARTED_AT, true );
		$expires_at    = get_user_meta( $user_id, Bitmomo_Pro_Entitlements::META_EXPIRES_AT, true );
		$is_founding   = ( Bitmomo_Pro_Entitlement_Service::TYPE_FOUNDING === $source );
		$dashboard_url = function_exists( 'bitmomo_pro_get_dashboard_url' ) ? bitmomo_pro_get_dashboard_url() : '';
		$started_label = $this->format_access_date( $started_at );
		$expires_label = $this->format_access_date( $expires_at );
		?>
		<div class="bm-pro-account__status">
			<p class="bm-pro-account__status-line">
				<strong><?php esc_html_e( 'Status Bitmomo Pro:', 'bitmomo-pro' ); ?></strong>
				<span class="bm-pro-account__badge bm-pro-account__badge--active"><?php esc_html_e( 'Aktif', 'bitmomo-pro' ); ?></span>
			</p>

			<?php if ( $is_founding ) : ?>
				<p class="bm-pro-account__status-line"><span class="bm-pro-account__badge bm-pro-account__badge--founding"><?php esc_html_e( 'Founding Member', 'bitmomo-pro' ); ?></span></p>
			<?php endif; ?>

			<p class="bm-pro-account__status-line"><?php echo esc_html( sprintf( __( 'Akses dimulai: %s', 'bitmomo-pro' ), $started_label ?: '—' ) ); ?></p>
			<p class="bm-pro-account__status-line"><?php echo esc_html( $expires_label ? sprintf( __( 'Akses berlaku sampai: %s', 'bitmomo-pro' ), $expires_label ) : __( 'Tidak ada tanggal kedaluwarsa.', 'bitmomo-pro' ) ); ?></p>

			<?php if ( ! empty( $dashboard_url ) ) : ?>
				<p class="bm-pro-account__status-line"><a class="bm-pro-account__cta" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Buka Dashboard Pro', 'bitmomo-pro' ); ?></a></p>
			<?php endif; ?>

			<?php $this->render_support(); ?>
			<p class="bm-pro-account__terms"><?php esc_html_e( 'Batalkan kapan saja melalui email. Akses tetap aktif sampai akhir periode yang sudah dibayar; pembayaran final setelah aktivasi kecuali pembayaran ganda, kesalahan transaksi, kegagalan layanan/akses, atau ketentuan hukum/penyedia pembayaran.', 'bitmomo-pro' ); ?></p>
		</div>
		<?php
	}
}

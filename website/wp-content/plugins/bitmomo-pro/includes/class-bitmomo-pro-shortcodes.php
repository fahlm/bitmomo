<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end rendering for the Pro dashboard: [bitmomo_pro_dashboard]
 *
 * Every branch below decides, server-side, whether Pro content may be
 * included at all — before any output is built. Paid fields are never
 * placed in markup for a visitor who isn't entitled; nothing is hidden
 * with CSS. Does not require any theme modification.
 *
 * The historical Pro brief storage key is `market_state`, but its allowed
 * bullish/neutral/bearish values are directional BIAS. The renderer therefore
 * presents it only as Bias while the storage migration is handled separately.
 */
class Bitmomo_Pro_Shortcodes {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bitmomo_pro_dashboard', array( $this, 'render_dashboard' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	public function add_body_class( $classes ) {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_dashboard' ) ) {
			$classes[] = 'bitmomo-pro-dashboard-page';
		}
		return $classes;
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_dashboard' ) ) {
			wp_enqueue_style( 'bitmomo-pro', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro.css', array(), BITMOMO_PRO_VERSION );
		}
	}

	public function render_dashboard( $atts ) {
		ob_start();
		echo '<div class="bm-pro">';
		echo '<header class="bm-pro__page-head">';
		echo '<p class="bm-pro__page-eyebrow">' . esc_html__( 'BITMOMO PRO', 'bitmomo-pro' ) . '</p>';
		echo '<h1 class="bm-pro__page-title">' . esc_html__( 'Dashboard Bitmomo Pro', 'bitmomo-pro' ) . '</h1>';
		echo '<p class="bm-pro__page-intro">' . esc_html__( 'Decision View terbaru untuk memahami skenario, invalidation, dan perubahan thesis BTC.', 'bitmomo-pro' ) . '</p>';
		echo '</header>';

		if ( ! is_user_logged_in() ) {
			$this->render_logged_out();
		} elseif ( ! bitmomo_user_has_pro_access( get_current_user_id() ) ) {
			$this->render_inactive();
		} else {
			$this->render_active();
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Renders the CTA toward checkout, or a graceful Founding Membership
	 * route if checkout is not configured. Never renders a dead link.
	 */
	private function checkout_cta( $label ) {
		$url = bitmomo_pro_get_checkout_url();
		if ( empty( $url ) ) {
			echo '<p class="bm-pro__contact-note">' . esc_html__( 'Akses baru Bitmomo Pro saat ini dibuka bertahap melalui Founding Membership.', 'bitmomo-pro' ) . ' <a href="' . esc_url( home_url( '/pro/' ) ) . '">' . esc_html__( 'Lihat detail Pro', 'bitmomo-pro' ) . '</a>.</p>';
			return;
		}
		printf(
			'<a class="bm-pro__cta" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private function current_url() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return home_url( '/' );
		}
		return esc_url_raw( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	private function format_timestamp( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}
		return wp_date( 'd M Y · H:i T', $timestamp, wp_timezone() );
	}

	private function render_logged_out() {
		echo '<div class="bm-pro__gate">';
		echo '<h2 class="bm-pro__gate-title">' . esc_html__( 'Masuk untuk membuka Bitmomo Pro', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="bm-pro__gate-text">' . esc_html__( 'Decision View Bitmomo Pro hanya tersedia untuk member aktif.', 'bitmomo-pro' ) . '</p>';

		echo '<div class="bm-pro__login-form">';
		wp_login_form(
			array(
				'redirect' => $this->current_url(),
			)
		);
		echo '</div>';
		echo '<p class="bm-pro__help-row"><a class="bm-pro__help-link" href="' . esc_url( wp_lostpassword_url( $this->current_url() ) ) . '">' . esc_html__( 'Lupa atau belum punya password?', 'bitmomo-pro' ) . '</a></p>';

		$this->checkout_cta( __( 'Lihat Bitmomo Pro', 'bitmomo-pro' ) );
		echo '</div>';
	}

	private function render_inactive() {
		echo '<div class="bm-pro__gate">';
		echo '<h2 class="bm-pro__gate-title">' . esc_html__( 'Akses Bitmomo Pro belum aktif', 'bitmomo-pro' ) . '</h2>';
		echo '<p class="bm-pro__gate-text">' . esc_html__( 'Akun kamu sudah masuk, tetapi belum memiliki akses Bitmomo Pro yang aktif.', 'bitmomo-pro' ) . '</p>';
		$this->checkout_cta( __( 'Aktifkan Bitmomo Pro', 'bitmomo-pro' ) );
		echo '<p class="bm-pro__help-row"><a class="bm-pro__help-link" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Keluar dari akun', 'bitmomo-pro' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * The only place "no eligible current brief" is decided is
	 * Bitmomo_Pro_Briefs::get_current_brief_for_display(). Whatever tier
	 * it returns — including 'unavailable' for a stale/invalid/never-
	 * published brief — is rendered as-is. This method never falls back
	 * to stale numbers and never fabricates a value.
	 */
	private function render_active() {
		$result = Bitmomo_Pro_Briefs::get_current_brief_for_display();
		$tier   = $result['tier'];
		$brief  = $result['brief'];

		if ( Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier || null === $brief ) {
			echo '<div class="bm-pro__gate">';
			echo '<h2 class="bm-pro__gate-title">' . esc_html__( 'Decision View terbaru belum tersedia', 'bitmomo-pro' ) . '</h2>';
			echo '<p class="bm-pro__gate-text">' . esc_html__( 'Sistem sedang menunggu data yang memenuhi standar kualitas.', 'bitmomo-pro' ) . ' <a class="bm-pro__help-link" href="' . esc_url( Bitmomo_Pro_Help_Center::question_url( 'quality-gate' ) ) . '">' . esc_html__( 'Mengapa?', 'bitmomo-pro' ) . '</a></p>';
			echo '</div>';
			return;
		}

		if ( isset( $brief['id'] ) ) {
			do_action( 'bitmomo_pro_brief_viewed', get_current_user_id(), (int) $brief['id'] );
		}

		$bias_label = array(
			'bullish' => __( 'Bullish', 'bitmomo-pro' ),
			'neutral' => __( 'Neutral', 'bitmomo-pro' ),
			'bearish' => __( 'Bearish', 'bitmomo-pro' ),
		);
		$freshness_label = array(
			Bitmomo_Pro_Brief_Readiness::TIER_FRESH   => __( 'Data terkini', 'bitmomo-pro' ),
			Bitmomo_Pro_Brief_Readiness::TIER_DELAYED => __( 'Data tertunda', 'bitmomo-pro' ),
		);

		// Legacy storage key; values are directional bias, not regime Market State.
		$bias = isset( $brief['market_state'] ) ? $brief['market_state'] : '';
		?>
		<div class="bm-pro__dashboard">
			<div class="bm-pro__header bm-pro__state--<?php echo esc_attr( $bias ? $bias : 'unknown' ); ?>">
				<span class="bm-pro__state-label"><?php echo esc_html( sprintf( __( 'Bias: %s', 'bitmomo-pro' ), isset( $bias_label[ $bias ] ) ? $bias_label[ $bias ] : $bias ) ); ?></span>
				<?php if ( '' !== $brief['confidence'] ) :
					$bm_pro_confidence_value = (int) $brief['confidence'];
					$bm_pro_confidence_label = $bm_pro_confidence_value >= 70 ? __( 'Tinggi', 'bitmomo-pro' ) : ( $bm_pro_confidence_value >= 40 ? __( 'Sedang', 'bitmomo-pro' ) : __( 'Rendah', 'bitmomo-pro' ) );
				?>
					<span class="bm-pro__confidence"><?php esc_html_e( 'Confidence', 'bitmomo-pro' ); ?>: <?php echo esc_html( $bm_pro_confidence_label ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $brief['btc_reference_price'] ) : ?>
					<span class="bm-pro__price">$<?php echo esc_html( number_format_i18n( floatval( $brief['btc_reference_price'] ) ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $brief['confidence_explanation'] ) ) : ?>
				<p class="bm-pro__confidence-explain"><?php echo esc_html( $brief['confidence_explanation'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $brief['expected_range_low'] || '' !== $brief['expected_range_high'] ) : ?>
				<div class="bm-pro__section">
					<h3><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?> <a class="bm-pro__help-link" href="<?php echo esc_url( Bitmomo_Pro_Help_Center::question_url( 'apa-itu-expected-range' ) ); ?>" aria-label="<?php esc_attr_e( 'Apa itu Expected Range?', 'bitmomo-pro' ); ?>">?</a></h3>
					<p class="bm-pro__range">$<?php echo esc_html( number_format_i18n( floatval( $brief['expected_range_low'] ) ) ); ?> &ndash; $<?php echo esc_html( number_format_i18n( floatval( $brief['expected_range_high'] ) ) ); ?></p>
				</div>
			<?php endif; ?>

			<div class="bm-pro__scenarios">
				<?php if ( ! empty( $brief['base_scenario'] ) ) : ?>
					<div class="bm-pro__scenario bm-pro__scenario--base">
						<h3><?php esc_html_e( 'Base Scenario', 'bitmomo-pro' ); ?></h3>
						<p><?php echo esc_html( $brief['base_scenario'] ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $brief['bull_scenario'] ) ) : ?>
					<div class="bm-pro__scenario bm-pro__scenario--bull">
						<h3><?php esc_html_e( 'Bull Scenario', 'bitmomo-pro' ); ?></h3>
						<p><?php echo esc_html( $brief['bull_scenario'] ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $brief['bear_scenario'] ) ) : ?>
					<div class="bm-pro__scenario bm-pro__scenario--bear">
						<h3><?php esc_html_e( 'Bear Scenario', 'bitmomo-pro' ); ?></h3>
						<p><?php echo esc_html( $brief['bear_scenario'] ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $brief['invalidation'] ) ) : ?>
				<div class="bm-pro__section bm-pro__invalidation">
					<h3><?php esc_html_e( 'Thesis Invalidation', 'bitmomo-pro' ); ?> <a class="bm-pro__help-link" href="<?php echo esc_url( Bitmomo_Pro_Help_Center::question_url( 'apa-itu-thesis-invalidation' ) ); ?>" aria-label="<?php esc_attr_e( 'Apa itu Thesis Invalidation?', 'bitmomo-pro' ); ?>">?</a></h3>
					<p><?php echo esc_html( $brief['invalidation'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $brief['what_changed'] ) ) : ?>
				<div class="bm-pro__section">
					<h3><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></h3>
					<p><?php echo esc_html( $brief['what_changed'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="bm-pro__footer">
				<span class="bm-pro__freshness bm-pro__freshness--<?php echo esc_attr( $tier ); ?>">
					<?php echo esc_html( isset( $freshness_label[ $tier ] ) ? $freshness_label[ $tier ] : $tier ); ?>
				</span>
				<?php if ( Bitmomo_Pro_Brief_Readiness::TIER_DELAYED === $tier ) : ?>
					<span class="bm-pro__timestamp"><?php echo esc_html( Bitmomo_Pro_Brief_Readiness::instance()->format_age_label( $brief['data_timestamp'] ) ); ?></span>
				<?php elseif ( ! empty( $brief['data_timestamp'] ) ) : ?>
					<span class="bm-pro__timestamp"><?php echo esc_html( $this->format_timestamp( $brief['data_timestamp'] ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

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
 * As of PR #31: which brief (if any) counts as "current" is decided by
 * Bitmomo_Pro_Briefs::get_current_brief_for_display(), which applies both
 * the release-readiness gate and the freshness gate. This class no longer
 * makes that decision itself — it only renders whatever tier/brief comes
 * back, including the safe "belum tersedia" state and the "delayed, but
 * still shown" state.
 *
 * As of PR #33: render_active() fires 'bitmomo_pro_brief_viewed' exactly
 * once, and only in the branch where a real, current brief is actually
 * about to be rendered for this already-authenticated, already-entitled
 * user (render_dashboard() only reaches render_active() after both the
 * login and entitlement checks). Bitmomo_Pro_Usage listens to that action
 * to record lightweight PMF usage signals — see that class for the full
 * privacy contract (no IP, no cookies, no fingerprinting).
 *
 * Known integration point: if the site later adds full-page HTML caching
 * in front of pages that render this shortcode, that cache must exclude
 * (or bypass for) logged-in/entitled visitors, otherwise a cached
 * anonymous render could be served to everyone. That is a caching-layer
 * concern outside this plugin's file ownership (Codex's production
 * cache/ShortPixel stabilization area) and is called out explicitly in
 * this PR's report rather than being touched here.
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
	 * Renders the CTA toward checkout, or a graceful manual-activation
	 * note if no checkout URL is configured yet. Never renders a dead link
	 * and never hardcodes a payment provider.
	 */
	private function checkout_cta( $label ) {
		$url = bitmomo_pro_get_checkout_url();
		if ( empty( $url ) ) {
			echo '<p class="bm-pro__contact-note">' . esc_html__( 'Aktivasi Bitmomo Pro saat ini dilakukan secara manual. Hubungi tim Bitmomo untuk bergabung.', 'bitmomo-pro' ) . '</p>';
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

	private function render_logged_out() {
		echo '<div class="bm-pro__gate">';
		echo '<h3 class="bm-pro__gate-title">' . esc_html__( 'Masuk untuk membuka Bitmomo Pro', 'bitmomo-pro' ) . '</h3>';
		echo '<p class="bm-pro__gate-text">' . esc_html__( 'Brief keputusan harian Bitmomo Pro hanya tersedia untuk member aktif.', 'bitmomo-pro' ) . '</p>';

		echo '<div class="bm-pro__login-form">';
		wp_login_form(
			array(
				'redirect' => $this->current_url(),
			)
		);
		echo '</div>';

		$this->checkout_cta( __( 'Lihat Bitmomo Pro', 'bitmomo-pro' ) );
		echo '</div>';
	}

	private function render_inactive() {
		echo '<div class="bm-pro__gate">';
		echo '<h3 class="bm-pro__gate-title">' . esc_html__( 'Akses Bitmomo Pro tidak aktif.', 'bitmomo-pro' ) . '</h3>';
		echo '<p class="bm-pro__gate-text">' . esc_html__( 'Akun kamu sudah login, tapi belum memiliki akses Bitmomo Pro yang aktif.', 'bitmomo-pro' ) . '</p>';
		$this->checkout_cta( __( 'Aktifkan Bitmomo Pro', 'bitmomo-pro' ) );
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
			echo '<p class="bm-pro__gate-text">' . esc_html__( 'Brief Bitmomo Pro terbaru belum tersedia.', 'bitmomo-pro' ) . '<br />' . esc_html__( 'Sistem sedang menunggu data yang memenuhi standar kualitas.', 'bitmomo-pro' ) . '</p>';
			echo '</div>';
			return;
		}

		// PMF usage signal: fired only here, i.e. only when a real, current
		// brief is actually about to be shown to an already-authenticated,
		// already-entitled user. See Bitmomo_Pro_Usage::record_view().
		if ( isset( $brief['id'] ) ) {
			do_action( 'bitmomo_pro_brief_viewed', get_current_user_id(), (int) $brief['id'] );
		}

		$state_label = array(
			'bullish' => __( 'Bullish', 'bitmomo-pro' ),
			'neutral' => __( 'Neutral', 'bitmomo-pro' ),
			'bearish' => __( 'Bearish', 'bitmomo-pro' ),
		);
		$freshness_label = array(
			Bitmomo_Pro_Brief_Readiness::TIER_FRESH   => __( 'Data terkini', 'bitmomo-pro' ),
			Bitmomo_Pro_Brief_Readiness::TIER_DELAYED => __( 'Data tertunda', 'bitmomo-pro' ),
		);

		$state = isset( $brief['market_state'] ) ? $brief['market_state'] : '';
		?>
		<div class="bm-pro__dashboard">
			<div class="bm-pro__header bm-pro__state--<?php echo esc_attr( $state ? $state : 'unknown' ); ?>">
				<span class="bm-pro__state-label"><?php echo esc_html( isset( $state_label[ $state ] ) ? $state_label[ $state ] : $state ); ?></span>
				<?php if ( '' !== $brief['confidence'] ) : ?>
					<span class="bm-pro__confidence"><?php echo esc_html( $brief['confidence'] ); ?>% <?php esc_html_e( 'confidence', 'bitmomo-pro' ); ?></span>
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
					<h4><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?></h4>
					<p class="bm-pro__range">$<?php echo esc_html( number_format_i18n( floatval( $brief['expected_range_low'] ) ) ); ?> &ndash; $<?php echo esc_html( number_format_i18n( floatval( $brief['expected_range_high'] ) ) ); ?></p>
				</div>
			<?php endif; ?>

			<div class="bm-pro__scenarios">
				<?php if ( ! empty( $brief['base_scenario'] ) ) : ?>
					<div class="bm-pro__scenario bm-pro__scenario--base">
						<h4><?php esc_html_e( 'Base Scenario', 'bitmomo-pro' ); ?></h4>
						<p><?php echo esc_html( $brief['base_scenario'] ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $brief['bull_scenario'] ) ) : ?>
					<div class="bm-pro__scenario bm-pro__scenario--bull">
						<h4><?php esc_html_e( 'Bull Scenario', 'bitmomo-pro' ); ?></h4>
						<p><?php echo esc_html( $brief['bull_scenario'] ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $brief['bear_scenario'] ) ) : ?>
					<div class="bm-pro__scenario bm-pro__scenario--bear">
						<h4><?php esc_html_e( 'Bear Scenario', 'bitmomo-pro' ); ?></h4>
						<p><?php echo esc_html( $brief['bear_scenario'] ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $brief['invalidation'] ) ) : ?>
				<div class="bm-pro__section bm-pro__invalidation">
					<h4><?php esc_html_e( 'Thesis Invalidation', 'bitmomo-pro' ); ?></h4>
					<p><?php echo esc_html( $brief['invalidation'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $brief['what_changed'] ) ) : ?>
				<div class="bm-pro__section">
					<h4><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></h4>
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
					<span class="bm-pro__timestamp"><?php echo esc_html( $brief['data_timestamp'] ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

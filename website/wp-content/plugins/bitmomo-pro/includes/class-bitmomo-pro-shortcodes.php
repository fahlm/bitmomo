<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end rendering for the Pro dashboard: [bitmomo_pro_dashboard]
 *
 * Every branch below decides, server-side, whether Pro content may be included
 * at all. Paid fields are never placed in markup for a visitor who is not
 * entitled; nothing is hidden with CSS.
 *
 * The current brief remains owned by Bitmomo_Pro_Briefs::get_current_brief_for_display(),
 * which applies release-readiness and freshness gates. This renderer consumes
 * that already-gated object once and never falls back to stale values.
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
			wp_enqueue_style(
				'bitmomo-pro-decision-view',
				BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-decision-view.css',
				array( 'bitmomo-pro' ),
				'2026.09.16-decision-view-v1'
			);
		}
	}

	public function render_dashboard( $atts ) {
		ob_start();
		echo '<div class="bm-pro bm-pro--decision-first">';

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
	 * Checkout remains fail-closed. While checkout is not configured, the
	 * canonical public conversion path is the Founding Whitelist.
	 */
	private function checkout_cta( $label ) {
		$url = bitmomo_pro_get_checkout_url();
		if ( empty( $url ) ) {
			printf(
				'<a class="bm-pro__cta" href="%1$s">%2$s</a><p class="bm-pro__contact-note">%3$s</p>',
				esc_url( home_url( '/pro/#bm-pro-whitelist' ) ),
				esc_html__( 'Gabung Founding Whitelist', 'bitmomo-pro' ),
				esc_html__( 'Checkout belum dibuka. Whitelist adalah jalur resmi untuk menerima pemberitahuan saat akses dibuka.', 'bitmomo-pro' )
			);
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
		echo '<p class="bm-pro__gate-text">' . esc_html__( 'Brief keputusan Bitmomo Pro hanya tersedia untuk member aktif.', 'bitmomo-pro' ) . '</p>';

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
		echo '<p class="bm-pro__gate-text">' . esc_html__( 'Akun kamu sudah login, tetapi belum memiliki akses Bitmomo Pro yang aktif.', 'bitmomo-pro' ) . '</p>';
		$this->checkout_cta( __( 'Aktifkan Bitmomo Pro', 'bitmomo-pro' ) );
		echo '</div>';
	}

	/**
	 * The only place "no eligible current brief" is decided is
	 * Bitmomo_Pro_Briefs::get_current_brief_for_display(). Whatever tier it
	 * returns is rendered as-is. This method never falls back to stale numbers
	 * and never fabricates a value.
	 */
	private function render_active() {
		$result = Bitmomo_Pro_Briefs::get_current_brief_for_display();
		$tier   = $result['tier'];
		$brief  = $result['brief'];

		if ( Bitmomo_Pro_Brief_Readiness::TIER_UNAVAILABLE === $tier || null === $brief ) {
			echo '<div class="bm-pro__gate">';
			echo '<p class="bm-pro__gate-text">' . esc_html__( 'Brief Bitmomo Pro terbaru belum tersedia.', 'bitmomo-pro' ) . '<br />' . esc_html__( 'Sistem sedang menunggu data yang memenuhi standar kualitas.', 'bitmomo-pro' ) . ' <a class="bm-pro__help-link" href="' . esc_url( Bitmomo_Pro_Help_Center::question_url( 'quality-gate' ) ) . '">' . esc_html__( 'Mengapa?', 'bitmomo-pro' ) . '</a></p>';
			echo '</div>';
			return;
		}

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

		$state = isset( $brief['market_state'] ) ? sanitize_key( (string) $brief['market_state'] ) : '';
		?>
		<div class="bm-pro__dashboard">
			<header class="bm-pro__header bm-pro__state--<?php echo esc_attr( $state ? $state : 'unknown' ); ?>">
				<span class="bm-pro__state-label"><?php echo esc_html( isset( $state_label[ $state ] ) ? $state_label[ $state ] : $state ); ?></span>
				<?php if ( '' !== (string) $brief['confidence'] ) :
					$bm_pro_confidence_value = (int) $brief['confidence'];
					$bm_pro_confidence_label = $bm_pro_confidence_value >= 70 ? __( 'Tinggi', 'bitmomo-pro' ) : ( $bm_pro_confidence_value >= 40 ? __( 'Sedang', 'bitmomo-pro' ) : __( 'Rendah', 'bitmomo-pro' ) );
				?>
					<span class="bm-pro__confidence"><?php esc_html_e( 'Confidence', 'bitmomo-pro' ); ?>: <?php echo esc_html( $bm_pro_confidence_label ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== (string) $brief['btc_reference_price'] ) : ?>
					<span class="bm-pro__price">$<?php echo esc_html( number_format_i18n( floatval( $brief['btc_reference_price'] ) ) ); ?></span>
				<?php endif; ?>
			</header>

			<?php $this->render_expected_range( $brief ); ?>

			<div class="bm-pro__scenarios" aria-label="<?php esc_attr_e( 'Scenario Map', 'bitmomo-pro' ); ?>">
				<?php if ( ! empty( $brief['base_scenario'] ) ) : ?>
					<section class="bm-pro__scenario bm-pro__scenario--base">
						<h4><?php esc_html_e( 'Base', 'bitmomo-pro' ); ?></h4>
						<p><?php echo esc_html( $brief['base_scenario'] ); ?></p>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $brief['bull_scenario'] ) ) : ?>
					<section class="bm-pro__scenario bm-pro__scenario--bull">
						<h4><?php esc_html_e( 'Bull', 'bitmomo-pro' ); ?></h4>
						<p><?php echo esc_html( $brief['bull_scenario'] ); ?></p>
					</section>
				<?php endif; ?>
				<?php if ( ! empty( $brief['bear_scenario'] ) ) : ?>
					<section class="bm-pro__scenario bm-pro__scenario--bear">
						<h4><?php esc_html_e( 'Bear', 'bitmomo-pro' ); ?></h4>
						<p><?php echo esc_html( $brief['bear_scenario'] ); ?></p>
					</section>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $brief['invalidation'] ) ) : ?>
				<section class="bm-pro__section bm-pro__invalidation" aria-labelledby="bm-pro-invalidation-title">
					<h4 id="bm-pro-invalidation-title"><?php esc_html_e( 'Apa yang membuat tesis ini tidak lagi berlaku?', 'bitmomo-pro' ); ?> <a class="bm-pro__help-link" href="<?php echo esc_url( Bitmomo_Pro_Help_Center::question_url( 'apa-itu-thesis-invalidation' ) ); ?>" aria-label="<?php esc_attr_e( 'Apa itu Thesis Invalidation?', 'bitmomo-pro' ); ?>">?</a></h4>
					<p><?php echo esc_html( $brief['invalidation'] ); ?></p>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $brief['what_changed'] ) ) : ?>
				<section class="bm-pro__section">
					<h4><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></h4>
					<p><?php echo esc_html( $brief['what_changed'] ); ?></p>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $brief['confidence_explanation'] ) ) : ?>
				<section class="bm-pro__section bm-pro__confidence-context">
					<h4><?php esc_html_e( 'Confidence Context', 'bitmomo-pro' ); ?></h4>
					<p class="bm-pro__confidence-explain"><?php echo esc_html( $brief['confidence_explanation'] ); ?></p>
				</section>
			<?php endif; ?>

			<footer class="bm-pro__footer">
				<span class="bm-pro__freshness bm-pro__freshness--<?php echo esc_attr( $tier ); ?>">
					<?php echo esc_html( isset( $freshness_label[ $tier ] ) ? $freshness_label[ $tier ] : $tier ); ?>
				</span>
				<?php if ( Bitmomo_Pro_Brief_Readiness::TIER_DELAYED === $tier ) : ?>
					<span class="bm-pro__timestamp"><?php echo esc_html( Bitmomo_Pro_Brief_Readiness::instance()->format_age_label( $brief['data_timestamp'] ) ); ?></span>
				<?php elseif ( ! empty( $brief['data_timestamp'] ) ) : ?>
					<span class="bm-pro__timestamp"><?php echo esc_html( $brief['data_timestamp'] ); ?></span>
				<?php endif; ?>
			</footer>
		</div>
		<?php
	}

	private function render_expected_range( array $brief ) {
		$low       = $this->positive_number( $brief['expected_range_low'] ?? null );
		$high      = $this->positive_number( $brief['expected_range_high'] ?? null );
		$reference = $this->positive_number( $brief['btc_reference_price'] ?? null );

		if ( null === $low || null === $high || $high < $low ) {
			return;
		}

		$positions = $this->range_positions( $low, $high, $reference );
		?>
		<section class="bm-pro__range-visual" aria-labelledby="bm-pro-range-title">
			<div class="bm-pro__range-head">
				<span id="bm-pro-range-title"><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?></span>
				<strong><?php echo esc_html( $this->format_price( $low ) . ' – ' . $this->format_price( $high ) ); ?></strong>
			</div>
			<div class="bm-pro__range-track" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Expected Range %1$s sampai %2$s', 'bitmomo-pro' ), $this->format_price( $low ), $this->format_price( $high ) ) ); ?>">
				<span class="bm-pro__range-band" style="--range-left:<?php echo esc_attr( $positions['range_left'] ); ?>%;--range-width:<?php echo esc_attr( $positions['range_width'] ); ?>%;"></span>
				<?php if ( null !== $reference ) : ?>
					<span class="bm-pro__range-marker" style="--marker-pos:<?php echo esc_attr( $positions['reference'] ); ?>%;"><i></i><small><?php echo esc_html( 'BTC REF ' . $this->format_price( $reference ) ); ?></small></span>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	private function range_positions( $low, $high, $reference ) {
		$points = array( $low, $high );
		if ( null !== $reference ) {
			$points[] = $reference;
		}
		$min         = min( $points );
		$max         = max( $points );
		$span        = max( 1.0, $max - $min );
		$padding     = $span * 0.12;
		$floor       = max( 0.0, $min - $padding );
		$ceiling     = $max + $padding;
		$visual_span = max( 1.0, $ceiling - $floor );
		$position    = static function ( $value ) use ( $floor, $visual_span ) {
			if ( null === $value ) {
				return null;
			}
			return round( max( 0, min( 100, ( ( $value - $floor ) / $visual_span ) * 100 ) ), 2 );
		};
		$range_left  = $position( $low );
		$range_right = $position( $high );

		return array(
			'range_left'  => $range_left,
			'range_width' => round( max( 1, $range_right - $range_left ), 2 ),
			'reference'   => $position( $reference ),
		);
	}

	private function positive_number( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	private function format_price( $value ) {
		return null !== $value ? '$' . number_format( (float) $value, 0, '.', ',' ) : '—';
	}
}

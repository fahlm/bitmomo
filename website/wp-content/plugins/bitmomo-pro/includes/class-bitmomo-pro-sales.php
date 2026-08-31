<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public, unprotected sales surface: [bitmomo_pro_sales]
 *
 * Unlike the dashboard shortcode, this renders the same generic content
 * for every visitor — it never reads entitlement state and never includes
 * any paid brief content. Safe to cache; Bitmomo_Pro_Cache deliberately
 * does not touch pages containing this shortcode.
 *
 * All facts below (price, seat cap, refund/cancellation wording, product
 * claims) are locked to the Founding Beta terms actually in force. Do not
 * add claims the current product doesn't support (no automated alerts,
 * no historical accuracy claims, no public track record, no 11 analysts,
 * no ETH/altcoins) and do not add affiliate content — this plugin's
 * rendering surfaces (dashboard and sales) carry zero affiliate/ad content.
 */
class Bitmomo_Pro_Sales {

	const PRICE_LABEL = 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
	const SEAT_CAP    = 149;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bitmomo_pro_sales', array( $this, 'render_sales' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	public function add_body_class( $classes ) {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_sales' ) ) {
			$classes[] = 'bm-pro-sales-page';
		}
		return $classes;
	}

	public function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_sales' ) ) {
			wp_enqueue_style( 'bitmomo-pro-sales', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-sales.css', array(), BITMOMO_PRO_VERSION );
		}
	}

	public function render_sales( $atts ) {
		ob_start();
		echo '<div class="bm-pro-sales">';
		$this->render_hero();
		$this->render_problem();
		$this->render_free_vs_pro();
		$this->render_daily_deliverable();
		$this->render_example();
		$this->render_founding_beta();
		$this->render_price();
		$this->render_cta();
		Bitmomo_Pro_Help_Center::render_pro_subset();
		$this->render_disclaimer();
		echo '</div>';
		return ob_get_clean();
	}

	private function render_hero() {
		?>
		<section class="bm-pro-sales__hero">
			<h1 class="bm-pro-sales__hero-title"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo-pro' ); ?></h1>
			<p class="bm-pro-sales__hero-sub"><?php esc_html_e( 'Satu decision view BTC harian yang terstruktur — bukan feed yang harus kamu susun sendiri setiap pagi.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_problem() {
		?>
		<section class="bm-pro-sales__section">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Masalahnya', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Kamu tidak seharusnya perlu menyusun sendiri arah BTC dari CT, grup Telegram, dan TradingView setiap hari. Bitmomo Pro merangkumnya menjadi satu tampilan keputusan harian yang terstruktur.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_free_vs_pro() {
		?>
		<section class="bm-pro-sales__section">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Free vs Pro', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__compare">
				<div class="bm-pro-sales__compare-col">
					<h3><?php esc_html_e( 'Free', 'bitmomo-pro' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'BTC state (bullish/neutral/bearish)', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'Confidence score', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'Reference price', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( '1 key driver', 'bitmomo-pro' ); ?></li>
					</ul>
				</div>
				<div class="bm-pro-sales__compare-col bm-pro-sales__compare-col--pro">
					<h3><?php esc_html_e( 'Pro', 'bitmomo-pro' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Expected range', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'Base / Bull / Bear scenario', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'Thesis invalidation', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'Confidence explanation', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></li>
						<li><?php esc_html_e( 'Daily protected decision view', 'bitmomo-pro' ); ?></li>
					</ul>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_daily_deliverable() {
		?>
		<section class="bm-pro-sales__section">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Yang kamu dapat setiap hari', 'bitmomo-pro' ); ?></h2>
			<ul class="bm-pro-sales__list">
				<li><?php esc_html_e( 'Market state dan confidence yang jelas', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Expected range dan tiga skenario (base/bull/bear)', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Titik invalidasi thesis', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Ringkasan "What Changed" dari hari sebelumnya', 'bitmomo-pro' ); ?></li>
			</ul>
		</section>
		<?php
	}

	/**
	 * A structural placeholder only. Never render real/current BTC values
	 * here — this method has no access to live market data by design, and
	 * even if it did, the sales page is not the place for it.
	 */
	private function render_example() {
		?>
		<section class="bm-pro-sales__section">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Contoh Tampilan Decision View', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__example-label"><?php esc_html_e( 'Contoh format, bukan analisis BTC saat ini.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__example">
				<div class="bm-pro-sales__example-header">
					<span class="bm-pro-sales__example-state"><?php esc_html_e( 'Bullish / Neutral / Bearish (contoh)', 'bitmomo-pro' ); ?></span>
					<span class="bm-pro-sales__example-confidence"><?php esc_html_e( 'XX% confidence', 'bitmomo-pro' ); ?></span>
				</div>
				<p class="bm-pro-sales__example-range">$XX,XXX &ndash; $XX,XXX</p>
				<div class="bm-pro-sales__example-scenarios">
					<div><h4><?php esc_html_e( 'Base', 'bitmomo-pro' ); ?></h4><p><?php esc_html_e( '(placeholder)', 'bitmomo-pro' ); ?></p></div>
					<div><h4><?php esc_html_e( 'Bull', 'bitmomo-pro' ); ?></h4><p><?php esc_html_e( '(placeholder)', 'bitmomo-pro' ); ?></p></div>
					<div><h4><?php esc_html_e( 'Bear', 'bitmomo-pro' ); ?></h4><p><?php esc_html_e( '(placeholder)', 'bitmomo-pro' ); ?></p></div>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_founding_beta() {
		?>
		<section class="bm-pro-sales__section">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Founding Beta', 'bitmomo-pro' ); ?></h2>
			<ul class="bm-pro-sales__list">
				<li><?php esc_html_e( 'Fokus khusus BTC untuk saat ini.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Sebagian proses operasional (aktivasi, pembatalan) masih dilakukan manual selama Founding Beta.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Produk akan terus disempurnakan selama periode beta.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Member yang bergabung sekarang mengunci harga Founding Beta selama langganan tidak terputus.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Watchtower termasuk dalam Bitmomo Pro ketika tersedia. Produk standalone lain di masa depan tidak otomatis termasuk.', 'bitmomo-pro' ); ?></li>
			</ul>
		</section>
		<?php
	}

	private function render_price() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__price">
			<p class="bm-pro-sales__price-amount"><?php echo esc_html( self::PRICE_LABEL ); ?></p>
			<p class="bm-pro-sales__price-sub"><?php echo esc_html( sprintf( __( 'Maksimal %d Founding Members. Paket bulanan dan tahunan mendapat fitur yang sama; satu akun untuk satu pengguna.', 'bitmomo-pro' ), self::SEAT_CAP ) ); ?></p>
			<p class="bm-pro-sales__price-terms"><?php esc_html_e( 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__price-terms"><?php esc_html_e( 'Pembayaran bersifat final setelah aktivasi, kecuali untuk pembayaran ganda, kesalahan transaksi, atau kondisi lain yang diwajibkan oleh hukum.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Reuses bitmomo_pro_get_checkout_url() (PR #64's fail-closed checkout
	 * URL) as the single source of truth for which CTA to show — never
	 * duplicated here. Checkout configured -> real purchase CTA, whitelist
	 * never shown as the primary action. Checkout not configured -> the
	 * Founding Membership Whitelist widget takes over this same slot. No
	 * template change is needed when a checkout URL is later configured;
	 * this branch just stops calling Bitmomo_Pro_Whitelist automatically.
	 */
	private function render_cta() {
		$url = bitmomo_pro_get_checkout_url();
		echo '<section class="bm-pro-sales__cta-section">';
		if ( empty( $url ) ) {
			if ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) {
				Bitmomo_Pro_Whitelist::instance()->render_widget( array( 'source' => 'pro_page' ) );
			} else {
				echo '<span class="bm-pro-sales__cta bm-pro-sales__cta--pending">' . esc_html__( 'Pendaftaran Founding Beta segera dibuka.', 'bitmomo-pro' ) . '</span>';
			}
		} else {
			printf(
				'<a class="bm-pro-sales__cta" href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Kunci Harga Founding Beta', 'bitmomo-pro' )
			);
		}
		echo '</section>';
	}

	private function render_disclaimer() {
		?>
		<section class="bm-pro-sales__disclaimer">
			<p><?php esc_html_e( 'Bitmomo Pro adalah alat bantu analisis, bukan nasihat keuangan. Pergerakan harga BTC memiliki risiko; keputusan trading sepenuhnya tanggung jawab pengguna.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}
}

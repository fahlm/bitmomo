<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Bitmomo Pro sales surface: [bitmomo_pro_sales].
 *
 * Decision-first contract: show a real delayed/frozen product artifact before
 * explaining the offer. Public rendering never reads the current protected Pro
 * brief and never fabricates a scenario, range, outcome, or scarcity signal.
 */
class Bitmomo_Pro_Sales {

	const PRICE_LABEL = 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
	const SEAT_CAP    = 149;
	const BATCH_ONE   = 25;
	const SALES_ASSET_VERSION = '2026.09.16-pro-decision-first-v1';

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
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_unneeded_help_assets' ), 20 );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );

		// Backwards-compatible fallback only. Canonical Bitmomo runtime detaches
		// these hooks so the public theme remains the single SEO metadata owner.
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_meta_description' ) );
		add_action( 'wp_head', array( $this, 'render_meta_description' ) );
	}

	public function filter_meta_description( $description ) {
		return is_page( 'pro' ) ? __( 'Bitmomo Pro memetakan Expected Range, Scenario Map, Thesis Invalidation, perubahan penting, dan bukti hasil historis BTC.', 'bitmomo-pro' ) : $description;
	}

	public function render_meta_description() {
		if ( is_page( 'pro' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
			echo '<meta name="description" content="' . esc_attr( $this->filter_meta_description( '' ) ) . '" />' . "\n";
		}
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
			wp_enqueue_style( 'bitmomo-pro-sales', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-sales.css', array(), self::SALES_ASSET_VERSION );
			wp_enqueue_style(
				'bitmomo-pro-decision-view',
				BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-decision-view.css',
				array( 'bitmomo-pro-sales' ),
				'2026.09.16-decision-view-v1'
			);
		}
	}

	/**
	 * Help Center owns its own full-page stylesheet. /pro renders a smaller FAQ
	 * with sales-owned markup/styles, so carrying bitmomo-pro-help.css here is
	 * redundant. Run after normal enqueue callbacks to keep the asset boundary
	 * deterministic without modifying the Help Center itself.
	 */
	public function dequeue_unneeded_help_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bitmomo_pro_sales' ) ) {
			wp_dequeue_style( 'bitmomo-pro-help' );
		}
	}

	public function render_sales( $atts ) {
		ob_start();
		echo '<div class="bm-pro-sales bm-pro-sales--decision-first">';

		$this->render_hero();
		$this->render_product_proof();
		$this->render_free_vs_pro();
		$this->render_accountability();

		echo '<section id="pro-pricing" class="bm-pro-sales__climax" aria-labelledby="bm-pro-pricing-title">';
		$this->render_founding_economics();
		$this->render_price();
		$this->render_cta();
		echo '</section>';

		$this->render_buyer_faq();
		$this->render_trust_links();
		$this->render_disclaimer();
		echo '</div>';
		return ob_get_clean();
	}

	private function render_hero() {
		?>
		<section id="pro-overview" class="bm-pro-sales__hero" aria-labelledby="bm-pro-hero-title">
			<p class="bm-pro-sales__hero-eyebrow"><?php esc_html_e( 'BITMOMO PRO · FOUNDING MEMBERSHIP', 'bitmomo-pro' ); ?></p>
			<h1 id="bm-pro-hero-title" class="bm-pro-sales__hero-title"><?php esc_html_e( 'Dari kondisi BTC sekarang ke skenario yang perlu dipantau berikutnya.', 'bitmomo-pro' ); ?></h1>
			<p class="bm-pro-sales__hero-sub"><?php esc_html_e( 'BTC Intelligence Free menjelaskan apa yang terjadi sekarang. Pro menambahkan Expected Range, Scenario Map, Thesis Invalidation, dan konteks perubahan untuk membantu menilai apa yang terjadi berikutnya.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__hero-offer">
				<div><p class="bm-pro-sales__hero-price"><?php esc_html_e( 'Founding Price Rp149.000/bulan', 'bitmomo-pro' ); ?></p><p class="bm-pro-sales__hero-price-sub"><?php esc_html_e( 'Rp1.490.000/tahun · hemat Rp298.000 dibanding 12× paket bulanan.', 'bitmomo-pro' ); ?></p></div>
				<p class="bm-pro-sales__hero-lock"><?php echo esc_html( sprintf( __( 'Maksimum %d Founding Members.', 'bitmomo-pro' ), self::SEAT_CAP ) ); ?></p>
			</div>
			<div class="bm-pro-sales__hero-actions">
				<?php $this->render_cta_link( 'bm-pro-sales__hero-cta' ); ?>
				<a class="bm-pro-sales__secondary-cta" href="#pro-example"><?php esc_html_e( 'Lihat Decision View historis', 'bitmomo-pro' ); ?></a>
			</div>
			<p class="bm-pro-sales__hero-micro"><?php esc_html_e( 'Whitelist tidak membutuhkan pembayaran dan tidak menjamin tempat. Checkout belum dibuka.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_cta_link( $extra_class = '' ) {
		$url   = bitmomo_pro_get_checkout_url();
		$class = trim( 'bm-pro-sales__cta ' . $extra_class );
		if ( empty( $url ) ) {
			printf( '<a class="%1$s" href="#bm-pro-whitelist">%2$s</a>', esc_attr( $class ), esc_html__( 'GABUNG FOUNDING WHITELIST', 'bitmomo-pro' ) );
			return;
		}
		printf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html__( 'Kunci Harga Founding', 'bitmomo-pro' ) );
	}

	private function delayed_public_proof() {
		if ( ! class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) || ! method_exists( 'Bitmomo_Btc_Intelligence_Accountability', 'delayed_proof' ) ) {
			return null;
		}
		$contract = Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 );
		$rows     = is_array( $contract['rows'] ?? null ) ? $contract['rows'] : array();
		return ! empty( $rows[0] ) && is_array( $rows[0] ) ? $rows[0] : null;
	}

	private function render_product_proof() {
		$row = $this->delayed_public_proof();
		?>
		<section id="pro-example" class="bm-pro-sales__product-proof" aria-labelledby="bm-pro-example-title">
			<div class="bm-pro-sales__proof-head">
				<div>
					<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'ACTUAL PRODUCT PROOF', 'bitmomo-pro' ); ?></p>
					<h2 id="bm-pro-example-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Ini bentuk Decision View yang benar-benar diterima member.', 'bitmomo-pro' ); ?></h2>
				</div>
				<span class="bm-pro-sales__historical-label"><?php esc_html_e( 'HISTORICAL · DELAYED ≥48H', 'bitmomo-pro' ); ?></span>
			</div>
			<p class="bm-pro-sales__section-intro"><?php esc_html_e( 'Contoh publik hanya berasal dari brief Pro yang sudah dibekukan saat publikasi, melewati delay minimum 48 jam, dan mencapai jendela evaluasi. Brief Pro aktif tidak dibaca atau ditampilkan di halaman ini.', 'bitmomo-pro' ); ?></p>
			<?php if ( $row ) :
				$this->render_historical_decision_view( $row );
			else : ?>
				<div class="bm-pro-sales__proof-pending" role="status">
					<strong><?php esc_html_e( 'Belum ada contoh historis yang memenuhi seluruh syarat publikasi.', 'bitmomo-pro' ); ?></strong>
					<p><?php esc_html_e( 'Bitmomo memilih ruang kosong daripada membuat range, skenario, confidence, atau outcome palsu.', 'bitmomo-pro' ); ?></p>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_historical_decision_view( array $row ) {
		$published = $this->format_wib( $row['published_at'] ?? '' );
		$state     = sanitize_key( (string) ( $row['market_state'] ?? '' ) );
		$verdict   = sanitize_key( (string) ( $row['verdict'] ?? 'unscored' ) );
		$low       = $this->positive_number( $row['expected_range_low'] ?? null );
		$high      = $this->positive_number( $row['expected_range_high'] ?? null );
		$reference = $this->positive_number( $row['reference_price'] ?? null );
		$outcome   = $this->positive_number( $row['outcome_price_24h'] ?? null );
		$base      = sanitize_textarea_field( (string) ( $row['base_scenario'] ?? '' ) );
		$bull      = sanitize_textarea_field( (string) ( $row['bull_scenario'] ?? '' ) );
		$bear      = sanitize_textarea_field( (string) ( $row['bear_scenario'] ?? '' ) );
		$settled   = 'evaluated' === sanitize_key( (string) ( $row['evaluation_status'] ?? '' ) );
		?>
		<article class="bm-pro-sales__decision-card">
			<header>
				<div><span><?php echo esc_html( $published ); ?></span><strong class="is-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( strtoupper( $state ?: '—' ) ); ?></strong></div>
				<span class="bm-pro-sales__verdict is-<?php echo esc_attr( $verdict ); ?>"><?php echo esc_html( $this->verdict_label( $verdict ) ); ?></span>
			</header>

			<div class="bm-pro-sales__decision-metrics bm-pro-sales__decision-metrics--primary">
				<div><span>BTC REFERENSI</span><strong><?php echo esc_html( $this->format_price( $reference ) ); ?></strong></div>
				<div><span>CONFIDENCE</span><strong><?php echo esc_html( isset( $row['confidence'] ) ? (int) $row['confidence'] . '/100' : '—' ); ?></strong></div>
				<div><span>HASIL +24H</span><strong><?php echo esc_html( $settled ? $this->format_return( $row['outcome_return_pct'] ?? null ) : '—' ); ?></strong></div>
				<div><span>STATUS</span><strong><?php echo esc_html( $settled ? __( 'SETTLED', 'bitmomo-pro' ) : __( 'WINDOW MISSED', 'bitmomo-pro' ) ); ?></strong></div>
			</div>

			<?php if ( null !== $low && null !== $high && $high >= $low ) :
				$positions = $this->range_positions( $low, $high, $reference, $outcome ); ?>
				<div class="bm-pro-sales__decision-visual">
					<div class="bm-pro-sales__range-head"><span>EXPECTED RANGE</span><strong><?php echo esc_html( $this->format_price( $low ) . ' – ' . $this->format_price( $high ) ); ?></strong></div>
					<div class="bm-pro-sales__range-track" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Expected Range historis %1$s sampai %2$s', 'bitmomo-pro' ), $this->format_price( $low ), $this->format_price( $high ) ) ); ?>">
						<span class="bm-pro-sales__range-band" style="--range-left:<?php echo esc_attr( $positions['range_left'] ); ?>%;--range-width:<?php echo esc_attr( $positions['range_width'] ); ?>%;"></span>
						<?php if ( null !== $reference ) : ?><span class="bm-pro-sales__range-marker is-reference" style="--marker-pos:<?php echo esc_attr( $positions['reference'] ); ?>%;"><i></i><small><?php echo esc_html( 'REF ' . $this->format_price( $reference ) ); ?></small></span><?php endif; ?>
						<?php if ( $settled && null !== $outcome ) : ?><span class="bm-pro-sales__range-marker is-outcome" style="--marker-pos:<?php echo esc_attr( $positions['outcome'] ); ?>%;"><i></i><small><?php echo esc_html( '+24H ' . $this->format_price( $outcome ) ); ?></small></span><?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $base || '' !== $bull || '' !== $bear ) : ?>
				<div class="bm-pro-sales__scenario-map" aria-label="<?php esc_attr_e( 'Scenario Map historis', 'bitmomo-pro' ); ?>">
					<?php if ( '' !== $bear ) : ?><section class="is-bear"><h3>BEAR</h3><p><?php echo esc_html( $bear ); ?></p></section><?php endif; ?>
					<?php if ( '' !== $base ) : ?><section class="is-base"><h3>BASE · WORKING THESIS</h3><p><?php echo esc_html( $base ); ?></p></section><?php endif; ?>
					<?php if ( '' !== $bull ) : ?><section class="is-bull"><h3>BULL</h3><p><?php echo esc_html( $bull ); ?></p></section><?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $row['invalidation'] ) ) : ?>
				<div class="bm-pro-sales__risk-boundary"><span>THESIS INVALIDATION</span><p><?php echo esc_html( (string) $row['invalidation'] ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $row['what_changed'] ) ) : ?>
				<div class="bm-pro-sales__change-block"><span>WHAT CHANGED</span><p><?php echo esc_html( (string) $row['what_changed'] ); ?></p></div>
			<?php endif; ?>

			<footer>
				<span><?php echo esc_html( 'Rentang tercapai: ' . ( 'yes' === ( $row['range_hit'] ?? '' ) ? 'YA' : ( 'no' === ( $row['range_hit'] ?? '' ) ? 'TIDAK' : '—' ) ) ); ?></span>
				<span><?php esc_html_e( 'Contoh historis tertunda · bukan guidance saat ini', 'bitmomo-pro' ); ?></span>
			</footer>
		</article>
		<?php
	}

	private function render_free_vs_pro() {
		$rows = array(
			array( 'Kondisi BTC sekarang', 'Ya', 'Ya' ),
			array( 'What Changed', 'Ringkas', 'Lebih dalam' ),
			array( 'Expected Range', '—', 'Ya' ),
			array( 'Scenario Map Base / Bull / Bear', '—', 'Ya' ),
			array( 'Thesis Invalidation', '—', 'Ya' ),
			array( 'What to Watch', '1 konteks utama', 'Monitoring lebih lengkap' ),
			array( 'Accountability hasil', 'Ya', 'Ya' ),
		);
		?>
		<section class="bm-pro-sales__comparison bm-pro-sales__comparison--compact" aria-labelledby="bm-pro-comparison-title">
			<p class="bm-pro-sales__eyebrow">FREE → PRO</p>
			<h2 id="bm-pro-comparison-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Free menjawab “apa yang terjadi sekarang”. Pro membantu menilai “apa yang perlu dipantau berikutnya”.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__comparison-table" role="table" aria-label="Perbandingan BTC Intelligence Free dan Bitmomo Pro">
				<div class="bm-pro-sales__comparison-row is-head" role="row"><span role="columnheader">DECISION LAYER</span><strong role="columnheader">FREE</strong><strong role="columnheader">PRO</strong></div>
				<?php foreach ( $rows as $item ) : ?>
					<div class="bm-pro-sales__comparison-row" role="row"><span role="cell"><?php echo esc_html( $item[0] ); ?></span><strong role="cell"><?php echo esc_html( $item[1] ); ?></strong><strong role="cell" class="is-yes"><?php echo esc_html( $item[2] ); ?></strong></div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function render_accountability() {
		$evaluated_n = null;
		if ( class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) && method_exists( 'Bitmomo_Btc_Intelligence_Accountability', 'decision_ledger' ) ) {
			$contract    = Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 20 );
			$evaluated_n = isset( $contract['evaluated_n'] ) ? (int) $contract['evaluated_n'] : null;
		}
		?>
		<section id="pro-proof" class="bm-pro-sales__accountability bm-pro-sales__accountability--compact" aria-labelledby="bm-pro-accountability-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'ACCOUNTABILITY', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-accountability-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Tesis dicatat sebelum outcome, lalu diuji setelah jendela evaluasi.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__accountability-grid">
				<div><strong><?php esc_html_e( 'Recorded before outcome', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Brief dibekukan saat publikasi; hasil tidak dipakai untuk menulis ulang tesis.', 'bitmomo-pro' ); ?></p></div>
				<div><strong><?php esc_html_e( 'Settled outcome', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Outcome +24h dievaluasi secara forward-only ketika jendela settlement tersedia.', 'bitmomo-pro' ); ?></p></div>
				<div><strong><?php esc_html_e( 'No accuracy theatre', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Bitmomo tidak menampilkan win-rate ketika kontrak sampel agregat belum cukup untuk mendukung persentase yang bermakna.', 'bitmomo-pro' ); ?></p></div>
			</div>
			<span class="bm-pro-sales__sample-state"><?php echo esc_html( null === $evaluated_n ? __( 'INSUFFICIENT SAMPLE', 'bitmomo-pro' ) : sprintf( __( 'INSUFFICIENT SAMPLE FOR ACCURACY %% · %d evaluasi matang terbaru tersedia', 'bitmomo-pro' ), $evaluated_n ) ); ?></span>
		</section>
		<?php
	}

	private function render_founding_economics() {
		?>
		<div class="bm-pro-sales__climax-block bm-pro-sales__economics">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-pricing-title" class="bm-pro-sales__section-title bm-pro-sales__section-title--climax"><?php esc_html_e( 'Masuk melalui whitelist. Bayar hanya ketika checkout resmi dibuka.', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__economics-anchor"><?php esc_html_e( 'FOUNDING PRICE Rp149.000/bulan', 'bitmomo-pro' ); ?></p>
			<p><?php echo esc_html( sprintf( __( 'Founding Membership dibatasi maksimum %d member. Tidak ada remaining-seat counter sampai sistem memiliki data kursi aktual yang dapat diverifikasi.', 'bitmomo-pro' ), self::SEAT_CAP ) ); ?></p>
			<p><?php esc_html_e( 'Founding Members yang menjaga membership tetap aktif mempertahankan Founding Price selama membership tersebut tetap aktif.', 'bitmomo-pro' ); ?></p>
		</div>
		<?php
	}

	private function render_price() {
		?>
		<div class="bm-pro-sales__climax-block bm-pro-sales__climax-divider bm-pro-sales__price">
			<p class="bm-pro-sales__price-stage"><?php esc_html_e( 'Tahap saat ini: Founding Whitelist', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__plans" aria-label="Pilihan harga Bitmomo Pro">
				<div><span><?php esc_html_e( 'BULANAN', 'bitmomo-pro' ); ?></span><strong><?php esc_html_e( 'Rp149.000', 'bitmomo-pro' ); ?></strong><small><?php esc_html_e( '/ bulan', 'bitmomo-pro' ); ?></small></div>
				<div class="is-best"><span><?php esc_html_e( 'TAHUNAN', 'bitmomo-pro' ); ?></span><strong><?php esc_html_e( 'Rp1.490.000', 'bitmomo-pro' ); ?></strong><small><?php esc_html_e( '/ tahun · hemat Rp298.000', 'bitmomo-pro' ); ?></small></div>
			</div>
			<div class="bm-pro-sales__terms"><p><?php esc_html_e( 'Fitur sama pada paket bulanan dan tahunan.', 'bitmomo-pro' ); ?></p><p><?php esc_html_e( 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan yang sudah dibayar.', 'bitmomo-pro' ); ?></p><p><?php esc_html_e( 'Pembayaran bersifat final setelah aktivasi, dengan pengecualian untuk pembayaran ganda, kegagalan pemberian akses dari sisi Bitmomo, kesalahan transaksi, atau kondisi lain yang diwajibkan hukum/penyedia pembayaran.', 'bitmomo-pro' ); ?></p></div>
		</div>
		<?php
	}

	private function render_cta() {
		$url = bitmomo_pro_get_checkout_url();
		echo '<div class="bm-pro-sales__climax-block bm-pro-sales__climax-divider bm-pro-sales__cta-section">';
		if ( empty( $url ) ) {
			if ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) {
				Bitmomo_Pro_Whitelist::instance()->render_widget( array( 'source' => 'pro_page' ) );
			} else {
				echo '<span class="bm-pro-sales__cta bm-pro-sales__cta--pending" id="bm-pro-whitelist">' . esc_html__( 'Pendaftaran Founding Whitelist segera dibuka.', 'bitmomo-pro' ) . '</span>';
			}
		} else {
			printf( '<a class="bm-pro-sales__cta" href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Kunci Harga Founding', 'bitmomo-pro' ) );
		}
		echo '</div>';
	}

	private function buyer_faq_ids() {
		return array( 'apa-itu-bitmomo-pro', 'free-vs-pro', 'sinyal-buy-atau-sell', 'harga-bitmomo-pro', 'apa-itu-founding-membership', 'batalkan-kapan-saja', 'kebijakan-refund', 'berhenti-dan-bergabung-kembali' );
	}

	private function buyer_faq_items() {
		$index = array();
		if ( ! class_exists( 'Bitmomo_Pro_Help_Center' ) || ! method_exists( 'Bitmomo_Pro_Help_Center', 'categories' ) ) {
			return $index;
		}
		foreach ( (array) Bitmomo_Pro_Help_Center::categories() as $category ) {
			foreach ( (array) ( $category['items'] ?? array() ) as $item ) {
				if ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$index[ $item['id'] ] = $item;
				}
			}
		}
		return $index;
	}

	private function render_buyer_faq() {
		$index = $this->buyer_faq_items();
		?>
		<section id="pro-faq" class="bm-pro-sales__buyer-faq" aria-labelledby="bm-pro-faq-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'BEFORE YOU JOIN', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-faq-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Hal yang perlu jelas sebelum bergabung.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__faq-list">
				<?php foreach ( $this->buyer_faq_ids() as $id ) : if ( empty( $index[ $id ] ) ) continue; $item = $index[ $id ]; ?>
					<details id="<?php echo esc_attr( sanitize_title( $id ) ); ?>"><summary><?php echo esc_html( (string) $item['question'] ); ?></summary><div><?php foreach ( (array) ( $item['answer'] ?? array() ) as $paragraph ) : ?><p><?php echo wp_kses_post( $paragraph ); ?></p><?php endforeach; ?></div></details>
				<?php endforeach; ?>
			</div>
			<a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Lihat seluruh Help Center →', 'bitmomo-pro' ); ?></a>
		</section>
		<?php
	}

	private function support_email() {
		$email = sanitize_email( (string) apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) ) );
		return is_email( $email ) ? $email : '';
	}

	private function render_trust_links() {
		$support_email = $this->support_email();
		?>
		<nav class="bm-pro-sales__trust-links" aria-label="<?php esc_attr_e( 'Trust dan kebijakan Bitmomo Pro', 'bitmomo-pro' ); ?>">
			<a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help Center', 'bitmomo-pro' ); ?></a>
			<?php if ( $support_email ) : ?><a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php esc_html_e( 'Support', 'bitmomo-pro' ); ?></a><?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'bitmomo-pro' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'bitmomo-pro' ); ?></a>
		</nav>
		<?php
	}

	private function render_disclaimer() {
		?>
		<section class="bm-pro-sales__disclaimer"><p><?php esc_html_e( 'Bitmomo Pro adalah alat bantu analisis, bukan nasihat keuangan. Pergerakan harga BTC dan aset kripto memiliki risiko; keputusan trading sepenuhnya tanggung jawab pengguna.', 'bitmomo-pro' ); ?></p></section>
		<?php
	}

	private function range_positions( $low, $high, $reference, $outcome ) {
		$points = array( $low, $high );
		if ( null !== $reference ) $points[] = $reference;
		if ( null !== $outcome ) $points[] = $outcome;
		$min         = min( $points );
		$max         = max( $points );
		$span        = max( 1.0, $max - $min );
		$padding     = $span * 0.12;
		$floor       = max( 0.0, $min - $padding );
		$ceiling     = $max + $padding;
		$visual_span = max( 1.0, $ceiling - $floor );
		$position    = static function ( $value ) use ( $floor, $visual_span ) {
			if ( null === $value ) return null;
			return round( max( 0, min( 100, ( ( $value - $floor ) / $visual_span ) * 100 ) ), 2 );
		};
		$range_left  = $position( $low );
		$range_right = $position( $high );
		return array(
			'range_left'  => $range_left,
			'range_width' => round( max( 1, $range_right - $range_left ), 2 ),
			'reference'   => $position( $reference ),
			'outcome'     => $position( $outcome ),
		);
	}

	private function positive_number( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	private function format_wib( $value ) {
		$timestamp = strtotime( (string) $value );
		if ( ! $timestamp ) return '—';
		$date = new DateTimeImmutable( '@' . $timestamp );
		return $date->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB';
	}

	private function format_price( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? '$' . number_format( (float) $value, 0, '.', ',' ) : '—';
	}

	private function format_return( $value ) {
		if ( ! is_numeric( $value ) ) return '—';
		$value = (float) $value;
		return ( $value > 0 ? '+' : '' ) . number_format( $value, 2 ) . '%';
	}

	private function verdict_label( $verdict ) {
		$labels = array( 'aligned' => 'SESUAI', 'missed' => 'TIDAK SESUAI', 'inconclusive' => 'TIDAK KONKLUSIF', 'unscored' => 'BELUM DINILAI' );
		$key    = sanitize_key( (string) $verdict );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels['unscored'];
	}
}

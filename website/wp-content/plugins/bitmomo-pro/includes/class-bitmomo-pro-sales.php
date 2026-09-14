<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Bitmomo Pro sales surface: [bitmomo_pro_sales].
 *
 * The buyer journey is deliberately narrow: explain the current Decision View,
 * show delayed historical proof, make Free vs Pro explicit, establish
 * accountability, then present Founding terms and the conversion path.
 */
class Bitmomo_Pro_Sales {

	const PRICE_LABEL = 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
	const SEAT_CAP    = 149;
	const BATCH_ONE   = 25;
	const METHODOLOGY_PAGE_LIVE = true;
	const SALES_ASSET_VERSION = '2026.09.14-pro-cognition-v2';

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

		// Kept for backwards compatibility with installs where the public theme
		// is absent. The canonical Bitmomo runtime detaches these two hooks at
		// plugin boot so one public SEO layer owns metadata in production.
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_meta_description' ) );
		add_action( 'wp_head', array( $this, 'render_meta_description' ) );
	}

	public function filter_meta_description( $description ) {
		return is_page( 'pro' ) ? __( 'Bitmomo Pro membantu memahami skenario BTC berikutnya, kondisi invalidasi, perubahan penting, dan rekam evaluasi hasil.', 'bitmomo-pro' ) : $description;
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
		}
	}

	public function render_sales( $atts ) {
		ob_start();
		echo '<div class="bm-pro-sales">';

		$this->render_hero();
		$this->render_section_nav();
		$this->render_what_exists_today();
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
			<p class="bm-pro-sales__hero-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP — BITMOMO PRO', 'bitmomo-pro' ); ?></p>
			<h1 id="bm-pro-hero-title" class="bm-pro-sales__hero-title"><?php esc_html_e( 'Pahami skenario berikutnya — dan kapan tesis BTC berubah.', 'bitmomo-pro' ); ?></h1>
			<p class="bm-pro-sales__hero-sub"><?php esc_html_e( 'Decision View BTC memetakan Expected Range, skenario Base/Bull/Bear, kondisi invalidasi, dan perubahan penting sejak analisis sebelumnya.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__hero-status" aria-label="Karakter produk">
				<span><?php esc_html_e( 'Decision View · produk inti Pro', 'bitmomo-pro' ); ?></span>
				<span><?php esc_html_e( 'Bukan sinyal beli/jual', 'bitmomo-pro' ); ?></span>
				<span><?php esc_html_e( 'Evidence before narrative', 'bitmomo-pro' ); ?></span>
			</div>
			<div class="bm-pro-sales__hero-offer">
				<div><p class="bm-pro-sales__hero-price"><?php esc_html_e( 'Founding Price Rp149.000/bulan', 'bitmomo-pro' ); ?></p><p class="bm-pro-sales__hero-price-sub"><?php esc_html_e( 'Rp1.490.000/tahun · hemat Rp298.000 dibanding 12× paket bulanan.', 'bitmomo-pro' ); ?></p></div>
				<p class="bm-pro-sales__hero-lock"><?php esc_html_e( 'Founding Price berlaku selama membership tetap aktif.', 'bitmomo-pro' ); ?></p>
			</div>
			<div class="bm-pro-sales__hero-facts">
				<div><strong><?php esc_html_e( 'Rp149.000', 'bitmomo-pro' ); ?></strong><span><?php esc_html_e( 'per bulan', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::SEAT_CAP ); ?></strong><span><?php esc_html_e( 'Founding Members', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::BATCH_ONE ); ?></strong><span><?php esc_html_e( 'Batch pertama', 'bitmomo-pro' ); ?></span></div>
			</div>
			<div class="bm-pro-sales__hero-actions">
				<?php $this->render_cta_link( 'bm-pro-sales__hero-cta' ); ?>
				<a class="bm-pro-sales__secondary-cta" href="#pro-example"><?php esc_html_e( 'Lihat contoh Pro', 'bitmomo-pro' ); ?></a>
			</div>
			<p class="bm-pro-sales__hero-micro"><?php esc_html_e( 'Whitelist tidak membutuhkan pembayaran dan tidak menjamin tempat. Membership aktif setelah pembayaran berhasil.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_section_nav() {
		?>
		<nav class="bm-pro-sales__rail" aria-label="<?php esc_attr_e( 'Navigasi Bitmomo Pro', 'bitmomo-pro' ); ?>">
			<a href="#pro-product"><?php esc_html_e( 'Produk', 'bitmomo-pro' ); ?></a>
			<a href="#pro-example"><?php esc_html_e( 'Contoh', 'bitmomo-pro' ); ?></a>
			<a href="#pro-proof"><?php esc_html_e( 'Bukti', 'bitmomo-pro' ); ?></a>
			<a href="#pro-pricing"><?php esc_html_e( 'Harga', 'bitmomo-pro' ); ?></a>
			<a href="#pro-faq">FAQ</a>
		</nav>
		<?php
	}

	private function render_cta_link( $extra_class = '' ) {
		$url   = bitmomo_pro_get_checkout_url();
		$class = trim( 'bm-pro-sales__cta ' . $extra_class );
		if ( empty( $url ) ) {
			printf( '<a class="%1$s" href="#bm-pro-whitelist">%2$s</a>', esc_attr( $class ), esc_html__( 'GABUNG FOUNDING WHITELIST', 'bitmomo-pro' ) );
		} else {
			printf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html__( 'Aktifkan Founding Membership', 'bitmomo-pro' ) );
		}
	}

	private function render_what_exists_today() {
		?>
		<section id="pro-product" class="bm-pro-sales__section--editorial bm-pro-sales__today" aria-labelledby="bm-pro-today-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'YANG SUDAH TERSEDIA SEKARANG', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-today-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Decision View BTC adalah produk inti Bitmomo Pro.', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__lead"><?php esc_html_e( 'BTC Intelligence gratis sudah menjelaskan kondisi saat ini, perubahan material, maknanya, dan satu konteks pantauan. Pro memperluasnya menjadi monitoring lengkap, Expected Range, skenario, dan kondisi invalidasi. Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__deliverables">
				<div><span>01</span><strong><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Rentang harga acuan berdasarkan kondisi pasar ketika analisis dibuat.', 'bitmomo-pro' ); ?></p></div>
				<div><span>02</span><strong><?php esc_html_e( 'Scenario Map', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Base, Bull, dan Bear beserta kondisi yang mendukung masing-masing jalur.', 'bitmomo-pro' ); ?></p></div>
				<div><span>03</span><strong><?php esc_html_e( 'Invalidasi Tesis', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Kondisi yang membuat tesis utama tidak lagi berlaku.', 'bitmomo-pro' ); ?></p></div>
				<div><span>04</span><strong><?php esc_html_e( 'Full Monitoring', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Seluruh kondisi yang perlu dipantau setelah brief, bukan hanya satu konteks publik.', 'bitmomo-pro' ); ?></p></div>
				<div><span>05</span><strong><?php esc_html_e( 'Confidence Context', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Penjelasan lebih dalam tentang kekuatan bukti di balik tesis; bukan probabilitas arah harga atau hasil investasi.', 'bitmomo-pro' ); ?></p></div>
			</div>
		</section>
		<?php
	}

	private function delayed_public_proof() {
		if ( ! class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) || ! method_exists( 'Bitmomo_Btc_Intelligence_Accountability', 'delayed_proof' ) ) {
			return null;
		}
		$contract = Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 );
		$rows = is_array( $contract['rows'] ?? null ) ? $contract['rows'] : array();
		return ! empty( $rows[0] ) && is_array( $rows[0] ) ? $rows[0] : null;
	}

	private function render_product_proof() {
		$row = $this->delayed_public_proof();
		?>
		<section id="pro-example" class="bm-pro-sales__product-proof" aria-labelledby="bm-pro-example-title">
			<div class="bm-pro-sales__proof-head">
				<div><p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'PRODUCT PROOF', 'bitmomo-pro' ); ?></p><h2 id="bm-pro-example-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Lihat bentuk decision support-nya, bukan sekadar daftar fitur.', 'bitmomo-pro' ); ?></h2></div>
				<span class="bm-pro-sales__proof-badge"><?php esc_html_e( 'ARSIP ≥ 48 JAM', 'bitmomo-pro' ); ?></span>
			</div>
			<p class="bm-pro-sales__section-intro"><?php esc_html_e( 'Contoh di bawah hanya menggunakan analisis Pro historis yang telah melewati periode publikasi tertunda dan evaluasi hasil. Analisis Pro aktif tidak ditampilkan pada halaman publik.', 'bitmomo-pro' ); ?></p>
			<?php if ( $row ) :
				$published = $this->format_wib( $row['published_at'] ?? '' );
				$state = sanitize_key( (string) ( $row['market_state'] ?? '' ) );
				$verdict = sanitize_key( (string) ( $row['verdict'] ?? 'unscored' ) );
				?>
				<article class="bm-pro-sales__decision-card">
					<header><div><span><?php echo esc_html( $published ); ?></span><strong class="is-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( strtoupper( $state ?: '—' ) ); ?></strong></div><span class="bm-pro-sales__verdict is-<?php echo esc_attr( $verdict ); ?>"><?php echo esc_html( $this->verdict_label( $verdict ) ); ?></span></header>
					<div class="bm-pro-sales__decision-metrics">
						<div><span>BTC REFERENSI</span><strong><?php echo esc_html( $this->format_price( $row['reference_price'] ?? null ) ); ?></strong></div>
						<div><span>EXPECTED RANGE</span><strong><?php echo esc_html( $this->format_price( $row['expected_range_low'] ?? null ) . ' – ' . $this->format_price( $row['expected_range_high'] ?? null ) ); ?></strong></div>
						<div><span>CONFIDENCE</span><strong><?php echo esc_html( isset( $row['confidence'] ) ? (int) $row['confidence'] . '/100' : '—' ); ?></strong></div>
						<div><span>HASIL +24H</span><strong><?php echo esc_html( $this->format_return( $row['outcome_return_pct'] ?? null ) ); ?></strong></div>
					</div>
					<div class="bm-pro-sales__decision-copy"><div><span>BASE CASE</span><p><?php echo esc_html( (string) ( $row['base_scenario'] ?? '' ) ); ?></p></div><div><span>INVALIDASI</span><p><?php echo esc_html( (string) ( $row['invalidation'] ?? '' ) ); ?></p></div><?php if ( ! empty( $row['what_changed'] ) ) : ?><div><span>WHAT CHANGED</span><p><?php echo esc_html( (string) $row['what_changed'] ); ?></p></div><?php endif; ?></div>
					<footer><span><?php echo esc_html( 'Rentang tercapai: ' . ( 'yes' === ( $row['range_hit'] ?? '' ) ? 'YA' : ( 'no' === ( $row['range_hit'] ?? '' ) ? 'TIDAK' : '—' ) ) ); ?></span><span><?php esc_html_e( 'Arsip historis · bukan guidance saat ini', 'bitmomo-pro' ); ?></span></footer>
				</article>
			<?php else : ?>
				<div class="bm-pro-sales__proof-pending" role="status"><strong><?php esc_html_e( 'Belum ada analisis historis yang memenuhi syarat publikasi dan evaluasi.', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Bitmomo tidak membuat harga, confidence, atau hasil contoh untuk mengisi ruang ini.', 'bitmomo-pro' ); ?></p></div>
			<?php endif; ?>
			<a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/btc-intelligence/#pro-archive' ) ); ?>"><?php esc_html_e( 'Buka Decision Ledger & arsip Pro publik →', 'bitmomo-pro' ); ?></a>
		</section>
		<?php
	}

	private function render_free_vs_pro() {
		$rows = array(
			array( 'Kondisi BTC sekarang', 'Ya', 'Ya' ),
			array( 'Bias & Confidence', 'Ya', 'Ya' ),
			array( 'What Changed · ringkas', 'Ya', 'Ya' ),
			array( 'Why It Matters', 'Ya', 'Ya' ),
			array( '1 konteks What to Watch', 'Ya', 'Ya' ),
			array( 'Full monitoring / watch set', '—', 'Ya' ),
			array( 'Expected Range', '—', 'Ya' ),
			array( 'Base / Bull / Bear', '—', 'Ya' ),
			array( 'Kondisi invalidasi tesis', '—', 'Ya' ),
			array( 'Public Decision Ledger', 'Ya', 'Ya' ),
		);
		?>
		<section class="bm-pro-sales__comparison" aria-labelledby="bm-pro-comparison-title">
			<p class="bm-pro-sales__eyebrow">FREE → PRO</p>
			<h2 id="bm-pro-comparison-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Bedanya bukan lebih banyak data. Bedanya adalah kedalaman keputusan.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__comparison-table" role="table" aria-label="Perbandingan BTC Intelligence gratis dan Bitmomo Pro">
				<div class="bm-pro-sales__comparison-row is-head" role="row"><span role="columnheader">INTELLIGENCE</span><strong role="columnheader">GRATIS</strong><strong role="columnheader">PRO</strong></div>
				<?php foreach ( $rows as $item ) : ?><div class="bm-pro-sales__comparison-row" role="row"><span role="cell"><?php echo esc_html( $item[0] ); ?></span><strong role="cell" class="<?php echo 'Ya' === $item[1] ? 'is-yes' : 'is-no'; ?>"><?php echo esc_html( $item[1] ); ?></strong><strong role="cell" class="is-yes"><?php echo esc_html( $item[2] ); ?></strong></div><?php endforeach; ?>
			</div>
			<p class="bm-pro-sales__micro-note"><?php esc_html_e( 'Gratis sudah cukup untuk memahami kondisi, perubahan, makna, dan satu konteks pantauan. Pro menambahkan monitoring lengkap, skenario, level, dan invalidasi untuk menavigasi apa yang terjadi berikutnya.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_accountability() {
		?>
		<section id="pro-proof" class="bm-pro-sales__accountability" aria-labelledby="bm-pro-accountability-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'ACCOUNTABILITY', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-accountability-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Setiap analisis harus dapat diuji terhadap hasil aktual.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__accountability-grid">
				<div><strong><?php esc_html_e( 'Dicatat sebelum hasil diketahui', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Tesis dan konteks dibekukan sebelum outcome diketahui, bukan ditulis ulang setelah pasar bergerak.', 'bitmomo-pro' ); ?></p></div>
				<div><strong><?php esc_html_e( 'Tidak memilih hasil yang bagus saja', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Decision Ledger mempertahankan hasil yang sesuai, tidak sesuai, tidak konklusif, maupun yang belum dapat dinilai.', 'bitmomo-pro' ); ?></p></div>
				<div><strong><?php esc_html_e( 'Batas metode tetap terlihat', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Confidence, jendela evaluasi, dan keterbatasan sampel dijelaskan agar angka tidak berdiri tanpa konteks.', 'bitmomo-pro' ); ?></p></div>
			</div>
			<?php if ( self::METHODOLOGY_PAGE_LIVE ) : ?><a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>"><?php esc_html_e( 'Audit Decision Ledger & track record →', 'bitmomo-pro' ); ?></a><?php endif; ?>
		</section>
		<?php
	}

	private function render_founding_economics() {
		?>
		<div class="bm-pro-sales__climax-block bm-pro-sales__economics">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-pricing-title" class="bm-pro-sales__section-title bm-pro-sales__section-title--climax"><?php esc_html_e( 'Akses awal ke produk yang sudah dapat dinilai hari ini.', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__economics-anchor"><?php esc_html_e( 'FOUNDING PRICE Rp149.000/bulan', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Founding Membership mencakup Decision View BTC dan fitur baru yang nantinya ditambahkan ke Bitmomo Pro. Harga untuk member baru dapat berubah seiring pengembangan produk.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Founding Members yang menjaga membership tetap aktif mempertahankan Founding Price selama membership tersebut tetap aktif.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__economics-boundary"><?php esc_html_e( 'Produk Bitmomo yang terpisah di masa depan dapat memiliki harga tersendiri.', 'bitmomo-pro' ); ?></p>
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
				echo '<span class="bm-pro-sales__cta bm-pro-sales__cta--pending" id="bm-pro-whitelist">' . esc_html__( 'Pendaftaran Founding Membership segera dibuka.', 'bitmomo-pro' ) . '</span>';
			}
		} else {
			printf( '<a class="bm-pro-sales__cta" href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Aktifkan Founding Membership', 'bitmomo-pro' ) );
		}
		echo '</div>';
	}

	private function buyer_faq_ids() {
		return array( 'apa-itu-bitmomo-pro', 'free-vs-pro', 'sinyal-buy-atau-sell', 'harga-bitmomo-pro', 'apa-itu-founding-membership', 'batalkan-kapan-saja', 'kebijakan-refund', 'berhenti-dan-bergabung-kembali' );
	}

	private function buyer_faq_items() {
		$index = array();
		if ( ! class_exists( 'Bitmomo_Pro_Help_Center' ) || ! method_exists( 'Bitmomo_Pro_Help_Center', 'categories' ) ) return $index;
		foreach ( (array) Bitmomo_Pro_Help_Center::categories() as $category ) {
			foreach ( (array) ( $category['items'] ?? array() ) as $item ) {
				if ( is_array( $item ) && ! empty( $item['id'] ) ) $index[ $item['id'] ] = $item;
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
			<a href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>"><?php esc_html_e( 'Decision Ledger', 'bitmomo-pro' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help Center', 'bitmomo-pro' ); ?></a>
			<?php if ( $support_email ) : ?><a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php esc_html_e( 'Support', 'bitmomo-pro' ); ?></a><?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'bitmomo-pro' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'bitmomo-pro' ); ?></a>
		</nav>
		<?php
		unset( $support_email );
	}

	private function render_disclaimer() {
		?>
		<section class="bm-pro-sales__disclaimer"><p><?php esc_html_e( 'Bitmomo Pro adalah alat bantu analisis, bukan nasihat keuangan. Pergerakan harga BTC dan aset kripto memiliki risiko; keputusan trading sepenuhnya tanggung jawab pengguna.', 'bitmomo-pro' ); ?></p></section>
		<?php
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
		$key = sanitize_key( (string) $verdict );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels['unscored'];
	}
}

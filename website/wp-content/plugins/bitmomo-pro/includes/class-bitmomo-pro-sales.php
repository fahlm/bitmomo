<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Bitmomo Pro sales surface: [bitmomo_pro_sales].
 *
 * The page is deliberately proof-first and current-first. A cold visitor must
 * understand what exists today, see how the paid layer differs from the free
 * layer, inspect accountable historical proof when it is safe to publish, and
 * understand the commercial terms before roadmap capabilities are discussed.
 */
class Bitmomo_Pro_Sales {

	const PRICE_LABEL = 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
	const SEAT_CAP    = 149;
	const BATCH_ONE   = 25;
	const METHODOLOGY_PAGE_LIVE = true;
	const SALES_ASSET_VERSION = '2026.09.13-pro-conversion-v1';

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
		return is_page( 'pro' ) ? __( 'Bitmomo Pro membantu Anda memahami kondisi BTC, skenario yang relevan, dan apa yang dapat mengubah thesis pasar.', 'bitmomo-pro' ) : $description;
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
		$this->render_context_problem();
		$this->render_intelligence_flow();
		$this->render_accountability();
		$this->render_market_experience();

		// Conversion follows the current product/proof story, not the roadmap.
		echo '<section id="pro-pricing" class="bm-pro-sales__climax" aria-labelledby="bm-pro-pricing-title">';
		$this->render_founding_economics();
		$this->render_price();
		$this->render_cta();
		echo '</section>';

		$this->render_buyer_faq();
		$this->render_roadmap();
		$this->render_trust_links();
		$this->render_disclaimer();
		echo '</div>';
		return ob_get_clean();
	}

	private function render_hero() {
		?>
		<section id="pro-overview" class="bm-pro-sales__hero" aria-labelledby="bm-pro-hero-title">
			<p class="bm-pro-sales__hero-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP — BITMOMO PRO', 'bitmomo-pro' ); ?></p>
			<h1 id="bm-pro-hero-title" class="bm-pro-sales__hero-title"><?php esc_html_e( 'Pahami BTC dalam konteks, bukan sekadar dari potongan data.', 'bitmomo-pro' ); ?></h1>
			<p class="bm-pro-sales__hero-sub"><?php esc_html_e( 'Decision View harian untuk memahami rentang, skenario, invalidation, dan perubahan penting BTC — tanpa harus menganalisis semuanya sendiri.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__hero-status" aria-label="Status produk">
				<span><?php esc_html_e( 'Decision View aktif hari ini', 'bitmomo-pro' ); ?></span>
				<span><?php esc_html_e( 'Bukan sinyal buy / sell', 'bitmomo-pro' ); ?></span>
				<span><?php esc_html_e( 'Evidence before narrative', 'bitmomo-pro' ); ?></span>
			</div>
			<div class="bm-pro-sales__hero-offer">
				<div><p class="bm-pro-sales__hero-price"><?php esc_html_e( 'Founding Price Rp149.000/bulan', 'bitmomo-pro' ); ?></p><p class="bm-pro-sales__hero-price-sub"><?php esc_html_e( 'Rp1.490.000/tahun · hemat Rp298.000 dibanding 12× paket bulanan.', 'bitmomo-pro' ); ?></p></div>
				<p class="bm-pro-sales__hero-lock"><?php esc_html_e( 'Founding price terkunci selama membership tetap aktif.', 'bitmomo-pro' ); ?></p>
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
			printf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html__( 'Kunci Harga Founding', 'bitmomo-pro' ) );
		}
	}

	/** Current capability is deliberately the first explanatory section. */
	private function render_what_exists_today() {
		?>
		<section id="pro-product" class="bm-pro-sales__section--editorial bm-pro-sales__today" aria-labelledby="bm-pro-today-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'YANG SUDAH TERSEDIA SEKARANG', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-today-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Decision View BTC, aktif setiap hari.', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__lead"><?php esc_html_e( 'Gratis membantu memahami apa yang sedang terjadi. Pro membantu memetakan apa yang perlu diperhatikan berikutnya.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__deliverables">
				<div><span>01</span><strong><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Rentang harga yang realistis berdasarkan kondisi saat intelligence dibuat.', 'bitmomo-pro' ); ?></p></div>
				<div><span>02</span><strong><?php esc_html_e( 'Scenario Map', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Base, Bull, dan Bear beserta kondisi yang mendukung masing-masing jalur.', 'bitmomo-pro' ); ?></p></div>
				<div><span>03</span><strong><?php esc_html_e( 'Thesis Invalidation', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Kondisi yang membuat thesis utama tidak lagi layak dipertahankan.', 'bitmomo-pro' ); ?></p></div>
				<div><span>04</span><strong><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Perubahan yang benar-benar relevan dibanding Decision View sebelumnya.', 'bitmomo-pro' ); ?></p></div>
				<div><span>05</span><strong><?php esc_html_e( 'Confidence Explanation', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Seberapa konsisten bukti mendukung thesis; bukan probabilitas profit.', 'bitmomo-pro' ); ?></p></div>
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
			<p class="bm-pro-sales__section-intro"><?php esc_html_e( 'Contoh di bawah hanya memakai brief Pro historis yang sudah melewati delay publik dan settlement gate. Guidance aktif tidak pernah dibocorkan ke landing page.', 'bitmomo-pro' ); ?></p>
			<?php if ( $row ) :
				$published = ! empty( $row['published_at'] ) && strtotime( $row['published_at'] ) ? wp_date( 'd M Y · H:i', strtotime( $row['published_at'] ) ) . ' WIB' : '—';
				$state = sanitize_key( (string) ( $row['market_state'] ?? '' ) );
				$verdict = sanitize_key( (string) ( $row['verdict'] ?? 'unscored' ) );
				?>
				<article class="bm-pro-sales__decision-card">
					<header><div><span><?php echo esc_html( $published ); ?></span><strong class="is-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( strtoupper( $state ?: '—' ) ); ?></strong></div><span class="bm-pro-sales__verdict is-<?php echo esc_attr( $verdict ); ?>"><?php echo esc_html( $this->verdict_label( $verdict ) ); ?></span></header>
					<div class="bm-pro-sales__decision-metrics">
						<div><span>BTC REFERENSI</span><strong><?php echo esc_html( $this->format_price( $row['reference_price'] ?? null ) ); ?></strong></div>
						<div><span>EXPECTED RANGE</span><strong><?php echo esc_html( $this->format_price( $row['expected_range_low'] ?? null ) . ' – ' . $this->format_price( $row['expected_range_high'] ?? null ) ); ?></strong></div>
						<div><span>CONFIDENCE</span><strong><?php echo esc_html( isset( $row['confidence'] ) ? (int) $row['confidence'] . '/100' : '—' ); ?></strong></div>
						<div><span>OUTCOME +24H</span><strong><?php echo esc_html( $this->format_return( $row['outcome_return_pct'] ?? null ) ); ?></strong></div>
					</div>
					<div class="bm-pro-sales__decision-copy"><div><span>BASE CASE</span><p><?php echo esc_html( (string) ( $row['base_scenario'] ?? '' ) ); ?></p></div><div><span>INVALIDATION</span><p><?php echo esc_html( (string) ( $row['invalidation'] ?? '' ) ); ?></p></div><?php if ( ! empty( $row['what_changed'] ) ) : ?><div><span>WHAT CHANGED</span><p><?php echo esc_html( (string) $row['what_changed'] ); ?></p></div><?php endif; ?></div>
					<footer><span><?php echo esc_html( 'Range hit: ' . ( 'yes' === ( $row['range_hit'] ?? '' ) ? 'YA' : ( 'no' === ( $row['range_hit'] ?? '' ) ? 'TIDAK' : '—' ) ) ); ?></span><span><?php esc_html_e( 'Arsip historis · bukan guidance saat ini', 'bitmomo-pro' ); ?></span></footer>
				</article>
			<?php else : ?>
				<div class="bm-pro-sales__proof-pending" role="status"><strong><?php esc_html_e( 'Arsip historis sedang menunggu brief yang memenuhi publication + delay + settlement gate.', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Bitmomo tidak membuat mock price, mock confidence, atau contoh hasil palsu untuk mengisi ruang ini.', 'bitmomo-pro' ); ?></p></div>
			<?php endif; ?>
			<a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/btc-intelligence/#pro-archive' ) ); ?>"><?php esc_html_e( 'Buka Decision Ledger & arsip Pro publik →', 'bitmomo-pro' ); ?></a>
		</section>
		<?php
	}

	private function render_free_vs_pro() {
		$rows = array(
			array( 'Kondisi BTC sekarang', 'Ya', 'Ya' ),
			array( 'Bias & Confidence', 'Ya', 'Ya' ),
			array( 'Expected Range', '—', 'Ya' ),
			array( 'Base / Bull / Bear scenarios', '—', 'Ya' ),
			array( 'Thesis Invalidation', '—', 'Ya' ),
			array( 'Apa yang perlu dipantau berikutnya', '—', 'Ya' ),
			array( 'Public Decision Ledger', 'Ya', 'Ya' ),
		);
		?>
		<section class="bm-pro-sales__comparison" aria-labelledby="bm-pro-comparison-title">
			<p class="bm-pro-sales__eyebrow">FREE → PRO</p>
			<h2 id="bm-pro-comparison-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Bedanya bukan lebih banyak data. Bedanya adalah keputusan apa yang dibantu.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__comparison-table" role="table" aria-label="Perbandingan BTC Intelligence gratis dan Bitmomo Pro">
				<div class="bm-pro-sales__comparison-row is-head" role="row"><span role="columnheader">INTELLIGENCE</span><strong role="columnheader">GRATIS</strong><strong role="columnheader">PRO</strong></div>
				<?php foreach ( $rows as $item ) : ?><div class="bm-pro-sales__comparison-row" role="row"><span role="cell"><?php echo esc_html( $item[0] ); ?></span><strong role="cell" class="<?php echo 'Ya' === $item[1] ? 'is-yes' : 'is-no'; ?>"><?php echo esc_html( $item[1] ); ?></strong><strong role="cell" class="is-yes"><?php echo esc_html( $item[2] ); ?></strong></div><?php endforeach; ?>
			</div>
			<p class="bm-pro-sales__micro-note"><?php esc_html_e( 'Free menjawab “apa yang terjadi sekarang”. Pro menambahkan scenario planning, invalidation, dan monitoring untuk menjawab “apa berikutnya dan kapan thesis harus berubah”.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_context_problem() {
		?>
		<section class="bm-pro-sales__section--editorial bm-pro-sales__context">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Data ada di mana-mana. Konteks yang jarang.', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__lead"><?php esc_html_e( 'Pasar kripto tidak kekurangan data. Yang sulit adalah memahami apa arti data itu ketika semuanya berubah pada saat yang sama.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Tools on-chain dan AI generik berguna, tapi keduanya berhenti di data mentah atau jawaban umum, bukan di pemahaman. Bitmomo mengisi bagian itu: mengubah data pasar menjadi konteks yang bisa langsung dipakai untuk mengambil keputusan.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__pullquote"><?php esc_html_e( 'Data bisa ditemukan di banyak tempat. Konteks dibangun dari pengalaman.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_intelligence_flow() {
		$stages = array(
			array( 'label' => __( 'DATA', 'bitmomo-pro' ), 'desc' => __( 'Harga, struktur pasar, funding/basis, positioning derivatives, momentum, dan volatilitas BTC diproses dari data pasar yang tersedia.', 'bitmomo-pro' ) ),
			array( 'label' => __( 'CONTEXT', 'bitmomo-pro' ), 'desc' => __( 'Data dibaca dalam konteks kondisi pasar dan pengalaman lintas siklus, bukan sebagai angka yang berdiri sendiri.', 'bitmomo-pro' ) ),
			array( 'label' => __( 'INTELLIGENCE', 'bitmomo-pro' ), 'desc' => __( 'Konteks diringkas menjadi kondisi pasar, arah, dan tingkat keyakinan yang mudah dibaca.', 'bitmomo-pro' ) ),
			array( 'label' => __( 'THESIS', 'bitmomo-pro' ), 'desc' => __( 'Kondisi tersebut disusun menjadi Expected Range, skenario Base/Bull/Bear, dan kondisi yang membatalkan thesis.', 'bitmomo-pro' ) ),
			array( 'label' => __( 'MONITORING', 'bitmomo-pro' ), 'desc' => __( 'Perubahan penting dibanding pembacaan sebelumnya ditandai agar thesis tidak dipertahankan secara buta.', 'bitmomo-pro' ) ),
			array( 'label' => __( 'ACCOUNTABILITY', 'bitmomo-pro' ), 'desc' => __( 'Setiap thesis dicatat sebelum hasil diketahui, lalu dievaluasi terhadap outcome aktual.', 'bitmomo-pro' ) ),
		);
		?>
		<section class="bm-pro-sales__system" aria-labelledby="bm-pro-system-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'INTELLIGENCE SYSTEM', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-system-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Bagaimana Bitmomo mengubah data menjadi intelligence', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__flow-stages">
				<?php foreach ( $stages as $i => $stage ) : ?><div class="bm-pro-sales__flow-stage"><span class="bm-pro-sales__flow-index"><?php echo esc_html( $i + 1 ); ?></span><h3><?php echo esc_html( $stage['label'] ); ?></h3><p><?php echo esc_html( $stage['desc'] ); ?></p></div><?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function render_accountability() {
		?>
		<section id="pro-proof" class="bm-pro-sales__accountability" aria-labelledby="bm-pro-accountability-title">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'ACCOUNTABILITY', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-accountability-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Setiap analisis harus bisa dipertanggungjawabkan.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__accountability-grid">
				<div><strong><?php esc_html_e( 'Dicatat sebelum outcome', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Thesis dan konteks dibekukan sebelum hasil diketahui, bukan ditulis ulang setelah pasar bergerak.', 'bitmomo-pro' ); ?></p></div>
				<div><strong><?php esc_html_e( 'Tidak memilih hasil yang bagus saja', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Decision Ledger mempertahankan hasil yang sesuai, meleset, inconclusive, maupun settlement window yang terlewat.', 'bitmomo-pro' ); ?></p></div>
				<div><strong><?php esc_html_e( 'Metodologi ikut terlihat', 'bitmomo-pro' ); ?></strong><p><?php esc_html_e( 'Confidence, outcome window, dan sample limitations dijelaskan agar angka tidak berdiri tanpa konteks.', 'bitmomo-pro' ); ?></p></div>
			</div>
			<?php if ( self::METHODOLOGY_PAGE_LIVE ) : ?><a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>"><?php esc_html_e( 'Audit Decision Ledger & track record →', 'bitmomo-pro' ); ?></a><?php endif; ?>
		</section>
		<?php
	}

	private function render_market_experience() {
		?>
		<section class="bm-pro-sales__section--editorial bm-pro-sales__lineage">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'DIBANGUN DARI PENGALAMAN PASAR', 'bitmomo-pro' ); ?></p>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'AI adalah bagian dari sistem. Konteks adalah fondasinya.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Bitmomo dikembangkan dari pengalaman riset dan partisipasi di pasar kripto sejak 2016. Kerangka analisisnya dibangun untuk membaca pasar lintas siklus, lalu menggunakan AI sebagai alat untuk membantu compression, consistency, dan monitoring — bukan sebagai oracle.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__principles"><span><?php esc_html_e( 'Pengalaman lintas siklus', 'bitmomo-pro' ); ?></span><span><?php esc_html_e( 'Evidence-first', 'bitmomo-pro' ); ?></span><span><?php esc_html_e( 'Forward evaluation', 'bitmomo-pro' ); ?></span></div>
			<a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/tentang-kami/' ) ); ?>"><?php esc_html_e( 'Tentang Bitmomo →', 'bitmomo-pro' ); ?></a>
		</section>
		<?php
	}

	private function render_founding_economics() {
		?>
		<div class="bm-pro-sales__climax-block bm-pro-sales__economics">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo-pro' ); ?></p>
			<h2 id="bm-pro-pricing-title" class="bm-pro-sales__section-title bm-pro-sales__section-title--climax"><?php esc_html_e( 'Bergabung sebelum Bitmomo Pro mencapai bentuk penuhnya.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__progression">
				<div class="bm-pro-sales__progression-step"><span class="bm-pro-sales__progression-label"><?php esc_html_e( 'HARI INI', 'bitmomo-pro' ); ?></span><p><?php esc_html_e( 'Decision View BTC harian.', 'bitmomo-pro' ); ?></p></div>
				<div class="bm-pro-sales__progression-step"><span class="bm-pro-sales__progression-label"><?php esc_html_e( 'SEGERA HADIR', 'bitmomo-pro' ); ?></span><p><?php esc_html_e( 'Altcoin Intelligence, Daily Alpha Discovery, Watchtower, dan 11 AI Analysts.', 'bitmomo-pro' ); ?></p></div>
				<div class="bm-pro-sales__progression-step"><span class="bm-pro-sales__progression-label"><?php esc_html_e( 'KE DEPAN', 'bitmomo-pro' ); ?></span><p><?php esc_html_e( 'Capability baru yang terus ditambahkan ke Bitmomo Pro.', 'bitmomo-pro' ); ?></p></div>
			</div>
			<p class="bm-pro-sales__economics-anchor"><?php esc_html_e( 'FOUNDING PRICE Rp149.000/bulan', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Rp149.000/bulan adalah Founding Price. Harga membership baru akan berubah seiring pengembangan fitur dan teknologi Bitmomo Pro. Founding Members yang menjaga membership tetap aktif dapat mempertahankan Founding Price selamanya.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__economics-boundary"><?php esc_html_e( 'Founding benefit berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro. Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.', 'bitmomo-pro' ); ?></p>
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
			printf( '<a class="bm-pro-sales__cta" href="%1$s">%2$s</a>', esc_url( $url ), esc_html__( 'Kunci Harga Founding', 'bitmomo-pro' ) );
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
			<h2 id="bm-pro-faq-title" class="bm-pro-sales__section-title"><?php esc_html_e( 'Pertanyaan yang seharusnya jelas sebelum Anda membayar.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__faq-list">
				<?php foreach ( $this->buyer_faq_ids() as $id ) : if ( empty( $index[ $id ] ) ) continue; $item = $index[ $id ]; ?>
					<details id="<?php echo esc_attr( sanitize_title( $id ) ); ?>"><summary><?php echo esc_html( (string) $item['question'] ); ?></summary><div><?php foreach ( (array) ( $item['answer'] ?? array() ) as $paragraph ) : ?><p><?php echo wp_kses_post( $paragraph ); ?></p><?php endforeach; ?></div></details>
				<?php endforeach; ?>
			</div>
			<a class="bm-pro-sales__text-link" href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Lihat seluruh Help Center →', 'bitmomo-pro' ); ?></a>
		</section>
		<?php
	}

	/** Roadmap is intentionally compact and appears after the buying decision. */
	private function render_roadmap() {
		?>
		<section class="bm-pro-sales__roadmap">
			<details>
				<summary><span class="bm-pro-sales__status-badge"><?php esc_html_e( 'ROADMAP — SEGERA HADIR', 'bitmomo-pro' ); ?></span><strong><?php esc_html_e( 'Apa yang sedang dibangun setelah Decision View BTC?', 'bitmomo-pro' ); ?></strong></summary>
				<div><p><?php esc_html_e( 'Capability berikut belum live hari ini dan tidak memiliki tanggal rilis yang dijanjikan. Semuanya tetap melewati riset, validasi, dan release gate Bitmomo.', 'bitmomo-pro' ); ?></p><ul class="bm-pro-sales__list bm-pro-sales__roadmap-list"><li><strong><?php esc_html_e( 'Altcoin Intelligence', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'relative strength, participation, positioning, structure, dan crowding.', 'bitmomo-pro' ); ?></li><li><strong><?php esc_html_e( 'Daily Alpha Discovery', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'menyaring perubahan aktivitas yang tidak biasa agar market yang layak diperhatikan lebih cepat terlihat.', 'bitmomo-pro' ); ?></li><li><strong><?php esc_html_e( '11 AI Analysts', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'perspektif spesialis dalam satu kerangka evidence dan evaluation.', 'bitmomo-pro' ); ?></li><li><strong><?php esc_html_e( 'Watchtower', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'monitoring perubahan thesis-relevant; bukan news feed.', 'bitmomo-pro' ); ?></li></ul><p class="bm-pro-sales__signature"><?php esc_html_e( '11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.', 'bitmomo-pro' ); ?></p></div>
			</details>
		</section>
		<?php
	}

	private function render_trust_links() {
		?>
		<nav class="bm-pro-sales__trust-links" aria-label="<?php esc_attr_e( 'Trust dan kebijakan Bitmomo Pro', 'bitmomo-pro' ); ?>">
			<a href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>"><?php esc_html_e( 'Decision Ledger', 'bitmomo-pro' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help Center', 'bitmomo-pro' ); ?></a>
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

	private function format_price( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? '$' . number_format( (float) $value, 0, '.', ',' ) : '—';
	}

	private function format_return( $value ) {
		if ( ! is_numeric( $value ) ) return '—';
		$value = (float) $value;
		return ( $value > 0 ? '+' : '' ) . number_format( $value, 2 ) . '%';
	}

	private function verdict_label( $verdict ) {
		$labels = array( 'aligned' => 'SESUAI', 'missed' => 'MELESET', 'inconclusive' => 'TIDAK KONKLUSIF', 'unscored' => 'TAK DINILAI' );
		$key = sanitize_key( (string) $verdict );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels['unscored'];
	}
}

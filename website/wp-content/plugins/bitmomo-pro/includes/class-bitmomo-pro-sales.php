<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Bitmomo Pro sales surface: [bitmomo_pro_sales].
 *
 * This renderer is intentionally current-first. A cold visitor must understand
 * what is live today, why it matters, how it is evaluated, and what it costs
 * before seeing roadmap capabilities. Future work is one compact, explicitly
 * non-live section rather than the dominant middle of the sales page.
 */
class Bitmomo_Pro_Sales {

	const PRICE_LABEL = 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
	const SEAT_CAP    = 149;
	const BATCH_ONE   = 25;
	const METHODOLOGY_PAGE_LIVE = true;

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
			wp_enqueue_style( 'bitmomo-pro-sales', BITMOMO_PRO_URL . 'assets/css/bitmomo-pro-sales.css', array(), BITMOMO_PRO_VERSION );
		}
	}

	public function render_sales( $atts ) {
		ob_start();
		echo '<div class="bm-pro-sales">';

		$this->render_hero();
		$this->render_what_exists_today();
		$this->render_context_problem();
		$this->render_intelligence_flow();
		$this->render_accountability();
		$this->render_market_experience();

		// Conversion follows the current product/proof story, not the roadmap.
		echo '<div class="bm-pro-sales__climax">';
		$this->render_founding_economics();
		$this->render_price();
		$this->render_cta();
		echo '</div>';

		$this->render_roadmap();
		Bitmomo_Pro_Help_Center::render_pro_subset();
		$this->render_disclaimer();
		echo '</div>';
		return ob_get_clean();
	}

	private function render_hero() {
		?>
		<section class="bm-pro-sales__hero">
			<p class="bm-pro-sales__hero-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP — BITMOMO PRO', 'bitmomo-pro' ); ?></p>
			<h1 class="bm-pro-sales__hero-title"><?php esc_html_e( 'Pahami BTC dalam konteks, bukan sekadar dari potongan data.', 'bitmomo-pro' ); ?></h1>
			<p class="bm-pro-sales__hero-sub"><?php esc_html_e( 'Decision View harian untuk memahami rentang, skenario, invalidation, dan perubahan penting BTC.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__hero-offer">
				<p class="bm-pro-sales__hero-price"><?php esc_html_e( 'Founding Price Rp149.000/bulan', 'bitmomo-pro' ); ?></p>
				<p class="bm-pro-sales__hero-price-sub"><?php esc_html_e( 'Rp1.490.000/tahun · harga ini terkunci selama membership tetap aktif.', 'bitmomo-pro' ); ?></p>
			</div>
			<div class="bm-pro-sales__hero-facts">
				<div><strong><?php esc_html_e( 'Rp149.000', 'bitmomo-pro' ); ?></strong><span><?php esc_html_e( 'per bulan', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::SEAT_CAP ); ?></strong><span><?php esc_html_e( 'Founding Members', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::BATCH_ONE ); ?></strong><span><?php esc_html_e( 'Batch pertama', 'bitmomo-pro' ); ?></span></div>
			</div>
			<?php $this->render_cta_link( 'bm-pro-sales__hero-cta' ); ?>
		</section>
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
		<section class="bm-pro-sales__section--editorial bm-pro-sales__today">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'YANG SUDAH TERSEDIA SEKARANG', 'bitmomo-pro' ); ?></p>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Decision View BTC, aktif setiap hari.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'BTC Daily Intelligence gratis menjawab apa yang sedang terjadi sekarang. Bitmomo Pro melengkapinya dengan Decision View harian yang sudah aktif hari ini:', 'bitmomo-pro' ); ?></p>
			<ul class="bm-pro-sales__list">
				<li><strong><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'rentang harga yang realistis berdasarkan kondisi saat ini.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Scenario Map', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'Base, Bull, dan Bear — kemungkinan jalur pasar beserta kondisi pendukungnya.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Thesis Invalidation', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'kondisi yang membuat thesis utama tidak lagi layak dipertahankan.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'perubahan penting dibanding Decision View sebelumnya.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Confidence Explanation', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'seberapa kuat bukti di balik kesimpulan tersebut.', 'bitmomo-pro' ); ?></li>
			</ul>
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
			array(
				'label' => __( 'DATA', 'bitmomo-pro' ),
				'desc'  => __( 'Harga, struktur pasar, funding/basis, positioning derivatives, momentum, dan volatilitas BTC diproses dari data pasar yang tersedia.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'CONTEXT', 'bitmomo-pro' ),
				'desc'  => __( 'Data dibaca dalam konteks kondisi pasar dan pengalaman lintas siklus, bukan sebagai angka yang berdiri sendiri.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'INTELLIGENCE', 'bitmomo-pro' ),
				'desc'  => __( 'Konteks diringkas menjadi kondisi pasar, arah, dan tingkat keyakinan yang mudah dibaca.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'THESIS', 'bitmomo-pro' ),
				'desc'  => __( 'Kondisi tersebut disusun menjadi Expected Range, skenario Base/Bull/Bear, dan kondisi yang membatalkan thesis.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'MONITORING', 'bitmomo-pro' ),
				'desc'  => __( 'Perubahan penting dibanding pembacaan sebelumnya ditandai agar thesis tidak dipertahankan secara buta.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'ACCOUNTABILITY', 'bitmomo-pro' ),
				'desc'  => __( 'Setiap thesis dicatat sebelum hasil diketahui, lalu dievaluasi terhadap outcome aktual.', 'bitmomo-pro' ),
			),
		);
		?>
		<section class="bm-pro-sales__section--editorial bm-pro-sales__flow">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Bagaimana Bitmomo mengubah data menjadi intelligence', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__flow-stages">
				<?php foreach ( $stages as $i => $stage ) : ?>
					<div class="bm-pro-sales__flow-stage">
						<span class="bm-pro-sales__flow-index"><?php echo esc_html( $i + 1 ); ?></span>
						<h3><?php echo esc_html( $stage['label'] ); ?></h3>
						<p><?php echo esc_html( $stage['desc'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function render_accountability() {
		?>
		<section class="bm-pro-sales__section--editorial bm-pro-sales__section--quiet bm-pro-sales__accountability">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Setiap analisis harus bisa dipertanggungjawabkan.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Setiap thesis dicatat sebelum hasilnya diketahui, lalu dievaluasi terbuka setelah fakta terjadi — termasuk hasil yang kurang baik dan sampel yang masih kecil, ditampilkan apa adanya, tidak dihapus atau dibesar-besarkan.', 'bitmomo-pro' ); ?></p>
			<?php if ( self::METHODOLOGY_PAGE_LIVE ) : ?>
				<a class="bm-pro-sales__accountability-link" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>"><?php esc_html_e( 'Lihat metodologi & track record lengkap →', 'bitmomo-pro' ); ?></a>
			<?php else : ?>
				<p class="bm-pro-sales__accountability-note"><?php esc_html_e( 'Metodologi & track record lengkap segera tersedia sebagai halaman terpisah.', 'bitmomo-pro' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_market_experience() {
		?>
		<section class="bm-pro-sales__section--editorial bm-pro-sales__section--quiet bm-pro-sales__lineage">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'DIBANGUN DARI PENGALAMAN PASAR', 'bitmomo-pro' ); ?></p>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'AI adalah bagian dari sistem. Konteks adalah fondasinya.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Bitmomo merupakan pengembangan dari research arm Perseverance Capital, yang aktif di pasar kripto sejak 2016 — termasuk pengalaman berinvestasi Bitcoin secara konsisten dan berpartisipasi sejak awal di ETHLend maupun BNB.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Pengalaman menghadapi banyak siklus pasar itulah yang menjadi fondasi kerangka analisis Bitmomo, bukan hanya data hari ini.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__lineage-note"><?php esc_html_e( 'Bitmomo bukan dibangun dari prompt semata, tapi dari pengalaman menghadapi siklus pasar yang berulang.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_founding_economics() {
		?>
		<div class="bm-pro-sales__climax-block bm-pro-sales__economics">
			<h2 class="bm-pro-sales__section-title bm-pro-sales__section-title--climax"><?php esc_html_e( 'Bergabung sebelum Bitmomo Pro mencapai bentuk penuhnya.', 'bitmomo-pro' ); ?></h2>
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
			<p class="bm-pro-sales__price-terms"><?php esc_html_e( 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__price-terms"><?php esc_html_e( 'Pembayaran bersifat final setelah aktivasi, kecuali untuk pembayaran ganda, kesalahan transaksi, atau kondisi lain yang diwajibkan oleh hukum.', 'bitmomo-pro' ); ?></p>
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

	/** Roadmap is intentionally compact and appears after the current offer. */
	private function render_roadmap() {
		?>
		<section class="bm-pro-sales__section--editorial bm-pro-sales__section--quiet bm-pro-sales__roadmap">
			<div class="bm-pro-sales__status-row"><span class="bm-pro-sales__status-badge"><?php esc_html_e( 'ROADMAP — SEGERA HADIR', 'bitmomo-pro' ); ?></span></div>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Yang sedang kami bangun setelah Decision View BTC.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Capability di bawah belum live hari ini. Tidak ada tanggal rilis atau hasil yang dijanjikan; semuanya tetap melewati riset, validasi, dan release gate Bitmomo.', 'bitmomo-pro' ); ?></p>
			<ul class="bm-pro-sales__list bm-pro-sales__roadmap-list">
				<li><strong><?php esc_html_e( 'ALTCOIN INTELLIGENCE — SEGERA HADIR', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'konteks BTC ditambah evidence spesifik aset seperti relative strength, participation, positioning, structure, dan crowding.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'DAILY ALPHA DISCOVERY — SEGERA HADIR', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'discovery perubahan aktivitas yang tidak biasa untuk mempersempit market yang layak diperhatikan.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( '11 AI ANALYSTS — SEGERA HADIR', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'perspektif spesialis yang bekerja dalam satu kerangka evidence dan evaluation.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'WATCHTOWER — SEGERA HADIR', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'monitoring perubahan thesis-relevant; bukan news feed yang mengirim setiap pergerakan harga.', 'bitmomo-pro' ); ?></li>
			</ul>
			<p class="bm-pro-sales__signature"><?php esc_html_e( '11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	private function render_disclaimer() {
		?>
		<section class="bm-pro-sales__disclaimer">
			<p><?php esc_html_e( 'Bitmomo Pro adalah alat bantu analisis, bukan nasihat keuangan. Pergerakan harga BTC dan aset kripto memiliki risiko; keputusan trading sepenuhnya tanggung jawab pengguna.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}
}

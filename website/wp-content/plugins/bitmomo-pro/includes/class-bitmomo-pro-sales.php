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
 * Positioning (2026-09 sellability redesign): Bitmomo Pro is sold as a
 * crypto market-intelligence system — DATA -> CONTEXT -> INTELLIGENCE ->
 * THESIS -> MONITORING -> ACCOUNTABILITY — in which AI is one component,
 * not the product itself. 11 AI Analysts and Watchtower are real,
 * in-development capabilities and are marketed honestly as "SEGERA HADIR"
 * (do not imply they are live today, and do not attach an exact ship date,
 * "24/7", "real-time", or a guaranteed Telegram delivery claim to either).
 *
 * All facts below (price, seat cap, refund/cancellation wording, product
 * claims) are locked to the Founding Membership terms actually in force.
 * Do not add claims the current product doesn't support (no automated
 * real-time alerts, no fabricated accuracy/track-record numbers, no
 * ETH/altcoins) and do not add affiliate content — this plugin's rendering
 * surfaces (dashboard and sales) carry zero affiliate/ad content.
 *
 * The Perseverance Capital / market-experience claims in
 * render_market_experience() are founder-supplied and marked VERIFY BEFORE
 * SHIP in the implementation report — they intentionally carry no dates,
 * amounts, or return figures beyond what the founder confirmed.
 *
 * The six-stage flow in render_intelligence_flow() is a narrative
 * simplification for visitors, not a literal 1:1 map of backend
 * services/classes — do not let future edits imply otherwise.
 */
class Bitmomo_Pro_Sales {

	const PRICE_LABEL = 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
	const SEAT_CAP    = 149;
	const BATCH_ONE   = 25;

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
		$this->render_hero();                 // 1. Hero + Founding Offer
		$this->render_context_problem();      // 2. Data Is Abundant, Context Is Scarce
		$this->render_market_experience();    // 3. Built From Market Experience / Perseverance Capital
		$this->render_intelligence_flow();    // 4. How Bitmomo Turns Data Into Intelligence
		$this->render_ai_analysts();          // 5. 11 AI Analysts
		$this->render_watchtower();           // 6. Watchtower
		$this->render_what_exists_today();    // 7. What Exists Today
		$this->render_founding_economics();   // 8a. Founding Membership Economics
		$this->render_price();                // 8b. Pricing
		$this->render_cta();                  // 8c. Whitelist
		$this->render_accountability();       // 9. Accountability
		Bitmomo_Pro_Help_Center::render_pro_subset(); // 10. Buying-Objection FAQ
		$this->render_final_cta();            // 11. Final CTA
		$this->render_disclaimer();
		echo '</div>';
		return ob_get_clean();
	}

	/** 1. Hero + Founding Offer. Copy is founder-locked — do not reword. */
	private function render_hero() {
		?>
		<section class="bm-pro-sales__hero">
			<p class="bm-pro-sales__hero-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP — BITMOMO PRO', 'bitmomo-pro' ); ?></p>
			<h1 class="bm-pro-sales__hero-title"><?php esc_html_e( 'Pahami BTC dalam konteks, bukan sekadar dari potongan data.', 'bitmomo-pro' ); ?></h1>
			<p class="bm-pro-sales__hero-sub"><?php esc_html_e( 'Bitmomo Pro sudah memiliki Decision View untuk membantu memahami kondisi BTC hari ini. 11 AI Analysts dan Watchtower akan segera hadir untuk memperluas analisis dan menjaga thesis tetap relevan ketika kondisi pasar berubah.', 'bitmomo-pro' ); ?></p>
			<div class="bm-pro-sales__hero-offer">
				<p class="bm-pro-sales__hero-price"><?php esc_html_e( 'Founding Price Rp149.000/bulan', 'bitmomo-pro' ); ?></p>
				<p class="bm-pro-sales__hero-price-sub"><?php esc_html_e( 'Pertahankan harga ini selama membership tetap aktif, meskipun capability Bitmomo Pro terus bertambah.', 'bitmomo-pro' ); ?></p>
			</div>
			<div class="bm-pro-sales__hero-facts">
				<div><strong><?php esc_html_e( 'Rp149.000', 'bitmomo-pro' ); ?></strong><span><?php esc_html_e( 'per bulan', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php esc_html_e( 'Rp1.490.000', 'bitmomo-pro' ); ?></strong><span><?php esc_html_e( 'per tahun', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::SEAT_CAP ); ?></strong><span><?php esc_html_e( 'Founding Members', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::BATCH_ONE ); ?></strong><span><?php esc_html_e( 'Batch pertama: 25 anggota', 'bitmomo-pro' ); ?></span></div>
			</div>
			<?php $this->render_cta_link( 'bm-pro-sales__hero-cta' ); ?>
		</section>
		<?php
	}

	/**
	 * Shared jump/purchase link used by the hero and Final CTA sections.
	 * Never a second form — always either a scroll-anchor to the one
	 * whitelist widget rendered in render_cta() (id="bm-pro-whitelist"),
	 * or, once bitmomo_pro_get_checkout_url() is configured, the same real
	 * purchase link render_cta() shows in the pricing section. Keeps the
	 * hero/Final CTA buttons truthful in both states without duplicating
	 * checkout logic.
	 */
	private function render_cta_link( $extra_class = '' ) {
		$url   = bitmomo_pro_get_checkout_url();
		$class = trim( 'bm-pro-sales__cta ' . $extra_class );
		if ( empty( $url ) ) {
			printf( '<a class="%1$s" href="#bm-pro-whitelist">%2$s</a>', esc_attr( $class ), esc_html__( 'GABUNG FOUNDING WHITELIST', 'bitmomo-pro' ) );
		} else {
			printf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html__( 'Kunci Harga Founding', 'bitmomo-pro' ) );
		}
	}

	/** 2. Data Is Abundant, Context Is Scarce. */
	private function render_context_problem() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__context">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Data ada di mana-mana. Konteks yang jarang.', 'bitmomo-pro' ); ?></h2>
			<p class="bm-pro-sales__lead"><?php esc_html_e( 'Pasar kripto tidak kekurangan data. Yang sulit adalah memahami apa arti data itu ketika semuanya berubah pada saat yang sama.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Ada tools on-chain seperti Nansen atau CryptoQuant yang menampilkan data mentah. Ada AI generik yang bisa menjawab pertanyaan apa pun tentang kripto. Keduanya berguna, tapi keduanya tidak dibangun untuk membaca BTC melalui konteks pengalaman pasar yang berkelanjutan — keduanya berhenti di data atau di jawaban generik, bukan di pemahaman.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Bitmomo tidak menggantikan data provider atau AI generik. Bitmomo mengisi bagian yang keduanya tidak dirancang untuk mengisi: mengubah data pasar menjadi konteks yang bisa langsung dipakai untuk mengambil keputusan.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__pullquote"><?php esc_html_e( 'Data bisa ditemukan di banyak tempat. Konteks dibangun dari pengalaman.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/**
	 * 3. Built From Market Experience / Perseverance Capital.
	 * VERIFY BEFORE SHIP: founder-supplied claims. No dates/amounts/returns
	 * beyond what is written here have been confirmed — do not embellish.
	 */
	private function render_market_experience() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__lineage">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'DIBANGUN DARI PENGALAMAN PASAR', 'bitmomo-pro' ); ?></p>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'AI adalah bagian dari sistem. Konteks adalah fondasinya.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Bitmomo merupakan pengembangan dari research arm Perseverance Capital, yang aktif di pasar kripto sejak 2016 — termasuk pengalaman berinvestasi Bitcoin secara konsisten dan berpartisipasi sejak awal di ETHLend maupun BNB.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Pengalaman itu terbentuk dari menghadapi banyak siklus: pergantian regime pasar, leverage unwind, hingga rotasi narasi antar sektor kripto. Konteks itulah yang menjadi fondasi kerangka analisis Bitmomo, bukan hanya data hari ini.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__lineage-note"><?php esc_html_e( 'Bitmomo bukan dibangun dari prompt semata, tapi dari pengalaman menghadapi siklus pasar yang berulang.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/**
	 * 4. How Bitmomo Turns Data Into Intelligence — six-stage narrative
	 * flow. Horizontal on desktop, stacked vertically on mobile (CSS).
	 * These are simplified explainer labels for visitors, not a literal
	 * map of backend services/classes.
	 */
	private function render_intelligence_flow() {
		$stages = array(
			array(
				'label' => __( 'DATA', 'bitmomo-pro' ),
				'desc'  => __( 'Harga, order book, funding, positioning, dan sinyal on-chain BTC dikumpulkan secara berkelanjutan.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'CONTEXT', 'bitmomo-pro' ),
				'desc'  => __( 'Data itu dibaca melalui pengalaman pasar — regime seperti apa ini, dan seberapa mirip dengan siklus sebelumnya.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'INTELLIGENCE', 'bitmomo-pro' ),
				'desc'  => __( 'Konteks itu diproses menjadi kondisi pasar yang jelas: Market State, Bias, dan Confidence.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'THESIS', 'bitmomo-pro' ),
				'desc'  => __( 'Kondisi tersebut disusun menjadi thesis: Expected Range, skenario Base/Bull/Bear, dan kondisi yang dapat membatalkannya.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'MONITORING', 'bitmomo-pro' ),
				'desc'  => __( 'Thesis dipantau untuk mendeteksi apakah kondisi pasar benar-benar berubah, bukan sekadar bergerak.', 'bitmomo-pro' ),
			),
			array(
				'label' => __( 'ACCOUNTABILITY', 'bitmomo-pro' ),
				'desc'  => __( 'Setiap thesis dicatat sebelum hasil diketahui, lalu dievaluasi secara terbuka setelahnya.', 'bitmomo-pro' ),
			),
		);
		?>
		<section class="bm-pro-sales__section bm-pro-sales__flow">
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

	/** 5. 11 AI Analysts — SEGERA HADIR. */
	private function render_ai_analysts() {
		$areas = array( __( 'Trend', 'bitmomo-pro' ), __( 'Structure', 'bitmomo-pro' ), __( 'Derivatives', 'bitmomo-pro' ), __( 'Volatility', 'bitmomo-pro' ), __( 'Positioning', 'bitmomo-pro' ), __( 'Market Context', 'bitmomo-pro' ), __( '+ lainnya', 'bitmomo-pro' ) );
		?>
		<section class="bm-pro-sales__section bm-pro-sales__analysts">
			<div class="bm-pro-sales__status-row">
				<span class="bm-pro-sales__status-badge"><?php esc_html_e( '11 AI ANALYSTS — SEGERA HADIR', 'bitmomo-pro' ); ?></span>
			</div>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( '11 perspektif spesialis, satu kerangka intelligence yang sama.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( '11 AI Analysts bukan 11 model AI yang berdiri sendiri-sendiri. Setiap analyst bekerja dalam kerangka intelligence Bitmomo yang sama, membaca BTC dari sudut pandang spesialisasinya masing-masing sebelum hasilnya disatukan menjadi satu Decision View.', 'bitmomo-pro' ); ?></p>
			<ul class="bm-pro-sales__pill-list">
				<?php foreach ( $areas as $area ) : ?><li><?php echo esc_html( $area ); ?></li><?php endforeach; ?>
			</ul>
			<p class="bm-pro-sales__signature"><?php esc_html_e( '11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__status-note"><?php esc_html_e( 'Sedang dalam pengembangan aktif dan akan menjadi bagian dari Bitmomo Pro berikutnya.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/** 6. Watchtower — SEGERA HADIR. */
	private function render_watchtower() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__watchtower">
			<div class="bm-pro-sales__status-row">
				<span class="bm-pro-sales__status-badge"><?php esc_html_e( 'WATCHTOWER — SEGERA HADIR', 'bitmomo-pro' ); ?></span>
			</div>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Bukan soal apa yang bergerak. Soal apakah sesuatu benar-benar berubah.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'Watchtower adalah monitoring layer yang sedang dikembangkan untuk mendeteksi perubahan penting di antara scheduled intelligence updates, lalu menilai apakah perubahan itu cukup berarti untuk memengaruhi thesis yang sedang berjalan.', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Watchtower dirancang sebagai high-signal monitoring system, bukan news feed. Tujuannya menyaring perubahan yang benar-benar relevan, bukan mengirim setiap pergerakan harga atau berita.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__status-note"><?php esc_html_e( 'Sedang dalam pengembangan aktif dan akan menjadi bagian dari Bitmomo Pro berikutnya.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/**
	 * 7. What Exists Today. Real, currently-live capability only — no
	 * preview, no mock example, no skeleton (founder decision, 2026-09).
	 * Definitions mirror Bitmomo_Pro_Help_Center's canonical wording.
	 */
	private function render_what_exists_today() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__today">
			<p class="bm-pro-sales__eyebrow"><?php esc_html_e( 'YANG SUDAH TERSEDIA SEKARANG', 'bitmomo-pro' ); ?></p>
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Decision View BTC, aktif setiap hari.', 'bitmomo-pro' ); ?></h2>
			<p><?php esc_html_e( 'BTC Daily Intelligence gratis menjawab apa yang sedang terjadi sekarang. Bitmomo Pro melengkapinya dengan Decision View harian yang sudah aktif hari ini:', 'bitmomo-pro' ); ?></p>
			<ul class="bm-pro-sales__list">
				<li><strong><?php esc_html_e( 'Expected Range', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'rentang harga yang dinilai masih masuk akal berdasarkan kondisi dan data saat intelligence dibuat.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Scenario Map', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'Base, Bull, dan Bear — kemungkinan jalur pasar beserta kondisi pendukungnya.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Thesis Invalidation', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'kondisi yang membuat thesis utama tidak lagi layak dipertahankan.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'What Changed', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'perubahan penting dibanding Decision View sebelumnya, tanpa perlu membandingkan chart sendiri.', 'bitmomo-pro' ); ?></li>
				<li><strong><?php esc_html_e( 'Confidence Explanation', 'bitmomo-pro' ); ?></strong> — <?php esc_html_e( 'seberapa kuat bukti yang mendukung kesimpulan tersebut saat dibuat.', 'bitmomo-pro' ); ?></li>
			</ul>
		</section>
		<?php
	}

	/** 8a. Founding Membership Economics. */
	private function render_founding_economics() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__economics">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Bergabung sebelum Bitmomo Pro mencapai bentuk penuhnya.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__progression">
				<div class="bm-pro-sales__progression-step">
					<span class="bm-pro-sales__progression-label"><?php esc_html_e( 'HARI INI', 'bitmomo-pro' ); ?></span>
					<p><?php esc_html_e( 'Decision View BTC harian.', 'bitmomo-pro' ); ?></p>
				</div>
				<div class="bm-pro-sales__progression-step">
					<span class="bm-pro-sales__progression-label"><?php esc_html_e( 'SEGERA HADIR', 'bitmomo-pro' ); ?></span>
					<p><?php esc_html_e( '11 AI Analysts dan Watchtower.', 'bitmomo-pro' ); ?></p>
				</div>
				<div class="bm-pro-sales__progression-step">
					<span class="bm-pro-sales__progression-label"><?php esc_html_e( 'KE DEPAN', 'bitmomo-pro' ); ?></span>
					<p><?php esc_html_e( 'Capability baru yang terus ditambahkan ke Bitmomo Pro.', 'bitmomo-pro' ); ?></p>
				</div>
			</div>
			<p class="bm-pro-sales__economics-anchor"><?php esc_html_e( 'FOUNDING PRICE Rp149.000/bulan', 'bitmomo-pro' ); ?></p>
			<p><?php esc_html_e( 'Rp149.000/bulan adalah Founding Price. Harga membership baru akan berubah seiring pengembangan fitur dan teknologi Bitmomo Pro. Founding Members yang menjaga membership tetap aktif dapat mempertahankan Founding Price selamanya.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__economics-boundary"><?php esc_html_e( 'Founding benefit berlaku untuk fitur baru yang ditambahkan ke Bitmomo Pro. Produk standalone Bitmomo di masa depan dapat memiliki pricing tersendiri.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/** 8b. Pricing block, immediately above the whitelist form. */
	private function render_price() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__price">
			<p class="bm-pro-sales__price-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__price-amount"><?php echo esc_html( self::PRICE_LABEL ); ?></p>
			<p class="bm-pro-sales__price-sub"><?php echo esc_html( sprintf( __( '%d Founding Members — Batch pertama: 25 anggota. Paket bulanan dan tahunan mendapat fitur yang sama.', 'bitmomo-pro' ), self::SEAT_CAP ) ); ?></p>
			<p class="bm-pro-sales__price-stage"><?php esc_html_e( 'Tahap saat ini: Founding Whitelist', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__price-terms"><?php esc_html_e( 'Batalkan kapan saja. Akses tetap aktif sampai akhir periode berlangganan.', 'bitmomo-pro' ); ?></p>
			<p class="bm-pro-sales__price-terms"><?php esc_html_e( 'Pembayaran bersifat final setelah aktivasi, kecuali untuk pembayaran ganda, kesalahan transaksi, atau kondisi lain yang diwajibkan oleh hukum.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}

	/**
	 * 8c. Whitelist. Reuses bitmomo_pro_get_checkout_url() (PR #64's
	 * fail-closed checkout URL) as the single source of truth for which
	 * CTA to show — never duplicated here. Checkout configured -> real
	 * purchase CTA, whitelist never shown as the primary action. Checkout
	 * not configured -> the Founding Membership Whitelist widget takes
	 * over this same slot (its markup carries id="bm-pro-whitelist", which
	 * the hero and Final CTA anchor buttons scroll to). No whitelist logic
	 * is touched here — only where it renders on the page.
	 */
	private function render_cta() {
		$url = bitmomo_pro_get_checkout_url();
		echo '<section class="bm-pro-sales__cta-section">';
		if ( empty( $url ) ) {
			if ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) {
				Bitmomo_Pro_Whitelist::instance()->render_widget( array( 'source' => 'pro_page' ) );
			} else {
				echo '<span class="bm-pro-sales__cta bm-pro-sales__cta--pending" id="bm-pro-whitelist">' . esc_html__( 'Pendaftaran Founding Membership segera dibuka.', 'bitmomo-pro' ) . '</span>';
			}
		} else {
			printf(
				'<a class="bm-pro-sales__cta" href="%1$s">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Kunci Harga Founding', 'bitmomo-pro' )
			);
		}
		echo '</section>';
	}

	/** 9. Accountability — short; full methodology lives at /btc-intelligence/. */
	private function render_accountability() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__accountability">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Setiap analisis harus bisa dipertanggungjawabkan.', 'bitmomo-pro' ); ?></h2>
			<ul class="bm-pro-sales__list">
				<li><?php esc_html_e( 'Setiap thesis dicatat sebelum hasilnya diketahui.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Hasilnya dievaluasi secara terbuka setelah fakta terjadi.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Hasil yang kurang baik tetap tercatat dalam riwayat, bukan dihapus.', 'bitmomo-pro' ); ?></li>
				<li><?php esc_html_e( 'Sampel yang masih kecil ditampilkan apa adanya, bukan dibesar-besarkan.', 'bitmomo-pro' ); ?></li>
			</ul>
			<a class="bm-pro-sales__accountability-link" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>"><?php esc_html_e( 'Lihat metodologi & track record lengkap →', 'bitmomo-pro' ); ?></a>
		</section>
		<?php
	}

	/** 11. Final CTA. Scrolls to the same whitelist form rendered in Section 8 — no duplicate form, no secondary CTA. */
	private function render_final_cta() {
		?>
		<section class="bm-pro-sales__section bm-pro-sales__final-cta">
			<h2 class="bm-pro-sales__section-title"><?php esc_html_e( 'Bergabung sebelum 11 AI Analysts dan Watchtower dirilis.', 'bitmomo-pro' ); ?></h2>
			<div class="bm-pro-sales__hero-facts">
				<div><strong><?php esc_html_e( 'Rp149.000', 'bitmomo-pro' ); ?></strong><span><?php esc_html_e( 'per bulan', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::SEAT_CAP ); ?></strong><span><?php esc_html_e( 'Founding Members', 'bitmomo-pro' ); ?></span></div>
				<div><strong><?php echo esc_html( self::BATCH_ONE ); ?></strong><span><?php esc_html_e( 'Batch pertama: 25 anggota', 'bitmomo-pro' ); ?></span></div>
			</div>
			<?php $this->render_cta_link(); ?>
		</section>
		<?php
	}

	private function render_disclaimer() {
		?>
		<section class="bm-pro-sales__disclaimer">
			<p><?php esc_html_e( 'Bitmomo Pro adalah alat bantu analisis, bukan nasihat keuangan. Pergerakan harga BTC memiliki risiko; keputusan trading sepenuhnya tanggung jawab pengguna.', 'bitmomo-pro' ); ?></p>
		</section>
		<?php
	}
}

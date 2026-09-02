<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public [bitmomo_btc_intelligence] page — /btc-intelligence/'s single
 * canonical source of truth (see Bitmomo_Btc_Intelligence_Setup for how the
 * route itself is provisioned).
 *
 * DATA SOURCES THIS CLASS IS ALLOWED TO READ (all already public today):
 * - Bitmomo_AI_Intelligence::free_projection() (bitmomo-ai) -- the exact
 *   same free snapshot already shown on the homepage card. Reused as-is,
 *   never recalculated.
 * - Bitmomo_Regime_State_Store::instance()->get_latest() and
 *   Bitmomo_Regime_Taxonomy::regime_label_id() (bitmomo-regime) -- same
 *   pattern the homepage card already uses for Market State.
 * - The bitmomo-regime plugin's own [bitmomo_market_regime_history]
 *   shortcode, invoked via do_shortcode() -- a self-contained, already
 *   public-safe render with its own honest "still accumulating" /
 *   "no history yet" states. This class never reads
 *   Bitmomo_Regime_State_Store's raw records directly for history; it
 *   defers entirely to that shortcode so regime history logic is never
 *   duplicated here.
 *
 * DATA SOURCES THIS CLASS DELIBERATELY NEVER READS:
 * - Bitmomo_AI_Scorecard (the private P1 evaluation engine) or its
 *   repository -- admin-only today, and copying its output or its
 *   render_admin() logic into a public page was explicitly out of scope
 *   for this task.
 * - Any Bitmomo_Pro_* class -- current/live Expected Range, Scenario Map,
 *   Thesis Invalidation, What Changed, or any other protected Pro value.
 *
 * PUBLIC ADAPTER DEPENDENCY: sections 7-10 (Track Record, Confidence vs
 * Accuracy, Expected Range historical hit-rate, Performance by Regime) and
 * part of section 11 (deeper data-quality metrics) need a public,
 * read-only summary of the P1 Scorecard that does not exist yet. This
 * class is written against the expected future shape
 * `Bitmomo_Public_Intelligence_Adapter::evaluation_summary()` (see
 * adapter_evaluation_summary() below) so that once that class lands, only
 * adapter_evaluation_summary()'s class_exists()/method_exists() guard
 * needs to start returning real data -- no other change to this file
 * should be required. Until then, every one of those sections renders an
 * honest "belum tersedia" boundary state: headings, framing copy, and
 * structure are all real; no number is ever fabricated.
 */
class Bitmomo_Btc_Intelligence_Page {

	private static $instance = null;

	/** Cached result of adapter_evaluation_summary() for one render pass. */
	private $evaluation_summary = null;
	private $evaluation_summary_loaded = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bitmomo_btc_intelligence', array( $this, 'render_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	public function maybe_enqueue_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_btc_intelligence' ) ) {
			return;
		}
		wp_enqueue_style(
			'bitmomo-btc-intelligence',
			BITMOMO_BTC_INTELLIGENCE_URL . 'assets/css/bitmomo-btc-intelligence.css',
			array(),
			BITMOMO_BTC_INTELLIGENCE_VERSION
		);
	}

	/**
	 * Returns Bitmomo_Public_Intelligence_Adapter::evaluation_summary()'s
	 * output if that class exists and defines the method, else null.
	 * Cached per request/render so every consuming section shares one call.
	 *
	 * Expected future shape (documented here for whoever builds the
	 * adapter, since no such class exists in this codebase yet): an array
	 * keyed by section, each entry carrying at minimum a `sample_status`
	 * field already computed backend-side as one of
	 * 'insufficient_sample' | 'early_sample' | 'adequate_sample' (per the
	 * canonical n<10 / 10-29 / >=30 thresholds -- this class never
	 * reimplements that threshold itself), an `n` observation count, and
	 * the section's own figure(s). This class does not assume more detail
	 * than that until the real adapter exists.
	 */
	private function adapter_evaluation_summary() {
		if ( $this->evaluation_summary_loaded ) {
			return $this->evaluation_summary;
		}
		$this->evaluation_summary_loaded = true;

		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
			&& method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'evaluation_summary' ) ) {
			$this->evaluation_summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
		}

		return $this->evaluation_summary;
	}

	private function adapter_available() {
		return is_array( $this->adapter_evaluation_summary() );
	}

	public function render_page( $atts ) {
		ob_start();
		echo '<div class="bm-bi">';
		$this->render_hero();
		$this->render_current_snapshot();
		$this->render_how_it_works();
		$this->render_five_axes();
		$this->render_how_to_read();
		$this->render_historical_regime();
		$this->render_track_record();
		$this->render_confidence_evaluation();
		$this->render_expected_range_performance();
		$this->render_regime_performance();
		$this->render_data_quality();
		$this->render_methodology();
		$this->render_pro_cta();
		echo '</div>';
		return ob_get_clean();
	}

	/* =====================================================================
	 * 1. HERO
	 * ================================================================== */
	private function render_hero() {
		?>
		<section class="bm-bi__hero">
			<p class="bm-bi__eyebrow">BTC INTELLIGENCE</p>
			<h1 class="bm-bi__hero-title"><?php esc_html_e( 'Pahami BTC dalam konteks.', 'bitmomo-btc-intelligence' ); ?></h1>
			<p class="bm-bi__hero-subhead"><?php esc_html_e( 'Lima axis. Satu framework. Track record terbuka.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__hero-micro"><?php esc_html_e( 'Bukan sinyal beli/jual. Bukan saran keuangan.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 2. CURRENT BTC INTELLIGENCE -- wired, same public source as homepage
	 * ================================================================== */
	private function render_current_snapshot() {
		$snapshot = class_exists( 'Bitmomo_AI_Intelligence' )
			? Bitmomo_AI_Intelligence::free_projection()
			: array(
				'status'  => 'unavailable',
				'message' => __( 'Update BTC terbaru belum tersedia.', 'bitmomo-btc-intelligence' ),
				'detail'  => __( 'Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ),
			);

		$status    = sanitize_key( (string) ( $snapshot['status'] ?? 'unavailable' ) );
		$available = in_array( $status, array( 'fresh', 'delayed' ), true );

		$regime = array();
		if ( class_exists( 'Bitmomo_Regime_State_Store' ) ) {
			$regime = Bitmomo_Regime_State_Store::instance()->get_latest();
		}
		$regime_label = ( class_exists( 'Bitmomo_Regime_Taxonomy' ) && ! empty( $regime['regime'] ) )
			? Bitmomo_Regime_Taxonomy::regime_label_id( $regime['regime'] )
			: __( 'Belum tersedia', 'bitmomo-btc-intelligence' );

		// Bias-absent rule: only render a Bias badge when a valid canonical
		// value exists. Never defaulted to Neutral -- Neutral is a real
		// value in its own right and must stay distinct from "no Bias
		// recorded."
		$raw_bias    = sanitize_key( (string) ( $snapshot['bias'] ?? '' ) );
		$bias_valid  = in_array( $raw_bias, array( 'bullish', 'bearish', 'neutral' ), true );
		$bias_labels = array(
			'bullish' => __( 'Bullish', 'bitmomo-btc-intelligence' ),
			'bearish' => __( 'Bearish', 'bitmomo-btc-intelligence' ),
			'neutral' => __( 'Neutral', 'bitmomo-btc-intelligence' ),
		);

		$confidence       = max( 0, min( 100, (int) ( $snapshot['confidence'] ?? 0 ) ) );
		$confidence_label = $confidence >= 70 ? __( 'Tinggi', 'bitmomo-btc-intelligence' ) : ( $confidence >= 40 ? __( 'Sedang', 'bitmomo-btc-intelligence' ) : __( 'Rendah', 'bitmomo-btc-intelligence' ) );

		$key_drivers = array_values( array_filter(
			array_map( 'strval', is_array( $snapshot['key_drivers'] ?? null ) ? $snapshot['key_drivers'] : array() ),
			static function ( $driver ) {
				return '' !== trim( $driver );
			}
		) );
		$key_drivers = array_slice( $key_drivers, 0, 5 );

		$updated = ! empty( $snapshot['timestamp_iso'] ) ? strtotime( (string) $snapshot['timestamp_iso'] ) : false;
		?>
		<section class="bm-bi__section bm-bi__section--peak bm-bi__snapshot">
			<p class="bm-bi__eyebrow">KONDISI BTC SAAT INI</p>
			<?php if ( ! $available ) : ?>
				<div class="bm-bi__snapshot-unavailable" role="status">
					<span class="bm-bi__badge bm-bi__badge--muted"><?php esc_html_e( 'Belum tersedia', 'bitmomo-btc-intelligence' ); ?></span>
					<h2><?php echo esc_html( (string) ( $snapshot['message'] ?? '' ) ); ?></h2>
					<p><?php echo esc_html( (string) ( $snapshot['detail'] ?? '' ) ); ?></p>
				</div>
			<?php else : ?>
				<div class="bm-bi__snapshot-grid">
					<div class="bm-bi__metric">
						<span class="bm-bi__kicker"><?php esc_html_e( 'Market State', 'bitmomo-btc-intelligence' ); ?></span>
						<strong><?php echo esc_html( $regime_label ); ?></strong>
					</div>
					<div class="bm-bi__metric">
						<span class="bm-bi__kicker"><?php esc_html_e( 'Directional Bias', 'bitmomo-btc-intelligence' ); ?></span>
						<?php if ( $bias_valid ) : ?>
							<strong class="bm-bi__bias is-<?php echo esc_attr( $raw_bias ); ?>"><?php echo esc_html( $bias_labels[ $raw_bias ] ); ?></strong>
						<?php else : ?>
							<strong class="bm-bi__bias is-unknown"><?php esc_html_e( 'Belum Tersedia', 'bitmomo-btc-intelligence' ); ?></strong>
						<?php endif; ?>
					</div>
					<div class="bm-bi__metric">
						<span class="bm-bi__kicker"><?php esc_html_e( 'Confidence', 'bitmomo-btc-intelligence' ); ?></span>
						<strong><?php echo esc_html( $confidence_label ); ?></strong>
					</div>
					<div class="bm-bi__metric">
						<span class="bm-bi__kicker"><?php esc_html_e( 'BTC Reference', 'bitmomo-btc-intelligence' ); ?></span>
						<strong>$<?php echo esc_html( number_format_i18n( (float) ( $snapshot['price'] ?? 0 ), 0 ) ); ?></strong>
					</div>
				</div>

				<div class="bm-bi__driver">
					<span class="bm-bi__kicker"><?php esc_html_e( 'Faktor Utama', 'bitmomo-btc-intelligence' ); ?></span>
					<?php if ( $key_drivers ) : ?>
						<ul class="bm-bi__driver-list">
							<?php foreach ( $key_drivers as $driver ) : ?>
								<li><?php echo esc_html( $driver ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="bm-bi__driver-empty"><?php esc_html_e( 'Faktor utama belum tersedia untuk snapshot ini.', 'bitmomo-btc-intelligence' ); ?></p>
					<?php endif; ?>
				</div>

				<p class="bm-bi__freshness">
					<?php
					if ( $updated ) {
						printf(
							/* translators: %s: human-readable local date/time of the snapshot */
							esc_html__( 'Data terkini — diperbarui %s.', 'bitmomo-btc-intelligence' ),
							esc_html( date_i18n( 'd M Y, H:i', $updated ) )
						);
					} else {
						esc_html_e( 'Waktu pembaruan belum tersedia.', 'bitmomo-btc-intelligence' );
					}
					?>
				</p>
			<?php endif; ?>
			<p class="bm-bi__snapshot-note"><?php esc_html_e( 'Market State dan Directional Bias adalah dua hal berbeda — lihat "Cara Membaca Bitmomo Intelligence" di bawah.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 3. HOW BITMOMO READS THE MARKET -- static
	 * ================================================================== */
	private function render_how_it_works() {
		?>
		<section class="bm-bi__section--editorial bm-bi__how">
			<h2 class="bm-bi__section-title">BAGAIMANA BITMOMO MEMBACA PASAR</h2>
			<p><?php esc_html_e( 'Bitmomo Intelligence tidak dimulai dari satu angka atau satu indikator. Setiap analisis BTC dibangun dari lima axis intelligence yang deterministik — masing-masing menjawab pertanyaan berbeda tentang kondisi pasar saat ini. Kelima axis ini digabungkan menjadi satu Market State, satu Directional Bias, dan satu level Confidence — bukan lima opini terpisah yang harus kamu tafsirkan sendiri.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__disclaimer-line"><?php esc_html_e( 'Ini bukan lima "agent" AI yang independen. Ini lima dimensi intelligence yang deterministik — proses yang sama, dijalankan secara konsisten setiap kali analisis dilakukan.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 4. FIVE INTELLIGENCE AXES -- static
	 * ================================================================== */
	private function render_five_axes() {
		$axes = array(
			array(
				'label'   => 'Direction',
				'q'       => 'Ke arah mana tekanan harga BTC saat ini lebih mungkin condong?',
				'body'    => 'Menangkap momentum dan arah pergerakan harga jangka pendek — axis paling langsung terkait dengan Directional Bias.',
			),
			array(
				'label'   => 'Volatility',
				'q'       => 'Seberapa besar dan cepat harga BTC bergerak saat ini dibanding biasanya?',
				'body'    => 'Menangkap intensitas pergerakan pasar. Volatilitas tinggi dapat mengubah seberapa lebar rentang harga yang realistis, dan seberapa cepat kondisi bisa berubah.',
			),
			array(
				'label'   => 'Carry',
				'q'       => 'Berapa besar biaya atau insentif untuk menahan posisi leverage saat ini (funding, cost-of-carry)?',
				'body'    => 'Menangkap tekanan struktural dari pasar derivatif/leverage. Carry yang ekstrem bisa jadi sinyal struktural meningkatnya risiko pembalikan atau unwind posisi.',
			),
			array(
				'label'   => 'Structure',
				'q'       => 'Bagaimana bentuk struktur harga BTC saat ini — level mana yang berfungsi sebagai support/resistance?',
				'body'    => 'Menangkap konteks teknikal/struktural di balik pergerakan harga — membantu membedakan level yang berarti dari noise jangka pendek.',
			),
			array(
				'label'   => 'Crowding',
				'q'       => 'Seberapa banyak pelaku pasar sudah berada di sisi yang sama saat ini?',
				'body'    => 'Menangkap konsentrasi positioning. Crowding ekstrem dapat meningkatkan risiko pergerakan tajam ke arah berlawanan jika posisi mulai dipaksa keluar.',
			),
		);
		?>
		<section class="bm-bi__section--editorial bm-bi__axes">
			<h2 class="bm-bi__section-title">LIMA INTELLIGENCE AXES</h2>
			<div class="bm-bi__axes-grid">
				<?php foreach ( $axes as $axis ) : ?>
					<div class="bm-bi__axis-card">
						<h3><?php echo esc_html( $axis['label'] ); ?></h3>
						<p class="bm-bi__axis-q"><?php echo esc_html( $axis['q'] ); ?></p>
						<p><?php echo esc_html( $axis['body'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="bm-bi__signature"><?php esc_html_e( 'Prosesnya sama setiap kali: konsisten, deterministik, tidak berubah-ubah tergantung mood pasar.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 5. HOW TO READ BITMOMO INTELLIGENCE -- static, locked vocabulary
	 * ================================================================== */
	private function render_how_to_read() {
		?>
		<section class="bm-bi__section--editorial bm-bi__section--quiet bm-bi__how-to-read">
			<h2 class="bm-bi__section-title">CARA MEMBACA BITMOMO INTELLIGENCE</h2>

			<h3><?php esc_html_e( 'Market State bukan Directional Bias', 'bitmomo-btc-intelligence' ); ?></h3>
			<p><?php esc_html_e( 'Market State menjelaskan struktur kondisi pasar saat ini — apakah sedang Akumulasi, Ekspansi, Distribusi, Kapitulasi, atau Transisi. Directional Bias menjelaskan arah yang lebih mungkin — Bullish, Neutral, atau Bearish. Dua hal ini bisa berbeda: pasar bisa berada di regime Transisi dengan Bias Bullish, misalnya. Bitmomo tidak pernah menggabungkan keduanya jadi satu label, dan tidak pernah menyimpulkan salah satu dari yang lain.', 'bitmomo-btc-intelligence' ); ?></p>

			<h3><?php esc_html_e( 'Confidence bukan probabilitas', 'bitmomo-btc-intelligence' ); ?></h3>
			<p><?php esc_html_e( 'Confidence ditampilkan sebagai Rendah, Sedang, atau Tinggi — ini adalah kekuatan bukti di balik analisis saat ini, bukan probabilitas statistik. Confidence "Tinggi" tidak berarti "70% kemungkinan benar." Ini berarti kelima axis intelligence saat ini secara konsisten mengarah ke kesimpulan yang sama.', 'bitmomo-btc-intelligence' ); ?></p>

			<h3><?php esc_html_e( 'Apa yang bukan Bitmomo Intelligence', 'bitmomo-btc-intelligence' ); ?></h3>
			<p><?php esc_html_e( 'Bukan sinyal beli/jual. Bukan target harga. Bukan jaminan hasil. Ini adalah hasil analisis kondisi pasar berbasis proses yang konsisten — dimaksudkan untuk membantu kamu memahami konteks, bukan menggantikan keputusan kamu.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 6. HISTORICAL MARKET STATE / BIAS -- wired via the regime plugin's
	 * own [bitmomo_market_regime_history] shortcode. This class never
	 * reads Bitmomo_Regime_State_Store's raw records itself for history.
	 * ================================================================== */
	private function render_historical_regime() {
		?>
		<section class="bm-bi__section--editorial bm-bi__history">
			<h2 class="bm-bi__section-title">RIWAYAT MARKET STATE &amp; BIAS</h2>
			<p><?php esc_html_e( 'Setiap analisis yang memenuhi kriteria pencatatan disimpan bersama timestamp dan versinya — bukan ditulis ulang setelah kejadian. Riwayat di bawah ini menunjukkan Market State dan Directional Bias yang sudah tercatat.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__history-embed">
				<?php
				if ( shortcode_exists( 'bitmomo_market_regime_history' ) ) {
					echo do_shortcode( '[bitmomo_market_regime_history days="30"]' );
				} else {
					echo '<div class="bm-bi__blocked"><p>' . esc_html__( 'Riwayat Market State belum tersedia.', 'bitmomo-btc-intelligence' ) . '</p></div>';
				}
				?>
			</div>
			<p class="bm-bi__history-note"><?php esc_html_e( 'Riwayat ini terus bertambah seiring waktu. Semakin panjang riwayatnya, semakin bisa dipercaya evaluasi performa di bawah.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Shared boundary renderer for every section that depends on the
	 * not-yet-built public evaluation adapter. Renders the real section
	 * shell (heading + framing copy, both approved/locked) plus an honest
	 * "belum tersedia" note -- never a fabricated number, never the
	 * INSUFFICIENT SAMPLE label (that's a specific backend-owned status
	 * for a *known* small n; here the adapter itself doesn't exist yet,
	 * which is a different, more upstream kind of "not available").
	 */
	private function render_blocked_boundary( $note = '' ) {
		if ( '' === $note ) {
			$note = __( 'Data evaluasi publik untuk bagian ini belum tersedia. Bagian ini akan otomatis terisi begitu adapter data publik (Bitmomo_Public_Intelligence_Adapter) tersedia dari tim engineering — tidak ada angka yang direkayasa di sini.', 'bitmomo-btc-intelligence' );
		}
		echo '<div class="bm-bi__blocked" role="status">';
		echo '<span class="bm-bi__badge bm-bi__badge--muted">' . esc_html__( 'Belum Tersedia', 'bitmomo-btc-intelligence' ) . '</span>';
		echo '<p>' . esc_html( $note ) . '</p>';
		echo '</div>';
	}

	/* =====================================================================
	 * 7. PERFORMANCE / TRACK RECORD -- blocked, see class docblock
	 * ================================================================== */
	private function render_track_record() {
		?>
		<section class="bm-bi__section--editorial bm-bi__track-record">
			<h2 class="bm-bi__section-title">TRACK RECORD</h2>
			<p><?php esc_html_e( 'Bitmomo tidak hanya menghasilkan analisis. Setiap hasil dicatat sebelum outcome diketahui, lalu dievaluasi terhadap apa yang benar-benar terjadi setelahnya. Bagian ini menunjukkan hasil evaluasi tersebut apa adanya, termasuk ketika sampel datanya masih terlalu kecil untuk disimpulkan.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( $this->adapter_available() ) : ?>
				<?php // Forward-compatible: real rendering will read $this->adapter_evaluation_summary()['directional_accuracy'] and its by-bias/by-session/by-regime breakdowns once the adapter exists. ?>
				<?php $this->render_blocked_boundary( __( 'Adapter publik terdeteksi tapi belum ada data ringkasan untuk Track Record.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<?php $this->render_blocked_boundary(); ?>
			<?php endif; ?>
			<ul class="bm-bi__breakdown-labels">
				<li><?php esc_html_e( 'Akurasi berdasarkan Bias (Bullish / Neutral / Bearish)', 'bitmomo-btc-intelligence' ); ?></li>
				<li><?php esc_html_e( 'Akurasi berdasarkan sesi (Morning vs US Session)', 'bitmomo-btc-intelligence' ); ?></li>
				<li><?php esc_html_e( 'Akurasi berdasarkan Market State / regime', 'bitmomo-btc-intelligence' ); ?></li>
			</ul>
		</section>
		<?php
	}

	/* =====================================================================
	 * 8. CONFIDENCE / OUTCOME EVALUATION -- locked copy, blocked data
	 * ================================================================== */
	private function render_confidence_evaluation() {
		?>
		<section class="bm-bi__section--editorial bm-bi__confidence-eval">
			<h2 class="bm-bi__section-title">HUBUNGAN CONFIDENCE DENGAN AKURASI</h2>
			<p class="bm-bi__confidence-headline"><?php esc_html_e( 'Apakah Confidence yang lebih tinggi benar-benar menghasilkan akurasi yang lebih konsisten?', 'bitmomo-btc-intelligence' ); ?></p>
			<p><?php esc_html_e( 'Kami membandingkan hasil aktual di setiap level Confidence untuk melihat apakah perbedaan tingkat keyakinan sistem benar-benar tercermin pada performanya.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__disclaimer-line"><?php esc_html_e( 'Confidence menunjukkan kekuatan evidence di balik analisis, bukan probabilitas keberhasilan.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__confidence-buckets">
				<?php foreach ( array( 'Rendah', 'Sedang', 'Tinggi' ) as $bucket ) : ?>
					<div class="bm-bi__confidence-bucket">
						<span class="bm-bi__kicker"><?php echo esc_html( $bucket ); ?></span>
						<?php $this->render_blocked_boundary( __( 'Belum tersedia.', 'bitmomo-btc-intelligence' ) ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/* =====================================================================
	 * 9. EXPECTED RANGE PERFORMANCE -- historical hit-rate only (public);
	 * current/live Expected Range stays Pro-only, never rendered here.
	 * ================================================================== */
	private function render_expected_range_performance() {
		?>
		<section class="bm-bi__section--editorial bm-bi__expected-range">
			<h2 class="bm-bi__section-title">PERFORMA EXPECTED RANGE</h2>
			<p><?php esc_html_e( 'Expected Range adalah proyeksi rentang harga BTC dari Bitmomo Pro. Bagian ini menunjukkan seberapa sering harga aktual berada di dalam rentang yang diproyeksikan — termasuk seberapa sering breach terjadi di sisi bawah atau atas.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php $this->render_blocked_boundary(); ?>
			<p class="bm-bi__editorial-note"><?php esc_html_e( 'Rentang Expected Range hari ini hanya tersedia untuk anggota Bitmomo Pro. Bagian ini hanya menunjukkan seberapa akurat rentang tersebut secara historis — bukan angka rentang yang sedang berlaku sekarang.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 10. PERFORMANCE BY MARKET REGIME -- blocked
	 * ================================================================== */
	private function render_regime_performance() {
		?>
		<section class="bm-bi__section--editorial bm-bi__regime-performance">
			<h2 class="bm-bi__section-title">PERFORMA BERDASARKAN REGIME</h2>
			<p><?php esc_html_e( 'Kondisi pasar tidak selalu sama — performa Bitmomo Intelligence pun bisa berbeda di tiap regime. Bagian ini memecah akurasi dan stabilitas regime per kategori Market State.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php $this->render_blocked_boundary(); ?>
		</section>
		<?php
	}

	/* =====================================================================
	 * 11. DATA QUALITY / FRESHNESS -- partially wired: freshness/timestamp
	 * reuses the section-2 snapshot; deeper quality metrics stay blocked.
	 * ================================================================== */
	private function render_data_quality() {
		$snapshot = class_exists( 'Bitmomo_AI_Intelligence' ) ? Bitmomo_AI_Intelligence::free_projection() : array();
		$updated  = ! empty( $snapshot['timestamp_iso'] ) ? strtotime( (string) $snapshot['timestamp_iso'] ) : false;
		?>
		<section class="bm-bi__section--editorial bm-bi__data-quality">
			<h2 class="bm-bi__section-title">KUALITAS &amp; KESEGARAN DATA</h2>
			<p><?php esc_html_e( 'Intelligence hanya sebaik data di baliknya. Bagian ini menunjukkan seberapa sering data diperbarui dan seberapa "segar" hasil analisis saat kamu melihatnya — bukan klaim real-time yang belum benar-benar live.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__quality-row">
				<div class="bm-bi__metric">
					<span class="bm-bi__kicker"><?php esc_html_e( 'Data terkini', 'bitmomo-btc-intelligence' ); ?></span>
					<strong>
						<?php
						if ( $updated ) {
							echo esc_html( date_i18n( 'd M Y, H:i', $updated ) );
						} else {
							esc_html_e( 'Belum tersedia', 'bitmomo-btc-intelligence' );
						}
						?>
					</strong>
				</div>
			</div>
			<?php $this->render_blocked_boundary( __( 'Metrik kualitas data yang lebih mendalam (konsistensi, cakupan sumber) belum tersedia secara publik.', 'bitmomo-btc-intelligence' ) ); ?>
		</section>
		<?php
	}

	/* =====================================================================
	 * 12. METHODOLOGY / ACCOUNTABILITY -- static
	 * ================================================================== */
	private function render_methodology() {
		?>
		<section class="bm-bi__section--editorial bm-bi__section--quiet bm-bi__methodology">
			<h2 class="bm-bi__section-title">METODOLOGI &amp; AKUNTABILITAS</h2>
			<p><?php esc_html_e( 'Lima axis intelligence yang deterministik. Tanpa spekulasi ke arah yang belum bisa dijelaskan. Setiap hasil dicatat sebelum outcome diketahui, lalu dievaluasi terhadap apa yang benar-benar terjadi — bukan dinilai ulang setelah fakta agar terlihat lebih baik.', 'bitmomo-btc-intelligence' ); ?></p>
			<p><?php esc_html_e( 'Halaman ini menunjukkan hasilnya apa adanya — termasuk saat meleset, dan termasuk ketika sampelnya masih terlalu kecil untuk disimpulkan.', 'bitmomo-btc-intelligence' ); ?></p>
		</section>
		<?php
	}

	/* =====================================================================
	 * 13. BITMOMO PRO CONVERSION -- locked CTA option 1 (recommended v1)
	 * ================================================================== */
	private function render_pro_cta() {
		?>
		<section class="bm-bi__section bm-bi__section--peak bm-bi__pro-cta">
			<p class="bm-bi__eyebrow">BITMOMO PRO</p>
			<h2 class="bm-bi__section-title bm-bi__section-title--climax"><?php esc_html_e( 'Ketahui apa yang perlu diperhatikan berikutnya.', 'bitmomo-btc-intelligence' ); ?></h2>
			<p><?php esc_html_e( 'Bitmomo Pro membantu kamu memahami skenario pasar yang paling relevan, kondisi yang dapat mengubah thesis, dan perubahan penting yang layak mendapat perhatian.', 'bitmomo-btc-intelligence' ); ?></p>
			<p class="bm-bi__signature"><?php esc_html_e( 'Lebih sedikit waktu memantau noise. Lebih banyak fokus pada perubahan yang benar-benar penting.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__pro-cta-actions">
				<a class="bm-bi__cta-primary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
				<a class="bm-bi__cta-secondary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat cara kerja Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
			</div>
		</section>
		<?php
	}
}

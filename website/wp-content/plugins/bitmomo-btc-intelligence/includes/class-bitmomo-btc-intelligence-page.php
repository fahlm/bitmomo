<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public BTC Intelligence page.
 *
 * Product rule: the main reading path must answer three questions quickly:
 * what is happening now, what has the system recorded, and how has it done.
 * Methodology and secondary evaluation stay available through progressive
 * disclosure instead of competing with the live intelligence hierarchy.
 */
class Bitmomo_Btc_Intelligence_Page {

	private static $instance = null;
	private $adapter_snapshot = null;
	private $adapter_snapshot_loaded = false;
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
		add_action( 'template_redirect', array( $this, 'prevent_snapshot_page_cache' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_meta_description' ) );
		add_action( 'wp_head', array( $this, 'render_meta_description' ) );
	}

	public function filter_meta_description( $description ) {
		return is_page( 'btc-intelligence' )
			? __( 'Kondisi BTC saat ini, riwayat 30 hari, dan track record Bitmomo Intelligence.', 'bitmomo-btc-intelligence' )
			: $description;
	}

	public function render_meta_description() {
		if ( is_page( 'btc-intelligence' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
			echo '<meta name="description" content="' . esc_attr( $this->filter_meta_description( '' ) ) . '" />' . "\n";
		}
	}

	public function prevent_snapshot_page_cache() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_btc_intelligence' ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'Canonical intelligence freshness' );
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

	private function adapter_snapshot() {
		if ( $this->adapter_snapshot_loaded ) return $this->adapter_snapshot;
		$this->adapter_snapshot_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'snapshot' ) ) {
			$this->adapter_snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		}
		return $this->adapter_snapshot;
	}

	private function adapter_evaluation_summary() {
		if ( $this->evaluation_summary_loaded ) return $this->evaluation_summary;
		$this->evaluation_summary_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'evaluation_summary' ) ) {
			$this->evaluation_summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
		}
		return $this->evaluation_summary;
	}

	private function regime_display_label( $value ) {
		$labels = array(
			'accumulation' => 'Akumulasi',
			'expansion' => 'Ekspansi',
			'distribution' => 'Distribusi',
			'capitulation' => 'Kapitulasi',
			'transition' => 'Transisi',
		);
		return is_string( $value ) && isset( $labels[ $value ] ) ? $labels[ $value ] : __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function public_sample_status_label( $sample_status ) {
		$labels = array(
			'ADEQUATE' => __( 'Sampel Memadai', 'bitmomo-btc-intelligence' ),
			'EARLY SAMPLE' => __( 'Sampel Awal', 'bitmomo-btc-intelligence' ),
			'INSUFFICIENT SAMPLE' => __( 'Sampel Belum Cukup', 'bitmomo-btc-intelligence' ),
		);
		$sample_status = (string) $sample_status;
		return $labels[ $sample_status ] ?? ( '' !== $sample_status ? $sample_status : __( 'Belum tersedia', 'bitmomo-btc-intelligence' ) );
	}

	private function render_freshness( $snapshot ) {
		$freshness = is_array( $snapshot['freshness'] ?? null ) ? $snapshot['freshness'] : array();
		$iso = (string) ( $freshness['timestamp_iso'] ?? '' );
		$timestamp = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $iso ) ? strtotime( $iso ) : false;
		if ( ! $timestamp ) {
			esc_html_e( 'Waktu belum tersedia', 'bitmomo-btc-intelligence' );
			return;
		}
		$exact = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB';
		echo '<time datetime="' . esc_attr( $iso ) . '">' . esc_html( $exact ) . '</time>';
	}

	private function render_public_metric( $label, $metric, $value_field = 'accuracy_pct', $suffix = '%' ) {
		$metric = is_array( $metric ) ? $metric : array();
		$value = array_key_exists( $value_field, $metric ) ? $metric[ $value_field ] : null;
		$n = isset( $metric['n'] ) ? (int) $metric['n'] : 0;
		$status = (string) ( $metric['sample_status'] ?? '' );
		?>
		<div class="bm-bi__proof-metric">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo null !== $value && is_numeric( $value ) ? esc_html( number_format_i18n( (float) $value, 1 ) . $suffix ) : esc_html__( '—', 'bitmomo-btc-intelligence' ); ?></strong>
			<small><?php echo esc_html( sprintf( 'n=%d · %s', $n, $this->public_sample_status_label( $status ) ) ); ?></small>
		</div>
		<?php
	}

	private function render_blocked_boundary( $note ) {
		echo '<div class="bm-bi__blocked" role="status"><strong>' . esc_html__( 'Belum tersedia', 'bitmomo-btc-intelligence' ) . '</strong><span>' . esc_html( $note ) . '</span></div>';
	}

	public function render_page( $atts ) {
		ob_start();
		echo '<div class="bm-bi">';
		$this->render_hero();
		$this->render_current_snapshot();
		$this->render_historical_regime();
		$this->render_track_record();
		$this->render_methodology();
		$this->render_pro_cta();
		echo '</div>';
		return ob_get_clean();
	}

	private function render_hero() {
		?>
		<section class="bm-bi__hero">
			<p class="bm-bi__eyebrow">BTC INTELLIGENCE</p>
			<h1>Pahami BTC dalam konteks.</h1>
			<p><?php esc_html_e( 'Kondisi sekarang, riwayat 30 hari, dan track record — dalam satu halaman.', 'bitmomo-btc-intelligence' ); ?></p>
			<small><?php esc_html_e( 'Bukan sinyal beli/jual. Bukan saran keuangan.', 'bitmomo-btc-intelligence' ); ?></small>
		</section>
		<?php
	}

	private function render_current_snapshot() {
		$snapshot = $this->adapter_snapshot();
		$available = is_array( $snapshot );
		$bias_labels = array( 'bullish' => 'Bullish', 'bearish' => 'Bearish', 'neutral' => 'Neutral' );
		$strength_labels = array(
			'strong_bearish' => 'Strong Bearish',
			'bearish' => 'Bearish',
			'neutral' => 'Neutral',
			'bullish' => 'Bullish',
			'strong_bullish' => 'Strong Bullish',
		);
		$strength_zone_index = array( 'strong_bearish' => 0, 'bearish' => 1, 'neutral' => 2, 'bullish' => 3, 'strong_bullish' => 4 );
		$confidence_display = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );

		$raw_bias = $available ? sanitize_key( (string) ( $snapshot['directional_bias'] ?? '' ) ) : '';
		$raw_strength = $available ? sanitize_key( (string) ( $snapshot['direction_strength'] ?? '' ) ) : '';
		$resolved = $available && isset( $bias_labels[ $raw_bias ], $strength_zone_index[ $raw_strength ] );
		$confidence_raw = $resolved ? sanitize_key( (string) ( $snapshot['confidence']['label'] ?? '' ) ) : '';
		$confidence_label = $confidence_display[ $confidence_raw ] ?? __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
		$state_label = $this->regime_display_label( $resolved ? ( $snapshot['market_state'] ?? '' ) : '' );
		$drivers = $resolved && is_array( $snapshot['key_drivers'] ?? null ) ? array_slice( array_values( array_filter( array_map( 'strval', $snapshot['key_drivers'] ) ) ), 0, 3 ) : array();
		$session = $resolved && is_array( $snapshot['session'] ?? null ) ? $snapshot['session'] : array();
		$session_label = trim( (string) ( $session['label'] ?? '' ) );
		?>
		<section class="bm-bi__snapshot">
			<div class="bm-bi__section-head">
				<div><p class="bm-bi__eyebrow"><?php echo esc_html( $session_label ?: 'KONDISI BTC SAAT INI' ); ?></p><h2><?php esc_html_e( 'Snapshot', 'bitmomo-btc-intelligence' ); ?></h2></div>
				<?php if ( $resolved ) : ?><span class="bm-bi__freshness"><?php $this->render_freshness( $snapshot ); ?></span><?php endif; ?>
			</div>
			<?php if ( ! $resolved ) : ?>
				<?php $this->render_blocked_boundary( __( 'Menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__snapshot-grid">
					<div><span>BTC</span><strong>$<?php echo esc_html( number_format_i18n( (float) ( $snapshot['btc_reference_price'] ?? 0 ), 0 ) ); ?></strong></div>
					<div><span>BIAS</span><strong class="is-<?php echo esc_attr( $raw_bias ); ?>"><?php echo esc_html( $strength_labels[ $raw_strength ] ); ?></strong></div>
					<div><span>CONFIDENCE</span><strong><?php echo esc_html( $confidence_label ); ?></strong></div>
					<div><span>STATE</span><strong><?php echo esc_html( $state_label ); ?></strong></div>
				</div>
				<div class="bm-bi__direction-rail" aria-label="Directional strength <?php echo esc_attr( $strength_labels[ $raw_strength ] ); ?>">
					<div class="bm-bi__direction-track" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><b class="is-<?php echo esc_attr( $raw_bias ); ?>" style="--bm-zone:<?php echo esc_attr( $strength_zone_index[ $raw_strength ] ); ?>"></b></div>
					<div class="bm-bi__direction-labels" aria-hidden="true"><span>Strong Bear</span><span>Neutral</span><span>Strong Bull</span></div>
				</div>
				<?php if ( $drivers ) : ?>
					<div class="bm-bi__drivers"><span>DRIVERS</span><ul><?php foreach ( $drivers as $driver ) : ?><li><?php echo esc_html( $driver ); ?></li><?php endforeach; ?></ul></div>
				<?php endif; ?>
				<p class="bm-bi__micro-note"><?php esc_html_e( 'Confidence = kekuatan evidence, bukan probabilitas keberhasilan.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_historical_regime() {
		?>
		<section class="bm-bi__section bm-bi__history">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">30 HARI</p><h2><?php esc_html_e( 'Market context', 'bitmomo-btc-intelligence' ); ?></h2></div></div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Setiap batang adalah catatan resmi: warna menunjukkan bias, tinggi menunjukkan certainty.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__history-embed">
				<?php
				if ( shortcode_exists( 'bitmomo_market_regime_history' ) ) {
					echo do_shortcode( '[bitmomo_market_regime_history days="30"]' );
				} else {
					$this->render_blocked_boundary( __( 'Riwayat Market State belum tersedia.', 'bitmomo-btc-intelligence' ) );
				}
				?>
			</div>
		</section>
		<?php
	}

	private function render_track_record() {
		$summary = $this->adapter_evaluation_summary();
		$versions = is_array( $summary['directional_evaluation'] ?? null ) ? $summary['directional_evaluation'] : array();
		$range = is_array( $summary['expected_range_evaluation'] ?? null ) ? $summary['expected_range_evaluation'] : array();
		$range_versions = is_array( $range['versions'] ?? null ) ? $range['versions'] : array();
		$data_quality = is_array( $summary['data_quality'] ?? null ) ? $summary['data_quality'] : array();
		?>
		<section class="bm-bi__section bm-bi__track-record">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">ACCOUNTABILITY</p><h2><?php esc_html_e( 'Track record', 'bitmomo-btc-intelligence' ); ?></h2></div></div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Insight dicatat sebelum outcome diketahui, lalu dibandingkan dengan hasil aktual.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( empty( $versions ) ) : ?>
				<?php $this->render_blocked_boundary( __( 'Data evaluasi publik belum cukup untuk diringkas.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<?php foreach ( $versions as $version => $metrics ) : ?>
					<div class="bm-bi__proof-group">
						<?php if ( count( $versions ) > 1 ) : ?><small>VERSION <?php echo esc_html( (string) $version ); ?></small><?php endif; ?>
						<div class="bm-bi__proof-grid">
							<?php $this->render_public_metric( __( 'Semua waktu', 'bitmomo-btc-intelligence' ), $metrics['all'] ?? array() ); ?>
							<?php $this->render_public_metric( __( 'Rolling 30', 'bitmomo-btc-intelligence' ), $metrics['rolling_30'] ?? array() ); ?>
							<?php $by_direction = is_array( $metrics['by_direction'] ?? null ) ? $metrics['by_direction'] : array(); ?>
							<?php $this->render_public_metric( 'Bullish', $by_direction['bullish'] ?? array() ); ?>
							<?php $this->render_public_metric( 'Bearish', $by_direction['bearish'] ?? array() ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( ! empty( $versions ) || ! empty( $range_versions ) || ! empty( $data_quality ) ) : ?>
			<details class="bm-bi__details">
				<summary><?php esc_html_e( 'Evaluasi lainnya', 'bitmomo-btc-intelligence' ); ?></summary>
				<div class="bm-bi__details-body">
					<?php foreach ( $versions as $version => $metrics ) :
						$buckets = is_array( $metrics['confidence_buckets'] ?? null ) ? $metrics['confidence_buckets'] : array();
						if ( $buckets ) : ?>
							<h3><?php esc_html_e( 'Confidence vs akurasi', 'bitmomo-btc-intelligence' ); ?></h3>
							<div class="bm-bi__proof-grid"><?php foreach ( $buckets as $bucket ) : $this->render_public_metric( (string) ( $bucket['range'] ?? 'Confidence' ), $bucket ); endforeach; ?></div>
						<?php endif;
						break;
					endforeach; ?>
					<?php if ( $range_versions ) : ?>
						<h3><?php esc_html_e( 'Expected Range historis', 'bitmomo-btc-intelligence' ); ?></h3>
						<?php $first_range = reset( $range_versions ); ?>
						<div class="bm-bi__proof-grid">
							<?php $this->render_public_metric( __( 'Range hit', 'bitmomo-btc-intelligence' ), $first_range, 'range_hit_pct' ); ?>
							<?php $this->render_public_metric( __( 'Breach bawah', 'bitmomo-btc-intelligence' ), $first_range, 'low_breach_pct' ); ?>
							<?php $this->render_public_metric( __( 'Breach atas', 'bitmomo-btc-intelligence' ), $first_range, 'high_breach_pct' ); ?>
						</div>
					<?php endif; ?>
					<?php if ( $data_quality ) : ?>
						<h3><?php esc_html_e( 'Kualitas data', 'bitmomo-btc-intelligence' ); ?></h3>
						<div class="bm-bi__proof-grid">
							<?php $this->render_public_metric( __( 'Stale rate', 'bitmomo-btc-intelligence' ), $data_quality, 'stale_rate_pct' ); ?>
							<?php $this->render_public_metric( __( 'Missing data', 'bitmomo-btc-intelligence' ), $data_quality, 'missing_data_rate_pct' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</details>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_methodology() {
		?>
		<section class="bm-bi__section bm-bi__methodology">
			<details class="bm-bi__details">
				<summary><?php esc_html_e( 'Cara kerja & metodologi', 'bitmomo-btc-intelligence' ); ?></summary>
				<div class="bm-bi__details-body">
					<p><?php esc_html_e( 'Bitmomo merangkum lima axis deterministik: Direction, Volatility, Carry, Structure, dan Crowding. Hasilnya menjadi Market State, Directional Bias, dan Confidence.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Market State menjelaskan konteks. Bias menjelaskan kecenderungan arah. Confidence menjelaskan kekuatan evidence — bukan probabilitas benar.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Setiap hasil dibekukan sebelum outcome diketahui dan tidak ditulis ulang agar terlihat lebih baik.', 'bitmomo-btc-intelligence' ); ?></p>
				</div>
			</details>
		</section>
		<?php
	}

	private function render_pro_cta() {
		?>
		<section class="bm-bi__pro-cta">
			<p class="bm-bi__eyebrow">BITMOMO PRO</p>
			<h2><?php esc_html_e( 'Dari kondisi sekarang ke apa yang perlu dipantau berikutnya.', 'bitmomo-btc-intelligence' ); ?></h2>
			<p><?php esc_html_e( 'Skenario, invalidation, perubahan thesis, dan alert — tanpa harus memantau semuanya sendiri.', 'bitmomo-btc-intelligence' ); ?></p>
			<a class="bm-bi__cta-primary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
		</section>
		<?php
	}
}

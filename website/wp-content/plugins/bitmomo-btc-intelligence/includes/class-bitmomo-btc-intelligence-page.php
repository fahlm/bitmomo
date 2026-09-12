<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public BTC Intelligence page.
 *
 * Public information is deliberately narrower than the engine contract:
 * visitors get the current reading, the reasons that matter, recent context,
 * and accountable outcome evidence. Diagnostics, calibration internals,
 * classifier identities, raw factor grids and protected Pro fields stay out
 * of the presentation layer.
 */
class Bitmomo_Btc_Intelligence_Page {

	private static $instance = null;
	private $adapter_snapshot = null;
	private $adapter_snapshot_loaded = false;
	private $surface_context = null;
	private $surface_context_loaded = false;
	private $history = null;
	private $history_loaded = false;
	private $evaluation_summary = null;
	private $evaluation_summary_loaded = false;

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
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
			? __( 'Kondisi BTC saat ini, alasan utama, konteks 30 hari, dan track record pembacaan Bitmomo.', 'bitmomo-btc-intelligence' )
			: $description;
	}

	public function render_meta_description() {
		if ( is_page( 'btc-intelligence' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
			echo '<meta name="description" content="' . esc_attr( $this->filter_meta_description( '' ) ) . '" />' . "\n";
		}
	}

	public function prevent_snapshot_page_cache() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_btc_intelligence' ) ) return;
		if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'Canonical intelligence freshness' );
	}

	public function maybe_enqueue_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( (string) $post->post_content, 'bitmomo_btc_intelligence' ) ) return;
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

	private function adapter_surface_context() {
		if ( $this->surface_context_loaded ) return $this->surface_context;
		$this->surface_context_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'surface_context' ) ) {
			$this->surface_context = Bitmomo_Public_Intelligence_Adapter::surface_context();
		}
		return is_array( $this->surface_context ) ? $this->surface_context : array();
	}

	private function adapter_history() {
		if ( $this->history_loaded ) return is_array( $this->history ) ? $this->history : array( 'days' => array() );
		$this->history_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'history' ) ) {
			$this->history = Bitmomo_Public_Intelligence_Adapter::history();
		}
		return is_array( $this->history ) ? $this->history : array( 'days' => array() );
	}

	private function adapter_evaluation_summary() {
		if ( $this->evaluation_summary_loaded ) return $this->evaluation_summary;
		$this->evaluation_summary_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'evaluation_summary' ) ) {
			$this->evaluation_summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
		}
		return is_array( $this->evaluation_summary ) ? $this->evaluation_summary : array();
	}

	private function bias_label( $value ) {
		$labels = array( 'bullish' => 'Bullish', 'neutral' => 'Netral', 'bearish' => 'Bearish' );
		$key = sanitize_key( (string) $value );
		return $labels[ $key ] ?? __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function direction_label( $value ) {
		$labels = array(
			'strong_bearish' => 'Bearish kuat',
			'bearish' => 'Bearish',
			'neutral' => 'Netral',
			'bullish' => 'Bullish',
			'strong_bullish' => 'Bullish kuat',
		);
		$key = sanitize_key( (string) $value );
		return $labels[ $key ] ?? __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function confidence_label( $value ) {
		$labels = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
		return $labels[ sanitize_key( (string) $value ) ] ?? '';
	}

	private function activity_display( $opportunity ) {
		$opportunity = is_array( $opportunity ) ? $opportunity : array();
		$state = sanitize_key( (string) ( $opportunity['state'] ?? '' ) );
		if ( 'available' !== sanitize_key( (string) ( $opportunity['status'] ?? '' ) ) || ! in_array( $state, array( 'high', 'normal', 'low' ), true ) ) {
			return array( 'available' => false, 'label' => __( 'Belum tersedia', 'bitmomo-btc-intelligence' ), 'copy' => __( 'Aktivitas pasar sedang menunggu data yang layak.', 'bitmomo-btc-intelligence' ) );
		}
		$labels = array( 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah' );
		$copy = array(
			'high' => __( 'BTC bergerak lebih aktif dibanding kondisi normal dua pekan terakhir.', 'bitmomo-btc-intelligence' ),
			'normal' => __( 'Aktivitas BTC berada di sekitar kondisi normal dua pekan terakhir.', 'bitmomo-btc-intelligence' ),
			'low' => __( 'BTC sedang relatif lebih tenang dibanding kondisi normal dua pekan terakhir.', 'bitmomo-btc-intelligence' ),
		);
		return array( 'available' => true, 'label' => $labels[ $state ], 'copy' => $copy[ $state ] );
	}

	private function public_sample_status_label( $sample_status ) {
		$labels = array(
			'ADEQUATE' => __( 'Sampel memadai', 'bitmomo-btc-intelligence' ),
			'EARLY SAMPLE' => __( 'Sampel awal', 'bitmomo-btc-intelligence' ),
			'INSUFFICIENT SAMPLE' => __( 'Sampel belum cukup', 'bitmomo-btc-intelligence' ),
		);
		return $labels[ (string) $sample_status ] ?? __( 'Sampel belum cukup', 'bitmomo-btc-intelligence' );
	}

	private function source_label( $source ) {
		$source = strtolower( (string) $source );
		if ( false !== strpos( $source, 'bybit' ) ) return 'Binance + Bybit';
		if ( false !== strpos( $source, 'binance' ) ) return 'Binance';
		return __( 'Sumber data terverifikasi', 'bitmomo-btc-intelligence' );
	}

	private function render_freshness( $snapshot ) {
		$freshness = is_array( $snapshot['freshness'] ?? null ) ? $snapshot['freshness'] : array();
		$state = sanitize_key( (string) ( $freshness['state'] ?? $snapshot['status'] ?? '' ) );
		$iso = trim( (string) ( $freshness['timestamp_iso'] ?? '' ) );
		$timestamp = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $iso ) ? strtotime( $iso ) : false;
		if ( ! $timestamp ) return;
		$exact = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB';
		$label = 'delayed' === $state ? __( 'DATA TERTUNDA', 'bitmomo-btc-intelligence' ) : __( 'DIPERBARUI', 'bitmomo-btc-intelligence' );
		echo '<span class="bm-bi__freshness is-' . esc_attr( $state ?: 'fresh' ) . '"><strong>' . esc_html( $label ) . '</strong><time datetime="' . esc_attr( $iso ) . '">' . esc_html( $exact ) . '</time></span>';
	}

	private function render_metric( $label, $metric ) {
		$metric = is_array( $metric ) ? $metric : array();
		$value = isset( $metric['accuracy_pct'] ) && is_numeric( $metric['accuracy_pct'] ) ? (float) $metric['accuracy_pct'] : null;
		$n = isset( $metric['n'] ) ? max( 0, (int) $metric['n'] ) : 0;
		$conclusive = isset( $metric['conclusive_n'] ) ? max( 0, (int) $metric['conclusive_n'] ) : 0;
		$status = $this->public_sample_status_label( $metric['sample_status'] ?? '' );
		?>
		<div class="bm-bi__proof-metric">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo null !== $value ? esc_html( number_format_i18n( $value, 1 ) . '%' ) : esc_html__( '—', 'bitmomo-btc-intelligence' ); ?></strong>
			<small><?php echo esc_html( sprintf( __( '%d outcome konklusif · %d total · %s', 'bitmomo-btc-intelligence' ), $conclusive, $n, $status ) ); ?></small>
		</div>
		<?php
	}

	private function render_blocked_boundary( $note ) {
		echo '<div class="bm-bi__blocked" role="status"><span>' . esc_html( $note ) . '</span></div>';
	}

	public function render_page( $atts ) {
		ob_start();
		echo '<div class="bm-bi">';
		$this->render_hero();
		$this->render_current_snapshot();
		$this->render_history();
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
			<h1><?php esc_html_e( 'Pahami kondisi BTC sekarang — tanpa tenggelam dalam data.', 'bitmomo-btc-intelligence' ); ?></h1>
			<p><?php esc_html_e( 'Lihat arah pasar, tingkat keyakinan pembacaan, alasan utamanya, konteks 30 hari, dan bagaimana pembacaan Bitmomo diuji terhadap hasil aktual.', 'bitmomo-btc-intelligence' ); ?></p>
			<small><?php esc_html_e( 'Bukan sinyal beli/jual. Bukan saran keuangan.', 'bitmomo-btc-intelligence' ); ?></small>
		</section>
		<?php
	}

	private function render_current_snapshot() {
		$snapshot = $this->adapter_snapshot();
		$surface = $this->adapter_surface_context();
		$available = is_array( $snapshot );
		$opportunity = $available && is_array( $snapshot['opportunity'] ?? null ) ? $snapshot['opportunity'] : ( is_array( $surface['opportunity'] ?? null ) ? $surface['opportunity'] : array() );
		$activity = $this->activity_display( $opportunity );
		$bias = $available ? sanitize_key( (string) ( $snapshot['directional_bias'] ?? '' ) ) : '';
		$strength = $available ? sanitize_key( (string) ( $snapshot['direction_strength'] ?? '' ) ) : '';
		$direction_valid = in_array( $bias, array( 'bullish', 'neutral', 'bearish' ), true ) && in_array( $strength, array( 'strong_bearish', 'bearish', 'neutral', 'bullish', 'strong_bullish' ), true );
		$confidence = $available && isset( $snapshot['confidence']['value'] ) && is_numeric( $snapshot['confidence']['value'] ) ? max( 0, min( 100, (int) $snapshot['confidence']['value'] ) ) : null;
		$confidence_label = $available ? $this->confidence_label( $snapshot['confidence']['label'] ?? '' ) : '';
		$price = $available && isset( $snapshot['btc_reference_price'] ) && is_numeric( $snapshot['btc_reference_price'] ) ? (float) $snapshot['btc_reference_price'] : null;
		$drivers = $available && is_array( $snapshot['key_drivers'] ?? null ) ? array_slice( array_values( array_filter( array_map( 'strval', $snapshot['key_drivers'] ) ) ), 0, 2 ) : array();
		$session_intelligence = $available && is_array( $snapshot['session_intelligence'] ?? null ) ? $snapshot['session_intelligence'] : array();
		?>
		<section class="bm-bi__snapshot" aria-labelledby="bm-bi-current-title">
			<div class="bm-bi__section-head">
				<div>
					<p class="bm-bi__eyebrow"><?php esc_html_e( 'SEKARANG', 'bitmomo-btc-intelligence' ); ?></p>
					<h2 id="bm-bi-current-title"><?php esc_html_e( 'Pembacaan BTC', 'bitmomo-btc-intelligence' ); ?></h2>
					<?php if ( null !== $price ) : ?><p class="bm-bi__reference-price">BTC $<?php echo esc_html( number_format_i18n( $price, 0 ) ); ?></p><?php endif; ?>
				</div>
				<?php if ( $available ) $this->render_freshness( $snapshot ); ?>
			</div>

			<?php if ( ! $available || ! $direction_valid ) : ?>
				<?php $this->render_blocked_boundary( __( 'Pembacaan arah sedang ditahan sampai data memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__snapshot-grid">
					<div><span>ARAH</span><strong class="is-<?php echo esc_attr( $bias ); ?>"><?php echo esc_html( $this->direction_label( $strength ) ); ?></strong><small><?php esc_html_e( 'ke mana bukti pasar saat ini lebih condong', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div><span>KEYAKINAN</span><strong><?php echo esc_html( null !== $confidence ? $confidence . '/100' . ( $confidence_label ? ' · ' . $confidence_label : '' ) : '—' ); ?></strong><small><?php esc_html_e( 'seberapa konsisten bukti yang dibaca, bukan probabilitas harga', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div><span>AKTIVITAS PASAR</span><strong><?php echo esc_html( $activity['label'] ); ?></strong><small><?php echo esc_html( $activity['copy'] ); ?></small></div>
				</div>

				<?php if ( $drivers ) : ?>
					<div class="bm-bi__reasons">
						<h3><?php esc_html_e( 'Kenapa pembacaannya seperti ini?', 'bitmomo-btc-intelligence' ); ?></h3>
						<ul><?php foreach ( $drivers as $driver ) : ?><li><?php echo esc_html( $driver ); ?></li><?php endforeach; ?></ul>
					</div>
				<?php endif; ?>

				<?php $this->render_what_changed( $session_intelligence ); ?>
				<?php $this->render_provenance( $snapshot ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_what_changed( $session_intelligence ) {
		$comparison = is_array( $session_intelligence['comparison'] ?? null ) ? $session_intelligence['comparison'] : array();
		$changes = is_array( $session_intelligence['what_changed'] ?? null ) ? $session_intelligence['what_changed'] : array();
		if ( ( $comparison['status'] ?? '' ) !== 'compared' || ! $changes ) return;

		$lines = array();
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) ) continue;
			$field = sanitize_key( (string) ( $change['field'] ?? '' ) );
			if ( 'directional_bias' === $field ) {
				$lines[] = sprintf( __( 'Arah berubah dari %s menjadi %s.', 'bitmomo-btc-intelligence' ), $this->bias_label( $change['from'] ?? '' ), $this->bias_label( $change['to'] ?? '' ) );
			} elseif ( 'direction_strength' === $field ) {
				$lines[] = sprintf( __( 'Kekuatan arah berubah dari %s menjadi %s.', 'bitmomo-btc-intelligence' ), $this->direction_label( $change['from'] ?? '' ), $this->direction_label( $change['to'] ?? '' ) );
			} elseif ( 'confidence' === $field ) {
				$from = max( 0, min( 100, (int) ( $change['from'] ?? 0 ) ) );
				$to = max( 0, min( 100, (int) ( $change['to'] ?? 0 ) ) );
				$lines[] = sprintf( __( 'Keyakinan pembacaan berubah dari %d menjadi %d.', 'bitmomo-btc-intelligence' ), $from, $to );
			} elseif ( 'opportunity_state' === $field ) {
				$map = array( 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah' );
				$from = $map[ sanitize_key( (string) ( $change['from'] ?? '' ) ) ] ?? '';
				$to = $map[ sanitize_key( (string) ( $change['to'] ?? '' ) ) ] ?? '';
				if ( $from && $to ) $lines[] = sprintf( __( 'Aktivitas pasar berubah dari %s menjadi %s.', 'bitmomo-btc-intelligence' ), $from, $to );
			}
			if ( count( $lines ) >= 2 ) break;
		}
		if ( ! $lines ) return;
		?>
		<div class="bm-bi__changes">
			<h3><?php esc_html_e( 'Apa yang berubah?', 'bitmomo-btc-intelligence' ); ?></h3>
			<ul><?php foreach ( $lines as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
		</div>
		<?php
	}

	private function render_provenance( $snapshot ) {
		$provenance = is_array( $snapshot['provenance'] ?? null ) ? $snapshot['provenance'] : array();
		$source = trim( (string) ( $provenance['source'] ?? '' ) );
		$as_of = trim( (string) ( $provenance['as_of'] ?? '' ) );
		$timestamp = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $as_of ) ? strtotime( $as_of ) : false;
		?>
		<div class="bm-bi__provenance">
			<?php if ( $timestamp ) : ?><time datetime="<?php echo esc_attr( $as_of ); ?>"><?php echo esc_html( ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB' ); ?></time><?php endif; ?>
			<?php if ( $source ) : ?><span><?php echo esc_html( sprintf( __( 'Sumber data: %s', 'bitmomo-btc-intelligence' ), $this->source_label( $source ) ) ); ?></span><?php endif; ?>
		</div>
		<?php
	}

	private function render_history() {
		$history = $this->adapter_history();
		$days = is_array( $history['days'] ?? null ) ? array_slice( $history['days'], -30 ) : array();
		$counts = array( 'bullish' => 0, 'neutral' => 0, 'bearish' => 0 );
		foreach ( $days as $day ) {
			$bias = sanitize_key( (string) ( $day['directional_bias'] ?? '' ) );
			if ( isset( $counts[ $bias ] ) ) $counts[ $bias ]++;
		}
		?>
		<section class="bm-bi__section bm-bi__history" aria-labelledby="bm-bi-history-title">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">KONTEKS 30 HARI</p><h2 id="bm-bi-history-title"><?php esc_html_e( 'Bagaimana arah pembacaan berubah?', 'bitmomo-btc-intelligence' ); ?></h2></div></div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Satu warna mewakili satu catatan resmi per market day. Hari tanpa catatan tidak diisi dengan data buatan.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( ! $days ) : ?>
				<?php $this->render_blocked_boundary( __( 'Riwayat 30 hari belum cukup tersedia.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__history-bars" style="--bm-bi-history-count:<?php echo esc_attr( max( 1, count( $days ) ) ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( __( '%d hari catatan arah BTC: %d bullish, %d netral, %d bearish.', 'bitmomo-btc-intelligence' ), count( $days ), $counts['bullish'], $counts['neutral'], $counts['bearish'] ) ); ?>">
					<?php foreach ( $days as $day ) :
						$date = (string) ( $day['date'] ?? '' );
						$bias = sanitize_key( (string) ( $day['directional_bias'] ?? '' ) );
						if ( ! isset( $counts[ $bias ] ) ) continue;
						$short_date = $date && strtotime( $date ) ? wp_date( 'd M', strtotime( $date ) ) : $date;
						?><span class="is-<?php echo esc_attr( $bias ); ?>" title="<?php echo esc_attr( $short_date . ' · ' . $this->bias_label( $bias ) ); ?>"></span><?php
					endforeach; ?>
				</div>
				<div class="bm-bi__history-summary">
					<span class="is-bullish"><?php echo esc_html( sprintf( __( 'Bullish %d', 'bitmomo-btc-intelligence' ), $counts['bullish'] ) ); ?></span>
					<span class="is-neutral"><?php echo esc_html( sprintf( __( 'Netral %d', 'bitmomo-btc-intelligence' ), $counts['neutral'] ) ); ?></span>
					<span class="is-bearish"><?php echo esc_html( sprintf( __( 'Bearish %d', 'bitmomo-btc-intelligence' ), $counts['bearish'] ) ); ?></span>
					<small><?php echo esc_html( sprintf( __( '%d hari data tersedia', 'bitmomo-btc-intelligence' ), count( $days ) ) ); ?></small>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_track_record() {
		$summary = $this->adapter_evaluation_summary();
		$versions = is_array( $summary['directional_evaluation'] ?? null ) ? $summary['directional_evaluation'] : array();
		$current = $versions ? reset( $versions ) : array();
		?>
		<section class="bm-bi__section bm-bi__track-record" aria-labelledby="bm-bi-track-title">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">TRACK RECORD</p><h2 id="bm-bi-track-title"><?php esc_html_e( 'Apakah pembacaannya terbukti?', 'bitmomo-btc-intelligence' ); ?></h2></div></div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Setiap pembacaan dibekukan sebelum hasil berikutnya diketahui. Angka di bawah hanya memakai metodologi evaluasi saat ini; metodologi lama tidak dicampur.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( ! is_array( $current ) || empty( $current ) || empty( $current['all']['n'] ) ) : ?>
				<?php $this->render_blocked_boundary( __( 'Belum ada cukup outcome yang layak untuk diringkas.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__proof-grid">
					<?php $this->render_metric( __( 'Keseluruhan', 'bitmomo-btc-intelligence' ), $current['all'] ?? array() ); ?>
					<?php $this->render_metric( __( '30 pembacaan terakhir', 'bitmomo-btc-intelligence' ), $current['rolling_30'] ?? array() ); ?>
					<?php $directions = is_array( $current['by_direction'] ?? null ) ? $current['by_direction'] : array(); ?>
					<?php $this->render_metric( 'Bullish', $directions['bullish'] ?? array() ); ?>
					<?php $this->render_metric( 'Bearish', $directions['bearish'] ?? array() ); ?>
				</div>
				<p class="bm-bi__micro-note"><?php esc_html_e( 'Outcome dinilai tepat +24 jam dari observation time. Untuk pembacaan Bullish/Bearish, gerak antara -0,5% dan +0,5% dianggap inconclusive dan tidak masuk denominator akurasi.', 'bitmomo-btc-intelligence' ); ?></p>
				<?php if ( count( $versions ) > 1 ) : ?><p class="bm-bi__history-policy"><?php esc_html_e( 'Hasil dari metodologi sebelumnya tetap disimpan untuk audit, tetapi tidak dicampur dengan angka di atas.', 'bitmomo-btc-intelligence' ); ?></p><?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_methodology() {
		?>
		<section class="bm-bi__section bm-bi__methodology">
			<details class="bm-bi__details">
				<summary><?php esc_html_e( 'Bagaimana membaca angka ini?', 'bitmomo-btc-intelligence' ); ?></summary>
				<div class="bm-bi__details-body">
					<p><?php esc_html_e( 'Bitmomo membaca price action, volatilitas, struktur pasar, dan kondisi pasar derivatif BTC lalu merangkum bukti yang saling mendukung atau bertentangan.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Arah menunjukkan ke mana bukti pasar lebih condong. Keyakinan menunjukkan seberapa kuat dan konsisten bukti tersebut; angka ini bukan probabilitas harga akan naik atau turun.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Aktivitas pasar membandingkan pergerakan BTC saat ini dengan kondisi normal dua pekan terakhir. Ia mengukur seberapa aktif pasar, bukan arah pergerakannya.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Jika data tidak memenuhi standar kualitas, Bitmomo menahan pembacaan daripada menebak. Track record memakai catatan yang sudah dibekukan dan outcome +24 jam yang kompatibel dengan metodologi saat ini.', 'bitmomo-btc-intelligence' ); ?></p>
				</div>
			</details>
		</section>
		<?php
	}

	private function render_pro_cta() {
		?>
		<section class="bm-bi__pro-cta">
			<p class="bm-bi__eyebrow">BITMOMO PRO</p>
			<h2><?php esc_html_e( 'Dari memahami kondisi sekarang ke apa yang perlu dipantau berikutnya.', 'bitmomo-btc-intelligence' ); ?></h2>
			<p><?php esc_html_e( 'Pro menambahkan skenario utama, rentang yang dipantau, level yang mengubah pandangan, dan perubahan penting — tanpa harus memantau semuanya sendiri.', 'bitmomo-btc-intelligence' ); ?></p>
			<a class="bm-bi__cta-primary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
		</section>
		<?php
	}
}

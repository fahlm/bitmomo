<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public BTC Intelligence terminal.
 *
 * Free answers, in order:
 * 1) What is happening now?
 * 2) What changed?
 * 3) Why does it matter?
 * 4) What is one public-safe thing worth observing next?
 * 5) What did Bitmomo say before, and what happened afterwards?
 *
 * The renderer remains presentation-only. Current intelligence comes from the
 * public adapter. Row-level accountability and delayed Pro proof come from the
 * dedicated read-only accountability boundary. No engine logic is recomputed
 * here and current protected Pro content is never queried from this class.
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
	private $decision_ledger = null;
	private $decision_ledger_loaded = false;
	private $delayed_proof = null;
	private $delayed_proof_loaded = false;

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
			? __( 'Kondisi BTC saat ini, perubahan penting, konteks yang perlu dipantau, Decision Ledger, dan bukti historis Bitmomo Pro.', 'bitmomo-btc-intelligence' )
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
		if ( $this->adapter_snapshot_loaded ) {
			return $this->adapter_snapshot;
		}
		$this->adapter_snapshot_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'snapshot' ) ) {
			$this->adapter_snapshot = Bitmomo_Public_Intelligence_Adapter::snapshot();
		}
		return $this->adapter_snapshot;
	}

	private function adapter_surface_context() {
		if ( $this->surface_context_loaded ) {
			return is_array( $this->surface_context ) ? $this->surface_context : array();
		}
		$this->surface_context_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'surface_context' ) ) {
			$this->surface_context = Bitmomo_Public_Intelligence_Adapter::surface_context();
		}
		return is_array( $this->surface_context ) ? $this->surface_context : array();
	}

	private function adapter_history() {
		if ( $this->history_loaded ) {
			return is_array( $this->history ) ? $this->history : array( 'days' => array() );
		}
		$this->history_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'history' ) ) {
			$this->history = Bitmomo_Public_Intelligence_Adapter::history();
		}
		return is_array( $this->history ) ? $this->history : array( 'days' => array() );
	}

	private function adapter_evaluation_summary() {
		if ( $this->evaluation_summary_loaded ) {
			return is_array( $this->evaluation_summary ) ? $this->evaluation_summary : array();
		}
		$this->evaluation_summary_loaded = true;
		if ( class_exists( 'Bitmomo_Public_Intelligence_Adapter' ) && method_exists( 'Bitmomo_Public_Intelligence_Adapter', 'evaluation_summary' ) ) {
			$this->evaluation_summary = Bitmomo_Public_Intelligence_Adapter::evaluation_summary();
		}
		return is_array( $this->evaluation_summary ) ? $this->evaluation_summary : array();
	}

	private function accountability_ledger() {
		if ( $this->decision_ledger_loaded ) {
			return is_array( $this->decision_ledger ) ? $this->decision_ledger : array( 'rows' => array() );
		}
		$this->decision_ledger_loaded = true;
		if ( class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) ) {
			$this->decision_ledger = Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 12 );
		}
		return is_array( $this->decision_ledger ) ? $this->decision_ledger : array( 'rows' => array() );
	}

	private function accountability_delayed_proof() {
		if ( $this->delayed_proof_loaded ) {
			return is_array( $this->delayed_proof ) ? $this->delayed_proof : array( 'rows' => array() );
		}
		$this->delayed_proof_loaded = true;
		if ( class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) ) {
			$this->delayed_proof = Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 3 );
		}
		return is_array( $this->delayed_proof ) ? $this->delayed_proof : array( 'rows' => array() );
	}

	private function bias_label( $value ) {
		$labels = array( 'bullish' => 'Bullish', 'neutral' => 'Netral', 'bearish' => 'Bearish' );
		$key = sanitize_key( (string) $value );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function direction_label( $value ) {
		$labels = array(
			'strong_bearish' => 'Bearish kuat',
			'bearish'        => 'Bearish',
			'neutral'        => 'Netral',
			'bullish'        => 'Bullish',
			'strong_bullish' => 'Bullish kuat',
		);
		$key = sanitize_key( (string) $value );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function confidence_label( $value ) {
		$labels = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
		$key = sanitize_key( (string) $value );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}

	private function activity_display( $opportunity ) {
		$opportunity = is_array( $opportunity ) ? $opportunity : array();
		$state = sanitize_key( strtolower( (string) ( $opportunity['state'] ?? '' ) ) );
		if ( 'available' !== sanitize_key( (string) ( $opportunity['status'] ?? '' ) ) || ! in_array( $state, array( 'high', 'normal', 'low' ), true ) ) {
			return array(
				'available' => false,
				'label'     => __( 'Belum tersedia', 'bitmomo-btc-intelligence' ),
				'copy'      => __( 'Data aktivitas pasar belum memenuhi standar publikasi.', 'bitmomo-btc-intelligence' ),
			);
		}
		$labels = array( 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah' );
		$copy = array(
			'high'   => __( 'Aktivitas pasar berada di atas kondisi normal 14 hari.', 'bitmomo-btc-intelligence' ),
			'normal' => __( 'Aktivitas pasar berada di sekitar kondisi normal 14 hari.', 'bitmomo-btc-intelligence' ),
			'low'    => __( 'Aktivitas pasar berada di bawah kondisi normal 14 hari.', 'bitmomo-btc-intelligence' ),
		);
		return array( 'available' => true, 'label' => $labels[ $state ], 'copy' => $copy[ $state ] );
	}

	private function public_sample_status_label( $sample_status ) {
		$labels = array(
			'ADEQUATE'            => __( 'Sampel memadai', 'bitmomo-btc-intelligence' ),
			'EARLY SAMPLE'        => __( 'Sampel awal — belum layak disimpulkan', 'bitmomo-btc-intelligence' ),
			'INSUFFICIENT SAMPLE' => __( 'Sampel belum cukup — jangan simpulkan performa', 'bitmomo-btc-intelligence' ),
		);
		return isset( $labels[ (string) $sample_status ] ) ? $labels[ (string) $sample_status ] : __( 'Sampel belum cukup — jangan simpulkan performa', 'bitmomo-btc-intelligence' );
	}

	private function source_label( $source ) {
		$source = strtolower( (string) $source );
		if ( false !== strpos( $source, 'bybit' ) ) {
			return 'Binance + Bybit';
		}
		if ( false !== strpos( $source, 'binance' ) ) {
			return 'Binance';
		}
		return __( 'Sumber data terverifikasi', 'bitmomo-btc-intelligence' );
	}

	private function format_wib( $iso, $format = 'd M Y · H:i' ) {
		$iso = trim( (string) $iso );
		$timestamp = preg_match( '/(?:Z|[+-]\d{2}:?\d{2})$/', $iso ) ? strtotime( $iso ) : false;
		if ( ! $timestamp ) {
			return '';
		}
		return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( $format ) . ' WIB';
	}

	private function format_price( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? '$' . number_format_i18n( (float) $value, 0 ) : '—';
	}

	private function format_return( $value ) {
		if ( ! is_numeric( $value ) ) {
			return '—';
		}
		$value = (float) $value;
		return ( $value > 0 ? '+' : '' ) . number_format_i18n( $value, 2 ) . '%';
	}

	private function verdict_label( $verdict ) {
		$labels = array(
			'aligned'      => __( 'SESUAI', 'bitmomo-btc-intelligence' ),
			'missed'       => __( 'TIDAK SESUAI', 'bitmomo-btc-intelligence' ),
			'inconclusive' => __( 'TIDAK KONKLUSIF', 'bitmomo-btc-intelligence' ),
			'unscored'     => __( 'BELUM DINILAI', 'bitmomo-btc-intelligence' ),
		);
		$key = sanitize_key( (string) $verdict );
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $labels['unscored'];
	}

	private function render_freshness( $snapshot ) {
		$freshness = is_array( $snapshot['freshness'] ?? null ) ? $snapshot['freshness'] : array();
		$state = sanitize_key( (string) ( $freshness['state'] ?? $snapshot['status'] ?? '' ) );
		$iso = trim( (string) ( $freshness['timestamp_iso'] ?? '' ) );
		$exact = $this->format_wib( $iso );
		if ( '' === $exact ) {
			return;
		}
		$label = 'delayed' === $state ? __( 'DATA TERTUNDA', 'bitmomo-btc-intelligence' ) : __( 'DIPERBARUI', 'bitmomo-btc-intelligence' );
		echo '<span class="bm-bi__freshness is-' . esc_attr( $state ?: 'fresh' ) . '"><strong>' . esc_html( $label ) . '</strong><time datetime="' . esc_attr( $iso ) . '">' . esc_html( $exact ) . '</time></span>';
	}

	private function render_metric( $label, $metric ) {
		$metric = is_array( $metric ) ? $metric : array();
		$value = isset( $metric['accuracy_pct'] ) && is_numeric( $metric['accuracy_pct'] ) ? (float) $metric['accuracy_pct'] : null;
		$n = isset( $metric['n'] ) ? max( 0, (int) $metric['n'] ) : 0;
		$conclusive = isset( $metric['conclusive_n'] ) ? max( 0, (int) $metric['conclusive_n'] ) : 0;
		$sample_code = (string) ( $metric['sample_status'] ?? '' );
		$status = $this->public_sample_status_label( $sample_code );
		$accuracy = null !== $value ? number_format_i18n( $value, 1 ) . '%' : '—';
		if ( 'INSUFFICIENT SAMPLE' === $sample_code ) {
			$accuracy_line = __( 'Akurasi ditahan sampai sampel minimum terpenuhi.', 'bitmomo-btc-intelligence' );
		} elseif ( 'EARLY SAMPLE' === $sample_code ) {
			$accuracy_line = sprintf( __( 'Akurasi sementara %s', 'bitmomo-btc-intelligence' ), $accuracy );
		} else {
			$accuracy_line = sprintf( __( 'Akurasi %s', 'bitmomo-btc-intelligence' ), $accuracy );
		}
		?>
		<div class="bm-bi__proof-metric">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( sprintf( '%d / %d', $conclusive, $n ) ); ?></strong>
			<small><?php echo esc_html( sprintf( __( 'outcome konklusif / total · %s', 'bitmomo-btc-intelligence' ), $status ) ); ?></small>
			<small><?php echo esc_html( $accuracy_line ); ?></small>
		</div>
		<?php
	}

	private function render_blocked_boundary( $note ) {
		echo '<div class="bm-bi__blocked" role="status"><span>' . esc_html( $note ) . '</span></div>';
	}

	private function render_delayed_current_boundary( $snapshot, $price ) {
		$freshness = is_array( $snapshot['freshness'] ?? null ) ? $snapshot['freshness'] : array();
		$iso = trim( (string) ( $freshness['timestamp_iso'] ?? '' ) );
		$exact = $this->format_wib( $iso );
		$note = __( 'PEMBACAAN SAAT INI DITAHAN — MAJOR BRIEF TERTUNDA. Bias dan confidence brief ditahan sampai data brief kembali memenuhi standar freshness Bitmomo. Market Pulse tetap ditampilkan terpisah bila data intraday-nya masih fresh.', 'bitmomo-btc-intelligence' );
		if ( $exact ) {
			$note .= ' ' . sprintf( __( 'Observasi brief terverifikasi terakhir: %s.', 'bitmomo-btc-intelligence' ), $exact );
		}
		if ( null !== $price ) {
			$note .= ' ' . sprintf( __( 'BTC referensi brief terakhir: %s.', 'bitmomo-btc-intelligence' ), $this->format_price( $price ) );
		}
		$this->render_blocked_boundary( $note );
		$this->render_provenance( $snapshot );
	}

	private function session_label( $snapshot ) {
		$session = is_array( $snapshot['session'] ?? null ) ? $snapshot['session'] : array();
		$label = trim( (string) ( $session['label'] ?? '' ) );
		if ( '' !== $label ) {
			return sanitize_text_field( $label );
		}
		$type = sanitize_key( (string) ( $session['type'] ?? '' ) );
		return 'us_pre_open' === $type ? 'US PRE-OPEN' : ( 'us_post_close' === $type ? 'US POST-CLOSE' : '' );
	}

	private function session_type( $snapshot ) {
		$session = is_array( $snapshot['session'] ?? null ) ? $snapshot['session'] : array();
		return sanitize_key( (string) ( $session['type'] ?? '' ) );
	}

	private function session_anchor_label( $snapshot ) {
		$session = is_array( $snapshot['session'] ?? null ) ? $snapshot['session'] : array();
		return $this->format_wib( $session['anchor'] ?? '', 'd M · H:i' );
	}

	private function market_pulse_line( $opportunity ) {
		if ( ! is_array( $opportunity ) || 'available' !== sanitize_key( (string) ( $opportunity['status'] ?? '' ) ) ) {
			return '';
		}
		return __( 'Market Pulse · evaluasi 15 menit dari candle 5 menit', 'bitmomo-btc-intelligence' );
	}

	private function render_market_pulse( $activity, $pulse_line ) {
		if ( empty( $activity['available'] ) ) {
			return;
		}
		?>
		<div class="bm-bi__brief-grid" aria-label="<?php esc_attr_e( 'Market Pulse intraday', 'bitmomo-btc-intelligence' ); ?>">
			<div class="bm-bi__brief-panel">
				<h3><?php esc_html_e( 'MARKET PULSE · INTRADAY', 'bitmomo-btc-intelligence' ); ?></h3>
				<span><?php esc_html_e( 'AKTIVITAS PASAR', 'bitmomo-btc-intelligence' ); ?></span>
				<p><strong><?php echo esc_html( $activity['label'] ); ?></strong> — <?php echo esc_html( $activity['copy'] ); ?></p>
			</div>
			<div class="bm-bi__brief-panel">
				<h3><?php esc_html_e( 'CLOCK & FRESHNESS', 'bitmomo-btc-intelligence' ); ?></h3>
				<p><?php echo esc_html( $pulse_line ?: __( 'Market Pulse memakai clock intraday terpisah dari Major Brief.', 'bitmomo-btc-intelligence' ) ); ?></p>
			</div>
		</div>
		<?php
	}

	private function what_happened_summary( $snapshot, $session_intelligence ) {
		if ( 'us_post_close' !== $this->session_type( $snapshot ) ) {
			return '';
		}
		$happened = is_array( $session_intelligence['what_happened'] ?? null ) ? $session_intelligence['what_happened'] : array();
		$change = $happened['btc_change_pct'] ?? null;
		$ending = sanitize_key( (string) ( $happened['ending_directional_bias'] ?? $snapshot['directional_bias'] ?? '' ) );
		if ( ! is_numeric( $change ) || ! in_array( $ending, array( 'bullish', 'neutral', 'bearish' ), true ) ) {
			return '';
		}
		return sprintf(
			__( 'Dalam 24 jam menuju brief ini, BTC bergerak %1$s dan pembacaan berakhir %2$s.', 'bitmomo-btc-intelligence' ),
			$this->format_return( $change ),
			$this->bias_label( $ending )
		);
	}

	private function why_it_matters_lines( $session_intelligence ) {
		$map = array(
			'directional_context_changed' => __( 'Arah dominan pasar berubah; konteks sebelumnya tidak lagi bisa dibaca dengan cara yang sama.', 'bitmomo-btc-intelligence' ),
			'evidence_strength_changed'   => __( 'Kekuatan bukti berubah; tingkat keyakinan terhadap pembacaan saat ini ikut bergeser.', 'bitmomo-btc-intelligence' ),
			'market_structure_changed'    => __( 'Struktur pasar berubah; respons harga berikutnya menjadi lebih penting untuk konfirmasi.', 'bitmomo-btc-intelligence' ),
			'leading_evidence_changed'    => __( 'Faktor utama berpindah; pendorong pembacaan saat ini tidak sama dengan brief pembanding.', 'bitmomo-btc-intelligence' ),
		);
		$lines = array();
		foreach ( (array) ( $session_intelligence['why_it_matters'] ?? array() ) as $code ) {
			$key = sanitize_key( (string) $code );
			if ( isset( $map[ $key ] ) && ! in_array( $map[ $key ], $lines, true ) ) {
				$lines[] = $map[ $key ];
			}
			if ( count( $lines ) >= 2 ) {
				break;
			}
		}
		return $lines;
	}

	private function public_watch_line( $session_intelligence ) {
		$allowlist = array(
			'directional_consistency' => __( 'Apakah kekuatan arah tetap konsisten pada pembacaan canonical berikutnya.', 'bitmomo-btc-intelligence' ),
			'structure_continuity'    => __( 'Apakah struktur harga tetap mendukung bias saat ini.', 'bitmomo-btc-intelligence' ),
		);
		foreach ( (array) ( $session_intelligence['what_to_watch'] ?? array() ) as $code ) {
			$key = sanitize_key( (string) $code );
			if ( isset( $allowlist[ $key ] ) ) {
				return $allowlist[ $key ];
			}
		}
		return '';
	}

	public function render_page( $atts ) {
		ob_start();
		echo '<div class="bm-bi">';
		$this->render_hero();
		$this->render_section_nav();
		$this->render_current_snapshot();
		$this->render_history();
		$this->render_decision_ledger();
		$this->render_track_record();
		$this->render_delayed_proof();
		$this->render_methodology();
		$this->render_pro_cta();
		echo '</div>';
		return ob_get_clean();
	}

	private function render_hero() {
		?>
		<section class="bm-bi__hero">
			<div>
				<p class="bm-bi__eyebrow">BITMOMO · BTC INTELLIGENCE</p>
				<h1><?php esc_html_e( 'BTC Intelligence', 'bitmomo-btc-intelligence' ); ?></h1>
				<p><?php esc_html_e( 'Market Pulse menunjukkan aktivitas intraday dengan clock yang cepat. Major Brief menunjukkan bias, apa yang berubah, mengapa penting, dan satu hal yang layak dipantau. Decision Ledger menunjukkan apa yang benar-benar terjadi sesudahnya.', 'bitmomo-btc-intelligence' ); ?></p>
			</div>
			<small><?php esc_html_e( 'Evidence before narrative · bukan sinyal beli/jual · bukan saran keuangan', 'bitmomo-btc-intelligence' ); ?></small>
		</section>
		<?php
	}

	private function render_section_nav() {
		?>
		<nav class="bm-bi__rail" aria-label="<?php esc_attr_e( 'Navigasi BTC Intelligence', 'bitmomo-btc-intelligence' ); ?>">
			<a href="#btc-now"><?php esc_html_e( 'Sekarang', 'bitmomo-btc-intelligence' ); ?></a>
			<a href="#btc-30d">30D</a>
			<a href="#decision-ledger"><?php esc_html_e( 'Decision Ledger', 'bitmomo-btc-intelligence' ); ?></a>
			<a href="#pro-archive"><?php esc_html_e( 'Arsip Pro', 'bitmomo-btc-intelligence' ); ?></a>
		</nav>
		<?php
	}

	private function render_current_snapshot() {
		$snapshot = $this->adapter_snapshot();
		$surface = $this->adapter_surface_context();
		$available = is_array( $snapshot );
		$freshness = $available && is_array( $snapshot['freshness'] ?? null ) ? $snapshot['freshness'] : array();
		$freshness_state = $available ? sanitize_key( (string) ( $freshness['state'] ?? $snapshot['status'] ?? '' ) ) : '';
		$is_delayed = $available && 'delayed' === $freshness_state;
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
		$session_label = $available ? $this->session_label( $snapshot ) : '';
		$session_anchor = $available ? $this->session_anchor_label( $snapshot ) : '';
		$pulse_line = $this->market_pulse_line( $opportunity );
		$happened = $available ? $this->what_happened_summary( $snapshot, $session_intelligence ) : '';
		$why_lines = $available ? $this->why_it_matters_lines( $session_intelligence ) : array();
		$watch_line = $available ? $this->public_watch_line( $session_intelligence ) : '';
		?>
		<section id="btc-now" class="bm-bi__snapshot" aria-labelledby="bm-bi-current-title">
			<div class="bm-bi__section-head bm-bi__section-head--terminal">
				<div>
					<p class="bm-bi__eyebrow"><?php echo esc_html( $session_label ? 'MAJOR BRIEF · ' . $session_label : 'CURRENT MARKET BRIEF' ); ?></p>
					<h2 id="bm-bi-current-title"><?php esc_html_e( 'BTC sekarang', 'bitmomo-btc-intelligence' ); ?></h2>
					<?php if ( $session_anchor && ! $is_delayed ) : ?><small class="bm-bi__section-kicker"><?php echo esc_html( sprintf( __( 'Anchor sesi · %s', 'bitmomo-btc-intelligence' ), $session_anchor ) ); ?></small><?php endif; ?>
				</div>
				<div class="bm-bi__headline-meta">
					<?php if ( null !== $price && ! $is_delayed ) : ?><strong><?php echo esc_html( $this->format_price( $price ) ); ?></strong><?php endif; ?>
					<?php if ( $available ) $this->render_freshness( $snapshot ); ?>
				</div>
			</div>

			<?php $this->render_market_pulse( $activity, $pulse_line ); ?>

			<?php if ( $is_delayed ) : ?>
				<?php $this->render_delayed_current_boundary( $snapshot, $price ); ?>
			<?php elseif ( ! $available || ! $direction_valid ) : ?>
				<?php $this->render_blocked_boundary( __( 'Pembacaan arah sedang ditahan karena Major Brief belum memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__snapshot-grid">
					<div><span>BIAS</span><strong class="is-<?php echo esc_attr( $bias ); ?>"><?php echo esc_html( $this->direction_label( $strength ) ); ?></strong><small><?php esc_html_e( 'arah dominan data pasar', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div><span>CONFIDENCE</span><strong><?php echo esc_html( null !== $confidence ? $confidence . '/100' . ( $confidence_label ? ' · ' . $confidence_label : '' ) : '—' ); ?></strong><small><?php esc_html_e( 'konsistensi bukti; bukan probabilitas pergerakan harga', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div><span>MAJOR BRIEF</span><strong><?php echo esc_html( $session_label ?: __( 'Canonical brief', 'bitmomo-btc-intelligence' ) ); ?></strong><small><?php echo esc_html( $session_anchor ? sprintf( __( 'Anchor sesi · %s', 'bitmomo-btc-intelligence' ), $session_anchor ) : __( 'Clock sesi terpisah dari Market Pulse', 'bitmomo-btc-intelligence' ) ); ?></small></div>
				</div>

				<div class="bm-bi__brief-grid">
					<div class="bm-bi__brief-panel">
						<h3><?php echo esc_html( 'us_post_close' === $this->session_type( $snapshot ) ? __( 'APA YANG TERJADI', 'bitmomo-btc-intelligence' ) : __( 'CURRENT SETUP', 'bitmomo-btc-intelligence' ) ); ?></h3>
						<?php if ( $happened ) : ?><p><?php echo esc_html( $happened ); ?></p><?php endif; ?>
						<?php if ( $drivers ) : ?><ul><?php foreach ( $drivers as $driver ) : ?><li><?php echo esc_html( $driver ); ?></li><?php endforeach; ?></ul><?php elseif ( ! $happened ) : ?><p>—</p><?php endif; ?>
					</div>
					<div class="bm-bi__brief-panel">
						<h3><?php esc_html_e( 'APA YANG BERUBAH?', 'bitmomo-btc-intelligence' ); ?></h3>
						<?php $this->render_what_changed( $session_intelligence, true ); ?>
					</div>
					<div class="bm-bi__brief-panel">
						<h3><?php esc_html_e( 'MENGAPA PENTING', 'bitmomo-btc-intelligence' ); ?></h3>
						<?php if ( $why_lines ) : ?><ul><?php foreach ( $why_lines as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'Belum ada perubahan material yang membutuhkan penjelasan tambahan.', 'bitmomo-btc-intelligence' ); ?></p><?php endif; ?>
					</div>
					<div class="bm-bi__brief-panel">
						<h3><?php esc_html_e( 'PANTAU BERIKUTNYA', 'bitmomo-btc-intelligence' ); ?></h3>
						<?php if ( $watch_line ) : ?><p><?php echo esc_html( $watch_line ); ?></p><?php else : ?><p><?php esc_html_e( 'Belum ada konteks pantauan publik yang memenuhi kontrak saat ini.', 'bitmomo-btc-intelligence' ); ?></p><?php endif; ?>
					</div>
				</div>
				<?php $this->render_provenance( $snapshot ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function changed_lines( $session_intelligence ) {
		$comparison = is_array( $session_intelligence['comparison'] ?? null ) ? $session_intelligence['comparison'] : array();
		$changes = is_array( $session_intelligence['what_changed'] ?? null ) ? $session_intelligence['what_changed'] : array();
		if ( ( $comparison['status'] ?? '' ) !== 'compared' || ! $changes ) {
			return array();
		}
		$lines = array();
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) ) continue;
			$field = sanitize_key( (string) ( $change['field'] ?? '' ) );
			if ( 'directional_bias' === $field ) {
				$lines[] = sprintf( __( 'Bias berubah dari %s menjadi %s.', 'bitmomo-btc-intelligence' ), $this->bias_label( $change['from'] ?? '' ), $this->bias_label( $change['to'] ?? '' ) );
			} elseif ( 'direction_strength' === $field ) {
				$lines[] = sprintf( __( 'Kekuatan arah berubah dari %s menjadi %s.', 'bitmomo-btc-intelligence' ), $this->direction_label( $change['from'] ?? '' ), $this->direction_label( $change['to'] ?? '' ) );
			} elseif ( 'confidence' === $field ) {
				$from = max( 0, min( 100, (int) ( $change['from'] ?? 0 ) ) );
				$to = max( 0, min( 100, (int) ( $change['to'] ?? 0 ) ) );
				$lines[] = sprintf( __( 'Confidence berubah dari %d menjadi %d.', 'bitmomo-btc-intelligence' ), $from, $to );
			} elseif ( 'opportunity_state' === $field ) {
				$map = array( 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah' );
				$from = isset( $map[ sanitize_key( (string) ( $change['from'] ?? '' ) ) ] ) ? $map[ sanitize_key( (string) ( $change['from'] ?? '' ) ) ] : '';
				$to = isset( $map[ sanitize_key( (string) ( $change['to'] ?? '' ) ) ] ) ? $map[ sanitize_key( (string) ( $change['to'] ?? '' ) ) ] : '';
				if ( $from && $to ) $lines[] = sprintf( __( 'Aktivitas pasar berubah dari %s menjadi %s.', 'bitmomo-btc-intelligence' ), $from, $to );
			}
			if ( count( $lines ) >= 2 ) break;
		}
		return $lines;
	}

	private function render_what_changed( $session_intelligence, $compact = false ) {
		$lines = $this->changed_lines( $session_intelligence );
		if ( ! $lines ) {
			echo $compact ? '<p class="bm-bi__no-change">' . esc_html__( 'Belum ada perubahan material dari pembacaan pembanding.', 'bitmomo-btc-intelligence' ) . '</p>' : '';
			return;
		}
		if ( $compact ) {
			echo '<ul>';
			foreach ( $lines as $line ) echo '<li>' . esc_html( $line ) . '</li>';
			echo '</ul>';
			return;
		}
		?>
		<div class="bm-bi__changes"><h3><?php esc_html_e( 'Apa yang berubah?', 'bitmomo-btc-intelligence' ); ?></h3><ul><?php foreach ( $lines as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul></div>
		<?php
	}

	private function render_provenance( $snapshot ) {
		$provenance = is_array( $snapshot['provenance'] ?? null ) ? $snapshot['provenance'] : array();
		$source = trim( (string) ( $provenance['source'] ?? '' ) );
		$as_of = trim( (string) ( $provenance['as_of'] ?? '' ) );
		$exact = $this->format_wib( $as_of );
		?>
		<div class="bm-bi__provenance">
			<?php if ( $exact ) : ?><time datetime="<?php echo esc_attr( $as_of ); ?>"><?php echo esc_html( $exact ); ?></time><?php endif; ?>
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
		<section id="btc-30d" class="bm-bi__section bm-bi__history" aria-labelledby="bm-bi-history-title">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">30D STATE TAPE</p><h2 id="bm-bi-history-title"><?php esc_html_e( 'Konteks 30 hari', 'bitmomo-btc-intelligence' ); ?></h2></div><small class="bm-bi__section-kicker"><?php esc_html_e( 'Satu catatan resmi / market day', 'bitmomo-btc-intelligence' ); ?></small></div>
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
				<div class="bm-bi__history-summary"><span class="is-bullish"><?php echo esc_html( sprintf( 'Bullish %d', $counts['bullish'] ) ); ?></span><span class="is-neutral"><?php echo esc_html( sprintf( 'Netral %d', $counts['neutral'] ) ); ?></span><span class="is-bearish"><?php echo esc_html( sprintf( 'Bearish %d', $counts['bearish'] ) ); ?></span><small><?php echo esc_html( sprintf( __( '%d hari tersedia', 'bitmomo-btc-intelligence' ), count( $days ) ) ); ?></small></div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_decision_ledger() {
		$ledger = $this->accountability_ledger();
		$rows = is_array( $ledger['rows'] ?? null ) ? $ledger['rows'] : array();
		?>
		<section id="decision-ledger" class="bm-bi__section bm-bi__ledger" aria-labelledby="bm-bi-ledger-title">
			<div class="bm-bi__section-head">
				<div><p class="bm-bi__eyebrow">DECISION LEDGER</p><h2 id="bm-bi-ledger-title"><?php esc_html_e( 'Apa yang kami katakan. Apa yang terjadi.', 'bitmomo-btc-intelligence' ); ?></h2></div>
				<?php if ( $rows ) : ?><span class="bm-bi__audit-badge"><?php esc_html_e( 'NO CHERRY-PICKING', 'bitmomo-btc-intelligence' ); ?></span><?php endif; ?>
			</div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Catatan terbaru yang sudah matang. Keputusan yang sesuai, tidak sesuai, tidak konklusif, dan window evaluasi yang terlewat diperlakukan dengan aturan yang sama.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( ! $rows ) : ?>
				<?php $this->render_blocked_boundary( __( 'Belum ada outcome matang yang dapat dipublikasikan ke Decision Ledger.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__ledger-wrap" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Decision Ledger BTC', 'bitmomo-btc-intelligence' ); ?>">
					<table class="bm-bi__ledger-table">
						<thead><tr><th>WAKTU</th><th>VIEW</th><th>CONF.</th><th>BTC</th><th>+24H</th><th>HASIL</th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $row ) :
							$verdict = sanitize_key( (string) ( $row['verdict'] ?? 'unscored' ) );
							$direction = sanitize_key( (string) ( $row['direction'] ?? '' ) );
							$date = $this->format_wib( $row['generated_at'] ?? '', 'd M · H:i' );
							$return = $row['forward_return_pct'] ?? null;
							$return_class = is_numeric( $return ) ? ( (float) $return > 0 ? 'is-positive' : ( (float) $return < 0 ? 'is-negative' : 'is-flat' ) ) : '';
							?>
							<tr>
								<td><time datetime="<?php echo esc_attr( (string) ( $row['generated_at'] ?? '' ) ); ?>"><?php echo esc_html( $date ?: '—' ); ?></time><small><?php echo esc_html( (string) ( $row['session'] ?? '' ) ); ?></small></td>
								<td><strong class="is-<?php echo esc_attr( $direction ); ?>"><?php echo esc_html( $this->bias_label( $direction ) ); ?></strong></td>
								<td><?php echo esc_html( isset( $row['confidence'] ) ? (int) $row['confidence'] . '/100' : '—' ); ?></td>
								<td><?php echo esc_html( $this->format_price( $row['reference_price'] ?? null ) ); ?></td>
								<td class="<?php echo esc_attr( $return_class ); ?>"><?php echo esc_html( $this->format_return( $return ) ); ?></td>
								<td><span class="bm-bi__verdict is-<?php echo esc_attr( $verdict ); ?>"><?php echo esc_html( $this->verdict_label( $verdict ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="bm-bi__micro-note"><?php esc_html_e( 'Evaluasi arah menggunakan outcome +24 jam. “Belum dinilai” berarti window settlement terlewat—record tetap ditampilkan, bukan dibuang.', 'bitmomo-btc-intelligence' ); ?></p>
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
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">AGGREGATE SCORECARD</p><h2 id="bm-bi-track-title"><?php esc_html_e( 'Track record', 'bitmomo-btc-intelligence' ); ?></h2></div><small class="bm-bi__section-kicker"><?php esc_html_e( 'Metodologi aktif saja', 'bitmomo-btc-intelligence' ); ?></small></div>
			<?php if ( ! is_array( $current ) || empty( $current ) || empty( $current['all']['n'] ) ) : ?>
				<?php $this->render_blocked_boundary( __( 'Belum ada cukup outcome yang layak untuk diringkas.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<p class="bm-bi__section-intro"><?php esc_html_e( 'Jumlah outcome konklusif ditampilkan lebih dulu. Persentase akurasi hanya ditampilkan sebagai statistik sementara setelah minimum sampel tercapai, dan baru dianggap memadai setelah ambang sampel kuat.', 'bitmomo-btc-intelligence' ); ?></p>
				<div class="bm-bi__proof-grid">
					<?php $this->render_metric( __( 'Keseluruhan', 'bitmomo-btc-intelligence' ), $current['all'] ?? array() ); ?>
					<?php $this->render_metric( __( '30 terakhir', 'bitmomo-btc-intelligence' ), $current['rolling_30'] ?? array() ); ?>
					<?php $directions = is_array( $current['by_direction'] ?? null ) ? $current['by_direction'] : array(); ?>
					<?php $this->render_metric( 'Bullish', $directions['bullish'] ?? array() ); ?>
					<?php $this->render_metric( 'Bearish', $directions['bearish'] ?? array() ); ?>
				</div>
				<p class="bm-bi__micro-note"><?php esc_html_e( 'Outcome dinilai tepat +24 jam dari observation time. Untuk Bullish/Bearish, gerak antara -0,5% dan +0,5% dianggap inconclusive dan tidak masuk denominator akurasi.', 'bitmomo-btc-intelligence' ); ?></p>
				<?php if ( count( $versions ) > 1 ) : ?><p class="bm-bi__history-policy"><?php esc_html_e( 'Metodologi lama tetap disimpan untuk audit tetapi tidak dicampur dengan angka di atas.', 'bitmomo-btc-intelligence' ); ?></p><?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_delayed_proof() {
		$proof = $this->accountability_delayed_proof();
		$rows = is_array( $proof['rows'] ?? null ) ? $proof['rows'] : array();
		$delay = isset( $proof['delay_hours'] ) ? max( 0, (int) $proof['delay_hours'] ) : 48;
		?>
		<section id="pro-archive" class="bm-bi__section bm-bi__pro-archive" aria-labelledby="bm-bi-pro-archive-title">
			<div class="bm-bi__section-head">
				<div><p class="bm-bi__eyebrow">FROM THE PRO ARCHIVE</p><h2 id="bm-bi-pro-archive-title"><?php esc_html_e( 'Lihat decision support yang sudah kedaluwarsa.', 'bitmomo-btc-intelligence' ); ?></h2></div>
				<span class="bm-bi__delay-badge"><?php echo esc_html( sprintf( __( 'DELAY ≥ %d JAM', 'bitmomo-btc-intelligence' ), $delay ) ); ?></span>
			</div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Ini bukan preview buatan. Ini brief Pro yang benar-benar pernah dipublikasikan, dibekukan saat itu juga, lalu baru dibuka ke publik setelah nilainya tidak lagi time-sensitive.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( ! $rows ) : ?>
				<?php $this->render_blocked_boundary( __( 'Belum ada brief Pro historis yang memenuhi delay dan settlement gate.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__archive-list">
				<?php foreach ( $rows as $row ) :
					$state = sanitize_key( (string) ( $row['market_state'] ?? '' ) );
					$verdict = sanitize_key( (string) ( $row['verdict'] ?? 'unscored' ) );
					$published = $this->format_wib( $row['published_at'] ?? '', 'd M Y · H:i' );
					$return = $row['outcome_return_pct'] ?? null;
					?>
					<article class="bm-bi__archive-card">
						<header class="bm-bi__archive-head">
							<div><span><?php echo esc_html( $published ?: '—' ); ?></span><strong class="is-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $this->bias_label( $state ) ); ?></strong></div>
							<span class="bm-bi__verdict is-<?php echo esc_attr( $verdict ); ?>"><?php echo esc_html( $this->verdict_label( $verdict ) ); ?></span>
						</header>
						<div class="bm-bi__archive-metrics">
							<div><span>BTC REFERENSI</span><strong><?php echo esc_html( $this->format_price( $row['reference_price'] ?? null ) ); ?></strong></div>
							<div><span>EXPECTED RANGE</span><strong><?php echo esc_html( $this->format_price( $row['expected_range_low'] ?? null ) . ' – ' . $this->format_price( $row['expected_range_high'] ?? null ) ); ?></strong></div>
							<div><span>CONFIDENCE</span><strong><?php echo esc_html( isset( $row['confidence'] ) ? (int) $row['confidence'] . '/100' : '—' ); ?></strong></div>
							<div><span>OUTCOME +24H</span><strong><?php echo esc_html( $this->format_return( $return ) ); ?></strong></div>
						</div>
						<div class="bm-bi__archive-thesis"><span>BASE CASE</span><p><?php echo esc_html( (string) ( $row['base_scenario'] ?? '' ) ); ?></p></div>
						<div class="bm-bi__archive-thesis bm-bi__archive-thesis--invalidate"><span>INVALIDATION</span><p><?php echo esc_html( (string) ( $row['invalidation'] ?? '' ) ); ?></p></div>
						<details class="bm-bi__archive-details">
							<summary><?php esc_html_e( 'Lihat skenario historis lengkap', 'bitmomo-btc-intelligence' ); ?></summary>
							<div class="bm-bi__archive-scenarios">
								<?php if ( ! empty( $row['bull_scenario'] ) ) : ?><div><span>BULL</span><p><?php echo esc_html( (string) $row['bull_scenario'] ); ?></p></div><?php endif; ?>
								<?php if ( ! empty( $row['bear_scenario'] ) ) : ?><div><span>BEAR</span><p><?php echo esc_html( (string) $row['bear_scenario'] ); ?></p></div><?php endif; ?>
								<?php if ( ! empty( $row['what_changed'] ) ) : ?><div><span>WHAT CHANGED</span><p><?php echo esc_html( (string) $row['what_changed'] ); ?></p></div><?php endif; ?>
							</div>
						</details>
						<footer class="bm-bi__archive-outcome"><span><?php echo esc_html( 'Range hit: ' . ( 'yes' === ( $row['range_hit'] ?? '' ) ? 'YA' : ( 'no' === ( $row['range_hit'] ?? '' ) ? 'TIDAK' : '—' ) ) ); ?></span><span><?php esc_html_e( 'Arsip historis · bukan guidance saat ini', 'bitmomo-btc-intelligence' ); ?></span></footer>
					</article>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_methodology() {
		?>
		<section class="bm-bi__section bm-bi__methodology">
			<details class="bm-bi__details">
				<summary><?php esc_html_e( 'Metodologi & aturan akuntabilitas', 'bitmomo-btc-intelligence' ); ?></summary>
				<div class="bm-bi__details-body">
					<p><?php esc_html_e( 'Bitmomo merangkum price action, volatilitas, struktur pasar, serta kondisi derivatif menjadi Bias, Confidence, dan Activity.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Bias menunjukkan arah dominan data pasar. Confidence mengukur konsistensi bukti; bukan probabilitas pergerakan harga. Activity mengukur intensitas pergerakan, bukan arah.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Market Pulse menggunakan candle 5 menit dan evaluasi canonical 15 menit dengan freshness intraday tersendiri. Major Brief memakai clock yang terpisah: 08:10 dan 20:10 America/New_York, menyesuaikan daylight-saving. Staleness pada salah satu clock tidak boleh membuat clock lain terlihat lebih fresh atau lebih stale daripada keadaan sebenarnya.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Decision Ledger hanya mengambil catatan recorded-live yang outcome-nya sudah matang. Record yang tidak sesuai atau window evaluasinya terlewat tetap dapat muncul; filter tidak bergantung pada hasil.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Arsip Pro hanya membaca snapshot yang dibekukan ketika brief dipublikasikan. Brief saat ini dan brief yang belum melewati delay publik tidak dapat muncul di sini.', 'bitmomo-btc-intelligence' ); ?></p>
				</div>
			</details>
		</section>
		<?php
	}

	private function render_pro_cta() {
		?>
		<section class="bm-bi__pro-cta">
			<div>
				<p class="bm-bi__eyebrow">BITMOMO PRO</p>
				<h2><?php esc_html_e( 'Gratis membantu memahami kondisi, perubahan, makna, dan satu konteks pantauan. Pro membuka decision support yang lebih dalam.', 'bitmomo-btc-intelligence' ); ?></h2>
				<p><?php esc_html_e( 'Pro menambahkan monitoring lengkap, skenario, expected range, dan invalidation ketika metodologinya memenuhi standar publikasi. Founding whitelist membuka akses secara bertahap.', 'bitmomo-btc-intelligence' ); ?></p>
			</div>
			<a class="bm-bi__cta-primary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
		</section>
		<?php
	}
}

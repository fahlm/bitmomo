<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public BTC Intelligence page.
 *
 * The launch-critical reading path answers, in order:
 * 1) is BTC active enough to matter right now (Opportunity),
 * 2) where does current evidence lean and how coherent is it,
 * 3) what changed since the preceding canonical edition,
 * 4) what has the system recorded historically and how has it performed.
 *
 * Forward-looking monitoring conditions, scenarios, invalidation and other
 * Pro-only fields never render on this public surface.
 */
class Bitmomo_Btc_Intelligence_Page {

	private static $instance = null;
	private $adapter_snapshot = null;
	private $adapter_snapshot_loaded = false;
	private $surface_context = null;
	private $surface_context_loaded = false;
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
			? __( 'Kondisi BTC saat ini, Opportunity, Directional Bias, perubahan konteks, riwayat 30 hari, dan track record Bitmomo Intelligence.', 'bitmomo-btc-intelligence' )
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
			'accumulation' => 'Akumulasi', 'expansion' => 'Ekspansi', 'distribution' => 'Distribusi',
			'capitulation' => 'Kapitulasi', 'transition' => 'Transisi',
		);
		return is_string( $value ) && isset( $labels[ $value ] ) ? $labels[ $value ] : __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function direction_display_label( $value ) {
		$labels = array(
			'strong_bearish' => 'Strong Bearish', 'bearish' => 'Bearish', 'neutral' => 'Neutral',
			'bullish' => 'Bullish', 'strong_bullish' => 'Strong Bullish',
		);
		return $labels[ sanitize_key( (string) $value ) ] ?? __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function bias_display_label( $value ) {
		$labels = array( 'bullish' => 'Bullish', 'bearish' => 'Bearish', 'neutral' => 'Neutral' );
		return $labels[ sanitize_key( (string) $value ) ] ?? __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
	}

	private function opportunity_display( $opportunity ) {
		$opportunity = is_array( $opportunity ) ? $opportunity : array();
		$state = strtolower( sanitize_key( (string) ( $opportunity['state'] ?? '' ) ) );
		$available = 'available' === sanitize_key( (string) ( $opportunity['status'] ?? '' ) ) && in_array( $state, array( 'high', 'normal', 'low' ), true );
		$labels = array( 'high' => 'HIGH', 'normal' => 'NORMAL', 'low' => 'LOW' );
		$copy = array(
			'high' => __( 'Aktivitas 60 menit berada di bagian atas distribusi 14 hari. BTC sedang lebih aktif dibanding kondisi normalnya.', 'bitmomo-btc-intelligence' ),
			'normal' => __( 'Aktivitas 60 menit berada di sekitar bagian tengah distribusi 14 hari.', 'bitmomo-btc-intelligence' ),
			'low' => __( 'Aktivitas 60 menit berada di bagian bawah distribusi 14 hari. BTC sedang relatif tenang.', 'bitmomo-btc-intelligence' ),
		);
		return array(
			'available' => $available,
			'state' => $state,
			'label' => $available ? $labels[ $state ] : __( 'Belum tersedia', 'bitmomo-btc-intelligence' ),
			'copy' => $available ? $copy[ $state ] : __( 'Menunggu data 5 menit dan baseline 14 hari yang memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ),
			'previous' => strtolower( sanitize_key( (string) ( $opportunity['previous_state'] ?? '' ) ) ),
			'changed' => $available && ! empty( $opportunity['changed'] ),
			'knowledge_time' => sanitize_text_field( (string) ( $opportunity['knowledge_time'] ?? '' ) ),
			'activity_percentile' => isset( $opportunity['activity_percentile'] ) && is_numeric( $opportunity['activity_percentile'] ) ? max( 0, min( 100, (float) $opportunity['activity_percentile'] ) ) : null,
			'range_60m_pct' => isset( $opportunity['range_60m_pct'] ) && is_numeric( $opportunity['range_60m_pct'] ) ? max( 0, (float) $opportunity['range_60m_pct'] ) : null,
		);
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
		$state = sanitize_key( (string) ( $freshness['state'] ?? $snapshot['status'] ?? '' ) );
		$iso = (string) ( $freshness['timestamp_iso'] ?? '' );
		$timestamp = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $iso ) ? strtotime( $iso ) : false;
		if ( ! $timestamp ) {
			echo '<span class="bm-bi__freshness is-unavailable">' . esc_html__( 'Waktu belum tersedia', 'bitmomo-btc-intelligence' ) . '</span>';
			return;
		}
		$exact = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB';
		$label = 'delayed' === $state ? __( 'DATA TERTUNDA', 'bitmomo-btc-intelligence' ) : __( 'UPDATE', 'bitmomo-btc-intelligence' );
		echo '<span class="bm-bi__freshness is-' . esc_attr( $state ?: 'fresh' ) . '"><strong>' . esc_html( $label ) . '</strong><time datetime="' . esc_attr( $iso ) . '">' . esc_html( $exact ) . '</time></span>';
	}

	private function render_public_metric( $label, $metric, $value_field = 'accuracy_pct', $suffix = '%' ) {
		$metric = is_array( $metric ) ? $metric : array();
		$value = array_key_exists( $value_field, $metric ) ? $metric[ $value_field ] : null;
		$n = isset( $metric['n'] ) ? (int) $metric['n'] : 0;
		$conclusive = isset( $metric['conclusive_n'] ) ? (int) $metric['conclusive_n'] : null;
		$status = (string) ( $metric['sample_status'] ?? '' );
		$sample_copy = 'accuracy_pct' === $value_field && null !== $conclusive
			? sprintf( __( '%d konklusif · %d total · %s', 'bitmomo-btc-intelligence' ), $conclusive, $n, $this->public_sample_status_label( $status ) )
			: sprintf( __( 'n=%d · %s', 'bitmomo-btc-intelligence' ), $n, $this->public_sample_status_label( $status ) );
		?>
		<div class="bm-bi__proof-metric">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo null !== $value && is_numeric( $value ) ? esc_html( number_format_i18n( (float) $value, 1 ) . $suffix ) : esc_html__( '—', 'bitmomo-btc-intelligence' ); ?></strong>
			<small><?php echo esc_html( $sample_copy ); ?></small>
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
			<h1><?php esc_html_e( 'Pahami kondisi BTC tanpa membaca semuanya sendiri.', 'bitmomo-btc-intelligence' ); ?></h1>
			<p><?php esc_html_e( 'Aktivitas pasar, arah evidence, perubahan konteks, riwayat 30 hari, dan track record — dari satu sistem yang sama.', 'bitmomo-btc-intelligence' ); ?></p>
			<small><?php esc_html_e( 'Bukan sinyal beli/jual. Bukan saran keuangan.', 'bitmomo-btc-intelligence' ); ?></small>
		</section>
		<?php
	}

	private function render_current_snapshot() {
		$snapshot = $this->adapter_snapshot();
		$surface = $this->adapter_surface_context();
		$available = is_array( $snapshot );
		$opportunity = $available && is_array( $snapshot['opportunity'] ?? null ) ? $snapshot['opportunity'] : ( is_array( $surface['opportunity'] ?? null ) ? $surface['opportunity'] : array() );
		$opp = $this->opportunity_display( $opportunity );
		$raw_bias = $available ? sanitize_key( (string) ( $snapshot['directional_bias'] ?? '' ) ) : '';
		$raw_strength = $available ? sanitize_key( (string) ( $snapshot['direction_strength'] ?? '' ) ) : '';
		$strength_zone_index = array( 'strong_bearish' => 0, 'bearish' => 1, 'neutral' => 2, 'bullish' => 3, 'strong_bullish' => 4 );
		$resolved = $available && in_array( $raw_bias, array( 'bullish', 'bearish', 'neutral' ), true ) && isset( $strength_zone_index[ $raw_strength ] );
		$confidence = $resolved && is_array( $snapshot['confidence'] ?? null ) ? $snapshot['confidence'] : array();
		$confidence_value = isset( $confidence['value'] ) ? max( 0, min( 100, (int) $confidence['value'] ) ) : null;
		$confidence_labels = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
		$confidence_label = $confidence_labels[ sanitize_key( (string) ( $confidence['label'] ?? '' ) ) ] ?? __( 'Belum tersedia', 'bitmomo-btc-intelligence' );
		$market_state = $resolved ? sanitize_key( (string) ( $snapshot['market_state'] ?? '' ) ) : '';
		$state_label = $this->regime_display_label( $market_state );
		$state_certainty = $resolved && isset( $snapshot['market_state_certainty'] ) && is_numeric( $snapshot['market_state_certainty'] ) ? max( 0, min( 100, (int) $snapshot['market_state_certainty'] ) ) : null;
		$drivers = $resolved && is_array( $snapshot['key_drivers'] ?? null ) ? array_slice( array_values( array_filter( array_map( 'strval', $snapshot['key_drivers'] ) ) ), 0, 3 ) : array();
		$session = $resolved && is_array( $snapshot['session'] ?? null ) ? $snapshot['session'] : array();
		$session_label = trim( (string) ( $session['label'] ?? '' ) );
		$session_intelligence = $resolved && is_array( $snapshot['session_intelligence'] ?? null ) ? $snapshot['session_intelligence'] : array();
		?>
		<section class="bm-bi__snapshot" aria-labelledby="bm-bi-current-title">
			<div class="bm-bi__section-head">
				<div><p class="bm-bi__eyebrow"><?php echo esc_html( $session_label ?: 'KONDISI BTC SAAT INI' ); ?></p><h2 id="bm-bi-current-title"><?php esc_html_e( 'Current intelligence', 'bitmomo-btc-intelligence' ); ?></h2></div>
				<?php if ( $resolved ) $this->render_freshness( $snapshot ); ?>
			</div>

			<?php $this->render_opportunity( $opp ); ?>

			<?php if ( ! $resolved ) : ?>
				<?php $this->render_blocked_boundary( __( 'Directional snapshot sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<div class="bm-bi__snapshot-grid">
					<div><span>BTC</span><strong>$<?php echo esc_html( number_format_i18n( (float) ( $snapshot['btc_reference_price'] ?? 0 ), 0 ) ); ?></strong><small><?php esc_html_e( 'reference close', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div><span>BIAS</span><strong class="is-<?php echo esc_attr( $raw_bias ); ?>"><?php echo esc_html( $this->direction_display_label( $raw_strength ) ); ?></strong><small><?php esc_html_e( 'arah evidence', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div class="bm-bi__confidence-metric"><span>CONFIDENCE</span><strong><?php echo null !== $confidence_value ? esc_html( $confidence_value . '/100 · ' . $confidence_label ) : esc_html( $confidence_label ); ?></strong><div class="bm-bi__confidence-track" aria-hidden="true"><i style="--bm-confidence:<?php echo esc_attr( null !== $confidence_value ? $confidence_value : 0 ); ?>%"></i></div><small><?php esc_html_e( 'kekuatan & koherensi evidence', 'bitmomo-btc-intelligence' ); ?></small></div>
					<div><span>STATE</span><strong><?php echo esc_html( $state_label ); ?></strong><small><?php echo null !== $state_certainty ? esc_html( sprintf( __( '%d%% certainty', 'bitmomo-btc-intelligence' ), $state_certainty ) ) : esc_html__( 'classification pending', 'bitmomo-btc-intelligence' ); ?></small></div>
				</div>

				<div class="bm-bi__direction-rail" aria-label="Directional strength <?php echo esc_attr( $this->direction_display_label( $raw_strength ) ); ?>">
					<div class="bm-bi__direction-track" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><b class="is-<?php echo esc_attr( $raw_bias ); ?>" style="--bm-zone:<?php echo esc_attr( $strength_zone_index[ $raw_strength ] ); ?>"></b></div>
					<div class="bm-bi__direction-labels" aria-hidden="true"><span>Strong Bear</span><span>Neutral</span><span>Strong Bull</span></div>
				</div>

				<?php $this->render_what_happened( $session_intelligence ); ?>
				<?php $this->render_what_changed( $session_intelligence ); ?>

				<?php if ( $drivers ) : ?>
					<div class="bm-bi__drivers"><span>PRIMARY DRIVERS</span><ul><?php foreach ( $drivers as $driver ) : ?><li><?php echo esc_html( $driver ); ?></li><?php endforeach; ?></ul></div>
				<?php endif; ?>
				<p class="bm-bi__micro-note"><?php esc_html_e( 'Opportunity mengukur aktivitas, Bias mengukur arah evidence, dan Confidence mengukur kekuatan serta koherensi evidence — bukan probabilitas harga akan bergerak sesuai bias.', 'bitmomo-btc-intelligence' ); ?></p>
				<?php $this->render_provenance( $snapshot ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_opportunity( $opp ) {
		$labels = array( 'high' => 'HIGH', 'normal' => 'NORMAL', 'low' => 'LOW' );
		$changed = ! empty( $opp['changed'] ) && isset( $labels[ $opp['previous'] ], $labels[ $opp['state'] ] );
		$timestamp = ! empty( $opp['knowledge_time'] ) ? strtotime( $opp['knowledge_time'] ) : false;
		?>
		<div class="bm-bi__opportunity is-<?php echo esc_attr( $opp['available'] ? $opp['state'] : 'unavailable' ); ?>">
			<div class="bm-bi__opportunity-head">
				<div><span>OPPORTUNITY</span><strong><?php echo esc_html( $opp['label'] ); ?></strong></div>
				<span class="bm-bi__opportunity-tag"><?php esc_html_e( 'Aktivitas · bukan arah', 'bitmomo-btc-intelligence' ); ?></span>
			</div>
			<p><?php echo esc_html( $opp['copy'] ); ?></p>
			<div class="bm-bi__opportunity-facts">
				<?php if ( null !== $opp['activity_percentile'] ) : ?><span><strong><?php echo esc_html( number_format_i18n( $opp['activity_percentile'], 0 ) ); ?></strong><small>activity percentile</small></span><?php endif; ?>
				<?php if ( null !== $opp['range_60m_pct'] ) : ?><span><strong><?php echo esc_html( number_format_i18n( $opp['range_60m_pct'], 2 ) . '%' ); ?></strong><small>60m range</small></span><?php endif; ?>
				<?php if ( $changed ) : ?><span><strong><?php echo esc_html( $labels[ $opp['previous'] ] . ' → ' . $labels[ $opp['state'] ] ); ?></strong><small>state changed</small></span><?php endif; ?>
				<?php if ( $timestamp ) : ?><span><strong><?php echo esc_html( ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'H:i' ) . ' WIB' ); ?></strong><small>15m observation</small></span><?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_what_happened( $session_intelligence ) {
		$context = is_array( $session_intelligence['what_happened'] ?? null ) ? $session_intelligence['what_happened'] : array();
		$derivatives = is_array( $context['derivatives_context'] ?? null ) ? $context['derivatives_context'] : array();
		$items = array();
		if ( isset( $context['btc_change_pct'] ) && is_numeric( $context['btc_change_pct'] ) ) $items[] = array( 'BTC 24H', (float) $context['btc_change_pct'], 'pct' );
		if ( isset( $derivatives['open_interest_change_24h_pct'] ) && is_numeric( $derivatives['open_interest_change_24h_pct'] ) ) $items[] = array( 'OI 24H', (float) $derivatives['open_interest_change_24h_pct'], 'pct' );
		if ( isset( $derivatives['funding_rate'] ) && is_numeric( $derivatives['funding_rate'] ) ) $items[] = array( 'FUNDING', (float) $derivatives['funding_rate'] * 100, 'pct4' );
		if ( isset( $derivatives['basis_pct'] ) && is_numeric( $derivatives['basis_pct'] ) ) $items[] = array( 'BASIS', (float) $derivatives['basis_pct'], 'pct' );
		if ( ! $items ) return;
		?>
		<div class="bm-bi__observed">
			<div class="bm-bi__subhead"><span>OBSERVED 24H</span><small><?php esc_html_e( 'Konteks terukur, bukan prediksi.', 'bitmomo-btc-intelligence' ); ?></small></div>
			<div class="bm-bi__observed-grid">
				<?php foreach ( $items as $item ) :
					$digits = 'pct4' === $item[2] ? 4 : 2;
					$value = number_format_i18n( $item[1], $digits ) . '%'; ?>
					<div><span><?php echo esc_html( $item[0] ); ?></span><strong><?php echo esc_html( $value ); ?></strong></div>
				<?php endforeach; ?>
			</div>
		</div>
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
				$lines[] = sprintf( __( 'Bias berubah: %s → %s', 'bitmomo-btc-intelligence' ), $this->bias_display_label( $change['from'] ?? '' ), $this->bias_display_label( $change['to'] ?? '' ) );
			} elseif ( 'direction_strength' === $field ) {
				$lines[] = sprintf( __( 'Kekuatan arah: %s → %s', 'bitmomo-btc-intelligence' ), $this->direction_display_label( $change['from'] ?? '' ), $this->direction_display_label( $change['to'] ?? '' ) );
			} elseif ( 'confidence' === $field ) {
				$from = max( 0, min( 100, (int) ( $change['from'] ?? 0 ) ) );
				$to = max( 0, min( 100, (int) ( $change['to'] ?? 0 ) ) );
				$lines[] = sprintf( __( 'Confidence: %d → %d', 'bitmomo-btc-intelligence' ), $from, $to );
			} elseif ( 'strongest_driver' === $field ) {
				$to = sanitize_text_field( (string) ( $change['to'] ?? '' ) );
				if ( '' !== $to ) $lines[] = sprintf( __( 'Driver utama sekarang: %s', 'bitmomo-btc-intelligence' ), $to );
			} elseif ( 'opportunity_state' === $field ) {
				$from = strtoupper( sanitize_key( (string) ( $change['from'] ?? '' ) ) );
				$to = strtoupper( sanitize_key( (string) ( $change['to'] ?? '' ) ) );
				if ( $from && $to ) $lines[] = sprintf( __( 'Opportunity: %s → %s', 'bitmomo-btc-intelligence' ), $from, $to );
			}
			if ( count( $lines ) >= 3 ) break;
		}
		if ( ! $lines ) return;
		?>
		<div class="bm-bi__changes">
			<div class="bm-bi__subhead"><span>WHAT CHANGED</span><small><?php esc_html_e( 'Dibanding edition canonical sebelumnya.', 'bitmomo-btc-intelligence' ); ?></small></div>
			<ul><?php foreach ( $lines as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
		</div>
		<?php
	}

	private function render_provenance( $snapshot ) {
		$provenance = is_array( $snapshot['provenance'] ?? null ) ? $snapshot['provenance'] : array();
		$source = trim( (string) ( $provenance['source'] ?? '' ) );
		$as_of = trim( (string) ( $provenance['as_of'] ?? '' ) );
		$timestamp = $as_of ? strtotime( $as_of ) : false;
		?>
		<div class="bm-bi__provenance">
			<span><strong>SOURCE</strong> <?php echo esc_html( $source ?: __( 'Belum tersedia', 'bitmomo-btc-intelligence' ) ); ?></span>
			<?php if ( $timestamp ) : ?><time datetime="<?php echo esc_attr( $as_of ); ?>"><strong>AS OF</strong> <?php echo esc_html( ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB' ); ?></time><?php endif; ?>
		</div>
		<?php
	}

	private function render_historical_regime() {
		?>
		<section class="bm-bi__section bm-bi__history">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">30 HARI</p><h2><?php esc_html_e( 'Market context', 'bitmomo-btc-intelligence' ); ?></h2></div></div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Setiap batang adalah catatan resmi. Warna menunjukkan Directional Bias; tinggi menunjukkan certainty Market State. Hari tanpa record tidak diisi dengan data buatan.', 'bitmomo-btc-intelligence' ); ?></p>
			<div class="bm-bi__history-embed">
				<?php
				if ( shortcode_exists( 'bitmomo_market_regime_history' ) ) echo do_shortcode( '[bitmomo_market_regime_history days="30"]' );
				else $this->render_blocked_boundary( __( 'Riwayat Market State belum tersedia.', 'bitmomo-btc-intelligence' ) );
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
		$regime_performance = is_array( $summary['regime_performance'] ?? null ) ? $summary['regime_performance'] : array();
		$data_quality = is_array( $summary['data_quality'] ?? null ) ? $summary['data_quality'] : array();
		?>
		<section class="bm-bi__section bm-bi__track-record">
			<div class="bm-bi__section-head"><div><p class="bm-bi__eyebrow">ACCOUNTABILITY</p><h2><?php esc_html_e( 'Track record', 'bitmomo-btc-intelligence' ); ?></h2></div></div>
			<p class="bm-bi__section-intro"><?php esc_html_e( 'Hasil dibekukan sebelum outcome diketahui. Versi engine yang tidak kompatibel tetap dipisahkan, bukan dicampur menjadi satu angka.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php if ( empty( $versions ) ) : ?>
				<?php $this->render_blocked_boundary( __( 'Data evaluasi publik belum cukup untuk diringkas.', 'bitmomo-btc-intelligence' ) ); ?>
			<?php else : ?>
				<?php foreach ( $versions as $version => $metrics ) : ?>
					<div class="bm-bi__proof-group">
						<?php if ( count( $versions ) > 1 ) : ?><small>VERSION <?php echo esc_html( (string) $version ); ?></small><?php endif; ?>
						<div class="bm-bi__proof-grid bm-bi__proof-grid--primary">
							<?php $this->render_public_metric( __( 'Semua waktu', 'bitmomo-btc-intelligence' ), $metrics['all'] ?? array() ); ?>
							<?php $this->render_public_metric( __( 'Rolling 30', 'bitmomo-btc-intelligence' ), $metrics['rolling_30'] ?? array() ); ?>
							<?php $by_direction = is_array( $metrics['by_direction'] ?? null ) ? $metrics['by_direction'] : array(); ?>
							<?php $this->render_public_metric( 'Bullish', $by_direction['bullish'] ?? array() ); ?>
							<?php $this->render_public_metric( 'Bearish', $by_direction['bearish'] ?? array() ); ?>
							<?php $this->render_public_metric( 'Neutral', $by_direction['neutral'] ?? array() ); ?>
						</div>
					</div>
				<?php endforeach; ?>
				<p class="bm-bi__micro-note"><?php esc_html_e( 'Directional accuracy memakai outcome sekitar 24 jam. Untuk Bullish/Bearish, pergerakan di antara -0,5% dan +0,5% diklasifikasikan inconclusive dan tidak masuk denominator accuracy; jumlah konklusif dan total selalu ditampilkan.', 'bitmomo-btc-intelligence' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $versions ) || ! empty( $range_versions ) || ! empty( $data_quality ) || ! empty( $regime_performance['versions'] ) ) : ?>
			<details class="bm-bi__details" data-proof-section="evaluation">
				<summary><?php esc_html_e( 'Evaluasi lainnya', 'bitmomo-btc-intelligence' ); ?></summary>
				<div class="bm-bi__details-body">
					<?php foreach ( $versions as $version => $metrics ) :
						$buckets = is_array( $metrics['confidence_buckets'] ?? null ) ? $metrics['confidence_buckets'] : array();
						if ( $buckets ) : ?>
							<h3><?php echo esc_html( count( $versions ) > 1 ? sprintf( 'Confidence vs akurasi · %s', $version ) : 'Confidence vs akurasi' ); ?></h3>
							<div class="bm-bi__proof-grid"><?php foreach ( $buckets as $bucket ) : $this->render_public_metric( (string) ( $bucket['range'] ?? 'Confidence' ), $bucket ); endforeach; ?></div>
						<?php endif;
					endforeach; ?>

					<?php foreach ( $range_versions as $version => $range_metric ) : ?>
						<h3><?php echo esc_html( count( $range_versions ) > 1 ? sprintf( 'Expected Range historis · %s', $version ) : 'Expected Range historis' ); ?></h3>
						<div class="bm-bi__proof-grid">
							<?php $this->render_public_metric( __( 'Range hit', 'bitmomo-btc-intelligence' ), $range_metric, 'range_hit_pct' ); ?>
							<?php $this->render_public_metric( __( 'Breach bawah', 'bitmomo-btc-intelligence' ), $range_metric, 'low_breach_pct' ); ?>
							<?php $this->render_public_metric( __( 'Breach atas', 'bitmomo-btc-intelligence' ), $range_metric, 'high_breach_pct' ); ?>
						</div>
					<?php endforeach; ?>

					<?php foreach ( (array) ( $regime_performance['versions'] ?? array() ) as $version => $rows ) : if ( ! is_array( $rows ) || ! $rows ) continue; ?>
						<h3><?php echo esc_html( sprintf( 'Perilaku setelah Market State · %s', $version ) ); ?></h3>
						<div class="bm-bi__proof-grid">
							<?php foreach ( $rows as $regime => $metric ) : $this->render_public_metric( $this->regime_display_label( $regime ) . ' · return', $metric, 'average_forward_return_pct' ); endforeach; ?>
						</div>
					<?php endforeach; ?>

					<?php if ( $data_quality ) : ?>
						<h3><?php esc_html_e( 'Kualitas data & settlement', 'bitmomo-btc-intelligence' ); ?></h3>
						<div class="bm-bi__proof-grid">
							<?php $this->render_public_metric( __( 'Stale rate', 'bitmomo-btc-intelligence' ), $data_quality, 'stale_rate_pct' ); ?>
							<?php $this->render_public_metric( __( 'Blocked/degraded', 'bitmomo-btc-intelligence' ), $data_quality, 'blocked_degraded_rate_pct' ); ?>
							<?php $this->render_public_metric( __( 'Missing data', 'bitmomo-btc-intelligence' ), $data_quality, 'missing_data_rate_pct' ); ?>
							<?php $this->render_public_metric( __( 'Settlement complete', 'bitmomo-btc-intelligence' ), $data_quality, 'settlement_completeness_pct' ); ?>
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
			<details class="bm-bi__details" data-proof-section="methodology">
				<summary><?php esc_html_e( 'Cara kerja & metodologi', 'bitmomo-btc-intelligence' ); ?></summary>
				<div class="bm-bi__details-body">
					<p><?php esc_html_e( 'Opportunity menggunakan candle BTC 5 menit dan dievaluasi setiap 15 menit terhadap distribusi aktivitas 14 hari. Ia mengukur aktivitas, bukan arah.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Directional Bias dan Confidence berasal dari lima axis deterministik: Direction, Volatility, Carry, Structure, dan Crowding. Confidence menggabungkan kekuatan dan koherensi evidence; evidence yang kuat tetapi saling berlawanan menurunkan confidence.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Market State adalah classifier terpisah. State hanya ditampilkan bersama snapshot saat record Regime memiliki source id canonical yang sama; Bitmomo tidak meminjam state dari edition lain ketika klasifikasi saat ini belum tersedia.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Snapshot berusia sampai 6 jam berstatus fresh, 6–30 jam ditandai DATA TERTUNDA, dan di atas 30 jam gagal tertutup sebagai unavailable.', 'bitmomo-btc-intelligence' ); ?></p>
					<p><?php esc_html_e( 'Track record dipisahkan per versi model/classifier. Directional accuracy hanya memakai outcome konklusif; jumlah konklusif dan total ditampilkan agar denominator tidak tersembunyi.', 'bitmomo-btc-intelligence' ); ?></p>
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
			<p><?php esc_html_e( 'Bitmomo Pro menambahkan skenario, expected range, invalidation, dan perubahan thesis di atas intelligence publik ini.', 'bitmomo-btc-intelligence' ); ?></p>
			<a class="bm-bi__cta-primary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat Bitmomo Pro', 'bitmomo-btc-intelligence' ); ?></a>
		</section>
		<?php
	}
}

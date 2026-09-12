<?php
/** Public BTC Intelligence: Opportunity-first, public adapter only. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bitmomo_snapshot = class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
    ? Bitmomo_Public_Intelligence_Adapter::snapshot()
    : null;
$bitmomo_available = is_array( $bitmomo_snapshot );
$bitmomo_status = $bitmomo_available ? sanitize_key( (string) ( $bitmomo_snapshot['status'] ?? 'unavailable' ) ) : 'unavailable';
$bitmomo_available = $bitmomo_available && in_array( $bitmomo_status, array( 'fresh', 'delayed' ), true );

$bitmomo_opportunity = $bitmomo_available && is_array( $bitmomo_snapshot['opportunity'] ?? null ) ? $bitmomo_snapshot['opportunity'] : array();
$bitmomo_opportunity_available = 'available' === sanitize_key( (string) ( $bitmomo_opportunity['status'] ?? '' ) );
$bitmomo_opportunity_state = $bitmomo_opportunity_available ? sanitize_key( (string) ( $bitmomo_opportunity['state'] ?? '' ) ) : '';
$bitmomo_opportunity_labels = array( 'high' => 'HIGH', 'normal' => 'NORMAL', 'low' => 'LOW' );
$bitmomo_opportunity_copy = array(
    'high'   => __( 'Aktivitas jangka pendek relatif tinggi. Peluang pergerakan bermakna meningkat dibanding periode yang lebih tenang.', 'bitmomo' ),
    'normal' => __( 'Aktivitas jangka pendek berada di sekitar tengah distribusi 14 hari terakhir.', 'bitmomo' ),
    'low'    => __( 'Aktivitas jangka pendek relatif rendah. Pasar sedang lebih tenang dibanding periode yang lebih aktif.', 'bitmomo' ),
);
$bitmomo_opportunity_previous = sanitize_key( (string) ( $bitmomo_opportunity['previous_state'] ?? '' ) );
$bitmomo_opportunity_changed = $bitmomo_opportunity_available && ! empty( $bitmomo_opportunity['changed'] ) && isset( $bitmomo_opportunity_labels[ $bitmomo_opportunity_previous ] );
$bitmomo_opportunity_updated = $bitmomo_opportunity_available && ! empty( $bitmomo_opportunity['knowledge_time'] ) ? strtotime( (string) $bitmomo_opportunity['knowledge_time'] ) : false;

$bitmomo_market_state = $bitmomo_available ? sanitize_key( (string) ( $bitmomo_snapshot['market_state'] ?? '' ) ) : '';
$bitmomo_regime_label = __( 'Belum tersedia', 'bitmomo' );
if ( '' !== $bitmomo_market_state ) {
    $bitmomo_regime_label = class_exists( 'Bitmomo_Regime_Taxonomy' )
        ? Bitmomo_Regime_Taxonomy::regime_label_id( $bitmomo_market_state )
        : ucfirst( str_replace( '_', ' ', $bitmomo_market_state ) );
}

$bitmomo_raw_bias = $bitmomo_available ? sanitize_key( (string) ( $bitmomo_snapshot['directional_bias'] ?? '' ) ) : '';
$bitmomo_bias_valid = in_array( $bitmomo_raw_bias, array( 'bullish', 'bearish', 'neutral' ), true );
$bitmomo_strength = $bitmomo_available ? sanitize_key( (string) ( $bitmomo_snapshot['direction_strength'] ?? '' ) ) : '';
$bitmomo_direction_labels = array(
    'strong_bullish' => __( 'Strong Bullish', 'bitmomo' ),
    'bullish'        => __( 'Moderate Bullish', 'bitmomo' ),
    'neutral'        => __( 'Neutral', 'bitmomo' ),
    'bearish'        => __( 'Moderate Bearish', 'bitmomo' ),
    'strong_bearish' => __( 'Strong Bearish', 'bitmomo' ),
);
$bitmomo_direction_label = $bitmomo_direction_labels[ $bitmomo_strength ] ?? ( $bitmomo_bias_valid ? ucfirst( $bitmomo_raw_bias ) : __( 'Belum tersedia', 'bitmomo' ) );

$bitmomo_confidence_map = array( 'high' => __( 'Tinggi', 'bitmomo' ), 'medium' => __( 'Sedang', 'bitmomo' ), 'low' => __( 'Rendah', 'bitmomo' ) );
$bitmomo_confidence_key = $bitmomo_available ? sanitize_key( (string) ( $bitmomo_snapshot['confidence']['label'] ?? '' ) ) : '';
$bitmomo_confidence_label = $bitmomo_confidence_map[ $bitmomo_confidence_key ] ?? __( 'Belum tersedia', 'bitmomo' );
$bitmomo_confidence_segments = array( 'low' => 1, 'medium' => 2, 'high' => 3 );
$bitmomo_active_segments = $bitmomo_confidence_segments[ $bitmomo_confidence_key ] ?? 0;

$bitmomo_key_drivers = $bitmomo_available && is_array( $bitmomo_snapshot['key_drivers'] ?? null )
    ? array_slice( array_values( array_filter( array_map( 'strval', $bitmomo_snapshot['key_drivers'] ) ) ), 0, 5 )
    : array();
$bitmomo_provenance = $bitmomo_available && is_array( $bitmomo_snapshot['provenance'] ?? null ) ? $bitmomo_snapshot['provenance'] : array();
$bitmomo_source = trim( (string) ( $bitmomo_provenance['source'] ?? '' ) );
$bitmomo_as_of = trim( (string) ( $bitmomo_provenance['as_of'] ?? ( $bitmomo_snapshot['freshness']['timestamp_iso'] ?? '' ) ) );
$bitmomo_timezone = trim( (string) ( $bitmomo_provenance['timezone'] ?? 'Asia/Jakarta' ) );
$bitmomo_timezone_label = 'Asia/Jakarta' === $bitmomo_timezone ? 'WIB' : $bitmomo_timezone;
$bitmomo_updated = $bitmomo_as_of ? strtotime( $bitmomo_as_of ) : false;
?>
<section class="bm-section bm-btc" aria-labelledby="bm-btc-title">
  <div class="bm-container">
    <header class="bm-btc-section-head">
      <h2 id="bm-btc-title">BTC INTELLIGENCE — GRATIS</h2>
      <p>Opportunity dievaluasi setiap 15 menit · ringkasan konteks diperbarui pada sesi utama</p>
    </header>

    <article class="bm-btc-card bm-btc-card--<?php echo esc_attr( $bitmomo_status ); ?>">
      <?php if ( ! $bitmomo_available ) : ?>
        <div class="bm-btc-unavailable" role="status">
          <span class="bm-btc-freshness is-unavailable"><?php esc_html_e( 'Belum tersedia', 'bitmomo' ); ?></span>
          <h3><?php esc_html_e( 'Update BTC terbaru belum tersedia.', 'bitmomo' ); ?></h3>
          <p><?php esc_html_e( 'Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo' ); ?> <a class="bm-btc-help" href="<?php echo esc_url( home_url( '/help/#quality-gate' ) ); ?>"><?php esc_html_e( 'Mengapa?', 'bitmomo' ); ?></a></p>
        </div>
      <?php else : ?>
        <div class="bm-btc-opportunity <?php echo esc_attr( $bitmomo_opportunity_available ? 'is-' . $bitmomo_opportunity_state : 'is-unavailable' ); ?>">
          <div class="bm-btc-opportunity-head">
            <div>
              <span class="bm-btc-kicker"><?php esc_html_e( 'Opportunity', 'bitmomo' ); ?></span>
              <strong><?php echo esc_html( $bitmomo_opportunity_available && isset( $bitmomo_opportunity_labels[ $bitmomo_opportunity_state ] ) ? $bitmomo_opportunity_labels[ $bitmomo_opportunity_state ] : __( 'Belum tersedia', 'bitmomo' ) ); ?></strong>
            </div>
            <span class="bm-btc-opportunity-tag"><?php esc_html_e( 'Aktivitas · bukan arah', 'bitmomo' ); ?></span>
          </div>
          <p><?php echo esc_html( $bitmomo_opportunity_available && isset( $bitmomo_opportunity_copy[ $bitmomo_opportunity_state ] ) ? $bitmomo_opportunity_copy[ $bitmomo_opportunity_state ] : __( 'Menunggu baseline dan data 5 menit yang memenuhi standar kualitas.', 'bitmomo' ) ); ?></p>
          <div class="bm-btc-opportunity-meta">
            <?php if ( $bitmomo_opportunity_changed ) : ?>
              <span><?php echo esc_html( 'CHANGED ' . $bitmomo_opportunity_labels[ $bitmomo_opportunity_previous ] . ' → ' . $bitmomo_opportunity_labels[ $bitmomo_opportunity_state ] ); ?></span>
            <?php else : ?>
              <span><?php esc_html_e( 'RELATIF TERHADAP 14 HARI TERAKHIR', 'bitmomo' ); ?></span>
            <?php endif; ?>
            <?php if ( $bitmomo_opportunity_updated ) : ?><time datetime="<?php echo esc_attr( (string) $bitmomo_opportunity['knowledge_time'] ); ?>"><?php echo esc_html( wp_date( 'H:i', $bitmomo_opportunity_updated ) . ' WIB' ); ?></time><?php endif; ?>
          </div>
        </div>

        <div class="bm-btc-overview">
          <div class="bm-btc-metric bm-btc-bias">
            <span class="bm-btc-kicker"><?php esc_html_e( 'Directional Bias', 'bitmomo' ); ?></span>
            <strong class="is-<?php echo esc_attr( $bitmomo_bias_valid ? $bitmomo_raw_bias : 'neutral' ); ?>"><?php echo esc_html( $bitmomo_direction_label ); ?></strong>
            <p><?php esc_html_e( 'Kecenderungan evidence saat ini, bukan probabilitas arah.', 'bitmomo' ); ?></p>
          </div>
          <div class="bm-btc-metric bm-btc-confidence">
            <span class="bm-btc-kicker"><?php esc_html_e( 'Confidence', 'bitmomo' ); ?></span>
            <strong><?php echo esc_html( $bitmomo_confidence_label ); ?></strong>
            <div class="bm-btc-confidence-segments" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Confidence %s', 'bitmomo' ), $bitmomo_confidence_label ) ); ?>">
              <?php for ( $bitmomo_segment = 1; $bitmomo_segment <= 3; $bitmomo_segment++ ) : ?>
                <span class="<?php echo $bitmomo_segment <= $bitmomo_active_segments ? 'is-active' : ''; ?>"></span>
              <?php endfor; ?>
            </div>
            <p><?php esc_html_e( 'Kekuatan dan konsistensi evidence; bukan peluang benar.', 'bitmomo' ); ?></p>
          </div>
          <div class="bm-btc-metric bm-btc-state">
            <span class="bm-btc-kicker"><?php esc_html_e( 'Market State', 'bitmomo' ); ?></span>
            <strong><i aria-hidden="true"></i><?php echo esc_html( $bitmomo_regime_label ); ?></strong>
            <p><?php esc_html_e( 'Konteks struktur pasar yang lebih lambat daripada Opportunity.', 'bitmomo' ); ?></p>
          </div>
        </div>

        <div class="bm-btc-driver">
          <span><?php esc_html_e( 'Primary Drivers', 'bitmomo' ); ?></span>
          <?php if ( $bitmomo_key_drivers ) : ?>
            <ul class="bm-btc-driver-list">
              <?php foreach ( $bitmomo_key_drivers as $bitmomo_driver ) : ?>
                <li><?php echo esc_html( $bitmomo_driver ); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="bm-btc-driver-empty"><?php esc_html_e( 'Faktor utama belum tersedia untuk snapshot ini.', 'bitmomo' ); ?></p>
          <?php endif; ?>
        </div>

        <footer class="bm-btc-status">
          <div>
            <span class="bm-btc-reference"><?php echo esc_html( sprintf( __( 'BTC Reference $%s', 'bitmomo' ), number_format_i18n( (float) ( $bitmomo_snapshot['btc_reference_price'] ?? 0 ), 0 ) ) ); ?></span>
            <time datetime="<?php echo esc_attr( $bitmomo_as_of ); ?>">AS OF <?php echo esc_html( $bitmomo_updated ? wp_date( 'd M Y · H:i', $bitmomo_updated ) . ' ' . $bitmomo_timezone_label : __( 'Belum tersedia', 'bitmomo' ) ); ?></time>
            <span class="bm-btc-freshness is-<?php echo esc_attr( $bitmomo_status ); ?>"><i aria-hidden="true"></i><?php echo $bitmomo_status === 'fresh' ? esc_html__( 'Data terkini', 'bitmomo' ) : esc_html__( 'Data tertunda', 'bitmomo' ); ?></span>
          </div>
          <p class="bm-btc-disclaimer"><strong><?php echo esc_html( sprintf( __( 'SOURCE %s', 'bitmomo' ), $bitmomo_source ) ); ?></strong><br><?php esc_html_e( 'Bukan sinyal beli/jual dan bukan nasihat keuangan. Opportunity mengukur aktivitas, bukan arah harga.', 'bitmomo' ); ?></p>
        </footer>
      <?php endif; ?>
    </article>
    <p class="bm-btc-methodology-bridge"><a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>"><?php esc_html_e( 'Lihat metodologi & track record lengkap →', 'bitmomo' ); ?></a></p>
  </div>
</section>
<?php unset(
  $bitmomo_snapshot, $bitmomo_available, $bitmomo_status, $bitmomo_opportunity, $bitmomo_opportunity_available,
  $bitmomo_opportunity_state, $bitmomo_opportunity_labels, $bitmomo_opportunity_copy, $bitmomo_opportunity_previous,
  $bitmomo_opportunity_changed, $bitmomo_opportunity_updated, $bitmomo_market_state, $bitmomo_regime_label,
  $bitmomo_raw_bias, $bitmomo_bias_valid, $bitmomo_strength, $bitmomo_direction_labels, $bitmomo_direction_label,
  $bitmomo_confidence_map, $bitmomo_confidence_key, $bitmomo_confidence_label, $bitmomo_confidence_segments,
  $bitmomo_active_segments, $bitmomo_key_drivers, $bitmomo_provenance, $bitmomo_source, $bitmomo_as_of,
  $bitmomo_timezone, $bitmomo_timezone_label, $bitmomo_updated, $bitmomo_segment, $bitmomo_driver
); ?>
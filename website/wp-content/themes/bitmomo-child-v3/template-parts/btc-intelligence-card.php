<?php
/** Public BTC Daily Intelligence: free projection only. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bitmomo_btc = class_exists( 'Bitmomo_AI_Intelligence' )
    ? Bitmomo_AI_Intelligence::free_projection()
    : array(
        'status'  => 'unavailable',
        'message' => __( 'Update BTC terbaru belum tersedia.', 'bitmomo' ),
        'detail'  => __( 'Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo' ),
    );

$bitmomo_status = sanitize_key( (string) ( $bitmomo_btc['status'] ?? 'unavailable' ) );
$bitmomo_available = in_array( $bitmomo_status, array( 'fresh', 'delayed' ), true );
$bitmomo_key_drivers = array_values( array_filter(
    array_map( 'strval', is_array( $bitmomo_btc['key_drivers'] ?? null ) ? $bitmomo_btc['key_drivers'] : array() ),
    static fn( $driver ) => '' !== trim( $driver )
) );
$bitmomo_key_drivers = array_slice( $bitmomo_key_drivers, 0, 5 );

$bitmomo_regime = array();
if ( class_exists( 'Bitmomo_Regime_State_Store' ) ) {
    $bitmomo_regime = Bitmomo_Regime_State_Store::instance()->get_latest();
}
$bitmomo_regime_label = class_exists( 'Bitmomo_Regime_Taxonomy' ) && ! empty( $bitmomo_regime['regime'] )
    ? Bitmomo_Regime_Taxonomy::regime_label_id( $bitmomo_regime['regime'] )
    : __( 'Belum tersedia', 'bitmomo' );
$bitmomo_confidence = max( 0, min( 100, (int) ( $bitmomo_btc['confidence'] ?? 0 ) ) );
$bitmomo_confidence_label = $bitmomo_confidence >= 70 ? __( 'Tinggi', 'bitmomo' ) : ( $bitmomo_confidence >= 40 ? __( 'Sedang', 'bitmomo' ) : __( 'Rendah', 'bitmomo' ) );
$bitmomo_confidence_segments = max( 1, min( 5, (int) ceil( $bitmomo_confidence / 20 ) ) );
$bitmomo_updated = ! empty( $bitmomo_btc['timestamp_iso'] ) ? strtotime( $bitmomo_btc['timestamp_iso'] ) : false;
?>
<section class="bm-section bm-btc" aria-labelledby="bm-btc-title">
  <div class="bm-container">
    <header class="bm-btc-section-head">
      <h2 id="bm-btc-title">BTC DAILY INTELLIGENCE — GRATIS</h2>
      <p>Diperbarui setiap hari, 07:00 WIB</p>
    </header>

    <article class="bm-btc-card bm-btc-card--<?php echo esc_attr( $bitmomo_status ); ?>">
      <?php if ( ! $bitmomo_available ) : ?>
        <div class="bm-btc-unavailable" role="status">
          <span class="bm-btc-freshness is-unavailable"><?php esc_html_e( 'Belum tersedia', 'bitmomo' ); ?></span>
          <h3><?php echo esc_html( (string) ( $bitmomo_btc['message'] ?? '' ) ); ?></h3>
          <p><?php echo esc_html( (string) ( $bitmomo_btc['detail'] ?? '' ) ); ?> <a class="bm-btc-help" href="<?php echo esc_url( home_url( '/help/#quality-gate' ) ); ?>"><?php esc_html_e( 'Mengapa?', 'bitmomo' ); ?></a></p>
        </div>
      <?php else : ?>
        <div class="bm-btc-overview">
          <div class="bm-btc-metric bm-btc-state">
            <span class="bm-btc-kicker"><?php esc_html_e( 'Market State', 'bitmomo' ); ?></span>
            <strong><i aria-hidden="true"></i><?php echo esc_html( $bitmomo_regime_label ); ?></strong>
            <p>Bias jangka pendek: <?php echo esc_html( strtolower( (string) ( $bitmomo_btc['bias'] ?? 'neutral' ) ) ); ?></p>
          </div>
          <div class="bm-btc-metric bm-btc-price">
            <span class="bm-btc-kicker"><?php esc_html_e( 'BTC Reference Price', 'bitmomo' ); ?></span>
            <strong>$<?php echo esc_html( number_format_i18n( (float) $bitmomo_btc['price'], 0 ) ); ?></strong>
            <p>Harga acuan snapshot, bukan harga real-time</p>
          </div>
          <div class="bm-btc-metric bm-btc-confidence">
            <span class="bm-btc-kicker"><?php esc_html_e( 'Confidence', 'bitmomo' ); ?></span>
            <strong><?php echo esc_html( $bitmomo_confidence_label ); ?></strong>
            <div class="bm-btc-confidence-segments" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Confidence %d persen', 'bitmomo' ), $bitmomo_confidence ) ); ?>">
              <?php for ( $bitmomo_segment = 1; $bitmomo_segment <= 5; $bitmomo_segment++ ) : ?>
                <span class="<?php echo $bitmomo_segment <= $bitmomo_confidence_segments ? 'is-active' : ''; ?>"></span>
              <?php endfor; ?>
            </div>
          </div>
        </div>

        <div class="bm-btc-driver">
          <span><?php esc_html_e( 'Faktor Utama', 'bitmomo' ); ?></span>
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
            <time datetime="<?php echo esc_attr( $bitmomo_btc['timestamp_iso'] ); ?>">UPDATED <?php echo esc_html( $bitmomo_updated ? wp_date( 'd M Y · H:i', $bitmomo_updated ) . ' WIB' : $bitmomo_btc['freshness_label'] ); ?></time>
            <span class="bm-btc-freshness is-<?php echo esc_attr( $bitmomo_status ); ?>"><i aria-hidden="true"></i><?php echo $bitmomo_status === 'fresh' ? esc_html__( 'Data terkini', 'bitmomo' ) : esc_html__( 'Data tertunda', 'bitmomo' ); ?></span>
          </div>
          <p class="bm-btc-disclaimer"><?php esc_html_e( 'Bukan nasihat keuangan. Snapshot edukatif untuk membantu penilaian risiko Anda sendiri.', 'bitmomo' ); ?></p>
        </footer>
      <?php endif; ?>
    </article>

    <aside class="bm-pro-teaser" id="bitmomo-pro" aria-labelledby="bm-pro-title">
      <div class="bm-pro-copy">
        <span class="bm-pro-eyebrow">BITMOMO PRO</span>
        <h3 id="bm-pro-title">Jangan cuma tahu bias pasar. Ketahui apa yang bisa mengubahnya.</h3>
        <p>Bitmomo Pro membantu Anda memahami skenario di balik perubahan pasar.</p>
        <a class="bm-pro-cta" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Lihat Bitmomo Pro</a>
      </div>
      <ul class="bm-pro-locks" aria-label="Fitur Bitmomo Pro">
        <li><span>Expected Range</span><span aria-hidden="true">🔒</span></li>
        <li><span>Scenario Map</span><span aria-hidden="true">🔒</span></li>
        <li><span>Thesis Invalidation</span><span aria-hidden="true">🔒</span></li>
        <li><span>What Changed</span><span aria-hidden="true">🔒</span></li>
      </ul>
    </aside>
  </div>
</section>
<?php unset( $bitmomo_btc, $bitmomo_status, $bitmomo_available, $bitmomo_key_drivers, $bitmomo_regime, $bitmomo_regime_label, $bitmomo_confidence, $bitmomo_confidence_label, $bitmomo_confidence_segments, $bitmomo_updated, $bitmomo_segment, $bitmomo_driver ); ?>


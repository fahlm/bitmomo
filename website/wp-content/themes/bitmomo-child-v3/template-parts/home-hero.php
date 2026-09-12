<?php
/** Homepage hero: one glanceable public BTC reading. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_snapshot = class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
    ? Bitmomo_Public_Intelligence_Adapter::snapshot()
    : null;
$bm_status = is_array( $bm_snapshot ) ? sanitize_key( (string) ( $bm_snapshot['status'] ?? '' ) ) : '';
$bm_available = is_array( $bm_snapshot ) && in_array( $bm_status, array( 'fresh', 'delayed' ), true );

$bm_bias = $bm_available ? sanitize_key( (string) ( $bm_snapshot['directional_bias'] ?? '' ) ) : '';
$bm_bias_labels = array( 'bullish' => 'Bullish', 'neutral' => 'Netral', 'bearish' => 'Bearish' );
$bm_bias_label = isset( $bm_bias_labels[ $bm_bias ] ) ? $bm_bias_labels[ $bm_bias ] : 'Belum tersedia';

$bm_confidence = $bm_available && isset( $bm_snapshot['confidence']['value'] ) && is_numeric( $bm_snapshot['confidence']['value'] )
    ? max( 0, min( 100, (int) $bm_snapshot['confidence']['value'] ) )
    : null;
$bm_confidence_key = $bm_available ? sanitize_key( (string) ( $bm_snapshot['confidence']['label'] ?? '' ) ) : '';
$bm_confidence_labels = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
$bm_confidence_label = isset( $bm_confidence_labels[ $bm_confidence_key ] ) ? $bm_confidence_labels[ $bm_confidence_key ] : '';

$bm_drivers = $bm_available && is_array( $bm_snapshot['key_drivers'] ?? null )
    ? array_values( array_filter( array_map( 'strval', $bm_snapshot['key_drivers'] ) ) )
    : array();
$bm_driver = trim( (string) ( $bm_drivers[0] ?? '' ) );

$bm_updated_iso = $bm_available ? trim( (string) ( $bm_snapshot['freshness']['timestamp_iso'] ?? '' ) ) : '';
$bm_updated_ts = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $bm_updated_iso ) ? strtotime( $bm_updated_iso ) : false;
$bm_updated_label = $bm_updated_ts
    ? ( new DateTimeImmutable( '@' . $bm_updated_ts ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M · H:i' ) . ' WIB'
    : 'Belum tersedia';
?>
<section class="bm-hero" aria-labelledby="bm-home-title">
  <div class="bm-container bm-hero-layout">
    <div class="bm-hero-copy">
      <p class="bm-hero-eyebrow"><span aria-hidden="true"></span>BITMOMO · BTC INTELLIGENCE</p>
      <h1 class="bm-hero-title" id="bm-home-title">Baca kondisi BTC tanpa tenggelam dalam noise.</h1>
      <p class="bm-hero-sub">Bitmomo merangkum arah pasar, tingkat keyakinan analisis, alasan utama, dan waktu pembaruan data — agar kondisi BTC dapat dipahami dalam sekali lihat.</p>
      <div class="bm-hero-actions">
        <a class="bm-hero-btn" href="#founding-whitelist">Gabung Founding Whitelist</a>
        <a class="bm-hero-link" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Pelajari Bitmomo Pro →</a>
      </div>
      <p class="bm-hero-notes">BTC ONLY <span>·</span> EVIDENCE-DRIVEN <span>·</span> BUKAN SINYAL BELI/JUAL</p>
    </div>

    <article class="bm-direction-card" aria-label="Ringkasan kondisi BTC saat ini">
      <header class="bm-direction-card-head">
        <span>KONDISI BTC SAAT INI</span>
        <?php if ( 'delayed' === $bm_status ) : ?><strong class="is-delayed">DATA TERTUNDA</strong><?php endif; ?>
      </header>

      <div class="bm-direction-summary">
        <div class="bm-direction-metric bm-direction-metric--bias">
          <span>ARAH</span>
          <strong class="is-<?php echo esc_attr( in_array( $bm_bias, array( 'bullish', 'neutral', 'bearish' ), true ) ? $bm_bias : 'unknown' ); ?>"><?php echo esc_html( $bm_bias_label ); ?></strong>
          <small>arah bukti pasar saat ini</small>
        </div>
        <div class="bm-direction-metric">
          <span>KEYAKINAN</span>
          <strong><?php echo null !== $bm_confidence ? esc_html( ( $bm_confidence_label ? $bm_confidence_label . ' · ' : '' ) . $bm_confidence . '/100' ) : esc_html__( 'Belum tersedia', 'bitmomo' ); ?></strong>
          <small>konsistensi bukti, bukan probabilitas harga</small>
        </div>
      </div>

      <div class="bm-direction-driver">
        <span>ALASAN UTAMA</span>
        <p><?php echo esc_html( $bm_driver ?: 'Pembacaan terbaru belum tersedia.' ); ?></p>
      </div>

      <footer class="bm-direction-footer">
        <p><span>DIPERBARUI</span> <?php echo esc_html( $bm_updated_label ); ?></p>
        <a class="bm-direction-deep-link" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Lihat riwayat &amp; track record →</a>
      </footer>
    </article>
  </div>
</section>
<?php unset(
    $bm_snapshot, $bm_status, $bm_available, $bm_bias, $bm_bias_labels, $bm_bias_label,
    $bm_confidence, $bm_confidence_key, $bm_confidence_labels, $bm_confidence_label,
    $bm_drivers, $bm_driver, $bm_updated_iso, $bm_updated_ts, $bm_updated_label
); ?>

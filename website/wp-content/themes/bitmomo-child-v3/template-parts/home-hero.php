<?php
/** Homepage hero: institutional product positioning + one auditable BTC reading. @package Bitmomo */
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

$bm_price = $bm_available && isset( $bm_snapshot['btc_reference_price'] ) && is_numeric( $bm_snapshot['btc_reference_price'] )
    ? (float) $bm_snapshot['btc_reference_price']
    : 0.0;
$bm_price_label = $bm_price > 0 ? '$' . number_format( $bm_price, 0, '.', ',' ) : 'Belum tersedia';

$bm_drivers = $bm_available && is_array( $bm_snapshot['key_drivers'] ?? null )
    ? array_values( array_filter( array_map( 'strval', $bm_snapshot['key_drivers'] ) ) )
    : array();
$bm_driver = trim( (string) ( $bm_drivers[0] ?? '' ) );

$bm_updated_iso = $bm_available ? trim( (string) ( $bm_snapshot['freshness']['timestamp_iso'] ?? '' ) ) : '';
$bm_updated_ts = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $bm_updated_iso ) ? strtotime( $bm_updated_iso ) : false;
$bm_updated_label = $bm_updated_ts
    ? ( new DateTimeImmutable( '@' . $bm_updated_ts ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M · H:i' ) . ' WIB'
    : 'Belum tersedia';

$bm_source = $bm_available ? trim( (string) ( $bm_snapshot['provenance']['source'] ?? '' ) ) : '';
$bm_source_label = $bm_source !== '' ? $bm_source : 'Sumber belum tersedia';
$bm_status_label = 'delayed' === $bm_status ? 'DATA TERTUNDA' : ( $bm_available ? 'DATA TERBARU' : 'BELUM TERSEDIA' );
$bm_status_class = 'fresh' === $bm_status ? '' : ' is-delayed';
?>
<section class="bm-home-hero" aria-labelledby="bm-home-title">
  <div class="bm-container">
    <div class="bm-home-hero__grid">
      <div class="bm-home-hero__copy">
        <p class="bm-home-hero__eyebrow">BTC MARKET INTELLIGENCE</p>
        <h1 class="bm-home-hero__title" id="bm-home-title">Pahami kondisi BTC sekarang. Ketahui apa yang perlu dipantau berikutnya.</h1>
        <p class="bm-home-hero__lead">BTC Intelligence merangkum arah, keyakinan, dan alasan utama di balik pembacaan pasar. Bitmomo Pro menambahkan skenario, rentang yang dipantau, dan kondisi yang mengubah pandangan. Setiap pembacaan dicatat agar hasilnya dapat ditinjau kembali.</p>

        <div class="bm-home-hero__actions">
          <a class="bm-home-hero__primary" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="hero_primary">Buka BTC Intelligence</a>
          <a class="bm-home-hero__secondary" href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>" data-bm-event="homepage_decision_ledger_click" data-bm-placement="hero_secondary">Lihat Decision Ledger →</a>
        </div>

        <p class="bm-home-hero__notes" aria-label="Karakteristik Bitmomo">
          <span>BTC ONLY</span>
          <span>PUBLIC LEDGER</span>
          <span>BUKAN SINYAL BELI/JUAL</span>
        </p>
      </div>

      <article class="bm-home-reading" aria-label="Pembacaan BTC terbaru">
        <header class="bm-home-reading__head">
          <span class="bm-home-reading__label">CURRENT BTC READING</span>
          <strong class="bm-home-reading__status<?php echo esc_attr( $bm_status_class ); ?>"><?php echo esc_html( $bm_status_label ); ?></strong>
        </header>

        <dl class="bm-home-reading__metrics">
          <div class="bm-home-reading__metric">
            <dt>ARAH</dt>
            <dd class="is-<?php echo esc_attr( in_array( $bm_bias, array( 'bullish', 'neutral', 'bearish' ), true ) ? $bm_bias : 'unknown' ); ?>"><?php echo esc_html( $bm_bias_label ); ?></dd>
            <small>Arah evidence pasar saat ini.</small>
          </div>
          <div class="bm-home-reading__metric">
            <dt>KEYAKINAN</dt>
            <dd><?php echo null !== $bm_confidence ? esc_html( ( $bm_confidence_label ? $bm_confidence_label . ' · ' : '' ) . $bm_confidence . '/100' ) : esc_html__( 'Belum tersedia', 'bitmomo' ); ?></dd>
            <small>Konsistensi evidence, bukan peluang harga.</small>
          </div>
          <div class="bm-home-reading__metric">
            <dt>REFERENSI BTC</dt>
            <dd><?php echo esc_html( $bm_price_label ); ?></dd>
            <small>Harga referensi pada waktu pembacaan.</small>
          </div>
          <div class="bm-home-reading__metric">
            <dt>DIPERBARUI</dt>
            <dd><?php echo esc_html( $bm_updated_label ); ?></dd>
            <small>Timestamp pembacaan publik.</small>
          </div>
        </dl>

        <div class="bm-home-reading__driver">
          <span>ALASAN UTAMA</span>
          <p><?php echo esc_html( $bm_driver ?: 'Pembacaan terbaru belum tersedia.' ); ?></p>
        </div>

        <footer class="bm-home-reading__footer">
          <p class="bm-home-reading__source"><strong>SUMBER</strong><br><?php echo esc_html( $bm_source_label ); ?></p>
          <a class="bm-home-reading__link" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="hero_market_card">Buka pembacaan lengkap →</a>
        </footer>
      </article>
    </div>

    <div class="bm-home-proof" aria-label="Prinsip akuntabilitas Bitmomo">
      <div class="bm-home-proof__item">
        <span class="bm-home-proof__kicker">TIMESTAMPED</span>
        <p>Pembacaan dicatat sebelum hasil pasar diketahui, bukan ditulis ulang setelah pasar bergerak.</p>
      </div>
      <div class="bm-home-proof__item">
        <span class="bm-home-proof__kicker">PUBLIC LEDGER</span>
        <p>Hasil yang selaras, meleset, dan tidak konklusif tetap dapat ditinjau secara publik.</p>
      </div>
      <div class="bm-home-proof__item">
        <span class="bm-home-proof__kicker">DATA DISCIPLINE</span>
        <p>Data yang terlambat atau tidak valid ditandai atau ditahan, bukan dipaksakan menjadi insight.</p>
      </div>
    </div>

    <p class="bm-home-hero__notes" aria-label="Founding access">
      <span>SETELAH MENINJAU PRODUK & BUKTI</span>
      <a class="bm-home-reading__link" href="#founding-whitelist" data-bm-event="homepage_whitelist_jump" data-bm-placement="proof_after_accountability">Lihat Founding Access →</a>
    </p>
  </div>
</section>
<?php unset(
    $bm_snapshot, $bm_status, $bm_available, $bm_bias, $bm_bias_labels, $bm_bias_label,
    $bm_confidence, $bm_confidence_key, $bm_confidence_labels, $bm_confidence_label,
    $bm_price, $bm_price_label, $bm_drivers, $bm_driver, $bm_updated_iso, $bm_updated_ts,
    $bm_updated_label, $bm_source, $bm_source_label, $bm_status_label, $bm_status_class
); ?>

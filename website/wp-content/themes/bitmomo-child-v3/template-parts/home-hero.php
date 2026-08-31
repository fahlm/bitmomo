<?php
/** Homepage hero and truthful 30-day direction visualization. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_hero_intel = class_exists( 'Bitmomo_AI_Intelligence' )
    ? Bitmomo_AI_Intelligence::free_projection()
    : array();
$bm_hero_available = in_array( sanitize_key( (string) ( $bm_hero_intel['status'] ?? '' ) ), array( 'fresh', 'delayed' ), true );
$bm_hero_records = array();
if ( class_exists( 'Bitmomo_Regime_State_Store' ) ) {
    $bm_hero_records = Bitmomo_Regime_State_Store::instance()->get_recent( 30 );
}

// One official bar per calendar day: US Session wins, otherwise Morning.
$bm_hero_official = array();
foreach ( $bm_hero_records as $bm_record ) {
    $bm_date_key = substr( (string) ( $bm_record['as_of'] ?? $bm_record['date'] ?? '' ), 0, 10 );
    if ( '' === $bm_date_key ) continue;
    if ( ! isset( $bm_hero_official[ $bm_date_key ] ) || ( 'us_session' === ( $bm_record['edition'] ?? '' ) && 'us_session' !== ( $bm_hero_official[ $bm_date_key ]['edition'] ?? '' ) ) ) {
        $bm_hero_official[ $bm_date_key ] = $bm_record;
    }
}
ksort( $bm_hero_official );
$bm_hero_official = array_slice( $bm_hero_official, -30, null, true );
$bm_hero_latest = $bm_hero_official ? end( $bm_hero_official ) : array();

$bm_direction_label = static function ( $bias, $confidence ) {
    $bias = sanitize_key( (string) $bias );
    $confidence = max( 0, min( 100, (float) $confidence ) );
    if ( 'neutral' === $bias ) return 'Neutral';
    if ( 'bullish' === $bias ) return $confidence >= 70 ? 'Strong Bullish' : 'Moderate Bullish';
    if ( 'bearish' === $bias ) return $confidence >= 70 ? 'Strong Bearish' : 'Moderate Bearish';
    return 'Neutral';
};

$bm_latest_bias = (string) ( $bm_hero_latest['directional_bias'] ?? ( $bm_hero_intel['bias'] ?? 'neutral' ) );
$bm_latest_confidence = (float) ( $bm_hero_intel['confidence'] ?? ( $bm_hero_latest['regime_confidence'] ?? 0 ) );
$bm_latest_direction = $bm_direction_label( $bm_latest_bias, $bm_latest_confidence );
$bm_market_state = class_exists( 'Bitmomo_Regime_Taxonomy' ) && ! empty( $bm_hero_latest['regime'] )
    ? Bitmomo_Regime_Taxonomy::regime_label_id( $bm_hero_latest['regime'] )
    : 'Belum tersedia';
$bm_hero_drivers = $bm_hero_available && is_array( $bm_hero_intel['key_drivers'] ?? null )
    ? array_values( array_filter( array_map( 'strval', $bm_hero_intel['key_drivers'] ) ) )
    : array();
$bm_hero_driver = trim( (string) ( $bm_hero_drivers[0] ?? '' ) );
$bm_hero_updated = ! empty( $bm_hero_intel['timestamp_iso'] ) ? strtotime( $bm_hero_intel['timestamp_iso'] ) : false;
$bm_confidence_label = $bm_latest_confidence >= 70 ? 'Tinggi' : ( $bm_latest_confidence >= 40 ? 'Sedang' : 'Rendah' );
?>
<section class="bm-hero" aria-labelledby="bm-home-title">
  <div class="bm-container bm-hero-layout">
    <div class="bm-hero-copy">
      <p class="bm-hero-eyebrow"><span aria-hidden="true"></span>BTC INTELLIGENCE PLATFORM</p>
      <h1 class="bm-hero-title" id="bm-home-title">BTC Intelligence untuk pasar yang berubah cepat.</h1>
      <p class="bm-hero-sub">Pahami kondisi BTC sekarang, skenario berikutnya, dan apa yang dapat mengubah thesis pasar — tanpa menyatukan puluhan indikator sendiri.</p>
      <div class="bm-hero-actions">
        <a class="bm-hero-btn" href="#bm-btc-title">Lihat BTC Intelligence</a>
        <a class="bm-hero-btn bm-hero-btn--secondary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Pelajari Bitmomo Pro</a>
      </div>
      <p class="bm-hero-notes">SNAPSHOT HARIAN GRATIS <span>·</span> TANPA SINYAL INSTAN <span>·</span> BTC ONLY</p>
    </div>

    <article class="bm-direction-card" aria-label="Market State 30 hari">
      <header class="bm-direction-head">
        <span>MARKET STATE / 30D</span>
        <strong><?php echo esc_html( strtoupper( (string) ( $bm_hero_intel['market_state'] ?? $bm_latest_direction ) ) ); ?></strong>
      </header>
      <div class="bm-direction-chart bm-state-chart" role="img" aria-label="Riwayat Market State resmi; tinggi batang mengikuti confidence Regime.">
        <?php if ( $bm_hero_official ) : foreach ( $bm_hero_official as $bm_day => $bm_record ) :
          $bm_conf = max( 0, min( 100, (float) ( $bm_record['regime_confidence'] ?? 0 ) ) );
          $bm_state_label = class_exists( 'Bitmomo_Regime_Taxonomy' ) ? Bitmomo_Regime_Taxonomy::regime_label_id( $bm_record['regime'] ?? '' ) : ( $bm_record['regime'] ?? '' );
        ?>
          <span class="bm-direction-bar" style="--direction-size:<?php echo esc_attr( max( 8, $bm_conf ) ); ?>" title="<?php echo esc_attr( $bm_day . ': ' . $bm_state_label . ' · ' . round( $bm_conf ) . '%' ); ?>"><span></span></span>
        <?php endforeach; else : ?>
          <p class="bm-direction-empty">Riwayat Direction akan tampil setelah evaluasi pasar resmi tersedia.</p>
        <?php endif; ?>
      </div>
      <dl class="bm-direction-meta">
        <div><dt>CONFIDENCE</dt><dd><?php echo esc_html( $bm_confidence_label ); ?></dd></div>
        <div class="bm-direction-meta-driver"><dt>DRIVER</dt><dd><?php echo esc_html( $bm_hero_driver ?: 'Belum tersedia' ); ?></dd></div>
        <div><dt>UPDATED</dt><dd><?php echo esc_html( $bm_hero_updated ? wp_date( 'H:i', $bm_hero_updated ) . ' WIB' : 'Belum tersedia' ); ?></dd></div>
      </dl>
    </article>
  </div>
</section>
<?php unset( $bm_hero_intel, $bm_hero_available, $bm_hero_records, $bm_hero_official, $bm_hero_latest, $bm_direction_label, $bm_latest_bias, $bm_latest_confidence, $bm_latest_direction, $bm_market_state, $bm_hero_drivers, $bm_hero_driver, $bm_hero_updated, $bm_confidence_label, $bm_record, $bm_date_key, $bm_day, $bm_conf, $bm_state_label ); ?>

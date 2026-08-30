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
$bm_latest_confidence = (float) ( $bm_hero_latest['directional_confidence'] ?? ( $bm_hero_intel['confidence'] ?? 0 ) );
$bm_latest_direction = $bm_direction_label( $bm_latest_bias, $bm_latest_confidence );
$bm_market_state = class_exists( 'Bitmomo_Regime_Taxonomy' ) && ! empty( $bm_hero_latest['regime'] )
    ? Bitmomo_Regime_Taxonomy::regime_label_id( $bm_hero_latest['regime'] )
    : 'Belum tersedia';
$bm_primary_driver = $bm_hero_available ? trim( (string) ( $bm_hero_intel['primary_driver'] ?? '' ) ) : '';
?>
<section class="bm-hero" aria-labelledby="bm-home-title">
  <div class="bm-container bm-hero-layout">
    <div class="bm-hero-copy">
      <p class="bm-hero-eyebrow"><span aria-hidden="true"></span>BTC INTELLIGENCE PLATFORM</p>
      <h1 class="bm-hero-title" id="bm-home-title">BTC Intelligence untuk pasar yang berubah cepat.</h1>
      <p class="bm-hero-sub">Pahami kondisi BTC sekarang, skenario berikutnya, dan apa yang dapat mengubah thesis pasar — tanpa harus menganalisis semuanya sendiri.</p>
      <div class="bm-hero-actions">
        <a class="bm-hero-btn" href="#bm-btc-title">Lihat BTC Intelligence</a>
        <a class="bm-hero-btn bm-hero-btn--secondary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Pelajari Bitmomo Pro</a>
      </div>
      <p class="bm-hero-notes">SNAPSHOT HARIAN GRATIS <span>·</span> TANPA SINYAL INSTAN <span>·</span> BTC ONLY</p>
    </div>

    <article class="bm-direction-card" aria-label="Market Direction 30 hari">
      <header class="bm-direction-head">
        <span>MARKET DIRECTION / 30D</span>
        <strong><?php echo esc_html( strtoupper( $bm_latest_direction ) ); ?></strong>
      </header>
      <div class="bm-direction-chart" role="img" aria-label="Riwayat arah pasar; bullish ke atas, bearish ke bawah, dan tinggi batang mengikuti confidence.">
        <span class="bm-direction-baseline" aria-hidden="true"></span>
        <?php if ( $bm_hero_official ) : foreach ( $bm_hero_official as $bm_day => $bm_record ) :
          $bm_bias = sanitize_key( (string) ( $bm_record['directional_bias'] ?? 'neutral' ) );
          $bm_conf = max( 0, min( 100, (float) ( $bm_record['directional_confidence'] ?? 0 ) ) );
          $bm_sign = 'bullish' === $bm_bias ? 1 : ( 'bearish' === $bm_bias ? -1 : 0 );
          $bm_value = 0 === $bm_sign ? 3 : round( $bm_sign * $bm_conf, 2 );
          $bm_label = $bm_direction_label( $bm_bias, $bm_conf );
        ?>
          <span class="bm-direction-bar is-<?php echo esc_attr( $bm_bias ); ?>" style="--direction-size:<?php echo esc_attr( abs( $bm_value ) ); ?>" title="<?php echo esc_attr( $bm_day . ': ' . $bm_label . ' · ' . round( $bm_conf ) . '%' ); ?>"><span></span></span>
        <?php endforeach; else : ?>
          <p class="bm-direction-empty">Riwayat Direction akan tampil setelah evaluasi pasar resmi tersedia.</p>
        <?php endif; ?>
      </div>
      <dl class="bm-direction-meta">
        <div><dt>MARKET STATE</dt><dd><?php echo esc_html( $bm_market_state ); ?></dd></div>
        <div><dt>CONFIDENCE</dt><dd><?php echo esc_html( round( $bm_latest_confidence ) ); ?>%</dd></div>
        <div class="bm-direction-meta-driver"><dt>PRIMARY DRIVER</dt><dd><?php echo esc_html( $bm_primary_driver ?: 'Belum tersedia' ); ?></dd></div>
      </dl>
    </article>
  </div>
</section>
<?php unset( $bm_hero_intel, $bm_hero_available, $bm_hero_records, $bm_hero_official, $bm_hero_latest, $bm_direction_label, $bm_latest_bias, $bm_latest_confidence, $bm_latest_direction, $bm_market_state, $bm_primary_driver, $bm_record, $bm_date_key, $bm_day, $bm_bias, $bm_conf, $bm_sign, $bm_value, $bm_label ); ?>

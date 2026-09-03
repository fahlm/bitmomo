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

$bm_direction_label = static function ( $strength, $bias ) {
    $labels = array(
        'strong_bullish' => 'Strong Bullish',
        'bullish'        => 'Moderate Bullish',
        'neutral'        => 'Neutral',
        'bearish'        => 'Moderate Bearish',
        'strong_bearish' => 'Strong Bearish',
    );
    $strength = sanitize_key( (string) $strength );
    if ( isset( $labels[ $strength ] ) ) return $labels[ $strength ];
    $bias = sanitize_key( (string) $bias );
    if ( in_array( $bias, array( 'bullish', 'bearish', 'neutral' ), true ) ) return ucfirst( $bias );
    return 'Neutral';
};

// Directional Bias: prefer the live canonical projection over a possibly-stale
// regime-record snapshot, so the hero stays consistent with the BTC Intelligence
// card below it. Collapses entirely (no hardcoded fallback) when bias is absent.
$bm_raw_bias = sanitize_key( (string) ( $bm_hero_intel['bias'] ?? ( $bm_hero_latest['directional_bias'] ?? '' ) ) );
$bm_bias_valid = in_array( $bm_raw_bias, array( 'bullish', 'bearish', 'neutral' ), true );
$bm_latest_confidence = (float) ( $bm_hero_intel['confidence'] ?? ( $bm_hero_latest['regime_confidence'] ?? 0 ) );
$bm_latest_strength = sanitize_key( (string) ( $bm_hero_intel['direction_strength'] ?? '' ) );
$bm_latest_direction = $bm_bias_valid ? $bm_direction_label( $bm_latest_strength, $bm_raw_bias ) : '';

// Market State: always sourced from the canonical regime plugin -- never from
// the AI projection's legacy `market_state` field, which is just a bias relabel.
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

    <article class="bm-direction-card" aria-label="Market State dan Directional Bias 30 hari">
      <header class="bm-direction-head">
        <div class="bm-direction-metric bm-direction-metric--state">
          <span>MARKET STATE / 30D</span>
          <strong><?php echo esc_html( strtoupper( $bm_market_state ) ); ?></strong>
        </div>
        <?php if ( $bm_latest_direction ) : ?>
        <div class="bm-direction-metric bm-direction-metric--bias">
          <span>DIRECTIONAL BIAS</span>
          <strong><?php echo esc_html( strtoupper( $bm_latest_direction ) ); ?></strong>
        </div>
        <?php endif; ?>
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
<?php unset( $bm_hero_intel, $bm_hero_available, $bm_hero_records, $bm_hero_official, $bm_hero_latest, $bm_direction_label, $bm_raw_bias, $bm_bias_valid, $bm_latest_confidence, $bm_latest_strength, $bm_latest_direction, $bm_market_state, $bm_hero_drivers, $bm_hero_driver, $bm_hero_updated, $bm_confidence_label, $bm_record, $bm_date_key, $bm_day, $bm_conf, $bm_state_label ); ?>

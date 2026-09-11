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

// Canonical Insight Confidence is produced ONLY by Bitmomo_AI_Signal_Engine
// (reached here via Bitmomo_AI_Intelligence::free_projection()['confidence']).
// It is NOT the same metric as the Regime classifier's regime_confidence
// (a classification-certainty score from a different, isolated pipeline --
// see class-bitmomo-regime-classifier.php vs class-bitmomo-ai-signal-engine.php).
// Fails closed when the AI projection is unavailable: never substitutes
// regime_confidence for canonical Confidence.
$bm_confidence_available = $bm_hero_available && isset( $bm_hero_intel['confidence'] );

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
$bm_session_label = $bm_hero_available ? trim( (string) ( $bm_hero_intel['session_label'] ?? '' ) ) : '';

// Plain-language 0-100 -> level bucket. Pure display math, not itself tied to
// either concept -- used BOTH for canonical Confidence (below, when available)
// and for the historical chart's Market State Certainty (regime_confidence).
// Reusing the same three-tier wording (Tinggi/Sedang/Rendah) is the existing
// customer-facing value representation; the two concepts stay separated by
// their labels ("CONFIDENCE" vs "MARKET STATE CERTAINTY"), never by this
// bucketing function.
$bm_level_bucket = static function ( $value ) {
    $value = (float) $value;
    if ( $value >= 70 ) return 'Tinggi';
    if ( $value >= 40 ) return 'Sedang';
    return 'Rendah';
};

// The word "Confidence" is reserved for this canonical value. When the AI
// projection is unavailable, this fails closed to 'Belum tersedia' -- it is
// never backfilled from regime_confidence or any other metric.
$bm_confidence_label = $bm_confidence_available
    ? $bm_level_bucket( (float) $bm_hero_intel['confidence'] )
    : 'Belum tersedia';

// Canonical bias -> display label. Historical regime records only ever carry
// the three-way Bitmomo_Regime_Taxonomy bias (bullish/neutral/bearish) -- NOT
// the five-way strong/moderate strength used above for the live projection.
// That strength value does not exist per historical day, so it is never
// fabricated here; each bar shows only the bias its own record genuinely has.
$bm_bias_display_label = static function ( $bias ) {
    $bias = sanitize_key( (string) $bias );
    $labels = array( 'bullish' => 'Bullish', 'neutral' => 'Neutral', 'bearish' => 'Bearish' );
    return isset( $labels[ $bias ] ) ? $labels[ $bias ] : 'Belum tersedia';
};

// Pre-render the detail state for the latest (default-selected) observation
// so the panel is meaningful before any interaction and still works with
// JavaScript disabled. 'certainty' here is Market State Certainty
// (regime_confidence), deliberately never labelled or keyed "confidence".
$bm_hero_default_detail = array( 'date' => '', 'bias' => 'Belum tersedia', 'bias_class' => '', 'certainty' => 'Belum tersedia', 'state' => 'Belum tersedia' );
if ( $bm_hero_official ) {
    $bm_default_day    = array_key_last( $bm_hero_official );
    $bm_default_record = $bm_hero_official[ $bm_default_day ];
    $bm_default_bias   = sanitize_key( (string) ( $bm_default_record['directional_bias'] ?? '' ) );
    $bm_hero_default_detail = array(
        'date'       => wp_date( 'd M', strtotime( $bm_default_day ) ),
        'bias'       => $bm_bias_display_label( $bm_default_bias ),
        'bias_class' => in_array( $bm_default_bias, array( 'bullish', 'neutral', 'bearish' ), true ) ? 'is-' . $bm_default_bias : '',
        'certainty'  => $bm_level_bucket( $bm_default_record['regime_confidence'] ?? 0 ),
        'state'      => class_exists( 'Bitmomo_Regime_Taxonomy' ) && ! empty( $bm_default_record['regime'] )
            ? Bitmomo_Regime_Taxonomy::regime_label_id( $bm_default_record['regime'] )
            : 'Belum tersedia',
    );
}
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
      <div
        class="bm-direction-chart bm-state-chart"
        role="group"
        aria-label="Riwayat Market State resmi. Tinggi batang mengikuti Market State Certainty, warna mengikuti Directional Bias. Pilih satu batang untuk detail."
      >
        <?php if ( $bm_hero_official ) :
          $bm_last_day = array_key_last( $bm_hero_official );
          foreach ( $bm_hero_official as $bm_day => $bm_record ) :
          $bm_conf = max( 0, min( 100, (float) ( $bm_record['regime_confidence'] ?? 0 ) ) );
          $bm_state_label = class_exists( 'Bitmomo_Regime_Taxonomy' ) ? Bitmomo_Regime_Taxonomy::regime_label_id( $bm_record['regime'] ?? '' ) : ( $bm_record['regime'] ?? '' );
          $bm_bar_bias = sanitize_key( (string) ( $bm_record['directional_bias'] ?? '' ) );
          $bm_bar_bias_valid = in_array( $bm_bar_bias, array( 'bullish', 'neutral', 'bearish' ), true );
          $bm_bar_bias_label = $bm_bias_display_label( $bm_bar_bias );
          $bm_bar_certainty_label = $bm_level_bucket( $bm_conf );
          $bm_bar_date_short = wp_date( 'd M', strtotime( $bm_day ) );
          $bm_is_current = ( $bm_day === $bm_last_day );
          $bm_bar_classes = 'bm-direction-bar';
          if ( $bm_bar_bias_valid ) $bm_bar_classes .= ' is-' . $bm_bar_bias;
          if ( $bm_is_current ) $bm_bar_classes .= ' is-current';
          if ( $bm_is_current ) $bm_bar_classes .= ' is-selected';
        ?>
          <button
            type="button"
            class="<?php echo esc_attr( $bm_bar_classes ); ?>"
            style="--direction-size:<?php echo esc_attr( max( 8, $bm_conf ) ); ?>"
            title="<?php echo esc_attr( $bm_day . ': ' . $bm_state_label . ' · ' . round( $bm_conf ) . '%' ); ?>"
            aria-pressed="<?php echo $bm_is_current ? 'true' : 'false'; ?>"
            aria-label="<?php echo esc_attr( $bm_bar_date_short . ': ' . $bm_bar_bias_label . ', market state certainty ' . $bm_bar_certainty_label . ', ' . $bm_state_label ); ?>"
            data-date="<?php echo esc_attr( $bm_bar_date_short ); ?>"
            data-bias-label="<?php echo esc_attr( $bm_bar_bias_label ); ?>"
            data-bias-class="<?php echo esc_attr( $bm_bar_bias_valid ? 'is-' . $bm_bar_bias : '' ); ?>"
            data-certainty-label="<?php echo esc_attr( $bm_bar_certainty_label ); ?>"
            data-state-label="<?php echo esc_attr( $bm_state_label ); ?>"
          ><span></span></button>
        <?php endforeach; ?>
        <?php else : ?>
          <p class="bm-direction-empty">Riwayat Direction akan tampil setelah evaluasi pasar resmi tersedia.</p>
        <?php endif; ?>
      </div>
      <?php if ( $bm_hero_official ) : ?>
      <div class="bm-direction-dates" aria-hidden="true">
        <?php foreach ( $bm_hero_official as $bm_day => $bm_record ) : ?>
          <span><?php echo esc_html( wp_date( 'd', strtotime( $bm_day ) ) ); ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <dl class="bm-direction-meta">
        <?php if ( '' !== $bm_session_label ) : ?><div><dt>SESSION</dt><dd><?php echo esc_html( $bm_session_label ); ?></dd></div><?php endif; ?>
        <div><dt>CONFIDENCE</dt><dd><?php echo esc_html( $bm_confidence_label ); ?></dd></div>
        <div class="bm-direction-meta-driver"><dt>DRIVER</dt><dd><?php echo esc_html( $bm_hero_driver ?: 'Belum tersedia' ); ?></dd></div>
        <div><dt>UPDATED</dt><dd><?php echo esc_html( $bm_hero_updated ? wp_date( 'H:i', $bm_hero_updated ) . ' WIB' : 'Belum tersedia' ); ?></dd></div>
      </dl>
      <?php if ( $bm_hero_official ) : ?>
      <dl class="bm-direction-detail" id="bm-direction-detail">
        <div><dt>DATE</dt><dd id="bm-direction-detail-date"><?php echo esc_html( $bm_hero_default_detail['date'] ); ?></dd></div>
        <div><dt>DIRECTIONAL BIAS</dt><dd id="bm-direction-detail-bias" class="<?php echo esc_attr( $bm_hero_default_detail['bias_class'] ); ?>"><?php echo esc_html( $bm_hero_default_detail['bias'] ); ?></dd></div>
        <div><dt>MARKET STATE CERTAINTY</dt><dd id="bm-direction-detail-certainty"><?php echo esc_html( $bm_hero_default_detail['certainty'] ); ?></dd></div>
        <div><dt>MARKET STATE</dt><dd id="bm-direction-detail-state"><?php echo esc_html( $bm_hero_default_detail['state'] ); ?></dd></div>
      </dl>
      <?php endif; ?>
    </article>
  </div>
</section>
<?php unset(
  $bm_hero_intel, $bm_hero_available, $bm_hero_records, $bm_hero_official, $bm_hero_latest,
  $bm_direction_label, $bm_raw_bias, $bm_bias_valid, $bm_confidence_available, $bm_latest_strength,
  $bm_latest_direction, $bm_market_state, $bm_hero_drivers, $bm_hero_driver, $bm_hero_updated,
  $bm_level_bucket, $bm_confidence_label, $bm_bias_display_label, $bm_hero_default_detail,
  $bm_default_day, $bm_default_record, $bm_default_bias, $bm_record, $bm_date_key, $bm_day, $bm_conf,
  $bm_state_label, $bm_last_day, $bm_bar_bias, $bm_bar_bias_valid, $bm_bar_bias_label,
  $bm_bar_certainty_label, $bm_bar_date_short, $bm_is_current, $bm_bar_classes
); ?>

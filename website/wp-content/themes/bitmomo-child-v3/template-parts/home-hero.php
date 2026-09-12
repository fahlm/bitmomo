<?php
/** Homepage hero with Opportunity-first current state and truthful 30-day context. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_snapshot = class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
    ? Bitmomo_Public_Intelligence_Adapter::snapshot()
    : null;
$bm_hero_available = is_array( $bm_snapshot ) && in_array( sanitize_key( (string) ( $bm_snapshot['status'] ?? '' ) ), array( 'fresh', 'delayed' ), true );

$bm_opportunity = $bm_hero_available && is_array( $bm_snapshot['opportunity'] ?? null ) ? $bm_snapshot['opportunity'] : array();
$bm_opportunity_available = 'available' === sanitize_key( (string) ( $bm_opportunity['status'] ?? '' ) );
$bm_opportunity_state = $bm_opportunity_available ? sanitize_key( (string) ( $bm_opportunity['state'] ?? '' ) ) : '';
$bm_opportunity_labels = array( 'high' => 'HIGH', 'normal' => 'NORMAL', 'low' => 'LOW' );
$bm_opportunity_copy = array(
    'high'   => 'Aktivitas jangka pendek relatif tinggi. Peluang pergerakan bermakna meningkat dibanding periode yang lebih tenang.',
    'normal' => 'Aktivitas jangka pendek berada di sekitar tengah distribusi 14 hari terakhir.',
    'low'    => 'Aktivitas jangka pendek relatif rendah. Pasar sedang lebih tenang dibanding periode yang lebih aktif.',
);
$bm_opportunity_previous = sanitize_key( (string) ( $bm_opportunity['previous_state'] ?? '' ) );
$bm_opportunity_changed = $bm_opportunity_available && ! empty( $bm_opportunity['changed'] ) && isset( $bm_opportunity_labels[ $bm_opportunity_previous ] );

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
    return in_array( $bias, array( 'bullish', 'bearish', 'neutral' ), true ) ? ucfirst( $bias ) : '';
};

$bm_raw_bias = $bm_hero_available ? sanitize_key( (string) ( $bm_snapshot['directional_bias'] ?? '' ) ) : '';
$bm_bias_valid = in_array( $bm_raw_bias, array( 'bullish', 'bearish', 'neutral' ), true );
$bm_latest_strength = $bm_hero_available ? sanitize_key( (string) ( $bm_snapshot['direction_strength'] ?? '' ) ) : '';
$bm_latest_direction = $bm_bias_valid ? $bm_direction_label( $bm_latest_strength, $bm_raw_bias ) : '';

$bm_raw_market_state = $bm_hero_available ? sanitize_key( (string) ( $bm_snapshot['market_state'] ?? '' ) ) : '';
$bm_market_state = 'Belum tersedia';
if ( '' !== $bm_raw_market_state ) {
    $bm_market_state = class_exists( 'Bitmomo_Regime_Taxonomy' )
        ? Bitmomo_Regime_Taxonomy::regime_label_id( $bm_raw_market_state )
        : ucfirst( str_replace( '_', ' ', $bm_raw_market_state ) );
}

$bm_confidence_map = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
$bm_confidence_key = $bm_hero_available ? sanitize_key( (string) ( $bm_snapshot['confidence']['label'] ?? '' ) ) : '';
$bm_confidence_label = $bm_confidence_map[ $bm_confidence_key ] ?? 'Belum tersedia';
$bm_hero_drivers = $bm_hero_available && is_array( $bm_snapshot['key_drivers'] ?? null )
    ? array_values( array_filter( array_map( 'strval', $bm_snapshot['key_drivers'] ) ) )
    : array();
$bm_hero_driver = trim( (string) ( $bm_hero_drivers[0] ?? '' ) );
$bm_hero_updated = $bm_hero_available && ! empty( $bm_snapshot['freshness']['timestamp_iso'] ) ? strtotime( $bm_snapshot['freshness']['timestamp_iso'] ) : false;
$bm_session_label = $bm_hero_available ? trim( (string) ( $bm_snapshot['session']['label'] ?? '' ) ) : '';

$bm_level_bucket = static function ( $value ) {
    $value = (float) $value;
    if ( $value >= 70 ) return 'Tinggi';
    if ( $value >= 40 ) return 'Sedang';
    return 'Rendah';
};
$bm_bias_display_label = static function ( $bias ) {
    $bias = sanitize_key( (string) $bias );
    $labels = array( 'bullish' => 'Bullish', 'neutral' => 'Neutral', 'bearish' => 'Bearish' );
    return isset( $labels[ $bias ] ) ? $labels[ $bias ] : 'Belum tersedia';
};

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
      <p class="bm-hero-sub">Ketahui kapan BTC sedang aktif, ke mana evidence condong, dan konteks apa yang perlu dipantau berikutnya — tanpa menyatukan puluhan indikator sendiri.</p>
      <div class="bm-hero-actions">
        <a class="bm-hero-btn" href="#bm-btc-title">Lihat BTC Intelligence</a>
        <a class="bm-hero-btn bm-hero-btn--secondary" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Pelajari Bitmomo Pro</a>
      </div>
      <p class="bm-hero-notes">OPPORTUNITY INTRADAY <span>·</span> BUKAN SINYAL BELI/JUAL <span>·</span> BTC ONLY</p>
    </div>

    <article class="bm-direction-card" aria-label="Opportunity dan konteks BTC saat ini">
      <div class="bm-hero-opportunity <?php echo esc_attr( $bm_opportunity_available ? 'is-' . $bm_opportunity_state : 'is-unavailable' ); ?>">
        <div class="bm-hero-opportunity-main">
          <span>OPPORTUNITY</span>
          <strong><?php echo esc_html( $bm_opportunity_available && isset( $bm_opportunity_labels[ $bm_opportunity_state ] ) ? $bm_opportunity_labels[ $bm_opportunity_state ] : 'BELUM TERSEDIA' ); ?></strong>
        </div>
        <p><?php echo esc_html( $bm_opportunity_available && isset( $bm_opportunity_copy[ $bm_opportunity_state ] ) ? $bm_opportunity_copy[ $bm_opportunity_state ] : 'Menunggu baseline dan data 5 menit yang memenuhi standar kualitas.' ); ?></p>
        <?php if ( $bm_opportunity_changed ) : ?>
          <small>CHANGED <?php echo esc_html( $bm_opportunity_labels[ $bm_opportunity_previous ] . ' → ' . $bm_opportunity_labels[ $bm_opportunity_state ] ); ?></small>
        <?php else : ?>
          <small>AKTIVITAS, BUKAN ARAH HARGA</small>
        <?php endif; ?>
      </div>

      <header class="bm-direction-head bm-direction-head--current">
        <div class="bm-direction-metric bm-direction-metric--bias">
          <span>DIRECTIONAL BIAS</span>
          <strong><?php echo esc_html( $bm_latest_direction ?: 'BELUM TERSEDIA' ); ?></strong>
        </div>
        <div class="bm-direction-metric">
          <span>CONFIDENCE</span>
          <strong><?php echo esc_html( strtoupper( $bm_confidence_label ) ); ?></strong>
        </div>
        <div class="bm-direction-metric bm-direction-metric--state">
          <span>MARKET STATE</span>
          <strong><?php echo esc_html( strtoupper( $bm_market_state ) ); ?></strong>
        </div>
      </header>

      <p class="bm-direction-context-label">30D MARKET CONTEXT</p>
      <div
        class="bm-direction-chart bm-state-chart"
        role="group"
        aria-label="Riwayat Market State resmi 30 hari. Tinggi batang mengikuti Market State Certainty, warna mengikuti Directional Bias. Pilih satu batang untuk detail."
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
          if ( $bm_is_current ) $bm_bar_classes .= ' is-current is-selected';
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
          <p class="bm-direction-empty">Riwayat Market State akan tampil setelah evaluasi resmi tersedia.</p>
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
        <div class="bm-direction-meta-driver"><dt>PRIMARY DRIVER</dt><dd><?php echo esc_html( $bm_hero_driver ?: 'Belum tersedia' ); ?></dd></div>
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
  $bm_snapshot, $bm_hero_available, $bm_opportunity, $bm_opportunity_available, $bm_opportunity_state,
  $bm_opportunity_labels, $bm_opportunity_copy, $bm_opportunity_previous, $bm_opportunity_changed,
  $bm_hero_records, $bm_hero_official, $bm_direction_label, $bm_raw_bias, $bm_bias_valid,
  $bm_latest_strength, $bm_latest_direction, $bm_raw_market_state, $bm_market_state,
  $bm_confidence_map, $bm_confidence_key, $bm_confidence_label, $bm_hero_drivers, $bm_hero_driver,
  $bm_hero_updated, $bm_session_label, $bm_level_bucket, $bm_bias_display_label, $bm_hero_default_detail,
  $bm_default_day, $bm_default_record, $bm_default_bias, $bm_record, $bm_date_key, $bm_day, $bm_conf,
  $bm_state_label, $bm_last_day, $bm_bar_bias, $bm_bar_bias_valid, $bm_bar_bias_label,
  $bm_bar_certainty_label, $bm_bar_date_short, $bm_is_current, $bm_bar_classes
); ?>
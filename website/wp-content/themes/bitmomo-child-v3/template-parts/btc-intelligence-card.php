<?php
/**
 * BTC Daily Intelligence card.
 *
 * Renders a manually/AI-supplied daily BTC outlook from a static JSON
 * sample file. This is the MVP data source -- a future pipeline can
 * replace the file read below with a live feed without touching this
 * template's markup.
 *
 * @package Bitmomo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$bitmomo_btc_file = get_stylesheet_directory() . '/data/btc-daily-sample.json';

if ( ! file_exists( $bitmomo_btc_file ) ) {
    return;
}

$bitmomo_btc_raw = file_get_contents( $bitmomo_btc_file );
$bitmomo_btc     = $bitmomo_btc_raw ? json_decode( $bitmomo_btc_raw, true ) : null;

if ( ! is_array( $bitmomo_btc ) || empty( $bitmomo_btc['timestamp'] ) ) {
    return;
}

if (
    ! function_exists( 'bitmomo_btc_record_is_fresh' ) ||
    ! bitmomo_btc_record_is_fresh( $bitmomo_btc )
) {
    return;
}

$direction_map = [
    'BULLISH' => [ 'label' => 'BULLISH', 'class' => 'is-bullish' ],
    'BEARISH' => [ 'label' => 'BEARISH', 'class' => 'is-bearish' ],
    'NEUTRAL' => [ 'label' => 'NEUTRAL', 'class' => 'is-neutral' ],
];
$direction      = strtoupper( (string) ( $bitmomo_btc['direction'] ?? 'NEUTRAL' ) );
$direction_info = $direction_map[ $direction ] ?? $direction_map['NEUTRAL'];

$confidence = max( 0, min( 100, (int) ( $bitmomo_btc['confidence'] ?? 0 ) ) );

$price          = (float) ( $bitmomo_btc['btc_reference_price'] ?? 0 );
$expected_low   = (float) ( $bitmomo_btc['expected_low'] ?? 0 );
$expected_high  = (float) ( $bitmomo_btc['expected_high'] ?? 0 );
$expected_move  = (float) ( $bitmomo_btc['expected_move_pct'] ?? 0 );

$consensus = wp_parse_args(
    is_array( $bitmomo_btc['consensus'] ?? null ) ? $bitmomo_btc['consensus'] : [],
    [ 'bullish' => 0, 'neutral' => 0, 'bearish' => 0 ]
);

$key_drivers  = is_array( $bitmomo_btc['key_drivers'] ?? null ) ? $bitmomo_btc['key_drivers'] : [];
$invalidation = (string) ( $bitmomo_btc['invalidation'] ?? '' );
$risk_level   = strtoupper( (string) ( $bitmomo_btc['risk_level'] ?? '' ) );

$timestamp_raw = (string) $bitmomo_btc['timestamp'];
$timestamp_int = strtotime( $timestamp_raw );

$updated_label = '';
if ( $timestamp_int ) {
    $updated_label = sprintf(
        /* translators: %s: human-readable time difference, e.g. "3 jam". */
        esc_html__( 'Diperbarui %s lalu', 'bitmomo' ),
        human_time_diff( $timestamp_int, current_time( 'timestamp' ) )
    );
}
$timestamp_display = $timestamp_int ? wp_date( 'd M Y, H:i', $timestamp_int ) . ' WIB' : '';
?>
<section class="bm-section bm-btc">
  <div class="bm-container">
    <h2 class="bm-section-title">BTC Daily Intelligence</h2>

    <div class="bm-btc-card">
      <div class="bm-btc-top">
        <div class="bm-btc-direction <?php echo esc_attr( $direction_info['class'] ); ?>">
          <?php echo esc_html( $direction_info['label'] ); ?>
        </div>
        <div class="bm-btc-price">
          $<?php echo esc_html( number_format_i18n( $price, 0 ) ); ?>
        </div>
      </div>

      <div class="bm-btc-confidence">
        <div class="bm-btc-confidence-label">
          Confidence: <strong><?php echo esc_html( $confidence ); ?>%</strong>
        </div>
        <div class="bm-btc-confidence-track">
          <div class="bm-btc-confidence-fill" style="width: <?php echo esc_attr( $confidence ); ?>%;"></div>
        </div>
      </div>

      <div class="bm-btc-range">
        <div>
          <span class="bm-btc-range-label">Expected 24h Low</span>
          <span class="bm-btc-range-value">$<?php echo esc_html( number_format_i18n( $expected_low, 0 ) ); ?></span>
        </div>
        <div>
          <span class="bm-btc-range-label">Expected 24h High</span>
          <span class="bm-btc-range-value">$<?php echo esc_html( number_format_i18n( $expected_high, 0 ) ); ?></span>
        </div>
        <div>
          <span class="bm-btc-range-label">Expected Move</span>
          <span class="bm-btc-range-value">&plusmn;<?php echo esc_html( number_format_i18n( $expected_move, 1 ) ); ?>%</span>
        </div>
      </div>

      <div class="bm-btc-consensus">
        <span class="bm-btc-pill is-bullish"><?php echo esc_html( $consensus['bullish'] ); ?> bullish</span>
        <span class="bm-btc-pill is-neutral"><?php echo esc_html( $consensus['neutral'] ); ?> neutral</span>
        <span class="bm-btc-pill is-bearish"><?php echo esc_html( $consensus['bearish'] ); ?> bearish</span>
      </div>

      <?php if ( ! empty( $key_drivers ) ) : ?>
        <div class="bm-btc-drivers">
          <h3>Key Drivers</h3>
          <ul>
            <?php foreach ( $key_drivers as $driver ) : ?>
              <li><?php echo esc_html( $driver ); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ( $invalidation ) : ?>
        <p class="bm-btc-invalidation">
          <strong>Invalidation:</strong> <?php echo esc_html( $invalidation ); ?>
        </p>
      <?php endif; ?>

      <div class="bm-btc-meta">
        <?php if ( $risk_level ) : ?>
          <span class="bm-btc-risk">Risk: <?php echo esc_html( $risk_level ); ?></span>
        <?php endif; ?>
        <?php if ( $timestamp_display ) : ?>
          <span class="bm-btc-time">
            <?php echo esc_html( $timestamp_display ); ?><?php echo $updated_label ? ' &middot; ' . esc_html( $updated_label ) : ''; ?>
          </span>
        <?php endif; ?>
      </div>

      <?php
      $bitmomo_cta     = function_exists( 'bitmomo_get_cta' ) ? bitmomo_get_cta( 'btc_intelligence' ) : null;
      $bitmomo_cta_url = function_exists( 'bitmomo_get_cta_url' ) ? bitmomo_get_cta_url( 'btc_intelligence' ) : '';
      ?>
      <?php if ( $bitmomo_cta && $bitmomo_cta_url ) : ?>
        <div class="bm-btc-cta-wrap">
          <a
            class="bm-btc-cta"
            href="<?php echo esc_url( $bitmomo_cta_url ); ?>"
            target="_blank"
            rel="<?php echo esc_attr( $bitmomo_cta['rel'] ?? 'sponsored nofollow noopener' ); ?>"
            data-bm-cta="btc_intelligence"
          >
            <?php echo esc_html( $bitmomo_cta['label'] ); ?> &rarr;
          </a>
          <?php if ( ! empty( $bitmomo_cta['disclosure'] ) ) : ?>
            <p class="bm-btc-cta-disclosure"><?php echo esc_html( $bitmomo_cta['disclosure'] ); ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <p class="bm-btc-disclaimer">
        Bitmomo Intelligence adalah alat bantu keputusan, bukan sinyal beli/jual otomatis. Data saat ini masih tahap manual/contoh -- belum ada rekam jejak akurasi historis yang ditampilkan.
      </p>
    </div>
  </div>
</section>
<?php
unset(
    $bitmomo_btc_file, $bitmomo_btc_raw, $bitmomo_btc,
    $direction, $direction_map, $direction_info, $confidence,
    $price, $expected_low, $expected_high, $expected_move,
    $consensus, $key_drivers, $invalidation, $risk_level,
    $timestamp_raw, $timestamp_int, $updated_label, $timestamp_display,
    $bitmomo_cta, $bitmomo_cta_url
);

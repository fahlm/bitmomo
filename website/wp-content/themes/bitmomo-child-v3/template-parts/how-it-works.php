<?php
/** Homepage evidence surface; legacy filename retained to avoid a second runtime owner. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_history = class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
    ? Bitmomo_Public_Intelligence_Adapter::history()
    : array();
$bm_days = is_array( $bm_history['days'] ?? null ) ? array_slice( $bm_history['days'], -30 ) : array();
$bm_counts = array( 'bullish' => 0, 'neutral' => 0, 'bearish' => 0 );
foreach ( $bm_days as $bm_day ) {
    $bm_day_bias = sanitize_key( (string) ( $bm_day['directional_bias'] ?? '' ) );
    if ( isset( $bm_counts[ $bm_day_bias ] ) ) $bm_counts[ $bm_day_bias ]++;
}

$bm_ledger = class_exists( 'Bitmomo_Btc_Intelligence_Accountability' )
    ? Bitmomo_Btc_Intelligence_Accountability::decision_ledger( 3 )
    : array();
$bm_ledger_rows = is_array( $bm_ledger['rows'] ?? null ) ? $bm_ledger['rows'] : array();

$bm_proof = class_exists( 'Bitmomo_Btc_Intelligence_Accountability' )
    ? Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 )
    : array();
$bm_proof_rows = is_array( $bm_proof['rows'] ?? null ) ? $bm_proof['rows'] : array();
$bm_proof_row = $bm_proof_rows[0] ?? null;

$bm_bias_labels = array( 'bullish' => 'Bullish', 'neutral' => 'Netral', 'bearish' => 'Bearish' );
$bm_verdict_labels = array(
    'aligned'      => 'SESUAI',
    'missed'       => 'TIDAK SESUAI',
    'inconclusive' => 'TIDAK KONKLUSIF',
    'unscored'     => 'BELUM DINILAI',
);
$bm_price = static function ( $value ) {
    return is_numeric( $value ) && (float) $value > 0 ? '$' . number_format_i18n( (float) $value, 0 ) : '—';
};
$bm_return = static function ( $value ) {
    if ( ! is_numeric( $value ) ) return '—';
    $value = (float) $value;
    return ( $value > 0 ? '+' : '' ) . number_format_i18n( $value, 2 ) . '%';
};
$bm_wib = static function ( $iso ) {
    $iso = trim( (string) $iso );
    $ts = preg_match( '/(?:Z|[+-]\d{2}:?\d{2})$/', $iso ) ? strtotime( $iso ) : false;
    if ( ! $ts ) return '';
    return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M · H:i' ) . ' WIB';
};

$bm_range_low = is_array( $bm_proof_row ) && is_numeric( $bm_proof_row['expected_range_low'] ?? null ) ? (float) $bm_proof_row['expected_range_low'] : null;
$bm_range_high = is_array( $bm_proof_row ) && is_numeric( $bm_proof_row['expected_range_high'] ?? null ) ? (float) $bm_proof_row['expected_range_high'] : null;
$bm_range_ref = is_array( $bm_proof_row ) && is_numeric( $bm_proof_row['reference_price'] ?? null ) ? (float) $bm_proof_row['reference_price'] : null;
$bm_range_outcome = is_array( $bm_proof_row ) && is_numeric( $bm_proof_row['outcome_price_24h'] ?? null ) ? (float) $bm_proof_row['outcome_price_24h'] : null;
$bm_range_span = null !== $bm_range_low && null !== $bm_range_high ? max( 1.0, $bm_range_high - $bm_range_low ) : null;
$bm_ref_pct = null !== $bm_range_span && null !== $bm_range_ref ? max( 0, min( 100, ( ( $bm_range_ref - $bm_range_low ) / $bm_range_span ) * 100 ) ) : null;
$bm_outcome_pct = null !== $bm_range_span && null !== $bm_range_outcome ? max( 0, min( 100, ( ( $bm_range_outcome - $bm_range_low ) / $bm_range_span ) * 100 ) ) : null;
?>
<section class="bm-section bm-home-evidence" aria-labelledby="bm-home-evidence-title">
  <div class="bm-container">
    <header class="bm-home-evidence__head">
      <span class="bm-home-evidence__eyebrow">BUKTI, BUKAN KLAIM</span>
      <div>
        <h2 id="bm-home-evidence-title">Lihat apa yang Bitmomo catat—dan apa yang terjadi setelahnya.</h2>
        <p>Riwayat dan arsip di bawah berasal dari catatan yang dibuat sebelum hasil pasar diketahui. Tidak ada pemilihan hasil berdasarkan apakah pembacaan terlihat bagus atau buruk.</p>
      </div>
    </header>

    <div class="bm-home-evidence__grid">
      <section class="bm-home-state" aria-labelledby="bm-home-state-title">
        <header>
          <span>30D STATE TAPE</span>
          <h3 id="bm-home-state-title">Konteks arah 30 hari</h3>
        </header>
        <?php if ( $bm_days ) : ?>
          <div class="bm-home-state__bars" style="--bm-home-state-count:<?php echo esc_attr( max( 1, count( $bm_days ) ) ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( '%d hari: %d bullish, %d netral, %d bearish.', count( $bm_days ), $bm_counts['bullish'], $bm_counts['neutral'], $bm_counts['bearish'] ) ); ?>">
            <?php foreach ( $bm_days as $bm_day ) :
                $bm_day_bias = sanitize_key( (string) ( $bm_day['directional_bias'] ?? '' ) );
                if ( ! isset( $bm_counts[ $bm_day_bias ] ) ) continue;
                $bm_date = (string) ( $bm_day['date'] ?? '' );
                $bm_date_label = $bm_date && strtotime( $bm_date ) ? wp_date( 'd M', strtotime( $bm_date ) ) : $bm_date;
            ?><span class="is-<?php echo esc_attr( $bm_day_bias ); ?>" title="<?php echo esc_attr( $bm_date_label . ' · ' . ( $bm_bias_labels[ $bm_day_bias ] ?? '' ) ); ?>"></span><?php endforeach; ?>
          </div>
          <div class="bm-home-state__summary">
            <strong class="is-bullish">Bullish <?php echo esc_html( $bm_counts['bullish'] ); ?></strong>
            <strong class="is-neutral">Netral <?php echo esc_html( $bm_counts['neutral'] ); ?></strong>
            <strong class="is-bearish">Bearish <?php echo esc_html( $bm_counts['bearish'] ); ?></strong>
            <span><?php echo esc_html( count( $bm_days ) ); ?> hari tersedia</span>
          </div>
        <?php else : ?>
          <p class="bm-home-evidence__empty">Riwayat 30 hari belum cukup tersedia.</p>
        <?php endif; ?>
        <a class="bm-home-evidence__link" href="<?php echo esc_url( home_url( '/btc-intelligence/#btc-30d' ) ); ?>">Buka konteks 30 hari →</a>
      </section>

      <section class="bm-home-ledger" aria-labelledby="bm-home-ledger-title">
        <header>
          <span>DECISION LEDGER</span>
          <h3 id="bm-home-ledger-title">Pembacaan terbaru yang sudah matang</h3>
        </header>
        <?php if ( $bm_ledger_rows ) : ?>
          <ol class="bm-home-ledger__list">
            <?php foreach ( $bm_ledger_rows as $bm_row ) :
                $bm_direction = sanitize_key( (string) ( $bm_row['direction'] ?? '' ) );
                $bm_verdict = sanitize_key( (string) ( $bm_row['verdict'] ?? 'unscored' ) );
            ?>
            <li>
              <div>
                <time datetime="<?php echo esc_attr( (string) ( $bm_row['generated_at'] ?? '' ) ); ?>"><?php echo esc_html( $bm_wib( $bm_row['generated_at'] ?? '' ) ?: '—' ); ?></time>
                <strong class="is-<?php echo esc_attr( $bm_direction ); ?>"><?php echo esc_html( $bm_bias_labels[ $bm_direction ] ?? '—' ); ?></strong>
              </div>
              <div>
                <span>+24H</span>
                <strong><?php echo esc_html( $bm_return( $bm_row['forward_return_pct'] ?? null ) ); ?></strong>
              </div>
              <span class="bm-home-ledger__verdict is-<?php echo esc_attr( $bm_verdict ); ?>"><?php echo esc_html( $bm_verdict_labels[ $bm_verdict ] ?? 'BELUM DINILAI' ); ?></span>
            </li>
            <?php endforeach; ?>
          </ol>
        <?php else : ?>
          <p class="bm-home-evidence__empty">Belum ada outcome matang yang dapat ditampilkan.</p>
        <?php endif; ?>
        <a class="bm-home-evidence__link" href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>">Periksa Decision Ledger lengkap →</a>
      </section>
    </div>

    <section class="bm-home-pro-proof" aria-labelledby="bm-home-pro-proof-title">
      <header class="bm-home-pro-proof__head">
        <div>
          <span>ARSIP PRO ≥48 JAM</span>
          <h3 id="bm-home-pro-proof-title">Contoh Decision View yang sudah melewati masa tunda</h3>
        </div>
        <a class="bm-home-evidence__link" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Lihat Bitmomo Pro →</a>
      </header>

      <?php if ( is_array( $bm_proof_row ) && null !== $bm_range_low && null !== $bm_range_high ) : ?>
        <div class="bm-home-pro-proof__meta">
          <span><?php echo esc_html( $bm_wib( $bm_proof_row['published_at'] ?? '' ) ?: 'Arsip historis' ); ?></span>
          <span>Bias <?php echo esc_html( $bm_bias_labels[ sanitize_key( (string) ( $bm_proof_row['market_state'] ?? '' ) ) ] ?? '—' ); ?></span>
          <span>Confidence <?php echo esc_html( (int) ( $bm_proof_row['confidence'] ?? 0 ) ); ?>/100</span>
        </div>

        <div class="bm-home-range" aria-label="Expected Range historis">
          <div class="bm-home-range__labels"><span><?php echo esc_html( $bm_price( $bm_range_low ) ); ?></span><strong>EXPECTED RANGE</strong><span><?php echo esc_html( $bm_price( $bm_range_high ) ); ?></span></div>
          <div class="bm-home-range__track">
            <?php if ( null !== $bm_ref_pct ) : ?><span class="bm-home-range__marker is-ref" style="left:<?php echo esc_attr( number_format( $bm_ref_pct, 2, '.', '' ) ); ?>%"><b>REF</b></span><?php endif; ?>
            <?php if ( null !== $bm_outcome_pct ) : ?><span class="bm-home-range__marker is-outcome" style="left:<?php echo esc_attr( number_format( $bm_outcome_pct, 2, '.', '' ) ); ?>%"><b>+24H</b></span><?php endif; ?>
          </div>
          <div class="bm-home-range__facts">
            <span>Referensi <strong><?php echo esc_html( $bm_price( $bm_range_ref ) ); ?></strong></span>
            <span>Outcome +24H <strong><?php echo esc_html( $bm_price( $bm_range_outcome ) ); ?></strong></span>
            <span>Return <strong><?php echo esc_html( $bm_return( $bm_proof_row['outcome_return_pct'] ?? null ) ); ?></strong></span>
          </div>
        </div>

        <div class="bm-home-scenarios">
          <?php if ( ! empty( $bm_proof_row['bear_scenario'] ) ) : ?><article><span>BEAR</span><p><?php echo esc_html( $bm_proof_row['bear_scenario'] ); ?></p></article><?php endif; ?>
          <article class="is-base"><span>BASE</span><p><?php echo esc_html( $bm_proof_row['base_scenario'] ?? '' ); ?></p></article>
          <?php if ( ! empty( $bm_proof_row['bull_scenario'] ) ) : ?><article><span>BULL</span><p><?php echo esc_html( $bm_proof_row['bull_scenario'] ); ?></p></article><?php endif; ?>
        </div>
        <p class="bm-home-pro-proof__invalidation"><strong>INVALIDASI TESIS</strong> <?php echo esc_html( $bm_proof_row['invalidation'] ?? '' ); ?></p>
      <?php else : ?>
        <p class="bm-home-evidence__empty">Belum ada Decision View historis yang memenuhi syarat publikasi arsip. Bitmomo tidak menampilkan contoh buatan.</p>
      <?php endif; ?>
    </section>

    <div class="bm-howworks" aria-labelledby="bm-howworks-title">
      <header class="bm-howworks-head">
        <span class="bm-howworks-eyebrow"><?php esc_html_e( 'HOW BITMOMO WORKS', 'bitmomo' ); ?></span>
        <h2 id="bm-howworks-title"><?php esc_html_e( 'Data pasar menjadi pembacaan yang bisa diuji.', 'bitmomo' ); ?></h2>
      </header>

      <ol class="bm-howworks-steps">
        <li>
          <span class="bm-howworks-step-label"><?php esc_html_e( '01 · UNDERSTAND NOW', 'bitmomo' ); ?></span>
          <p><?php esc_html_e( 'BTC Intelligence merangkum kondisi, perubahan material, maknanya, dan satu konteks pantauan untuk membantu memahami pasar sekarang.', 'bitmomo' ); ?></p>
        </li>
        <li>
          <span class="bm-howworks-step-label"><?php esc_html_e( '02 · MAP WHAT CHANGES', 'bitmomo' ); ?></span>
          <p><?php esc_html_e( 'Bitmomo Pro menambahkan Expected Range, Scenario Map, invalidasi tesis, dan perubahan sejak brief sebelumnya.', 'bitmomo' ); ?></p>
        </li>
        <li>
          <span class="bm-howworks-step-label"><?php esc_html_e( '03 · AUDIT THE RESULT', 'bitmomo' ); ?></span>
          <p><?php esc_html_e( 'Analisis dicatat sebelum hasil pasar diketahui lalu dibandingkan dengan hasil aktual. Data yang tidak memenuhi standar tidak dipaksakan menjadi analisis.', 'bitmomo' ); ?></p>
        </li>
      </ol>
    </div>
  </div>
</section>
<?php unset(
    $bm_history, $bm_days, $bm_counts, $bm_day, $bm_day_bias, $bm_date, $bm_date_label, $bm_ledger,
    $bm_ledger_rows, $bm_row, $bm_direction, $bm_verdict, $bm_proof, $bm_proof_rows, $bm_proof_row,
    $bm_bias_labels, $bm_verdict_labels, $bm_price, $bm_return, $bm_wib, $bm_range_low, $bm_range_high,
    $bm_range_ref, $bm_range_outcome, $bm_range_span, $bm_ref_pct, $bm_outcome_pct
); ?>

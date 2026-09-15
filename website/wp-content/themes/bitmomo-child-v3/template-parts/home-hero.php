<?php
/** Homepage hero: show current BTC intelligence, then real delayed proof. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_snapshot = class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
    ? Bitmomo_Public_Intelligence_Adapter::snapshot()
    : null;
$bm_status = is_array( $bm_snapshot ) ? sanitize_key( (string) ( $bm_snapshot['status'] ?? '' ) ) : '';
$bm_snapshot_available = is_array( $bm_snapshot ) && in_array( $bm_status, array( 'fresh', 'delayed' ), true );
$bm_current_available = $bm_snapshot_available && 'fresh' === $bm_status;
$bm_delayed = $bm_snapshot_available && 'delayed' === $bm_status;

$bm_bias_labels = array( 'bullish' => 'Bullish', 'neutral' => 'Netral', 'bearish' => 'Bearish' );
$bm_direction_labels = array(
    'strong_bearish' => 'Bearish kuat',
    'bearish'        => 'Bearish',
    'neutral'        => 'Netral',
    'bullish'        => 'Bullish',
    'strong_bullish' => 'Bullish kuat',
);
$bm_activity_labels = array( 'high' => 'Tinggi', 'normal' => 'Normal', 'low' => 'Rendah' );

$bm_bias = $bm_current_available ? sanitize_key( (string) ( $bm_snapshot['directional_bias'] ?? '' ) ) : '';
$bm_bias_label = $bm_delayed ? 'Ditahan' : ( $bm_bias_labels[ $bm_bias ] ?? 'Belum tersedia' );
$bm_confidence = $bm_current_available && isset( $bm_snapshot['confidence']['value'] ) && is_numeric( $bm_snapshot['confidence']['value'] )
    ? max( 0, min( 100, (int) $bm_snapshot['confidence']['value'] ) )
    : null;
$bm_confidence_key = $bm_current_available ? sanitize_key( (string) ( $bm_snapshot['confidence']['label'] ?? '' ) ) : '';
$bm_confidence_labels = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
$bm_confidence_label = $bm_confidence_labels[ $bm_confidence_key ] ?? '';
$bm_confidence_display = $bm_delayed ? 'Ditahan' : ( null !== $bm_confidence ? ( $bm_confidence_label ? $bm_confidence_label . ' · ' : '' ) . $bm_confidence . '/100' : 'Belum tersedia' );

$bm_price = $bm_current_available && isset( $bm_snapshot['btc_reference_price'] ) && is_numeric( $bm_snapshot['btc_reference_price'] )
    ? (float) $bm_snapshot['btc_reference_price']
    : 0.0;
$bm_price_label = $bm_delayed ? 'Ditahan' : ( $bm_price > 0 ? '$' . number_format( $bm_price, 0, '.', ',' ) : 'Belum tersedia' );

$bm_opportunity = $bm_snapshot_available && is_array( $bm_snapshot['opportunity'] ?? null ) ? $bm_snapshot['opportunity'] : array();
$bm_activity_state = 'available' === sanitize_key( (string) ( $bm_opportunity['status'] ?? '' ) )
    ? sanitize_key( strtolower( (string) ( $bm_opportunity['state'] ?? '' ) ) )
    : '';
$bm_activity_label = $bm_activity_labels[ $bm_activity_state ] ?? 'Belum tersedia';

$bm_session = $bm_current_available && is_array( $bm_snapshot['session'] ?? null ) ? $bm_snapshot['session'] : array();
$bm_session_label = trim( (string) ( $bm_session['label'] ?? '' ) );
$bm_session_intelligence = $bm_current_available && is_array( $bm_snapshot['session_intelligence'] ?? null ) ? $bm_snapshot['session_intelligence'] : array();

$bm_change_line = '';
$bm_comparison = is_array( $bm_session_intelligence['comparison'] ?? null ) ? $bm_session_intelligence['comparison'] : array();
$bm_changes = is_array( $bm_session_intelligence['what_changed'] ?? null ) ? $bm_session_intelligence['what_changed'] : array();
if ( 'compared' === ( $bm_comparison['status'] ?? '' ) ) {
    foreach ( $bm_changes as $bm_change ) {
        if ( ! is_array( $bm_change ) ) continue;
        $bm_field = sanitize_key( (string) ( $bm_change['field'] ?? '' ) );
        if ( 'directional_bias' === $bm_field ) {
            $bm_from = $bm_bias_labels[ sanitize_key( (string) ( $bm_change['from'] ?? '' ) ) ] ?? '';
            $bm_to = $bm_bias_labels[ sanitize_key( (string) ( $bm_change['to'] ?? '' ) ) ] ?? '';
            if ( $bm_from && $bm_to ) $bm_change_line = sprintf( 'Bias: %s → %s', $bm_from, $bm_to );
        } elseif ( 'direction_strength' === $bm_field ) {
            $bm_from = $bm_direction_labels[ sanitize_key( (string) ( $bm_change['from'] ?? '' ) ) ] ?? '';
            $bm_to = $bm_direction_labels[ sanitize_key( (string) ( $bm_change['to'] ?? '' ) ) ] ?? '';
            if ( $bm_from && $bm_to ) $bm_change_line = sprintf( 'Kekuatan arah: %s → %s', $bm_from, $bm_to );
        } elseif ( 'confidence' === $bm_field ) {
            $bm_change_line = sprintf( 'Confidence: %d → %d', max( 0, min( 100, (int) ( $bm_change['from'] ?? 0 ) ) ), max( 0, min( 100, (int) ( $bm_change['to'] ?? 0 ) ) ) );
        } elseif ( 'opportunity_state' === $bm_field ) {
            $bm_from = $bm_activity_labels[ sanitize_key( strtolower( (string) ( $bm_change['from'] ?? '' ) ) ) ] ?? '';
            $bm_to = $bm_activity_labels[ sanitize_key( strtolower( (string) ( $bm_change['to'] ?? '' ) ) ) ] ?? '';
            if ( $bm_from && $bm_to ) $bm_change_line = sprintf( 'Aktivitas: %s → %s', $bm_from, $bm_to );
        }
        if ( $bm_change_line ) break;
    }
}
if ( ! $bm_change_line ) $bm_change_line = $bm_current_available ? 'Belum ada perubahan material dari pembacaan pembanding.' : 'Menunggu data yang memenuhi standar publikasi.';

$bm_why_map = array(
    'directional_context_changed' => 'Arah dominan berubah; konteks sebelumnya tidak lagi dibaca dengan cara yang sama.',
    'evidence_strength_changed'   => 'Kekuatan bukti berubah; keyakinan terhadap pembacaan saat ini ikut bergeser.',
    'market_structure_changed'    => 'Struktur pasar berubah; respons harga berikutnya menjadi lebih penting untuk konfirmasi.',
    'leading_evidence_changed'    => 'Faktor utama berpindah; pendorong pembacaan saat ini tidak sama dengan brief pembanding.',
);
$bm_why_line = '';
foreach ( (array) ( $bm_session_intelligence['why_it_matters'] ?? array() ) as $bm_why_code ) {
    $bm_why_key = sanitize_key( (string) $bm_why_code );
    if ( isset( $bm_why_map[ $bm_why_key ] ) ) { $bm_why_line = $bm_why_map[ $bm_why_key ]; break; }
}
if ( ! $bm_why_line ) $bm_why_line = $bm_current_available ? 'Belum ada perubahan material yang membutuhkan penjelasan tambahan.' : 'Makna perubahan ditahan sampai pembacaan current valid.';

$bm_watch_map = array(
    'directional_consistency' => 'Apakah kekuatan arah tetap konsisten pada pembacaan berikutnya.',
    'structure_continuity'    => 'Apakah struktur harga tetap mendukung bias saat ini.',
);
$bm_watch_line = '';
foreach ( (array) ( $bm_session_intelligence['what_to_watch'] ?? array() ) as $bm_watch_code ) {
    $bm_watch_key = sanitize_key( (string) $bm_watch_code );
    if ( isset( $bm_watch_map[ $bm_watch_key ] ) ) { $bm_watch_line = $bm_watch_map[ $bm_watch_key ]; break; }
}
if ( ! $bm_watch_line ) $bm_watch_line = $bm_current_available ? 'Belum ada konteks pantauan publik tambahan.' : 'Pantauan current ditahan sampai data kembali valid.';

$bm_drivers = $bm_current_available && is_array( $bm_snapshot['key_drivers'] ?? null ) ? array_values( array_filter( array_map( 'strval', $bm_snapshot['key_drivers'] ) ) ) : array();
$bm_driver = trim( (string) ( $bm_drivers[0] ?? '' ) );
$bm_driver_display = $bm_delayed ? 'Pembacaan saat ini ditahan sampai data kembali memenuhi standar freshness Bitmomo.' : ( $bm_driver ?: 'Analisis terbaru belum tersedia.' );

$bm_updated_iso = $bm_snapshot_available ? trim( (string) ( $bm_snapshot['freshness']['timestamp_iso'] ?? '' ) ) : '';
$bm_updated_ts = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $bm_updated_iso ) ? strtotime( $bm_updated_iso ) : false;
$bm_updated_label = $bm_updated_ts ? ( new DateTimeImmutable( '@' . $bm_updated_ts ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M · H:i' ) . ' WIB' : 'Belum tersedia';
$bm_source = $bm_snapshot_available ? trim( (string) ( $bm_snapshot['provenance']['source'] ?? '' ) ) : '';
$bm_source_label = $bm_source !== '' ? $bm_source : 'Sumber belum tersedia';
$bm_status_label = $bm_delayed ? 'DATA TERTUNDA' : ( $bm_current_available ? 'DATA TERBARU' : 'BELUM TERSEDIA' );
$bm_status_class = $bm_current_available ? '' : ' is-delayed';

$bm_proof_row = null;
if ( class_exists( 'Bitmomo_Btc_Intelligence_Accountability' ) && method_exists( 'Bitmomo_Btc_Intelligence_Accountability', 'delayed_proof' ) ) {
    $bm_proof_contract = Bitmomo_Btc_Intelligence_Accountability::delayed_proof( 1 );
    $bm_proof_rows = is_array( $bm_proof_contract['rows'] ?? null ) ? $bm_proof_contract['rows'] : array();
    $bm_proof_row = ! empty( $bm_proof_rows[0] ) && is_array( $bm_proof_rows[0] ) ? $bm_proof_rows[0] : null;
}
$bm_proof_labels = array( 'aligned' => 'SESUAI', 'missed' => 'TIDAK SESUAI', 'inconclusive' => 'TIDAK KONKLUSIF', 'unscored' => 'BELUM DINILAI' );
$bm_proof_state = $bm_proof_row ? sanitize_key( (string) ( $bm_proof_row['market_state'] ?? '' ) ) : '';
$bm_proof_verdict_key = $bm_proof_row ? sanitize_key( (string) ( $bm_proof_row['verdict'] ?? 'unscored' ) ) : 'unscored';
$bm_proof_verdict = $bm_proof_labels[ $bm_proof_verdict_key ] ?? $bm_proof_labels['unscored'];
$bm_proof_range = $bm_proof_row && is_numeric( $bm_proof_row['expected_range_low'] ?? null ) && is_numeric( $bm_proof_row['expected_range_high'] ?? null )
    ? '$' . number_format( (float) $bm_proof_row['expected_range_low'], 0, '.', ',' ) . ' – $' . number_format( (float) $bm_proof_row['expected_range_high'], 0, '.', ',' )
    : '—';
$bm_proof_return = $bm_proof_row && is_numeric( $bm_proof_row['outcome_return_pct'] ?? null )
    ? ( (float) $bm_proof_row['outcome_return_pct'] > 0 ? '+' : '' ) . number_format( (float) $bm_proof_row['outcome_return_pct'], 2 ) . '%'
    : '—';
$bm_proof_published = '';
if ( $bm_proof_row && ! empty( $bm_proof_row['published_at'] ) && strtotime( (string) $bm_proof_row['published_at'] ) ) {
    $bm_proof_published = ( new DateTimeImmutable( '@' . strtotime( (string) $bm_proof_row['published_at'] ) ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M Y · H:i' ) . ' WIB';
}
?>
<section class="bm-home-hero bm-home-hero--show-first" aria-labelledby="bm-home-title">
  <div class="bm-container">
    <div class="bm-home-hero__grid">
      <div class="bm-home-hero__copy">
        <p class="bm-home-hero__eyebrow">BITMOMO · BTC INTELLIGENCE</p>
        <h1 class="bm-home-hero__title" id="bm-home-title">Satu layar untuk memahami BTC — sekarang, berikutnya, dan setelah hasilnya diketahui.</h1>
        <p class="bm-home-hero__lead">Lihat pembacaan BTC aktual, apa yang berubah, mengapa penting, dan apa yang layak dipantau. Analisis dicatat sebelum outcome diketahui sehingga hasilnya dapat diuji, bukan sekadar dipercaya.</p>
        <div class="bm-home-hero__actions">
          <a class="bm-home-hero__primary" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="hero_primary">Buka BTC Intelligence</a>
          <a class="bm-home-hero__secondary" href="<?php echo esc_url( $bm_proof_row ? '#home-proof' : home_url( '/pro/#pro-example' ) ); ?>" data-bm-event="homepage_proof_click" data-bm-placement="hero_secondary">Lihat bukti historis ↓</a>
        </div>
        <p class="bm-home-hero__notes" aria-label="Karakteristik Bitmomo"><span>BTC ONLY</span><span>RECORDED BEFORE OUTCOME</span><span>BUKAN SINYAL BELI/JUAL</span></p>
      </div>

      <article class="bm-home-reading" aria-label="BTC Intelligence saat ini">
        <header class="bm-home-reading__head">
          <span class="bm-home-reading__label">BTC MARKET VIEW</span>
          <div class="bm-home-reading__status-stack">
            <?php if ( $bm_session_label ) : ?><span class="bm-home-reading__session"><?php echo esc_html( $bm_session_label ); ?></span><?php endif; ?>
            <strong class="bm-home-reading__status<?php echo esc_attr( $bm_status_class ); ?>"><?php echo esc_html( $bm_status_label ); ?></strong>
            <span class="bm-home-reading__updated"><strong>DIPERBARUI</strong> <?php echo esc_html( $bm_updated_label ); ?></span>
          </div>
        </header>

        <div class="bm-home-reading__metrics" role="list">
          <div class="bm-home-reading__metric" role="listitem"><span class="bm-home-reading__metric-label">REFERENSI BTC</span><strong class="bm-home-reading__metric-value"><?php echo esc_html( $bm_price_label ); ?></strong></div>
          <div class="bm-home-reading__metric" role="listitem"><span class="bm-home-reading__metric-label">BIAS</span><strong class="bm-home-reading__metric-value is-<?php echo esc_attr( in_array( $bm_bias, array( 'bullish', 'neutral', 'bearish' ), true ) ? $bm_bias : 'unknown' ); ?>"><?php echo esc_html( $bm_bias_label ); ?></strong></div>
          <div class="bm-home-reading__metric" role="listitem"><span class="bm-home-reading__metric-label">CONFIDENCE</span><strong class="bm-home-reading__metric-value"><?php echo esc_html( $bm_confidence_display ); ?></strong><small>Bukti pendukung, bukan probabilitas arah harga.</small></div>
          <div class="bm-home-reading__metric" role="listitem"><span class="bm-home-reading__metric-label">MARKET PULSE</span><strong class="bm-home-reading__metric-value"><?php echo esc_html( $bm_activity_label ); ?></strong><small>Aktivitas relatif terhadap kondisi normal 14 hari.</small></div>
        </div>

        <div class="bm-home-reading__decisions">
          <div><span>APA YANG BERUBAH</span><p><?php echo esc_html( $bm_change_line ); ?></p></div>
          <div><span>MENGAPA PENTING</span><p><?php echo esc_html( $bm_why_line ); ?></p></div>
          <div><span>PANTAU BERIKUTNYA</span><p><?php echo esc_html( $bm_watch_line ); ?></p></div>
        </div>

        <div class="bm-home-reading__driver"><span>FAKTOR UTAMA</span><p><?php echo esc_html( $bm_driver_display ); ?></p></div>
        <footer class="bm-home-reading__footer">
          <p class="bm-home-reading__source"><strong>SUMBER DATA</strong><br><?php echo esc_html( $bm_source_label ); ?></p>
          <a class="bm-home-reading__link" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="hero_market_card">Buka view lengkap →</a>
        </footer>
      </article>
    </div>

    <?php if ( $bm_proof_row ) : ?>
      <article id="home-proof" class="bm-home-proof-example" aria-label="Contoh keputusan Pro historis yang sudah dievaluasi">
        <header>
          <div><span>HISTORICAL PRO DECISION · RECORDED BEFORE OUTCOME</span><strong><?php echo esc_html( $bm_proof_published ?: 'Arsip historis' ); ?></strong></div>
          <span class="bm-home-proof-example__verdict is-<?php echo esc_attr( $bm_proof_verdict_key ); ?>"><?php echo esc_html( $bm_proof_verdict ); ?></span>
        </header>
        <div class="bm-home-proof-example__metrics">
          <div><span>VIEW</span><strong class="is-<?php echo esc_attr( $bm_proof_state ?: 'unknown' ); ?>"><?php echo esc_html( strtoupper( $bm_proof_state ?: '—' ) ); ?></strong></div>
          <div><span>EXPECTED RANGE</span><strong><?php echo esc_html( $bm_proof_range ); ?></strong></div>
          <div><span>HASIL +24H</span><strong><?php echo esc_html( $bm_proof_return ); ?></strong></div>
          <div><span>RANGE HIT</span><strong><?php echo esc_html( 'yes' === ( $bm_proof_row['range_hit'] ?? '' ) ? 'YA' : ( 'no' === ( $bm_proof_row['range_hit'] ?? '' ) ? 'TIDAK' : '—' ) ); ?></strong></div>
        </div>
        <?php if ( ! empty( $bm_proof_row['base_scenario'] ) ) : ?><p class="bm-home-proof-example__base"><span>BASE CASE</span><?php echo esc_html( sanitize_textarea_field( (string) $bm_proof_row['base_scenario'] ) ); ?></p><?php endif; ?>
        <footer><span>Arsip ≥48 jam · bukan guidance saat ini</span><a href="<?php echo esc_url( home_url( '/pro/#pro-example' ) ); ?>" data-bm-event="homepage_pro_interest" data-bm-placement="historical_proof">Lihat bagaimana Pro bekerja →</a></footer>
      </article>
    <?php endif; ?>

    <div class="bm-home-proof" aria-label="Prinsip akuntabilitas Bitmomo">
      <div class="bm-home-proof__item"><span class="bm-home-proof__kicker">RECORDED LIVE</span><p>Tesis dibekukan sebelum hasil diketahui.</p></div>
      <div class="bm-home-proof__item"><span class="bm-home-proof__kicker">NO CHERRY PICKING</span><p>Hasil sesuai, tidak sesuai, dan tidak konklusif tetap dicatat.</p></div>
      <div class="bm-home-proof__item"><span class="bm-home-proof__kicker">FAIL CLOSED</span><p>Data yang tidak memenuhi standar tidak dipaksakan menjadi analisis.</p></div>
    </div>
  </div>
</section>
<?php unset(
    $bm_snapshot, $bm_status, $bm_snapshot_available, $bm_current_available, $bm_delayed, $bm_bias_labels, $bm_direction_labels,
    $bm_activity_labels, $bm_bias, $bm_bias_label, $bm_confidence, $bm_confidence_key, $bm_confidence_labels,
    $bm_confidence_label, $bm_confidence_display, $bm_price, $bm_price_label, $bm_opportunity, $bm_activity_state,
    $bm_activity_label, $bm_session, $bm_session_label, $bm_session_intelligence, $bm_change_line, $bm_comparison,
    $bm_changes, $bm_change, $bm_field, $bm_from, $bm_to, $bm_why_map, $bm_why_line, $bm_why_code, $bm_why_key,
    $bm_watch_map, $bm_watch_line, $bm_watch_code, $bm_watch_key, $bm_drivers, $bm_driver, $bm_driver_display,
    $bm_updated_iso, $bm_updated_ts, $bm_updated_label, $bm_source, $bm_source_label, $bm_status_label, $bm_status_class,
    $bm_proof_row, $bm_proof_contract, $bm_proof_rows, $bm_proof_labels, $bm_proof_state, $bm_proof_verdict_key,
    $bm_proof_verdict, $bm_proof_range, $bm_proof_return, $bm_proof_published
); ?>
<?php
/** Homepage hero: show current public BTC intelligence before product explanation. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_snapshot = class_exists( 'Bitmomo_Public_Intelligence_Adapter' )
    ? Bitmomo_Public_Intelligence_Adapter::snapshot()
    : null;
$bm_status = is_array( $bm_snapshot ) ? sanitize_key( (string) ( $bm_snapshot['status'] ?? '' ) ) : '';
$bm_snapshot_available = is_array( $bm_snapshot ) && in_array( $bm_status, array( 'fresh', 'delayed' ), true );
$bm_current_available = $bm_snapshot_available && 'fresh' === $bm_status;
$bm_delayed = $bm_snapshot_available && 'delayed' === $bm_status;

$bm_bias = $bm_current_available ? sanitize_key( (string) ( $bm_snapshot['directional_bias'] ?? '' ) ) : '';
$bm_bias_labels = array( 'bullish' => 'Bullish', 'neutral' => 'Netral', 'bearish' => 'Bearish' );
$bm_bias_label = $bm_delayed ? 'Ditahan' : ( isset( $bm_bias_labels[ $bm_bias ] ) ? $bm_bias_labels[ $bm_bias ] : 'Belum tersedia' );

$bm_confidence = $bm_current_available && isset( $bm_snapshot['confidence']['value'] ) && is_numeric( $bm_snapshot['confidence']['value'] )
    ? max( 0, min( 100, (int) $bm_snapshot['confidence']['value'] ) )
    : null;
$bm_confidence_key = $bm_current_available ? sanitize_key( (string) ( $bm_snapshot['confidence']['label'] ?? '' ) ) : '';
$bm_confidence_labels = array( 'high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah' );
$bm_confidence_label = isset( $bm_confidence_labels[ $bm_confidence_key ] ) ? $bm_confidence_labels[ $bm_confidence_key ] : '';
$bm_confidence_display = $bm_delayed
    ? 'Ditahan'
    : ( null !== $bm_confidence ? ( $bm_confidence . '/100' . ( $bm_confidence_label ? ' · ' . $bm_confidence_label : '' ) ) : 'Belum tersedia' );

$bm_price = $bm_current_available && isset( $bm_snapshot['btc_reference_price'] ) && is_numeric( $bm_snapshot['btc_reference_price'] )
    ? (float) $bm_snapshot['btc_reference_price']
    : 0.0;
$bm_price_label = $bm_delayed ? 'Ditahan' : ( $bm_price > 0 ? '$' . number_format( $bm_price, 0, '.', ',' ) : 'Belum tersedia' );

$bm_drivers = $bm_current_available && is_array( $bm_snapshot['key_drivers'] ?? null )
    ? array_values( array_filter( array_map( 'strval', $bm_snapshot['key_drivers'] ) ) )
    : array();
$bm_driver = trim( (string) ( $bm_drivers[0] ?? '' ) );
$bm_driver_display = $bm_delayed
    ? 'Faktor pasar terbaru tidak ditampilkan karena Major Brief sedang tertunda.'
    : ( $bm_driver ?: 'Faktor pasar utama belum tersedia.' );

$bm_updated_iso = $bm_snapshot_available ? trim( (string) ( $bm_snapshot['freshness']['timestamp_iso'] ?? '' ) ) : '';
$bm_updated_ts = preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $bm_updated_iso ) ? strtotime( $bm_updated_iso ) : false;
$bm_updated_label = $bm_updated_ts
    ? ( new DateTimeImmutable( '@' . $bm_updated_ts ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'd M · H:i' ) . ' WIB'
    : 'Belum tersedia';
$bm_updated_note = $bm_delayed ? 'Observasi terverifikasi terakhir.' : 'Waktu Major Brief terbaru.';

$bm_source = $bm_snapshot_available ? trim( (string) ( $bm_snapshot['provenance']['source'] ?? '' ) ) : '';
$bm_source_lc = strtolower( $bm_source );
$bm_source_label = false !== strpos( $bm_source_lc, 'bybit' )
    ? 'Binance + Bybit'
    : ( false !== strpos( $bm_source_lc, 'binance' ) ? 'Binance' : ( $bm_source !== '' ? 'Sumber data terverifikasi' : 'Belum tersedia' ) );
$bm_status_label = $bm_delayed ? 'DATA TERTUNDA' : ( $bm_current_available ? 'DATA TERBARU' : 'BELUM TERSEDIA' );
$bm_status_class = $bm_current_available ? '' : ' is-delayed';

$bm_session_intelligence = $bm_current_available && is_array( $bm_snapshot['session_intelligence'] ?? null )
    ? $bm_snapshot['session_intelligence']
    : array();
$bm_comparison = is_array( $bm_session_intelligence['comparison'] ?? null ) ? $bm_session_intelligence['comparison'] : array();
$bm_changes = is_array( $bm_session_intelligence['what_changed'] ?? null ) ? $bm_session_intelligence['what_changed'] : array();
$bm_what_happened = is_array( $bm_session_intelligence['what_happened'] ?? null ) ? $bm_session_intelligence['what_happened'] : array();

$bm_direction_labels = array(
    'strong_bearish' => 'Bearish kuat',
    'bearish'        => 'Bearish',
    'neutral'        => 'Netral',
    'bullish'        => 'Bullish',
    'strong_bullish' => 'Bullish kuat',
);
$bm_label_bias = static function ( $value ) use ( $bm_bias_labels ) {
    $key = sanitize_key( (string) $value );
    return isset( $bm_bias_labels[ $key ] ) ? $bm_bias_labels[ $key ] : '';
};
$bm_label_direction = static function ( $value ) use ( $bm_direction_labels ) {
    $key = sanitize_key( (string) $value );
    return isset( $bm_direction_labels[ $key ] ) ? $bm_direction_labels[ $key ] : '';
};

$bm_changed_lines = array();
if ( 'compared' === ( $bm_comparison['status'] ?? '' ) ) {
    foreach ( $bm_changes as $bm_change ) {
        if ( ! is_array( $bm_change ) ) continue;
        $bm_field = sanitize_key( (string) ( $bm_change['field'] ?? '' ) );
        if ( 'directional_bias' === $bm_field ) {
            $bm_from = $bm_label_bias( $bm_change['from'] ?? '' );
            $bm_to = $bm_label_bias( $bm_change['to'] ?? '' );
            if ( $bm_from && $bm_to ) $bm_changed_lines[] = sprintf( 'Bias berubah dari %s menjadi %s.', $bm_from, $bm_to );
        } elseif ( 'direction_strength' === $bm_field ) {
            $bm_from = $bm_label_direction( $bm_change['from'] ?? '' );
            $bm_to = $bm_label_direction( $bm_change['to'] ?? '' );
            if ( $bm_from && $bm_to ) $bm_changed_lines[] = sprintf( 'Kekuatan arah berubah dari %s menjadi %s.', $bm_from, $bm_to );
        } elseif ( 'market_state' === $bm_field ) {
            $bm_changed_lines[] = 'Konteks pasar berubah dibanding brief sebelumnya.';
        } elseif ( 'structural_state' === $bm_field ) {
            $bm_changed_lines[] = 'Struktur harga berubah dibanding brief sebelumnya.';
        } elseif ( 'confidence' === $bm_field ) {
            $bm_from = max( 0, min( 100, (int) ( $bm_change['from'] ?? 0 ) ) );
            $bm_to = max( 0, min( 100, (int) ( $bm_change['to'] ?? 0 ) ) );
            $bm_changed_lines[] = sprintf( 'Confidence berubah dari %d menjadi %d.', $bm_from, $bm_to );
        } elseif ( 'strongest_driver' === $bm_field ) {
            $bm_to = trim( (string) ( $bm_change['to'] ?? '' ) );
            if ( $bm_to !== '' ) $bm_changed_lines[] = 'Faktor dominan berubah: ' . $bm_to;
        }
        if ( count( $bm_changed_lines ) >= 2 ) break;
    }
}

$bm_why_map = array(
    'directional_context_changed' => 'Arah dominan berubah; konteks analisis sebelumnya tidak lagi sepenuhnya berlaku.',
    'evidence_strength_changed'   => 'Kekuatan bukti berubah; tingkat keyakinan terhadap analisis saat ini ikut bergeser.',
    'market_structure_changed'    => 'Struktur harga berubah; respons berikutnya menjadi lebih penting untuk konfirmasi.',
    'leading_evidence_changed'    => 'Faktor utama berpindah; pendorong analisis saat ini berbeda dari brief pembanding.',
);
$bm_why_lines = array();
foreach ( (array) ( $bm_session_intelligence['why_it_matters'] ?? array() ) as $bm_code ) {
    $bm_key = sanitize_key( (string) $bm_code );
    if ( isset( $bm_why_map[ $bm_key ] ) && ! in_array( $bm_why_map[ $bm_key ], $bm_why_lines, true ) ) {
        $bm_why_lines[] = $bm_why_map[ $bm_key ];
    }
    if ( count( $bm_why_lines ) >= 2 ) break;
}

$bm_watch_map = array(
    'directional_consistency' => 'Apakah kekuatan arah tetap konsisten pada brief berikutnya.',
    'structure_continuity'    => 'Apakah struktur harga tetap mendukung bias saat ini.',
);
$bm_watch_line = '';
foreach ( (array) ( $bm_session_intelligence['what_to_watch'] ?? array() ) as $bm_code ) {
    $bm_key = sanitize_key( (string) $bm_code );
    if ( isset( $bm_watch_map[ $bm_key ] ) ) {
        $bm_watch_line = $bm_watch_map[ $bm_key ];
        break;
    }
}

$bm_happened_line = '';
if ( $bm_current_available && isset( $bm_what_happened['btc_change_pct'] ) && is_numeric( $bm_what_happened['btc_change_pct'] ) ) {
    $bm_change_pct = (float) $bm_what_happened['btc_change_pct'];
    $bm_happened_line = sprintf( 'BTC bergerak %s%s%% dalam 24 jam menuju brief ini.', $bm_change_pct > 0 ? '+' : '', number_format_i18n( $bm_change_pct, 2 ) );
}
if ( $bm_happened_line === '' && $bm_driver ) $bm_happened_line = $bm_driver;
?>
<section class="bm-home-hero" aria-labelledby="bm-home-title">
  <div class="bm-container">
    <div class="bm-home-hero__grid">
      <div class="bm-home-hero__copy">
        <p class="bm-home-hero__eyebrow">BTC MARKET INTELLIGENCE</p>
        <h1 class="bm-home-hero__title" id="bm-home-title">Apa yang berubah di Bitcoin hari ini?</h1>
        <p class="bm-home-hero__lead">Bitmomo menganalisis kondisi pasar, perubahan antar-brief, dan kekuatan bukti. Analisis terbaru tampil langsung di halaman ini dan disimpan untuk evaluasi.</p>

        <div class="bm-home-hero__actions">
          <a class="bm-home-hero__primary" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="hero_primary">Buka BTC Intelligence</a>
          <a class="bm-home-hero__secondary" href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>" data-bm-event="homepage_decision_ledger_click" data-bm-placement="hero_secondary">Periksa rekam jejak →</a>
        </div>

        <p class="bm-home-hero__notes" aria-label="Karakteristik Bitmomo">
          <span>BTC ONLY</span>
          <span>TIMESTAMPED</span>
          <span>BUKAN SINYAL BELI/JUAL</span>
        </p>
      </div>

      <article class="bm-home-reading" aria-label="Analisis BTC terbaru">
        <header class="bm-home-reading__head">
          <span class="bm-home-reading__label">BTC MARKET VIEW</span>
          <strong class="bm-home-reading__status<?php echo esc_attr( $bm_status_class ); ?>"><?php echo esc_html( $bm_status_label ); ?></strong>
        </header>

        <div class="bm-home-reading__metrics" role="list">
          <div class="bm-home-reading__metric" role="listitem">
            <span class="bm-home-reading__metric-label">BIAS</span>
            <strong class="bm-home-reading__metric-value is-<?php echo esc_attr( in_array( $bm_bias, array( 'bullish', 'neutral', 'bearish' ), true ) ? $bm_bias : 'unknown' ); ?>"><?php echo esc_html( $bm_bias_label ); ?></strong>
            <small><?php echo esc_html( $bm_delayed ? 'Bias terbaru tidak ditampilkan dari brief yang tertunda.' : 'Arah dominan berdasarkan data pasar saat ini.' ); ?></small>
          </div>
          <div class="bm-home-reading__metric" role="listitem">
            <span class="bm-home-reading__metric-label">CONFIDENCE</span>
            <strong class="bm-home-reading__metric-value"><?php echo esc_html( $bm_confidence_display ); ?></strong>
            <small><?php echo esc_html( $bm_delayed ? 'Confidence terbaru ditahan sampai Major Brief kembali valid.' : 'Konsistensi bukti pendukung; bukan probabilitas pergerakan harga.' ); ?></small>
          </div>
          <div class="bm-home-reading__metric" role="listitem">
            <span class="bm-home-reading__metric-label">REFERENSI BTC</span>
            <strong class="bm-home-reading__metric-value"><?php echo esc_html( $bm_price_label ); ?></strong>
            <small><?php echo esc_html( $bm_delayed ? 'Harga referensi terkini tidak ditampilkan dari brief yang tertunda.' : 'Harga referensi ketika analisis dibuat.' ); ?></small>
          </div>
          <div class="bm-home-reading__metric" role="listitem">
            <span class="bm-home-reading__metric-label">DIPERBARUI</span>
            <strong class="bm-home-reading__metric-value"><?php echo esc_html( $bm_updated_label ); ?></strong>
            <small><?php echo esc_html( $bm_updated_note ); ?></small>
          </div>
        </div>

        <div class="bm-home-reading__driver">
          <span>FAKTOR UTAMA</span>
          <p><?php echo esc_html( $bm_driver_display ); ?></p>
        </div>

        <footer class="bm-home-reading__footer">
          <p class="bm-home-reading__source"><strong>SUMBER DATA</strong><br><?php echo esc_html( $bm_source_label ); ?></p>
          <a class="bm-home-reading__link" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="hero_market_card">Buka analisis lengkap →</a>
        </footer>
      </article>
    </div>

    <div class="bm-home-brief" aria-label="Ringkasan intelligence BTC terbaru">
      <article class="bm-home-brief__panel">
        <span>APA YANG TERJADI</span>
        <p><?php echo esc_html( $bm_current_available ? ( $bm_happened_line ?: 'Belum ada ringkasan pergerakan yang memenuhi standar publikasi.' ) : 'Analisis terbaru sedang ditahan sampai Major Brief kembali valid.' ); ?></p>
      </article>
      <article class="bm-home-brief__panel">
        <span>APA YANG BERUBAH</span>
        <?php if ( $bm_current_available && $bm_changed_lines ) : ?>
          <ul><?php foreach ( $bm_changed_lines as $bm_line ) : ?><li><?php echo esc_html( $bm_line ); ?></li><?php endforeach; ?></ul>
        <?php else : ?>
          <p><?php echo esc_html( $bm_current_available ? 'Belum ada perubahan material dibanding brief sebelumnya.' : 'Perubahan antar-brief tidak ditampilkan dari data yang tertunda.' ); ?></p>
        <?php endif; ?>
      </article>
      <article class="bm-home-brief__panel">
        <span>MENGAPA PENTING</span>
        <?php if ( $bm_current_available && $bm_why_lines ) : ?>
          <ul><?php foreach ( $bm_why_lines as $bm_line ) : ?><li><?php echo esc_html( $bm_line ); ?></li><?php endforeach; ?></ul>
        <?php else : ?>
          <p><?php echo esc_html( $bm_current_available ? 'Belum ada perubahan material yang membutuhkan penjelasan tambahan.' : 'Penjelasan terbaru ditahan bersama Major Brief.' ); ?></p>
        <?php endif; ?>
      </article>
      <article class="bm-home-brief__panel">
        <span>PANTAU BERIKUTNYA</span>
        <p><?php echo esc_html( $bm_current_available ? ( $bm_watch_line ?: 'Belum ada konteks pantauan publik yang memenuhi standar saat ini.' ) : 'Konteks pantauan terbaru akan kembali tampil setelah Major Brief valid.' ); ?></p>
      </article>
    </div>
  </div>
</section>
<?php unset(
    $bm_snapshot, $bm_status, $bm_snapshot_available, $bm_current_available, $bm_delayed,
    $bm_bias, $bm_bias_labels, $bm_bias_label, $bm_confidence, $bm_confidence_key, $bm_confidence_labels,
    $bm_confidence_label, $bm_confidence_display, $bm_price, $bm_price_label, $bm_drivers, $bm_driver,
    $bm_driver_display, $bm_updated_iso, $bm_updated_ts, $bm_updated_label, $bm_updated_note, $bm_source,
    $bm_source_lc, $bm_source_label, $bm_status_label, $bm_status_class, $bm_session_intelligence,
    $bm_comparison, $bm_changes, $bm_what_happened, $bm_direction_labels, $bm_label_bias, $bm_label_direction,
    $bm_changed_lines, $bm_why_map, $bm_why_lines, $bm_watch_map, $bm_watch_line, $bm_happened_line,
    $bm_change_pct, $bm_change, $bm_field, $bm_from, $bm_to, $bm_code, $bm_key, $bm_line
); ?>
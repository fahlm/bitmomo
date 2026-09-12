<?php
/**
 * Turns one BTC Daily Intelligence record into reusable content assets
 * (X/Twitter post, YouTube Shorts script outline, newsletter blurb).
 *
 * No auto-publishing -- this only generates copyable text for a human
 * to paste into X, a Shorts editor, or the newsletter tool. Viewable at
 * Tools -> BTC Content Assets (admin only).
 *
 * @package Bitmomo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function bitmomo_btc_record_is_fresh( array $record, $max_age_seconds = 36 * HOUR_IN_SECONDS ) {
    $timestamp = strtotime( (string) ( $record['timestamp'] ?? '' ) );
    if ( ! $timestamp || $timestamp > time() + ( 5 * MINUTE_IN_SECONDS ) ) return false;
    return ( time() - $timestamp ) <= max( HOUR_IN_SECONDS, (int) $max_age_seconds );
}

function bitmomo_build_btc_content_assets( array $record ) {
    $direction  = strtoupper( (string) ( $record['direction'] ?? 'NEUTRAL' ) );
    $confidence = max( 0, min( 100, (int) ( $record['confidence'] ?? 0 ) ) );
    $price      = (float) ( $record['btc_reference_price'] ?? 0 );
    $low        = (float) ( $record['expected_low'] ?? 0 );
    $high       = (float) ( $record['expected_high'] ?? 0 );
    $move       = (float) ( $record['expected_move_pct'] ?? 0 );
    $consensus  = wp_parse_args(
        is_array( $record['consensus'] ?? null ) ? $record['consensus'] : array(),
        array( 'bullish' => 0, 'neutral' => 0, 'bearish' => 0 )
    );
    $drivers      = is_array( $record['key_drivers'] ?? null ) ? $record['key_drivers'] : array();
    $invalidation = (string) ( $record['invalidation'] ?? '' );
    $top_driver   = $drivers[0] ?? '';
    $site_url     = home_url( '/' );
    $emoji_map    = array( 'BULLISH' => '🟢', 'BEARISH' => '🔴', 'NEUTRAL' => '⚪' );
    $emoji        = $emoji_map[ $direction ] ?? '⚪';
    $price_fmt    = '$' . number_format_i18n( $price, 0 );
    $low_fmt      = '$' . number_format_i18n( $low, 0 );
    $high_fmt     = '$' . number_format_i18n( $high, 0 );

    $x_post = sprintf(
        "%s BTC Daily Outlook: %s (%d%% confidence)\nHarga referensi: %s | Range 24h: %s–%s (±%.1f%%)\nKonsensus: %d bullish / %d neutral / %d bearish\n%s\nBukan nasihat finansial. Analisis lengkap: %s\n#Bitcoin #BTC #Bitmomo",
        $emoji, $direction, $confidence, $price_fmt, $low_fmt, $high_fmt, $move,
        $consensus['bullish'], $consensus['neutral'], $consensus['bearish'],
        $top_driver ? 'Driver utama: ' . $top_driver : '', $site_url
    );

    $shorts_lines   = array();
    $shorts_lines[] = 'HOOK: BTC hari ini ' . strtolower( $direction ) . ' -- ini yang perlu kamu tahu.';
    $shorts_lines[] = '';
    $shorts_lines[] = 'BODY:';
    $shorts_lines[] = '- Harga referensi: ' . $price_fmt;
    $shorts_lines[] = sprintf(
        '- Confidence: %d%% (konsensus %d bullish / %d neutral / %d bearish)',
        $confidence, $consensus['bullish'], $consensus['neutral'], $consensus['bearish']
    );
    $shorts_lines[] = sprintf( '- Expected range 24 jam: %s - %s (±%.1f%%)', $low_fmt, $high_fmt, $move );
    foreach ( $drivers as $driver ) $shorts_lines[] = '- Driver: ' . $driver;
    if ( $invalidation ) $shorts_lines[] = '- Invalidation: ' . $invalidation;
    $shorts_lines[] = '';
    $shorts_lines[] = 'CTA: Full breakdown & data harian di bitmomo.id -- link di bio.';
    $shorts_script  = implode( "\n", $shorts_lines );

    $timestamp_int = strtotime( (string) ( $record['timestamp'] ?? 'now' ) );
    $newsletter = sprintf(
        "BTC Daily Intelligence -- %s\n\nBitcoin saat ini berada di %s dengan outlook %s (tingkat keyakinan %d%%, berdasarkan konsensus %d bullish, %d neutral, dan %d bearish dari model AI kami). Model memperkirakan pergerakan 24 jam berada di kisaran %s hingga %s (±%.1f%%).%s\n\n%s\n\nSeperti biasa, ini adalah alat bantu keputusan, bukan sinyal beli/jual -- selalu lakukan riset Anda sendiri. Baca analisis lengkap di %s.",
        $timestamp_int ? wp_date( 'd M Y', $timestamp_int ) : '',
        $price_fmt, $direction, $confidence,
        $consensus['bullish'], $consensus['neutral'], $consensus['bearish'],
        $low_fmt, $high_fmt, $move,
        $top_driver ? ( ' Driver utama saat ini: ' . $top_driver . '.' ) : '',
        $invalidation ? ( 'Skenario ini batal jika: ' . $invalidation ) : '',
        $site_url
    );

    return array(
        'x_post'        => trim( $x_post ),
        'shorts_script' => trim( $shorts_script ),
        'newsletter'    => trim( $newsletter ),
    );
}

add_action( 'admin_menu', 'bitmomo_register_content_assets_page' );
add_action( 'admin_enqueue_scripts', 'bitmomo_enqueue_content_assets_admin_style' );

function bitmomo_register_content_assets_page() {
    add_management_page(
        __( 'BTC Content Assets', 'bitmomo' ),
        __( 'BTC Content Assets', 'bitmomo' ),
        'manage_options',
        'bitmomo-btc-content-assets',
        'bitmomo_render_content_assets_page'
    );
}

function bitmomo_enqueue_content_assets_admin_style( $hook_suffix ) {
    if ( 'tools_page_bitmomo-btc-content-assets' !== $hook_suffix ) return;
    $path = get_stylesheet_directory() . '/assets/css/admin/content-assets.css';
    if ( ! file_exists( $path ) ) return;
    wp_enqueue_style(
        'bitmomo-content-assets-admin',
        get_stylesheet_directory_uri() . '/assets/css/admin/content-assets.css',
        array(),
        (string) filemtime( $path )
    );
}

function bitmomo_render_content_assets_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $file   = get_stylesheet_directory() . '/data/btc-daily-sample.json';
    $record = null;
    if ( file_exists( $file ) ) {
        $raw     = file_get_contents( $file );
        $decoded = $raw ? json_decode( $raw, true ) : null;
        if ( is_array( $decoded ) && ! empty( $decoded['timestamp'] ) && bitmomo_btc_record_is_fresh( $decoded ) ) $record = $decoded;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'BTC Daily Intelligence — Content Assets', 'bitmomo' ); ?></h1>
        <?php if ( ! $record ) : ?>
            <p><?php esc_html_e( 'Belum ada data BTC Intelligence yang valid dan terbaru (data/btc-daily-sample.json).', 'bitmomo' ); ?></p>
        <?php else :
            $assets = bitmomo_build_btc_content_assets( $record );
            $fields = array(
                'x_post'        => __( 'X / Twitter Post', 'bitmomo' ),
                'shorts_script' => __( 'YouTube Shorts — Script Outline', 'bitmomo' ),
                'newsletter'    => __( 'Newsletter Blurb', 'bitmomo' ),
            );
            foreach ( $fields as $key => $label ) : ?>
                <h2><?php echo esc_html( $label ); ?></h2>
                <textarea readonly rows="8" class="bm-content-assets__field" onclick="this.select();"><?php echo esc_textarea( $assets[ $key ] ); ?></textarea>
            <?php endforeach; ?>
            <p class="bm-content-assets__note">
                <?php esc_html_e( 'Klik teks untuk pilih semua, lalu salin manual (Ctrl/Cmd+C). Belum ada auto-publish ke X/YouTube/email -- ini hanya draft siap-tempel.', 'bitmomo' ); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

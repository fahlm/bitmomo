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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds X post / Shorts script outline / newsletter blurb from one
 * BTC Daily Intelligence record (same shape as data/btc-daily-sample.json).
 */
function bitmomo_build_btc_content_assets( array $record ) {
    $direction  = strtoupper( (string) ( $record['direction'] ?? 'NEUTRAL' ) );
    $confidence = max( 0, min( 100, (int) ( $record['confidence'] ?? 0 ) ) );

    $price = (float) ( $record['btc_reference_price'] ?? 0 );
    $low   = (float) ( $record['expected_low'] ?? 0 );
    $high  = (float) ( $record['expected_high'] ?? 0 );
    $move  = (float) ( $record['expected_move_pct'] ?? 0 );

    $consensus = wp_parse_args(
        is_array( $record['consensus'] ?? null ) ? $record['consensus'] : [],
        [ 'bullish' => 0, 'neutral' => 0, 'bearish' => 0 ]
    );

    $drivers      = is_array( $record['key_drivers'] ?? null ) ? $record['key_drivers'] : [];
    $invalidation = (string) ( $record['invalidation'] ?? '' );
    $top_driver   = $drivers[0] ?? '';
    $site_url     = home_url( '/' );

    $emoji_map = [ 'BULLISH' => '🟢', 'BEARISH' => '🔴', 'NEUTRAL' => '⚪' ];
    $emoji     = $emoji_map[ $direction ] ?? '⚪';

    $price_fmt = '$' . number_format_i18n( $price, 0 );
    $low_fmt   = '$' . number_format_i18n( $low, 0 );
    $high_fmt  = '$' . number_format_i18n( $high, 0 );

    /* ---------- X / Twitter post ---------- */
    $x_post = sprintf(
        "%s BTC Daily Outlook: %s (%d%% confidence)\nHarga referensi: %s | Range 24h: %s\xe2\x80\x93%s (\xc2\xb1%.1f%%)\nKonsensus: %d bullish / %d neutral / %d bearish\n%s\nBukan nasihat finansial. Analisis lengkap: %s\n#Bitcoin #BTC #Bitmomo",
        $emoji,
        $direction,
        $confidence,
        $price_fmt,
        $low_fmt,
        $high_fmt,
        $move,
        $consensus['bullish'],
        $consensus['neutral'],
        $consensus['bearish'],
        $top_driver ? 'Driver utama: ' . $top_driver : '',
        $site_url
    );

    /* ---------- YouTube Shorts script outline ---------- */
    $shorts_lines   = [];
    $shorts_lines[] = 'HOOK: BTC hari ini ' . strtolower( $direction ) . ' -- ini yang perlu kamu tahu.';
    $shorts_lines[] = '';
    $shorts_lines[] = 'BODY:';
    $shorts_lines[] = '- Harga referensi: ' . $price_fmt;
    $shorts_lines[] = sprintf(
        '- Confidence: %d%% (konsensus %d bullish / %d neutral / %d bearish)',
        $confidence,
        $consensus['bullish'],
        $consensus['neutral'],
        $consensus['bearish']
    );
    $shorts_lines[] = sprintf( '- Expected range 24 jam: %s - %s (\xc2\xb1%.1f%%)', $low_fmt, $high_fmt, $move );

    foreach ( $drivers as $driver ) {
        $shorts_lines[] = '- Driver: ' . $driver;
    }

    if ( $invalidation ) {
        $shorts_lines[] = '- Invalidation: ' . $invalidation;
    }

    $shorts_lines[] = '';
    $shorts_lines[] = 'CTA: Full breakdown & data harian di bitmomo.id -- link di bio.';
    $shorts_script  = implode( "\n", $shorts_lines );

    /* ---------- Newsletter blurb ---------- */
    $timestamp_int = strtotime( (string) ( $record['timestamp'] ?? 'now' ) );
    $newsletter    = sprintf(
        "BTC Daily Intelligence -- %s\n\nBitcoin saat ini berada di %s dengan outlook %s (tingkat keyakinan %d%%, berdasarkan konsensus %d bullish, %d neutral, dan %d bearish dari model AI kami). Model memperkirakan pergerakan 24 jam berada di kisaran %s hingga %s (\xc2\xb1%.1f%%).%s\n\n%s\n\nSeperti biasa, ini adalah alat bantu keputusan, bukan sinyal beli/jual -- selalu lakukan riset Anda sendiri. Baca analisis lengkap di %s.",
        $timestamp_int ? wp_date( 'd M Y', $timestamp_int ) : '',
        $price_fmt,
        $direction,
        $confidence,
        $consensus['bullish'],
        $consensus['neutral'],
        $consensus['bearish'],
        $low_fmt,
        $high_fmt,
        $move,
        $top_driver ? ( ' Driver utama saat ini: ' . $top_driver . '.' ) : '',
        $invalidation ? ( 'Skenario ini batal jika: ' . $invalidation ) : '',
        $site_url
    );

    return [
        'x_post'        => trim( $x_post ),
        'shorts_script' => trim( $shorts_script ),
        'newsletter'    => trim( $newsletter ),
    ];
}

/* ---------- Admin page: Tools -> BTC Content Assets ---------- */

add_action( 'admin_menu', 'bitmomo_register_content_assets_page' );

function bitmomo_register_content_assets_page() {
    add_management_page(
        __( 'BTC Content Assets', 'bitmomo' ),
        __( 'BTC Content Assets', 'bitmomo' ),
        'manage_options',
        'bitmomo-btc-content-assets',
        'bitmomo_render_content_assets_page'
    );
}

function bitmomo_render_content_assets_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $file    = get_stylesheet_directory() . '/data/btc-daily-sample.json';
    $record  = null;

    if ( file_exists( $file ) ) {
        $raw     = file_get_contents( $file );
        $decoded = $raw ? json_decode( $raw, true ) : null;

        if ( is_array( $decoded ) && ! empty( $decoded['timestamp'] ) ) {
            $record = $decoded;
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'BTC Daily Intelligence — Content Assets', 'bitmomo' ); ?></h1>

        <?php if ( ! $record ) : ?>
            <p><?php esc_html_e( 'Belum ada data BTC Intelligence yang valid (data/btc-daily-sample.json).', 'bitmomo' ); ?></p>
        <?php else :
            $assets = bitmomo_build_btc_content_assets( $record );
            $fields = [
                'x_post'        => __( 'X / Twitter Post', 'bitmomo' ),
                'shorts_script' => __( 'YouTube Shorts — Script Outline', 'bitmomo' ),
                'newsletter'    => __( 'Newsletter Blurb', 'bitmomo' ),
            ];
            foreach ( $fields as $key => $label ) :
                ?>
                <h2><?php echo esc_html( $label ); ?></h2>
                <textarea
                    readonly
                    rows="8"
                    style="width:100%;max-width:720px;font-family:monospace;font-size:13px;"
                    onclick="this.select();"
                ><?php echo esc_textarea( $assets[ $key ] ); ?></textarea>
                <?php
            endforeach;
            ?>
            <p style="opacity:.7;font-size:12px;">
                <?php esc_html_e( 'Klik teks untuk pilih semua, lalu salin manual (Ctrl/Cmd+C). Belum ada auto-publish ke X/YouTube/email -- ini hanya draft siap-tempel.', 'bitmomo' ); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-public-copy.php' );
$sales  = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-sales.php' );
$help   = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-help-center.php' );
$main   = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );

$checks = array(
    'copy compatibility class exists' => false !== strpos( $source, 'final class Bitmomo_Pro_Public_Copy' ),
    'compatibility shim is intentionally empty' => false !== strpos( $source, 'Intentionally empty. Public copy must be source-owned.' ),
    'compatibility shim owns no post-render mutation' => false === strpos( $source, "add_filter( 'gettext'" ) && false === strpos( $source, 'do_shortcode_tag' ) && false === strpos( $source, 'strtr(' ),
    'sales renderer owns Free-to-Pro boundary directly' => false !== strpos( $sales, 'BTC Intelligence Free menjelaskan apa yang terjadi sekarang.' ) && false !== strpos( $sales, 'Pro menambahkan Expected Range, Scenario Map, Thesis Invalidation' ),
    'sales renderer owns evidence-first historical boundary directly' => false !== strpos( $sales, 'HISTORICAL · DELAYED ≥48H' ) && false !== strpos( $sales, 'Brief Pro aktif tidak dibaca atau ditampilkan di halaman ini.' ),
    'sales renderer owns no-fabrication fallback directly' => false !== strpos( $sales, 'Bitmomo memilih ruang kosong daripada membuat range, skenario, confidence, atau outcome palsu.' ),
    'sales renderer owns thesis invalidation as explicit risk boundary' => false !== strpos( $sales, 'THESIS INVALIDATION' ),
    'Help renderer owns history language directly' => false !== strpos( $help, 'Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.' ),
    'Help renderer owns two-clock product language directly' => false !== strpos( $help, 'Market Pulse</strong> mengevaluasi kondisi intraday setiap 15 menit dari candle 5 menit' ) && false !== strpos( $help, 'Major Brief</strong> terbit pada anchor US Post-Close sekitar 20.10 New York dan US Pre-Open sekitar 08.10 New York' ),
    'plugin loads no-op compatibility shim' => false !== strpos( $main, "class-bitmomo-pro-public-copy.php" ) && false !== strpos( $main, 'Bitmomo_Pro_Public_Copy::init()' ),
    'no post-render Show-First enhancer is part of canonical public copy ownership' => false === strpos( $main, 'class-bitmomo-pro-show-first.php' ) && false === strpos( $sales, 'do_shortcode_tag' ),
);

$failed = array();
foreach ( $checks as $label => $pass ) {
    echo '[' . ( $pass ? 'PASS' : 'FAIL' ) . '] ' . $label . PHP_EOL;
    if ( ! $pass ) $failed[] = $label;
}

if ( $failed ) {
    fwrite( STDERR, 'Public copy contract failed: ' . implode( ', ', $failed ) . PHP_EOL );
    exit( 1 );
}

echo "PASS Bitmomo Pro public copy contract.\n";

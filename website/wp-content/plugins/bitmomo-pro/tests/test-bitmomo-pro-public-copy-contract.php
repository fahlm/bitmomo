<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-public-copy.php' );
$sales = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-sales.php' );
$help = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-help-center.php' );
$main = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );

$checks = array(
    'copy compatibility class exists' => false !== strpos( $source, 'final class Bitmomo_Pro_Public_Copy' ),
    'compatibility shim is intentionally empty' => false !== strpos( $source, 'Intentionally empty. Public copy must be source-owned.' ),
    'compatibility shim owns no post-render mutation' => false === strpos( $source, "add_filter( 'gettext'" ) && false === strpos( $source, 'do_shortcode_tag' ) && false === strpos( $source, 'strtr(' ),
    'sales renderer owns thesis language directly' => false !== strpos( $sales, 'Pahami skenario berikutnya — dan kapan tesis BTC berubah.' ),
    'sales renderer owns quality boundary directly' => false !== strpos( $sales, 'Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.' ),
    'sales renderer owns historical-proof boundary directly' => false !== strpos( $sales, 'Analisis Pro aktif tidak ditampilkan pada halaman publik.' ),
    'Help renderer owns history language directly' => false !== strpos( $help, 'Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.' ),
    'Help renderer owns two-clock product language directly' => false !== strpos( $help, 'Market Pulse</strong> mengevaluasi kondisi intraday setiap 15 menit dari candle 5 menit' ) && false !== strpos( $help, 'Major Brief</strong> terbit pada anchor US Post-Close sekitar 20.10 New York dan US Pre-Open sekitar 08.10 New York' ),
    'plugin loads no-op compatibility shim' => false !== strpos( $main, "class-bitmomo-pro-public-copy.php" ) && false !== strpos( $main, 'Bitmomo_Pro_Public_Copy::init()' ),
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

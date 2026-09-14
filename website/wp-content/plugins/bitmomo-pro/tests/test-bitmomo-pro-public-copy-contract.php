<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-public-copy.php' );
$sales = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-sales.php' );
$main = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );

$checks = array(
    'copy class exists' => false !== strpos( $source, 'final class Bitmomo_Pro_Public_Copy' ),
    'compatibility layer no longer owns gettext globally' => false === strpos( $source, "add_filter( 'gettext'" ),
    'shortcode scope is Help only' => false !== strpos( $source, "'bitmomo_help_center' !== \$tag" ) && false === strpos( $source, 'bitmomo_pro_sales' ),
    'sales renderer owns thesis language directly' => false !== strpos( $sales, 'Pahami skenario berikutnya — dan kapan tesis BTC berubah.' ),
    'sales renderer owns quality boundary directly' => false !== strpos( $sales, 'Analisis hanya ditampilkan ketika data memenuhi standar kualitas Bitmomo.' ),
    'sales renderer owns historical-proof boundary directly' => false !== strpos( $sales, 'Analisis Pro aktif tidak ditampilkan pada halaman publik.' ),
    'Help history language avoids casual fake-history wording' => false !== strpos( $source, 'Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.' ),
    'plugin loads transitional Help copy owner' => false !== strpos( $main, "class-bitmomo-pro-public-copy.php" ) && false !== strpos( $main, 'Bitmomo_Pro_Public_Copy::init()' ),
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

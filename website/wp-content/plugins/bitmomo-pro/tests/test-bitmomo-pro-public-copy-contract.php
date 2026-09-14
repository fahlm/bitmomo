<?php
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-bitmomo-pro-public-copy.php' );
$main = file_get_contents( dirname( __DIR__ ) . '/bitmomo-pro.php' );

$checks = array(
    'copy class exists' => false !== strpos( $source, 'final class Bitmomo_Pro_Public_Copy' ),
    'gettext scope is bitmomo-pro only' => false !== strpos( $source, "'bitmomo-pro' !== \$domain" ),
    'shortcode scope is public Pro and Help only' => false !== strpos( $source, "array( 'bitmomo_help_center', 'bitmomo_pro_sales' )" ),
    'thesis language is normalized' => false !== strpos( $source, 'kondisi yang dapat mengubah tesis pasar.' ),
    'founding language is restrained' => false !== strpos( $source, 'Founding Price berlaku selama membership tetap aktif.' ),
    'active Pro content boundary is professional' => false !== strpos( $source, 'Analisis Pro aktif tidak ditampilkan pada halaman publik.' ),
    'Help history language avoids casual fake-history wording' => false !== strpos( $source, 'Bitmomo tidak melakukan backfill retrospektif hanya untuk melengkapi visualisasi.' ),
    'plugin loads canonical public copy owner' => false !== strpos( $main, "class-bitmomo-pro-public-copy.php" ) && false !== strpos( $main, 'Bitmomo_Pro_Public_Copy::init()' ),
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

<?php
/** Posts index — neutral legacy publication index, intentionally noindex. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_archive_eyebrow = __( 'PUBLIKASI', 'bitmomo' );
$bm_archive_title = __( 'Arsip Publikasi', 'bitmomo' );
$bm_archive_description = __( 'Arsip publikasi Bitmomo. Untuk riset yang telah melalui classification institutional, gunakan Bitmomo Research.', 'bitmomo' );
?>
<main id="primary" class="bm-public-main">
  <?php get_template_part( 'template-parts/archive', 'index' ); ?>
</main>
<?php get_footer(); ?>

<?php
/** Search results — Bitmomo public surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_archive_eyebrow = __( 'PENCARIAN', 'bitmomo' );
$bm_archive_title = sprintf( __( 'Hasil untuk “%s”', 'bitmomo' ), get_search_query() );
$bm_archive_description = __( 'Publikasi Bitmomo yang paling relevan dengan pencarian Anda.', 'bitmomo' );
?>
<main id="primary" class="bm-public-main">
  <?php get_template_part( 'template-parts/archive', 'index' ); ?>
</main>
<?php get_footer(); ?>

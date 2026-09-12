<?php
/** Category archive — canonical Bitmomo public surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_term = get_queried_object();
$bm_is_research = $bm_term instanceof WP_Term && 'riset' === $bm_term->slug;
$bm_archive_eyebrow = $bm_is_research ? __( 'BITMOMO RESEARCH', 'bitmomo' ) : __( 'ARSIP', 'bitmomo' );
$bm_archive_title = $bm_is_research ? __( 'Riset & Analisis', 'bitmomo' ) : single_cat_title( '', false );
$bm_archive_description = category_description();
if ( $bm_is_research && '' === trim( wp_strip_all_tags( $bm_archive_description ) ) ) {
  $bm_archive_description = __( 'Analisis mendalam tentang Bitcoin, struktur pasar, teknologi, dan tema yang membentuk ekosistem crypto.', 'bitmomo' );
}
?>
<main id="primary" class="bm-public-main">
  <?php get_template_part( 'template-parts/archive', 'index' ); ?>
</main>
<?php unset( $bm_term, $bm_is_research ); get_footer(); ?>

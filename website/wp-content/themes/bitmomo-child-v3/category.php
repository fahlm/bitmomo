<?php
/** Category archive — canonical Bitmomo public surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_term = get_queried_object();
$bm_is_research = $bm_term instanceof WP_Term && 'riset' === $bm_term->slug;
?>
<main id="primary" class="bm-public-main">
  <?php if ( $bm_is_research ) : ?>
    <?php get_template_part( 'template-parts/research', 'hub' ); ?>
  <?php else : ?>
    <?php
    $bm_archive_eyebrow = __( 'ARSIP', 'bitmomo' );
    $bm_archive_title = single_cat_title( '', false );
    $bm_archive_description = category_description();
    get_template_part( 'template-parts/archive', 'index' );
    ?>
  <?php endif; ?>
</main>
<?php unset( $bm_term, $bm_is_research ); get_footer(); ?>

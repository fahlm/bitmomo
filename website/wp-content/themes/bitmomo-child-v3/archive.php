<?php
/** Generic archive fallback owned by Bitmomo. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_archive_eyebrow = __( 'ARSIP BITMOMO', 'bitmomo' );
$bm_archive_title = get_the_archive_title();
$bm_archive_description = get_the_archive_description();
?>
<main id="primary" class="bm-public-main">
  <?php get_template_part( 'template-parts/archive', 'index' ); ?>
</main>
<?php get_footer(); ?>

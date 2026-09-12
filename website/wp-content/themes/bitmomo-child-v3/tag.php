<?php
/** Tag archive — uses the same public archive contract as Research. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_archive_eyebrow = __( 'TOPIK', 'bitmomo' );
$bm_archive_title = single_tag_title( '', false );
$bm_archive_description = tag_description();
?>
<main id="primary" class="bm-public-main">
  <?php get_template_part( 'template-parts/archive', 'index' ); ?>
</main>
<?php get_footer(); ?>

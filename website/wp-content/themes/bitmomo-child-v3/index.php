<?php
/** Final WordPress hierarchy fallback — never fall back to Hello Elementor. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$bm_archive_eyebrow = __( 'BITMOMO RESEARCH', 'bitmomo' );
$bm_archive_title = __( 'Riset & Analisis', 'bitmomo' );
$bm_archive_description = __( 'Riset pasar, Bitcoin, struktur pasar, dan perkembangan teknologi yang relevan dengan ekosistem Bitmomo.', 'bitmomo' );
?>
<main id="primary" class="bm-public-main">
  <?php get_template_part( 'template-parts/archive', 'index' ); ?>
</main>
<?php get_footer(); ?>

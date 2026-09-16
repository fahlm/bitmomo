<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * Homepage responsibility is intentionally narrow: show current BTC
 * intelligence, prove accountability with recorded evidence, surface qualified
 * research, then convert interest into Bitmomo Pro / Founding access.
 *
 * 1. Header
 * 2. Current BTC intelligence
 * 3. Accountability + delayed Pro proof + compact product model
 * 4. Qualified market research
 * 5. Bitmomo Pro + Founding conversion
 * 6. Footer
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary">
  <?php get_template_part( 'template-parts/home', 'hero' ); ?>
  <?php get_template_part( 'template-parts/how-it-works' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
  <?php get_template_part( 'template-parts/whitelist' ); ?>
</main>
<?php get_footer(); ?>

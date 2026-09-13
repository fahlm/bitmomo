<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * Homepage responsibility is intentionally narrow: establish product value,
 * show one auditable current BTC reading, explain the product model, convert
 * qualified interest, then surface current institutional research.
 *
 * 1. Header
 * 2. Product positioning + current BTC reading + public-proof principles
 * 3. How Bitmomo works
 * 4. Bitmomo Pro + Founding conversion
 * 5. Latest qualified market research
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
  <?php get_template_part( 'template-parts/whitelist' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
</main>
<?php get_footer(); ?>

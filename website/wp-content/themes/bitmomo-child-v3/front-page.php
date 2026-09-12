<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * Homepage responsibility is intentionally narrow: explain the product,
 * show the current BTC reading, convert interest, and surface useful research.
 * Deeper history, methodology, AI-system research and referral/editorial
 * content belong on their dedicated destinations rather than competing with
 * the launch funnel.
 *
 * 1. Header
 * 2. Current BTC reading
 * 3. Bitmomo Pro + Founding conversion
 * 4. How Bitmomo works
 * 5. Latest research
 * 6. Footer
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main>
  <?php get_template_part( 'template-parts/home', 'hero' ); ?>
  <?php get_template_part( 'template-parts/whitelist' ); ?>
  <?php get_template_part( 'template-parts/how-it-works' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
</main>
<?php get_footer(); ?>

<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * The homepage is intentionally a glance surface, not a second copy of
 * /btc-intelligence/. One current-state + 30D intelligence instrument lives
 * in the hero; deeper methodology, history and accountability live on the
 * dedicated BTC Intelligence page.
 *
 * 1. Header
 * 2. BTC current state + compact 30D context
 * 3. Unified Bitmomo Pro + Founding conversion surface
 * 4. How Bitmomo Works
 * 5. AI Lab
 * 6. Research
 * 7. Platforms
 * 8. Footer
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
  <?php get_template_part( 'template-parts/ai', 'lab' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
  <?php get_template_part( 'template-parts/platform' ); ?>
</main>
<?php get_footer(); ?>

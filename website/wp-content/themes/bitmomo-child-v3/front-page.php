<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * 1. Header
 * 2. BTC Intelligence hero / 30D context
 * 3. BTC Daily Intelligence
 * 4. Unified Bitmomo Pro + Founding conversion surface
 * 5. How Bitmomo Works
 * 6. AI Lab
 * 7. Research
 * 8. Platforms
 * 9. Newsletter
 * 10. Footer
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main>
  <?php get_template_part( 'template-parts/home', 'hero' ); ?>
  <?php get_template_part( 'template-parts/btc-intelligence', 'card' ); ?>
  <?php get_template_part( 'template-parts/whitelist' ); ?>
  <?php get_template_part( 'template-parts/how-it-works' ); ?>
  <?php get_template_part( 'template-parts/ai', 'lab' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
  <?php get_template_part( 'template-parts/platform' ); ?>
  <?php get_template_part( 'template-parts/newsletter' ); ?>
</main>
<?php get_footer(); ?>

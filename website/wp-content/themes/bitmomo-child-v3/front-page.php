<?php
/**
 * Front Page — Bitmomo homepage hierarchy:
 * 1. Hero + current BTC Intelligence
 * 2. Bitmomo Pro
 * 3. Future Pro capability (11 AI Analysts / Watchtower / Telegram — IN DEVELOPMENT)
 * 4. Research + AI Lab
 * 5. Platform yang Kami Gunakan
 * 6. Newsletter / social
 * 7. Footer
 *
 * @package Bitmomo
 */
get_header();
?>

<main>

  <?php get_template_part( 'template-parts/home', 'hero' ); ?>

  <?php get_template_part( 'template-parts/btc-intelligence', 'card' ); ?>

  <?php get_template_part( 'template-parts/pro', 'teaser' ); ?>

  <?php get_template_part( 'template-parts/pro', 'future' ); ?>

  <?php get_template_part( 'template-parts/research' ); ?>

  <?php get_template_part( 'template-parts/ai', 'lab' ); ?>

  <?php get_template_part( 'template-parts/platform' ); ?>

  <?php get_template_part( 'template-parts/newsletter' ); ?>

</main>

<?php get_footer(); ?>

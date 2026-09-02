<?php
/**
 * Front Page — Bitmomo homepage hierarchy (frontend-completion sprint,
 * matches the brief's Part 2 IA exactly):
 * 1. Header (get_header())
 * 2. Hero + Market Direction / 30D
 * 3. BTC Daily Intelligence
 * 4. Bitmomo Pro
 * 5. Founding Membership Whitelist (PRIMARY conversion goal pre-checkout)
 * 6. How Bitmomo Works (system overview; absorbs the former standalone
 *    "Future Pro capability" 11 AI Analysts / Watchtower / Telegram section
 *    — IN DEVELOPMENT only — as its future stage)
 * 7. AI Lab
 * 8. Bitmomo Research
 * 9. Platform yang Kami Gunakan
 * 10. Newsletter (compact, secondary — see newsletter.php)
 * 11. Footer (get_footer())
 *
 * @package Bitmomo
 */
get_header();
?>

<main>

  <?php get_template_part( 'template-parts/home', 'hero' ); ?>

  <?php get_template_part( 'template-parts/btc-intelligence', 'card' ); ?>

  <?php get_template_part( 'template-parts/pro', 'teaser' ); ?>

  <?php get_template_part( 'template-parts/whitelist' ); ?>

  <?php get_template_part( 'template-parts/how-it-works' ); ?>

  <?php get_template_part( 'template-parts/ai', 'lab' ); ?>

  <?php get_template_part( 'template-parts/research' ); ?>

  <?php get_template_part( 'template-parts/platform' ); ?>

  <?php get_template_part( 'template-parts/newsletter' ); ?>

</main>

<?php get_footer(); ?>

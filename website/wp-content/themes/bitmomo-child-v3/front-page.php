<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * Homepage responsibility:
 * 1. explain current BTC state quickly;
 * 2. establish why Bitmomo is credible as a research/intelligence product;
 * 3. expose research proof and methodology;
 * 4. convert qualified visitors into the Founding funnel.
 *
 * Deep BTC history/accountability remains owned by /btc-intelligence/ and
 * detailed commercial explanation remains owned by /pro/.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary">
  <?php get_template_part( 'template-parts/home', 'hero' ); ?>
  <?php get_template_part( 'template-parts/home', 'authority' ); ?>
  <?php get_template_part( 'template-parts/how-it-works' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
  <?php get_template_part( 'template-parts/ai', 'lab' ); ?>
  <?php get_template_part( 'template-parts/whitelist' ); ?>
  <?php get_template_part( 'template-parts/platform' ); ?>
</main>
<?php get_footer(); ?>

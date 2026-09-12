<?php
/**
 * Canonical public page template.
 *
 * Prevents ordinary WordPress pages from silently falling back to the Hello
 * Elementor parent theme. Product shortcodes keep their own H1/layout while
 * About/legal/editorial pages use the shared Bitmomo public surface.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<main id="primary" class="bm-public-main">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
  <?php
  $bm_content = (string) get_post_field( 'post_content', get_the_ID() );
  $bm_product_shortcodes = array(
    'bitmomo_btc_intelligence',
    'bitmomo_pro_sales',
    'bitmomo_help_center',
    'bitmomo_pro_dashboard',
    'bitmomo_pro_account',
  );
  $bm_is_product_surface = false;
  foreach ( $bm_product_shortcodes as $bm_shortcode ) {
    if ( has_shortcode( $bm_content, $bm_shortcode ) ) {
      $bm_is_product_surface = true;
      break;
    }
  }
  ?>

  <?php if ( $bm_is_product_surface ) : ?>
    <article <?php post_class( 'bm-product-surface' ); ?>>
      <?php the_content(); ?>
    </article>
  <?php else : ?>
    <article <?php post_class( 'bm-public-page' ); ?>>
      <div class="bm-public-page__inner">
        <header class="bm-public-head bm-public-head--page">
          <p class="bm-public-eyebrow"><?php esc_html_e( 'BITMOMO', 'bitmomo' ); ?></p>
          <h1 class="bm-public-title"><?php the_title(); ?></h1>
        </header>
        <div class="bm-public-body">
          <?php the_content(); ?>
        </div>
      </div>
    </article>
  <?php endif; ?>

  <?php unset( $bm_content, $bm_product_shortcodes, $bm_shortcode, $bm_is_product_surface ); ?>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>

<?php
/**
 * Canonical public page template.
 *
 * Product shortcodes keep renderer-owned H1/layout. Ordinary pages use the
 * shared Bitmomo reading surface so Hello Elementor never silently becomes
 * the public design system.
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
    <?php
    $bm_rendered_content = apply_filters( 'the_content', $bm_content );
    $bm_rendered_content = bitmomo_normalize_public_page_body_headings( $bm_rendered_content );
    ?>
    <article <?php post_class( 'bm-public-page' ); ?>>
      <div class="bm-public-page__inner">
        <header class="bm-public-head bm-public-head--page">
          <p class="bm-public-eyebrow"><?php echo esc_html( is_page( 'tentang-kami' ) ? 'BITMOMO · RESEARCH & INTELLIGENCE' : 'BITMOMO' ); ?></p>
          <h1 class="bm-public-title"><?php the_title(); ?></h1>
        </header>

        <?php if ( is_page( 'tentang-kami' ) ) : ?>
          <?php get_template_part( 'template-parts/about', 'authority' ); ?>
        <?php endif; ?>

        <div class="bm-public-body">
          <?php
          // WordPress post content is rendered through the canonical filter stack above.
          // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
          echo $bm_rendered_content;
          ?>
        </div>
      </div>
    </article>
  <?php endif; ?>

  <?php unset( $bm_content, $bm_rendered_content, $bm_product_shortcodes, $bm_shortcode, $bm_is_product_surface ); ?>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>

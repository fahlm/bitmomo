<?php
/** Single post — canonical Bitmomo reading surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary" class="bm-public-main">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
  <?php $bm_cats = get_the_category(); ?>
  <article <?php post_class( 'bm-article' ); ?>>
    <div class="bm-container">
      <header class="bm-article-head">
        <p class="bm-public-eyebrow"><?php echo esc_html( $bm_cats ? $bm_cats[0]->name : __( 'BITMOMO RESEARCH', 'bitmomo' ) ); ?></p>
        <h1 class="bm-article-title"><?php the_title(); ?></h1>
        <div class="bm-article-meta">
          <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
          <?php if ( $bm_cats ) : ?><span class="sep">•</span><a href="<?php echo esc_url( get_category_link( $bm_cats[0]->term_id ) ); ?>"><?php echo esc_html( $bm_cats[0]->name ); ?></a><?php endif; ?>
        </div>
      </header>

      <?php if ( has_post_thumbnail() ) : ?>
        <figure class="bm-article-figure"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></figure>
      <?php endif; ?>

      <div class="bm-article-body"><?php the_content(); ?></div>

      <footer class="bm-article-foot">
        <nav class="bm-post-nav" aria-label="<?php esc_attr_e( 'Artikel lain', 'bitmomo' ); ?>">
          <div class="prev"><?php previous_post_link( '%link', '← %title' ); ?></div>
          <div class="next"><?php next_post_link( '%link', '%title →' ); ?></div>
        </nav>
      </footer>
    </div>
  </article>

  <?php
  $bm_primary_cat_id = $bm_cats ? (int) $bm_cats[0]->term_id : 0;
  if ( $bm_primary_cat_id ) :
    $bm_related = new WP_Query(
      array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'post__not_in' => array( get_the_ID() ),
        'posts_per_page' => 3,
        'ignore_sticky_posts' => true,
        'cat' => $bm_primary_cat_id,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'update_post_term_cache' => false,
      )
    );
    if ( $bm_related->have_posts() ) : ?>
      <section class="bm-section bm-related">
        <div class="bm-container">
          <p class="bm-public-eyebrow"><?php esc_html_e( 'LANJUTKAN MEMBACA', 'bitmomo' ); ?></p>
          <h2 class="bm-section-title"><?php esc_html_e( 'Riset Terkait', 'bitmomo' ); ?></h2>
          <div class="bm-cards bm-cards--research">
            <?php while ( $bm_related->have_posts() ) : $bm_related->the_post();
              get_template_part( 'template-parts/content', 'card', array( 'heading_level' => 'h3', 'image_size' => 'bm-card', 'excerpt_words' => 20 ) );
            endwhile; ?>
          </div>
        </div>
      </section>
    <?php endif;
    wp_reset_postdata();
  endif;
  unset( $bm_cats, $bm_primary_cat_id, $bm_related );
  ?>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>

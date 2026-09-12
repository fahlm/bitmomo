<?php
/** Single post — canonical Bitmomo reading surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary" class="bm-public-main">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
  <?php
  $bm_post_id = (int) get_the_ID();
  $bm_cats = get_the_category();
  $bm_classification = function_exists( 'bitmomo_post_research_classification' )
    ? bitmomo_post_research_classification( $bm_post_id )
    : 'unclassified';

  if ( 'market' === $bm_classification ) {
    $bm_article_label = __( 'MARKET RESEARCH', 'bitmomo' );
  } elseif ( 'ai-systems' === $bm_classification ) {
    $bm_article_label = __( 'AI SYSTEMS RESEARCH', 'bitmomo' );
  } else {
    $bm_article_label = $bm_cats ? $bm_cats[0]->name : __( 'BITMOMO', 'bitmomo' );
  }
  ?>
  <article <?php post_class( 'bm-article' ); ?>>
    <div class="bm-container">
      <header class="bm-article-head">
        <p class="bm-public-eyebrow"><?php echo esc_html( $bm_article_label ); ?></p>
        <h1 class="bm-article-title"><?php the_title(); ?></h1>
        <div class="bm-article-meta">
          <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
        </div>
      </header>

      <?php if ( has_post_thumbnail() ) : ?>
        <figure class="bm-article-figure"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
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
  $bm_related_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'post__not_in'           => array( $bm_post_id ),
    'posts_per_page'         => 3,
    'ignore_sticky_posts'    => true,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
  );
  $bm_related_title = __( 'Publikasi Terkait', 'bitmomo' );
  $bm_related_eyebrow = __( 'LANJUTKAN MEMBACA', 'bitmomo' );

  if ( 'market' === $bm_classification ) {
    $bm_riset = get_category_by_slug( 'riset' );
    $bm_market_slugs = function_exists( 'bitmomo_market_research_taxonomy_slugs' ) ? bitmomo_market_research_taxonomy_slugs() : array();
    if ( $bm_riset && $bm_market_slugs ) {
      $bm_related_args['cat'] = (int) $bm_riset->term_id;
      $bm_related_args['tax_query'] = array(
        'relation' => 'OR',
        array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $bm_market_slugs ),
        array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $bm_market_slugs ),
      );
      $bm_ai_tag = get_term_by( 'slug', 'ai-lab', 'post_tag' );
      if ( $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) $bm_related_args['tag__not_in'] = array( (int) $bm_ai_tag->term_id );
      $bm_related_title = __( 'Market Research Terkait', 'bitmomo' );
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  } elseif ( 'ai-systems' === $bm_classification ) {
    $bm_riset = get_category_by_slug( 'riset' );
    $bm_ai_tag = get_term_by( 'slug', 'ai-lab', 'post_tag' );
    if ( $bm_riset && $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) {
      $bm_related_args['cat'] = (int) $bm_riset->term_id;
      $bm_related_args['tag__in'] = array( (int) $bm_ai_tag->term_id );
      $bm_related_title = __( 'AI Systems Research Terkait', 'bitmomo' );
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  } else {
    $bm_primary_cat_id = $bm_cats ? (int) $bm_cats[0]->term_id : 0;
    if ( $bm_primary_cat_id ) {
      $bm_related_args['cat'] = $bm_primary_cat_id;
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  }

  $bm_related = new WP_Query( $bm_related_args );
  if ( $bm_related->have_posts() ) : ?>
    <section class="bm-section bm-related">
      <div class="bm-container">
        <p class="bm-public-eyebrow"><?php echo esc_html( $bm_related_eyebrow ); ?></p>
        <h2 class="bm-section-title"><?php echo esc_html( $bm_related_title ); ?></h2>
        <div class="bm-cards bm-cards--research">
          <?php while ( $bm_related->have_posts() ) : $bm_related->the_post();
            if ( 'market' === $bm_classification && function_exists( 'bitmomo_post_is_market_research' ) && ! bitmomo_post_is_market_research( get_the_ID() ) ) continue;
            if ( 'ai-systems' === $bm_classification && function_exists( 'bitmomo_post_is_ai_systems_research' ) && ! bitmomo_post_is_ai_systems_research( get_the_ID() ) ) continue;
            get_template_part( 'template-parts/content', 'card', array( 'heading_level' => 'h3', 'image_size' => 'bm-card', 'excerpt_words' => 20 ) );
          endwhile; ?>
        </div>
      </div>
    </section>
  <?php endif;
  wp_reset_postdata();
  unset(
    $bm_post_id, $bm_cats, $bm_classification, $bm_article_label, $bm_related_args,
    $bm_related_title, $bm_related_eyebrow, $bm_related, $bm_riset, $bm_market_slugs,
    $bm_ai_tag, $bm_primary_cat_id
  );
  ?>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>

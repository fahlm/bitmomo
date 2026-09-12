<?php
/** Single post — canonical Bitmomo research reading surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary" class="bm-public-main">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
  <?php
  $bm_post_id        = get_the_ID();
  $bm_primary_cat    = bitmomo_primary_public_category( $bm_post_id );
  $bm_tags           = get_the_tags( $bm_post_id );
  $bm_research_lane  = bitmomo_post_research_lane( $bm_post_id );
  $bm_classification = bitmomo_research_classification_label( $bm_post_id );
  $bm_excerpt        = trim( wp_strip_all_tags( get_the_excerpt( $bm_post_id ) ) );
  $bm_read_minutes   = bitmomo_estimated_reading_minutes( $bm_post_id );
  $bm_modified       = get_the_modified_time( 'U', $bm_post_id );
  $bm_published      = get_the_time( 'U', $bm_post_id );
  ?>
  <article <?php post_class( 'bm-article' ); ?>>
    <div class="bm-container">
      <header class="bm-article-head">
        <div class="bm-article-head__classification">
          <span><?php echo esc_html( $bm_classification ); ?></span>
          <?php if ( $bm_primary_cat ) : ?><a href="<?php echo esc_url( get_category_link( $bm_primary_cat->term_id ) ); ?>"><?php echo esc_html( $bm_primary_cat->name ); ?></a><?php endif; ?>
        </div>
        <h1 class="bm-article-title"><?php the_title(); ?></h1>
        <?php if ( $bm_excerpt ) : ?><p class="bm-article-deck"><?php echo esc_html( $bm_excerpt ); ?></p><?php endif; ?>
        <div class="bm-article-meta">
          <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y' ) ); ?></time>
          <span aria-hidden="true">·</span>
          <span><?php echo esc_html( sprintf( __( '%d menit baca', 'bitmomo' ), $bm_read_minutes ) ); ?></span>
          <?php if ( $bm_modified > ( $bm_published + DAY_IN_SECONDS ) ) : ?>
            <span aria-hidden="true">·</span>
            <span><?php echo esc_html( sprintf( __( 'Diperbarui %s', 'bitmomo' ), get_the_modified_date( 'd M Y' ) ) ); ?></span>
          <?php endif; ?>
        </div>
      </header>

      <?php if ( has_post_thumbnail() ) : ?>
        <figure class="bm-article-figure"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
      <?php endif; ?>

      <div class="bm-article-body"><?php the_content(); ?></div>

      <aside class="bm-article-standard" aria-label="Standar editorial Bitmomo">
        <strong><?php esc_html_e( 'Standar Bitmomo Research', 'bitmomo' ); ?></strong>
        <p><?php esc_html_e( 'Kami memisahkan data, interpretasi, dan thesis sejauh materi memungkinkan; membatasi claim pada evidence yang tersedia; dan memperbarui analisis ketika konteks material berubah. Research bukan nasihat keuangan.', 'bitmomo' ); ?></p>
      </aside>

      <?php if ( $bm_tags ) : ?>
        <ul class="bm-article-tags" aria-label="Topik artikel">
          <?php foreach ( $bm_tags as $bm_tag ) : ?>
            <li><a href="<?php echo esc_url( get_tag_link( $bm_tag->term_id ) ); ?>"><?php echo esc_html( $bm_tag->name ); ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

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
  $bm_research_taxonomy = bitmomo_research_taxonomy();
  if ( 'ai' === $bm_research_lane && $bm_research_taxonomy['ai_lab_tag_id'] ) {
    $bm_related_args['tag__in'] = array( (int) $bm_research_taxonomy['ai_lab_tag_id'] );
    if ( $bm_research_taxonomy['riset_id'] ) $bm_related_args['cat'] = (int) $bm_research_taxonomy['riset_id'];
  } elseif ( $bm_primary_cat ) {
    $bm_related_args['cat'] = (int) $bm_primary_cat->term_id;
  }

  $bm_related = new WP_Query( $bm_related_args );
  if ( $bm_related->have_posts() ) : ?>
    <section class="bm-section bm-related">
      <div class="bm-container">
        <span class="bm-eyebrow"><?php esc_html_e( 'LANJUTKAN MEMBACA', 'bitmomo' ); ?></span>
        <h2 class="bm-heading"><?php esc_html_e( 'Riset Terkait', 'bitmomo' ); ?></h2>
        <div class="bm-research-grid bm-related-grid">
          <?php while ( $bm_related->have_posts() ) : $bm_related->the_post();
            $bm_related_cat = bitmomo_primary_public_category( get_the_ID() );
            $bm_related_label = $bm_related_cat ? $bm_related_cat->name : bitmomo_research_classification_label( get_the_ID() );
          ?>
            <article <?php post_class( 'bm-research-card' ); ?>>
              <a class="bm-research-card__art" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
                <?php if ( has_post_thumbnail() ) the_post_thumbnail( 'bm-card', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
              </a>
              <div class="bm-research-card__body">
                <span class="bm-research-card__meta"><?php echo esc_html( $bm_related_label . ' · ' . get_the_date( 'd M Y' ) ); ?></span>
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '…' ) ); ?></p>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
      </div>
    </section>
  <?php endif;
  wp_reset_postdata();
  unset(
    $bm_post_id, $bm_primary_cat, $bm_tags, $bm_tag, $bm_research_lane, $bm_classification,
    $bm_excerpt, $bm_read_minutes, $bm_modified, $bm_published, $bm_related_args,
    $bm_research_taxonomy, $bm_related, $bm_related_cat, $bm_related_label
  );
  ?>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>

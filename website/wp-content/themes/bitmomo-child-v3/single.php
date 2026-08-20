<?php
/** Single Post — Bitmomo */
get_header();
?>

<main>
  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

    <article class="bm-article">
      <div class="bm-container">
        <header class="bm-article-head">
          <h1 class="bm-article-title"><?php the_title(); ?></h1>
          <div class="bm-article-meta">
            <span><?php echo get_the_date(); ?></span>
            <?php
              $cats = get_the_category();
              if ($cats) {
                echo '<span class="sep">•</span><span>';
                echo esc_html($cats[0]->name);
                echo '</span>';
              }
            ?>
          </div>
        </header>

        <?php if ( has_post_thumbnail() ) : ?>
          <figure class="bm-article-figure">
            <?php the_post_thumbnail('large', ['loading' => 'eager']); ?>
          </figure>
        <?php endif; ?>

        <div class="bm-article-body">
          <?php the_content(); ?>
        </div>

        <footer class="bm-article-foot">
          <nav class="bm-post-nav">
            <div class="prev"><?php previous_post_link('%link','« %title'); ?></div>
            <div class="next"><?php next_post_link('%link','%title »'); ?></div>
          </nav>
        </footer>
      </div>
    </article>

    <?php
      $primary_cat_id = $cats ? $cats[0]->term_id : 0;
      if ( $primary_cat_id ) :
        $rel = new WP_Query([
          'post__not_in'       => [ get_the_ID() ],
          'posts_per_page'     => 3,
          'ignore_sticky_posts'=> true,
          'cat'                => $primary_cat_id,
        ]);
        if ( $rel->have_posts() ) : ?>
          <section class="bm-section bm-related">
            <div class="bm-container">
              <h2 class="bm-section-title">Bacaan Terkait</h2>
              <div class="bm-cards">
              <?php while ( $rel->have_posts() ) : $rel->the_post(); ?>

                <?php
                get_template_part(
                  'template-parts/content',
                  'card',
                  [
                    'heading_level' => 'h3',
                    'image_size'    => 'large',
                    'excerpt_words' => 22,
                  ]
                );
                ?>
              <?php endwhile; wp_reset_postdata(); ?>
              </div>
            </div>
          </section>
        <?php endif;
      endif;
    ?>

  <?php endwhile; endif; ?>
</main>

<?php get_footer(); ?>

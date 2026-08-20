<?php
/** Single Post — Bitmomo */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header class="bm-header">
  <div class="bm-container">
    <?php bitmomo_render_brand(); ?>
    <?php bitmomo_render_menu_toggle(); ?>

    <nav class="bm-nav" id="bm-nav">
      <?php
      $menu_html = wp_nav_menu([
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => 'bm-nav-list',
        'fallback_cb'    => '__return_false',
        'echo'           => false,
        'depth'          => 1,
      ]);
      if ($menu_html) { echo $menu_html; }
      ?>
    </nav>

    <a class="bm-cta" href="<?php echo esc_url( home_url('/subscribe/') ); ?>">SUBSCRIBE</a>
  </div>
</header>

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
      // ===== Related posts by same category (max 3) =====
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
                <article class="bm-card">
                  <a class="bm-card-art" href="<?php the_permalink(); ?>">
                    <?php if ( has_post_thumbnail() ) {
                      the_post_thumbnail('large', ['loading' => 'lazy']);
                    } else { ?>
                      <img src="https://placehold.co/800x450/0f2434/adc7cf?text=<?php echo rawurlencode(get_the_title()); ?>" alt="">
                    <?php } ?>
                  </a>
                  <h3 class="bm-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                  <p class="bm-card-text"><?php echo wp_trim_words( get_the_excerpt(), 22, '…' ); ?></p>
                </article>
              <?php endwhile; wp_reset_postdata(); ?>
              </div>
            </div>
          </section>
        <?php endif;
      endif;
    ?>

  <?php endwhile; endif; ?>
</main>

<footer class="bm-footer">
  <div class="bm-container">
    <p>© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. Semua hak cipta dilindungi.</p>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

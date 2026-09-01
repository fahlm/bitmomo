<?php
/** Front Page — Bitmomo VERIFIED FIXED */
get_header();
?>

<main>

  <?php get_template_part( 'template-parts/home', 'hero' ); ?>

  <?php get_template_part( 'template-parts/btc-intelligence', 'card' ); ?>

  <?php get_template_part( 'template-parts/why', 'bitmomo' ); ?>

  <section class="bm-section bm-section--stories">
    <div class="bm-container">
      <h2 class="bm-section-title">BIG STORIES</h2>

      <div class="bm-cards">
        <?php
        $big = new WP_Query([
          'posts_per_page'      => 6,
          'post_status'         => 'publish',
          'ignore_sticky_posts' => true,
          'tag'                 => 'big-stories',
          'orderby'             => 'date',
          'order'               => 'DESC',
          'no_found_rows'       => true,
        ]);

        if ( $big->have_posts() ) :
          $first = true;
          while ( $big->have_posts() ) : $big->the_post(); ?>

            <?php
            get_template_part(
              'template-parts/content',
              'card',
              [
                'heading_level' => 'h3',
                'image_size'    => 'bm-card',
                'excerpt_words' => 22,
                'eager'         => $first,
              ]
            );
            ?>
          <?php
            $first = false;
          endwhile;
          wp_reset_postdata();
        else :
          echo '<p style="opacity:.8;text-align:center">Belum ada artikel dengan tag <strong>big-stories</strong>.</p>';
        endif;
        ?>
      </div>

      <div class="bm-discover-more">
        <?php
        $tag = get_term_by( 'slug', 'big-stories', 'post_tag' );
        if ( $tag ) {
          echo '<a href="'. esc_url( get_tag_link( $tag->term_id ) ) .'" class="bm-btn">Lihat Artikel Lainnya →</a>';
        }
        ?>
      </div>

    </div>
  </section>

</main>

<?php get_footer(); ?>

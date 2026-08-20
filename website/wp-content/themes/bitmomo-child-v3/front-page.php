<?php
/** Front Page — Bitmomo VERIFIED FIXED */
get_header();
?>

<main>

  <section class="bm-hero">
    <div class="bm-container">
      <h1 class="bm-hero-title">
        Informasi AI & <span class="teal">Crypto</span> Terdepan
      </h1>
      <p class="bm-hero-sub">
        Bergabung dengan ribuan pembaca dan dapatkan berita serta analisis terkini langsung ke inbox Anda.
      </p>
      <a class="bm-hero-btn js-open-subscribe" href="#subscribe">Mulai Berlangganan</a>
    </div>
  </section>

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
            <article class="bm-card">
              <a class="bm-card-art" href="<?php echo esc_url( get_permalink() ); ?>">
                <?php
                if ( has_post_thumbnail() ) {
                  $attrs = [
                    'class'         => 'bm-card-img',
                    'alt'           => the_title_attribute(['echo'=>false]),
                    'decoding'      => 'async',
                    'loading'       => ( $first ? 'eager' : 'lazy' ),
                    'fetchpriority' => ( $first ? 'high' : null ),
                    'sizes'         => '(max-width: 900px) 100vw, 400px',
                  ];
                  the_post_thumbnail( 'bm-card', array_filter( $attrs ) );
                } else {
                  $title_txt = wp_strip_all_tags( get_the_title() );
                  $safe_txt  = esc_html( mb_strimwidth( $title_txt, 0, 48, '…', 'UTF-8' ) );
                  $svg = rawurlencode(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450">
                      <rect width="800" height="450" fill="#0f2434"/>
                      <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
                        font-family="system-ui,-apple-system,Segoe UI,Roboto,Arial" font-size="28" fill="#adc7cf">'.$safe_txt.'</text>
                    </svg>'
                  );
                  echo '<img class="bm-card-img" src="data:image/svg+xml;charset=UTF-8,'.$svg.'" width="800" height="450" alt="">';
                }
                ?>
              </a>

              <h3 class="bm-card-title">
                <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
              </h3>

              <p class="bm-card-text"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ); ?></p>
            </article>
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

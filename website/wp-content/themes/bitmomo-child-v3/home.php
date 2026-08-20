<?php
/** Bitmomo — Home (Latest Posts) v4.0 */
get_header();
?>

<main>
  <section class="bm-hero">
    <div class="bm-container">
      <h1 class="bm-hero-title">Informasi AI &amp; <span class="teal">Crypto</span> Terdepan</h1>
      <p class="bm-hero-sub">Bergabung dengan ribuan pembaca dan dapatkan berita serta analisis terkini langsung ke inbox Anda.</p>
      <a class="bm-hero-btn js-open-subscribe" href="#subscribe">Mulai Berlangganan</a>
    </div>
  </section>

  <section class="bm-section">
    <div class="bm-container">
      <?php if (have_posts()) : ?>
        <div class="bm-cards">
          <?php $i = 0; while (have_posts()) : the_post(); $i++; ?>
            <article class="bm-card">
              <a class="bm-card-art" href="<?php the_permalink(); ?>">
                <?php if (has_post_thumbnail()) {
                  the_post_thumbnail('bm-card', [
                    'class'         => 'bm-card-img',
                    'loading'       => $i === 1 ? 'eager' : 'lazy',
                    'fetchpriority' => $i === 1 ? 'high' : false,
                    'width'         => 800,
                    'height'        => 450
                  ]);
                } else { ?>
                  <img class="bm-card-img" src="https://placehold.co/800x450/0f2434/adc7cf?text=<?php echo rawurlencode(get_the_title()); ?>" alt="" width="800" height="450">
                <?php } ?>
              </a>
              <h3 class="bm-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
              <p class="bm-card-text"><?php echo wp_trim_words(get_the_excerpt(), 26, '…'); ?></p>
            </article>
          <?php endwhile; ?>
        </div>
        <nav class="bm-pagination">
          <?php echo paginate_links([
            'prev_text' => '« Prev',
            'next_text' => 'Next »',
          ]); ?>
        </nav>
      <?php else : ?>
        <p>Tidak ada artikel.</p>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php get_footer(); ?>

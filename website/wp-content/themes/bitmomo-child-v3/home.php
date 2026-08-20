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

            <?php
            get_template_part(
              'template-parts/content',
              'card',
              [
                'heading_level' => 'h3',
                'image_size'    => 'bm-card',
                'excerpt_words' => 26,
                'eager'         => 1 === $i,
              ]
            );
            ?>
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

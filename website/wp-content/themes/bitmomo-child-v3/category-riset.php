<?php
/**
 * Riset archive — dedicated category template (category-riset.php).
 *
 * WordPress's template hierarchy serves this file only for
 * /category/riset/, so it never touches category.php (still used by every
 * other category archive) or any other page. Shows only posts genuinely
 * assigned to the Riset category -- no taxonomy changes, no merged
 * content from other categories.
 *
 * @package Bitmomo
 */
get_header();
?>

<main>
  <header class="bm-page-head bm-riset-head">
    <div class="bm-container">
      <span class="bm-riset-eyebrow">RISET</span>
      <h1 class="bm-page-title">Riset &amp; Analisis Mendalam</h1>
      <p class="bm-riset-sub">Eksplorasi lebih dalam tentang crypto, AI, teknologi, dan perkembangan yang membentuk pasar.</p>
    </div>
  </header>

  <section class="bm-section">
    <div class="bm-container">

      <?php if ( have_posts() ) : ?>
        <ul class="bm-research-list bm-riset-list">
          <?php
          while ( have_posts() ) :
            the_post();
            $bm_riset_cats = get_the_category();
            ?>
            <li class="bm-research-item">
              <?php if ( has_post_thumbnail() ) : ?>
                <a class="bm-riset-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                  <?php the_post_thumbnail( 'thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) ); ?>
                </a>
              <?php endif; ?>
              <?php if ( $bm_riset_cats ) : ?>
                <span class="bm-research-category"><?php echo esc_html( $bm_riset_cats[0]->name ); ?></span>
              <?php endif; ?>
              <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
              <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '…' ) ); ?></p>
              <div class="bm-riset-meta">
                <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                <a class="bm-research-link" href="<?php the_permalink(); ?>">Baca selengkapnya &rarr;</a>
              </div>
            </li>
          <?php endwhile; ?>
        </ul>

        <nav class="bm-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'bitmomo' ); ?>">
          <?php
          echo paginate_links(
            array(
              'prev_text' => '« Prev',
              'next_text' => 'Next »',
            )
          );
          ?>
        </nav>

      <?php else : ?>
        <p style="opacity:.8"><?php esc_html_e( 'Belum ada riset yang dipublikasikan.', 'bitmomo' ); ?></p>
      <?php endif; ?>

    </div>
  </section>
</main>

<?php get_footer(); ?>

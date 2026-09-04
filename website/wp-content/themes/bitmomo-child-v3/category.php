<?php
/** Archive (Kategori) — Bitmomo FINAL FIX (with critical inline CSS) */
get_header();

if ( is_category( 'riset' ) ) :
	/**
	 * Riset gets a dedicated archive experience (compact eyebrow/H1/support
	 * header, de-emphasized thumbnails, controlled excerpts, visible
	 * dates) while every other category below keeps the original generic
	 * archive markup completely unchanged. Kept inline in this shared
	 * file -- rather than as a separate category-riset.php -- because
	 * this environment's deploy path can only edit existing theme files,
	 * not create new ones; behavior at /category/riset/ is otherwise
	 * identical to a dedicated template, and every other category archive
	 * is byte-for-byte unaffected.
	 */
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
	<?php

else :
	?>

<main>
  <header class="bm-page-head">
    <div class="bm-container">
      <h1 class="bm-page-title"><?php single_cat_title(); ?></h1>
    </div>
  </header>

  <section class="bm-section">
    <div class="bm-container">

      <?php if ( have_posts() ) : ?>
        <div class="bm-cards">
          <?php while ( have_posts() ) : the_post(); ?>

            <?php
            get_template_part(
              'template-parts/content',
              'card',
              [
                'heading_level' => 'h3',
                'image_size'    => 'bm-card',
                'excerpt_words' => 26,
              ]
            );
            ?>
          <?php endwhile; ?>
        </div>

        <nav class="bm-pagination" aria-label="<?php esc_attr_e('Pagination','bitmomo'); ?>">
          <?php
          echo paginate_links([
            'prev_text' => '« Prev',
            'next_text' => 'Next »',
          ]);
          ?>
        </nav>

      <?php else : ?>
        <p style="opacity:.8"><?php esc_html_e('Tidak ada artikel di kategori ini.', 'bitmomo'); ?></p>
      <?php endif; ?>

    </div>
  </section>
</main>

<?php
endif;

get_footer();

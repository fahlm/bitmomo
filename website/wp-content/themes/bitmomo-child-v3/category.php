<?php
/** Archive (Kategori) — Bitmomo FINAL FIX (with critical inline CSS) */
get_header();

if ( is_category( 'riset' ) ) :
	/**
	 * Riset gets a dedicated archive experience while every other category
	 * below keeps the original generic archive markup completely unchanged.
	 * Kept inline in this shared file -- rather than as a separate
	 * category-riset.php -- because this environment's deploy path can only
	 * edit existing theme files, not create new ones; every other category
	 * archive is byte-for-byte unaffected.
	 *
	 * P0 visual correction (2026-09): replaces the flat single-grid archive
	 * with two editorial sections -- Market Research (genuine Riset-category
	 * posts) and AI Lab (a small, explicit, hand-picked list of existing
	 * posts that genuinely match the homepage AI Lab thesis -- Decentralized
	 * AI, Agent Systems, AI Evaluation -- verified against each post's
	 * actual content, not merely its title). No taxonomy migration: AI Lab
	 * has no dedicated category/tag yet, so this is the smallest workable
	 * mechanism for the current small inventory -- an explicit post-ID list,
	 * not a new taxonomy term. A post appears in only one section: the one
	 * Riset-category post also selected for AI Lab (RAG) is excluded from
	 * Market Research to avoid duplicating it on the same page.
	 */

	// Small, explicit, hand-picked AI Lab selection -- verified against each
	// post's actual excerpt/content against ai-lab.php's three themes
	// (Decentralized AI, Agent Systems, AI Evaluation). Never auto-derived.
	$bm_ai_lab_ids = array( 2314, 2205, 2154, 2342, 2281, 2251 );

	$bm_riset_term    = get_category_by_slug( 'riset' );
	$bm_research_query = new WP_Query(
		array(
			'post_type'            => 'post',
			'post_status'          => 'publish',
			'cat'                  => $bm_riset_term ? $bm_riset_term->term_id : 0,
			'post__not_in'         => $bm_ai_lab_ids,
			'posts_per_page'       => -1,
			'orderby'              => 'date',
			'order'                => 'DESC',
			'no_found_rows'        => true,
			'ignore_sticky_posts'  => true,
		)
	);

	$bm_ai_lab_query = new WP_Query(
		array(
			'post_type'            => 'post',
			'post_status'          => 'publish',
			'post__in'             => $bm_ai_lab_ids,
			'orderby'              => 'post__in',
			'posts_per_page'       => count( $bm_ai_lab_ids ),
			'no_found_rows'        => true,
			'ignore_sticky_posts'  => true,
		)
	);

	// Shared card renderer for both sections -- $bm_topic overrides the
	// visible topic label (Market Research shows the post's own category;
	// AI Lab always shows the section label, not the post's Tren AI/Riset
	// category, since it is a curated cross-category selection).
	if ( ! function_exists( 'bm_riset_render_card' ) ) {
		function bm_riset_render_card( $bm_topic = '' ) {
			$bm_cats  = get_the_category();
			$bm_label = $bm_topic ? $bm_topic : ( $bm_cats ? $bm_cats[0]->name : __( 'Riset', 'bitmomo' ) );
			?>
			<li class="bm-riset-card">
			  <a class="bm-riset-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			    <?php if ( has_post_thumbnail() ) : ?>
			      <?php the_post_thumbnail( 'bm-card', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) ); ?>
			    <?php else : ?>
			      <?php
			      $bm_safe_title = esc_html( mb_strimwidth( wp_strip_all_tags( get_the_title() ), 0, 48, '…', 'UTF-8' ) );
			      $bm_svg        = rawurlencode(
			        '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450">'
			        . '<rect width="800" height="450" fill="#0f2434"/>'
			        . '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" '
			        . 'font-family="system-ui,-apple-system,Segoe UI,Roboto,Arial" font-size="28" fill="#adc7cf">'
			        . $bm_safe_title
			        . '</text></svg>'
			      );
			      printf( '<img class="bm-riset-card-img" src="data:image/svg+xml;charset=UTF-8,%s" width="800" height="450" alt="" loading="lazy" decoding="async">', $bm_svg );
			      ?>
			    <?php endif; ?>
			  </a>
			  <div class="bm-riset-card-body">
			    <span class="bm-riset-card-topic"><?php echo esc_html( strtoupper( $bm_label ) ); ?></span>
			    <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
			    <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 16, '…' ) ); ?></p>
			    <div class="bm-riset-card-meta">
			      <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			      <a class="bm-riset-card-link" href="<?php the_permalink(); ?>">Baca &rarr;</a>
			    </div>
			  </div>
			</li>
			<?php
		}
	}
	?>
	<main>
	  <header class="bm-page-head bm-riset-head bm-riset-head--compact">
	    <div class="bm-container">
	      <span class="bm-riset-eyebrow">RISET</span>
	      <h1 class="bm-page-title">Research untuk memahami perubahan pasar dan teknologi.</h1>
	      <p class="bm-riset-sub">Riset independen Bitmomo tentang pasar dan sistem AI yang membentuknya.</p>
	    </div>
	    <?php if ( $bm_research_query->have_posts() && $bm_ai_lab_query->have_posts() ) : ?>
	    <nav class="bm-riset-subnav bm-container" aria-label="<?php esc_attr_e( 'Bagian Riset', 'bitmomo' ); ?>">
	      <a href="#bm-riset-research">Research</a>
	      <a href="#bm-riset-ai-lab">AI Lab</a>
	    </nav>
	    <?php endif; ?>
	  </header>

	  <?php if ( $bm_research_query->have_posts() ) : ?>
	  <section class="bm-riset-section bm-riset-section--research" id="bm-riset-research">
	    <div class="bm-container">
	      <div class="bm-riset-section-head">
	        <span class="bm-riset-section-eyebrow">MARKET RESEARCH</span>
	        <h2>Riset pasar &amp; teknologi</h2>
	      </div>
	      <ul class="bm-riset-grid">
	        <?php while ( $bm_research_query->have_posts() ) : $bm_research_query->the_post(); bm_riset_render_card(); endwhile; ?>
	      </ul>
	    </div>
	  </section>
	  <?php endif; wp_reset_postdata(); ?>

	  <?php if ( $bm_ai_lab_query->have_posts() ) : ?>
	  <section class="bm-riset-section bm-riset-section--ai-lab" id="bm-riset-ai-lab">
	    <div class="bm-container">
	      <div class="bm-riset-section-head bm-riset-ai-lab-head">
	        <span class="bm-riset-section-eyebrow bm-riset-ai-lab-eyebrow">AI LAB</span>
	        <h2>Eksperimen &amp; riset AI Bitmomo</h2>
	        <p>Eksperimen dan riset Bitmomo tentang AI, agents, dan teknologi yang sedang membentuk generasi berikutnya.</p>
	      </div>
	      <ul class="bm-riset-grid bm-riset-grid--ai-lab">
	        <?php while ( $bm_ai_lab_query->have_posts() ) : $bm_ai_lab_query->the_post(); bm_riset_render_card( 'AI Lab' ); endwhile; ?>
	      </ul>
	    </div>
	  </section>
	  <?php endif; wp_reset_postdata(); ?>

	  <?php if ( ! $bm_research_query->have_posts() && ! $bm_ai_lab_query->have_posts() ) : ?>
	  <section class="bm-section">
	    <div class="bm-container">
	      <p style="opacity:.8"><?php esc_html_e( 'Belum ada riset yang dipublikasikan.', 'bitmomo' ); ?></p>
	    </div>
	  </section>
	  <?php endif; ?>
	</main>
	<?php
	unset( $bm_ai_lab_ids, $bm_riset_term, $bm_research_query, $bm_ai_lab_query );

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

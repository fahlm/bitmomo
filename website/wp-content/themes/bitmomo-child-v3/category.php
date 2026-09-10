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
	 * P0 visual correction v2 (2026-09-04): full redesign away from an
	 * editorial/magazine layout toward a product-brand market-intelligence
	 * page -- compact left-aligned hero, a "Latest Research" primary +
	 * secondary spotlight, a distinct "Bitmomo AI Lab" brand/R&D module,
	 * and a dense "Arsip Riset" list for the remainder. Section label is
	 * "Latest Research" (not "Market Research") because the underlying
	 * posts are general market/technology research, not narrowly market
	 * analysis -- an honest umbrella rather than a mislabel.
	 *
	 * AI Lab is still the same small, explicit, hand-picked post-ID list
	 * (verified against actual post content, not titles, against
	 * ai-lab.php's three themes) -- no taxonomy migration, no new
	 * category/tag. Only 4 of the 6 AI Lab posts are surfaced here as a
	 * curated "From the Lab" proof layer, per instruction not to expose
	 * all six as article cards; the other two remain valid AI Lab posts,
	 * just not featured on this page in this pass.
	 */

	// Full curated AI Lab selection (verified against actual post content,
	// not titles, against ai-lab.php's three themes: Decentralized AI,
	// Agent Systems, AI Evaluation). Kept out of the Latest Research /
	// Arsip pool below so nothing on this page is shown twice.
	$bm_ai_lab_ids = array( 2314, 2205, 2154, 2342, 2281, 2251 );

	// Curated "From the Lab" proof layer -- 4 of the 6, one per brand
	// pillar plus one extra. Not all six are surfaced here by design.
	$bm_ai_lab_featured_ids = array( 2314, 2342, 2251, 2205 );

	$bm_ai_lab_pillar_map = array(
		2314 => 'AI Agents',
		2342 => 'Decentralized AI',
		2251 => 'AI Intelligence Systems',
		2205 => 'AI Agents',
	);

	$bm_riset_term = get_category_by_slug( 'riset' );

	$bm_riset_posts = get_posts(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'cat'                  => $bm_riset_term ? $bm_riset_term->term_id : 0,
			'post__not_in'         => $bm_ai_lab_ids,
			'posts_per_page'       => -1,
			'orderby'              => 'date',
			'order'                => 'DESC',
			'no_found_rows'        => true,
			'ignore_sticky_posts'  => true,
		)
	);

	// One primary spotlight + a compact secondary list make up "Latest
	// Research"; whatever's left goes to the dense "Arsip Riset" list --
	// nothing dropped, nothing duplicated.
	$bm_riset_primary   = array_slice( $bm_riset_posts, 0, 1 );
	$bm_riset_secondary = array_slice( $bm_riset_posts, 1, 3 );
	$bm_riset_archive   = array_slice( $bm_riset_posts, 4 );

	$bm_ai_lab_posts = get_posts(
		array(
			'post_type'            => 'post',
			'post_status'          => 'publish',
			'post__in'             => $bm_ai_lab_featured_ids,
			'orderby'              => 'post__in',
			'posts_per_page'       => count( $bm_ai_lab_featured_ids ),
			'no_found_rows'        => true,
			'ignore_sticky_posts'  => true,
		)
	);
	?>
	<main class="bm-riset-page">

	  <header class="bm-page-head bm-riset-hero">
	    <div class="bm-container bm-riset-hero-inner">
	      <div class="bm-riset-hero-copy">
	        <span class="bm-riset-eyebrow">RISET</span>
	        <h1 class="bm-page-title">Research yang memperluas cara kami melihat pasar.</h1>
	        <p class="bm-riset-sub">Analisis pasar, teknologi, dan eksperimen dari Bitmomo.</p>
	      </div>
	      <nav class="bm-riset-nav" aria-label="<?php esc_attr_e( 'Bagian Riset', 'bitmomo' ); ?>">
	        <a href="#bm-riset-latest">Latest Research</a>
	        <a href="#bm-riset-ai-lab">AI Lab</a>
	        <a href="#bm-riset-archive">Arsip</a>
	      </nav>
	    </div>
	  </header>

	  <?php if ( ! empty( $bm_riset_primary ) ) : $bm_p = $bm_riset_primary[0]; ?>
	  <section class="bm-riset-latest" id="bm-riset-latest">
	    <div class="bm-container">
	      <span class="bm-riset-section-eyebrow">LATEST RESEARCH</span>

	      <div class="bm-riset-latest-grid">
	        <article class="bm-riset-primary">
	          <a class="bm-riset-primary-media" href="<?php echo esc_url( get_permalink( $bm_p ) ); ?>" tabindex="-1" aria-hidden="true">
	            <?php if ( has_post_thumbnail( $bm_p ) ) : ?>
	              <?php echo get_the_post_thumbnail( $bm_p, 'bm-card', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) ); ?>
	            <?php else : ?>
	              <?php
	              $bm_safe_title = esc_html( mb_strimwidth( wp_strip_all_tags( get_the_title( $bm_p ) ), 0, 48, '…', 'UTF-8' ) );
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
	          <div class="bm-riset-primary-body">
	            <h2><a href="<?php echo esc_url( get_permalink( $bm_p ) ); ?>"><?php echo esc_html( get_the_title( $bm_p ) ); ?></a></h2>
	            <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_p ), 20, '…' ) ); ?></p>
	            <div class="bm-riset-meta">
	              <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_p ) ); ?>"><?php echo esc_html( get_the_date( '', $bm_p ) ); ?></time>
	              <a class="bm-riset-link" href="<?php echo esc_url( get_permalink( $bm_p ) ); ?>">Baca &rarr;</a>
	            </div>
	          </div>
	        </article>

	        <?php if ( ! empty( $bm_riset_secondary ) ) : ?>
	        <ul class="bm-riset-secondary-list">
	          <?php foreach ( $bm_riset_secondary as $bm_s ) : ?>
	          <li>
	            <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_s ) ); ?>"><?php echo esc_html( get_the_date( '', $bm_s ) ); ?></time>
	            <h3><a href="<?php echo esc_url( get_permalink( $bm_s ) ); ?>"><?php echo esc_html( get_the_title( $bm_s ) ); ?></a></h3>
	            <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_s ), 12, '…' ) ); ?></p>
	          </li>
	          <?php endforeach; ?>
	        </ul>
	        <?php endif; ?>
	      </div>
	    </div>
	  </section>
	  <?php endif; ?>

	  <?php if ( ! empty( $bm_ai_lab_posts ) ) : ?>
	  <section class="bm-ai-lab-brand" id="bm-riset-ai-lab">
	    <div class="bm-container bm-ai-lab-inner">
	      <div class="bm-ai-lab-intro">
	        <p class="bm-ai-lab-kicker"><span>BITMOMO</span><strong>AI LAB</strong></p>
	        <h2>Mengeksplorasi bagaimana AI akan mengubah cara intelligence dibangun.</h2>
	        <p class="bm-ai-lab-support">Riset dan eksperimen Bitmomo pada AI agents, decentralized AI, dan sistem intelligence generasi berikutnya.</p>

	        <ul class="bm-ai-lab-pillars">
	          <li>
	            <span class="bm-ai-lab-pillar-name">AI Agents</span>
	            <p>Bagaimana sistem AI otonom mengambil keputusan yang konsisten dan dapat dipertanggungjawabkan.</p>
	          </li>
	          <li>
	            <span class="bm-ai-lab-pillar-name">Decentralized AI</span>
	            <p>Transparansi model dan provider yang dapat diverifikasi, bukan ketergantungan pada satu black-box provider.</p>
	          </li>
	          <li>
	            <span class="bm-ai-lab-pillar-name">AI Intelligence Systems</span>
	            <p>Mengukur apakah intelligence yang dihasilkan tetap akurat saat model, provider, dan kondisi pasar berubah.</p>
	          </li>
	        </ul>
	      </div>

	      <div class="bm-ai-lab-work">
	        <span class="bm-ai-lab-work-label">FROM THE LAB</span>
	        <ul class="bm-ai-lab-work-list">
	          <?php foreach ( $bm_ai_lab_posts as $bm_a ) : ?>
	          <li>
	            <span class="bm-ai-lab-work-topic"><?php echo esc_html( strtoupper( isset( $bm_ai_lab_pillar_map[ $bm_a->ID ] ) ? $bm_ai_lab_pillar_map[ $bm_a->ID ] : 'AI Lab' ) ); ?></span>
	            <a href="<?php echo esc_url( get_permalink( $bm_a ) ); ?>"><?php echo esc_html( get_the_title( $bm_a ) ); ?></a>
	          </li>
	          <?php endforeach; ?>
	        </ul>
	      </div>
	    </div>
	  </section>
	  <?php endif; ?>

	  <?php if ( ! empty( $bm_riset_archive ) ) : ?>
	  <section class="bm-riset-archive" id="bm-riset-archive">
	    <div class="bm-container">
	      <span class="bm-riset-section-eyebrow">ARSIP RISET</span>
	      <ul class="bm-riset-archive-list">
	        <?php foreach ( $bm_riset_archive as $bm_r ) : ?>
	        <li>
	          <div class="bm-riset-archive-row">
	            <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_r ) ); ?>"><?php echo esc_html( get_the_date( '', $bm_r ) ); ?></time>
	            <a href="<?php echo esc_url( get_permalink( $bm_r ) ); ?>"><?php echo esc_html( get_the_title( $bm_r ) ); ?></a>
	            <a class="bm-riset-archive-arrow" href="<?php echo esc_url( get_permalink( $bm_r ) ); ?>" aria-hidden="true">&rarr;</a>
	          </div>
	          <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_r ), 14, '…' ) ); ?></p>
	        </li>
	        <?php endforeach; ?>
	      </ul>
	    </div>
	  </section>
	  <?php endif; ?>

	  <?php if ( empty( $bm_riset_primary ) && empty( $bm_ai_lab_posts ) ) : ?>
	  <section class="bm-section">
	    <div class="bm-container">
	      <p style="opacity:.8"><?php esc_html_e( 'Belum ada riset yang dipublikasikan.', 'bitmomo' ); ?></p>
	    </div>
	  </section>
	  <?php endif; ?>
	</main>
	<?php
	unset( $bm_ai_lab_ids, $bm_ai_lab_featured_ids, $bm_ai_lab_pillar_map, $bm_riset_term, $bm_riset_posts, $bm_riset_primary, $bm_riset_secondary, $bm_riset_archive, $bm_ai_lab_posts );

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

<?php
/**
 * Canonical Bitmomo Research Hub.
 *
 * Riset remains the publication source of truth. AI Systems Research is the
 * ai-lab tagged subset; the remaining Riset corpus is Market Research. All
 * public surfaces consume the same taxonomy helpers so classification cannot
 * drift between hub, article and related-content surfaces.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_taxonomy  = bitmomo_research_taxonomy();
$bm_riset_id  = (int) $bm_taxonomy['riset_id'];
$bm_ai_tag_id = (int) $bm_taxonomy['ai_lab_tag_id'];

$bm_featured_posts = $bm_riset_id ? get_posts( array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 1,
    'cat'                    => $bm_riset_id,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
) ) : array();
$bm_featured    = $bm_featured_posts ? $bm_featured_posts[0] : null;
$bm_featured_id = $bm_featured ? (int) $bm_featured->ID : 0;

$bm_market_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 4,
    'cat'                    => $bm_riset_id,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
);
if ( $bm_ai_tag_id ) $bm_market_args['tag__not_in'] = array( $bm_ai_tag_id );
if ( $bm_featured_id ) $bm_market_args['post__not_in'] = array( $bm_featured_id );
$bm_market_posts = $bm_riset_id ? get_posts( $bm_market_args ) : array();

$bm_ai_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 4,
    'cat'                    => $bm_riset_id,
    'tag__in'                => $bm_ai_tag_id ? array( $bm_ai_tag_id ) : array( 0 ),
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
);
if ( $bm_featured_id ) $bm_ai_args['post__not_in'] = array( $bm_featured_id );
$bm_ai_posts = ( $bm_riset_id && $bm_ai_tag_id ) ? get_posts( $bm_ai_args ) : array();
?>
<section class="bm-research-hub" aria-labelledby="bm-research-hub-title">
  <div class="bm-container">
    <header class="bm-research-hub__hero">
      <div>
        <span class="bm-eyebrow">BITMOMO RESEARCH</span>
        <h1 id="bm-research-hub-title">Riset untuk memahami pasar. Riset untuk membangun intelligence yang lebih dapat dipercaya.</h1>
      </div>
      <p class="bm-research-hub__hero-copy">Bitmomo bekerja di dua lapisan yang saling melengkapi: memahami struktur dan fundamental pasar kripto, lalu meneliti sistem AI yang membantu mengubah data tersebut menjadi intelligence yang konsisten.</p>
      <p class="bm-research-hub__thesis">Kami tidak mengejar lebih banyak indikator atau lebih banyak output AI. Kami mengejar pemahaman yang lebih tajam, provenance yang lebih jelas, dan thesis yang dapat diuji.</p>
    </header>

    <div class="bm-research-disciplines" aria-label="Disiplin riset Bitmomo">
      <article class="bm-research-discipline">
        <span class="bm-research-discipline__index">01 · MARKETS</span>
        <h2>Crypto Market Research</h2>
        <p>Bitcoin, market structure, derivatives positioning, liquidity, macro, volatility, cycle behavior, dan fundamental drivers yang mengubah konteks pasar.</p>
        <ul aria-label="Fokus Crypto Market Research">
          <li>Bitcoin</li><li>Market Structure</li><li>Derivatives</li><li>Macro &amp; Liquidity</li><li>Fundamentals</li>
        </ul>
      </article>
      <article class="bm-research-discipline">
        <span class="bm-research-discipline__index">02 · SYSTEMS</span>
        <h2>AI Systems Research</h2>
        <p>Agent systems, evaluation, provenance, reliability, dan arsitektur intelligence yang tetap dapat dipertanggungjawabkan ketika model atau provider berubah.</p>
        <ul aria-label="Fokus AI Systems Research">
          <li>Agent Systems</li><li>AI Evaluation</li><li>Provenance</li><li>Decentralized AI</li><li>Reliability</li>
        </ul>
      </article>
    </div>

    <?php if ( $bm_featured ) :
      $bm_featured_cat = bitmomo_primary_public_category( $bm_featured_id );
      $bm_featured_label = $bm_featured_cat ? $bm_featured_cat->name : bitmomo_research_classification_label( $bm_featured_id );
      $bm_featured_excerpt = wp_trim_words( get_the_excerpt( $bm_featured ), 34, '…' );
    ?>
      <section class="bm-research-featured" aria-labelledby="bm-featured-research-title">
        <header class="bm-research-section-head">
          <div><span class="bm-eyebrow">FEATURED RESEARCH</span><h2 id="bm-featured-research-title">Riset unggulan terbaru</h2></div>
          <p>Analisis terbaru yang kami sorot untuk membantu memahami perubahan pasar atau sistem intelligence.</p>
        </header>
        <article class="bm-research-featured__card">
          <a class="bm-research-featured__media" href="<?php echo esc_url( get_permalink( $bm_featured ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $bm_featured ) ); ?>">
            <?php if ( has_post_thumbnail( $bm_featured ) ) : ?>
              <?php echo get_the_post_thumbnail( $bm_featured, 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
            <?php else : ?>
              <span class="bm-research-featured__placeholder">BITMOMO RESEARCH</span>
            <?php endif; ?>
          </a>
          <div class="bm-research-featured__body">
            <span class="bm-research-featured__meta"><?php echo esc_html( $bm_featured_label . ' · ' . get_the_date( 'd M Y', $bm_featured ) ); ?></span>
            <h3><a href="<?php echo esc_url( get_permalink( $bm_featured ) ); ?>"><?php echo esc_html( get_the_title( $bm_featured ) ); ?></a></h3>
            <?php if ( $bm_featured_excerpt ) : ?><p><?php echo esc_html( $bm_featured_excerpt ); ?></p><?php endif; ?>
            <a class="bm-research-featured__link" href="<?php echo esc_url( get_permalink( $bm_featured ) ); ?>">Baca riset lengkap &rarr;</a>
          </div>
        </article>
      </section>
    <?php endif; ?>

    <section class="bm-research-principles" aria-labelledby="bm-research-principles-title">
      <header class="bm-research-section-head">
        <div><span class="bm-eyebrow">RESEARCH STANDARD</span><h2 id="bm-research-principles-title">Standar yang sama untuk pasar maupun AI.</h2></div>
        <p>Format dapat berbeda, tetapi prinsip dasarnya tidak: evidence harus mendahului narrative dan claim harus punya batas yang jelas.</p>
      </header>
      <div class="bm-research-principles__grid">
        <article><strong>Traceable evidence</strong><p>Sumber data, waktu observasi, dan batas kualitas harus dapat ditelusuri.</p></article>
        <article><strong>Context over noise</strong><p>Data dibaca dalam struktur dan regime, bukan diperlakukan sebagai angka yang berdiri sendiri.</p></article>
        <article><strong>Thesis + invalidation</strong><p>Analisis harus menjelaskan apa yang mendukung thesis dan apa yang membuatnya tidak lagi berlaku.</p></article>
        <article><strong>Evaluation</strong><p>Insight dinilai terhadap hasil aktual dan proses diperbaiki ketika evidence berubah.</p></article>
      </div>
    </section>

    <div class="bm-research-streams">
      <section class="bm-research-stream" aria-labelledby="bm-market-research-stream">
        <header class="bm-research-stream__head">
          <h2 id="bm-market-research-stream">Market Research</h2>
          <p>Crypto markets, macro, structure, positioning, volatility, dan fundamentals.</p>
        </header>
        <?php if ( $bm_market_posts ) : ?>
          <ol class="bm-research-stream__list">
            <?php foreach ( $bm_market_posts as $bm_post ) : ?>
              <li class="bm-research-stream__item">
                <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y', $bm_post ) ); ?></time>
                <h3><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_post ), 18, '…' ) ); ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else : ?><p class="bm-research-stream__empty">Belum ada publikasi Market Research yang dapat ditampilkan.</p><?php endif; ?>
      </section>

      <section class="bm-research-stream" aria-labelledby="bm-ai-research-stream">
        <header class="bm-research-stream__head">
          <h2 id="bm-ai-research-stream">AI Lab</h2>
          <p>Agent systems, evaluation, provenance, decentralized AI, dan reliability.</p>
        </header>
        <?php if ( $bm_ai_posts ) : ?>
          <ol class="bm-research-stream__list">
            <?php foreach ( $bm_ai_posts as $bm_post ) : ?>
              <li class="bm-research-stream__item">
                <small>AI LAB · <?php echo esc_html( get_the_date( 'd M Y', $bm_post ) ); ?></small>
                <h3><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_post ), 18, '…' ) ); ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else : ?><p class="bm-research-stream__empty">Publikasi AI Lab akan muncul di sini setelah artikel dengan tag <strong>ai-lab</strong> diterbitkan.</p><?php endif; ?>
      </section>
    </div>

    <section class="bm-research-archive" aria-labelledby="bm-all-research-title">
      <header class="bm-research-section-head">
        <div><span class="bm-eyebrow">ARCHIVE</span><h2 id="bm-all-research-title">Seluruh publikasi</h2></div>
        <p>Arsip kronologis seluruh artikel dalam kategori Riset.</p>
      </header>

      <?php if ( have_posts() ) : ?>
        <div class="bm-research-grid">
          <?php while ( have_posts() ) : the_post();
            $bm_card_cat = bitmomo_primary_public_category( get_the_ID() );
            $bm_card_label = $bm_card_cat ? $bm_card_cat->name : bitmomo_research_classification_label( get_the_ID() );
          ?>
            <article <?php post_class( 'bm-research-card' ); ?>>
              <a class="bm-research-card__art" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
                <?php if ( has_post_thumbnail() ) the_post_thumbnail( 'bm-card', array( 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
              </a>
              <div class="bm-research-card__body">
                <span class="bm-research-card__meta"><?php echo esc_html( $bm_card_label . ' · ' . get_the_date( 'd M Y' ) ); ?></span>
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ); ?></p>
              </div>
            </article>
          <?php endwhile; ?>
        </div>
        <nav class="bm-pagination bm-pagination--v2" aria-label="<?php esc_attr_e( 'Pagination', 'bitmomo' ); ?>">
          <?php echo wp_kses_post( paginate_links( array( 'prev_text' => '← ' . __( 'Sebelumnya', 'bitmomo' ), 'next_text' => __( 'Berikutnya', 'bitmomo' ) . ' →' ) ) ); ?>
        </nav>
      <?php else : ?>
        <div class="bm-public-empty"><h2>Belum ada publikasi.</h2><p>Riset baru akan muncul di sini setelah diterbitkan.</p></div>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php
unset(
  $bm_taxonomy, $bm_riset_id, $bm_ai_tag_id, $bm_featured_posts, $bm_featured, $bm_featured_id,
  $bm_market_args, $bm_market_posts, $bm_ai_args, $bm_ai_posts, $bm_featured_cat, $bm_featured_label,
  $bm_featured_excerpt, $bm_post, $bm_card_cat, $bm_card_label
);
?>

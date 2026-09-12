<?php
/**
 * Canonical Bitmomo Research Hub.
 *
 * Only explicitly classified Market Research or AI Systems Research is
 * promoted on this surface. Legacy/general Riset posts remain published at
 * their original URLs but are not silently upgraded into the new research
 * taxonomy.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_riset_term = get_category_by_slug( 'riset' );
$bm_riset_id   = $bm_riset_term ? (int) $bm_riset_term->term_id : 0;
$bm_ai_tag     = get_term_by( 'slug', 'ai-lab', 'post_tag' );
$bm_ai_tag_id  = ( $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) ? (int) $bm_ai_tag->term_id : 0;
$bm_market_slugs = function_exists( 'bitmomo_market_research_taxonomy_slugs' )
    ? bitmomo_market_research_taxonomy_slugs()
    : array( 'bitcoin', 'btc', 'makro', 'macro', 'market-structure', 'derivatives', 'funding-rate', 'etf', 'liquidity', 'likuiditas', 'fundamental', 'fundamentals' );

$bm_market_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 7,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
);
if ( $bm_riset_id ) $bm_market_args['cat'] = $bm_riset_id;
if ( $bm_ai_tag_id ) $bm_market_args['tag__not_in'] = array( $bm_ai_tag_id );
$bm_market_args['tax_query'] = array(
    'relation' => 'OR',
    array(
        'taxonomy' => 'category',
        'field'    => 'slug',
        'terms'    => $bm_market_slugs,
    ),
    array(
        'taxonomy' => 'post_tag',
        'field'    => 'slug',
        'terms'    => $bm_market_slugs,
    ),
);
$bm_market_posts = $bm_riset_id ? get_posts( $bm_market_args ) : array();

$bm_ai_posts = ( $bm_riset_id && $bm_ai_tag_id ) ? get_posts( array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 7,
    'cat'                    => $bm_riset_id,
    'tag__in'                => array( $bm_ai_tag_id ),
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
) ) : array();

$bm_qualified_map = array();
foreach ( array_merge( $bm_market_posts, $bm_ai_posts ) as $bm_post ) {
    $bm_qualified_map[ (int) $bm_post->ID ] = $bm_post;
}
$bm_qualified_posts = array_values( $bm_qualified_map );
usort( $bm_qualified_posts, static function ( $a, $b ) {
    return strcmp( (string) $b->post_date_gmt, (string) $a->post_date_gmt );
} );
$bm_featured = $bm_qualified_posts ? $bm_qualified_posts[0] : null;

if ( $bm_featured ) {
    $bm_featured_id = (int) $bm_featured->ID;
    $bm_market_posts = array_values( array_filter( $bm_market_posts, static fn( $post ) => (int) $post->ID !== $bm_featured_id ) );
    $bm_ai_posts = array_values( array_filter( $bm_ai_posts, static fn( $post ) => (int) $post->ID !== $bm_featured_id ) );
} else {
    $bm_featured_id = 0;
}
?>
<section class="bm-research-hub" aria-labelledby="bm-research-hub-title">
  <div class="bm-container">
    <header class="bm-research-hub__hero">
      <div class="bm-research-hub__hero-copyblock">
        <p class="bm-research-kicker">BITMOMO RESEARCH</p>
        <h1 id="bm-research-hub-title">Riset untuk memahami pasar. Sistem untuk mengubah evidence menjadi intelligence.</h1>
        <p class="bm-research-hub__lead">Bitmomo bekerja di dua disiplin yang saling melengkapi: crypto market research dan AI systems research. Tujuannya bukan menghasilkan lebih banyak indikator atau lebih banyak output AI, tetapi menghasilkan pemahaman yang lebih tajam dan dapat diuji.</p>
        <div class="bm-research-hub__actions">
          <a class="bm-research-button bm-research-button--primary" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Lihat BTC Intelligence</a>
          <a class="bm-research-button" href="<?php echo esc_url( home_url( '/#founding-whitelist' ) ); ?>">Gabung Founding Whitelist</a>
        </div>
      </div>
      <aside class="bm-research-manifesto" aria-label="Standar Bitmomo Research">
        <span>RESEARCH STANDARD</span>
        <strong>Evidence before narrative.</strong>
        <p>Claim harus punya sumber, konteks, batas, dan cara untuk dinilai ketika evidence berubah.</p>
      </aside>
    </header>

    <section class="bm-research-disciplines" aria-label="Disiplin riset Bitmomo">
      <article class="bm-research-discipline">
        <span class="bm-research-discipline__index">01 · MARKETS</span>
        <h2>Crypto Market Research</h2>
        <p>Bitcoin, market structure, derivatives positioning, liquidity, macro, volatility, cycle behavior, serta fundamental drivers yang mengubah konteks pasar.</p>
        <ul aria-label="Fokus Crypto Market Research">
          <li>Bitcoin</li><li>Market Structure</li><li>Derivatives</li><li>Macro &amp; Liquidity</li><li>Fundamentals</li>
        </ul>
      </article>
      <article class="bm-research-discipline">
        <span class="bm-research-discipline__index">02 · SYSTEMS</span>
        <h2>AI Systems Research</h2>
        <p>Agent systems, evaluation, provenance, reliability, decentralized AI, dan arsitektur intelligence yang tetap dapat dipertanggungjawabkan ketika model atau provider berubah.</p>
        <ul aria-label="Fokus AI Systems Research">
          <li>Agent Systems</li><li>AI Evaluation</li><li>Provenance</li><li>Reliability</li><li>Decentralized AI</li>
        </ul>
      </article>
    </section>

    <section class="bm-research-principles" aria-labelledby="bm-research-principles-title">
      <header class="bm-research-section-head">
        <div>
          <p class="bm-research-kicker">HOW WE WORK</p>
          <h2 id="bm-research-principles-title">Research yang harus bertahan terhadap scrutiny.</h2>
        </div>
        <p>Format publikasi dapat berbeda, tetapi standar dasarnya sama untuk riset pasar maupun AI.</p>
      </header>
      <div class="bm-research-principles__grid">
        <article><strong>Traceable evidence</strong><p>Sumber data, waktu observasi, dan quality boundary harus dapat ditelusuri sejauh sistem memungkinkan.</p></article>
        <article><strong>Context over noise</strong><p>Data dibaca dalam struktur pasar, bukan diperlakukan sebagai angka atau headline yang berdiri sendiri.</p></article>
        <article><strong>Thesis + invalidation</strong><p>Analisis harus menjelaskan apa yang mendukung thesis dan apa yang membuatnya tidak lagi berlaku.</p></article>
        <article><strong>Evaluation</strong><p>Insight yang dapat diuji dinilai terhadap hasil aktual; sampel kecil tetap disebut sebagai sampel kecil.</p></article>
      </div>
    </section>

    <?php if ( $bm_featured ) :
      $bm_featured_is_ai = has_tag( 'ai-lab', $bm_featured_id );
      $bm_featured_label = $bm_featured_is_ai ? 'AI SYSTEMS RESEARCH' : 'MARKET RESEARCH';
      $bm_featured_excerpt = wp_trim_words( get_the_excerpt( $bm_featured ), 32, '…' );
    ?>
      <section class="bm-research-featured" aria-labelledby="bm-featured-research-title">
        <header class="bm-research-section-head">
          <div>
            <p class="bm-research-kicker">LATEST QUALIFIED RESEARCH</p>
            <h2 id="bm-featured-research-title">Publikasi terbaru</h2>
          </div>
          <p>Hanya publikasi dengan klasifikasi research yang eksplisit yang dipromosikan pada surface ini.</p>
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
            <a class="bm-research-featured__link" href="<?php echo esc_url( get_permalink( $bm_featured ) ); ?>">Baca riset lengkap →</a>
          </div>
        </article>
      </section>
    <?php endif; ?>

    <div class="bm-research-streams">
      <section class="bm-research-stream" aria-labelledby="bm-market-research-stream">
        <header class="bm-research-stream__head">
          <p class="bm-research-kicker">MARKETS</p>
          <h2 id="bm-market-research-stream">Market Research</h2>
          <p>Publikasi dengan taxonomy pasar yang eksplisit: Bitcoin, macro, market structure, derivatives, liquidity, ETF, funding, atau fundamentals.</p>
        </header>
        <?php if ( $bm_market_posts ) : ?>
          <ol class="bm-research-stream__list">
            <?php foreach ( array_slice( $bm_market_posts, 0, 5 ) as $bm_post ) : ?>
              <li class="bm-research-stream__item">
                <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y', $bm_post ) ); ?></time>
                <h3><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_post ), 20, '…' ) ); ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else : ?>
          <p class="bm-research-stream__empty">Belum ada publikasi yang memenuhi taxonomy Market Research.</p>
        <?php endif; ?>
      </section>

      <section class="bm-research-stream" aria-labelledby="bm-ai-research-stream">
        <header class="bm-research-stream__head">
          <p class="bm-research-kicker">SYSTEMS</p>
          <h2 id="bm-ai-research-stream">AI Systems Research</h2>
          <p>Agent systems, evaluation, provenance, reliability, decentralized AI, dan metode untuk mengurangi failure mode dari black-box intelligence.</p>
        </header>
        <?php if ( $bm_ai_posts ) : ?>
          <ol class="bm-research-stream__list">
            <?php foreach ( array_slice( $bm_ai_posts, 0, 5 ) as $bm_post ) : ?>
              <li class="bm-research-stream__item">
                <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y', $bm_post ) ); ?></time>
                <h3><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_post ), 20, '…' ) ); ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else : ?>
          <p class="bm-research-stream__empty">Publikasi AI Systems Research akan muncul setelah artikel Riset memiliki tag <strong>ai-lab</strong>.</p>
        <?php endif; ?>
      </section>
    </div>

    <footer class="bm-research-footnote">
      <strong>Classification boundary</strong>
      <p>Artikel legacy/general yang belum memiliki taxonomy research baru tetap tersedia di URL aslinya, tetapi tidak dipromosikan sebagai Market Research atau AI Systems Research sampai klasifikasinya eksplisit.</p>
    </footer>
  </div>
</section>
<?php
unset(
    $bm_riset_term, $bm_riset_id, $bm_ai_tag, $bm_ai_tag_id, $bm_market_slugs, $bm_market_args,
    $bm_market_posts, $bm_ai_posts, $bm_qualified_map, $bm_qualified_posts, $bm_featured,
    $bm_featured_id, $bm_featured_is_ai, $bm_featured_label, $bm_featured_excerpt, $bm_post
);
?>

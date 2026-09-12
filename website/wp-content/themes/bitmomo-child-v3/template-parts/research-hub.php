<?php
/**
 * Canonical Bitmomo Research Hub.
 *
 * This surface is a research workspace, not a generic WordPress archive or a
 * marketing landing page. Only explicitly classified Market Research or
 * Intelligence Systems Research may be promoted here.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_riset_term = get_category_by_slug( 'riset' );
$bm_riset_id   = $bm_riset_term ? (int) $bm_riset_term->term_id : 0;
$bm_research_url = $bm_riset_term ? get_category_link( $bm_riset_id ) : home_url( '/category/riset/' );
$bm_ai_tag     = get_term_by( 'slug', 'ai-lab', 'post_tag' );
$bm_ai_tag_id  = ( $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) ? (int) $bm_ai_tag->term_id : 0;
$bm_market_slugs = bitmomo_market_research_taxonomy_slugs();
$bm_focus_filters = bitmomo_research_focus_filters();

$bm_focus = isset( $_GET['focus'] ) ? sanitize_key( wp_unslash( $_GET['focus'] ) ) : 'all';
if ( ! isset( $bm_focus_filters[ $bm_focus ] ) ) $bm_focus = 'all';
$bm_research_q = isset( $_GET['research_q'] ) ? sanitize_text_field( wp_unslash( $_GET['research_q'] ) ) : '';
$bm_research_q = mb_substr( trim( $bm_research_q ), 0, 80 );

$bm_market_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 36,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
);
if ( $bm_research_q ) $bm_market_args['s'] = $bm_research_q;
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
$bm_market_posts = array_values( array_filter( $bm_market_posts, static fn( $post ) => bitmomo_post_is_market_research( (int) $post->ID ) ) );

$bm_ai_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'posts_per_page'         => 36,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
);
if ( $bm_research_q ) $bm_ai_args['s'] = $bm_research_q;
if ( $bm_riset_id ) $bm_ai_args['cat'] = $bm_riset_id;
if ( $bm_ai_tag_id ) $bm_ai_args['tag__in'] = array( $bm_ai_tag_id );
$bm_ai_posts = ( $bm_riset_id && $bm_ai_tag_id ) ? get_posts( $bm_ai_args ) : array();
$bm_ai_posts = array_values( array_filter( $bm_ai_posts, static fn( $post ) => bitmomo_post_is_ai_systems_research( (int) $post->ID ) ) );

$bm_qualified_map = array();
foreach ( array_merge( $bm_market_posts, $bm_ai_posts ) as $bm_post ) {
    $bm_qualified_map[ (int) $bm_post->ID ] = $bm_post;
}
$bm_qualified_posts = array_values( $bm_qualified_map );
usort( $bm_qualified_posts, static function ( $a, $b ) {
    return strcmp( (string) $b->post_date_gmt, (string) $a->post_date_gmt );
} );

$bm_filtered_posts = array_values( array_filter(
    $bm_qualified_posts,
    static fn( $post ) => bitmomo_post_matches_research_focus( (int) $post->ID, $bm_focus )
) );
$bm_lead = $bm_filtered_posts ? $bm_filtered_posts[0] : null;
$bm_lead_id = $bm_lead ? (int) $bm_lead->ID : 0;
$bm_library_posts = array_slice(
    array_values( array_filter( $bm_filtered_posts, static fn( $post ) => (int) $post->ID !== $bm_lead_id ) ),
    0,
    10
);

$bm_filter_url = static function ( $focus ) use ( $bm_research_url, $bm_research_q ) {
    $args = array();
    if ( 'all' !== $focus ) $args['focus'] = $focus;
    if ( $bm_research_q ) $args['research_q'] = $bm_research_q;
    return $args ? add_query_arg( $args, $bm_research_url ) : $bm_research_url;
};

$bm_domain_market = array_slice( array_values( array_filter(
    $bm_market_posts,
    static fn( $post ) => (int) $post->ID !== $bm_lead_id
) ), 0, 3 );
$bm_domain_systems = array_slice( array_values( array_filter(
    $bm_ai_posts,
    static fn( $post ) => (int) $post->ID !== $bm_lead_id
) ), 0, 3 );
?>
<section class="bm-research-hub" aria-labelledby="bm-research-hub-title">
  <div class="bm-container">
    <header class="bm-research-head">
      <p class="bm-research-kicker">BITMOMO RESEARCH</p>
      <div class="bm-research-head__grid">
        <div>
          <h1 id="bm-research-hub-title">Riset pasar yang dapat diuji. Sistem intelligence yang dapat dipercaya.</h1>
          <p class="bm-research-head__lead">Research desk untuk Bitcoin dan digital-asset markets, dengan fokus pada market structure, derivatives, liquidity, macro, capital flows, serta sistem intelligence yang menjaga provenance, reliability, dan evaluation.</p>
        </div>
        <div class="bm-research-head__utility">
          <p><strong>Coverage</strong><span>Markets · Intelligence Systems</span></p>
          <p><strong>Standard</strong><span>Evidence before narrative.</span></p>
          <a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Open BTC Intelligence →</a>
        </div>
      </div>
      <div class="bm-research-standard-line" aria-label="Standar Research Bitmomo">
        <span>TRACEABLE EVIDENCE</span><span>THESIS + INVALIDATION</span><span>PROVENANCE</span><span>EVALUATION</span>
      </div>
    </header>

    <section class="bm-research-discovery" aria-label="Navigasi dan pencarian research">
      <nav class="bm-research-filter" aria-label="Filter Research">
        <?php foreach ( $bm_focus_filters as $bm_filter_key => $bm_filter ) : ?>
          <a href="<?php echo esc_url( $bm_filter_url( $bm_filter_key ) ); ?>"<?php echo $bm_filter_key === $bm_focus ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $bm_filter['label'] ); ?></a>
        <?php endforeach; ?>
      </nav>
      <form class="bm-research-search" method="get" action="<?php echo esc_url( $bm_research_url ); ?>" role="search">
        <?php if ( 'all' !== $bm_focus ) : ?><input type="hidden" name="focus" value="<?php echo esc_attr( $bm_focus ); ?>"><?php endif; ?>
        <label for="bm-research-search-input">Cari research</label>
        <div>
          <input id="bm-research-search-input" type="search" name="research_q" value="<?php echo esc_attr( $bm_research_q ); ?>" placeholder="Search title, thesis, topic…">
          <button type="submit">Search</button>
        </div>
      </form>
    </section>

    <?php if ( $bm_lead ) :
      $bm_lead_class = bitmomo_post_research_classification( $bm_lead_id );
      $bm_lead_domain = 'ai-systems' === $bm_lead_class ? 'INTELLIGENCE SYSTEMS' : 'MARKET RESEARCH';
      $bm_lead_topic = bitmomo_post_research_topic_label( $bm_lead_id );
      $bm_lead_minutes = bitmomo_post_reading_minutes( $bm_lead_id );
      $bm_lead_excerpt = wp_trim_words( get_the_excerpt( $bm_lead ), 38, '…' );
      $bm_lead_summary_label = has_excerpt( $bm_lead_id ) ? 'KEY FINDING' : 'RESEARCH SUMMARY';
      $bm_has_figure = has_post_thumbnail( $bm_lead_id );
    ?>
      <section class="bm-research-lead" id="latest-research" aria-labelledby="bm-lead-research-title">
        <div class="bm-research-section-label"><span>LEAD RESEARCH</span><span><?php echo esc_html( $bm_focus_filters[ $bm_focus ]['label'] ); ?></span></div>
        <article class="bm-research-lead__paper<?php echo $bm_has_figure ? '' : ' bm-research-lead__paper--text'; ?>">
          <div class="bm-research-lead__body">
            <p class="bm-research-meta"><?php echo esc_html( $bm_lead_domain . ' · ' . strtoupper( $bm_lead_topic ) . ' · ' . get_the_date( 'd M Y', $bm_lead ) . ' · ' . $bm_lead_minutes . ' MIN READ' ); ?></p>
            <h2 id="bm-lead-research-title"><a href="<?php echo esc_url( get_permalink( $bm_lead ) ); ?>"><?php echo esc_html( get_the_title( $bm_lead ) ); ?></a></h2>
            <?php if ( $bm_lead_excerpt ) : ?>
              <div class="bm-research-lead__finding">
                <span><?php echo esc_html( $bm_lead_summary_label ); ?></span>
                <p><?php echo esc_html( $bm_lead_excerpt ); ?></p>
              </div>
            <?php endif; ?>
            <a class="bm-research-text-link" href="<?php echo esc_url( get_permalink( $bm_lead ) ); ?>">Read full research →</a>
          </div>
          <?php if ( $bm_has_figure ) : ?>
            <a class="bm-research-lead__figure" href="<?php echo esc_url( get_permalink( $bm_lead ) ); ?>" aria-label="Buka <?php echo esc_attr( get_the_title( $bm_lead ) ); ?>">
              <span>RESEARCH FIGURE</span>
              <?php echo get_the_post_thumbnail( $bm_lead, 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
            </a>
          <?php endif; ?>
        </article>
      </section>
    <?php endif; ?>

    <section class="bm-research-library" id="research-library" aria-labelledby="bm-research-library-title">
      <header class="bm-research-library__head">
        <div>
          <p class="bm-research-kicker">RESEARCH LIBRARY</p>
          <h2 id="bm-research-library-title"><?php echo esc_html( $bm_focus_filters[ $bm_focus ]['label'] ); ?></h2>
        </div>
        <p><?php echo $bm_research_q ? esc_html( 'Search: “' . $bm_research_q . '”' ) : 'Latest qualified publications, ordered by publication date.'; ?></p>
      </header>

      <?php if ( $bm_library_posts ) : ?>
        <ol class="bm-research-library__list">
          <?php foreach ( $bm_library_posts as $bm_post ) :
            $bm_post_id = (int) $bm_post->ID;
            $bm_classification = bitmomo_post_research_classification( $bm_post_id );
            $bm_domain_label = 'ai-systems' === $bm_classification ? 'INTELLIGENCE SYSTEMS' : 'MARKET RESEARCH';
            $bm_topic_label = bitmomo_post_research_topic_label( $bm_post_id );
            $bm_minutes = bitmomo_post_reading_minutes( $bm_post_id );
          ?>
            <li>
              <div class="bm-research-library__meta">
                <span><?php echo esc_html( $bm_domain_label ); ?></span>
                <strong><?php echo esc_html( $bm_topic_label ); ?></strong>
                <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y', $bm_post ) ); ?></time>
                <small><?php echo esc_html( $bm_minutes . ' min read' ); ?></small>
              </div>
              <div class="bm-research-library__copy">
                <h3><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_post ), 24, '…' ) ); ?></p>
              </div>
              <a class="bm-research-library__open" href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>" aria-label="Baca <?php echo esc_attr( get_the_title( $bm_post ) ); ?>">↗</a>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php elseif ( ! $bm_lead ) : ?>
        <div class="bm-research-empty">
          <strong>No qualified research found.</strong>
          <p>Ubah filter atau kata pencarian. Artikel legacy yang belum memiliki classification research eksplisit tetap tidak akan dipromosikan di sini.</p>
          <a href="<?php echo esc_url( $bm_research_url ); ?>">Reset research view →</a>
        </div>
      <?php else : ?>
        <p class="bm-research-library__single">Hanya satu publikasi qualified tersedia untuk view ini; publikasi tersebut ditampilkan sebagai Lead Research di atas.</p>
      <?php endif; ?>
    </section>

    <section class="bm-research-domains" aria-labelledby="bm-research-domains-title">
      <header class="bm-research-library__head">
        <div>
          <p class="bm-research-kicker">RESEARCH DOMAINS</p>
          <h2 id="bm-research-domains-title">Two disciplines. One intelligence standard.</h2>
        </div>
        <p>Markets menjelaskan apa yang berubah. Intelligence Systems menjelaskan bagaimana evidence diproses, diuji, dan dibuat dapat dipercaya.</p>
      </header>
      <div class="bm-research-domains__grid">
        <article class="bm-research-domain">
          <div class="bm-research-domain__head">
            <span>01 · MARKETS</span>
            <h3>Market Research</h3>
            <p>Bitcoin, macro, market structure, derivatives, liquidity, capital flows, volatility, dan fundamental drivers.</p>
            <a href="<?php echo esc_url( $bm_filter_url( 'bitcoin' ) ); ?>">Browse market research →</a>
          </div>
          <?php if ( $bm_domain_market ) : ?><ol><?php foreach ( $bm_domain_market as $bm_post ) : ?><li><time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M', $bm_post ) ); ?></time><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></li><?php endforeach; ?></ol><?php endif; ?>
        </article>
        <article class="bm-research-domain">
          <div class="bm-research-domain__head">
            <span>02 · SYSTEMS</span>
            <h3>Intelligence Systems</h3>
            <p>Agent architecture, evaluation, provenance, reliability, model boundaries, and decision-intelligence systems.</p>
            <a href="<?php echo esc_url( $bm_filter_url( 'systems' ) ); ?>">Browse intelligence systems →</a>
          </div>
          <?php if ( $bm_domain_systems ) : ?><ol><?php foreach ( $bm_domain_systems as $bm_post ) : ?><li><time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M', $bm_post ) ); ?></time><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></li><?php endforeach; ?></ol><?php endif; ?>
        </article>
      </div>
    </section>

    <section class="bm-research-programs" aria-labelledby="bm-research-programs-title">
      <header class="bm-research-library__head">
        <div>
          <p class="bm-research-kicker">RESEARCH PROGRAMS</p>
          <h2 id="bm-research-programs-title">Recurring lenses for complex markets.</h2>
        </div>
        <p>Program research mengelompokkan pertanyaan yang terus diuji, bukan sekadar topik yang sedang ramai.</p>
      </header>
      <div class="bm-research-programs__list">
        <a href="<?php echo esc_url( $bm_filter_url( 'market-structure' ) ); ?>"><span>01</span><strong>Market Structure Notes</strong><em>Trend, structure breaks, regime context, and price behavior.</em><b>→</b></a>
        <a href="<?php echo esc_url( $bm_filter_url( 'derivatives' ) ); ?>"><span>02</span><strong>Derivatives Monitor</strong><em>Funding, positioning, leverage, and futures-market structure.</em><b>→</b></a>
        <a href="<?php echo esc_url( $bm_filter_url( 'flows' ) ); ?>"><span>03</span><strong>ETF &amp; Capital Flows</strong><em>Demand absorption, institutional flows, and market transmission.</em><b>→</b></a>
        <a href="<?php echo esc_url( $bm_filter_url( 'systems' ) ); ?>"><span>04</span><strong>Intelligence Systems</strong><em>Provenance, evaluation, agent reliability, and decision-system design.</em><b>→</b></a>
      </div>
    </section>

    <section class="bm-research-methodology" id="research-standard" aria-labelledby="bm-research-methodology-title">
      <div class="bm-research-methodology__intro">
        <p class="bm-research-kicker">RESEARCH STANDARD</p>
        <h2 id="bm-research-methodology-title">Evidence before narrative.</h2>
        <p>Research Bitmomo harus menjelaskan evidence, konteks, batas thesis, dan bagaimana kesimpulan dapat dievaluasi ketika data berubah.</p>
      </div>
      <div class="bm-research-methodology__rules">
        <div><span>01</span><strong>Traceable evidence</strong><p>Sumber, waktu observasi, dan quality boundary harus dapat ditelusuri sejauh sistem memungkinkan.</p></div>
        <div><span>02</span><strong>Context over noise</strong><p>Data dibaca dalam struktur dan regime, bukan sebagai angka atau headline yang berdiri sendiri.</p></div>
        <div><span>03</span><strong>Thesis + invalidation</strong><p>Analisis menjelaskan apa yang mendukung thesis dan apa yang membuatnya tidak lagi berlaku.</p></div>
        <div><span>04</span><strong>Evaluation</strong><p>Insight yang dapat diuji dinilai terhadap hasil aktual; ketidakpastian dan sampel kecil tetap disebut apa adanya.</p></div>
      </div>
    </section>

    <footer class="bm-research-boundary">
      <strong>Classification boundary</strong>
      <p>Hanya publikasi dengan classification research eksplisit yang muncul di Research Hub. Artikel legacy/general tetap tersedia di URL aslinya tetapi tidak otomatis memperoleh label institutional research.</p>
    </footer>
  </div>
</section>
<?php
unset(
    $bm_riset_term, $bm_riset_id, $bm_research_url, $bm_ai_tag, $bm_ai_tag_id, $bm_market_slugs,
    $bm_focus_filters, $bm_focus, $bm_research_q, $bm_market_args, $bm_ai_args, $bm_market_posts,
    $bm_ai_posts, $bm_qualified_map, $bm_qualified_posts, $bm_filtered_posts, $bm_lead, $bm_lead_id,
    $bm_library_posts, $bm_filter_url, $bm_domain_market, $bm_domain_systems, $bm_filter_key, $bm_filter,
    $bm_lead_class, $bm_lead_domain, $bm_lead_topic, $bm_lead_minutes, $bm_lead_excerpt,
    $bm_lead_summary_label, $bm_has_figure, $bm_post, $bm_post_id, $bm_classification, $bm_domain_label,
    $bm_topic_label, $bm_minutes
);
?>

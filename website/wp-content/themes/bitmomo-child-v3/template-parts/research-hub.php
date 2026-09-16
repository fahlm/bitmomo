<?php
/**
 * Canonical Bitmomo Research Hub.
 *
 * Publication-first: qualified research is the primary proof. Desk and topic
 * navigation support discovery without advertising an empty research surface.
 *
 * Research Taxonomy V3 has a migration-safe compatibility bridge:
 * - before activation, the audited legacy Riset boundary remains authoritative;
 * - after activation, explicit Research Desk + Research Topic metadata owns
 *   classification and scalable server-side discovery.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_riset_term = get_category_by_slug( 'riset' );
$bm_riset_id   = $bm_riset_term ? (int) $bm_riset_term->term_id : 0;
$bm_research_url = $bm_riset_term ? get_category_link( $bm_riset_id ) : home_url( '/category/riset/' );
$bm_focus_filters = bitmomo_research_focus_filters();
$bm_taxonomy_v3 = function_exists( 'bitmomo_research_taxonomy_is_active' ) && bitmomo_research_taxonomy_is_active();

$bm_requested_focus = isset( $_GET['focus'] ) ? sanitize_key( wp_unslash( $_GET['focus'] ) ) : 'all';
$bm_research_q = isset( $_GET['research_q'] ) ? sanitize_text_field( wp_unslash( $_GET['research_q'] ) ) : '';
$bm_research_q = mb_substr( trim( $bm_research_q ), 0, 80 );
$bm_page = max( 1, (int) get_query_var( 'paged', 1 ) );
$bm_per_page = 10;

$bm_visible_filters = array();
$bm_lead = null;
$bm_lead_id = 0;
$bm_library_posts = array();
$bm_total_pages = 1;
$bm_library_query = null;

if ( $bm_taxonomy_v3 ) {
    if ( isset( $bm_focus_filters['all'] ) ) $bm_visible_filters['all'] = $bm_focus_filters['all'];
    foreach ( $bm_focus_filters as $bm_filter_key => $bm_filter ) {
        if ( 'all' === $bm_filter_key ) continue;
        if ( bitmomo_research_focus_has_posts( $bm_filter_key, $bm_research_q ) ) {
            $bm_visible_filters[ $bm_filter_key ] = $bm_filter;
        }
    }

    $bm_focus = isset( $bm_visible_filters[ $bm_requested_focus ] ) ? $bm_requested_focus : 'all';

    $bm_lead_query = new WP_Query(
        bitmomo_research_query_args(
            $bm_focus,
            $bm_research_q,
            array(
                'posts_per_page' => 6,
                'no_found_rows'  => true,
            )
        )
    );
    foreach ( $bm_lead_query->posts as $bm_candidate ) {
        if ( bitmomo_post_matches_research_focus( (int) $bm_candidate->ID, $bm_focus ) ) {
            $bm_lead = $bm_candidate;
            break;
        }
    }
    $bm_lead_id = $bm_lead ? (int) $bm_lead->ID : 0;

    $bm_library_query = new WP_Query(
        bitmomo_research_query_args(
            $bm_focus,
            $bm_research_q,
            array(
                'posts_per_page' => $bm_per_page,
                'paged'          => $bm_page,
                'post__not_in'   => $bm_lead_id ? array( $bm_lead_id ) : array(),
            )
        )
    );
    $bm_library_posts = array_values(
        array_filter(
            $bm_library_query->posts,
            static fn( $post ) => bitmomo_post_matches_research_focus( (int) $post->ID, $bm_focus )
        )
    );
    $bm_total_pages = max( 1, (int) $bm_library_query->max_num_pages );
} else {
    /* Migration-safe legacy path. V3 activation remains a separate DB decision. */
    $bm_ai_tag     = get_term_by( 'slug', 'ai-lab', 'post_tag' );
    $bm_ai_tag_id  = ( $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) ? (int) $bm_ai_tag->term_id : 0;
    $bm_market_slugs = bitmomo_market_research_taxonomy_slugs();

    $bm_market_args = array(
        'post_type'              => 'post',
        'post_status'            => 'publish',
        'posts_per_page'         => 100,
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
        'posts_per_page'         => 100,
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

    if ( isset( $bm_focus_filters['all'] ) ) $bm_visible_filters['all'] = $bm_focus_filters['all'];
    foreach ( $bm_focus_filters as $bm_filter_key => $bm_filter ) {
        if ( 'all' === $bm_filter_key ) continue;
        foreach ( $bm_qualified_posts as $bm_post ) {
            if ( bitmomo_post_matches_research_focus( (int) $bm_post->ID, $bm_filter_key ) ) {
                $bm_visible_filters[ $bm_filter_key ] = $bm_filter;
                break;
            }
        }
    }

    $bm_focus = isset( $bm_visible_filters[ $bm_requested_focus ] ) ? $bm_requested_focus : 'all';
    $bm_filtered_posts = array_values(
        array_filter(
            $bm_qualified_posts,
            static fn( $post ) => bitmomo_post_matches_research_focus( (int) $post->ID, $bm_focus )
        )
    );
    $bm_lead = $bm_filtered_posts ? $bm_filtered_posts[0] : null;
    $bm_lead_id = $bm_lead ? (int) $bm_lead->ID : 0;
    $bm_library_all = array_values(
        array_filter( $bm_filtered_posts, static fn( $post ) => (int) $post->ID !== $bm_lead_id )
    );
    $bm_total_pages = max( 1, (int) ceil( count( $bm_library_all ) / $bm_per_page ) );
    $bm_library_posts = array_slice( $bm_library_all, ( $bm_page - 1 ) * $bm_per_page, $bm_per_page );
}

if ( empty( $bm_visible_filters ) ) {
    $bm_visible_filters['all'] = $bm_focus_filters['all'];
}
$bm_focus = isset( $bm_visible_filters[ $bm_requested_focus ] ) ? $bm_requested_focus : 'all';
$bm_active_filter = $bm_visible_filters[ $bm_focus ];
$bm_active_discipline = (string) ( $bm_active_filter['discipline'] ?? 'all' );
$bm_active_desk = 'market' === $bm_active_discipline ? 'market' : ( 'ai-systems' === $bm_active_discipline ? 'systems' : 'all' );

$bm_visible_desks = array();
foreach ( array( 'all', 'market', 'systems' ) as $bm_desk_key ) {
    if ( isset( $bm_visible_filters[ $bm_desk_key ] ) ) $bm_visible_desks[ $bm_desk_key ] = $bm_visible_filters[ $bm_desk_key ];
}
$bm_visible_topics = array();
if ( in_array( $bm_active_discipline, array( 'market', 'ai-systems' ), true ) ) {
    foreach ( $bm_visible_filters as $bm_filter_key => $bm_filter ) {
        if ( in_array( $bm_filter_key, array( 'all', 'market', 'systems' ), true ) ) continue;
        if ( (string) ( $bm_filter['discipline'] ?? '' ) !== $bm_active_discipline ) continue;
        if ( empty( $bm_filter['terms'] ) ) continue;
        $bm_visible_topics[ $bm_filter_key ] = $bm_filter;
    }
}

$bm_filter_url = static function ( $focus ) use ( $bm_research_url, $bm_research_q ) {
    $args = array();
    if ( 'all' !== $focus ) $args['focus'] = $focus;
    if ( $bm_research_q ) $args['research_q'] = $bm_research_q;
    return $args ? add_query_arg( $args, $bm_research_url ) : $bm_research_url;
};

$bm_pagination_args = array();
if ( 'all' !== $bm_focus ) $bm_pagination_args['focus'] = $bm_focus;
if ( $bm_research_q ) $bm_pagination_args['research_q'] = $bm_research_q;
?>
<section class="bm-research-hub" aria-labelledby="bm-research-hub-title">
  <div class="bm-container">
    <header class="bm-research-head">
      <p class="bm-research-kicker">BITMOMO RESEARCH</p>
      <div class="bm-research-head__grid">
        <div>
          <h1 id="bm-research-hub-title">Riset pasar dan AI yang dapat diuji.</h1>
          <p class="bm-research-head__lead">Bitmomo Research menguji bagaimana pasar bergerak dan bagaimana sistem intelligence dibangun—dengan bukti, konteks, batas tesis, dan evaluasi hasil yang dapat ditelusuri.</p>
        </div>
        <div class="bm-research-head__utility">
          <p><strong>Desk</strong><span>Market Research · AI &amp; Intelligence Systems</span></p>
          <p><strong>Proses</strong><span>Bukti → tesis → invalidasi → evaluasi</span></p>
          <a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Buka BTC Intelligence →</a>
        </div>
      </div>
      <div class="bm-research-standard-line" aria-label="Standar riset Bitmomo">
        <span>BUKTI TERLACAK</span><span>TESIS + INVALIDASI</span><span>EVALUASI HASIL</span>
      </div>
    </header>

    <?php if ( count( $bm_visible_desks ) > 1 || $bm_visible_topics || $bm_research_q ) : ?>
      <section class="bm-research-discovery" aria-label="Navigasi dan pencarian riset">
        <?php if ( count( $bm_visible_desks ) > 1 ) : ?>
          <nav class="bm-research-filter" aria-label="Desk riset">
            <?php foreach ( $bm_visible_desks as $bm_filter_key => $bm_filter ) : ?>
              <a href="<?php echo esc_url( $bm_filter_url( $bm_filter_key ) ); ?>"<?php echo $bm_filter_key === $bm_active_desk ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $bm_filter['label'] ); ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>

        <?php if ( $bm_visible_topics ) : ?>
          <nav class="bm-research-filter bm-research-filter--topics" aria-label="Topik <?php echo esc_attr( $bm_visible_desks[ $bm_active_desk ]['label'] ?? 'riset' ); ?>">
            <?php foreach ( $bm_visible_topics as $bm_filter_key => $bm_filter ) : ?>
              <a href="<?php echo esc_url( $bm_filter_url( $bm_filter_key ) ); ?>"<?php echo $bm_filter_key === $bm_focus ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $bm_filter['label'] ); ?></a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>

        <form class="bm-research-search" method="get" action="<?php echo esc_url( $bm_research_url ); ?>" role="search">
          <?php if ( 'all' !== $bm_focus ) : ?><input type="hidden" name="focus" value="<?php echo esc_attr( $bm_focus ); ?>"><?php endif; ?>
          <label for="bm-research-search-input">Cari riset</label>
          <div>
            <input id="bm-research-search-input" type="search" name="research_q" value="<?php echo esc_attr( $bm_research_q ); ?>" placeholder="Judul, tesis, topik…" maxlength="80">
            <button type="submit">Cari</button>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <?php if ( $bm_lead && 1 === $bm_page ) :
      $bm_lead_class = bitmomo_post_research_classification( $bm_lead_id );
      $bm_lead_domain = 'ai-systems' === $bm_lead_class ? 'AI & INTELLIGENCE SYSTEMS' : 'MARKET RESEARCH';
      $bm_lead_topic = bitmomo_post_research_topic_label( $bm_lead_id );
      $bm_lead_minutes = bitmomo_post_reading_minutes( $bm_lead_id );
      $bm_lead_excerpt = wp_trim_words( get_the_excerpt( $bm_lead ), 38, '…' );
      $bm_lead_summary_label = has_excerpt( $bm_lead_id ) ? 'TEMUAN UTAMA' : 'RINGKASAN RISET';
      $bm_has_figure = has_post_thumbnail( $bm_lead_id );
    ?>
      <section class="bm-research-lead" id="latest-research" aria-labelledby="bm-lead-research-title">
        <div class="bm-research-section-label"><span>LEAD RESEARCH</span><span><?php echo esc_html( $bm_visible_filters[ $bm_focus ]['label'] ); ?></span></div>
        <article class="bm-research-lead__paper<?php echo $bm_has_figure ? '' : ' bm-research-lead__paper--text'; ?>">
          <div class="bm-research-lead__body">
            <p class="bm-research-meta"><?php echo esc_html( $bm_lead_domain . ' · ' . strtoupper( $bm_lead_topic ) . ' · ' . get_the_date( 'd M Y', $bm_lead ) . ' · ' . $bm_lead_minutes . ' MENIT BACA' ); ?></p>
            <h2 id="bm-lead-research-title" class="bm-research-lead__title"><a href="<?php echo esc_url( get_permalink( $bm_lead ) ); ?>"><?php echo esc_html( get_the_title( $bm_lead ) ); ?></a></h2>
            <?php if ( $bm_lead_excerpt ) : ?>
              <div class="bm-research-lead__finding">
                <span><?php echo esc_html( $bm_lead_summary_label ); ?></span>
                <p><?php echo esc_html( $bm_lead_excerpt ); ?></p>
              </div>
            <?php endif; ?>
            <a class="bm-research-text-link" href="<?php echo esc_url( get_permalink( $bm_lead ) ); ?>">Baca riset lengkap →</a>
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
          <p class="bm-research-kicker">LATEST RESEARCH</p>
          <h2 id="bm-research-library-title"><?php echo esc_html( $bm_visible_filters[ $bm_focus ]['label'] ); ?></h2>
        </div>
        <p>
          <?php
          if ( $bm_research_q ) {
              echo esc_html( 'Pencarian: “' . $bm_research_q . '”' );
          } elseif ( $bm_page > 1 ) {
              echo esc_html( sprintf( 'Halaman %d dari %d.', $bm_page, $bm_total_pages ) );
          } else {
              echo 'Publikasi terbaru yang memenuhi standar riset Bitmomo.';
          }
          ?>
        </p>
      </header>

      <?php if ( $bm_library_posts ) : ?>
        <ol class="bm-research-library__list">
          <?php foreach ( $bm_library_posts as $bm_post ) :
            $bm_post_id = (int) $bm_post->ID;
            $bm_classification = bitmomo_post_research_classification( $bm_post_id );
            $bm_domain_label = 'ai-systems' === $bm_classification ? 'AI & INTELLIGENCE SYSTEMS' : 'MARKET RESEARCH';
            $bm_topic_label = bitmomo_post_research_topic_label( $bm_post_id );
            $bm_minutes = bitmomo_post_reading_minutes( $bm_post_id );
          ?>
            <li>
              <div class="bm-research-library__meta">
                <span><?php echo esc_html( $bm_domain_label ); ?></span>
                <strong><?php echo esc_html( $bm_topic_label ); ?></strong>
                <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y', $bm_post ) ); ?></time>
                <small><?php echo esc_html( $bm_minutes . ' menit baca' ); ?></small>
              </div>
              <div class="bm-research-library__copy">
                <h3><a href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_post ) ); ?></a></h3>
                <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $bm_post ), 24, '…' ) ); ?></p>
              </div>
              <a class="bm-research-library__open" href="<?php echo esc_url( get_permalink( $bm_post ) ); ?>" aria-label="Baca <?php echo esc_attr( get_the_title( $bm_post ) ); ?>">↗</a>
            </li>
          <?php endforeach; ?>
        </ol>

        <?php if ( $bm_total_pages > 1 ) : ?>
          <nav class="bm-research-pagination" aria-label="Halaman Research">
            <?php
            echo wp_kses_post(
                paginate_links(
                    array(
                        'base'      => str_replace( 999999999, '%#%', esc_url_raw( get_pagenum_link( 999999999 ) ) ),
                        'format'    => '?paged=%#%',
                        'current'   => $bm_page,
                        'total'     => $bm_total_pages,
                        'mid_size'  => 1,
                        'end_size'  => 1,
                        'prev_text' => '← Sebelumnya',
                        'next_text' => 'Berikutnya →',
                        'add_args'  => $bm_pagination_args,
                        'type'      => 'plain',
                    )
                )
            );
            ?>
          </nav>
        <?php endif; ?>
      <?php elseif ( ! $bm_lead || $bm_page > 1 ) : ?>
        <div class="bm-research-empty">
          <strong>Belum ada publikasi yang cocok dengan tampilan ini.</strong>
          <p>Ubah desk, topik, atau kata pencarian. Research Hub hanya menampilkan publikasi yang sudah diklasifikasikan sebagai riset Bitmomo.</p>
          <a href="<?php echo esc_url( $bm_research_url ); ?>">Reset tampilan riset →</a>
        </div>
      <?php else : ?>
        <p class="bm-research-library__single">Publikasi terpilih ditampilkan sebagai Lead Research di atas.</p>
      <?php endif; ?>
    </section>

    <section class="bm-research-methodology" id="research-standard" aria-labelledby="bm-research-methodology-title">
      <div class="bm-research-methodology__intro">
        <p class="bm-research-kicker">RESEARCH STANDARD</p>
        <h2 id="bm-research-methodology-title">Setiap tesis harus dapat diuji.</h2>
        <p>Riset Bitmomo menghubungkan bukti, tesis, kondisi invalidasi, dan evaluasi hasil. Tujuannya bukan memperbanyak narasi, tetapi membuat kesimpulan dapat diperiksa ketika data, model, atau kondisi pasar berubah.</p>
      </div>
      <div class="bm-research-methodology__rules">
        <div><span>01</span><strong>Bukti dapat ditelusuri</strong><p>Sumber dan waktu observasi harus jelas sejauh data memungkinkan.</p></div>
        <div><span>02</span><strong>Konteks sebelum kesimpulan</strong><p>Data dibaca dalam konteks sistem atau struktur pasar, bukan sebagai sinyal yang berdiri sendiri.</p></div>
        <div><span>03</span><strong>Batas tesis eksplisit</strong><p>Analisis menjelaskan kondisi yang membuat tesis perlu dievaluasi ulang.</p></div>
        <div><span>04</span><strong>Evaluasi hasil</strong><p>Analisis yang dapat diuji dibandingkan dengan hasil aktual tanpa memilih hanya hasil yang sesuai dengan tesis.</p></div>
      </div>
    </section>
  </div>
</section>
<?php
if ( $bm_library_query instanceof WP_Query ) wp_reset_postdata();
unset(
    $bm_riset_term, $bm_riset_id, $bm_research_url, $bm_focus_filters, $bm_taxonomy_v3,
    $bm_requested_focus, $bm_research_q, $bm_page, $bm_per_page, $bm_visible_filters,
    $bm_focus, $bm_active_filter, $bm_active_discipline, $bm_active_desk, $bm_visible_desks,
    $bm_visible_topics, $bm_desk_key, $bm_lead, $bm_lead_id, $bm_library_posts,
    $bm_total_pages, $bm_library_query, $bm_lead_query, $bm_candidate, $bm_filter_url,
    $bm_pagination_args, $bm_filter_key, $bm_filter, $bm_ai_tag, $bm_ai_tag_id,
    $bm_market_slugs, $bm_market_args, $bm_ai_args, $bm_market_posts, $bm_ai_posts,
    $bm_qualified_map, $bm_qualified_posts, $bm_filtered_posts, $bm_library_all,
    $bm_post, $bm_post_id, $bm_classification, $bm_domain_label, $bm_topic_label,
    $bm_minutes, $bm_lead_class, $bm_lead_domain, $bm_lead_topic, $bm_lead_minutes,
    $bm_lead_excerpt, $bm_lead_summary_label, $bm_has_figure
);
?>
<?php
/** Single post — canonical Bitmomo reading surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary" class="bm-public-main">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
  <?php
  $bm_post_id = (int) get_the_ID();
  $bm_cats = get_the_category();
  $bm_classification = function_exists( 'bitmomo_post_research_classification' )
    ? bitmomo_post_research_classification( $bm_post_id )
    : 'unclassified';
  $bm_is_research = in_array( $bm_classification, array( 'market', 'ai-systems' ), true );
  $bm_article_label = function_exists( 'bitmomo_post_publication_label' )
    ? bitmomo_post_publication_label( $bm_post_id )
    : __( 'PUBLIKASI', 'bitmomo' );
  $bm_topic_label = $bm_is_research && function_exists( 'bitmomo_post_research_topic_label' )
    ? bitmomo_post_research_topic_label( $bm_post_id )
    : '';
  $bm_read_minutes = function_exists( 'bitmomo_post_reading_minutes' )
    ? bitmomo_post_reading_minutes( $bm_post_id )
    : 1;
  $bm_deck = function_exists( 'bitmomo_post_manual_deck' )
    ? bitmomo_post_manual_deck( $bm_post_id )
    : '';
  $bm_has_meaningful_update = function_exists( 'bitmomo_post_has_meaningful_update' )
    ? bitmomo_post_has_meaningful_update( $bm_post_id )
    : false;
  $bm_research_url = home_url( '/category/riset/' );
  $bm_about_url = home_url( '/tentang-kami/' );
  $bm_figure_caption = has_post_thumbnail() ? trim( (string) get_the_post_thumbnail_caption( $bm_post_id ) ) : '';

  /*
   * Research copy is stored in WordPress, not in this theme. Keep a narrow
   * presentation safety boundary for artifacts that must never reach a public
   * research article: ChatGPT attribution query strings and literal editor
   * scaffolding. The canonical DB cleanup remains a separate idempotent
   * migration; this renderer prevents stale DB/cache copies from leaking them.
   */
  $bm_article_content = apply_filters( 'the_content', get_the_content( null, false, $bm_post_id ) );
  if ( $bm_is_research && is_string( $bm_article_content ) ) {
    $bm_article_content = preg_replace_callback(
      '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
      static function ( $matches ) {
        $quote = $matches[1];
        $url = html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $query = wp_parse_url( $url, PHP_URL_QUERY );
        $params = array();
        if ( is_string( $query ) ) parse_str( $query, $params );
        if ( isset( $params['utm_source'] ) && 'chatgpt.com' === strtolower( trim( (string) $params['utm_source'] ) ) ) {
          $url = remove_query_arg( 'utm_source', $url );
        }
        return 'href=' . $quote . esc_url( $url ) . $quote;
      },
      $bm_article_content
    );
    $bm_article_content = preg_replace( '/<p\b[^>]*>\s*Excerpt:\s*<\/p>/i', '', $bm_article_content );
    $bm_article_content = preg_replace( '/(<p\b[^>]*>\s*)Excerpt:\s*/i', '$1', $bm_article_content );
  }

  /* Suppress an auto-truncated excerpt when it merely repeats paragraph one. */
  if ( $bm_is_research && '' !== $bm_deck ) {
    $bm_original_deck = $bm_deck;
    $bm_deck = preg_replace( '/\s*(?:\[\x{2026}\]|\[\.\.\.\])\s*$/u', '', $bm_deck );
    $bm_deck = is_string( $bm_deck ) ? trim( $bm_deck ) : '';
    $bm_deck_plain = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $bm_deck ) );
    $bm_body_plain = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $bm_article_content ) );
    $bm_auto_truncated = (bool) preg_match( '/(?:\[\x{2026}\]|\[\.\.\.\])\s*$/u', $bm_original_deck );
    $bm_duplicate_opening = '' !== $bm_deck_plain && '' !== $bm_body_plain && 0 === strpos( $bm_body_plain, $bm_deck_plain );
    if ( $bm_duplicate_opening && ( $bm_auto_truncated || strlen( $bm_deck_plain ) >= 80 ) ) {
      $bm_deck = '';
    }
  }
  ?>
  <article <?php post_class( 'bm-article' ); ?>>
    <div class="bm-container">
      <header class="bm-article-head">
        <?php if ( $bm_is_research ) : ?>
          <nav class="bm-article-breadcrumb" aria-label="<?php esc_attr_e( 'Konteks riset', 'bitmomo' ); ?>">
            <a href="<?php echo esc_url( $bm_research_url ); ?>">Bitmomo Research</a>
            <?php if ( $bm_topic_label ) : ?><span aria-hidden="true">/</span><span><?php echo esc_html( $bm_topic_label ); ?></span><?php endif; ?>
          </nav>
        <?php endif; ?>

        <p class="bm-public-eyebrow"><?php echo esc_html( $bm_article_label ); ?></p>
        <h1 class="bm-article-title"><?php the_title(); ?></h1>

        <?php if ( $bm_deck ) : ?>
          <p class="bm-article-deck"><?php echo esc_html( $bm_deck ); ?></p>
        <?php endif; ?>

        <div class="bm-article-meta" aria-label="<?php esc_attr_e( 'Informasi publikasi', 'bitmomo' ); ?>">
          <a class="bm-article-byline" href="<?php echo esc_url( $bm_about_url ); ?>"><?php echo esc_html( $bm_is_research ? 'Bitmomo Research' : 'Bitmomo' ); ?></a>
          <span class="bm-article-meta__separator" aria-hidden="true">·</span>
          <span><?php esc_html_e( 'Dipublikasikan', 'bitmomo' ); ?> <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y' ) ); ?></time></span>
          <?php if ( $bm_has_meaningful_update ) : ?>
            <span class="bm-article-meta__separator" aria-hidden="true">·</span>
            <span><?php esc_html_e( 'Diperbarui', 'bitmomo' ); ?> <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>"><?php echo esc_html( get_the_modified_date( 'd M Y' ) ); ?></time></span>
          <?php endif; ?>
          <span class="bm-article-meta__separator" aria-hidden="true">·</span>
          <span><?php echo esc_html( $bm_read_minutes . ' menit baca' ); ?></span>
        </div>
      </header>

      <?php if ( has_post_thumbnail() ) : ?>
        <figure class="bm-article-figure">
          <?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?>
          <?php if ( $bm_figure_caption ) : ?><figcaption><?php echo esc_html( $bm_figure_caption ); ?></figcaption><?php endif; ?>
        </figure>
      <?php endif; ?>

      <div class="bm-article-body"><?php echo $bm_article_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output already passed through the_content; URL cleanup is narrow. ?></div>

      <footer class="bm-article-foot">
        <?php if ( $bm_is_research ) : ?>
          <aside class="bm-article-standard" aria-label="<?php esc_attr_e( 'Standar riset Bitmomo', 'bitmomo' ); ?>">
            <span>RESEARCH STANDARD</span>
            <p><strong>Evidence before narrative.</strong> Bukti, konteks, batas tesis, dan metode evaluasi harus tetap dapat ditelusuri ketika kesimpulan diuji ulang.</p>
            <a href="<?php echo esc_url( $bm_research_url . '#research-standard' ); ?>">Lihat standar riset →</a>
          </aside>
          <a class="bm-article-back" href="<?php echo esc_url( $bm_research_url ); ?>">← Kembali ke Bitmomo Research</a>
        <?php else : ?>
          <a class="bm-article-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">← Kembali ke Bitmomo</a>
        <?php endif; ?>
      </footer>
    </div>
  </article>

  <?php
  $bm_related_args = array(
    'post_type'              => 'post',
    'post_status'            => 'publish',
    'post__not_in'           => array( $bm_post_id ),
    'posts_per_page'         => 8,
    'ignore_sticky_posts'    => true,
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => true,
  );
  $bm_related_title = __( 'Publikasi Terkait', 'bitmomo' );
  $bm_related_eyebrow = __( 'LANJUTKAN MEMBACA', 'bitmomo' );
  $bm_taxonomy_v3 = function_exists( 'bitmomo_research_taxonomy_is_active' ) && bitmomo_research_taxonomy_is_active();

  if ( $bm_is_research && $bm_taxonomy_v3 ) {
    $bm_desk_slug = bitmomo_post_research_desk_slug( $bm_post_id );
    if ( $bm_desk_slug ) {
      $bm_related_args['tax_query'] = array(
        array(
          'taxonomy' => bitmomo_research_desk_taxonomy(),
          'field'    => 'slug',
          'terms'    => array( $bm_desk_slug ),
        ),
      );
      $bm_related_title = 'market' === $bm_classification
        ? __( 'Riset Pasar Terkait', 'bitmomo' )
        : __( 'Riset Sistem Intelligence Terkait', 'bitmomo' );
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  } elseif ( 'market' === $bm_classification ) {
    $bm_riset = get_category_by_slug( 'riset' );
    $bm_market_slugs = function_exists( 'bitmomo_market_research_taxonomy_slugs' ) ? bitmomo_market_research_taxonomy_slugs() : array();
    if ( $bm_riset && $bm_market_slugs ) {
      $bm_related_args['cat'] = (int) $bm_riset->term_id;
      $bm_related_args['tax_query'] = array(
        'relation' => 'OR',
        array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $bm_market_slugs ),
        array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $bm_market_slugs ),
      );
      $bm_ai_tag = get_term_by( 'slug', 'ai-lab', 'post_tag' );
      if ( $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) $bm_related_args['tag__not_in'] = array( (int) $bm_ai_tag->term_id );
      $bm_related_title = __( 'Riset Pasar Terkait', 'bitmomo' );
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  } elseif ( 'ai-systems' === $bm_classification ) {
    $bm_riset = get_category_by_slug( 'riset' );
    $bm_ai_tag = get_term_by( 'slug', 'ai-lab', 'post_tag' );
    if ( $bm_riset && $bm_ai_tag && ! is_wp_error( $bm_ai_tag ) ) {
      $bm_related_args['cat'] = (int) $bm_riset->term_id;
      $bm_related_args['tag__in'] = array( (int) $bm_ai_tag->term_id );
      $bm_related_title = __( 'Riset Sistem Intelligence Terkait', 'bitmomo' );
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  } else {
    $bm_primary_cat_id = 0;
    foreach ( $bm_cats as $bm_cat ) {
      if ( 'riset' === $bm_cat->slug ) continue;
      $bm_primary_cat_id = (int) $bm_cat->term_id;
      break;
    }
    if ( $bm_primary_cat_id ) {
      $bm_related_args['cat'] = $bm_primary_cat_id;
    } else {
      $bm_related_args['post__in'] = array( 0 );
    }
  }

  $bm_related = new WP_Query( $bm_related_args );
  $bm_related_posts = array();
  foreach ( $bm_related->posts as $bm_related_candidate ) {
    $bm_candidate_id = (int) $bm_related_candidate->ID;
    if ( 'market' === $bm_classification && function_exists( 'bitmomo_post_is_market_research' ) && ! bitmomo_post_is_market_research( $bm_candidate_id ) ) continue;
    if ( 'ai-systems' === $bm_classification && function_exists( 'bitmomo_post_is_ai_systems_research' ) && ! bitmomo_post_is_ai_systems_research( $bm_candidate_id ) ) continue;
    $bm_related_posts[] = $bm_related_candidate;
    if ( 4 === count( $bm_related_posts ) ) break;
  }

  if ( $bm_related_posts ) : ?>
    <section class="bm-section bm-related bm-related--editorial">
      <div class="bm-container">
        <header class="bm-related-head">
          <p class="bm-public-eyebrow"><?php echo esc_html( $bm_related_eyebrow ); ?></p>
          <h2 class="bm-section-title"><?php echo esc_html( $bm_related_title ); ?></h2>
        </header>
        <ol class="bm-related-list">
          <?php foreach ( $bm_related_posts as $bm_related_post ) :
            $bm_related_post_id = (int) $bm_related_post->ID;
            $bm_related_label = function_exists( 'bitmomo_post_publication_label' ) ? bitmomo_post_publication_label( $bm_related_post_id ) : 'PUBLIKASI';
            $bm_related_minutes = function_exists( 'bitmomo_post_reading_minutes' ) ? bitmomo_post_reading_minutes( $bm_related_post_id ) : 1;
          ?>
            <li>
              <div class="bm-related-list__meta">
                <span><?php echo esc_html( $bm_related_label ); ?></span>
                <time datetime="<?php echo esc_attr( get_the_date( 'c', $bm_related_post ) ); ?>"><?php echo esc_html( get_the_date( 'd M Y', $bm_related_post ) ); ?></time>
                <small><?php echo esc_html( $bm_related_minutes . ' menit' ); ?></small>
              </div>
              <h3><a href="<?php echo esc_url( get_permalink( $bm_related_post ) ); ?>"><?php echo esc_html( get_the_title( $bm_related_post ) ); ?></a></h3>
              <a class="bm-related-list__open" href="<?php echo esc_url( get_permalink( $bm_related_post ) ); ?>" aria-label="Baca <?php echo esc_attr( get_the_title( $bm_related_post ) ); ?>">→</a>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>
  <?php endif;
  unset(
    $bm_post_id, $bm_cats, $bm_cat, $bm_classification, $bm_is_research, $bm_article_label,
    $bm_topic_label, $bm_read_minutes, $bm_deck, $bm_original_deck, $bm_deck_plain, $bm_body_plain,
    $bm_auto_truncated, $bm_duplicate_opening, $bm_article_content, $bm_has_meaningful_update, $bm_research_url,
    $bm_about_url, $bm_figure_caption, $bm_related_args, $bm_related_title, $bm_related_eyebrow,
    $bm_taxonomy_v3, $bm_desk_slug, $bm_related, $bm_related_posts, $bm_related_candidate,
    $bm_candidate_id, $bm_related_post, $bm_riset, $bm_market_slugs, $bm_ai_tag, $bm_primary_cat_id,
    $bm_related_post_id, $bm_related_label, $bm_related_minutes
  );
  ?>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>
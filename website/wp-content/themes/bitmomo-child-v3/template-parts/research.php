<?php
/** Compact qualified market-research desk for the homepage. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_riset_term = get_category_by_slug( 'riset' );
$bm_riset_url  = $bm_riset_term ? get_category_link( $bm_riset_term->term_id ) : home_url( '/category/riset/' );
$bm_home_priority_slugs = array( 'bitcoin', 'makro', 'market-structure' );
$bm_market_slugs = function_exists( 'bitmomo_market_research_taxonomy_slugs' )
    ? array_values( array_unique( array_merge( $bm_home_priority_slugs, bitmomo_market_research_taxonomy_slugs() ) ) )
    : array( 'bitcoin', 'makro', 'market-structure', 'btc', 'macro', 'derivatives', 'funding-rate', 'etf', 'liquidity', 'likuiditas', 'fundamental', 'fundamentals' );

$bm_research_items = array();

// Homepage research must obey the same institutional qualification boundary as
// the Research Hub. Broad `Riset` membership alone is never enough.
if ( $bm_riset_term && $bm_market_slugs ) {
    $bm_query_args = array(
        'posts_per_page'      => 3,
        'post_status'         => 'publish',
        'cat'                 => (int) $bm_riset_term->term_id,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'tax_query'           => array(
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
        ),
    );

    $bm_ai_lab_tag = get_term_by( 'slug', 'ai-lab', 'post_tag' );
    if ( $bm_ai_lab_tag && ! is_wp_error( $bm_ai_lab_tag ) ) {
        $bm_query_args['tag__not_in'] = array( (int) $bm_ai_lab_tag->term_id );
    }

    $bm_research_query = new WP_Query( $bm_query_args );
    if ( $bm_research_query->have_posts() ) {
        while ( $bm_research_query->have_posts() ) {
            $bm_research_query->the_post();

            if ( function_exists( 'bitmomo_post_is_market_research' ) && ! bitmomo_post_is_market_research( get_the_ID() ) ) {
                continue;
            }

            $bm_reading_minutes = function_exists( 'bitmomo_post_reading_minutes' )
                ? bitmomo_post_reading_minutes( get_the_ID() )
                : 1;

            $bm_research_items[] = array(
                'label'        => function_exists( 'bitmomo_post_research_topic_label' ) ? bitmomo_post_research_topic_label( get_the_ID() ) : __( 'Market Research', 'bitmomo' ),
                'title'        => get_the_title(),
                'link'         => get_permalink(),
                'excerpt'      => wp_trim_words( get_the_excerpt(), 18, '…' ),
                'date_iso'     => get_the_date( DATE_W3C ),
                'date_label'   => get_the_date( 'd M Y' ),
                'reading_time' => max( 1, (int) $bm_reading_minutes ),
            );
        }
        wp_reset_postdata();
    }
}

if ( ! $bm_research_items ) {
    unset( $bm_riset_term, $bm_riset_url, $bm_home_priority_slugs, $bm_market_slugs, $bm_research_items, $bm_query_args, $bm_ai_lab_tag, $bm_research_query, $bm_reading_minutes );
    return;
}
?>
<section class="bm-home-research" aria-labelledby="bm-home-research-title">
  <div class="bm-container">
    <header class="bm-home-research__head">
      <div>
        <span class="bm-home-research__eyebrow"><?php esc_html_e( 'RESEARCH DESK', 'bitmomo' ); ?></span>
        <h2 id="bm-home-research-title"><?php esc_html_e( 'Riset pasar terbaru', 'bitmomo' ); ?></h2>
      </div>
      <a class="bm-home-research__all" href="<?php echo esc_url( $bm_riset_url ); ?>"><?php esc_html_e( 'Buka Research Hub →', 'bitmomo' ); ?></a>
    </header>

    <ol class="bm-home-research__list">
      <?php foreach ( $bm_research_items as $bm_index => $bm_item ) : ?>
      <li class="bm-home-research__item">
        <span class="bm-home-research__index"><?php echo esc_html( str_pad( (string) ( $bm_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
        <div class="bm-home-research__meta">
          <strong><?php echo esc_html( $bm_item['label'] ); ?></strong>
          <time datetime="<?php echo esc_attr( $bm_item['date_iso'] ); ?>"><?php echo esc_html( $bm_item['date_label'] ); ?></time>
          <span><?php echo esc_html( sprintf( __( '%d min read', 'bitmomo' ), $bm_item['reading_time'] ) ); ?></span>
        </div>
        <div class="bm-home-research__body">
          <h3><a href="<?php echo esc_url( $bm_item['link'] ); ?>"><?php echo esc_html( $bm_item['title'] ); ?></a></h3>
          <p><?php echo esc_html( $bm_item['excerpt'] ); ?></p>
        </div>
        <span class="bm-home-research__arrow" aria-hidden="true">→</span>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php unset( $bm_riset_term, $bm_riset_url, $bm_home_priority_slugs, $bm_market_slugs, $bm_research_items, $bm_query_args, $bm_ai_lab_tag, $bm_research_query, $bm_reading_minutes, $bm_index, $bm_item ); ?>

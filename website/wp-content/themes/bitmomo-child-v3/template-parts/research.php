<?php
/** Compact qualified market-research surface for the homepage. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_riset_term = get_category_by_slug( 'riset' );
$bm_riset_url  = $bm_riset_term ? get_category_link( $bm_riset_term->term_id ) : home_url( '/category/riset/' );
$bm_home_priority_slugs = array( 'bitcoin', 'makro', 'market-structure' );
$bm_market_slugs = function_exists( 'bitmomo_market_research_taxonomy_slugs' )
	? array_values( array_unique( array_merge( $bm_home_priority_slugs, bitmomo_market_research_taxonomy_slugs() ) ) )
	: array( 'bitcoin', 'makro', 'market-structure', 'btc', 'macro', 'derivatives', 'funding-rate', 'etf', 'liquidity', 'likuiditas', 'fundamental', 'fundamentals' );

$bm_research_items = array();

// Homepage research must obey the same institutional qualification boundary as
// the Research Hub. Broad `Riset` membership alone is never enough: legacy AI
// or general editorial posts must not leak into the BTC-first launch surface.
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

			// Defense in depth: query-level taxonomy is authoritative, while this
			// helper prevents future query edits from silently widening the surface.
			if ( function_exists( 'bitmomo_post_is_market_research' ) && ! bitmomo_post_is_market_research( get_the_ID() ) ) {
				continue;
			}

			$bm_research_items[] = array(
				'label'   => __( 'Market Research', 'bitmomo' ),
				'title'   => get_the_title(),
				'link'    => get_permalink(),
				'excerpt' => wp_trim_words( get_the_excerpt(), 14, '…' ),
			);
		}
		wp_reset_postdata();
	}
}

if ( ! $bm_research_items ) {
	unset( $bm_riset_term, $bm_riset_url, $bm_home_priority_slugs, $bm_market_slugs, $bm_research_items, $bm_query_args, $bm_ai_lab_tag, $bm_research_query );
	return;
}
?>
<section class="bm-section bm-research" aria-labelledby="bm-research-title">
  <div class="bm-container">
    <header class="bm-research-head">
      <div>
        <span class="bm-research-eyebrow"><?php esc_html_e( 'RESEARCH', 'bitmomo' ); ?></span>
        <h2 id="bm-research-title">Riset terbaru</h2>
      </div>
      <a class="bm-research-more" href="<?php echo esc_url( $bm_riset_url ); ?>">Lihat semua &rarr;</a>
    </header>
    <ul class="bm-research-list">
      <?php foreach ( $bm_research_items as $bm_item ) : ?>
      <li class="bm-research-item">
        <span class="bm-research-category"><?php echo esc_html( $bm_item['label'] ); ?></span>
        <h3><a href="<?php echo esc_url( $bm_item['link'] ); ?>"><?php echo esc_html( $bm_item['title'] ); ?></a></h3>
        <p><?php echo esc_html( $bm_item['excerpt'] ); ?></p>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php unset( $bm_riset_term, $bm_riset_url, $bm_home_priority_slugs, $bm_market_slugs, $bm_research_items, $bm_query_args, $bm_ai_lab_tag, $bm_research_query, $bm_item ); ?>

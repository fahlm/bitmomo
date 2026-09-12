<?php
/**
 * "Bitmomo Research" homepage section (hierarchy position 8, immediately
 * after AI Lab). BTC/market research stays distinct from AI Lab so the
 * homepage reinforces one clear topical authority: BTC market intelligence.
 *
 * Preferred sources are Bitcoin, Makro, and Market Structure categories.
 * If those categories do not yet exist in the live taxonomy, fall back to
 * the latest "Riset" posts while explicitly excluding the `ai-lab` tag when
 * that tag exists. This keeps AI-system research in AI Lab instead of letting
 * it leak back into the BTC Research surface.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bm_research_target_slugs = array( 'bitcoin', 'makro', 'market-structure' );
$bm_research_cats         = array();

foreach ( $bm_research_target_slugs as $bm_slug ) {
	$bm_cat = get_category_by_slug( $bm_slug );
	if ( $bm_cat ) {
		$bm_research_cats[] = $bm_cat;
	}
}

$bm_research_items = array();

if ( $bm_research_cats ) {
	foreach ( $bm_research_cats as $bm_cat ) {
		$bm_research_query = new WP_Query(
			array(
				'posts_per_page'      => 1,
				'post_status'         => 'publish',
				'cat'                 => $bm_cat->term_id,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);
		if ( $bm_research_query->have_posts() ) {
			while ( $bm_research_query->have_posts() ) {
				$bm_research_query->the_post();
				$bm_research_items[] = array(
					'label'   => $bm_cat->name,
					'title'   => get_the_title(),
					'link'    => get_permalink(),
					'excerpt' => wp_trim_words( get_the_excerpt(), 20, '…' ),
				);
			}
			wp_reset_postdata();
		}
	}
}

$bm_riset_term = get_category_by_slug( 'riset' );
$bm_riset_url  = $bm_riset_term ? get_category_link( $bm_riset_term->term_id ) : home_url( '/category/riset/' );

if ( ! $bm_research_items && $bm_riset_term ) {
	$bm_fallback_args = array(
		'posts_per_page'      => 3,
		'post_status'         => 'publish',
		'cat'                 => $bm_riset_term->term_id,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	);

	$bm_ai_lab_tag = get_term_by( 'slug', 'ai-lab', 'post_tag' );
	if ( $bm_ai_lab_tag && ! is_wp_error( $bm_ai_lab_tag ) ) {
		$bm_fallback_args['tag__not_in'] = array( (int) $bm_ai_lab_tag->term_id );
	}

	$bm_research_query = new WP_Query( $bm_fallback_args );
	if ( $bm_research_query->have_posts() ) {
		while ( $bm_research_query->have_posts() ) {
			$bm_research_query->the_post();
			$bm_post_cats = get_the_category();
			$bm_research_items[] = array(
				'label'   => $bm_post_cats ? $bm_post_cats[0]->name : __( 'Riset', 'bitmomo' ),
				'title'   => get_the_title(),
				'link'    => get_permalink(),
				'excerpt' => wp_trim_words( get_the_excerpt(), 20, '…' ),
			);
		}
		wp_reset_postdata();
	}
}

if ( ! $bm_research_items ) {
	unset( $bm_research_target_slugs, $bm_research_cats, $bm_research_items, $bm_riset_term, $bm_riset_url, $bm_cat, $bm_research_query, $bm_post_cats, $bm_ai_lab_tag, $bm_fallback_args );
	return;
}
?>
<section class="bm-section bm-research" aria-labelledby="bm-research-title">
  <div class="bm-container">
    <header class="bm-research-head">
      <div>
        <span class="bm-research-eyebrow"><?php esc_html_e( 'BTC / MARKET RESEARCH', 'bitmomo' ); ?></span>
        <h2 id="bm-research-title">Bitmomo Research</h2>
      </div>
      <a class="bm-research-more" href="<?php echo esc_url( $bm_riset_url ); ?>">Lihat semua riset &rarr;</a>
    </header>
    <ul class="bm-research-list">
      <?php foreach ( $bm_research_items as $bm_item ) : ?>
      <li class="bm-research-item">
        <span class="bm-research-category"><?php echo esc_html( $bm_item['label'] ); ?></span>
        <h3><a href="<?php echo esc_url( $bm_item['link'] ); ?>"><?php echo esc_html( $bm_item['title'] ); ?></a></h3>
        <p><?php echo esc_html( $bm_item['excerpt'] ); ?></p>
        <a class="bm-research-link" href="<?php echo esc_url( $bm_item['link'] ); ?>">Baca selengkapnya &rarr;</a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php unset( $bm_research_target_slugs, $bm_research_cats, $bm_research_items, $bm_riset_term, $bm_riset_url, $bm_cat, $bm_research_query, $bm_post_cats, $bm_item, $bm_ai_lab_tag, $bm_fallback_args ); ?>

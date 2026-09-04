<?php
/** Single Post — Bitmomo */
get_header();
?>
<main>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <article class="bm-article"><div class="bm-container">
    <header class="bm-article-head">
      <h1 class="bm-article-title"><?php the_title(); ?></h1>
      <div class="bm-article-meta"><span><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></span>
      <?php $cats = get_the_category(); if ($cats) : ?><span class="sep">•</span><span><?php echo esc_html($cats[0]->name); ?></span><?php endif; ?>
      </div>
    </header>
    <?php if (has_post_thumbnail()) : ?><figure class="bm-article-figure"><?php the_post_thumbnail('large', ['loading' => 'eager', 'fetchpriority' => 'high']); ?></figure><?php endif; ?>
    <div class="bm-article-body"><?php the_content(); ?></div>
    <footer class="bm-article-foot"><nav class="bm-post-nav"><div class="prev"><?php previous_post_link('%link','« %title'); ?></div><div class="next"><?php next_post_link('%link','%title »'); ?></div></nav></footer>
  </div></article>
  <?php
  $primary_cat_id = $cats ? (int) $cats[0]->term_id : 0;
  if ($primary_cat_id) :
    $related = new WP_Query([
      'post_type'              => 'post',
      'post_status'            => 'publish',
      'post__not_in'           => [get_the_ID()],
      'posts_per_page'         => 3,
      'ignore_sticky_posts'    => true,
      'cat'                    => $primary_cat_id,
      'orderby'                => 'date',
      'order'                  => 'DESC',
      'no_found_rows'          => true,
      'update_post_term_cache' => false,
    ]);
    if ($related->have_posts()) : ?>
      <section class="bm-section bm-related"><div class="bm-container"><h2 class="bm-section-title">Bacaan Terkait</h2><div class="bm-cards">
      <?php while ($related->have_posts()) : $related->the_post();
        get_template_part('template-parts/content', 'card', ['heading_level'=>'h3','image_size'=>'large','excerpt_words'=>22]);
      endwhile; ?>
      </div></div></section>
    <?php endif; wp_reset_postdata();
  endif; ?>
  <section class="bm-section bm-riset-bridge"><div class="bm-container">
    <div class="bm-riset-bridge-card">
      <div class="bm-riset-bridge-copy">
        <span class="bm-riset-bridge-eyebrow">BTC Intelligence</span>
        <p>Lihat bagaimana Bitmomo membaca kondisi BTC saat ini.</p>
      </div>
      <a class="bm-riset-bridge-cta" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Lihat BTC Intelligence &rarr;</a>
    </div>
  </div></section>
<?php endwhile; endif; ?>
</main>
<?php get_footer(); ?>

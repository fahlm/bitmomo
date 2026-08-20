<?php
/* Template: Tag Archive (Bitmomo) — Grid default, LIST khusus big-stories */
get_header();

$term = get_queried_object();
$slug = $term && ! is_wp_error($term) ? $term->slug : '';

/** Judul khusus untuk big-stories, lainnya pakai nama tag */
$archive_title = ($slug === 'big-stories') ? 'Big Stories' : single_tag_title('', false);
$archive_desc  = tag_description();
?>

<main id="primary" class="site-main bm-archive <?php echo ($slug==='big-stories') ? 'bm-archive--list' : 'bm-archive--grid'; ?>">

  <!-- Header Arsip -->
  <section class="bm-archive-head">
    <div class="bm-container">
      <h1 class="bm-archive-title"><?php echo esc_html($archive_title); ?></h1>
      <?php if ( ! empty( $archive_desc ) ) : ?>
        <p class="bm-archive-desc"><?php echo wp_kses_post( $archive_desc ); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ( $slug === 'big-stories' ) : ?>
  <!-- =============================== -->
  <!-- LIST VIEW khusus tag big-stories -->
  <!-- =============================== -->
  <section class="bm-section bm-list">
    <div class="bm-container">
      <?php if ( have_posts() ) : ?>
        <div class="bm-list-wrap">
          <?php while ( have_posts() ) : the_post(); ?>
            <article <?php post_class('bm-list-item'); ?>>

              <h2 class="bm-list-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h2>

              <div class="bm-list-meta">
                <time datetime="<?php echo esc_attr( get_the_date('c') ); ?>">
                  <?php echo esc_html( get_the_date() ); ?>
                </time>
                <span class="sep">·</span>
                <span class="bm-list-cat">
                  <?php
                    $cats = get_the_category();
                    if ($cats) echo esc_html($cats[0]->name);
                  ?>
                </span>
              </div>

              <p class="bm-list-excerpt">
                <?php
                  $raw = has_excerpt() ? get_the_excerpt() : wp_strip_all_tags( get_the_content() );
                  echo esc_html( wp_trim_words( $raw, 42, '…' ) );
                ?>
              </p>

            </article>
          <?php endwhile; ?>
        </div>

        <nav class="bm-pagination" aria-label="Pagination">
          <?php echo paginate_links(['prev_text'=>'« Prev','next_text'=>'Next »']); ?>
        </nav>

      <?php else : ?>
        <p style="text-align:center;opacity:.8">Belum ada artikel untuk tag ini.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php else : ?>

  <!-- =============================== -->
  <!-- GRID VIEW untuk tag selain big-stories -->
  <!-- =============================== -->
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
                'heading_level' => 'h2',
                'image_size'    => 'large',
                'excerpt_words' => 26,
              ]
            );
            ?>
          <?php endwhile; ?>
        </div>

        <nav class="bm-pagination" aria-label="Pagination">
          <?php echo paginate_links(['prev_text'=>'« Prev','next_text'=>'Next »']); ?>
        </nav>

      <?php else : ?>
        <p style="text-align:center;opacity:.8">Belum ada artikel untuk tag ini.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php endif; ?>

</main>

<?php get_footer(); ?>

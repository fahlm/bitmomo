<?php
/** Archive (Kategori) — Bitmomo FINAL FIX (with critical inline CSS) */
get_header();
?>

<style id="bm-archive-critical">
  .bm-page-head{ text-align:center!important; padding:clamp(60px,8vw,120px) 20px clamp(40px,6vw,80px)!important; border-bottom:1px solid var(--stroke)!important;
    background:
      radial-gradient(800px 400px at 70% -10%, rgba(38,208,198,.12), transparent 65%),
      radial-gradient(600px 300px at 20% -12%, rgba(36,139,199,.12), transparent 60%),
      linear-gradient(180deg, #0f2233 0%, #0c1c2a 100%) !important;
  }
  .bm-page-head .bm-container{ max-width: var(--wrap); margin:0 auto; padding:0 var(--space); }
  .bm-page-title{ margin:0!important; font-weight:800!important; font-size:clamp(32px,5vw,52px)!important; line-height:1.2!important;
    color:var(--ink-strong)!important; text-shadow:0 2px 14px rgba(0,0,0,.25)!important; position:relative!important; display:inline-block!important; padding-bottom:12px!important;
  }
  .bm-page-title::after{ content:""; position:absolute; left:50%; transform:translateX(-50%); bottom:0; width:60px; height:4px; border-radius:2px; background:var(--teal); }
  .bm-card-art{ aspect-ratio:16/9; display:block; border-radius:14px; overflow:hidden; background:#0a1d2a; }
  .bm-card-img{ width:100%; height:100%; object-fit:cover; display:block; }
  .bm-cta{ cursor:pointer; }
</style>

<main>
  <header class="bm-page-head">
    <div class="bm-container">
      <h1 class="bm-page-title"><?php single_cat_title(); ?></h1>
    </div>
  </header>

  <section class="bm-section">
    <div class="bm-container">

      <?php if ( have_posts() ) : ?>
        <div class="bm-cards">
          <?php while ( have_posts() ) : the_post(); ?>
            <article class="bm-card">
              <a class="bm-card-art" href="<?php the_permalink(); ?>">
                <?php
                if ( has_post_thumbnail() ) {
                  the_post_thumbnail( 'bm-card', [
                    'class'    => 'bm-card-img',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'alt'      => the_title_attribute(['echo'=>false]),
                  ] );
                } else {
                  $ph = rawurlencode( get_the_title() );
                  echo '<img class="bm-card-img" src="https://placehold.co/800x450/0f2434/adc7cf?text='.$ph.
                       '" width="800" height="450" alt="">';
                }
                ?>
              </a>

              <h3 class="bm-card-title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h3>

              <p class="bm-card-text"><?php echo wp_trim_words( get_the_excerpt(), 26, '…' ); ?></p>
            </article>
          <?php endwhile; ?>
        </div>

        <nav class="bm-pagination" aria-label="<?php esc_attr_e('Pagination','bitmomo'); ?>">
          <?php
          echo paginate_links([
            'prev_text' => '« Prev',
            'next_text' => 'Next »',
          ]);
          ?>
        </nav>

      <?php else : ?>
        <p style="opacity:.8"><?php esc_html_e('Tidak ada artikel di kategori ini.', 'bitmomo'); ?></p>
      <?php endif; ?>

    </div>
  </section>
</main>

<?php get_footer(); ?>

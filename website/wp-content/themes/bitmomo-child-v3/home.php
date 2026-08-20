<?php
/** Bitmomo — Home (Latest Posts) v4.0 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header class="bm-header">
  <div class="bm-container">
    
    <!-- BRAND/LOGO -->
    <?php if (function_exists('the_custom_logo') && has_custom_logo()) : ?>
      <div class="bm-brand"><?php the_custom_logo(); ?></div>
    <?php else : ?>
      <a href="<?php echo esc_url(home_url('/')); ?>" class="bm-brand">
        <span class="bm-brand-text">BITMOMO</span>
      </a>
    <?php endif; ?>
    
    <!-- HAMBURGER BUTTON (Mobile) -->
    <button class="bm-hamburger" id="bm-hamburger" aria-label="Menu" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>
    
    <!-- NAVIGATION -->
    <nav class="bm-nav" id="bm-nav">
      <?php
      wp_nav_menu([
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => 'bm-nav-list',
        'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
        'fallback_cb'    => false,
        'depth'          => 1,
      ]);
      ?>
    </nav>
    
    <!-- SUBSCRIBE BUTTON (Desktop) -->
    <a class="bm-cta js-open-subscribe" href="#subscribe">SUBSCRIBE</a>
    
  </div>
</header>

<main>
  <!-- HERO -->
  <section class="bm-hero">
    <div class="bm-container">
      <h1 class="bm-hero-title">Informasi AI &amp; <span class="teal">Crypto</span> Terdepan</h1>
      <p class="bm-hero-sub">Bergabung dengan ribuan pembaca dan dapatkan berita serta analisis terkini langsung ke inbox Anda.</p>
      <a class="bm-hero-btn js-open-subscribe" href="#subscribe">Mulai Berlangganan</a>
    </div>
  </section>

  <!-- LIST POSTS -->
  <section class="bm-section">
    <div class="bm-container">
      <?php if (have_posts()) : ?>
        <div class="bm-cards">
          <?php $i = 0; while (have_posts()) : the_post(); $i++; ?>
            <article class="bm-card">
              <a class="bm-card-art" href="<?php the_permalink(); ?>">
                <?php if (has_post_thumbnail()) {
                  the_post_thumbnail('bm-card', [
                    'class'         => 'bm-card-img',
                    'loading'       => $i === 1 ? 'eager' : 'lazy',
                    'fetchpriority' => $i === 1 ? 'high' : false,
                    'width'         => 800,
                    'height'        => 450
                  ]);
                } else { ?>
                  <img class="bm-card-img" src="https://placehold.co/800x450/0f2434/adc7cf?text=<?php echo rawurlencode(get_the_title()); ?>" alt="" width="800" height="450">
                <?php } ?>
              </a>
              <h3 class="bm-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
              <p class="bm-card-text"><?php echo wp_trim_words(get_the_excerpt(), 26, '…'); ?></p>
            </article>
          <?php endwhile; ?>
        </div>
        <nav class="bm-pagination">
          <?php echo paginate_links([
            'prev_text' => '« Prev',
            'next_text' => 'Next »',
          ]); ?>
        </nav>
      <?php else : ?>
        <p>Tidak ada artikel.</p>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer class="bm-footer">
  <div class="bm-container">
    <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>.</p>
  </div>
</footer>

<?php wp_footer(); ?>

<!-- Hamburger Menu Script -->
<script>
(function() {
  var hamburger = document.getElementById('bm-hamburger');
  var nav = document.getElementById('bm-nav');
  if (!hamburger || !nav) return;
  
  hamburger.addEventListener('click', function() {
    hamburger.classList.toggle('active');
    nav.classList.toggle('open');
    document.body.classList.toggle('menu-open');
    
    var expanded = hamburger.getAttribute('aria-expanded') === 'true';
    hamburger.setAttribute('aria-expanded', !expanded);
  });
  
  // Close menu when clicking a link
  var links = nav.querySelectorAll('a');
  links.forEach(function(link) {
    link.addEventListener('click', function() {
      hamburger.classList.remove('active');
      nav.classList.remove('open');
      document.body.classList.remove('menu-open');
      hamburger.setAttribute('aria-expanded', 'false');
    });
  });
  
  // Close menu on resize to desktop
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
      hamburger.classList.remove('active');
      nav.classList.remove('open');
      document.body.classList.remove('menu-open');
      hamburger.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
</body>
</html>

<?php
/** Safe placeholder route for the first Bitmomo Pro funnel slice. */

if (!defined('ABSPATH')) exit;

function bitmomo_render_pro_placeholder() {
    $path = trim((string) wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if ($path !== 'pro') return;

    status_header(200);
    nocache_headers();
    get_header();
    ?>
    <main class="bm-pro-page">
      <section class="bm-pro-page-hero">
        <div class="bm-container">
          <span class="bm-pro-eyebrow">BITMOMO PRO</span>
          <h1>Intelligence yang menunjukkan kapan thesis pasar berubah.</h1>
          <p>Halaman lengkap Bitmomo Pro sedang disiapkan. Belum ada pembayaran atau langganan yang diproses di halaman ini.</p>
          <a class="bm-pro-page-back" href="<?php echo esc_url(home_url('/#bitmomo-pro')); ?>">Kembali ke BTC Daily Intelligence</a>
        </div>
      </section>
    </main>
    <?php
    get_footer();
    exit;
}
add_action('template_redirect', 'bitmomo_render_pro_placeholder', 1);

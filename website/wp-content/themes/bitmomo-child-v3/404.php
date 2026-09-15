<?php
/** 404 — Bitmomo public surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary" class="bm-public-main">
  <section class="bm-public-page bm-public-page--error">
    <div class="bm-public-page__inner">
      <header class="bm-public-head bm-public-head--page">
        <p class="bm-public-eyebrow">404</p>
        <h1 class="bm-public-title"><?php esc_html_e( 'Halaman tidak ditemukan.', 'bitmomo' ); ?></h1>
        <p class="bm-public-lead"><?php esc_html_e( 'Tautan ini mungkin sudah berubah atau halaman yang Anda cari tidak tersedia.', 'bitmomo' ); ?></p>
      </header>
      <div class="bm-public-actions">
        <a class="bm-public-button" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>"><?php esc_html_e( 'Buka BTC Intelligence', 'bitmomo' ); ?></a>
        <a class="bm-public-link" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Kembali ke beranda →', 'bitmomo' ); ?></a>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>

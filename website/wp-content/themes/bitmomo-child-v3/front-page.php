<?php
/**
 * Front Page — Bitmomo canonical public hierarchy.
 *
 * Homepage responsibility is intentionally narrow: show current BTC
 * intelligence, prove accountability with recorded evidence, identify the data
 * and infrastructure ecosystem, surface qualified research, then convert
 * interest into Bitmomo Pro / Founding access.
 *
 * 1. Header
 * 2. Current BTC intelligence
 * 3. Data & infrastructure trust strip
 * 4. Accountability + delayed Pro proof + compact product model
 * 5. Qualified market research
 * 6. Bitmomo Pro + Founding conversion
 * 7. Footer
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The homepage now renders the canonical public intelligence snapshot directly.
// Do not let full-page/CDN cache make a valid old brief look current after the
// adapter has already moved to delayed/unavailable state.
if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
nocache_headers();
do_action( 'litespeed_control_set_nocache', 'Homepage intelligence freshness' );

get_header();
?>
<main id="primary">
  <?php get_template_part( 'template-parts/home', 'hero' ); ?>

  <section class="bm-home-infrastructure" aria-labelledby="bm-home-infrastructure-title">
    <div class="bm-container">
      <div class="bm-home-infrastructure__row">
        <p class="bm-home-infrastructure__eyebrow" id="bm-home-infrastructure-title">DATA &amp; INFRASTRUCTURE</p>
        <div class="bm-home-infrastructure__content">
          <ul class="bm-home-infrastructure__brands" aria-label="Ekosistem data dan infrastruktur Bitmomo">
            <li class="bm-home-infrastructure__brand">Binance</li>
            <li class="bm-home-infrastructure__brand">Bybit</li>
            <li class="bm-home-infrastructure__brand">TradingView</li>
            <li class="bm-home-infrastructure__brand">Glassnode</li>
            <li class="bm-home-infrastructure__brand">CryptoQuant</li>
            <li class="bm-home-infrastructure__brand">Dune</li>
            <li class="bm-home-infrastructure__brand">CoinGecko</li>
            <li class="bm-home-infrastructure__brand">Hyperliquid</li>
          </ul>
          <p class="bm-home-infrastructure__note">Bitmomo membangun intelligence dari data pasar, analytical tools, dan market infrastructure.</p>
          <p class="bm-home-infrastructure__legal">Nama dan merek pihak ketiga adalah milik masing-masing pemilik dan tidak menyiratkan afiliasi atau endorsement.</p>
        </div>
      </div>
    </div>
  </section>

  <?php get_template_part( 'template-parts/how-it-works' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
  <?php get_template_part( 'template-parts/whitelist' ); ?>
</main>
<?php get_footer(); ?>

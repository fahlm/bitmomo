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

  <?php
  $bm_infra_sprite = get_stylesheet_directory_uri() . '/assets/images/infrastructure-logos.svg';
  $bm_market_data_brands = array(
    array( 'slug' => 'binance', 'label' => 'Binance', 'wordmark' => false ),
    array( 'slug' => 'bybit', 'label' => 'Bybit', 'wordmark' => true ),
  );
  $bm_research_ecosystem_brands = array(
    array( 'slug' => 'tradingview', 'label' => 'TradingView', 'wordmark' => false ),
    array( 'slug' => 'glassnode', 'label' => 'Glassnode', 'wordmark' => true ),
    array( 'slug' => 'cryptoquant', 'label' => 'CryptoQuant', 'wordmark' => false ),
    array( 'slug' => 'dune', 'label' => 'Dune', 'wordmark' => true ),
    array( 'slug' => 'coingecko', 'label' => 'CoinGecko', 'wordmark' => false ),
    array( 'slug' => 'hyperliquid', 'label' => 'Hyperliquid', 'wordmark' => false ),
  );
  ?>
  <section class="bm-home-infrastructure" aria-labelledby="bm-home-infrastructure-title">
    <div class="bm-container">
      <div class="bm-home-infrastructure__row">
        <p class="bm-home-infrastructure__eyebrow" id="bm-home-infrastructure-title">DATA &amp; INFRASTRUKTUR</p>
        <div class="bm-home-infrastructure__content">
          <div class="bm-home-infrastructure__group">
            <p class="bm-home-infrastructure__group-label">SUMBER DATA PASAR</p>
            <ul class="bm-home-infrastructure__brands" aria-label="Sumber data pasar Bitmomo">
              <?php foreach ( $bm_market_data_brands as $bm_brand ) : ?>
                <li class="bm-home-infrastructure__brand<?php echo $bm_brand['wordmark'] ? ' is-wordmark' : ''; ?>">
                  <svg class="bm-home-infrastructure__mark" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $bm_infra_sprite . '#' . $bm_brand['slug'] ); ?>"></use></svg>
                  <span class="bm-home-infrastructure__name"><?php echo esc_html( $bm_brand['label'] ); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="bm-home-infrastructure__group">
            <p class="bm-home-infrastructure__group-label">RISET &amp; ANALISIS</p>
            <ul class="bm-home-infrastructure__brands" aria-label="Ekosistem riset dan analisis Bitmomo">
              <?php foreach ( $bm_research_ecosystem_brands as $bm_brand ) : ?>
                <li class="bm-home-infrastructure__brand<?php echo $bm_brand['wordmark'] ? ' is-wordmark' : ''; ?>">
                  <svg class="bm-home-infrastructure__mark" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $bm_infra_sprite . '#' . $bm_brand['slug'] ); ?>"></use></svg>
                  <span class="bm-home-infrastructure__name"><?php echo esc_html( $bm_brand['label'] ); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <p class="bm-home-infrastructure__note">Sumber data pasar yang digunakan saat ini dipisahkan dari platform riset dan analisis yang menjadi bagian dari cakupan kerja Bitmomo.</p>
          <p class="bm-home-infrastructure__legal">Nama dan merek pihak ketiga adalah milik masing-masing pemilik. Penampilannya tidak menyiratkan afiliasi, kemitraan, atau endorsement.</p>
        </div>
      </div>
    </div>
  </section>
  <?php unset( $bm_infra_sprite, $bm_market_data_brands, $bm_research_ecosystem_brands, $bm_brand ); ?>

  <?php get_template_part( 'template-parts/how-it-works' ); ?>
  <?php get_template_part( 'template-parts/research' ); ?>
  <?php get_template_part( 'template-parts/whitelist' ); ?>
</main>
<?php get_footer(); ?>

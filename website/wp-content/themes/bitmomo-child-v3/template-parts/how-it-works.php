<?php
/**
 * Compact explanation of how the live Bitmomo system turns data into a
 * Decision View. Future capabilities are mentioned once, not repeated as a
 * second full section immediately below the flow.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-howworks" aria-labelledby="bm-howworks-title">
  <div class="bm-container">
    <header class="bm-howworks-head">
      <span class="bm-howworks-eyebrow"><?php esc_html_e( 'CARA KERJA BITMOMO', 'bitmomo' ); ?></span>
      <h2 id="bm-howworks-title"><?php esc_html_e( 'Dari data pasar mentah menjadi satu Decision View.', 'bitmomo' ); ?></h2>
    </header>

    <ol class="bm-howworks-steps">
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( 'Market Data', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Price, market structure, derivatives (funding & basis), dan momentum diproses dari sumber pasar yang tersedia.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( 'Bitmomo Intelligence', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Deterministic logic dan quality gate mengubah input menjadi Opportunity, Market State, Directional Bias, Confidence, dan drivers.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( 'Decision View', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Hasil diringkas menjadi kondisi sekarang dan, di Pro, skenario berikutnya serta apa yang dapat mengubah thesis.', 'bitmomo' ); ?></p>
      </li>
    </ol>

    <div class="bm-howworks-roadmap">
      <strong><?php esc_html_e( 'Roadmap Pro:', 'bitmomo' ); ?></strong>
      <?php esc_html_e( 'Altcoin Intelligence, Daily Alpha Discovery, 11 AI Analysts, dan Watchtower sedang dikembangkan.', 'bitmomo' ); ?>
      <a href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Lihat roadmap →', 'bitmomo' ); ?></a>
    </div>
  </div>
</section>

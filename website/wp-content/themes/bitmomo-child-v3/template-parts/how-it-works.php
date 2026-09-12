<?php
/** Compact homepage explanation in visitor language. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-howworks" aria-labelledby="bm-howworks-title">
  <div class="bm-container">
    <header class="bm-howworks-head">
      <span class="bm-howworks-eyebrow"><?php esc_html_e( 'CARA KERJA', 'bitmomo' ); ?></span>
      <h2 id="bm-howworks-title"><?php esc_html_e( 'Compression → Decision Support → Accountability.', 'bitmomo' ); ?></h2>
    </header>

    <ol class="bm-howworks-steps">
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( '01 · Compression', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Harga, struktur pasar, volatilitas, momentum, dan derivatif diringkas menjadi insight yang jelas dan mudah dibaca.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( '02 · Decision Support', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Gratis menjawab apa yang terjadi sekarang. Pro menambahkan skenario paling relevan, apa yang perlu dipantau, dan kapan thesis berubah.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( '03 · Accountability', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Setiap insight diberi timestamp, dicatat, dan dievaluasi terhadap hasil aktual. Data stale atau tidak valid tidak dipaksakan menjadi insight.', 'bitmomo' ); ?></p>
      </li>
    </ol>
  </div>
</section>

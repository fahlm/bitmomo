<?php
/** Compact homepage explanation in visitor language. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-howworks" aria-labelledby="bm-howworks-title">
  <div class="bm-container">
    <header class="bm-howworks-head">
      <span class="bm-howworks-eyebrow"><?php esc_html_e( 'CARA KERJA', 'bitmomo' ); ?></span>
      <h2 id="bm-howworks-title"><?php esc_html_e( 'Dari data pasar ke pembacaan yang bisa dipakai.', 'bitmomo' ); ?></h2>
    </header>

    <ol class="bm-howworks-steps">
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( '01 · Baca pasar', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Bitmomo membaca pergerakan harga, volatilitas, struktur pasar, dan kondisi derivatif BTC.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( '02 · Ringkas konteks', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Data yang saling mendukung atau bertentangan diringkas menjadi arah, tingkat keyakinan, dan alasan utamanya.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( '03 · Pahami langkah berikutnya', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Versi gratis menjawab apa yang terjadi sekarang. Pro menjelaskan apa yang perlu dipantau berikutnya dan kapan pandangan pasar perlu berubah.', 'bitmomo' ); ?></p>
      </li>
    </ol>
  </div>
</section>

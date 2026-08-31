<?php
/**
 * Standalone "Bitmomo Pro" homepage section (hierarchy position 4).
 *
 * Extracted from btc-intelligence-card.php so BTC Intelligence (position 3)
 * and Bitmomo Pro (position 4) are two distinct sections instead of one
 * bundled together -- same markup/CSS as before, just its own section.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section">
  <div class="bm-container">
    <aside class="bm-pro-teaser" id="bitmomo-pro" aria-labelledby="bm-pro-title">
      <div class="bm-pro-copy">
        <span class="bm-pro-eyebrow">BITMOMO PRO</span>
        <h3 id="bm-pro-title">Jangan cuma tahu bias pasar. Ketahui apa yang bisa mengubahnya.</h3>
        <p>Bitmomo Pro membantu Anda memahami skenario di balik perubahan pasar.</p>
        <a class="bm-pro-cta" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Lihat Bitmomo Pro</a>
      </div>
      <ul class="bm-pro-locks" aria-label="Fitur Bitmomo Pro">
        <li><span>Expected Range</span><span aria-hidden="true">🔒</span></li>
        <li><span>Scenario Map</span><span aria-hidden="true">🔒</span></li>
        <li><span>Thesis Invalidation</span><span aria-hidden="true">🔒</span></li>
        <li><span>What Changed</span><span aria-hidden="true">🔒</span></li>
      </ul>
    </aside>
  </div>
</section>

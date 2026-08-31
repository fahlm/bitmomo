<?php
/**
 * Newsletter / social section -- homepage section (hierarchy position 6).
 *
 * Reuses the existing MailPoet subscribe modal (js-open-subscribe / #subscribe,
 * already wired in bitmomo-frontend.js and functions.php) instead of adding
 * new signup infrastructure. No social links are included here: no verified
 * social handles/URLs exist anywhere in the codebase, and fabricating them
 * would risk dead or wrong links -- see final report.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-newsletter" aria-labelledby="bm-newsletter-title">
  <div class="bm-container bm-newsletter-inner">
    <div class="bm-newsletter-copy">
      <h2 id="bm-newsletter-title">Jangan lewatkan update Bitmomo</h2>
      <p>Ringkasan BTC Intelligence dan riset terbaru, langsung ke email Anda.</p>
    </div>
    <a class="bm-newsletter-cta js-open-subscribe" href="#subscribe">Gabung Newsletter</a>
  </div>
</section>

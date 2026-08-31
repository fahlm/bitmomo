<?php
/**
 * Newsletter section -- homepage section (hierarchy position 10, compact,
 * near the footer).
 *
 * SECONDARY email capture. Founding Membership Whitelist (whitelist.php,
 * position 5) is now the PRIMARY conversion goal while checkout is closed --
 * this section must not visually compete with it: no orange (--cta is
 * reserved for the single primary action), smaller type, an outline CTA
 * instead of a filled one. See custom.css's "Newsletter" block.
 *
 * Reuses the existing MailPoet subscribe modal (js-open-subscribe / #subscribe,
 * already wired in bitmomo-frontend.js and functions.php) instead of adding
 * new signup infrastructure -- this stays the free-newsletter delivery
 * layer; it is not, and must not become, the whitelist's canonical record.
 * No social links are included here: no verified social handles/URLs exist
 * anywhere in the codebase, and fabricating them would risk dead or wrong
 * links -- see final report.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-newsletter" aria-labelledby="bm-newsletter-title">
  <div class="bm-container bm-newsletter-inner">
    <div class="bm-newsletter-copy">
      <h2 id="bm-newsletter-title">Dapatkan ringkasan BTC di inbox</h2>
      <p>Ringkasan BTC Intelligence dan riset terbaru, langsung ke email Anda.</p>
    </div>
    <a class="bm-newsletter-cta js-open-subscribe" href="#subscribe">Gabung Newsletter</a>
  </div>
</section>

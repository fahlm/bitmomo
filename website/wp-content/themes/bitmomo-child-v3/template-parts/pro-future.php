<?php
/**
 * "Future Pro capability" homepage section (hierarchy position 3).
 *
 * 11 AI Analysts / Watchtower / Telegram Alerts. None of this is live --
 * status is always rendered as "IN DEVELOPMENT" only (never "COMING",
 * "BETA", or "LIVE"), and this section deliberately does not sit inside
 * the Bitmomo Pro section above it so it never reads as an active feature.
 * Copy is verbatim from the brief and matches the canonical Help Center
 * FAQ category ("11 AI Analysts & Watchtower") this section links out to.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-future" aria-labelledby="bm-future-title">
  <div class="bm-container">
    <header class="bm-future-head">
      <span class="bm-future-eyebrow">BITMOMO PRO &middot; IN DEVELOPMENT</span>
      <h2 id="bm-future-title">11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.</h2>
      <p class="bm-future-sub">Dua kali sehari, 11 analyst memberi verdict. Di antaranya, Watchtower mendeteksi perubahan penting dan mengirim update ke Telegram.</p>
    </header>
    <ul class="bm-future-list" aria-label="Fitur yang sedang dikembangkan">
      <li><span class="bm-future-badge">IN DEVELOPMENT</span><span class="bm-future-name">11 AI Analysts</span></li>
      <li><span class="bm-future-badge">IN DEVELOPMENT</span><span class="bm-future-name">Watchtower</span></li>
      <li><span class="bm-future-badge">IN DEVELOPMENT</span><span class="bm-future-name">Telegram Alerts</span></li>
    </ul>
    <a class="bm-future-link" href="<?php echo esc_url( home_url( '/help/#ai-analysts-watchtower' ) ); ?>">Pelajari lebih lanjut &rarr;</a>
  </div>
</section>

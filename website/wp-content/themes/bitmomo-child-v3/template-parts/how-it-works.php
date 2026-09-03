<?php
/**
 * "How Bitmomo Works" — homepage section (hierarchy position 6): a compact
 * system overview, Market Data -> Bitmomo Intelligence -> Decision View ->
 * future Watchtower.
 *
 * Absorbs the former standalone pro-future.php section intact (same
 * verbatim copy, same IN DEVELOPMENT-only badges) as this pipeline's future
 * stage -- repositioned, not deleted, per the sprint's "don't remove
 * meaningful existing sections, merge intelligently" rule. Nothing here is
 * new content; only the surrounding frame is new.
 *
 * The three "live" steps only name inputs actually read by the live Signal
 * Engine today (price, market structure, derivatives funding/basis,
 * momentum -- confirmed in class-bitmomo-ai-signal-engine.php and
 * class-bitmomo-ai-binance.php). Macro, geopolitics, sentiment and news
 * monitoring are not wired into any live data source in this codebase, so
 * they are never named as current inputs -- only Watchtower, explicitly
 * marked IN DEVELOPMENT, ever gestures at that future scope.
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
        <p><?php esc_html_e( 'Price, market structure, derivatives (funding & basis), dan momentum — diproses langsung dari data pasar.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( 'Bitmomo Intelligence', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Deterministic logic dan quality gate mengubah data mentah menjadi Market State, Bias, dan Confidence.', 'bitmomo' ); ?></p>
      </li>
      <li>
        <span class="bm-howworks-step-label"><?php esc_html_e( 'Decision View', 'bitmomo' ); ?></span>
        <p><?php esc_html_e( 'Dirangkum menjadi satu tampilan: kondisi saat ini, skenario berikutnya, dan apa yang bisa mengubahnya.', 'bitmomo' ); ?></p>
      </li>
      <li class="bm-howworks-step--future">
        <span class="bm-howworks-step-label"><?php esc_html_e( 'Watchtower', 'bitmomo' ); ?> <span class="bm-future-badge"><?php esc_html_e( 'SEGERA HADIR', 'bitmomo' ); ?></span></span>
        <p><?php esc_html_e( 'Mendeteksi perubahan penting di antara update terjadwal dan mengirim alert — detail di bawah.', 'bitmomo' ); ?></p>
      </li>
    </ol>

    <div class="bm-howworks-future">
      <header class="bm-future-head">
        <span class="bm-future-eyebrow"><?php esc_html_e( 'BITMOMO PRO · SEGERA HADIR', 'bitmomo' ); ?></span>
        <h3 id="bm-future-title"><?php esc_html_e( '11 AI Analysts membangun thesis. Watchtower menjaganya tetap relevan.', 'bitmomo' ); ?></h3>
        <p class="bm-future-sub"><?php esc_html_e( 'Dua kali sehari, 11 analyst memberi verdict. Di antaranya, Watchtower mendeteksi perubahan penting dan mengirim update ke Telegram.', 'bitmomo' ); ?></p>
      </header>
      <ul class="bm-future-list" aria-label="<?php esc_attr_e( 'Fitur yang sedang dikembangkan', 'bitmomo' ); ?>">
        <li><span class="bm-future-badge"><?php esc_html_e( 'SEGERA HADIR', 'bitmomo' ); ?></span><span class="bm-future-name"><?php esc_html_e( '11 AI Analysts', 'bitmomo' ); ?></span></li>
        <li><span class="bm-future-badge"><?php esc_html_e( 'SEGERA HADIR', 'bitmomo' ); ?></span><span class="bm-future-name"><?php esc_html_e( 'Watchtower', 'bitmomo' ); ?></span></li>
        <li><span class="bm-future-badge"><?php esc_html_e( 'IN DEVELOPMENT', 'bitmomo' ); ?></span><span class="bm-future-name"><?php esc_html_e( 'Telegram Alerts', 'bitmomo' ); ?></span></li>
      </ul>
      <a class="bm-future-link" href="<?php echo esc_url( home_url( '/help/#ai-analysts-watchtower' ) ); ?>"><?php esc_html_e( 'Pelajari lebih lanjut →', 'bitmomo' ); ?></a>
    </div>
  </div>
</section>

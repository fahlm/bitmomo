<?php
/** Homepage value map: expose the whole product without dumping the engine. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="bm-section bm-howworks bm-home-value-map" aria-labelledby="bm-howworks-title">
  <div class="bm-container">
    <header class="bm-howworks-head">
      <span class="bm-howworks-eyebrow"><?php esc_html_e( 'THE BITMOMO LOOP', 'bitmomo' ); ?></span>
      <h2 id="bm-howworks-title"><?php esc_html_e( 'Sekarang. Berikutnya. Setelah outcome.', 'bitmomo' ); ?></h2>
    </header>

    <div class="bm-home-value-map__grid">
      <article class="bm-home-value-map__lane is-now">
        <div class="bm-home-value-map__lane-head">
          <span class="bm-howworks-step-label"><?php esc_html_e( 'NOW · BTC INTELLIGENCE', 'bitmomo' ); ?></span>
          <strong><?php esc_html_e( 'Pahami kondisi pasar tanpa membuka lima dashboard.', 'bitmomo' ); ?></strong>
        </div>
        <div class="bm-home-value-map__outputs" aria-label="<?php esc_attr_e( 'Output BTC Intelligence gratis', 'bitmomo' ); ?>">
          <span>Bias</span><span>Confidence</span><span>Market Pulse</span><span>What Changed</span><span>Why It Matters</span><span>1 Watch</span>
        </div>
        <a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>" data-bm-event="homepage_btc_intelligence_click" data-bm-placement="value_map_now"><?php esc_html_e( 'Lihat current intelligence →', 'bitmomo' ); ?></a>
      </article>

      <article class="bm-home-value-map__lane is-next">
        <div class="bm-home-value-map__lane-head">
          <span class="bm-howworks-step-label"><?php esc_html_e( 'NEXT · BITMOMO PRO', 'bitmomo' ); ?></span>
          <strong><?php esc_html_e( 'Ubah pembacaan pasar menjadi peta keputusan yang bisa dipantau.', 'bitmomo' ); ?></strong>
        </div>
        <div class="bm-home-value-map__outputs" aria-label="<?php esc_attr_e( 'Output Bitmomo Pro', 'bitmomo' ); ?>">
          <span>Expected Range</span><span>Base / Bull / Bear</span><span>Invalidation</span><span>Full Monitoring</span>
        </div>
        <a href="<?php echo esc_url( home_url( '/pro/#pro-example' ) ); ?>" data-bm-event="homepage_pro_interest" data-bm-placement="value_map_next"><?php esc_html_e( 'Lihat Decision View Pro →', 'bitmomo' ); ?></a>
      </article>

      <article class="bm-home-value-map__lane is-proof">
        <div class="bm-home-value-map__lane-head">
          <span class="bm-howworks-step-label"><?php esc_html_e( 'AFTER · ACCOUNTABILITY', 'bitmomo' ); ?></span>
          <strong><?php esc_html_e( 'Nilai analisis setelah pasar bergerak, bukan setelah narasinya diedit.', 'bitmomo' ); ?></strong>
        </div>
        <div class="bm-home-value-map__outputs" aria-label="<?php esc_attr_e( 'Bukti akuntabilitas Bitmomo', 'bitmomo' ); ?>">
          <span>Frozen Thesis</span><span>+24H Outcome</span><span>Range Hit</span><span>Track Record</span>
        </div>
        <a href="<?php echo esc_url( home_url( '/btc-intelligence/#pro-archive' ) ); ?>" data-bm-event="homepage_proof_click" data-bm-placement="value_map_proof"><?php esc_html_e( 'Audit bukti historis →', 'bitmomo' ); ?></a>
      </article>
    </div>
  </div>
</section>

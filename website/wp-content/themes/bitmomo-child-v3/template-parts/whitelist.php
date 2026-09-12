<?php
/**
 * Unified homepage Bitmomo Pro conversion surface.
 *
 * One section explains the paid value and owns the next action. Before a
 * checkout URL exists it renders the canonical whitelist widget; once a real
 * checkout is configured the same surface becomes the purchase CTA. This
 * avoids the previous Pro teaser + separate whitelist repetition.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_wl_checkout_url = function_exists( 'bitmomo_pro_get_checkout_url' ) ? bitmomo_pro_get_checkout_url() : '';
$bm_wl_cap = class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP : 149;
$bm_wl_batch = class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_OPERATIONAL_BATCH : 25;
?>
<section class="bm-section bm-wl-home" aria-labelledby="bm-wl-home-title">
  <div class="bm-container">
    <div class="bm-wl-unified">
      <div class="bm-wl-unified__intro">
        <span class="bm-wl-unified__eyebrow"><?php esc_html_e( 'BITMOMO PRO', 'bitmomo' ); ?></span>
        <h2 class="bm-wl-unified__title" id="bm-wl-home-title"><?php esc_html_e( 'Dari kondisi pasar ke apa yang perlu dipantau berikutnya.', 'bitmomo' ); ?></h2>
        <p class="bm-wl-unified__copy"><?php esc_html_e( 'Pro menambahkan Decision View BTC: skenario, expected range, invalidation, perubahan thesis, dan penjelasan confidence.', 'bitmomo' ); ?></p>
        <ul class="bm-wl-unified__features" aria-label="<?php esc_attr_e( 'Bitmomo Pro saat ini', 'bitmomo' ); ?>">
          <li><?php esc_html_e( 'Expected Range', 'bitmomo' ); ?></li>
          <li><?php esc_html_e( 'Scenario Map', 'bitmomo' ); ?></li>
          <li><?php esc_html_e( 'Thesis Invalidation', 'bitmomo' ); ?></li>
          <li><?php esc_html_e( 'What Changed', 'bitmomo' ); ?></li>
        </ul>
        <p class="bm-wl-teaser-note"><?php esc_html_e( 'Roadmap Pro: Altcoin Intelligence, Daily Alpha Discovery, 11 AI Analysts, dan Watchtower — semuanya masih dalam pengembangan.', 'bitmomo' ); ?></p>
      </div>

      <div class="bm-wl-unified__form">
        <?php if ( ! empty( $bm_wl_checkout_url ) ) : ?>
          <div class="bm-wl-teaser">
            <p class="bm-wl-teaser-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php esc_html_e( 'Rp149.000 / bulan', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php esc_html_e( 'Rp1.490.000 / tahun', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members · Batch pertama %d', 'bitmomo' ), $bm_wl_cap, $bm_wl_batch ) ); ?></p>
            <a class="bm-wl-teaser-cta" href="<?php echo esc_url( $bm_wl_checkout_url ); ?>"><?php esc_html_e( 'KUNCI HARGA FOUNDING', 'bitmomo' ); ?></a>
          </div>
        <?php elseif ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) : ?>
          <?php Bitmomo_Pro_Whitelist::instance()->render_widget( array( 'source' => 'homepage' ) ); ?>
        <?php else : ?>
          <div class="bm-wl-teaser">
            <p class="bm-wl-teaser-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php esc_html_e( 'Rp149.000 / bulan', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php esc_html_e( 'Rp1.490.000 / tahun', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members · Batch pertama %d', 'bitmomo' ), $bm_wl_cap, $bm_wl_batch ) ); ?></p>
            <a class="bm-wl-teaser-cta" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'PELAJARI BITMOMO PRO', 'bitmomo' ); ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php unset( $bm_wl_checkout_url, $bm_wl_cap, $bm_wl_batch ); ?>

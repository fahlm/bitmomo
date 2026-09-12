<?php
/** Unified homepage Bitmomo Pro conversion surface. @package Bitmomo */
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
        <h2 class="bm-wl-unified__title" id="bm-wl-home-title"><?php esc_html_e( 'Lihat apa yang perlu dipantau berikutnya.', 'bitmomo' ); ?></h2>
        <p class="bm-wl-unified__copy"><?php esc_html_e( 'Skenario, expected range, invalidation, dan perubahan thesis — tanpa harus memantau semuanya sendiri.', 'bitmomo' ); ?></p>
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
            <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members · Batch pertama %d', 'bitmomo' ), $bm_wl_cap, $bm_wl_batch ) ); ?></p>
            <a class="bm-wl-teaser-cta" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'PELAJARI BITMOMO PRO', 'bitmomo' ); ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php unset( $bm_wl_checkout_url, $bm_wl_cap, $bm_wl_batch ); ?>

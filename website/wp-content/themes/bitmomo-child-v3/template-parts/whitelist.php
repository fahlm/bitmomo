<?php
/** Unified homepage Bitmomo Pro conversion surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_wl_checkout_url = function_exists( 'bitmomo_pro_get_checkout_url' ) ? bitmomo_pro_get_checkout_url() : '';
$bm_wl_price_label  = class_exists( 'Bitmomo_Pro_Sales' ) ? Bitmomo_Pro_Sales::PRICE_LABEL : 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
$bm_wl_cap          = class_exists( 'Bitmomo_Pro_Sales' ) ? Bitmomo_Pro_Sales::SEAT_CAP : ( class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP : 149 );
$bm_wl_batch        = class_exists( 'Bitmomo_Pro_Sales' ) ? Bitmomo_Pro_Sales::BATCH_ONE : ( class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_OPERATIONAL_BATCH : 25 );
?>
<section class="bm-section bm-wl-home" aria-labelledby="bm-wl-home-title">
  <div class="bm-container">
    <div class="bm-wl-unified">
      <div class="bm-wl-unified__intro">
        <span class="bm-wl-unified__eyebrow"><?php esc_html_e( 'BITMOMO PRO · FOUNDING', 'bitmomo' ); ?></span>
        <h2 class="bm-wl-unified__title" id="bm-wl-home-title"><?php esc_html_e( 'Ketahui apa yang perlu dipantau berikutnya.', 'bitmomo' ); ?></h2>
        <p class="bm-wl-unified__copy"><?php esc_html_e( 'Pro merangkum skenario utama, rentang yang dipantau, level yang mengubah pandangan, dan perubahan penting — tanpa harus memantau semuanya sendiri.', 'bitmomo' ); ?></p>

        <dl class="bm-wl-unified__facts" aria-label="<?php esc_attr_e( 'Founding Membership', 'bitmomo' ); ?>">
          <div class="bm-wl-unified__fact">
            <dt><?php esc_html_e( 'Harga Founding', 'bitmomo' ); ?></dt>
            <dd><?php echo esc_html( $bm_wl_price_label ); ?></dd>
          </div>
          <div class="bm-wl-unified__fact">
            <dt><?php esc_html_e( 'Kapasitas', 'bitmomo' ); ?></dt>
            <dd><?php echo esc_html( sprintf( __( '%d anggota', 'bitmomo' ), $bm_wl_cap ) ); ?></dd>
          </div>
          <div class="bm-wl-unified__fact">
            <dt><?php esc_html_e( 'Batch pertama', 'bitmomo' ); ?></dt>
            <dd><?php echo esc_html( sprintf( __( '%d anggota', 'bitmomo' ), $bm_wl_batch ) ); ?></dd>
          </div>
        </dl>

        <a class="bm-wl-unified__detail-link" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">
          <?php esc_html_e( 'Lihat detail Bitmomo Pro →', 'bitmomo' ); ?>
        </a>
      </div>

      <div class="bm-wl-unified__form">
        <div class="bm-wl-unified__form-head">
          <?php if ( ! empty( $bm_wl_checkout_url ) ) : ?>
            <p class="bm-wl-unified__form-title"><?php esc_html_e( 'Founding Membership tersedia', 'bitmomo' ); ?></p>
            <p class="bm-wl-unified__form-copy"><?php esc_html_e( 'Lanjutkan melalui checkout resmi Bitmomo.', 'bitmomo' ); ?></p>
          <?php elseif ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) : ?>
            <p class="bm-wl-unified__form-title"><?php esc_html_e( 'Masuk Founding Whitelist', 'bitmomo' ); ?></p>
            <p class="bm-wl-unified__form-copy"><?php esc_html_e( 'Tinggalkan email. Kami akan memberi tahu saat akses batch pertama dibuka.', 'bitmomo' ); ?></p>
          <?php else : ?>
            <p class="bm-wl-unified__form-title"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo' ); ?></p>
            <p class="bm-wl-unified__form-copy"><?php esc_html_e( 'Pelajari Founding Membership dan apa yang akan Anda dapatkan.', 'bitmomo' ); ?></p>
          <?php endif; ?>
        </div>

        <?php if ( ! empty( $bm_wl_checkout_url ) ) : ?>
          <div class="bm-wl-teaser">
            <p class="bm-wl-teaser-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php echo esc_html( $bm_wl_price_label ); ?></p>
            <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members · Batch pertama %d', 'bitmomo' ), $bm_wl_cap, $bm_wl_batch ) ); ?></p>
            <a class="bm-wl-teaser-cta" href="<?php echo esc_url( $bm_wl_checkout_url ); ?>"><?php esc_html_e( 'KUNCI HARGA FOUNDING', 'bitmomo' ); ?></a>
          </div>
        <?php elseif ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) : ?>
          <?php Bitmomo_Pro_Whitelist::instance()->render_widget( array( 'source' => 'homepage' ) ); ?>
        <?php else : ?>
          <div class="bm-wl-teaser">
            <p class="bm-wl-teaser-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php echo esc_html( $bm_wl_price_label ); ?></p>
            <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members · Batch pertama %d', 'bitmomo' ), $bm_wl_cap, $bm_wl_batch ) ); ?></p>
            <a class="bm-wl-teaser-cta" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'PELAJARI BITMOMO PRO', 'bitmomo' ); ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php unset( $bm_wl_checkout_url, $bm_wl_price_label, $bm_wl_cap, $bm_wl_batch ); ?>

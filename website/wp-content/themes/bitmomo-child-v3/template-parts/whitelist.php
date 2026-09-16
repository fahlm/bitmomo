<?php
/** Unified homepage Bitmomo Pro conversion surface. @package Bitmomo */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_wl_checkout_url = function_exists( 'bitmomo_pro_get_checkout_url' ) ? bitmomo_pro_get_checkout_url() : '';
$bm_wl_price_label  = class_exists( 'Bitmomo_Pro_Sales' ) ? Bitmomo_Pro_Sales::PRICE_LABEL : 'Rp149.000 / bulan atau Rp1.490.000 / tahun';
$bm_wl_cap          = class_exists( 'Bitmomo_Pro_Sales' ) ? Bitmomo_Pro_Sales::SEAT_CAP : ( class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP : 149 );
$bm_wl_batch        = class_exists( 'Bitmomo_Pro_Sales' ) ? Bitmomo_Pro_Sales::BATCH_ONE : ( class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_OPERATIONAL_BATCH : 25 );
?>
<section id="founding-whitelist" class="bm-section bm-wl-home" aria-labelledby="bm-wl-home-title">
  <div class="bm-container">
    <div class="bm-wl-unified">
      <div class="bm-wl-unified__intro">
        <span class="bm-wl-unified__eyebrow"><?php esc_html_e( 'BITMOMO PRO · FOUNDING ACCESS', 'bitmomo' ); ?></span>
        <h2 class="bm-wl-unified__title" id="bm-wl-home-title"><?php esc_html_e( 'Butuh skenario yang lebih lengkap? Masuk ke Bitmomo Pro.', 'bitmomo' ); ?></h2>
        <p class="bm-wl-unified__copy"><?php esc_html_e( 'BTC Intelligence gratis menunjukkan kondisi pasar saat ini. Pro menambahkan Expected Range, Scenario Map, invalidasi tesis, dan perubahan sejak brief sebelumnya—dengan rekam evaluasi yang tetap dapat diaudit.', 'bitmomo' ); ?></p>

        <dl class="bm-wl-unified__facts" aria-label="<?php esc_attr_e( 'Founding Membership', 'bitmomo' ); ?>">
          <div class="bm-wl-unified__fact">
            <dt><?php esc_html_e( 'Founding Price', 'bitmomo' ); ?></dt>
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

        <div class="bm-wl-unified__links">
          <a class="bm-wl-unified__proof-link" href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>" data-bm-event="homepage_decision_ledger_click" data-bm-placement="whitelist_proof">
            <?php esc_html_e( 'Periksa Decision Ledger publik →', 'bitmomo' ); ?>
          </a>
          <a class="bm-wl-unified__detail-link" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>" data-bm-event="homepage_pro_interest" data-bm-placement="whitelist_detail">
            <?php esc_html_e( 'Lihat detail Bitmomo Pro →', 'bitmomo' ); ?>
          </a>
        </div>
      </div>

      <div class="bm-wl-unified__form">
        <div class="bm-wl-unified__form-head">
          <?php if ( ! empty( $bm_wl_checkout_url ) ) : ?>
            <p class="bm-wl-unified__form-title"><?php esc_html_e( 'Founding Membership tersedia', 'bitmomo' ); ?></p>
            <p class="bm-wl-unified__form-copy"><?php esc_html_e( 'Lanjutkan melalui checkout resmi Bitmomo.', 'bitmomo' ); ?></p>
          <?php elseif ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) : ?>
            <p class="bm-wl-unified__form-title"><?php esc_html_e( 'Daftar untuk Founding Access', 'bitmomo' ); ?></p>
            <p class="bm-wl-unified__form-copy"><?php esc_html_e( 'Masukkan email untuk menerima pemberitahuan ketika batch pertama dibuka. Tidak ada pembayaran pada tahap whitelist.', 'bitmomo' ); ?></p>
          <?php else : ?>
            <p class="bm-wl-unified__form-title"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo' ); ?></p>
            <p class="bm-wl-unified__form-copy"><?php esc_html_e( 'Pelajari struktur akses dan manfaat Founding Membership.', 'bitmomo' ); ?></p>
          <?php endif; ?>
        </div>

        <?php if ( ! empty( $bm_wl_checkout_url ) ) : ?>
          <div class="bm-wl-teaser">
            <p class="bm-wl-teaser-eyebrow"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo' ); ?></p>
            <p class="bm-wl-teaser-price"><?php echo esc_html( $bm_wl_price_label ); ?></p>
            <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members · Batch pertama %d', 'bitmomo' ), $bm_wl_cap, $bm_wl_batch ) ); ?></p>
            <a class="bm-wl-teaser-cta" href="<?php echo esc_url( $bm_wl_checkout_url ); ?>"><?php esc_html_e( 'AKTIFKAN FOUNDING MEMBERSHIP', 'bitmomo' ); ?></a>
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

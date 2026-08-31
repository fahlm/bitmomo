<?php
/**
 * "Founding Membership Whitelist" — homepage section (hierarchy position 5).
 *
 * While Bitmomo Pro checkout is not yet open, this is the PRIMARY Pro
 * conversion goal on the homepage (ahead of the newsletter) -- see
 * newsletter.php, now demoted to a compact, secondary section near the
 * footer.
 *
 * Does NOT duplicate PR #67's Bitmomo_Pro_Whitelist widget/AJAX/CPT
 * architecture. When that class is present (i.e. PR #64 + PR #67 have
 * merged), this section is a thin pass-through call to its own
 * render_widget() -- exactly what Bitmomo_Pro_Sales::render_cta() already
 * does on /pro, so the real submit/dedup/consent/rate-limit logic still
 * lives in exactly one place. Until those PRs merge into this branch's
 * base, it shows a static, honest, price-forward teaser (same locked
 * copy/numbers, sourced from Bitmomo_Pro_Entitlement_Service where
 * possible) whose CTA deep-links to /pro -- never a duplicated
 * form/AJAX/CPT implementation living in the theme.
 *
 * Collapses entirely once a real checkout URL is configured (the same
 * fail-open signal Bitmomo_Pro_Sales already keys off), so no template
 * rewrite is needed for that transition -- the Bitmomo Pro section above
 * this one is what carries the real purchase CTA at that point.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_wl_checkout_url = function_exists( 'bitmomo_pro_get_checkout_url' ) ? bitmomo_pro_get_checkout_url() : '';
if ( ! empty( $bm_wl_checkout_url ) ) {
	unset( $bm_wl_checkout_url );
	return;
}

$bm_wl_cap   = class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_SEAT_CAP : 149;
$bm_wl_batch = class_exists( 'Bitmomo_Pro_Entitlement_Service' ) ? Bitmomo_Pro_Entitlement_Service::FOUNDING_OPERATIONAL_BATCH : 25;
?>
<section class="bm-section bm-wl-home" aria-labelledby="bm-wl-home-title">
  <div class="bm-container">
    <?php if ( class_exists( 'Bitmomo_Pro_Whitelist' ) ) : ?>
      <?php Bitmomo_Pro_Whitelist::instance()->render_widget( array( 'source' => 'homepage' ) ); ?>
    <?php else : ?>
      <div class="bm-wl-teaser">
        <p class="bm-wl-teaser-eyebrow" id="bm-wl-home-title"><?php esc_html_e( 'FOUNDING MEMBERSHIP', 'bitmomo' ); ?></p>
        <p class="bm-wl-teaser-price"><?php esc_html_e( 'Rp149.000 / bulan', 'bitmomo' ); ?></p>
        <p class="bm-wl-teaser-price"><?php esc_html_e( 'Rp1.490.000 / tahun', 'bitmomo' ); ?></p>
        <p class="bm-wl-teaser-cap"><?php echo esc_html( sprintf( __( '%d Founding Members', 'bitmomo' ), $bm_wl_cap ) ); ?> &middot; <?php echo esc_html( sprintf( __( 'Batch pertama: %d anggota', 'bitmomo' ), $bm_wl_batch ) ); ?></p>
        <p class="bm-wl-teaser-sub"><?php echo esc_html( sprintf( __( 'Daftar untuk mendapat akses lebih awal saat Bitmomo Pro dibuka. Batch pertama dibatasi %d anggota.', 'bitmomo' ), $bm_wl_batch ) ); ?></p>
        <a class="bm-wl-teaser-cta" href="<?php echo esc_url( home_url( '/pro/' ) . '#bitmomo-pro' ); ?>"><?php esc_html_e( 'GABUNG WHITELIST', 'bitmomo' ); ?></a>
        <p class="bm-wl-teaser-note"><?php esc_html_e( 'Masuk whitelist tidak menjamin tempat. Membership aktif setelah pembayaran berhasil.', 'bitmomo' ); ?></p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php unset( $bm_wl_checkout_url, $bm_wl_cap, $bm_wl_batch ); ?>

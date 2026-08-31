<?php
/**
 * "Platform yang Kami Gunakan" -- homepage section (hierarchy position 5).
 *
 * Editorial, not promotional: platform name, short reason, "Baca ulasan →",
 * with a quiet referral disclosure. Wires up the existing, previously-unused
 * bitmomo_get_cta() config (inc/bitmomo-cta-config.php) instead of adding a
 * new one -- referral targets are unchanged per the brief. Collapses
 * naturally if the CTA config or its URL is ever unavailable.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bm_platform_cta = function_exists( 'bitmomo_get_cta' ) ? bitmomo_get_cta( 'btc_intelligence' ) : null;
$bm_platform_url = $bm_platform_cta && function_exists( 'bitmomo_get_cta_url' ) ? bitmomo_get_cta_url( 'btc_intelligence' ) : '';

if ( ! $bm_platform_cta || ! $bm_platform_url ) {
	unset( $bm_platform_cta, $bm_platform_url );
	return;
}
?>
<section class="bm-section bm-platform" aria-labelledby="bm-platform-title">
  <div class="bm-container">
    <h2 id="bm-platform-title">Platform yang Kami Gunakan</h2>
    <article class="bm-platform-card">
      <h3>RedotPay</h3>
      <p>Salah satu platform yang kami gunakan sehari-hari untuk transaksi dan pembayaran terkait crypto.</p>
      <a class="bm-platform-link" href="<?php echo esc_url( $bm_platform_url ); ?>" rel="<?php echo esc_attr( $bm_platform_cta['rel'] ?? 'sponsored nofollow noopener' ); ?>" target="_blank" data-bm-cta="btc_intelligence">Baca ulasan &rarr;</a>
    </article>
    <?php if ( ! empty( $bm_platform_cta['disclosure'] ) ) : ?>
    <p class="bm-platform-disclosure"><?php echo esc_html( $bm_platform_cta['disclosure'] ); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php unset( $bm_platform_cta, $bm_platform_url ); ?>

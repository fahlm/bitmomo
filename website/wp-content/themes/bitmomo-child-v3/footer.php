<?php
/** Shared site footer for Bitmomo. @package Bitmomo */
$bm_social_links = function_exists( 'bitmomo_public_social_links' ) ? bitmomo_public_social_links() : array();
$bm_terms_page   = get_page_by_path( 'syarat-layanan', OBJECT, 'page' );
$bm_terms_url    = ( $bm_terms_page && 'publish' === $bm_terms_page->post_status ) ? get_permalink( $bm_terms_page ) : '';

/*
 * Newsletter is a free retention surface, independent from the commercial
 * Founding Whitelist. Backend identity is environment-owned: define
 * BITMOMO_NEWSLETTER_FORM_ID or set the `bitmomo_newsletter_form_id` option.
 * There is deliberately no numeric fallback; missing/invalid configuration
 * fails closed and renders no decorative/broken subscription capability.
 */
$bm_newsletter_default_id = defined( 'BITMOMO_NEWSLETTER_FORM_ID' )
  ? (int) BITMOMO_NEWSLETTER_FORM_ID
  : (int) get_option( 'bitmomo_newsletter_form_id', 0 );
$bm_newsletter_form_id = max( 0, (int) apply_filters( 'bitmomo_newsletter_form_id', $bm_newsletter_default_id ) );
$bm_newsletter_available = $bm_newsletter_form_id > 0 && shortcode_exists( 'mailpoet_form' );
?>
<footer class="bm-footer">
  <div class="bm-container">
    <div class="bm-footer-grid">
      <div class="bm-footer-brand">
        <?php bitmomo_render_brand(); ?>
        <p><?php esc_html_e( 'Market intelligence BTC untuk memahami kondisi, skenario, dan rekam jejak keputusan.', 'bitmomo' ); ?></p>

        <?php if ( $bm_social_links ) : ?>
          <nav class="bm-footer-social" aria-label="<?php esc_attr_e( 'Kanal resmi Bitmomo', 'bitmomo' ); ?>">
            <strong><?php esc_html_e( 'KANAL RESMI', 'bitmomo' ); ?></strong>
            <div class="bm-footer-social__links">
              <?php foreach ( $bm_social_links as $bm_social_key => $bm_social ) : ?>
                <a data-social="<?php echo esc_attr( $bm_social_key ); ?>" href="<?php echo esc_url( $bm_social['url'] ); ?>" rel="me noopener"><?php echo esc_html( $bm_social['label'] ); ?></a>
              <?php endforeach; ?>
            </div>
          </nav>
        <?php endif; ?>
      </div>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Produk', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'PRODUK', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>"><?php esc_html_e( 'BTC Intelligence', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/btc-intelligence/#decision-ledger' ) ); ?>"><?php esc_html_e( 'Decision Ledger', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo' ); ?></a>
      </nav>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Research', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'RESEARCH', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>"><?php esc_html_e( 'Market Research', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( add_query_arg( 'focus', 'systems', home_url( '/category/riset/' ) ) ); ?>"><?php esc_html_e( 'Intelligence Systems', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/category/riset/#research-standard' ) ); ?>"><?php esc_html_e( 'Research Standard', 'bitmomo' ); ?></a>
      </nav>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Bitmomo', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'BITMOMO', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/tentang-kami/' ) ); ?>"><?php esc_html_e( 'Tentang Bitmomo', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help Center', 'bitmomo' ); ?></a>
      </nav>
    </div>

    <?php if ( $bm_newsletter_available ) : ?>
      <div id="newsletter" class="bm-footer-newsletter-anchor">
        <section class="bm-footer-newsletter" aria-labelledby="bm-footer-newsletter-title">
          <div class="bm-footer-newsletter__copy">
            <span><?php esc_html_e( 'BITMOMO BRIEF', 'bitmomo' ); ?></span>
            <h2 id="bm-footer-newsletter-title"><?php esc_html_e( 'BTC intelligence dan riset terbaru, langsung ke inbox.', 'bitmomo' ); ?></h2>
            <p><?php esc_html_e( 'Newsletter gratis dan terpisah dari Founding Whitelist. Berhenti berlangganan kapan saja.', 'bitmomo' ); ?></p>
          </div>
          <div class="bm-footer-newsletter__form">
            <?php echo do_shortcode( sprintf( '[mailpoet_form id="%d"]', $bm_newsletter_form_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
        </section>
      </div>
    <?php endif; ?>

    <div class="bm-footer-bottom">
      <div class="bm-footer-bottom__identity">
        <p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Bitmomo.</p>
        <p><?php esc_html_e( 'Market intelligence; bukan rekomendasi beli/jual atau nasihat keuangan.', 'bitmomo' ); ?></p>
      </div>

      <nav class="bm-footer-legal" aria-label="<?php esc_attr_e( 'Legal', 'bitmomo' ); ?>">
        <?php if ( $bm_terms_url ) : ?>
          <a href="<?php echo esc_url( $bm_terms_url ); ?>"><?php esc_html_e( 'Syarat Layanan', 'bitmomo' ); ?></a>
        <?php endif; ?>
        <a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'bitmomo' ); ?></a>
      </nav>
    </div>
  </div>
</footer>

<?php unset( $bm_social_links, $bm_social_key, $bm_social, $bm_terms_page, $bm_terms_url, $bm_newsletter_default_id, $bm_newsletter_form_id, $bm_newsletter_available ); ?>
<?php wp_footer(); ?>
</body>
</html>
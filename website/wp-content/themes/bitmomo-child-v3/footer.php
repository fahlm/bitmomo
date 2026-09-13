<?php
/** Shared site footer for Bitmomo. @package Bitmomo */
$bm_social_links       = function_exists( 'bitmomo_public_social_links' ) ? bitmomo_public_social_links() : array();
$bm_terms_page         = get_page_by_path( 'syarat-layanan', OBJECT, 'page' );
$bm_terms_url          = ( $bm_terms_page && 'publish' === $bm_terms_page->post_status ) ? get_permalink( $bm_terms_page ) : '';
$bm_newsletter_enabled = shortcode_exists( 'mailpoet_form' );
$bm_show_connect       = $bm_newsletter_enabled || ! empty( $bm_social_links );
?>
<footer class="bm-footer">
  <div class="bm-container">
    <div class="bm-footer-grid">
      <div class="bm-footer-brand">
        <?php bitmomo_render_brand(); ?>
        <p><?php esc_html_e( 'Market intelligence BTC untuk memahami kondisi, skenario, dan rekam jejak keputusan.', 'bitmomo' ); ?></p>
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

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Bitmomo dan legal', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'BITMOMO', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/tentang-kami/' ) ); ?>"><?php esc_html_e( 'Tentang Bitmomo', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help Center', 'bitmomo' ); ?></a>
        <?php if ( $bm_terms_url ) : ?>
          <a href="<?php echo esc_url( $bm_terms_url ); ?>"><?php esc_html_e( 'Syarat Layanan', 'bitmomo' ); ?></a>
        <?php endif; ?>
        <a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'bitmomo' ); ?></a>
      </nav>
    </div>

    <?php if ( $bm_show_connect ) : ?>
    <section class="bm-footer-connect"<?php echo $bm_newsletter_enabled ? ' id="newsletter"' : ''; ?> aria-label="<?php esc_attr_e( 'Email brief dan media sosial', 'bitmomo' ); ?>">
      <?php if ( $bm_newsletter_enabled ) : ?>
        <span id="subscribe" class="bm-footer-anchor" aria-hidden="true"></span>
        <div class="bm-footer-newsletter">
          <div class="bm-footer-connect__copy">
            <strong><?php esc_html_e( 'EMAIL BRIEF', 'bitmomo' ); ?></strong>
            <span><?php esc_html_e( 'Brief BTC dan publikasi research terbaru.', 'bitmomo' ); ?></span>
          </div>
          <div class="bm-footer-newsletter__form">
            <?php echo do_shortcode( '[mailpoet_form id="' . absint( BM_MAILPOET_FORM_ID ) . '"]' ); ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ( $bm_social_links ) : ?>
        <nav class="bm-footer-social" aria-label="<?php esc_attr_e( 'Media sosial Bitmomo', 'bitmomo' ); ?>">
          <?php foreach ( $bm_social_links as $bm_social_key => $bm_social ) : ?>
            <a data-social="<?php echo esc_attr( $bm_social_key ); ?>" href="<?php echo esc_url( $bm_social['url'] ); ?>" rel="me noopener"><?php echo esc_html( $bm_social['label'] ); ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="bm-footer-bottom">
      <p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Bitmomo.</p>
      <p><?php esc_html_e( 'Market intelligence, bukan nasihat keuangan.', 'bitmomo' ); ?></p>
    </div>
  </div>
</footer>

<?php unset( $bm_social_links, $bm_social_key, $bm_social, $bm_terms_page, $bm_terms_url, $bm_newsletter_enabled, $bm_show_connect ); ?>
<?php wp_footer(); ?>
</body>
</html>

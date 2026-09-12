<?php
/** Shared site footer for Bitmomo. @package Bitmomo */
$bm_social_links = function_exists( 'bitmomo_public_social_links' ) ? bitmomo_public_social_links() : array();
?>
<footer class="bm-footer">
  <div class="bm-container">
    <div class="bm-footer-grid">
      <div class="bm-footer-brand">
        <strong>bitmomo</strong>
        <p><?php esc_html_e( 'BTC market intelligence yang mengubah data menjadi konteks, decision support, dan accountability.', 'bitmomo' ); ?></p>
      </div>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Produk', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'PRODUK', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>"><?php esc_html_e( 'BTC Intelligence', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/pro/' ) ); ?>"><?php esc_html_e( 'Bitmomo Pro', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help Center', 'bitmomo' ); ?></a>
      </nav>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Riset', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'RISET', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>"><?php esc_html_e( 'Market Research', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( add_query_arg( 'focus', 'systems', home_url( '/category/riset/' ) ) ); ?>"><?php esc_html_e( 'Intelligence Systems', 'bitmomo' ); ?></a>
      </nav>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Tentang dan legal', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'BITMOMO', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/tentang-kami/' ) ); ?>"><?php esc_html_e( 'Tentang Kami', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'bitmomo' ); ?></a>
      </nav>
    </div>

    <section class="bm-footer-connect" id="newsletter" aria-label="<?php esc_attr_e( 'Newsletter dan media sosial', 'bitmomo' ); ?>">
      <span id="subscribe" class="bm-footer-anchor" aria-hidden="true"></span>
      <div class="bm-footer-newsletter">
        <div class="bm-footer-connect__copy">
          <strong><?php esc_html_e( 'EMAIL BRIEF', 'bitmomo' ); ?></strong>
          <span><?php esc_html_e( 'Ringkasan BTC dan riset terbaru.', 'bitmomo' ); ?></span>
        </div>
        <div class="bm-footer-newsletter__form">
          <?php if ( shortcode_exists( 'mailpoet_form' ) ) : ?>
            <?php echo do_shortcode( '[mailpoet_form id="' . absint( BM_MAILPOET_FORM_ID ) . '"]' ); ?>
          <?php else : ?>
            <span class="bm-footer-newsletter__unavailable"><?php esc_html_e( 'Subscribe email sementara tidak tersedia.', 'bitmomo' ); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <nav class="bm-footer-social" aria-label="<?php esc_attr_e( 'Media sosial Bitmomo', 'bitmomo' ); ?>">
        <?php foreach ( $bm_social_links as $bm_social_key => $bm_social ) : ?>
          <?php if ( ! empty( $bm_social['url'] ) ) : ?>
            <a data-social="<?php echo esc_attr( $bm_social_key ); ?>" href="<?php echo esc_url( $bm_social['url'] ); ?>" rel="me noopener"><?php echo esc_html( $bm_social['label'] ); ?></a>
          <?php elseif ( 'telegram' === $bm_social_key ) : ?>
            <span class="bm-footer-social__pending" data-social="telegram" aria-label="<?php esc_attr_e( 'Telegram publik belum dikonfigurasi', 'bitmomo' ); ?>"><?php esc_html_e( 'Telegram', 'bitmomo' ); ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </section>

    <div class="bm-footer-bottom">
      <p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.</p>
      <p><?php esc_html_e( 'Market intelligence, bukan nasihat keuangan.', 'bitmomo' ); ?></p>
    </div>
  </div>
</footer>

<?php unset( $bm_social_links, $bm_social_key, $bm_social ); ?>
<?php wp_footer(); ?>
</body>
</html>

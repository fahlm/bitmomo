<?php
/** Shared site footer for Bitmomo. @package Bitmomo */
$bm_social_links = function_exists( 'bitmomo_public_social_links' ) ? bitmomo_public_social_links() : array();
$bm_terms_page   = get_page_by_path( 'syarat-layanan', OBJECT, 'page' );
$bm_terms_url    = ( $bm_terms_page && 'publish' === $bm_terms_page->post_status ) ? get_permalink( $bm_terms_page ) : '';
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

<?php unset( $bm_social_links, $bm_social_key, $bm_social, $bm_terms_page, $bm_terms_url ); ?>
<?php wp_footer(); ?>
</body>
</html>

<?php
/** Shared site footer for Bitmomo. @package Bitmomo */
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
        <a href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>"><?php esc_html_e( 'Riset & Analisis', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/#ai-lab' ) ); ?>"><?php esc_html_e( 'AI Lab', 'bitmomo' ); ?></a>
      </nav>

      <nav class="bm-footer-group" aria-label="<?php esc_attr_e( 'Tentang dan legal', 'bitmomo' ); ?>">
        <strong><?php esc_html_e( 'BITMOMO', 'bitmomo' ); ?></strong>
        <a href="<?php echo esc_url( home_url( '/tentang-kami/' ) ); ?>"><?php esc_html_e( 'Tentang Kami', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/kebijakan-privasi/' ) ); ?>"><?php esc_html_e( 'Kebijakan Privasi', 'bitmomo' ); ?></a>
        <a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'bitmomo' ); ?></a>
      </nav>
    </div>

    <div class="bm-footer-bottom">
      <p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.</p>
      <p><?php esc_html_e( 'Market intelligence, bukan nasihat keuangan.', 'bitmomo' ); ?></p>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Shared site footer for the Bitmomo child theme.
 *
 * @package Bitmomo
 */
?>
<footer class="bm-footer">
  <div class="bm-container">
    <nav class="bm-footer-links" aria-label="<?php esc_attr_e('Tautan footer', 'bitmomo'); ?>">
      <a href="<?php echo esc_url(home_url('/tentang-kami/')); ?>"><?php esc_html_e('Tentang Kami', 'bitmomo'); ?></a>
      <a href="<?php echo esc_url(home_url('/kebijakan-privasi/')); ?>"><?php esc_html_e('Kebijakan Privasi', 'bitmomo'); ?></a>
      <a href="<?php echo esc_url(home_url('/disclaimer/')); ?>"><?php esc_html_e('Disclaimer', 'bitmomo'); ?></a>
    </nav>
    <p>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('Semua hak cipta dilindungi.', 'bitmomo'); ?></p>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

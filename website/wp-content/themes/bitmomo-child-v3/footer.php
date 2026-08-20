<?php
/**
 * Shared site footer for the Bitmomo child theme.
 *
 * @package Bitmomo
 */
?>
<footer class="bm-footer">
  <div class="bm-container">
    <p>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('Semua hak cipta dilindungi.', 'bitmomo'); ?></p>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>

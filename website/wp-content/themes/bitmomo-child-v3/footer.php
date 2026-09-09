<?php
/**
 * Shared site footer for the Bitmomo child theme.
 *
 * @package Bitmomo
 */

$bm_footer_x_url = trim((string) apply_filters('bitmomo_social_x_url', 'https://x.com/bitmomoid'));
$bm_footer_youtube = trim((string) apply_filters('bitmomo_social_youtube_url', 'https://www.youtube.com/@bitmomoid'));
?>
<footer class="bm-footer">
  <div class="bm-container">
    <div class="bm-footer-grid">
      <div class="bm-footer-brand">
        <span class="bm-footer-wordmark">BITMOMO</span>
        <p><?php esc_html_e('Market intelligence harian untuk BTC — regime pasar, bias, dan confidence, dengan track record yang transparan.', 'bitmomo'); ?></p>
      </div>

      <div class="bm-footer-col">
        <h3><?php esc_html_e('Product', 'bitmomo'); ?></h3>
        <ul>
          <li><a href="<?php echo esc_url(home_url('/btc-intelligence/')); ?>">BTC Intelligence</a></li>
          <li>
            <?php
            $bm_footer_riset = get_category_by_slug('riset');
            $bm_footer_riset_url = $bm_footer_riset ? get_category_link($bm_footer_riset->term_id) : home_url('/category/riset/');
            ?>
            <a href="<?php echo esc_url($bm_footer_riset_url); ?>">Riset</a>
          </li>
          <li><a href="<?php echo esc_url(home_url('/pro/')); ?>">Bitmomo Pro</a></li>
        </ul>
      </div>

      <div class="bm-footer-col">
        <h3><?php esc_html_e('Company', 'bitmomo'); ?></h3>
        <ul>
          <?php
          $bm_footer_about = get_page_by_path('tentang-kami', OBJECT, 'page');
          $bm_footer_about_url = $bm_footer_about ? get_permalink($bm_footer_about) : home_url('/tentang-kami/');
          ?>
          <li><a href="<?php echo esc_url($bm_footer_about_url); ?>"><?php esc_html_e('Tentang Kami', 'bitmomo'); ?></a></li>
          <li><a href="<?php echo esc_url(home_url('/kebijakan-privasi/')); ?>"><?php esc_html_e('Kebijakan Privasi', 'bitmomo'); ?></a></li>
          <li><a href="<?php echo esc_url(home_url('/disclaimer/')); ?>"><?php esc_html_e('Disclaimer', 'bitmomo'); ?></a></li>
        </ul>
      </div>

      <?php if ($bm_footer_x_url || $bm_footer_youtube) : ?>
        <div class="bm-footer-col">
          <h3><?php esc_html_e('Follow', 'bitmomo'); ?></h3>
          <div class="bm-footer-social">
            <?php if ($bm_footer_x_url) : ?>
              <a href="<?php echo esc_url($bm_footer_x_url); ?>" aria-label="X" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 2H22l-7.6 8.7L23 22h-6.9l-5.4-6.9L4.5 22H1.3l8.1-9.3L1 2h7l4.9 6.3L18.9 2Zm-1.2 18h1.9L7.4 4H5.4l12.3 16Z"/></svg>
              </a>
            <?php endif; ?>
            <?php if ($bm_footer_youtube) : ?>
              <a href="<?php echo esc_url($bm_footer_youtube); ?>" aria-label="YouTube" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 12s0-3.6-.5-5.3a3 3 0 0 0-2.1-2.1C18.7 4 12 4 12 4s-6.7 0-8.4.6a3 3 0 0 0-2.1 2.1C1 8.4 1 12 1 12s0 3.6.5 5.3a3 3 0 0 0 2.1 2.1C5.3 20 12 20s6.7 0 8.4-.6a3 3 0 0 0 2.1-2.1C23 15.6 23 12 23 12ZM9.8 15.5v-7l6 3.5-6 3.5Z"/></svg>
              </a>
            <?php endif; ?>
          </div>
          <div class="bm-footer-newsletter">
            <?php if (class_exists('\\Hostinger\\Reach\\Blocks\\SubscriptionFormBlock')) : ?>
              <?php \Hostinger\Reach\Blocks\SubscriptionFormBlock::render_block_html(['formId' => 'bm-footer-newsletter-form']); ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <p class="bm-footer-bottom">&copy; <?php echo esc_html(wp_date('Y')); ?> Bitmomo. <?php esc_html_e('All rights reserved.', 'bitmomo'); ?></p>
  </div>
</footer>

<?php
unset(
    $bm_footer_x_url,
    $bm_footer_youtube,
    $bm_footer_riset,
    $bm_footer_riset_url,
    $bm_footer_about,
    $bm_footer_about_url
);
?>
<?php wp_footer(); ?>
</body>
</html>

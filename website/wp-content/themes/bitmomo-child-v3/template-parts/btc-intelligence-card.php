<?php
/** Public BTC Daily Intelligence: free projection only. @package Bitmomo */

if (!defined('ABSPATH')) exit;

$bitmomo_btc = class_exists('Bitmomo_AI_Intelligence')
    ? Bitmomo_AI_Intelligence::free_projection()
    : [
        'status' => 'unavailable',
        'message' => __('Update BTC terbaru belum tersedia.', 'bitmomo'),
        'detail' => __('Sistem sedang menunggu data yang memenuhi standar kualitas Bitmomo.', 'bitmomo'),
    ];

$bitmomo_status = sanitize_key((string) ($bitmomo_btc['status'] ?? 'unavailable'));
$bitmomo_available = in_array($bitmomo_status, ['fresh', 'delayed'], true);
?>
<section class="bm-section bm-btc" aria-labelledby="bm-btc-title">
  <div class="bm-container">
    <h2 class="bm-section-title" id="bm-btc-title">BTC Daily Intelligence</h2>

    <article class="bm-btc-card bm-btc-card--<?php echo esc_attr($bitmomo_status); ?>">
      <?php if (!$bitmomo_available) : ?>
        <div class="bm-btc-unavailable" role="status">
          <span class="bm-btc-freshness is-unavailable"><?php esc_html_e('Belum tersedia', 'bitmomo'); ?></span>
          <h3><?php echo esc_html((string) ($bitmomo_btc['message'] ?? '')); ?></h3>
          <p><?php echo esc_html((string) ($bitmomo_btc['detail'] ?? '')); ?></p>
        </div>
      <?php else : ?>
        <header class="bm-btc-summary">
          <div>
            <span class="bm-btc-kicker"><?php esc_html_e('Market State', 'bitmomo'); ?></span>
            <span class="bm-btc-direction is-<?php echo esc_attr($bitmomo_btc['bias']); ?>">
              <?php echo esc_html($bitmomo_btc['market_state']); ?>
            </span>
          </div>
          <div class="bm-btc-price">
            <span><?php esc_html_e('BTC Reference', 'bitmomo'); ?></span>
            <strong>$<?php echo esc_html(number_format_i18n((float) $bitmomo_btc['price'], 0)); ?></strong>
          </div>
        </header>

        <div class="bm-btc-confidence">
          <div class="bm-btc-confidence-label">
            <span><?php esc_html_e('Confidence', 'bitmomo'); ?></span>
            <strong><?php echo esc_html((int) $bitmomo_btc['confidence']); ?>%</strong>
          </div>
          <div class="bm-btc-confidence-track" aria-hidden="true">
            <span class="bm-btc-confidence-fill" style="width: <?php echo esc_attr((int) $bitmomo_btc['confidence']); ?>%;"></span>
          </div>
        </div>

        <div class="bm-btc-driver">
          <span><?php esc_html_e('Primary Driver', 'bitmomo'); ?></span>
          <p><?php echo esc_html($bitmomo_btc['primary_driver']); ?></p>
        </div>

        <footer class="bm-btc-status">
          <time datetime="<?php echo esc_attr($bitmomo_btc['timestamp_iso']); ?>">
            <?php echo esc_html($bitmomo_btc['freshness_label']); ?>
          </time>
          <span class="bm-btc-freshness is-<?php echo esc_attr($bitmomo_status); ?>">
            <?php echo $bitmomo_status === 'fresh' ? esc_html__('Fresh', 'bitmomo') : esc_html__('Delayed', 'bitmomo'); ?>
          </span>
        </footer>
      <?php endif; ?>

      <p class="bm-btc-disclaimer">
        <?php esc_html_e('Ringkasan edukasi berbasis data pasar; bukan sinyal beli atau jual.', 'bitmomo'); ?>
      </p>
    </article>

    <aside class="bm-pro-teaser" id="bitmomo-pro" aria-labelledby="bm-pro-title">
      <span class="bm-pro-eyebrow">BITMOMO PRO</span>
      <h3 id="bm-pro-title">Jangan cuma tahu bias pasar. Ketahui apa yang bisa mengubahnya.</h3>
      <p>Bitmomo Pro membantu Anda memahami skenario di balik perubahan pasar.</p>
      <ul class="bm-pro-locks" aria-label="Fitur Bitmomo Pro">
        <li><span>Expected Range</span><span aria-hidden="true">🔒</span></li>
        <li><span>Scenario Map</span><span aria-hidden="true">🔒</span></li>
        <li><span>Thesis Invalidation</span><span aria-hidden="true">🔒</span></li>
        <li><span>What Changed</span><span aria-hidden="true">🔒</span></li>
      </ul>
      <a class="bm-pro-cta" href="<?php echo esc_url(home_url('/pro/')); ?>">Lihat Bitmomo Pro</a>
    </aside>
  </div>
</section>
<?php unset($bitmomo_btc, $bitmomo_status, $bitmomo_available); ?>

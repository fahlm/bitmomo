<?php
/**
 * Canonical About content for Bitmomo.
 *
 * The public About page is code-owned so stale WordPress/Elementor copy cannot
 * silently reposition Bitmomo as a generic crypto/AI media publisher.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_about_support_email = sanitize_email( (string) apply_filters( 'bitmomo_pro_support_email', get_option( 'admin_email' ) ) );
if ( ! is_email( $bm_about_support_email ) ) $bm_about_support_email = '';
?>
<section class="bm-about-authority" aria-labelledby="bm-about-authority-title">
  <p class="bm-about-authority__eyebrow">RESEARCH &amp; INTELLIGENCE</p>
  <h2 id="bm-about-authority-title">Disiplin riset di balik produk market intelligence.</h2>
  <p class="bm-about-authority__lead">Bitmomo adalah <strong>platform Bitcoin market intelligence &amp; research</strong>. Produk kami berfokus membantu memahami kondisi BTC dan perubahan yang relevan; program research yang mendukungnya juga mempelajari digital-asset markets dan intelligence systems agar evidence dapat ditelusuri, diuji, dan diperbaiki.</p>

  <div class="bm-about-authority__grid" aria-label="Fokus Bitmomo">
    <article>
      <span>01 · MARKETS</span>
      <h3>Crypto Market Research</h3>
      <p>Bitcoin, market structure, derivatives positioning, liquidity, volatility, macro, cycle behavior, dan fundamental drivers.</p>
    </article>
    <article>
      <span>02 · SYSTEMS</span>
      <h3>Intelligence Systems Research</h3>
      <p>Agent systems, evaluation, provenance, reliability, quality gates, dan arsitektur yang mengurangi ketergantungan pada satu black box.</p>
    </article>
    <article>
      <span>03 · PRODUCT</span>
      <h3>Decision Intelligence</h3>
      <p>Compression → context → thesis → monitoring → accountability. Research dibuat berguna tanpa menyamarkan uncertainty atau batas metodologi.</p>
    </article>
  </div>
</section>

<section class="bm-about-principles" aria-labelledby="bm-about-principles-title">
  <header>
    <p class="bm-about-authority__eyebrow">RESEARCH STANDARD</p>
    <h2 id="bm-about-principles-title">Evidence first. Claims have boundaries.</h2>
    <p>Bitmomo bukan portal berita kripto dan bukan lapisan AI yang menghasilkan narasi sebanyak mungkin. Kami membangun intelligence yang harus tetap dapat dipertanggungjawabkan ketika data, model, atau kondisi pasar berubah.</p>
  </header>

  <div class="bm-about-principles__grid">
    <article><strong>Evidence before narrative</strong><span>Claim dibatasi oleh data yang benar-benar tersedia dan sumbernya harus dapat ditelusuri.</span></article>
    <article><strong>Context over noise</strong><span>Angka dibaca dalam struktur dan kondisi pasar, bukan diperlakukan sebagai sinyal yang berdiri sendiri.</span></article>
    <article><strong>Thesis + invalidation</strong><span>Analisis harus menjelaskan apa yang mendukung pandangan dan apa yang membuat pandangan tersebut berubah.</span></article>
    <article><strong>Accountability</strong><span>Insight yang dapat diuji dicatat sebelum outcome diketahui dan dievaluasi terhadap hasil aktual.</span></article>
  </div>
</section>

<section class="bm-about-product" aria-labelledby="bm-about-product-title">
  <div>
    <p class="bm-about-authority__eyebrow">FROM RESEARCH TO PRODUCT</p>
    <h2 id="bm-about-product-title">Gratis menjelaskan apa yang terjadi sekarang. Pro berfokus pada apa yang perlu dipantau berikutnya.</h2>
    <p>BTC Intelligence merangkum pembacaan BTC saat ini beserta konteks dan track record-nya. Bitmomo Pro menambahkan scenario thinking, invalidation, monitoring, dan perubahan penting yang perlu diperhatikan.</p>
    <p class="bm-about-product__boundary">Bitmomo menyediakan research dan decision support — bukan perintah transaksi, bukan sinyal beli/jual, dan bukan nasihat keuangan personal.</p>
  </div>
  <div class="bm-about-product__actions">
    <a class="bm-about-button bm-about-button--primary" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Lihat BTC Intelligence</a>
    <a class="bm-about-button" href="<?php echo esc_url( home_url( '/#founding-whitelist' ) ); ?>">Gabung Founding Whitelist</a>
    <a class="bm-about-text-link" href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>">Buka Bitmomo Research →</a>
  </div>
</section>

<section class="bm-about-contact" aria-labelledby="bm-about-contact-title">
  <h2 id="bm-about-contact-title">Kontak</h2>
  <?php if ( $bm_about_support_email ) : ?>
    <p>Untuk pertanyaan, feedback, koreksi research, atau support, hubungi <a href="mailto:<?php echo esc_attr( $bm_about_support_email ); ?>"><?php echo esc_html( $bm_about_support_email ); ?></a>.</p>
  <?php else : ?>
    <p>Untuk pertanyaan, feedback, koreksi research, atau support, gunakan kanal resmi yang tercantum di <a href="<?php echo esc_url( home_url( '/help/' ) ); ?>">Help Center</a>.</p>
  <?php endif; ?>
  <p><a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>">Baca Disclaimer →</a></p>
</section>
<?php unset( $bm_about_support_email ); ?>
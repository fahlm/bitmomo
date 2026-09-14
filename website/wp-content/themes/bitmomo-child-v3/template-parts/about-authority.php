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
  <h2 id="bm-about-authority-title">Dari data pasar menjadi tesis yang dapat diuji.</h2>
  <p class="bm-about-authority__lead">Bitmomo adalah <strong>platform market intelligence dan riset Bitcoin</strong>. Kami menghubungkan data pasar, konteks, tesis, kondisi invalidasi, dan evaluasi hasil agar perubahan BTC dapat dipahami tanpa harus merangkai puluhan sumber secara manual.</p>

  <div class="bm-about-authority__grid" aria-label="Output Bitmomo">
    <article>
      <span>01 · CURRENT VIEW</span>
      <h3>BTC Intelligence</h3>
      <p>Ringkasan kondisi BTC saat ini, perubahan material, maknanya, dan satu konteks yang layak dipantau — lengkap dengan sumber dan waktu analisis.</p>
    </article>
    <article>
      <span>02 · RESEARCH</span>
      <h3>Bitmomo Research</h3>
      <p>Publikasi yang menguji struktur pasar, derivatives, likuiditas, volatilitas, makro, capital flows, serta sistem intelligence yang digunakan untuk membaca BTC.</p>
    </article>
    <article>
      <span>03 · DECISION SUPPORT</span>
      <h3>Bitmomo Pro</h3>
      <p>Monitoring lengkap, Expected Range, Scenario Map, dan invalidasi tesis untuk membantu menavigasi apa yang terjadi berikutnya dan kapan pembacaan pasar perlu dievaluasi ulang.</p>
    </article>
  </div>
</section>

<section class="bm-about-principles" aria-labelledby="bm-about-principles-title">
  <header>
    <p class="bm-about-authority__eyebrow">RESEARCH STANDARD</p>
    <h2 id="bm-about-principles-title">Setiap tesis harus dapat diuji.</h2>
    <p>Riset Bitmomo menghubungkan bukti, tesis, kondisi invalidasi, dan evaluasi hasil dalam satu proses yang dapat ditelusuri. Kesimpulan tidak berhenti pada narasi; setiap klaim harus memiliki dasar dan batas yang jelas.</p>
  </header>

  <div class="bm-about-principles__grid">
    <article><strong>Bukti sebelum narasi</strong><span>Kesimpulan dibatasi oleh data yang tersedia dan sumbernya harus dapat ditelusuri.</span></article>
    <article><strong>Konteks, bukan noise</strong><span>Data dibaca dalam struktur dan kondisi pasar, bukan sebagai sinyal yang berdiri sendiri.</span></article>
    <article><strong>Tesis + invalidasi</strong><span>Analisis menjelaskan apa yang mendukung tesis dan kondisi yang membuatnya perlu dievaluasi ulang.</span></article>
    <article><strong>Evaluasi hasil</strong><span>Analisis yang dapat diuji dicatat sebelum hasil diketahui lalu dibandingkan dengan outcome aktual.</span></article>
  </div>
</section>

<section class="bm-about-product" aria-labelledby="bm-about-product-title">
  <div>
    <p class="bm-about-authority__eyebrow">FROM RESEARCH TO PRODUCT</p>
    <h2 id="bm-about-product-title">Gratis membantu memahami sekarang. Pro membantu menavigasi berikutnya.</h2>
    <p>BTC Intelligence merangkum kondisi BTC, perubahan material, mengapa perubahan itu penting, dan satu konteks pantauan. Bitmomo Pro memperluasnya dengan monitoring lengkap, Expected Range, Scenario Map, dan kondisi invalidasi tesis.</p>
    <p class="bm-about-product__boundary">Decision Ledger memperlihatkan apa yang Bitmomo katakan sebelumnya dan apa yang benar-benar terjadi. Bitmomo tetap merupakan alat bantu analisis, bukan perintah transaksi atau nasihat keuangan personal.</p>
  </div>
  <div class="bm-about-product__actions">
    <a class="bm-about-button" href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Lihat BTC Intelligence</a>
    <a class="bm-about-button bm-about-button--primary" href="<?php echo esc_url( home_url( '/#founding-whitelist' ) ); ?>">Gabung Founding Whitelist</a>
    <a class="bm-about-text-link" href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>">Buka Bitmomo Research →</a>
  </div>
</section>

<section class="bm-about-contact" aria-labelledby="bm-about-contact-title">
  <h2 id="bm-about-contact-title">Kontak</h2>
  <?php if ( $bm_about_support_email ) : ?>
    <p>Untuk pertanyaan, masukan, koreksi riset, atau bantuan, hubungi <a href="mailto:<?php echo esc_attr( $bm_about_support_email ); ?>"><?php echo esc_html( $bm_about_support_email ); ?></a>.</p>
  <?php else : ?>
    <p>Untuk pertanyaan, masukan, koreksi riset, atau bantuan, gunakan kanal resmi yang tercantum di <a href="<?php echo esc_url( home_url( '/help/' ) ); ?>">Help Center</a>.</p>
  <?php endif; ?>
  <p><a href="<?php echo esc_url( home_url( '/disclaimer/' ) ); ?>">Baca Disclaimer →</a></p>
</section>
<?php unset( $bm_about_support_email ); ?>
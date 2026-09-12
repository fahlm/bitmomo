<?php
/**
 * About — canonical institutional positioning for Bitmomo.
 *
 * Deliberately avoids unverified founder/performance claims. This page defines
 * what Bitmomo works on, how it approaches research, and how research maps to
 * public products.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<main id="primary" class="bm-about">
  <section class="bm-about-hero">
    <div class="bm-container bm-about-hero__grid">
      <div>
        <span class="bm-eyebrow">ABOUT BITMOMO</span>
        <h1>Market understanding first. Intelligence systems built to be accountable.</h1>
      </div>
      <div class="bm-about-hero__copy">
        <p>Bitmomo adalah research &amp; intelligence platform yang bekerja di persimpangan <strong>crypto markets</strong> dan <strong>AI systems</strong>.</p>
        <p>Kami menggunakan research untuk memahami apa yang benar-benar berubah di pasar, lalu membangun sistem yang mengubah evidence tersebut menjadi intelligence yang lebih ringkas, konsisten, dan dapat dievaluasi.</p>
      </div>
    </div>
  </section>

  <section class="bm-section bm-about-domains" aria-labelledby="bm-about-domains-title">
    <div class="bm-container">
      <header class="bm-about-section-head">
        <span class="bm-eyebrow">TWO RESEARCH DOMAINS</span>
        <h2 id="bm-about-domains-title">Satu tujuan. Dua disiplin yang saling memperkuat.</h2>
      </header>
      <div class="bm-about-domain-grid">
        <article class="bm-about-domain">
          <span>01 · MARKETS</span>
          <h3>Crypto Market Research</h3>
          <p>Bitcoin, market structure, derivatives positioning, liquidity, macro, volatility, cycle behavior, dan fundamental drivers.</p>
          <p class="bm-about-domain__purpose">Tujuannya bukan menambah indikator, tetapi memahami konteks di balik pergerakan harga.</p>
        </article>
        <article class="bm-about-domain">
          <span>02 · SYSTEMS</span>
          <h3>AI Systems Research</h3>
          <p>Agent systems, evaluation, provenance, decentralized AI, reliability, dan architecture untuk intelligence systems.</p>
          <p class="bm-about-domain__purpose">Tujuannya bukan memakai lebih banyak model, tetapi membuat output yang lebih dapat dipercaya dan dipertanggungjawabkan.</p>
        </article>
      </div>
    </div>
  </section>

  <section class="bm-section bm-about-method" aria-labelledby="bm-about-method-title">
    <div class="bm-container bm-about-method__grid">
      <header>
        <span class="bm-eyebrow">HOW WE WORK</span>
        <h2 id="bm-about-method-title">Evidence sebelum narrative.</h2>
        <p>Research yang terlihat meyakinkan tetapi tidak punya provenance, batas, atau cara dievaluasi tetaplah lemah.</p>
      </header>
      <ol class="bm-about-method__steps">
        <li><strong>Observe</strong><span>Mulai dari data dan kondisi yang benar-benar tersedia.</span></li>
        <li><strong>Contextualize</strong><span>Baca evidence dalam struktur, regime, dan hubungan antar-variabel.</span></li>
        <li><strong>Form a thesis</strong><span>Nyatakan interpretasi beserta kondisi yang dapat membatalkannya.</span></li>
        <li><strong>Evaluate</strong><span>Bandingkan dengan hasil aktual dan perbaiki proses ketika evidence berubah.</span></li>
      </ol>
    </div>
  </section>

  <section class="bm-section bm-about-principles" aria-labelledby="bm-about-principles-title">
    <div class="bm-container">
      <header class="bm-about-section-head">
        <span class="bm-eyebrow">OPERATING PRINCIPLES</span>
        <h2 id="bm-about-principles-title">Kepercayaan harus dibangun di dalam sistem.</h2>
      </header>
      <div class="bm-about-principles__grid">
        <article><strong>Traceable</strong><p>Sumber, waktu observasi, dan kualitas data harus dapat ditelusuri sejauh sumber memungkinkan.</p></article>
        <article><strong>Fail closed</strong><p>Ketika data penting stale, invalid, atau hilang, sistem tidak boleh mengarang kepastian.</p></article>
        <article><strong>Separation of concerns</strong><p>Data, interpretation, thesis, dan presentation tidak boleh bercampur sampai source-of-truth menjadi ambigu.</p></article>
        <article><strong>Accountable</strong><p>Insight yang material harus dapat dicatat dan dibandingkan dengan apa yang benar-benar terjadi.</p></article>
      </div>
    </div>
  </section>

  <section class="bm-section bm-about-products" aria-labelledby="bm-about-products-title">
    <div class="bm-container">
      <header class="bm-about-section-head">
        <span class="bm-eyebrow">FROM RESEARCH TO PRODUCT</span>
        <h2 id="bm-about-products-title">Research bukan dekorasi. Ia harus menghasilkan utility.</h2>
      </header>
      <div class="bm-about-product-grid">
        <article>
          <small>WHAT IS HAPPENING NOW</small>
          <h3>BTC Intelligence</h3>
          <p>Pembacaan ringkas kondisi BTC saat ini, historical context, provenance, dan evaluasi publik.</p>
          <a href="<?php echo esc_url( home_url( '/btc-intelligence/' ) ); ?>">Lihat BTC Intelligence &rarr;</a>
        </article>
        <article>
          <small>WHAT TO WATCH NEXT</small>
          <h3>Bitmomo Pro</h3>
          <p>Layer decision-support untuk scenario, monitoring, thesis change, dan accountability yang berkembang dari fondasi intelligence yang sama.</p>
          <a href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">Lihat Bitmomo Pro &rarr;</a>
        </article>
        <article>
          <small>WHY WE BELIEVE IT</small>
          <h3>Bitmomo Research</h3>
          <p>Market research dan AI systems research yang memperlihatkan cara Bitmomo membangun pemahaman, bukan hanya hasil akhirnya.</p>
          <?php $bm_riset = get_category_by_slug( 'riset' ); ?>
          <a href="<?php echo esc_url( $bm_riset ? get_category_link( $bm_riset->term_id ) : home_url( '/category/riset/' ) ); ?>">Jelajahi Research &rarr;</a>
        </article>
      </div>
    </div>
  </section>

  <section class="bm-about-boundary">
    <div class="bm-container">
      <div class="bm-about-boundary__inner">
        <strong>Bitmomo membantu memahami informasi dan konteks pasar.</strong>
        <p>Bitmomo bukan sinyal beli/jual dan bukan nasihat keuangan. Ketidakpastian harus tetap terlihat ketika evidence memang belum cukup kuat.</p>
      </div>
    </div>
  </section>
</main>
<?php unset( $bm_riset ); get_footer(); ?>

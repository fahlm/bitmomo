<?php
/** Shared site footer for Bitmomo. @package Bitmomo */

$bm_social_links = function_exists( 'bitmomo_public_social_links' ) ? bitmomo_public_social_links() : array();
$bm_terms_page   = get_page_by_path( 'syarat-layanan', OBJECT, 'page' );
$bm_terms_url    = ( $bm_terms_page && 'publish' === $bm_terms_page->post_status ) ? get_permalink( $bm_terms_page ) : '';

/*
 * Footer information architecture is source-owned and deterministic. Keep
 * visitor-facing navigation here instead of depending on mutable WordPress menu
 * state so release review can validate every public destination.
 */
$bm_footer_groups = array(
  array(
    'label' => __( 'PRODUK', 'bitmomo' ),
    'aria'  => __( 'Produk', 'bitmomo' ),
    'links' => array(
      array(
        'label' => __( 'BTC Intelligence', 'bitmomo' ),
        'url'   => home_url( '/btc-intelligence/' ),
      ),
      array(
        'label' => __( 'Bitmomo Pro', 'bitmomo' ),
        'url'   => home_url( '/pro/' ),
      ),
      array(
        'label' => __( 'Track Record', 'bitmomo' ),
        'url'   => home_url( '/btc-intelligence/#bm-bi-track-title' ),
      ),
    ),
  ),
  array(
    'label' => __( 'RESEARCH', 'bitmomo' ),
    'aria'  => __( 'Research', 'bitmomo' ),
    'links' => array(
      array(
        'label' => __( 'Market Research', 'bitmomo' ),
        'url'   => home_url( '/category/riset/' ),
      ),
      array(
        'label' => __( 'AI Research', 'bitmomo' ),
        'url'   => add_query_arg( 'focus', 'systems', home_url( '/category/riset/' ) ),
      ),
      array(
        'label' => __( 'Research Standard', 'bitmomo' ),
        'url'   => home_url( '/category/riset/#research-standard' ),
      ),
    ),
  ),
  array(
    'label' => __( 'BITMOMO', 'bitmomo' ),
    'aria'  => __( 'Bitmomo', 'bitmomo' ),
    'links' => array(
      array(
        'label' => __( 'Tentang Bitmomo', 'bitmomo' ),
        'url'   => home_url( '/tentang-kami/' ),
      ),
      array(
        'label' => __( 'Help Center', 'bitmomo' ),
        'url'   => home_url( '/help/' ),
      ),
    ),
  ),
);

$bm_footer_principles = array(
  array(
    'label' => __( 'EVIDENCE FIRST', 'bitmomo' ),
    'text'  => __( 'Konteks pasar dibangun dari data yang memenuhi standar kualitas.', 'bitmomo' ),
  ),
  array(
    'label' => __( 'TESTABLE RESEARCH', 'bitmomo' ),
    'text'  => __( 'Sistem AI diperlakukan sebagai sistem yang harus diuji, bukan sekadar klaim.', 'bitmomo' ),
  ),
  array(
    'label' => __( 'FAIL CLOSED', 'bitmomo' ),
    'text'  => __( 'Data yang terlambat atau tidak valid tidak ditampilkan sebagai intelligence terkini.', 'bitmomo' ),
  ),
);

/*
 * Newsletter is a free retention surface, independent from the commercial
 * Founding Whitelist. Backend identity is environment-owned: define
 * BITMOMO_NEWSLETTER_FORM_ID or set the `bitmomo_newsletter_form_id` option.
 * There is deliberately no numeric fallback; missing/invalid configuration
 * fails closed and the public copy must fail closed with it.
 */
$bm_newsletter_default_id = defined( 'BITMOMO_NEWSLETTER_FORM_ID' )
  ? (int) BITMOMO_NEWSLETTER_FORM_ID
  : (int) get_option( 'bitmomo_newsletter_form_id', 0 );
$bm_newsletter_form_id = max( 0, (int) apply_filters( 'bitmomo_newsletter_form_id', $bm_newsletter_default_id ) );
$bm_newsletter_available = $bm_newsletter_form_id > 0 && shortcode_exists( 'mailpoet_form' );
?>
<footer class="bm-footer">
  <div class="bm-container">
    <section class="bm-footer-principles" aria-label="<?php esc_attr_e( 'Prinsip operasional Bitmomo', 'bitmomo' ); ?>">
      <?php foreach ( $bm_footer_principles as $bm_footer_principle ) : ?>
        <div class="bm-footer-principle">
          <span><?php echo esc_html( $bm_footer_principle['label'] ); ?></span>
          <p><?php echo esc_html( $bm_footer_principle['text'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </section>

    <div class="bm-footer-grid">
      <div class="bm-footer-brand">
        <?php bitmomo_render_brand(); ?>
        <p><?php esc_html_e( 'Market intelligence BTC dan riset AI untuk memahami kondisi pasar, menguji tesis, dan menilai rekam jejak keputusan.', 'bitmomo' ); ?></p>

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

      <?php foreach ( $bm_footer_groups as $bm_footer_group ) : ?>
        <nav class="bm-footer-group" aria-label="<?php echo esc_attr( $bm_footer_group['aria'] ); ?>">
          <strong><?php echo esc_html( $bm_footer_group['label'] ); ?></strong>
          <?php foreach ( $bm_footer_group['links'] as $bm_footer_link ) : ?>
            <a href="<?php echo esc_url( $bm_footer_link['url'] ); ?>"><?php echo esc_html( $bm_footer_link['label'] ); ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endforeach; ?>
    </div>

    <div id="newsletter" class="bm-footer-newsletter-anchor">
      <?php if ( $bm_newsletter_available ) : ?>
        <section class="bm-footer-newsletter" aria-labelledby="bm-footer-newsletter-title">
          <div class="bm-footer-newsletter__copy">
            <span><?php esc_html_e( 'BITMOMO BRIEF · GRATIS', 'bitmomo' ); ?></span>
            <h2 id="bm-footer-newsletter-title"><?php esc_html_e( 'BTC intelligence dan riset terbaru, tanpa noise yang tidak perlu.', 'bitmomo' ); ?></h2>
            <p><?php esc_html_e( 'Newsletter gratis dan terpisah dari Founding Whitelist. Berhenti berlangganan kapan saja.', 'bitmomo' ); ?></p>
          </div>
          <div class="bm-footer-newsletter__form">
            <?php echo do_shortcode( sprintf( '[mailpoet_form id="%d"]', $bm_newsletter_form_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </div>
        </section>
      <?php else : ?>
        <section class="bm-footer-newsletter is-paused" aria-labelledby="bm-footer-research-title">
          <div class="bm-footer-newsletter__copy">
            <span><?php esc_html_e( 'BITMOMO RESEARCH', 'bitmomo' ); ?></span>
            <h2 id="bm-footer-research-title"><?php esc_html_e( 'Baca riset pasar dan AI terbaru dari Bitmomo.', 'bitmomo' ); ?></h2>
            <p><?php esc_html_e( 'Publikasi yang tersedia dapat dibaca langsung tanpa pendaftaran email.', 'bitmomo' ); ?></p>
          </div>
          <div class="bm-footer-newsletter__standby">
            <a href="<?php echo esc_url( home_url( '/category/riset/' ) ); ?>"><?php esc_html_e( 'Baca Research', 'bitmomo' ); ?></a>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <div class="bm-footer-bottom">
      <div class="bm-footer-bottom__identity">
        <p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Bitmomo.</p>
        <p><?php esc_html_e( 'Untuk riset dan edukasi; bukan rekomendasi beli/jual atau nasihat keuangan.', 'bitmomo' ); ?></p>
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

<?php
unset(
  $bm_social_links,
  $bm_social_key,
  $bm_social,
  $bm_terms_page,
  $bm_terms_url,
  $bm_footer_groups,
  $bm_footer_group,
  $bm_footer_link,
  $bm_footer_principles,
  $bm_footer_principle,
  $bm_newsletter_default_id,
  $bm_newsletter_form_id,
  $bm_newsletter_available
);
?>
<?php wp_footer(); ?>
</body>
</html>

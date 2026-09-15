<?php
/**
 * Shared archive/listing surface.
 *
 * Callers may set $bm_archive_eyebrow, $bm_archive_title and
 * $bm_archive_description before including this part.
 *
 * @package Bitmomo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bm_archive_eyebrow = isset( $args['eyebrow'] ) ? (string) $args['eyebrow'] : ( isset( $bm_archive_eyebrow ) ? (string) $bm_archive_eyebrow : __( 'BITMOMO RESEARCH', 'bitmomo' ) );
$bm_archive_title = isset( $args['title'] ) ? (string) $args['title'] : ( isset( $bm_archive_title ) ? (string) $bm_archive_title : __( 'Riset & Analisis', 'bitmomo' ) );
$bm_archive_description = isset( $args['description'] ) ? (string) $args['description'] : ( isset( $bm_archive_description ) ? (string) $bm_archive_description : '' );
?>
<section class="bm-archive-v2" aria-labelledby="bm-archive-title">
  <div class="bm-container">
    <header class="bm-public-head bm-public-head--archive">
      <p class="bm-public-eyebrow"><?php echo esc_html( $bm_archive_eyebrow ); ?></p>
      <h1 class="bm-public-title" id="bm-archive-title"><?php echo esc_html( $bm_archive_title ); ?></h1>
      <?php if ( '' !== trim( wp_strip_all_tags( $bm_archive_description ) ) ) : ?>
        <div class="bm-public-lead"><?php echo wp_kses_post( $bm_archive_description ); ?></div>
      <?php endif; ?>
    </header>

    <?php if ( have_posts() ) : ?>
      <div class="bm-cards bm-cards--research">
        <?php $bm_card_index = 0; while ( have_posts() ) : the_post(); $bm_card_index++; ?>
          <?php
          get_template_part(
            'template-parts/content',
            'card',
            array(
              'heading_level' => 'h2',
              'image_size'    => 'bm-card',
              'excerpt_words' => 24,
              'eager'         => 1 === $bm_card_index,
            )
          );
          ?>
        <?php endwhile; ?>
      </div>

      <nav class="bm-pagination bm-pagination--v2" aria-label="<?php esc_attr_e( 'Pagination', 'bitmomo' ); ?>">
        <?php
        echo wp_kses_post(
          paginate_links(
            array(
              'prev_text' => '← ' . __( 'Sebelumnya', 'bitmomo' ),
              'next_text' => __( 'Berikutnya', 'bitmomo' ) . ' →',
            )
          )
        );
        ?>
      </nav>
    <?php else : ?>
      <div class="bm-public-empty">
        <h2><?php esc_html_e( 'Belum ada publikasi di sini.', 'bitmomo' ); ?></h2>
        <p><?php esc_html_e( 'Coba kembali ke halaman Riset atau gunakan navigasi utama untuk menjelajahi Bitmomo.', 'bitmomo' ); ?></p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php unset( $bm_archive_eyebrow, $bm_archive_title, $bm_archive_description, $bm_card_index ); ?>

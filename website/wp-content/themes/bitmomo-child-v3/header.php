<?php
/**
 * Shared site header for the Bitmomo child theme.
 *
 * @package Bitmomo
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="bm-header">
  <div class="bm-container">
    <?php bitmomo_render_brand(); ?>
    <?php bitmomo_render_menu_toggle(); ?>

    <nav class="bm-nav" id="bm-nav" aria-label="<?php esc_attr_e('Primary', 'bitmomo'); ?>">
      <?php
      $bm_riset_term = get_category_by_slug( 'riset' );
      $bm_riset_url = $bm_riset_term ? get_category_link( $bm_riset_term->term_id ) : home_url( '/category/riset/' );
      $bm_about_page = get_page_by_path( 'tentang-kami', OBJECT, 'page' );
      $bm_about_url = $bm_about_page ? get_permalink( $bm_about_page ) : home_url( '/tentang-kami/' );
      $bm_account_page = get_page_by_path( 'pro/account', OBJECT, 'page' );
      $bm_account_url = $bm_account_page ? get_permalink( $bm_account_page ) : home_url( '/pro/account/' );
      ?>
      <div class="bm-nav-groups">
        <ul class="bm-nav-list bm-nav-list--content">
          <li><a href="<?php echo esc_url( home_url( '/#bm-btc-title' ) ); ?>">BTC Intelligence</a></li>
          <li><a href="<?php echo esc_url( $bm_riset_url ); ?>">Riset</a></li>
          <li><a href="<?php echo esc_url( $bm_about_url ); ?>">Tentang</a></li>
        </ul>
        <ul class="bm-nav-list bm-nav-list--actions">
          <li><a class="bm-nav-login" href="<?php echo esc_url( $bm_account_url ); ?>">Masuk</a></li>
          <li><a class="bm-nav-pro" href="<?php echo esc_url( home_url( '/pro/' ) ); ?>">BITMOMO PRO</a></li>
        </ul>
      </div>
    </nav>
  </div>
</header>
<?php unset( $bm_riset_term, $bm_riset_url, $bm_about_page, $bm_about_url, $bm_account_page, $bm_account_url ); ?>

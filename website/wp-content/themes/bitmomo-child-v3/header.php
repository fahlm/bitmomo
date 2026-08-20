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
      wp_nav_menu([
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => 'bm-nav-list',
        'fallback_cb'    => false,
        'depth'          => 1,
      ]);
      ?>
    </nav>

    <a class="bm-cta js-open-subscribe" href="#subscribe"><?php esc_html_e('SUBSCRIBE', 'bitmomo'); ?></a>
  </div>
</header>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c1c2a">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e('Skip to content', 'bitmomo-speed'); ?></a>

<header class="bm-header" role="banner">
    <div class="bm-container">
        <div class="bm-header-inner">
            
            <!-- Logo -->
            <a href="<?php echo esc_url(home_url('/')); ?>" class="bm-logo" rel="home">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <span class="bm-logo-icon">🔷</span>
                    <?php bloginfo('name'); ?>
                <?php endif; ?>
            </a>

            <!-- Navigation -->
            <nav class="bm-nav-wrapper" role="navigation" aria-label="<?php esc_attr_e('Primary Navigation', 'bitmomo-speed'); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'menu_class' => 'bm-nav',
                    'container' => false,
                    'fallback_cb' => 'bm_fallback_menu',
                    'walker' => new BM_Nav_Walker(),
                    'depth' => 1,
                ]);
                ?>
                
                <!-- CTA Button -->
                <a href="#subscribe" class="bm-cta-btn js-newsletter-trigger">
                    Subscribe
                </a>
            </nav>

        </div>
    </div>
</header>

<main id="main" class="bm-main" role="main">
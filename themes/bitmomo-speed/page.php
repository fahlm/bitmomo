<?php get_header(); ?>

<?php while (have_posts()) : the_post(); ?>

<article <?php post_class('bm-article'); ?>>
    
    <!-- Page Header -->
    <header class="bm-article-header">
        <h1 class="bm-article-title"><?php the_title(); ?></h1>
        
        <?php if (has_excerpt()) : ?>
            <div class="bm-page-excerpt">
                <?php the_excerpt(); ?>
            </div>
        <?php endif; ?>
    </header>
    
    <!-- Featured Image -->
    <?php if (has_post_thumbnail()) : ?>
        <div class="bm-article-featured">
            <?php 
            echo get_the_post_thumbnail(get_the_ID(), 'large', [
                'loading' => 'eager',
                'fetchpriority' => 'high',
                'decoding' => 'async'
            ]); 
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Page Content -->
    <div class="bm-article-content">
        <?php the_content(); ?>
        
        <?php
        wp_link_pages([
            'before' => '<div class="bm-page-links">Pages: ',
            'after'  => '</div>',
        ]);
        ?>
    </div>
    
</article>

<?php endwhile; ?>

<style>
.bm-page-excerpt {
    font-size: 18px;
    color: #c7d4db;
    text-align: center;
    margin-top: 16px;
    font-style: italic;
}

.bm-page-links {
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
}

.bm-page-links a {
    display: inline-block;
    padding: 8px 16px;
    margin: 0 4px;
    background: rgba(38, 208, 198, 0.1);
    color: #26d0c6;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.bm-page-links a:hover {
    background: rgba(38, 208, 198, 0.2);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .bm-page-excerpt {
        font-size: 16px;
    }
}
</style>

<?php get_footer(); ?>
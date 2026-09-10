<?php get_header(); ?>

<?php while (have_posts()) : the_post(); ?>

<article <?php post_class('bm-article'); ?>>
    
    <!-- Article Header -->
    <header class="bm-article-header">
        <h1 class="bm-article-title"><?php the_title(); ?></h1>
        
        <div class="bm-article-meta">
            <time datetime="<?php echo get_the_date('c'); ?>" class="bm-meta-date">
                <?php echo get_the_date('F j, Y'); ?>
            </time>
            
            <span class="bm-meta-separator">•</span>
            
            <span class="bm-meta-reading-time">
                <?php echo bm_reading_time(); ?> min read
            </span>
            
            <?php if (get_the_category()) : ?>
                <span class="bm-meta-separator">•</span>
                <span class="bm-meta-category">
                    <?php 
                    $categories = get_the_category();
                    echo '<a href="' . esc_url(get_category_link($categories[0]->term_id)) . '">' . esc_html($categories[0]->name) . '</a>';
                    ?>
                </span>
            <?php endif; ?>
        </div>
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
    
    <!-- Article Content -->
    <div class="bm-article-content">
        <?php the_content(); ?>
    </div>
    
    <!-- Article Footer -->
    <footer class="bm-article-footer">
        
        <!-- Tags -->
        <?php if (has_tag()) : ?>
            <div class="bm-article-tags">
                <span class="bm-tags-label">Tags:</span>
                <?php 
                $tags = get_the_tags();
                $tag_links = [];
                foreach ($tags as $tag) {
                    $tag_links[] = '<a href="' . esc_url(get_tag_link($tag->term_id)) . '" class="bm-tag">' . esc_html($tag->name) . '</a>';
                }
                echo implode('', $tag_links);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Newsletter CTA -->
        <div class="bm-cta-section">
            <h3 class="bm-cta-title">Enjoyed this article?</h3>
            <p class="bm-cta-text">Subscribe to get more insights like this delivered to your inbox weekly.</p>
            <a href="#subscribe" class="bm-btn js-newsletter-trigger">Subscribe to Newsletter</a>
        </div>
        
        <!-- Disclaimer -->
        <div class="bm-disclaimer">
            <p>
                <em>Informasi ini hanya untuk tujuan edukasi dan bukan merupakan saran investasi. 
                Risiko investasi aset kripto sangat tinggi. Selalu lakukan riset Anda sendiri (DYOR) sebelum membuat keputusan investasi.</em>
            </p>
        </div>
        
    </footer>
    
</article>

<!-- Related Posts -->
<section class="bm-section bm-related-posts">
    <div class="bm-container">
        <?php
        $related_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 3,
            'post__not_in' => [get_the_ID()],
            'category__in' => wp_get_post_categories(get_the_ID()),
            'orderby' => 'rand',
            'meta_query' => [
                [
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        if ($related_posts) :
        ?>
            <h2 class="bm-section-title">Related Articles</h2>
            <div class="bm-cards">
                <?php foreach ($related_posts as $post) : setup_postdata($post); ?>
                    <article class="bm-card">
                        <a href="<?php the_permalink(); ?>" class="bm-card-link">
                            
                            <div class="bm-card-image">
                                <?php 
                                echo bm_get_thumbnail(get_the_ID(), 'bm-card', [
                                    'loading' => 'lazy',
                                    'decoding' => 'async'
                                ]); 
                                ?>
                            </div>
                            
                            <div class="bm-card-content">
                                <h3 class="bm-card-title">
                                    <?php the_title(); ?>
                                </h3>
                                
                                <p class="bm-card-excerpt">
                                    <?php echo bm_get_excerpt(get_the_ID(), 15); ?>
                                </p>
                                
                                <div class="bm-card-meta">
                                    <span class="bm-card-date">
                                        <?php echo get_the_date('M j, Y'); ?>
                                    </span>
                                </div>
                            </div>
                            
                        </a>
                    </article>
                <?php endforeach; wp_reset_postdata(); ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php endwhile; ?>

<style>
.bm-article-meta {
    font-size: 14px;
    color: #c7d4db;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.bm-article-meta a {
    color: #26d0c6;
    text-decoration: none;
}

.bm-article-meta a:hover {
    text-decoration: underline;
}

.bm-meta-separator {
    opacity: 0.5;
}

.bm-article-footer {
    margin-top: 48px;
    padding-top: 32px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.bm-article-tags {
    margin-bottom: 32px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.bm-tags-label {
    font-weight: 600;
    color: #c7d4db;
    margin-right: 8px;
}

.bm-tag {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(38, 208, 198, 0.1);
    color: #26d0c6;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}

.bm-tag:hover {
    background: rgba(38, 208, 198, 0.2);
    transform: translateY(-1px);
}

.bm-disclaimer {
    margin-top: 32px;
    padding: 24px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    text-align: center;
}

.bm-disclaimer p {
    margin: 0;
    font-size: 14px;
    color: #a0a0a0;
    line-height: 1.5;
}

.bm-related-posts {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

@media (max-width: 768px) {
    .bm-article {
        padding: 24px 16px;
    }
    
    .bm-article-meta {
        font-size: 13px;
        flex-direction: column;
        gap: 4px;
    }
    
    .bm-meta-separator {
        display: none;
    }
    
    .bm-article-tags {
        justify-content: center;
    }
    
    .bm-disclaimer {
        padding: 20px 16px;
    }
}
</style>

<?php get_footer(); ?>
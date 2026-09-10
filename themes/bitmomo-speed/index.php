<?php get_header(); ?>

<?php if (is_front_page() && is_home()) : ?>
    <!-- Hero Section for Homepage -->
    <section class="bm-hero">
        <div class="bm-container">
            <div class="bm-hero-content">
                <h1 class="bm-hero-title">
                    Informasi AI & <span class="accent">Crypto</span> Terdepan
                </h1>
                <p class="bm-hero-subtitle">
                    Bergabung dengan ribuan pembaca dan dapatkan berita serta analisis terkini langsung ke inbox Anda.
                </p>
                <a href="#subscribe" class="bm-hero-cta js-newsletter-trigger">
                    Mulai Berlangganan
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (is_category() || is_tag() || is_archive()) : ?>
    <!-- Archive Header -->
    <section class="bm-archive-header">
        <div class="bm-container">
            <div class="bm-archive-content">
                <?php if (is_category()) : ?>
                    <h1 class="bm-archive-title">Kategori: <?php single_cat_title(); ?></h1>
                    <?php if (category_description()) : ?>
                        <div class="bm-archive-description">
                            <?php echo category_description(); ?>
                        </div>
                    <?php endif; ?>
                <?php elseif (is_tag()) : ?>
                    <h1 class="bm-archive-title">Tag: <?php single_tag_title(); ?></h1>
                    <?php if (tag_description()) : ?>
                        <div class="bm-archive-description">
                            <?php echo tag_description(); ?>
                        </div>
                    <?php endif; ?>
                <?php elseif (is_date()) : ?>
                    <h1 class="bm-archive-title">Arsip: <?php echo get_the_date('F Y'); ?></h1>
                <?php else : ?>
                    <h1 class="bm-archive-title">Arsip</h1>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Posts Section -->
<section class="bm-section">
    <div class="bm-container">
        
        <?php if (is_front_page() && is_home()) : ?>
            <h2 class="bm-section-title">Latest Stories</h2>
        <?php endif; ?>
        
        <?php if (have_posts()) : ?>
            <div class="bm-cards">
                <?php 
                $post_count = 0;
                while (have_posts()) : the_post(); 
                    $post_count++;
                    $is_first = ($post_count === 1);
                ?>
                    <article <?php post_class('bm-card'); ?>>
                        <a href="<?php the_permalink(); ?>" class="bm-card-link">
                            
                            <?php if (has_post_thumbnail()) : ?>
                                <div class="bm-card-image">
                                    <?php 
                                    echo bm_get_thumbnail(get_the_ID(), 'bm-card', [
                                        'loading' => $is_first ? 'eager' : 'lazy',
                                        'fetchpriority' => $is_first ? 'high' : 'auto',
                                        'decoding' => 'async'
                                    ]); 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="bm-card-content">
                                <h3 class="bm-card-title">
                                    <?php the_title(); ?>
                                </h3>
                                
                                <p class="bm-card-excerpt">
                                    <?php echo bm_get_excerpt(get_the_ID(), 20); ?>
                                </p>
                                
                                <div class="bm-card-meta">
                                    <span class="bm-card-date">
                                        <?php echo get_the_date('M j, Y'); ?>
                                    </span>
                                    <span class="bm-card-reading-time">
                                        <?php echo bm_reading_time(); ?> min read
                                    </span>
                                </div>
                            </div>
                            
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php 
            $pagination = paginate_links([
                'type' => 'array',
                'prev_text' => '← Prev',
                'next_text' => 'Next →',
                'mid_size' => 2,
                'end_size' => 1,
            ]);
            
            if ($pagination) :
            ?>
                <nav class="bm-pagination" role="navigation" aria-label="Posts pagination">
                    <?php foreach ($pagination as $link) : ?>
                        <?php echo $link; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            
        <?php else : ?>
            
            <!-- No Posts Found -->
            <div class="bm-no-posts">
                <h2>No posts found</h2>
                <p>Sorry, but nothing matched your search terms. Please try again with some different keywords.</p>
                <a href="<?php echo home_url(); ?>" class="bm-btn">Back to Homepage</a>
            </div>
            
        <?php endif; ?>
        
    </div>
</section>

<?php if (is_front_page() && is_home()) : ?>
    <!-- Newsletter CTA Section -->
    <section class="bm-section">
        <div class="bm-container">
            <div class="bm-cta-section">
                <h2 class="bm-cta-title">Stay Updated with Bitmomo</h2>
                <p class="bm-cta-text">Get the latest AI and crypto insights delivered straight to your inbox. Join thousands of readers who trust Bitmomo for cutting-edge analysis.</p>
                <a href="#subscribe" class="bm-btn js-newsletter-trigger">Subscribe Now</a>
            </div>
        </div>
    </section>
<?php endif; ?>

<style>
.bm-archive-header {
    padding: 40px 0;
    background: linear-gradient(135deg, #0f2233 0%, #0c1c2a 100%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
}

.bm-archive-title {
    font-size: clamp(24px, 3vw, 36px);
    font-weight: 800;
    color: #26d0c6;
    margin: 0 0 16px;
}

.bm-archive-description {
    color: #c7d4db;
    font-size: 16px;
    max-width: 600px;
    margin: 0 auto;
}

.bm-archive-description p {
    margin: 0;
}

.bm-no-posts {
    text-align: center;
    padding: 60px 20px;
    color: #c7d4db;
}

.bm-no-posts h2 {
    color: #26d0c6;
    margin: 0 0 16px;
    font-size: 28px;
    font-weight: 700;
}

.bm-no-posts p {
    margin: 0 0 24px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .bm-archive-header {
        padding: 32px 0;
    }
}
</style>

<?php get_footer(); ?>
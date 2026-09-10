<?php
/**
 * Template for the front page of the Bitmomo child theme.
 *
 * When this file exists in your child theme, WordPress will use it
 * automatically for the site front page when you select a static
 * homepage under Settings → Reading.  The design is inspired by
 * ChainOfThought.xyz and shows a hero section, a trending posts
 * section pulling the latest three posts, a newsletter signup
 * placeholder and a latest posts section.  You can further edit
 * this template to fetch popular posts or add additional widgets.
 */

get_header();
?>

<div class="bitmomo-home">
    <!-- Hero Section -->
    <section class="hero">
        <h1>Informasi AI &amp; Crypto Terdepan</h1>
        <p>Bergabung dengan ribuan pembaca dan dapatkan berita serta analisis terkini langsung ke inbox Anda.</p>
        <a href="#" class="btn">Mulai Berlangganan</a>
    </section>

    <!-- Trending Posts Section -->
    <section class="trending-posts">
        <h2>Unggahan Populer</h2>
        <div class="posts-grid">
            <?php
            // Fetch the three most recent posts. You can modify the
            // query arguments (e.g. meta_key for popular posts) to
            // better reflect “trending”.
            $trending = get_posts( array( 'numberposts' => 3 ) );
            foreach ( $trending as $post ) :
                setup_postdata( $post );
                // Get thumbnail URL or a placeholder if none exists
                $thumb = get_the_post_thumbnail_url( $post->ID, 'large' );
                if ( ! $thumb ) {
                    $thumb = 'https://placehold.co/600x400?text=Post';
                }
                ?>
                <article class="post-card">
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php the_title_attribute(); ?>">
                    <div class="post-content">
                        <?php
                        $categories = get_the_category();
                        if ( ! empty( $categories ) ) {
                            echo '<span class="category">' . esc_html( $categories[0]->name ) . '</span>';
                        }
                        ?>
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <p><?php echo wp_trim_words( get_the_excerpt(), 20, '…' ); ?></p>
                        <a href="<?php the_permalink(); ?>" class="read-more">Baca Selengkapnya</a>
                    </div>
                </article>
            <?php endforeach; wp_reset_postdata(); ?>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter">
        <h2>Jadilah Yang Pertama Tahu</h2>
        <p>Daftar ke newsletter kami untuk mendapatkan pembaruan AI &amp; Crypto langsung ke email Anda.</p>
        <!-- Replace the form below with your newsletter provider’s form or a plugin shortcode -->
        <form action="#" method="post">
            <input type="email" name="email" placeholder="Alamat email Anda" required>
            <button type="submit" class="btn">Daftar</button>
        </form>
    </section>

    <!-- Latest Posts Section -->
    <section class="latest-posts">
        <h2>Unggahan Terbaru</h2>
        <div class="posts-list">
            <?php
            // Fetch the latest three posts (can adjust number as needed)
            $latest = new WP_Query( array( 'posts_per_page' => 3 ) );
            if ( $latest->have_posts() ) :
                while ( $latest->have_posts() ) : $latest->the_post(); ?>
                    <article>
                        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <p class="meta"><?php echo get_the_date(); ?> · <?php echo get_the_category_list( ', ' ); ?></p>
                        <p><?php echo wp_trim_words( get_the_excerpt(), 30, '…' ); ?></p>
                    </article>
                <?php endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </div>
    </section>
</div>

<?php
get_footer();
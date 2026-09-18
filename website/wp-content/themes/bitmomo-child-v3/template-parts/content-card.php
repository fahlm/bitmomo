<?php
/**
 * Shared article card.
 *
 * Expected arguments:
 * - heading_level: h2 or h3.
 * - image_size: registered WordPress image size.
 * - excerpt_words: number of words in the excerpt.
 * - eager: whether this card is the likely LCP card.
 *
 * @package Bitmomo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$args = wp_parse_args(
    $args ?? [],
    [
        'heading_level' => 'h3',
        'image_size'    => 'bm-card',
        'excerpt_words' => 26,
        'eager'         => false,
    ]
);

$heading_level = in_array( $args['heading_level'], [ 'h2', 'h3' ], true )
    ? $args['heading_level']
    : 'h3';
$image_size    = sanitize_key( $args['image_size'] );
$excerpt_words = max( 1, (int) $args['excerpt_words'] );
$is_eager      = (bool) $args['eager'];
$title         = get_the_title();
?>
<article <?php post_class( 'bm-card' ); ?>>
  <a class="bm-card-art" href="<?php echo esc_url( get_permalink() ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
    <?php
    if ( has_post_thumbnail() ) {
        $image_attributes = [
            'class'    => 'bm-card-img',
            'alt'      => the_title_attribute( [ 'echo' => false ] ),
            'loading'  => $is_eager ? 'eager' : 'lazy',
            'decoding' => 'async',
        ];

        if ( $is_eager ) {
            $image_attributes['fetchpriority'] = 'high';
        }

        the_post_thumbnail( $image_size, $image_attributes );
    } else {
        echo '<span class="bm-card-art__placeholder" aria-hidden="true"></span>';
    }
    ?>
  </a>

  <<?php echo tag_escape( $heading_level ); ?> class="bm-card-title">
    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
  </<?php echo tag_escape( $heading_level ); ?>>

  <p class="bm-card-text">
    <?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerpt_words, '…' ) ); ?>
  </p>
</article>

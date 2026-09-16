<?php
/**
 * Launch cleanup for the two qualified Research articles audited on staging.
 *
 * DRY RUN by default:
 *   wp eval-file scripts/migrations/clean-launch-research-articles.php
 * Apply only after backup:
 *   BITMOMO_APPLY_RESEARCH_CLEANUP=1 wp eval-file scripts/migrations/clean-launch-research-articles.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Run through WP-CLI so WordPress is loaded.\n" );
    exit( 1 );
}

$apply = '1' === (string) getenv( 'BITMOMO_APPLY_RESEARCH_CLEANUP' );
$targets = array(
    'membaca-funding-rate-tanpa-terjebak-noise-harian' => array(
        'title'   => 'Membaca Funding Rate Tanpa Terjebak Noise Harian',
        'excerpt' => 'Funding rate berguna sebagai konteks positioning pasar, bukan sinyal arah tunggal. Nilainya perlu dibaca bersama basis, open interest, price action, dan perubahan posisi derivatif.',
    ),
    'struktur-permintaan-etf-dan-implikasinya-pada-volatilitas-btc' => array(
        'title'   => 'Struktur Permintaan ETF dan Implikasinya pada Volatilitas BTC',
        'excerpt' => 'Arus ETF spot dapat mengubah struktur permintaan BTC, tetapi dampaknya pada volatilitas bergantung pada persistensi arus, likuiditas spot, dan respons pasar derivatif.',
    ),
);

function bitmomo_clean_research_href( $matches ) {
    $quote = $matches[1];
    $url   = html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $query = wp_parse_url( $url, PHP_URL_QUERY );
    if ( ! $query ) return $matches[0];

    parse_str( $query, $args );
    if ( ! isset( $args['utm_source'] ) || 'chatgpt.com' !== strtolower( (string) $args['utm_source'] ) ) return $matches[0];

    $url = remove_query_arg( 'utm_source', $url );
    return 'href=' . $quote . esc_url_raw( $url ) . $quote;
}

function bitmomo_clean_research_content( $content ) {
    $content = preg_replace_callback(
        '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
        'bitmomo_clean_research_href',
        (string) $content
    );
    // Remove editor/import residue without rewriting authored prose.
    $content = preg_replace( '/(<p\b[^>]*>\s*(?:<[^>]+>\s*)*)Excerpt:\s*/i', '$1', $content );
    $content = preg_replace( '/(^|\n)\s*Excerpt:\s*/i', '$1', $content );
    return $content;
}

$changed = 0;
foreach ( $targets as $slug => $target ) {
    $post = get_page_by_path( $slug, OBJECT, 'post' );
    if ( ! $post || $target['title'] !== $post->post_title ) {
        printf( "MISS  %s — exact title/slug pair not found; no fallback mutation performed.\n", $slug );
        continue;
    }

    $before = (string) $post->post_content;
    $after  = bitmomo_clean_research_content( $before );
    $old_excerpt = (string) $post->post_excerpt;
    $new_excerpt = (string) $target['excerpt'];

    $utm_before = substr_count( strtolower( $before ), 'utm_source=chatgpt.com' );
    $excerpt_markers_before = preg_match_all( '/\bExcerpt:\s*/i', wp_strip_all_tags( $before ) );
    $needs_change = $before !== $after || $old_excerpt !== $new_excerpt;

    printf(
        "%s %s | chatgpt_utm=%d | excerpt_markers=%d | manual_excerpt=%s\n",
        $needs_change ? ( $apply ? 'APPLY' : 'DRY' ) : 'CLEAN',
        $slug,
        $utm_before,
        $excerpt_markers_before,
        $old_excerpt === $new_excerpt ? 'already-set' : 'update'
    );

    if ( ! $needs_change || ! $apply ) continue;

    $result = wp_update_post(
        array(
            'ID'           => (int) $post->ID,
            'post_content' => $after,
            'post_excerpt' => $new_excerpt,
        ),
        true
    );
    if ( is_wp_error( $result ) ) {
        printf( "ERROR %s — %s\n", $slug, $result->get_error_message() );
        exit( 1 );
    }
    clean_post_cache( (int) $post->ID );
    $changed++;
}

printf( "%s complete. changed=%d.\n", $apply ? 'APPLY' : 'DRY RUN', $changed );
if ( ! $apply ) {
    echo "No database writes were made. Set BITMOMO_APPLY_RESEARCH_CLEANUP=1 only after a verified backup.\n";
}

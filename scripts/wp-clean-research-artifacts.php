<?php
/**
 * Idempotent WordPress Research content cleanup.
 *
 * Usage from repository root with WordPress loaded by WP-CLI:
 *   wp eval-file scripts/wp-clean-research-artifacts.php
 *   BITMOMO_APPLY_RESEARCH_CLEANUP=1 wp eval-file scripts/wp-clean-research-artifacts.php
 *
 * Dry-run is the default. The script only touches published posts in the Riset
 * category, removes the ChatGPT attribution query parameter from anchor hrefs,
 * removes literal editor scaffolding "Excerpt:", and clears an excerpt only
 * when it is an auto-truncated duplicate of the article opening.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress is not loaded. Run through wp eval-file.\n" );
	exit( 2 );
}

$apply = '1' === (string) getenv( 'BITMOMO_APPLY_RESEARCH_CLEANUP' );
$category = get_category_by_slug( 'riset' );
if ( ! $category || is_wp_error( $category ) ) {
	echo "No Riset category found; nothing to do.\n";
	return;
}

$ids = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'cat'            => (int) $category->term_id,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);

$changed = 0;
$utm_removed = 0;
$excerpt_scaffolds = 0;
$duplicate_decks = 0;

$normalize = static function ( $text ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
	$text = preg_replace( '/\s+/u', ' ', $text );
	return trim( is_string( $text ) ? $text : '' );
};

foreach ( (array) $ids as $post_id ) {
	$post_id = (int) $post_id;
	$post = get_post( $post_id );
	if ( ! $post ) continue;

	$content = (string) $post->post_content;
	$excerpt = (string) $post->post_excerpt;
	$next_content = preg_replace_callback(
		'/href=(["\'])(https?:\/\/[^"\']+)\1/i',
		static function ( $matches ) use ( &$utm_removed ) {
			$quote = $matches[1];
			$url = html_entity_decode( $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$query = wp_parse_url( $url, PHP_URL_QUERY );
			$params = array();
			if ( is_string( $query ) ) parse_str( $query, $params );
			if ( ! isset( $params['utm_source'] ) || 'chatgpt.com' !== strtolower( trim( (string) $params['utm_source'] ) ) ) {
				return $matches[0];
			}
			$utm_removed++;
			$url = remove_query_arg( 'utm_source', $url );
			return 'href=' . $quote . esc_url_raw( $url ) . $quote;
		},
		$content
	);
	$next_content = is_string( $next_content ) ? $next_content : $content;

	$before_excerpt_cleanup = $next_content;
	$next_content = preg_replace( '/<p\b[^>]*>\s*Excerpt:\s*<\/p>/i', '', $next_content );
	$next_content = preg_replace( '/(<p\b[^>]*>\s*)Excerpt:\s*/i', '$1', $next_content );
	$next_content = is_string( $next_content ) ? $next_content : $before_excerpt_cleanup;
	if ( $next_content !== $before_excerpt_cleanup ) $excerpt_scaffolds++;

	$next_excerpt = $excerpt;
	$excerpt_trimmed = preg_replace( '/\s*(?:\[\x{2026}\]|\[\.\.\.\])\s*$/u', '', $excerpt );
	$excerpt_trimmed = is_string( $excerpt_trimmed ) ? trim( $excerpt_trimmed ) : trim( $excerpt );
	$deck_plain = $normalize( $excerpt_trimmed );
	$body_plain = $normalize( $next_content );
	$auto_truncated = (bool) preg_match( '/(?:\[\x{2026}\]|\[\.\.\.\])\s*$/u', $excerpt );
	$duplicates_opening = '' !== $deck_plain && '' !== $body_plain && 0 === strpos( $body_plain, $deck_plain );
	if ( $duplicates_opening && ( $auto_truncated || strlen( $deck_plain ) >= 80 ) ) {
		$next_excerpt = '';
		$duplicate_decks++;
	}

	if ( $next_content === $content && $next_excerpt === $excerpt ) continue;
	$changed++;

	printf(
		"[%s] #%d %s | content:%s excerpt:%s\n",
		$apply ? 'APPLY' : 'DRY-RUN',
		$post_id,
		get_the_title( $post_id ),
		$next_content === $content ? 'same' : 'clean',
		$next_excerpt === $excerpt ? 'same' : 'clean'
	);

	if ( $apply ) {
		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $next_content,
				'post_excerpt' => $next_excerpt,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			fwrite( STDERR, sprintf( "Failed #%d: %s\n", $post_id, $result->get_error_message() ) );
			exit( 1 );
		}
	}
}

printf(
	"Research cleanup %s: scanned=%d changed=%d chatgpt_utm_removed=%d excerpt_scaffolds=%d duplicate_decks=%d\n",
	$apply ? 'APPLIED' : 'DRY-RUN',
	count( $ids ),
	$changed,
	$utm_removed,
	$excerpt_scaffolds,
	$duplicate_decks
);

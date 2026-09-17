<?php
/**
 * Bitmomo Research content-hygiene repair.
 *
 * Run read-only first:
 *   wp eval-file scripts/repair-research-content-hygiene.php
 *
 * Apply only after reviewing the dry-run output:
 *   BITMOMO_APPLY_RESEARCH_HYGIENE=1 wp eval-file scripts/repair-research-content-hygiene.php
 *
 * Scope is intentionally narrow:
 * - published posts in category `riset` only;
 * - remove `utm_source=chatgpt.com` from source links without changing the destination;
 * - remove literal authoring marker `Excerpt:`;
 * - remove excerpt truncation markers `[…]` / `[...]`;
 * - clear a manual excerpt only when it is demonstrably a duplicate of the
 *   article's opening paragraph.
 *
 * The script never rewrites source names, article claims, or citation text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$apply = '1' === (string) getenv( 'BITMOMO_APPLY_RESEARCH_HYGIENE' );
$category = get_category_by_slug( 'riset' );

if ( ! $category ) {
	echo "ERROR: category `riset` not found.\n";
	exit( 1 );
}

$post_ids = get_posts(
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

function bitmomo_hygiene_log( $message ) {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::log( $message );
		return;
	}
	echo $message . "\n";
}

function bitmomo_hygiene_compact_text( $value ) {
	$value = wp_strip_all_tags( strip_shortcodes( (string) $value ) );
	$value = preg_replace( '/\s+/u', ' ', $value );
	return trim( (string) $value );
}

function bitmomo_hygiene_strip_chatgpt_tracking( $content, &$removed_count ) {
	$removed_count = 0;
	return preg_replace_callback(
		'/href=("|\')(.*?)\1/isu',
		static function ( $match ) use ( &$removed_count ) {
			$quote = $match[1];
			$raw = html_entity_decode( (string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$query = wp_parse_url( $raw, PHP_URL_QUERY );
			if ( ! is_string( $query ) || '' === $query ) return $match[0];

			$params = array();
			parse_str( $query, $params );
			if ( ! isset( $params['utm_source'] ) || 'chatgpt.com' !== strtolower( trim( (string) $params['utm_source'] ) ) ) {
				return $match[0];
			}

			$clean = remove_query_arg( 'utm_source', $raw );
			$removed_count++;
			return 'href=' . $quote . esc_url( $clean ) . $quote;
		},
		(string) $content
	);
}

function bitmomo_hygiene_strip_excerpt_marker( $content, &$removed ) {
	$removed = false;
	$clean = preg_replace(
		'/<p\b[^>]*>\s*(?:<[^>]+>\s*)*Excerpt\s*:\s*(?:<\/[^>]+>\s*)*<\/p>/iu',
		'',
		(string) $content,
		-1,
		$count
	);
	if ( $count > 0 ) $removed = true;

	$second = preg_replace( '/^\s*Excerpt\s*:\s*/iu', '', (string) $clean, 1, $prefix_count );
	if ( $prefix_count > 0 ) $removed = true;
	return $second;
}

function bitmomo_hygiene_first_paragraph_text( $content ) {
	if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/isu', (string) $content, $match ) ) {
		return bitmomo_hygiene_compact_text( $match[1] );
	}
	return '';
}

bitmomo_hygiene_log( sprintf( 'MODE: %s', $apply ? 'APPLY' : 'DRY-RUN' ) );
bitmomo_hygiene_log( sprintf( 'Research posts scanned: %d', count( $post_ids ) ) );

$changed_posts = 0;
$tracking_links = 0;
$excerpt_markers = 0;
$duplicate_decks = 0;

foreach ( $post_ids as $post_id ) {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) continue;

	$original_content = (string) $post->post_content;
	$original_excerpt = (string) $post->post_excerpt;
	$content = $original_content;
	$excerpt = trim( $original_excerpt );

	$content = bitmomo_hygiene_strip_chatgpt_tracking( $content, $post_tracking_links );
	$content = bitmomo_hygiene_strip_excerpt_marker( $content, $post_excerpt_marker );

	$excerpt = preg_replace( '/^\s*Excerpt\s*:\s*/iu', '', (string) $excerpt, 1, $excerpt_prefix_count );
	$excerpt = preg_replace( '/\s*\[(?:…|\.\.\.)\]\s*$/u', '', (string) $excerpt, 1, $excerpt_truncation_count );
	$excerpt = trim( (string) $excerpt );

	$opening = bitmomo_hygiene_first_paragraph_text( $content );
	$deck_compact = mb_strtolower( bitmomo_hygiene_compact_text( $excerpt ), 'UTF-8' );
	$opening_compact = mb_strtolower( bitmomo_hygiene_compact_text( $opening ), 'UTF-8' );
	$post_duplicate_deck = false;
	if ( mb_strlen( $deck_compact, 'UTF-8' ) >= 60 && '' !== $opening_compact ) {
		$probe = mb_substr( $deck_compact, 0, min( 120, mb_strlen( $deck_compact, 'UTF-8' ) ), 'UTF-8' );
		$post_duplicate_deck = 0 === mb_strpos( $opening_compact, $probe, 0, 'UTF-8' );
		if ( $post_duplicate_deck ) $excerpt = '';
	}

	$changed = $content !== $original_content || $excerpt !== $original_excerpt;
	if ( ! $changed ) continue;

	$changed_posts++;
	$tracking_links += (int) $post_tracking_links;
	$excerpt_markers += (int) ( $post_excerpt_marker || $excerpt_prefix_count || $excerpt_truncation_count );
	$duplicate_decks += $post_duplicate_deck ? 1 : 0;

	bitmomo_hygiene_log(
		sprintf(
			'POST %d `%s`: chatgpt_links=%d excerpt_artifact=%s duplicate_deck=%s',
			$post_id,
			$post->post_name,
			$post_tracking_links,
			( $post_excerpt_marker || $excerpt_prefix_count || $excerpt_truncation_count ) ? 'yes' : 'no',
			$post_duplicate_deck ? 'yes' : 'no'
		)
	);

	if ( ! $apply ) continue;

	$result = wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		bitmomo_hygiene_log( sprintf( 'ERROR updating post %d: %s', $post_id, $result->get_error_message() ) );
		exit( 1 );
	}
}

bitmomo_hygiene_log(
	sprintf(
		'SUMMARY: changed_posts=%d chatgpt_links=%d excerpt_artifacts=%d duplicate_decks=%d',
		$changed_posts,
		$tracking_links,
		$excerpt_markers,
		$duplicate_decks
	)
);

if ( ! $apply && $changed_posts > 0 ) {
	bitmomo_hygiene_log( 'DRY-RUN ONLY: rerun with BITMOMO_APPLY_RESEARCH_HYGIENE=1 after review.' );
}

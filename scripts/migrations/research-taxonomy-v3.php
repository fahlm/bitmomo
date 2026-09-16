<?php
/**
 * Bitmomo Research Taxonomy V3 migration.
 *
 * Usage:
 *   wp --require=scripts/migrations/research-taxonomy-v3.php bitmomo research-taxonomy-v3
 *   wp --require=scripts/migrations/research-taxonomy-v3.php bitmomo research-taxonomy-v3 --apply
 *   wp --require=scripts/migrations/research-taxonomy-v3.php bitmomo research-taxonomy-v3 --apply --map=/absolute/path/research-map.json
 *
 * Default is DRY RUN. Public classification does not switch to V3 until every
 * planned write validates and the version option is activated at the very end.
 * Partial writes therefore remain invisible to public classification.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

final class Bitmomo_Research_Taxonomy_V3_Command {
    /** @var array<string,string> */
    private array $legacy_topic_aliases = array(
        'bitcoin'          => 'bitcoin',
        'btc'              => 'bitcoin',
        'macro'            => 'macro',
        'makro'            => 'macro',
        'market-structure' => 'market-structure',
        'derivatives'      => 'derivatives',
        'funding-rate'     => 'derivatives',
        'etf'              => 'etf-flows',
        'liquidity'        => 'liquidity',
        'likuiditas'       => 'liquidity',
        'fundamental'      => 'fundamentals',
        'fundamentals'     => 'fundamentals',
    );

    /**
     * Migrate canonical Research Desk + Research Topic metadata.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Persist the validated plan. Without this flag the command is dry-run.
     *
     * [--map=<path>]
     * : Optional JSON mapping for editorial overrides. Shape:
     *   {"posts":{"post-slug":{"desk":"market-research","topics":["bitcoin"]}}}
     *   Use an empty desk to explicitly keep a post outside institutional research.
     *
     * ## EXAMPLES
     *
     *     wp --require=scripts/migrations/research-taxonomy-v3.php bitmomo research-taxonomy-v3
     *
     *     wp --require=scripts/migrations/research-taxonomy-v3.php bitmomo research-taxonomy-v3 --apply --map=/tmp/research-map.json
     */
    public function __invoke( array $args, array $assoc_args ): void {
        $this->assert_runtime();

        $apply = isset( $assoc_args['apply'] );
        $map_path = isset( $assoc_args['map'] ) ? (string) $assoc_args['map'] : '';
        $overrides = $this->load_map( $map_path );

        if ( function_exists( 'bitmomo_register_research_taxonomies' ) ) {
            bitmomo_register_research_taxonomies();
        }

        $desk_taxonomy = bitmomo_research_desk_taxonomy();
        $topic_taxonomy = bitmomo_research_topic_taxonomy();
        $desk_definitions = bitmomo_research_desk_definitions();
        $topic_definitions = bitmomo_research_topic_definitions();

        if ( ! taxonomy_exists( $desk_taxonomy ) || ! taxonomy_exists( $topic_taxonomy ) ) {
            WP_CLI::error( 'Research V3 taxonomies are not registered.' );
        }

        $post_ids = get_posts(
            array(
                'post_type'              => 'post',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => true,
            )
        );

        $plan = array();
        $errors = array();
        $seen_override_slugs = array();

        foreach ( $post_ids as $post_id ) {
            $post_id = (int) $post_id;
            $post = get_post( $post_id );
            if ( ! $post ) continue;

            $slug = (string) $post->post_name;
            $override = array_key_exists( $slug, $overrides ) ? $overrides[ $slug ] : null;
            if ( null !== $override ) $seen_override_slugs[] = $slug;

            $legacy = $this->legacy_signals( $post_id );
            if ( $legacy['ambiguous'] && null === $override ) {
                $errors[] = sprintf(
                    '#%d %s: ambiguous legacy research signals (AI + market). Add an explicit map override.',
                    $post_id,
                    $slug
                );
                continue;
            }

            if ( null !== $override ) {
                $desk = sanitize_key( (string) ( $override['desk'] ?? '' ) );
                $topics = isset( $override['topics'] ) && is_array( $override['topics'] )
                    ? array_values( array_unique( array_map( 'sanitize_key', $override['topics'] ) ) )
                    : array();

                if ( '' !== $desk && ! isset( $desk_definitions[ $desk ] ) ) {
                    $errors[] = sprintf( '#%d %s: invalid mapped desk "%s".', $post_id, $slug, $desk );
                    continue;
                }

                $invalid_topics = array_diff( $topics, array_keys( $topic_definitions ) );
                if ( $invalid_topics ) {
                    $errors[] = sprintf(
                        '#%d %s: invalid mapped topics: %s.',
                        $post_id,
                        $slug,
                        implode( ', ', $invalid_topics )
                    );
                    continue;
                }

                $plan[ $post_id ] = array(
                    'slug'   => $slug,
                    'desk'   => $desk,
                    'topics' => $topics,
                    'source' => 'map',
                );
                continue;
            }

            $existing_desks = wp_get_object_terms( $post_id, $desk_taxonomy, array( 'fields' => 'slugs' ) );
            if ( is_wp_error( $existing_desks ) ) {
                $errors[] = sprintf( '#%d %s: failed to read existing desk terms.', $post_id, $slug );
                continue;
            }

            if ( count( $existing_desks ) > 1 ) {
                $errors[] = sprintf( '#%d %s: more than one canonical Research Desk is assigned.', $post_id, $slug );
                continue;
            }

            if ( 1 === count( $existing_desks ) ) {
                $desk = sanitize_key( (string) reset( $existing_desks ) );
                if ( ! isset( $desk_definitions[ $desk ] ) ) {
                    $errors[] = sprintf( '#%d %s: unknown canonical Research Desk "%s".', $post_id, $slug, $desk );
                    continue;
                }

                $existing_topics = wp_get_object_terms( $post_id, $topic_taxonomy, array( 'fields' => 'slugs' ) );
                if ( is_wp_error( $existing_topics ) ) {
                    $errors[] = sprintf( '#%d %s: failed to read existing topic terms.', $post_id, $slug );
                    continue;
                }
                $topics = array_values(
                    array_intersect(
                        array_keys( $topic_definitions ),
                        array_map( 'sanitize_key', $existing_topics )
                    )
                );
                $plan[ $post_id ] = array(
                    'slug'   => $slug,
                    'desk'   => $desk,
                    'topics' => $topics,
                    'source' => 'existing-v3',
                );
                continue;
            }

            if ( 'market' === $legacy['classification'] ) {
                $plan[ $post_id ] = array(
                    'slug'   => $slug,
                    'desk'   => 'market-research',
                    'topics' => $legacy['topics'],
                    'source' => 'legacy-explicit',
                );
            } elseif ( 'ai-systems' === $legacy['classification'] ) {
                $plan[ $post_id ] = array(
                    'slug'   => $slug,
                    'desk'   => 'intelligence-systems',
                    'topics' => array(),
                    'source' => 'legacy-explicit',
                );
            }
        }

        $unknown_overrides = array_diff( array_keys( $overrides ), $seen_override_slugs );
        foreach ( $unknown_overrides as $slug ) {
            $errors[] = sprintf( 'Map references unknown or unpublished post slug "%s".', $slug );
        }

        $rows = array();
        foreach ( $plan as $post_id => $item ) {
            $rows[] = array(
                'ID'     => $post_id,
                'slug'   => $item['slug'],
                'desk'   => $item['desk'] ?: 'UNCLASSIFIED',
                'topics' => $item['topics'] ? implode( ',', $item['topics'] ) : '—',
                'source' => $item['source'],
            );
        }

        if ( $rows ) {
            WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'slug', 'desk', 'topics', 'source' ) );
        } else {
            WP_CLI::warning( 'No qualified research assignments were discovered.' );
        }

        if ( $errors ) {
            foreach ( $errors as $error ) WP_CLI::warning( $error );
            WP_CLI::error( sprintf( 'Migration plan rejected with %d error(s). No activation performed.', count( $errors ) ) );
        }

        WP_CLI::log(
            sprintf(
                '%s plan: %d canonical assignment(s), %d published post(s) inspected.',
                $apply ? 'APPLY' : 'DRY RUN',
                count( $plan ),
                count( $post_ids )
            )
        );

        if ( ! $apply ) {
            WP_CLI::success( 'Dry run passed. Re-run with --apply only after reviewing the plan and taking a database backup.' );
            return;
        }

        $this->seed_terms( $desk_taxonomy, $topic_taxonomy, $desk_definitions, $topic_definitions );

        foreach ( $plan as $post_id => $item ) {
            $desk_result = wp_set_object_terms(
                (int) $post_id,
                '' === $item['desk'] ? array() : array( $item['desk'] ),
                $desk_taxonomy,
                false
            );
            if ( is_wp_error( $desk_result ) ) {
                WP_CLI::error(
                    sprintf(
                        '#%d %s: desk write failed: %s. V3 remains inactive.',
                        $post_id,
                        $item['slug'],
                        $desk_result->get_error_message()
                    )
                );
            }

            $topic_result = wp_set_object_terms(
                (int) $post_id,
                $item['topics'],
                $topic_taxonomy,
                false
            );
            if ( is_wp_error( $topic_result ) ) {
                WP_CLI::error(
                    sprintf(
                        '#%d %s: topic write failed: %s. V3 remains inactive.',
                        $post_id,
                        $item['slug'],
                        $topic_result->get_error_message()
                    )
                );
            }
        }

        $validation_errors = $this->validate_after_write( $post_ids, $plan, $desk_taxonomy, $desk_definitions );
        if ( $validation_errors ) {
            foreach ( $validation_errors as $error ) WP_CLI::warning( $error );
            WP_CLI::error( 'Post-write validation failed. Canonical terms may have been staged, but V3 was NOT activated.' );
        }

        update_option( 'bitmomo_research_taxonomy_version', bitmomo_research_taxonomy_version(), false );
        WP_CLI::success( 'Research Taxonomy V3 validated and activated.' );
    }

    private function assert_runtime(): void {
        $required = array(
            'bitmomo_research_taxonomy_version',
            'bitmomo_research_desk_taxonomy',
            'bitmomo_research_topic_taxonomy',
            'bitmomo_research_desk_definitions',
            'bitmomo_research_topic_definitions',
            'bitmomo_market_research_taxonomy_slugs',
            'bitmomo_legacy_post_research_classification',
        );
        foreach ( $required as $function ) {
            if ( ! function_exists( $function ) ) {
                WP_CLI::error(
                    sprintf(
                        'Required theme helper %s() is unavailable. Run this command with the Bitmomo child-v3 theme code loaded.',
                        $function
                    )
                );
            }
        }
    }

    /**
     * @return array<string,array{desk:string,topics:array<int,string>}>
     */
    private function load_map( string $path ): array {
        if ( '' === trim( $path ) ) return array();

        if ( ! is_readable( $path ) ) {
            WP_CLI::error( sprintf( 'Map file is not readable: %s', $path ) );
        }

        $decoded = json_decode( (string) file_get_contents( $path ), true );
        if ( ! is_array( $decoded ) || ! isset( $decoded['posts'] ) || ! is_array( $decoded['posts'] ) ) {
            WP_CLI::error( 'Map JSON must contain an object at key "posts".' );
        }

        $result = array();
        foreach ( $decoded['posts'] as $slug => $item ) {
            $slug = sanitize_title( (string) $slug );
            if ( '' === $slug || ! is_array( $item ) ) {
                WP_CLI::error( 'Every map entry must use a valid post slug and object value.' );
            }
            $result[ $slug ] = array(
                'desk'   => isset( $item['desk'] ) ? sanitize_key( (string) $item['desk'] ) : '',
                'topics' => isset( $item['topics'] ) && is_array( $item['topics'] ) ? $item['topics'] : array(),
            );
        }
        return $result;
    }

    /**
     * @return array{classification:string,topics:array<int,string>,ambiguous:bool}
     */
    private function legacy_signals( int $post_id ): array {
        if ( ! has_category( 'riset', $post_id ) ) {
            return array(
                'classification' => 'unclassified',
                'topics'         => array(),
                'ambiguous'      => false,
            );
        }

        $has_ai = has_tag( 'ai-lab', $post_id );
        $market_matches = array();

        foreach ( bitmomo_market_research_taxonomy_slugs() as $slug ) {
            if ( has_category( $slug, $post_id ) || has_tag( $slug, $post_id ) ) {
                $market_matches[] = $slug;
            }
        }

        $has_market = (bool) $market_matches;
        $topics = array();
        foreach ( $market_matches as $legacy_slug ) {
            if ( isset( $this->legacy_topic_aliases[ $legacy_slug ] ) ) {
                $topics[] = $this->legacy_topic_aliases[ $legacy_slug ];
            }
        }

        return array(
            'classification' => $has_ai && ! $has_market
                ? 'ai-systems'
                : ( $has_market && ! $has_ai ? 'market' : 'unclassified' ),
            'topics'    => array_values( array_unique( $topics ) ),
            'ambiguous' => $has_ai && $has_market,
        );
    }

    private function seed_terms(
        string $desk_taxonomy,
        string $topic_taxonomy,
        array $desk_definitions,
        array $topic_definitions
    ): void {
        foreach ( $desk_definitions as $slug => $definition ) {
            if ( term_exists( $slug, $desk_taxonomy ) ) continue;
            $result = wp_insert_term(
                (string) $definition['label'],
                $desk_taxonomy,
                array( 'slug' => $slug )
            );
            if ( is_wp_error( $result ) ) {
                WP_CLI::error( sprintf( 'Failed creating desk term %s: %s', $slug, $result->get_error_message() ) );
            }
        }

        foreach ( $topic_definitions as $slug => $label ) {
            if ( term_exists( $slug, $topic_taxonomy ) ) continue;
            $result = wp_insert_term(
                (string) $label,
                $topic_taxonomy,
                array( 'slug' => $slug )
            );
            if ( is_wp_error( $result ) ) {
                WP_CLI::error( sprintf( 'Failed creating topic term %s: %s', $slug, $result->get_error_message() ) );
            }
        }
    }

    /**
     * @return array<int,string>
     */
    private function validate_after_write(
        array $post_ids,
        array $plan,
        string $desk_taxonomy,
        array $desk_definitions
    ): array {
        $errors = array();

        foreach ( $post_ids as $post_id ) {
            $post_id = (int) $post_id;
            $post = get_post( $post_id );
            if ( ! $post ) continue;

            $terms = wp_get_object_terms( $post_id, $desk_taxonomy, array( 'fields' => 'slugs' ) );
            if ( is_wp_error( $terms ) ) {
                $errors[] = sprintf( '#%d %s: cannot read desk terms after write.', $post_id, $post->post_name );
                continue;
            }

            if ( count( $terms ) > 1 ) {
                $errors[] = sprintf( '#%d %s: fail-closed invariant violated; multiple desks assigned.', $post_id, $post->post_name );
                continue;
            }

            if ( 1 === count( $terms ) ) {
                $slug = sanitize_key( (string) reset( $terms ) );
                if ( ! isset( $desk_definitions[ $slug ] ) ) {
                    $errors[] = sprintf( '#%d %s: unknown desk remains after write.', $post_id, $post->post_name );
                }
            }

            $legacy = $this->legacy_signals( $post_id );
            if ( in_array( $legacy['classification'], array( 'market', 'ai-systems' ), true ) ) {
                if ( ! isset( $plan[ $post_id ] ) || '' === $plan[ $post_id ]['desk'] ) {
                    $errors[] = sprintf( '#%d %s: legacy-qualified research would lose classification.', $post_id, $post->post_name );
                }
            }
        }

        return $errors;
    }
}

WP_CLI::add_command( 'bitmomo research-taxonomy-v3', 'Bitmomo_Research_Taxonomy_V3_Command' );

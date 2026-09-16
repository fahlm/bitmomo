<?php
/** Template rendering helpers. @package Bitmomo */

if (!defined('ABSPATH')) exit;

if (!function_exists('bitmomo_render_brand')) {
    function bitmomo_render_brand() {
        $logo_url = get_site_icon_url(96);
        $mark = $logo_url
            ? sprintf('<img class="bm-brand-mark" src="%s" width="48" height="48" alt="" decoding="async">', esc_url($logo_url))
            : '<span class="bm-brand-mark-fallback" aria-hidden="true">b</span>';

        printf(
            '<div class="bm-brand"><a class="bm-brand-logo" href="%s" aria-label="%s">%s<span class="bm-brand-name">bitmomo</span></a></div>',
            esc_url(home_url('/')),
            esc_attr__('Bitmomo home', 'bitmomo'),
            $mark
        );
    }
}

if (!function_exists('bitmomo_render_menu_toggle')) {
    function bitmomo_render_menu_toggle() {
        printf(
            '<button class="bm-hamburger" id="bm-hamburger" type="button" aria-label="%s" aria-controls="bm-nav" aria-expanded="false"><span></span><span></span><span></span></button>',
            esc_attr__('Buka menu', 'bitmomo')
        );
    }
}

if (!function_exists('bitmomo_public_social_links')) {
    /**
     * Canonical public social destinations.
     *
     * Public finance surfaces fail closed: a channel is rendered only when an
     * HTTPS destination is explicitly configured via wp-config constant or the
     * matching filter. There are deliberately no hard-coded public fallbacks.
     *
     * @return array<string,array{label:string,url:string}>
     */
    function bitmomo_public_social_links() {
        $definitions = array(
            'telegram' => array(
                'label'    => 'Telegram',
                'constant' => 'BITMOMO_TELEGRAM_URL',
                'filter'   => 'bitmomo_telegram_url',
            ),
            'youtube' => array(
                'label'    => 'YouTube',
                'constant' => 'BITMOMO_YOUTUBE_URL',
                'filter'   => 'bitmomo_youtube_url',
            ),
            'x' => array(
                'label'    => 'X',
                'constant' => 'BITMOMO_X_URL',
                'filter'   => 'bitmomo_x_url',
            ),
        );
        $links = array();

        foreach ($definitions as $key => $definition) {
            $configured = defined($definition['constant']) ? (string) constant($definition['constant']) : '';
            $candidate = trim((string) apply_filters($definition['filter'], $configured));
            if ('' === $candidate) continue;

            $url = esc_url_raw($candidate, array('https'));
            if ('' === $url || 0 !== stripos($url, 'https://') || !wp_http_validate_url($url)) continue;

            $links[$key] = array(
                'label' => $definition['label'],
                'url'   => $url,
            );
        }

        return $links;
    }
}

/* -------------------------------------------------------------------------
 * Canonical Research classification.
 *
 * V3 separates institutional research identity from WordPress' historical
 * category/tag archive. The new desk/topic taxonomies are editorial metadata,
 * not public archive routes. They fail closed: exactly one allow-listed desk
 * is required to qualify a post once the V3 migration flag is active.
 *
 * Until the migration is explicitly activated, the current production-safe
 * Riset + legacy taxonomy boundary remains in force. This compatibility bridge
 * prevents a code deploy from silently emptying Research before the audited
 * database migration has passed.
 * ---------------------------------------------------------------------- */

if (!function_exists('bitmomo_research_taxonomy_version')) {
    function bitmomo_research_taxonomy_version() {
        return 'v3';
    }
}

if (!function_exists('bitmomo_research_desk_taxonomy')) {
    function bitmomo_research_desk_taxonomy() {
        return 'bm_research_desk';
    }
}

if (!function_exists('bitmomo_research_topic_taxonomy')) {
    function bitmomo_research_topic_taxonomy() {
        return 'bm_research_topic';
    }
}

if (!function_exists('bitmomo_research_desk_definitions')) {
    function bitmomo_research_desk_definitions() {
        return array(
            'market-research' => array(
                'label'          => 'Market Research',
                'classification' => 'market',
            ),
            'intelligence-systems' => array(
                'label'          => 'AI & Intelligence Systems',
                'classification' => 'ai-systems',
            ),
        );
    }
}

if (!function_exists('bitmomo_research_topic_definitions')) {
    function bitmomo_research_topic_definitions() {
        return array(
            'bitcoin'             => 'Bitcoin',
            'macro'               => 'Macro',
            'market-structure'    => 'Market Structure',
            'derivatives'         => 'Derivatives',
            'etf-flows'           => 'ETF & Flows',
            'liquidity'           => 'Liquidity',
            'fundamentals'        => 'Fundamentals',
            'inference'           => 'Inference',
            'compute'             => 'Compute',
            'agents'              => 'AI Agents',
            'evaluation'          => 'Evaluation',
            'provenance'          => 'Data Provenance',
            'decentralized-ai'    => 'Decentralized AI',
            'ai-infrastructure'   => 'AI Infrastructure',
            'models'              => 'Models',
            'industry-society'    => 'AI Industry & Society',
        );
    }
}

if (!function_exists('bitmomo_register_research_taxonomies')) {
    function bitmomo_register_research_taxonomies() {
        register_taxonomy(
            bitmomo_research_desk_taxonomy(),
            array('post'),
            array(
                'labels' => array(
                    'name'          => __('Research Desks', 'bitmomo'),
                    'singular_name' => __('Research Desk', 'bitmomo'),
                ),
                'public'             => false,
                'publicly_queryable' => false,
                'show_ui'            => false,
                'show_in_rest'       => true,
                'show_admin_column'  => false,
                'show_in_nav_menus'  => false,
                'hierarchical'       => false,
                'query_var'          => false,
                'rewrite'            => false,
            )
        );

        register_taxonomy(
            bitmomo_research_topic_taxonomy(),
            array('post'),
            array(
                'labels' => array(
                    'name'          => __('Research Topics', 'bitmomo'),
                    'singular_name' => __('Research Topic', 'bitmomo'),
                ),
                'public'             => false,
                'publicly_queryable' => false,
                'show_ui'            => false,
                'show_in_rest'       => true,
                'show_admin_column'  => false,
                'show_in_nav_menus'  => false,
                'hierarchical'       => false,
                'query_var'          => false,
                'rewrite'            => false,
            )
        );
    }
}
add_action('init', 'bitmomo_register_research_taxonomies', 5);

if (!function_exists('bitmomo_research_taxonomy_is_active')) {
    function bitmomo_research_taxonomy_is_active() {
        if (!taxonomy_exists(bitmomo_research_desk_taxonomy()) || !taxonomy_exists(bitmomo_research_topic_taxonomy())) return false;
        return bitmomo_research_taxonomy_version() === (string) get_option('bitmomo_research_taxonomy_version', '');
    }
}

if (!function_exists('bitmomo_post_research_desk_slug')) {
    function bitmomo_post_research_desk_slug($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id || !bitmomo_research_taxonomy_is_active()) return '';

        $terms = wp_get_object_terms($post_id, bitmomo_research_desk_taxonomy(), array('fields' => 'slugs'));
        if (is_wp_error($terms) || 1 !== count($terms)) return '';

        $slug = sanitize_key((string) reset($terms));
        return isset(bitmomo_research_desk_definitions()[$slug]) ? $slug : '';
    }
}

if (!function_exists('bitmomo_post_research_topic_slugs')) {
    function bitmomo_post_research_topic_slugs($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id || !bitmomo_research_taxonomy_is_active()) return array();

        $terms = wp_get_object_terms($post_id, bitmomo_research_topic_taxonomy(), array('fields' => 'slugs'));
        if (is_wp_error($terms) || !$terms) return array();

        $allowed = bitmomo_research_topic_definitions();
        $slugs = array();
        foreach ($terms as $term) {
            $slug = sanitize_key((string) $term);
            if (isset($allowed[$slug])) $slugs[] = $slug;
        }
        return array_values(array_unique($slugs));
    }
}

if (!function_exists('bitmomo_market_research_taxonomy_slugs')) {
    /** Legacy allow-list retained only for the pre-V3 migration bridge. */
    function bitmomo_market_research_taxonomy_slugs() {
        return array(
            'bitcoin', 'btc', 'makro', 'macro', 'market-structure', 'derivatives',
            'funding-rate', 'etf', 'liquidity', 'likuiditas', 'fundamental', 'fundamentals',
        );
    }
}

if (!function_exists('bitmomo_legacy_post_research_classification')) {
    function bitmomo_legacy_post_research_classification($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id || !has_category('riset', $post_id)) return 'unclassified';

        $has_ai = has_tag('ai-lab', $post_id);
        $has_market = false;
        foreach (bitmomo_market_research_taxonomy_slugs() as $slug) {
            if (has_category($slug, $post_id) || has_tag($slug, $post_id)) {
                $has_market = true;
                break;
            }
        }

        // Ambiguous legacy classification fails closed instead of choosing a side.
        if ($has_ai && $has_market) return 'unclassified';
        if ($has_ai) return 'ai-systems';
        if ($has_market) return 'market';
        return 'unclassified';
    }
}

if (!function_exists('bitmomo_post_research_classification')) {
    function bitmomo_post_research_classification($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return 'unclassified';

        if (!bitmomo_research_taxonomy_is_active()) {
            return bitmomo_legacy_post_research_classification($post_id);
        }

        // Desk is the identity owner, but `Riset` remains the canonical route
        // container. A split state fails closed even if terms were corrupted.
        if (!has_category('riset', $post_id)) return 'unclassified';

        $desk = bitmomo_post_research_desk_slug($post_id);
        if ('' === $desk) return 'unclassified';

        $definitions = bitmomo_research_desk_definitions();
        return isset($definitions[$desk]['classification'])
            ? (string) $definitions[$desk]['classification']
            : 'unclassified';
    }
}

if (!function_exists('bitmomo_post_is_market_research')) {
    function bitmomo_post_is_market_research($post_id = 0) {
        return 'market' === bitmomo_post_research_classification($post_id);
    }
}

if (!function_exists('bitmomo_post_is_ai_systems_research')) {
    function bitmomo_post_is_ai_systems_research($post_id = 0) {
        return 'ai-systems' === bitmomo_post_research_classification($post_id);
    }
}

if (!function_exists('bitmomo_post_publication_label')) {
    function bitmomo_post_publication_label($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        $classification = bitmomo_post_research_classification($post_id);
        if ('market' === $classification) return 'MARKET RESEARCH';
        if ('ai-systems' === $classification) return 'AI & INTELLIGENCE SYSTEMS';
        $categories = get_the_category($post_id);
        foreach ($categories as $category) {
            if ('riset' === $category->slug) continue;
            return strtoupper((string) $category->name);
        }
        return 'PUBLIKASI';
    }
}

if (!function_exists('bitmomo_research_focus_filters')) {
    function bitmomo_research_focus_filters() {
        return array(
            'all' => array(
                'label' => 'Semua Riset',
                'discipline' => 'all',
                'terms' => array(),
                'legacy_terms' => array(),
            ),
            'market' => array(
                'label' => 'Market Research',
                'discipline' => 'market',
                'terms' => array(),
                'legacy_terms' => array(),
            ),
            'bitcoin' => array(
                'label' => 'Bitcoin',
                'discipline' => 'market',
                'terms' => array('bitcoin'),
                'legacy_terms' => array('bitcoin', 'btc'),
            ),
            'macro' => array(
                'label' => 'Macro',
                'discipline' => 'market',
                'terms' => array('macro'),
                'legacy_terms' => array('macro', 'makro'),
            ),
            'market-structure' => array(
                'label' => 'Market Structure',
                'discipline' => 'market',
                'terms' => array('market-structure'),
                'legacy_terms' => array('market-structure'),
            ),
            'derivatives' => array(
                'label' => 'Derivatives',
                'discipline' => 'market',
                'terms' => array('derivatives'),
                'legacy_terms' => array('derivatives', 'funding-rate'),
            ),
            'flows' => array(
                'label' => 'ETF & Flows',
                'discipline' => 'market',
                'terms' => array('etf-flows'),
                'legacy_terms' => array('etf'),
            ),
            'liquidity' => array(
                'label' => 'Liquidity',
                'discipline' => 'market',
                'terms' => array('liquidity'),
                'legacy_terms' => array('liquidity', 'likuiditas'),
            ),
            'systems' => array(
                'label' => 'AI & Intelligence Systems',
                'discipline' => 'ai-systems',
                'terms' => array(),
                'legacy_terms' => array('ai-lab'),
            ),
            'inference' => array(
                'label' => 'Inference',
                'discipline' => 'ai-systems',
                'terms' => array('inference'),
                'legacy_terms' => array('inference'),
            ),
            'compute' => array(
                'label' => 'Compute',
                'discipline' => 'ai-systems',
                'terms' => array('compute'),
                'legacy_terms' => array('compute'),
            ),
            'models' => array(
                'label' => 'Models',
                'discipline' => 'ai-systems',
                'terms' => array('models'),
                'legacy_terms' => array('models', 'ai-models'),
            ),
            'agents' => array(
                'label' => 'AI Agents',
                'discipline' => 'ai-systems',
                'terms' => array('agents'),
                'legacy_terms' => array('agents', 'ai-agents'),
            ),
            'evaluation' => array(
                'label' => 'Evaluation',
                'discipline' => 'ai-systems',
                'terms' => array('evaluation'),
                'legacy_terms' => array('evaluation'),
            ),
            'provenance' => array(
                'label' => 'Data Provenance',
                'discipline' => 'ai-systems',
                'terms' => array('provenance'),
                'legacy_terms' => array('provenance', 'data-provenance'),
            ),
            'ai-infrastructure' => array(
                'label' => 'AI Infrastructure',
                'discipline' => 'ai-systems',
                'terms' => array('ai-infrastructure'),
                'legacy_terms' => array('ai-infrastructure'),
            ),
        );
    }
}

if (!function_exists('bitmomo_post_matches_research_focus')) {
    function bitmomo_post_matches_research_focus($post_id, $focus) {
        $post_id = (int) $post_id;
        $filters = bitmomo_research_focus_filters();
        $focus = isset($filters[$focus]) ? (string) $focus : 'all';
        $classification = bitmomo_post_research_classification($post_id);
        if ('unclassified' === $classification) return false;
        if ('all' === $focus) return true;

        $filter = $filters[$focus];
        if ((string) $filter['discipline'] !== $classification) return false;
        if (empty($filter['terms'])) return true;

        if (bitmomo_research_taxonomy_is_active()) {
            $topics = bitmomo_post_research_topic_slugs($post_id);
            return (bool) array_intersect($filter['terms'], $topics);
        }

        foreach ($filter['legacy_terms'] as $slug) {
            if (has_category($slug, $post_id) || has_tag($slug, $post_id)) return true;
        }
        return false;
    }
}

if (!function_exists('bitmomo_post_research_topic_label')) {
    function bitmomo_post_research_topic_label($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        $classification = bitmomo_post_research_classification($post_id);
        if ('unclassified' === $classification) return 'Research';

        if (bitmomo_research_taxonomy_is_active()) {
            $topics = bitmomo_post_research_topic_slugs($post_id);
            $labels = bitmomo_research_topic_definitions();
            $priority = array(
                'etf-flows', 'derivatives', 'market-structure', 'macro', 'liquidity',
                'fundamentals', 'bitcoin', 'inference', 'compute', 'evaluation',
                'provenance', 'agents', 'ai-infrastructure', 'models',
                'decentralized-ai', 'industry-society',
            );
            foreach ($priority as $slug) {
                if (in_array($slug, $topics, true) && isset($labels[$slug])) return $labels[$slug];
            }
            return 'ai-systems' === $classification ? 'AI & Intelligence Systems' : 'Market Research';
        }

        if ('ai-systems' === $classification) {
            $ai_priority = array(
                'inference' => 'Inference', 'compute' => 'Compute', 'models' => 'Models',
                'ai-models' => 'Models', 'agents' => 'AI Agents', 'ai-agents' => 'AI Agents',
                'evaluation' => 'Evaluation', 'provenance' => 'Data Provenance',
                'data-provenance' => 'Data Provenance', 'ai-infrastructure' => 'AI Infrastructure',
            );
            foreach ($ai_priority as $slug => $label) {
                if (has_category($slug, $post_id) || has_tag($slug, $post_id)) return $label;
            }
            return 'AI & Intelligence Systems';
        }

        $priority = array(
            'etf' => 'ETF & Flows', 'funding-rate' => 'Derivatives', 'derivatives' => 'Derivatives',
            'market-structure' => 'Market Structure', 'macro' => 'Macro', 'makro' => 'Macro',
            'liquidity' => 'Liquidity', 'likuiditas' => 'Liquidity', 'fundamental' => 'Fundamentals',
            'fundamentals' => 'Fundamentals', 'bitcoin' => 'Bitcoin', 'btc' => 'Bitcoin',
        );
        foreach ($priority as $slug => $label) {
            if (has_category($slug, $post_id) || has_tag($slug, $post_id)) return $label;
        }
        return 'Market Research';
    }
}

if (!function_exists('bitmomo_research_query_tax_query')) {
    /** Build the strict V3 taxonomy query for the Research Hub. */
    function bitmomo_research_query_tax_query($focus = 'all') {
        if (!bitmomo_research_taxonomy_is_active()) return array();

        $filters = bitmomo_research_focus_filters();
        $focus = isset($filters[$focus]) ? (string) $focus : 'all';
        $filter = $filters[$focus];

        if ('market' === $filter['discipline']) {
            $desk_terms = array('market-research');
        } elseif ('ai-systems' === $filter['discipline']) {
            $desk_terms = array('intelligence-systems');
        } else {
            $desk_terms = array_keys(bitmomo_research_desk_definitions());
        }

        $clauses = array(
            array(
                'taxonomy' => bitmomo_research_desk_taxonomy(),
                'field'    => 'slug',
                'terms'    => $desk_terms,
            ),
        );

        if (in_array($filter['discipline'], array('market', 'ai-systems'), true) && !empty($filter['terms'])) {
            $clauses[] = array(
                'taxonomy' => bitmomo_research_topic_taxonomy(),
                'field'    => 'slug',
                'terms'    => $filter['terms'],
            );
        }

        if (1 === count($clauses)) return $clauses;
        return array_merge(array('relation' => 'AND'), $clauses);
    }
}

if (!function_exists('bitmomo_research_query_args')) {
    /**
     * Canonical strict query arguments. Call only when V3 is active.
     *
     * @param string $focus Active Research focus.
     * @param string $search Optional search string.
     * @param array  $overrides WP_Query overrides.
     */
    function bitmomo_research_query_args($focus = 'all', $search = '', $overrides = array()) {
        $args = array(
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
            'tax_query'              => bitmomo_research_query_tax_query($focus),
        );
        $search = trim((string) $search);
        if ('' !== $search) $args['s'] = $search;
        return array_replace($args, $overrides);
    }
}

if (!function_exists('bitmomo_research_focus_has_posts')) {
    function bitmomo_research_focus_has_posts($focus, $search = '') {
        if (!bitmomo_research_taxonomy_is_active()) return false;
        $query = new WP_Query(bitmomo_research_query_args(
            $focus,
            $search,
            array(
                'posts_per_page' => 4,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        ));
        foreach ($query->posts as $post_id) {
            if (bitmomo_post_matches_research_focus((int) $post_id, $focus)) return true;
        }
        return false;
    }
}

if (!function_exists('bitmomo_research_editor_meta_box')) {
    function bitmomo_research_editor_meta_box() {
        add_meta_box(
            'bitmomo-research-classification',
            __('Bitmomo Research Classification', 'bitmomo'),
            'bitmomo_render_research_editor_meta_box',
            'post',
            'side',
            'high'
        );
    }
}
add_action('add_meta_boxes_post', 'bitmomo_research_editor_meta_box');

if (!function_exists('bitmomo_render_research_editor_meta_box')) {
    function bitmomo_render_research_editor_meta_box($post) {
        $post_id = (int) $post->ID;
        $desk_terms = wp_get_object_terms($post_id, bitmomo_research_desk_taxonomy(), array('fields' => 'slugs'));
        $topic_terms = wp_get_object_terms($post_id, bitmomo_research_topic_taxonomy(), array('fields' => 'slugs'));
        $selected_desk = (!is_wp_error($desk_terms) && 1 === count($desk_terms)) ? sanitize_key((string) reset($desk_terms)) : '';
        $selected_topics = is_wp_error($topic_terms) ? array() : array_map('sanitize_key', $topic_terms);

        wp_nonce_field('bitmomo_save_research_classification', 'bitmomo_research_classification_nonce');
        echo '<p><strong>' . esc_html__('Research desk', 'bitmomo') . '</strong></p>';
        echo '<p><label><input type="radio" name="bitmomo_research_desk" value="" ' . checked('', $selected_desk, false) . '> ' . esc_html__('Not institutional research', 'bitmomo') . '</label></p>';
        foreach (bitmomo_research_desk_definitions() as $slug => $definition) {
            echo '<p><label><input type="radio" name="bitmomo_research_desk" value="' . esc_attr($slug) . '" ' . checked($slug, $selected_desk, false) . '> ' . esc_html($definition['label']) . '</label></p>';
        }

        echo '<hr><p><strong>' . esc_html__('Research topics', 'bitmomo') . '</strong></p>';
        foreach (bitmomo_research_topic_definitions() as $slug => $label) {
            echo '<p><label><input type="checkbox" name="bitmomo_research_topics[]" value="' . esc_attr($slug) . '" ' . checked(in_array($slug, $selected_topics, true), true, false) . '> ' . esc_html($label) . '</label></p>';
        }

        if (!bitmomo_research_taxonomy_is_active()) {
            echo '<p><small>' . esc_html__('V3 metadata can be prepared now; public classification remains on the migration-safe legacy boundary until the audited migration activates V3.', 'bitmomo') . '</small></p>';
        }
    }
}

if (!function_exists('bitmomo_enforce_research_desk_container')) {
    /**
     * Research Desk is the institutional identity owner while `Riset` remains
     * the canonical route/container. Enforce the invariant at the taxonomy
     * write boundary so editor, WP-CLI migration, REST, and future tooling all
     * converge on the same state.
     *
     * Removing a desk deliberately keeps `Riset`; the post then becomes an
     * unclassified archive item until editorial review.
     */
    function bitmomo_enforce_research_desk_container($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        unset($terms, $append, $old_tt_ids);
        if (bitmomo_research_desk_taxonomy() !== (string) $taxonomy || empty($tt_ids)) return;

        $post_id = (int) $object_id;
        if (!$post_id || 'post' !== get_post_type($post_id)) return;

        $riset = get_category_by_slug('riset');
        if (!$riset || is_wp_error($riset)) return;

        $category_ids = wp_get_post_categories($post_id);
        if (is_wp_error($category_ids)) return;

        $category_ids = array_map('intval', (array) $category_ids);
        $riset_id = (int) $riset->term_id;
        if (in_array($riset_id, $category_ids, true)) return;

        $category_ids[] = $riset_id;
        wp_set_post_categories($post_id, array_values(array_unique($category_ids)), false);
    }
}
add_action('set_object_terms', 'bitmomo_enforce_research_desk_container', 10, 6);

if (!function_exists('bitmomo_save_research_editor_meta_box')) {
    function bitmomo_save_research_editor_meta_box($post_id) {
        $post_id = (int) $post_id;
        if (!$post_id || wp_is_post_revision($post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['bitmomo_research_classification_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bitmomo_research_classification_nonce'])), 'bitmomo_save_research_classification')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $desk = isset($_POST['bitmomo_research_desk']) ? sanitize_key(wp_unslash($_POST['bitmomo_research_desk'])) : '';
        $desks = bitmomo_research_desk_definitions();
        wp_set_object_terms(
            $post_id,
            isset($desks[$desk]) ? array($desk) : array(),
            bitmomo_research_desk_taxonomy(),
            false
        );

        $submitted_topics = isset($_POST['bitmomo_research_topics']) && is_array($_POST['bitmomo_research_topics'])
            ? array_map('sanitize_key', wp_unslash($_POST['bitmomo_research_topics']))
            : array();
        $allowed_topics = array_keys(bitmomo_research_topic_definitions());
        $topics = array_values(array_intersect($allowed_topics, $submitted_topics));
        wp_set_object_terms($post_id, $topics, bitmomo_research_topic_taxonomy(), false);
    }
}
add_action('save_post_post', 'bitmomo_save_research_editor_meta_box');

if (!function_exists('bitmomo_post_reading_minutes')) {
    function bitmomo_post_reading_minutes($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return 1;
        $content = (string) get_post_field('post_content', $post_id);
        $words = str_word_count(wp_strip_all_tags(strip_shortcodes($content)));
        return max(1, (int) ceil($words / 220));
    }
}

if (!function_exists('bitmomo_post_manual_deck')) {
    function bitmomo_post_manual_deck($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return '';
        $excerpt = trim((string) get_post_field('post_excerpt', $post_id));
        if ('' === $excerpt) return '';
        return trim(wp_strip_all_tags(strip_shortcodes($excerpt)));
    }
}

if (!function_exists('bitmomo_post_has_meaningful_update')) {
    function bitmomo_post_has_meaningful_update($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) return false;
        $published = (int) get_post_time('U', true, $post_id);
        $modified  = (int) get_post_modified_time('U', true, $post_id);
        return $published > 0 && $modified > ($published + DAY_IN_SECONDS);
    }
}

if (!function_exists('bitmomo_normalize_public_page_body_headings')) {
    function bitmomo_normalize_public_page_body_headings($html) {
        $source = (string) $html;
        $normalized = preg_replace(
            array('/<h1(\b[^>]*)>/i', '/<\/h1\s*>/i'),
            array('<h2$1>', '</h2>'),
            $source
        );
        return is_string($normalized) ? $normalized : $source;
    }
}

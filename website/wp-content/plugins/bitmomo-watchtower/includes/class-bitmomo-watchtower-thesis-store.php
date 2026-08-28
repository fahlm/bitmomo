<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistence layer for the current thesis/state model (WATCHTOWER PR 2).
 *
 * This is the ONLY class in bitmomo-watchtower that touches WordPress
 * state so far — Bitmomo_Watchtower_Thesis, Bitmomo_Watchtower_Event_Input,
 * Bitmomo_Watchtower_Materiality_Engine, and Bitmomo_Watchtower_Deduplicator
 * all remain pure PHP with no WordPress dependency. Mirrors
 * Bitmomo_Regime_State_Store's separation of pure domain logic from the
 * WP-touching seam.
 *
 * APPEND-ONLY, same convention as bitmomo-regime and bitmomo-pro: a new
 * post per thesis update, never an overwrite of an earlier one. WATCHTOWER
 * PR 3's state transition engine needs to compare "the newly computed
 * thesis" against "the thesis currently on record" — that comparison is
 * meaningless if history can be silently rewritten.
 *
 * NO CRON, NO AUTOMATIC EVALUATION HERE. set_current() is a plain
 * callable for a future scheduled job (Codex's runtime work, and/or
 * WATCHTOWER PR 3's state transition engine) to call.
 */
class Bitmomo_Watchtower_Thesis_Store {

	const POST_TYPE = 'bm_watchtower_thesis';

	const META_KEYS = array(
		'as_of',
		'regime',
		'directional_bias',
		'confidence',
		'expected_range_low',
		'expected_range_high',
		'invalidation',
		'major_driver',
		'source_record_id',
	);

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Headless, private, non-public — identical trust posture to
	 * bitmomo-regime's `bm_regime_state` post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Watchtower Thesis Records', 'bitmomo-watchtower' ),
					'singular_name' => __( 'Watchtower Thesis Record', 'bitmomo-watchtower' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * Validates and appends a new thesis record. Does NOT compare against
	 * the previous thesis or decide whether anything "changed" — that
	 * decision belongs to WATCHTOWER PR 3's state transition engine. This
	 * method's only job is fail-closed validation plus append-only write.
	 *
	 * @param array       $raw_thesis See Bitmomo_Watchtower_Thesis::REQUIRED_FIELDS.
	 * @param string|null $as_of      YYYY-MM-DD HH:MM:SS, defaults to current_time('mysql').
	 *
	 * @return array{success:bool,errors:string[],record:?array}
	 */
	public function set_current( $raw_thesis, $as_of = null ) {
		$validation = Bitmomo_Watchtower_Thesis::validate( $raw_thesis );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'errors'  => $validation['errors'],
				'record'  => null,
			);
		}

		$as_of = $as_of ? $as_of : current_time( 'mysql' );
		$state = array_merge( array( 'as_of' => $as_of ), $validation['input'] );

		$post_id = $this->record( $state );
		if ( ! $post_id ) {
			return array(
				'success' => false,
				'errors'  => array( 'Failed to persist the thesis record.' ),
				'record'  => null,
			);
		}

		$state['id'] = $post_id;
		return array(
			'success' => true,
			'errors'  => array(),
			'record'  => $state,
		);
	}

	private function record( $state ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Watchtower Thesis — %s', $state['as_of'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		foreach ( self::META_KEYS as $key ) {
			if ( ! array_key_exists( $key, $state ) ) {
				continue;
			}
			update_post_meta( $post_id, '_bitmomo_watchtower_thesis_' . $key, $state[ $key ] );
		}

		return (int) $post_id;
	}

	/**
	 * The current thesis — what WATCHTOWER PR 3's transition engine
	 * compares a new evaluation against. Null if none has ever been set.
	 */
	public function get_current() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return $this->hydrate( $posts[0] );
	}

	/**
	 * Up to $limit most recent thesis records, newest first — an audit
	 * trail of how the thesis has evolved. Never fabricates missing
	 * history.
	 *
	 * @return array[]
	 */
	public function get_history( $limit = 30 ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $limit ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		$records = array();
		foreach ( $posts as $post ) {
			$records[] = $this->hydrate( $post );
		}
		return $records;
	}

	private function hydrate( $post ) {
		$record = array(
			'id'   => $post->ID,
			'date' => get_the_date( 'Y-m-d H:i:s', $post ),
		);

		foreach ( self::META_KEYS as $key ) {
			$record[ $key ] = get_post_meta( $post->ID, '_bitmomo_watchtower_thesis_' . $key, true );
		}

		return $record;
	}
}

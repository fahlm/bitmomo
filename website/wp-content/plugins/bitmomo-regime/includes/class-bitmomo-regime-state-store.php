<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistence layer for Product A (REGIME PR 2) — the versioned daily
 * state record and the orchestration entrypoint that ties validation ->
 * classification -> hysteresis -> storage together.
 *
 * This is the ONLY class in bitmomo-regime that touches WordPress state
 * (a private custom post type, `bm_regime_state`). Bitmomo_Regime_Input,
 * Bitmomo_Regime_Classifier, and Bitmomo_Regime_Hysteresis remain pure
 * PHP with no WordPress dependency — this class is the seam between them
 * and the database, mirroring how bitmomo-pro isolates side-effecting
 * code (e.g. Bitmomo_Pro_Briefs::save_meta()) from pure domain logic
 * (e.g. Bitmomo_Pro_Brief_Readiness::evaluate()).
 *
 * APPEND-ONLY: record() always creates a NEW post; it never updates an
 * existing state record's classification after the fact. This is what
 * "history must not be overwritten by the next evaluation" (PR spec,
 * A5) means in practice — every evaluation, confirmed or merely pending,
 * is preserved. This is the same pattern Bitmomo_Pro_Briefs uses for
 * daily Pro briefs: a new post per evaluation, old ones untouched.
 *
 * NO CRON IS REGISTERED HERE. evaluate_and_record() exists for a future
 * scheduled job (Codex's runtime work, not this PR) to call. Nothing in
 * this class fetches market data or runs automatically — see the Codex
 * handoff contract in this PR's report.
 */
class Bitmomo_Regime_State_Store {

	const POST_TYPE = 'bm_regime_state';

	/**
	 * Meta keys are stored with this prefix, e.g. `_bitmomo_regime_regime`.
	 * Kept in one list so record()/hydrate() can never drift apart on
	 * which fields exist.
	 */
	const META_KEYS = array(
		'as_of',
		'source_record_id',
		'regime',
		'candidate_regime',
		'directional_bias',
		'regime_confidence',
		'directional_confidence',
		'evidence',
		'conflicts',
		'input_summary',
		'input_hash',
		'classifier_version',
		'transition_strength',
		'transition_reason',
		'pending_regime',
		'pending_streak',
		'edition',
		'provenance',
	);

	/**
	 * Meta values that are arrays and must be JSON-encoded/decoded on the
	 * way in/out of post meta (WordPress stores scalars natively but not
	 * arrays without serialization footguns — JSON is explicit and
	 * inspectable in the database).
	 */
	const JSON_META_KEYS = array( 'evidence', 'conflicts', 'input_summary' );

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
	 * Deliberately headless: no admin UI of its own (show_ui false), no
	 * public URL, no REST route. These are auto-generated evaluation
	 * records, not hand-edited content — REGIME PR 3's admin diagnostics
	 * screen is the purpose-built way to inspect them, not the default
	 * post-list screen.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Regime State Records', 'bitmomo-regime' ),
					'singular_name' => __( 'Regime State Record', 'bitmomo-regime' ),
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
	 * The orchestration entrypoint: raw (not yet normalized) input in,
	 * a new persisted state record out. Runs, in order: input validation
	 * (fails closed, never fabricates), classification (deterministic,
	 * see Bitmomo_Regime_Classifier), hysteresis against the current
	 * latest record (see Bitmomo_Regime_Hysteresis), then an append-only
	 * write.
	 *
	 * @param array       $raw_input See Bitmomo_Regime_Input::REQUIRED_FIELDS/OPTIONAL_FIELDS.
	 * @param string|null $as_of     YYYY-MM-DD HH:MM:SS, defaults to current_time('mysql').
	 *
	 * @return array{success:bool,errors:string[],record:?array}
	 */
	public function evaluate_and_record( $raw_input, $as_of = null ) {
		$validation = Bitmomo_Regime_Input::validate( $raw_input );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'errors'  => $validation['errors'],
				'record'  => null,
			);
		}

		$input      = $validation['input'];
		$classifier = new Bitmomo_Regime_Classifier();
		$result     = $classifier->evaluate( $input );

		$previous   = $this->get_latest();
		$hysteresis = new Bitmomo_Regime_Hysteresis();
		$decision   = $hysteresis->apply( $result, $previous );

		$as_of = $as_of ? $as_of : current_time( 'mysql' );

		$state = array(
			'as_of'                  => $as_of,
			'source_record_id'       => isset( $input['source_record_id'] ) ? $input['source_record_id'] : '',
			'regime'                 => $decision['regime'],
			'candidate_regime'       => $decision['candidate_regime'],
			'directional_bias'       => $result['directional_bias'],
			'regime_confidence'      => $result['confidence'],
			'directional_confidence' => $result['directional_confidence'],
			'evidence'               => $result['evidence'],
			'conflicts'              => $result['conflicts'],
			'input_summary'          => $this->summarize_input( $input ),
			'input_hash'             => md5( wp_json_encode( $input ) ),
			'classifier_version'     => $result['classifier_version'],
			'transition_strength'    => $decision['transition_strength'],
			'transition_reason'      => $decision['transition_reason'],
			'pending_regime'         => $decision['pending_regime'],
			'pending_streak'         => $decision['pending_streak'],
			'edition'                => in_array( ( $raw_input['edition'] ?? '' ), array( 'morning', 'us_session' ), true ) ? $raw_input['edition'] : 'us_session',
			'provenance'             => ( $raw_input['provenance'] ?? '' ) === 'historical_reconstruction' ? 'historical_reconstruction' : 'recorded_live',
		);

		$post_id = $this->record( $state );
		if ( ! $post_id ) {
			return array(
				'success' => false,
				'errors'  => array( 'Failed to persist the state record.' ),
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

	/**
	 * Compact, frontend-safe-ish headline numbers only — not the full raw
	 * input — stored alongside each record for admin diagnostics (PR3).
	 */
	private function summarize_input( $input ) {
		return array(
			'return_1d'             => $input['return_1d'],
			'return_7d'             => $input['return_7d'],
			'return_30d'            => $input['return_30d'],
			'volatility_percentile' => $input['volatility_percentile'],
			'volume_percentile'     => $input['volume_percentile'],
			'range_position_pct'    => $input['range_position_pct'],
			'structure_state'       => $input['structure_state'],
		);
	}

	/**
	 * Always INSERTS a new post — see the class docblock on why this is
	 * append-only. Returns the new post ID, or 0 on failure.
	 */
	private function record( $state ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Regime State — %s', $state['as_of'] ),
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
			$value = $state[ $key ];
			if ( in_array( $key, self::JSON_META_KEYS, true ) ) {
				$value = wp_json_encode( $value );
			}
			update_post_meta( $post_id, '_bitmomo_regime_' . $key, $value );
		}

		return (int) $post_id;
	}

	/**
	 * The most recent OFFICIAL state record — what hysteresis compares
	 * against, and what a "current state" consumer (PR3's shortcode)
	 * should read. Null if nothing has ever been recorded.
	 */
	public function get_latest() {
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
	 * Up to $limit most recent records, newest first. Never fabricates
	 * missing history — if fewer than $limit records exist, fewer than
	 * $limit are returned; PR3's history reader is what reports
	 * available_days vs target_days to the frontend.
	 *
	 * @return array[]
	 */
	public function get_recent( $limit = 30 ) {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $limit * 2 ),
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
			$raw = get_post_meta( $post->ID, '_bitmomo_regime_' . $key, true );
			if ( in_array( $key, self::JSON_META_KEYS, true ) ) {
				$decoded         = json_decode( (string) $raw, true );
				$record[ $key ]  = is_array( $decoded ) ? $decoded : array();
			} else {
				$record[ $key ] = $raw;
			}
		}

		return $record;
	}
}

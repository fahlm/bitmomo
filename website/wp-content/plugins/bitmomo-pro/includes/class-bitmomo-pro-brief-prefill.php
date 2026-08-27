<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The RECEIVING side of canonical-intelligence → Pro-brief prefill.
 *
 * This class does not fetch anything and does not call bitmomo-ai. It only
 * accepts an already-normalized payload and maps it into a new bm_pro_brief
 * DRAFT — publishing still requires a human. Codex's canonical adapter is
 * being built independently; this file exists so that once it's ready,
 * wiring it up is a small addition here rather than a redesign of the
 * bm_pro_brief content type.
 *
 * ============================================================
 * CODEX HANDOFF CONTRACT — read this before wiring the adapter up
 * ============================================================
 *
 * Two ways to call in, pick whichever fits the adapter's own design:
 *
 * 1) Direct method call (preferred — you get the return value):
 *      $result = Bitmomo_Pro_Brief_Prefill::instance()->create_draft_from_source( $payload );
 *
 * 2) Filter-based admin UI (no code call needed from the adapter's request
 *    path at all — just make the latest payload available):
 *      add_filter( 'bitmomo_pro_available_source_payload', function( $payload ) {
 *          return [ ...normalized payload as below... ];
 *      } );
 *    When this filter returns a non-null array, the Pro Briefs admin list
 *    screen shows a "Buat draft dari canonical record terbaru" button that
 *    calls create_draft_from_source() with exactly what the filter
 *    returned. By default the filter returns null and nothing renders —
 *    no fake data source, no dead UI, until something real is wired in.
 *
 * INPUT SCHEMA ($payload — associative array):
 *   Required:
 *     source_record_id      string   Canonical record identifier. Used for
 *                                     idempotency — see below.
 *     btc_reference_price   numeric  > 0
 *     market_state          string   one of 'bullish', 'neutral', 'bearish'
 *                                     (Bitmomo_Pro_Briefs::MARKET_STATES)
 *     confidence             numeric  0–100
 *     expected_range_low    numeric
 *     expected_range_high   numeric  must be >= expected_range_low
 *     data_timestamp        string   anything strtotime() can parse
 *     data_freshness_status string   one of 'fresh', 'delayed',
 *                                     'unavailable'
 *                                     (Bitmomo_Pro_Briefs::FRESHNESS_STATES)
 *   Optional:
 *     invalidation           string
 *
 *   Deliberately NOT accepted — these stay blank on the draft for the
 *   founder/editor to write by hand: confidence_explanation,
 *   base_scenario, bull_scenario, bear_scenario, what_changed.
 *
 * RETURN SHAPE:
 *   array(
 *     'status'   => 'created' | 'duplicate' | 'error',
 *     'post_id'  => int|null,
 *     'edit_url' => string|null,
 *     'errors'   => array<string>  // populated only when status === 'error'
 *   )
 *
 * DUPLICATE BEHAVIOR: if a bm_pro_brief (any status) already carries
 * _bitmomo_pro_source_record_id === $payload['source_record_id'], no new
 * post is created. Returns status 'duplicate' pointing at the existing
 * draft. This is the ONLY de-duplication key — call with a stable,
 * genuinely unique source_record_id per canonical record.
 *
 * ERROR BEHAVIOR: any required field missing, wrong type, or out of range
 * — including expected_range_low > expected_range_high or an unparseable
 * data_timestamp — returns status 'error' with specific messages and
 * creates nothing. No partial/garbage draft is ever created.
 *
 * There is no remote endpoint and no unauthenticated route here — the
 * filter-based UI path requires 'edit_posts' capability and a nonce, same
 * as any other wp-admin action.
 */
class Bitmomo_Pro_Brief_Prefill {

	const REQUIRED_FIELDS = array(
		'source_record_id',
		'btc_reference_price',
		'market_state',
		'confidence',
		'expected_range_low',
		'expected_range_high',
		'data_timestamp',
		'data_freshness_status',
	);

	const AVAILABILITY_FILTER = 'bitmomo_pro_available_source_payload';
	const INGEST_ACTION       = 'bitmomo_pro_admin_ingest_source_payload';
	const INGEST_NONCE_ACTION = 'bitmomo_pro_ingest_source_payload';
	const INGEST_NONCE_FIELD  = 'bitmomo_pro_ingest_nonce';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_render_prefill_notice' ) );
		add_action( 'admin_post_' . self::INGEST_ACTION, array( $this, 'handle_admin_ingest' ) );
	}

	/**
	 * Validates and maps a normalized payload into a new bm_pro_brief
	 * draft. See the class docblock above for the full contract.
	 *
	 * @param array $payload
	 * @return array{status:string,post_id:?int,edit_url:?string,errors:array}
	 */
	public function create_draft_from_source( $payload ) {
		$errors = $this->validate( $payload );
		if ( ! empty( $errors ) ) {
			return array(
				'status'   => 'error',
				'post_id'  => null,
				'edit_url' => null,
				'errors'   => $errors,
			);
		}

		$existing_id = $this->find_existing_by_source_id( $payload['source_record_id'] );
		if ( $existing_id ) {
			return array(
				'status'   => 'duplicate',
				'post_id'  => $existing_id,
				'edit_url' => get_edit_post_link( $existing_id, '' ),
				'errors'   => array(),
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Bitmomo_Pro_Briefs::POST_TYPE,
				'post_status' => 'draft',
				/* translators: %s: canonical source record's reported data timestamp */
				'post_title'  => sprintf( __( 'Pro Brief draft — %s', 'bitmomo-pro' ), sanitize_text_field( $payload['data_timestamp'] ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array(
				'status'   => 'error',
				'post_id'  => null,
				'edit_url' => null,
				'errors'   => array( __( 'Failed to create draft post.', 'bitmomo-pro' ) ),
			);
		}

		$fields = array(
			'market_state'           => sanitize_key( $payload['market_state'] ),
			'btc_reference_price'    => floatval( $payload['btc_reference_price'] ),
			'confidence'             => floatval( $payload['confidence'] ),
			'expected_range_low'     => floatval( $payload['expected_range_low'] ),
			'expected_range_high'    => floatval( $payload['expected_range_high'] ),
			'data_timestamp'         => sanitize_text_field( $payload['data_timestamp'] ),
			'data_freshness_status'  => sanitize_key( $payload['data_freshness_status'] ),
			'source_record_id'       => sanitize_text_field( $payload['source_record_id'] ),
		);
		if ( ! empty( $payload['invalidation'] ) ) {
			$fields['invalidation'] = sanitize_textarea_field( $payload['invalidation'] );
		}

		foreach ( $fields as $key => $value ) {
			update_post_meta( $post_id, '_bitmomo_pro_' . $key, $value );
		}

		/**
		 * Fires after a Pro brief draft is prefilled from a canonical
		 * source payload. Informational only — nothing in this plugin
		 * subscribes to it yet.
		 */
		do_action( 'bitmomo_pro_brief_prefilled', $post_id, $payload );

		return array(
			'status'   => 'created',
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, '' ),
			'errors'   => array(),
		);
	}

	private function validate( $payload ) {
		if ( ! is_array( $payload ) ) {
			return array( __( 'Payload must be an array.', 'bitmomo-pro' ) );
		}

		$errors = array();
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( ! isset( $payload[ $field ] ) || '' === $payload[ $field ] ) {
				/* translators: %s: missing field name */
				$errors[] = sprintf( __( 'Missing required field: %s', 'bitmomo-pro' ), $field );
			}
		}
		if ( ! empty( $errors ) ) {
			return $errors; // Don't attempt deeper checks against missing fields.
		}

		if ( ! in_array( $payload['market_state'], Bitmomo_Pro_Briefs::MARKET_STATES, true ) ) {
			$errors[] = __( 'market_state must be one of bullish/neutral/bearish.', 'bitmomo-pro' );
		}

		if ( ! is_numeric( $payload['btc_reference_price'] ) || floatval( $payload['btc_reference_price'] ) <= 0 ) {
			$errors[] = __( 'btc_reference_price must be a positive number.', 'bitmomo-pro' );
		}

		if ( ! is_numeric( $payload['confidence'] ) || floatval( $payload['confidence'] ) < 0 || floatval( $payload['confidence'] ) > 100 ) {
			$errors[] = __( 'confidence must be numeric between 0 and 100.', 'bitmomo-pro' );
		}

		if ( ! is_numeric( $payload['expected_range_low'] ) || ! is_numeric( $payload['expected_range_high'] ) ) {
			$errors[] = __( 'expected_range_low and expected_range_high must be numeric.', 'bitmomo-pro' );
		} elseif ( floatval( $payload['expected_range_low'] ) > floatval( $payload['expected_range_high'] ) ) {
			$errors[] = __( 'expected_range_low must not be greater than expected_range_high.', 'bitmomo-pro' );
		}

		if ( false === strtotime( (string) $payload['data_timestamp'] ) ) {
			$errors[] = __( 'data_timestamp is not a parseable date/time.', 'bitmomo-pro' );
		}

		if ( ! in_array( $payload['data_freshness_status'], Bitmomo_Pro_Briefs::FRESHNESS_STATES, true ) ) {
			$errors[] = __( 'data_freshness_status must be one of fresh/delayed/unavailable.', 'bitmomo-pro' );
		}

		if ( ! is_string( $payload['source_record_id'] ) || '' === trim( $payload['source_record_id'] ) ) {
			$errors[] = __( 'source_record_id must be a non-empty string.', 'bitmomo-pro' );
		}

		return $errors;
	}

	private function find_existing_by_source_id( $source_record_id ) {
		$posts = get_posts(
			array(
				'post_type'      => Bitmomo_Pro_Briefs::POST_TYPE,
				'post_status'    => array( 'draft', 'pending', 'publish', 'future' ),
				'posts_per_page' => 1,
				'meta_key'       => '_bitmomo_pro_source_record_id',
				'meta_value'     => sanitize_text_field( (string) $source_record_id ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Public wrapper around find_existing_by_source_id() so other classes
	 * (e.g. Bitmomo_Pro_Daily) can check for an existing draft/pending/
	 * publish/future post for a given canonical source_record_id without
	 * re-implementing this lookup with their own get_posts() query.
	 */
	public function find_draft_by_source_record_id( $source_record_id ) {
		return $this->find_existing_by_source_id( $source_record_id );
	}

	/**
	 * Renders a one-click "create draft" admin notice on the Pro Briefs
	 * screens, but ONLY when something has hooked
	 * apply_filters( self::AVAILABILITY_FILTER, null ) to return a real
	 * payload array. By default that filter returns null and this renders
	 * nothing — see the class docblock's handoff contract.
	 */
	public function maybe_render_prefill_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->post_type, Bitmomo_Pro_Briefs::POST_TYPE ) ) {
			return;
		}

		$payload = apply_filters( self::AVAILABILITY_FILTER, null );
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return;
		}

		$existing_id = isset( $payload['source_record_id'] ) ? $this->find_existing_by_source_id( $payload['source_record_id'] ) : 0;

		echo '<div class="notice notice-info"><p>';
		if ( $existing_id ) {
			printf(
				'%s <a href="%s">%s</a>',
				esc_html__( 'A draft already exists for the latest canonical source record.', 'bitmomo-pro' ),
				esc_url( (string) get_edit_post_link( $existing_id, '' ) ),
				esc_html__( 'Edit it', 'bitmomo-pro' )
			);
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
			wp_nonce_field( self::INGEST_NONCE_ACTION, self::INGEST_NONCE_FIELD );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::INGEST_ACTION ) . '" />';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Buat draft dari canonical record terbaru', 'bitmomo-pro' ) . '</button>';
			echo '</form>';
		}
		echo '</p></div>';
	}

	public function handle_admin_ingest() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		}

		if ( ! isset( $_POST[ self::INGEST_NONCE_FIELD ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::INGEST_NONCE_FIELD ] ) ), self::INGEST_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );
		}

		$payload  = apply_filters( self::AVAILABILITY_FILTER, null );
		$redirect = admin_url( 'edit.php?post_type=' . Bitmomo_Pro_Briefs::POST_TYPE );

		if ( is_array( $payload ) ) {
			$result = $this->create_draft_from_source( $payload );
			if ( in_array( $result['status'], array( 'created', 'duplicate' ), true ) && $result['post_id'] ) {
				$redirect = admin_url( 'post.php?post=' . $result['post_id'] . '&action=edit' );
			}
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}

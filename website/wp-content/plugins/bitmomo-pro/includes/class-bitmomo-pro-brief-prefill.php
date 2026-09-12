<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receiving side of canonical-intelligence → Pro-brief prefill.
 *
 * Preferred payload field: `directional_bias` (bullish/neutral/bearish).
 * `market_state` is accepted only as a backwards-compatible alias because
 * historical Pro storage used that name for directional bias. Actual regime
 * Market State is a separate canonical concept and must never be passed here.
 */
class Bitmomo_Pro_Brief_Prefill {

	const REQUIRED_FIELDS = array(
		'source_record_id',
		'btc_reference_price',
		'confidence',
		'data_timestamp',
		'data_freshness_status',
	);

	const AVAILABILITY_FILTER = 'bitmomo_pro_available_source_payload';
	const INGEST_ACTION       = 'bitmomo_pro_admin_ingest_source_payload';
	const INGEST_NONCE_ACTION = 'bitmomo_pro_ingest_source_payload';
	const INGEST_NONCE_FIELD  = 'bitmomo_pro_ingest_nonce';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'maybe_render_prefill_notice' ) );
		add_action( 'admin_post_' . self::INGEST_ACTION, array( $this, 'handle_admin_ingest' ) );
	}

	private function payload_bias( $payload ) {
		if ( isset( $payload['directional_bias'] ) && '' !== trim( (string) $payload['directional_bias'] ) ) {
			return sanitize_key( (string) $payload['directional_bias'] );
		}
		if ( isset( $payload['market_state'] ) && '' !== trim( (string) $payload['market_state'] ) ) {
			return sanitize_key( (string) $payload['market_state'] );
		}
		return '';
	}

	public function create_draft_from_source( $payload ) {
		$errors = $this->validate( $payload );
		if ( ! empty( $errors ) ) {
			return array( 'status' => 'error', 'post_id' => null, 'edit_url' => null, 'errors' => $errors );
		}

		$existing_id = $this->find_existing_by_source_id( $payload['source_record_id'] );
		if ( $existing_id ) {
			return array( 'status' => 'duplicate', 'post_id' => $existing_id, 'edit_url' => get_edit_post_link( $existing_id, '' ), 'errors' => array() );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Bitmomo_Pro_Briefs::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => sprintf( __( 'Pro Brief draft — %s', 'bitmomo-pro' ), sanitize_text_field( $payload['data_timestamp'] ) ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return array( 'status' => 'error', 'post_id' => null, 'edit_url' => null, 'errors' => array( __( 'Failed to create draft post.', 'bitmomo-pro' ) ) );
		}

		$fields = array(
			// Legacy storage key; the value is canonically Directional Bias.
			'market_state'          => $this->payload_bias( $payload ),
			'btc_reference_price'   => floatval( $payload['btc_reference_price'] ),
			'confidence'            => floatval( $payload['confidence'] ),
			'data_timestamp'        => sanitize_text_field( $payload['data_timestamp'] ),
			'data_freshness_status' => sanitize_key( $payload['data_freshness_status'] ),
			'source_record_id'      => sanitize_text_field( $payload['source_record_id'] ),
		);
		if ( isset( $payload['expected_range_low'], $payload['expected_range_high'] ) ) {
			$fields['expected_range_low']  = floatval( $payload['expected_range_low'] );
			$fields['expected_range_high'] = floatval( $payload['expected_range_high'] );
		}
		if ( ! empty( $payload['invalidation'] ) ) {
			$fields['invalidation'] = sanitize_textarea_field( $payload['invalidation'] );
		}

		foreach ( $fields as $key => $value ) {
			update_post_meta( $post_id, '_bitmomo_pro_' . $key, $value );
		}

		do_action( 'bitmomo_pro_brief_prefilled', $post_id, $payload );

		return array( 'status' => 'created', 'post_id' => $post_id, 'edit_url' => get_edit_post_link( $post_id, '' ), 'errors' => array() );
	}

	private function validate( $payload ) {
		if ( ! is_array( $payload ) ) return array( __( 'Payload must be an array.', 'bitmomo-pro' ) );

		$errors = array();
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( ! isset( $payload[ $field ] ) || '' === $payload[ $field ] ) {
				$errors[] = sprintf( __( 'Missing required field: %s', 'bitmomo-pro' ), $field );
			}
		}

		$bias = $this->payload_bias( $payload );
		if ( '' === $bias ) {
			$errors[] = __( 'Missing required field: directional_bias.', 'bitmomo-pro' );
		} elseif ( ! in_array( $bias, Bitmomo_Pro_Briefs::DIRECTIONAL_BIASES, true ) ) {
			$errors[] = __( 'directional_bias must be one of bullish/neutral/bearish.', 'bitmomo-pro' );
		}

		if ( ! empty( $errors ) ) return $errors;

		if ( ! is_numeric( $payload['btc_reference_price'] ) || floatval( $payload['btc_reference_price'] ) <= 0 ) {
			$errors[] = __( 'btc_reference_price must be a positive number.', 'bitmomo-pro' );
		}
		if ( ! is_numeric( $payload['confidence'] ) || floatval( $payload['confidence'] ) < 0 || floatval( $payload['confidence'] ) > 100 ) {
			$errors[] = __( 'confidence must be numeric between 0 and 100.', 'bitmomo-pro' );
		}

		$has_low  = isset( $payload['expected_range_low'] ) && '' !== $payload['expected_range_low'];
		$has_high = isset( $payload['expected_range_high'] ) && '' !== $payload['expected_range_high'];
		if ( $has_low !== $has_high ) {
			$errors[] = __( 'expected_range_low and expected_range_high must be supplied together.', 'bitmomo-pro' );
		} elseif ( $has_low && ( ! is_numeric( $payload['expected_range_low'] ) || ! is_numeric( $payload['expected_range_high'] ) ) ) {
			$errors[] = __( 'expected_range_low and expected_range_high must be numeric.', 'bitmomo-pro' );
		} elseif ( $has_low && floatval( $payload['expected_range_low'] ) > floatval( $payload['expected_range_high'] ) ) {
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

	public function find_draft_by_source_record_id( $source_record_id ) {
		return $this->find_existing_by_source_id( $source_record_id );
	}

	public function maybe_render_prefill_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->post_type, Bitmomo_Pro_Briefs::POST_TYPE ) ) return;

		$payload = apply_filters( self::AVAILABILITY_FILTER, null );
		if ( empty( $payload ) || ! is_array( $payload ) ) return;
		$existing_id = isset( $payload['source_record_id'] ) ? $this->find_existing_by_source_id( $payload['source_record_id'] ) : 0;

		echo '<div class="notice notice-info"><p>';
		if ( $existing_id ) {
			printf( '%s <a href="%s">%s</a>', esc_html__( 'A draft already exists for the latest canonical source record.', 'bitmomo-pro' ), esc_url( (string) get_edit_post_link( $existing_id, '' ) ), esc_html__( 'Edit it', 'bitmomo-pro' ) );
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
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( esc_html__( 'Not allowed.', 'bitmomo-pro' ) );
		if ( ! isset( $_POST[ self::INGEST_NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::INGEST_NONCE_FIELD ] ) ), self::INGEST_NONCE_ACTION ) ) wp_die( esc_html__( 'Security check failed.', 'bitmomo-pro' ) );

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

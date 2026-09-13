<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only adapter from Bitmomo AI's validated canonical projection into
 * the Pro draft-prefill contract. It never publishes and never invents
 * editorial fields or an expected range.
 */
class Bitmomo_Pro_Canonical_Adapter {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_filter( Bitmomo_Pro_Brief_Prefill::AVAILABILITY_FILTER, array( $this, 'available_payload' ) );
	}

	public function available_payload( $payload ) {
		if ( ! class_exists( 'Bitmomo_AI_Intelligence' ) ) return $payload;

		$free = Bitmomo_AI_Intelligence::free_projection();
		if ( ! is_array( $free ) || empty( $free['timestamp'] ) || 'unavailable' === ( $free['status'] ?? '' ) ) return $payload;

		$source_record_id = trim( (string) ( $free['edition_id'] ?? '' ) );
		if ( '' === $source_record_id ) return $payload; // Stable canonical lineage is mandatory.

		add_filter( 'bitmomo_ai_pro_entitled', '__return_true', PHP_INT_MAX );
		$pro = Bitmomo_AI_Intelligence::pro_projection();
		remove_filter( 'bitmomo_ai_pro_entitled', '__return_true', PHP_INT_MAX );

		$result = array(
			'source_record_id'      => sanitize_text_field( $source_record_id ),
			'btc_reference_price'   => (float) $free['price'],
			// Legacy Pro V1 field name. Its enum is directional by contract.
			'market_state'          => sanitize_key( (string) $free['bias'] ),
			'confidence'            => (float) $free['confidence'],
			'data_timestamp'        => gmdate( 'c', (int) $free['timestamp'] ),
			'data_freshness_status' => sanitize_key( (string) $free['status'] ),
		);

		if ( is_array( $pro ) && ! empty( $pro['invalidation'] ) && (float) $pro['invalidation'] > 0 ) {
			$result['invalidation'] = (string) (float) $pro['invalidation'];
		}

		// IMPORTANT: support/resistance zones are NOT an Expected Range.
		// They may help an editor author a brief, but mapping them directly to
		// expected_range_low/high would fabricate a forecast methodology and
		// contaminate the public forward-validation ledger. Expected Range stays
		// blank until a dedicated, versioned range model or an editor supplies it.

		return $result;
	}
}

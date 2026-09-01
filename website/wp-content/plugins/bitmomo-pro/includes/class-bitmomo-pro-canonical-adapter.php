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
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( Bitmomo_Pro_Brief_Prefill::AVAILABILITY_FILTER, array( $this, 'available_payload' ) );
	}

	public function available_payload( $payload ) {
		if ( ! class_exists( 'Bitmomo_AI_Intelligence' ) ) {
			return $payload;
		}

		$free = Bitmomo_AI_Intelligence::free_projection();
		if ( ! is_array( $free ) || empty( $free['timestamp'] ) || 'unavailable' === ( $free['status'] ?? '' ) ) {
			return $payload;
		}

		add_filter( 'bitmomo_ai_pro_entitled', '__return_true', PHP_INT_MAX );
		$pro = Bitmomo_AI_Intelligence::pro_projection();
		remove_filter( 'bitmomo_ai_pro_entitled', '__return_true', PHP_INT_MAX );

		$result = array(
			'source_record_id'      => 'bitmomo-ai:' . (int) $free['timestamp'],
			'btc_reference_price'   => (float) $free['price'],
			'market_state'          => sanitize_key( (string) $free['bias'] ),
			'confidence'            => (float) $free['confidence'],
			'data_timestamp'        => gmdate( 'c', (int) $free['timestamp'] ),
			'data_freshness_status' => sanitize_key( (string) $free['status'] ),
		);

		if ( is_array( $pro ) && ! empty( $pro['invalidation'] ) && (float) $pro['invalidation'] > 0 ) {
			$result['invalidation'] = (string) (float) $pro['invalidation'];
		}
		if ( is_array( $pro ) ) {
			$range_low  = (float) ( $pro['support_zone']['low'] ?? 0 );
			$range_high = (float) ( $pro['resistance_zone']['high'] ?? 0 );
			if ( $range_low > 0 && $range_high >= $range_low ) {
				$result['expected_range_low']  = $range_low;
				$result['expected_range_high'] = $range_high;
			}
		}

		return $result;
	}
}

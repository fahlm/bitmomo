<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Forward-only evaluation of the Pro brief subscribers actually received. */
final class Bitmomo_Pro_Performance {
	const MIN_EVALUATION_HOURS = 22;
	const MAX_EVALUATION_HOURS = 27;
	const ORIGINAL_META = '_bitmomo_pro_evaluation_original';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'transition_post_status', array( $this, 'freeze_on_publish' ), 10, 3 );
		add_action( 'bitmomo_ai_settlement_snapshot', array( $this, 'settle' ) );
	}

	public function freeze_on_publish( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || Bitmomo_Pro_Briefs::POST_TYPE !== $post->post_type ) return;
		if ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $post->ID ) ) return;
		if ( get_post_meta( $post->ID, self::ORIGINAL_META, true ) ) return;
		$original = array();
		foreach ( Bitmomo_Pro_Briefs::field_keys() as $field ) {
			$original[ $field ] = get_post_meta( $post->ID, '_bitmomo_pro_' . $field, true );
		}
		$original['published_at'] = gmdate( 'c' );
		update_post_meta( $post->ID, self::ORIGINAL_META, wp_json_encode( $original ) );
		update_post_meta( $post->ID, '_bitmomo_pro_evaluation_status', 'pending' );
	}

	public function settle( $market_data ) {
		if ( ! is_array( $market_data ) ) return 0;
		$window = (array) ( $market_data['outcome_window'] ?? array() );
		$close = (float) ( $market_data['close'] ?? 0 );
		$high = (float) ( $window['high_24h'] ?? 0 );
		$low = (float) ( $window['low_24h'] ?? 0 );
		if ( $close <= 0 || $high <= 0 || $low <= 0 ) return 0;

		$ids = get_posts( array(
			'post_type' => Bitmomo_Pro_Briefs::POST_TYPE, 'post_status' => 'publish',
			'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
			'meta_query' => array( 'relation' => 'OR',
				array( 'key' => '_bitmomo_pro_evaluation_status', 'value' => 'pending' ),
				array( 'key' => '_bitmomo_pro_evaluation_status', 'compare' => 'NOT EXISTS' ),
			),
		) );
		$settled = 0;
		foreach ( $ids as $post_id ) {
			$original = json_decode( (string) get_post_meta( $post_id, self::ORIGINAL_META, true ), true );
			if ( ! is_array( $original ) || empty( $original['published_at'] ) ) continue; // No fabricated history.
			$age_hours = ( time() - strtotime( $original['published_at'] ) ) / HOUR_IN_SECONDS;
			if ( $age_hours < self::MIN_EVALUATION_HOURS ) continue;
			if ( $age_hours > self::MAX_EVALUATION_HOURS ) {
				update_post_meta( $post_id, '_bitmomo_pro_evaluation_status', 'window_missed' );
				update_post_meta( $post_id, '_bitmomo_pro_evaluated_at', gmdate( 'c' ) );
				continue;
			}
			$range_low = (float) ( $original['expected_range_low'] ?? 0 );
			$range_high = (float) ( $original['expected_range_high'] ?? 0 );
			$reference = (float) ( $original['btc_reference_price'] ?? 0 );
			if ( $range_low <= 0 || $range_high < $range_low || $reference <= 0 ) continue;
			update_post_meta( $post_id, '_bitmomo_pro_evaluation_status', 'evaluated' );
			update_post_meta( $post_id, '_bitmomo_pro_evaluated_at', gmdate( 'c' ) );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_price_24h', (string) $close );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_high_24h', (string) $high );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_low_24h', (string) $low );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_return_pct', (string) round( ( ( $close - $reference ) / $reference ) * 100, 4 ) );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_range_hit', $low <= $range_high && $high >= $range_low ? 'yes' : 'no' );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_breached_low', $low < $range_low ? 'yes' : 'no' );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_breached_high', $high > $range_high ? 'yes' : 'no' );
			$move_pct = ( ( $close - $reference ) / $reference ) * 100;
			$state = (string) ( $original['market_state'] ?? 'neutral' );
			$result = 'neutral' === $state
				? ( abs( $move_pct ) < 0.5 ? 'correct' : 'incorrect' )
				: ( ( 'bullish' === $state && $move_pct >= 0.5 ) || ( 'bearish' === $state && $move_pct <= -0.5 ) ? 'correct' : ( abs( $move_pct ) < 0.5 ? 'inconclusive' : 'incorrect' ) );
			update_post_meta( $post_id, '_bitmomo_pro_outcome_market_state', $result );
			$settled++;
		}
		return $settled;
	}
}

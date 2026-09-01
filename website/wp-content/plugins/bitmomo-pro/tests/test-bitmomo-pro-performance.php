<?php
require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/../includes/class-bitmomo-pro-briefs.php';
require __DIR__ . '/../includes/class-bitmomo-pro-performance.php';

function performance_check( $label, $condition ) {
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
	if ( ! $condition ) $GLOBALS['__performance_failed'] = true;
}

$performance = Bitmomo_Pro_Performance::instance();
stub_insert_post( 10, 'publish', 'Forward brief' );
$fields = array(
	'market_state' => 'bullish', 'btc_reference_price' => 100,
	'expected_range_low' => 95, 'expected_range_high' => 110,
	'confidence' => 60, 'base_scenario' => 'Frozen scenario',
);
foreach ( $fields as $key => $value ) update_post_meta( 10, '_bitmomo_pro_' . $key, $value );
$post = (object) array( 'ID' => 10, 'post_type' => Bitmomo_Pro_Briefs::POST_TYPE );
$performance->freeze_on_publish( 'publish', 'draft', $post );
$original = json_decode( get_post_meta( 10, Bitmomo_Pro_Performance::ORIGINAL_META, true ), true );
performance_check( 'publish freezes original evaluable and editorial fields', 'Frozen scenario' === $original['base_scenario'] );

$original['published_at'] = gmdate( 'c', time() - ( 23 * HOUR_IN_SECONDS ) );
update_post_meta( 10, Bitmomo_Pro_Performance::ORIGINAL_META, wp_json_encode( $original ) );
$settled = $performance->settle( array( 'close' => 105, 'outcome_window' => array( 'high_24h' => 112, 'low_24h' => 97 ) ) );
performance_check( 'eligible brief settles', 1 === $settled );
performance_check( 'range intersection is recorded', 'yes' === get_post_meta( 10, '_bitmomo_pro_outcome_range_hit', true ) );
performance_check( 'upper breach is recorded', 'yes' === get_post_meta( 10, '_bitmomo_pro_outcome_breached_high', true ) );
performance_check( 'frozen bullish state is evaluated', 'correct' === get_post_meta( 10, '_bitmomo_pro_outcome_market_state', true ) );

stub_insert_post( 11, 'publish', 'Historical brief' );
update_post_meta( 11, '_bitmomo_pro_evaluation_status', 'pending' );
$performance->settle( array( 'close' => 105, 'outcome_window' => array( 'high_24h' => 112, 'low_24h' => 97 ) ) );
performance_check( 'brief without frozen original is not backfilled or fabricated', '' === get_post_meta( 11, '_bitmomo_pro_evaluated_at', true ) );

exit( empty( $GLOBALS['__performance_failed'] ) ? 0 : 1 );

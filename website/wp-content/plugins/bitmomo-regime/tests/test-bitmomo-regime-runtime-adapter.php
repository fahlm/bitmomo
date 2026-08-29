<?php
define( 'ABSPATH', __DIR__ );
class Bitmomo_AI_Intelligence {
    public static $projection = null;
    public static function regime_projection() { return self::$projection; }
}
require __DIR__ . '/../includes/class-bitmomo-regime-runtime-adapter.php';

$checks = array();
function check_adapter( $label, $value ) { global $checks; $checks[] = array( $label, (bool) $value ); }

$missing = Bitmomo_Regime_Runtime_Adapter::current_input();
check_adapter( 'missing canonical projection fails closed', false === $missing['success'] && null === $missing['input'] );

Bitmomo_AI_Intelligence::$projection = array(
    'source_record_id' => 'bitmomo-ai:123', 'timestamp_iso' => '2026-08-29T12:00:00+00:00',
    'metrics' => array( 'return_1d' => 1, 'return_7d' => 2, 'return_30d' => 3, 'volatility_percentile' => 40, 'volatility_change' => 5, 'volume_percentile' => 60, 'range_position_pct' => 70, 'structure_state' => 'range' ),
    'direction_score' => 45, 'directional_bias' => 'bullish', 'directional_confidence' => 62,
    'open_interest_change_pct' => 4, 'funding_rate' => 0.0001, 'basis_pct' => 0.05,
);
$result = Bitmomo_Regime_Runtime_Adapter::current_input();
check_adapter( 'validated projection adapts successfully', true === $result['success'] );
check_adapter( 'funding converts decimal rate to percent', abs( $result['input']['funding_rate_pct'] - 0.01 ) < 0.000001 );
check_adapter( 'liquidation remains explicitly absent', null === $result['input']['liquidation_pressure'] );
check_adapter( 'crowding remains explicitly absent', null === $result['input']['crowding_score'] );
check_adapter( 'source traceability is preserved', 'bitmomo-ai:123' === $result['input']['source_record_id'] );

$passed = count( array_filter( $checks, function ( $row ) { return $row[1]; } ) );
foreach ( $checks as $row ) printf( "[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0] );
printf( "\n%d/%d passed.\n", $passed, count( $checks ) );
exit( $passed === count( $checks ) ? 0 : 1 );

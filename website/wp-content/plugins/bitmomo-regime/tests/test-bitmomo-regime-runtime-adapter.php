<?php
define( 'ABSPATH', __DIR__ );
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
class Bitmomo_AI_Intelligence {
    public static $projection = null;
    public static function regime_projection() { return self::$projection; }
}
class Bitmomo_AI_Runtime_State {
    public static $metadata = array();
    public static function latest_valid_metadata() { return self::$metadata; }
}
require __DIR__ . '/../includes/class-bitmomo-regime-runtime-adapter.php';

$checks = array();
function check_adapter( $label, $value ) { global $checks; $checks[] = array( $label, (bool) $value ); }

$missing = Bitmomo_Regime_Runtime_Adapter::current_input();
check_adapter( 'missing canonical projection fails closed', false === $missing['success'] && null === $missing['input'] );

Bitmomo_AI_Intelligence::$projection = array(
    'source_record_id' => 'legacy-synthetic-id', 'timestamp_iso' => '2026-08-29T12:00:00+00:00',
    'metrics' => array( 'return_1d' => 1, 'return_7d' => 2, 'return_30d' => 3, 'volatility_percentile' => 40, 'volatility_change' => 5, 'volume_percentile' => 60, 'range_position_pct' => 70, 'structure_state' => 'range' ),
    'direction_score' => 45, 'directional_bias' => 'bullish', 'directional_confidence' => 62,
    'open_interest_change_pct' => 4, 'funding_rate' => 0.0001, 'basis_pct' => 0.05,
);
$missing_lineage = Bitmomo_Regime_Runtime_Adapter::current_input();
check_adapter( 'projection without canonical record identity fails closed', false === $missing_lineage['success'] && null === $missing_lineage['input'] );

Bitmomo_AI_Runtime_State::$metadata = array( 'canonical_record_id' => 'bitmomo-ai:canonical-v2' );
$result = Bitmomo_Regime_Runtime_Adapter::current_input();
check_adapter( 'validated projection adapts successfully', true === $result['success'] );
check_adapter( 'funding converts decimal rate to percent', abs( $result['input']['funding_rate_pct'] - 0.01 ) < 0.000001 );
check_adapter( 'liquidation remains explicitly absent', null === $result['input']['liquidation_pressure'] );
check_adapter( 'crowding remains explicitly absent', null === $result['input']['crowding_score'] );
check_adapter( 'canonical source traceability overrides legacy synthetic regime id', 'bitmomo-ai:canonical-v2' === $result['input']['source_record_id'] );

$passed = count( array_filter( $checks, function ( $row ) { return $row[1]; } ) );
foreach ( $checks as $row ) printf( "[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0] );
printf( "\n%d/%d passed.\n", $passed, count( $checks ) );
exit( $passed === count( $checks ) ? 0 : 1 );

<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-bitmomo-ai-regime-metrics.php';

$checks = array();
function check_metric( $label, $value ) { global $checks; $checks[] = array( $label, (bool) $value ); }
function candles( $count, $start, $step ) {
    $rows = array();
    for ( $i = 0; $i < $count; $i++ ) {
        $close = $start + ( $i * $step );
        $rows[] = array( 0, $close - 0.5, $close + 2, $close - 2, $close, 100 + $i, 1 );
    }
    return $rows;
}

$metrics = Bitmomo_AI_Regime_Metrics::from_candles( candles( 40, 100, 1 ), candles( 320, 100, 1 ), 'breakout_up' );
check_metric( 'complete candles produce metrics', is_array( $metrics ) );
check_metric( 'all eight market fields exist', 8 === count( $metrics ) );
check_metric( '1D return uses 24 closed hourly periods', abs( $metrics['return_1d'] - 20.869565 ) < 0.00001 );
check_metric( '7D return is positive', $metrics['return_7d'] > 0 );
check_metric( '30D return is positive', $metrics['return_30d'] > $metrics['return_7d'] );
check_metric( 'volume percentile is bounded', $metrics['volume_percentile'] >= 0 && $metrics['volume_percentile'] <= 100 );
check_metric( 'range position is bounded', $metrics['range_position_pct'] >= 0 && $metrics['range_position_pct'] <= 100 );
check_metric( 'breakout_up is preserved', 'breakout_up' === $metrics['structure_state'] );
check_metric( 'breakout_down normalizes to breakdown', 'breakdown' === Bitmomo_AI_Regime_Metrics::from_candles( candles( 40, 100, 1 ), candles( 320, 100, 1 ), 'breakout_down' )['structure_state'] );
check_metric( 'trend shorthand is not fabricated as range', 'unknown' === Bitmomo_AI_Regime_Metrics::from_candles( candles( 40, 100, 1 ), candles( 320, 100, 1 ), 'hh_hl' )['structure_state'] );
check_metric( 'insufficient candles fail closed', null === Bitmomo_AI_Regime_Metrics::from_candles( candles( 24, 100, 1 ), candles( 30, 100, 1 ), 'range' ) );

$passed = count( array_filter( $checks, function ( $row ) { return $row[1]; } ) );
foreach ( $checks as $row ) printf( "[%s] %s\n", $row[1] ? 'PASS' : 'FAIL', $row[0] );
printf( "\n%d/%d passed.\n", $passed, count( $checks ) );
exit( $passed === count( $checks ) ? 0 : 1 );

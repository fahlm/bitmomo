<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Read-only adapter from Bitmomo AI's validated canonical projection. */
final class Bitmomo_Regime_Runtime_Adapter {
    public static function current_input() {
        if ( ! class_exists( 'Bitmomo_AI_Intelligence' ) || ! method_exists( 'Bitmomo_AI_Intelligence', 'regime_projection' ) ) {
            return array( 'success' => false, 'errors' => array( 'Bitmomo AI regime projection is unavailable.' ), 'input' => null );
        }
        $source = Bitmomo_AI_Intelligence::regime_projection();
        if ( ! is_array( $source ) || empty( $source['metrics'] ) ) {
            return array( 'success' => false, 'errors' => array( 'No validated canonical Regime input is available.' ), 'input' => null );
        }
        $metrics = $source['metrics'];
        $input = array_merge($metrics, array(
            'momentum_score' => max( -100, min( 100, (float) ( $source['direction_score'] ?? 0 ) ) ),
            'directional_bias' => (string) ( $source['directional_bias'] ?? 'neutral' ),
            'directional_confidence' => (float) ( $source['directional_confidence'] ?? 0 ),
            'open_interest_change_pct' => isset( $source['open_interest_change_pct'] ) ? (float) $source['open_interest_change_pct'] : null,
            'funding_rate_pct' => isset( $source['funding_rate'] ) ? (float) $source['funding_rate'] * 100 : null,
            'basis_pct' => isset( $source['basis_pct'] ) ? (float) $source['basis_pct'] : null,
            'liquidation_pressure' => null,
            'crowding_score' => null,
            'source_record_id' => (string) ( $source['source_record_id'] ?? '' ),
            'as_of' => (string) ( $source['timestamp_iso'] ?? '' ),
        ));
        return array( 'success' => true, 'errors' => array(), 'input' => $input );
    }
}

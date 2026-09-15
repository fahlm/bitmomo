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

        // Regime is a classification of one canonical Intelligence edition,
        // not a parallel record identity. The old adapter accepted the
        // regime_projection() compatibility id (date + legacy edition), which
        // prevented exact joins back to the v2 session/signal record and could
        // let a public consumer combine different editions. Require the true
        // canonical record id from Runtime State and persist it unchanged.
        $metadata = class_exists( 'Bitmomo_AI_Runtime_State' ) && method_exists( 'Bitmomo_AI_Runtime_State', 'latest_valid_metadata' )
            ? Bitmomo_AI_Runtime_State::latest_valid_metadata()
            : array();
        $canonical_source_id = sanitize_text_field( (string) ( $metadata['canonical_record_id'] ?? '' ) );
        if ( '' === $canonical_source_id ) {
            return array( 'success' => false, 'errors' => array( 'Canonical source record id is unavailable; Regime evaluation failed closed.' ), 'input' => null );
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
            'source_record_id' => $canonical_source_id,
            'as_of' => (string) ( $source['timestamp_iso'] ?? '' ),
            'edition' => (string) ( $source['edition'] ?? 'us_session' ),
            'provenance' => (string) ( $source['provenance'] ?? 'recorded_live' ),
        ));
        return array( 'success' => true, 'errors' => array(), 'input' => $input );
    }
}

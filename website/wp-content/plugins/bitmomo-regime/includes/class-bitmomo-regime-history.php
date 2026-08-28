<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 30-day frontend-safe history projection for Product A (REGIME PR 3).
 *
 * Pure PHP, no WordPress dependency, no I/O — takes an already-fetched
 * array of full state records (as returned by
 * Bitmomo_Regime_State_Store::get_recent()) and shapes them into the
 * narrow, frontend-safe field set the shortcodes/history widget need.
 * Directly unit testable (see tests/test-bitmomo-regime-history.php).
 *
 * Two hard rules from the spec, both enforced here:
 * - NEVER fabricate missing history. If fewer records exist than
 *   requested, `available_days` reports the true count and `days` is
 *   simply shorter — no placeholder/filler rows are ever synthesized.
 * - Only frontend-safe fields are projected: date, regime (+ both
 *   labels), directional_bias, regime_confidence. Diagnostic-only fields
 *   (evidence, conflicts, input_summary/input_hash, source_record_id,
 *   transition_strength/reason, pending_regime/streak, classifier_version)
 *   never leave this class — those stay admin-only, see
 *   Bitmomo_Regime_Admin_Diagnostics.
 */
class Bitmomo_Regime_History {

	/**
	 * The only day-count values the frontend may request, per the spec
	 * ("support 1/7/14/30 days"). Anything else falls back to DEFAULT_DAYS
	 * rather than being silently reinterpreted as some arbitrary number.
	 */
	const ALLOWED_DAYS = array( 1, 7, 14, 30 );

	const DEFAULT_DAYS = 7;

	/**
	 * The overall target window Product A's history is meant to reach.
	 * `available_days` is always compared against this, independent of
	 * what a given call actually requested, so a caller can render
	 * "5 of 30 days collected so far" even when it asked for 7.
	 */
	const TARGET_DAYS = 30;

	/**
	 * @param array $records        Newest-first full state records — the
	 *                               contract Bitmomo_Regime_State_Store::get_recent()
	 *                               returns. The caller is expected to have
	 *                               fetched at most TARGET_DAYS of them;
	 *                               this method does not re-fetch and does
	 *                               not enforce that cap itself.
	 * @param int   $requested_days One of ALLOWED_DAYS. Any other value
	 *                               (including omission) falls back to
	 *                               DEFAULT_DAYS.
	 *
	 * @return array{requested_days:int,target_days:int,available_days:int,days:array[]}
	 */
	public static function for_frontend( array $records, $requested_days = self::DEFAULT_DAYS ) {
		$requested_days = in_array( $requested_days, self::ALLOWED_DAYS, true )
			? (int) $requested_days
			: self::DEFAULT_DAYS;

		// $records arrives newest-first. Reverse to chronological ascending
		// (oldest -> newest) for a natural left-to-right time axis, then
		// keep only the most recent $requested_days of that — never more
		// than what actually exists.
		$chronological = array_reverse( array_values( $records ) );
		$available     = count( $chronological );

		$slice = ( $requested_days >= $available )
			? $chronological
			: array_slice( $chronological, $available - $requested_days );

		$days = array();
		foreach ( $slice as $record ) {
			$days[] = self::project( $record );
		}

		return array(
			'requested_days' => $requested_days,
			'target_days'    => self::TARGET_DAYS,
			'available_days' => $available,
			'days'           => $days,
		);
	}

	/**
	 * The frontend-safe field set. Deliberately an explicit whitelist
	 * (never `array_diff_key`/blacklist) so a new diagnostic-only field
	 * added to Bitmomo_Regime_State_Store::META_KEYS in the future cannot
	 * leak to the frontend by omission.
	 */
	private static function project( $record ) {
		$date = isset( $record['as_of'] ) && '' !== $record['as_of']
			? $record['as_of']
			: ( isset( $record['date'] ) ? $record['date'] : null );

		return array(
			'date'              => $date,
			'regime'            => isset( $record['regime'] ) ? $record['regime'] : null,
			'regime_label_id'   => Bitmomo_Regime_Taxonomy::regime_label_id( isset( $record['regime'] ) ? $record['regime'] : '' ),
			'regime_label_en'   => Bitmomo_Regime_Taxonomy::regime_label_en( isset( $record['regime'] ) ? $record['regime'] : '' ),
			'directional_bias'  => isset( $record['directional_bias'] ) ? $record['directional_bias'] : null,
			'regime_confidence' => isset( $record['regime_confidence'] ) ? $record['regime_confidence'] : null,
		);
	}
}

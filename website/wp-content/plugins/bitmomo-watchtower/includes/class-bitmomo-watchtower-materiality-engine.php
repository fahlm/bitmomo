<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The deterministic materiality engine for Product B (WATCHTOWER PR 1).
 *
 * Pure PHP, no I/O, no LLM, no WordPress dependency — a validated event
 * candidate (see Bitmomo_Watchtower_Event_Input) in, a 0-100 composite
 * score plus a full per-dimension reason breakdown and a policy band out.
 * Directly unit testable (see tests/test-bitmomo-watchtower-materiality.php).
 *
 * Six dimensions, each a 0-100 input on the validated event, combined as
 * a fixed weighted sum (weights in Bitmomo_Watchtower_Config, must sum to
 * 100 — enforced in the constructor, not just documented): Magnitude,
 * Novelty, BTC Relevance, Cross Confirmation, Thesis Impact, Source
 * Quality. The composite score is mapped to one of four policy bands
 * (Bitmomo_Watchtower_Taxonomy::MATERIALITY_BANDS) via the cut points in
 * Bitmomo_Watchtower_Config.
 *
 * This PR does NOT decide what happens with a scored event (no alerting,
 * no dedup/clustering, no state transitions) — that is WATCHTOWER PR 2+.
 * This class only answers "how material is this candidate, and why."
 */
class Bitmomo_Watchtower_Materiality_Engine {

	/**
	 * Fixed evaluation order — also the order reason_breakdown is
	 * returned in, so it reads the same way every time.
	 */
	const DIMENSIONS = array(
		'magnitude'          => array(
			'field'  => 'magnitude_score',
			'label'  => 'Magnitude',
			'weight' => Bitmomo_Watchtower_Config::WEIGHT_MAGNITUDE,
		),
		'novelty'            => array(
			'field'  => 'novelty_score',
			'label'  => 'Novelty',
			'weight' => Bitmomo_Watchtower_Config::WEIGHT_NOVELTY,
		),
		'btc_relevance'      => array(
			'field'  => 'btc_relevance_score',
			'label'  => 'BTC Relevance',
			'weight' => Bitmomo_Watchtower_Config::WEIGHT_BTC_RELEVANCE,
		),
		'cross_confirmation' => array(
			'field'  => 'cross_confirmation_score',
			'label'  => 'Cross Confirmation',
			'weight' => Bitmomo_Watchtower_Config::WEIGHT_CROSS_CONFIRMATION,
		),
		'thesis_impact'      => array(
			'field'  => 'thesis_impact_score',
			'label'  => 'Thesis Impact',
			'weight' => Bitmomo_Watchtower_Config::WEIGHT_THESIS_IMPACT,
		),
		'source_quality'     => array(
			'field'  => 'source_quality_score',
			'label'  => 'Source Quality',
			'weight' => Bitmomo_Watchtower_Config::WEIGHT_SOURCE_QUALITY,
		),
	);

	/**
	 * @throws RuntimeException If Bitmomo_Watchtower_Config's dimension
	 *                           weights do not sum to 100 — a hard guard
	 *                           against a future edit to one weight
	 *                           without adjusting the others.
	 */
	public function __construct() {
		if ( 100 !== Bitmomo_Watchtower_Config::TOTAL_WEIGHT ) {
			throw new RuntimeException( 'Bitmomo_Watchtower_Config dimension weights must sum to 100.' );
		}
	}

	/**
	 * @param array $input A validated record from Bitmomo_Watchtower_Event_Input::validate().
	 *
	 * @return array{score:int,band:string,dimension_scores:array,reason_breakdown:array[],engine_version:string}
	 */
	public function evaluate( $input ) {
		$dimension_scores = array();
		$breakdown         = array();
		$composite         = 0.0;

		foreach ( self::DIMENSIONS as $key => $meta ) {
			$raw = isset( $input[ $meta['field'] ] ) ? (float) $input[ $meta['field'] ] : 0.0;
			$raw = $this->clamp( $raw, 0, 100 );

			$contribution = round( $raw * $meta['weight'] / 100, 2 );
			$composite   += $contribution;

			$dimension_scores[ $key ] = $raw;
			$breakdown[]              = array(
				'dimension'    => $key,
				'label'        => $meta['label'],
				'raw_score'    => $raw,
				'weight_pct'   => $meta['weight'],
				'contribution' => $contribution,
			);
		}

		$score = (int) round( $composite );
		$band  = $this->band_for_score( $score );

		return array(
			'score'            => $score,
			'band'             => $band,
			'dimension_scores' => $dimension_scores,
			'reason_breakdown' => $breakdown,
			'engine_version'   => Bitmomo_Watchtower_Config::MATERIALITY_ENGINE_VERSION,
		);
	}

	private function band_for_score( $score ) {
		if ( $score <= Bitmomo_Watchtower_Config::BAND_NOISE_MAX ) {
			return Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_NOISE;
		}
		if ( $score <= Bitmomo_Watchtower_Config::BAND_INTERESTING_MAX ) {
			return Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_INTERESTING;
		}
		if ( $score <= Bitmomo_Watchtower_Config::BAND_MATERIAL_CANDIDATE_MAX ) {
			return Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_MATERIAL_CANDIDATE;
		}
		return Bitmomo_Watchtower_Taxonomy::MATERIALITY_BAND_CRITICAL_CANDIDATE;
	}

	/**
	 * max()/min() return whichever argument's value wins, preserving that
	 * argument's original type — so clamp(0.0, 0, 100) can silently
	 * return the int 0 (when the int bound "wins" the comparison) instead
	 * of the float 0.0. dimension_scores must be consistently float
	 * regardless of whether the raw value or a bound won the clamp, so
	 * the result is explicitly cast here rather than left to whichever
	 * argument max()/min() happened to return.
	 */
	private function clamp( $value, $min, $max ) {
		return (float) max( $min, min( $max, $value ) );
	}
}

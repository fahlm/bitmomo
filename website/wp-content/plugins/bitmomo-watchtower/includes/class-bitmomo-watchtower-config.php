<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every weight and threshold the WATCHTOWER PR 1 materiality engine
 * uses, in one inspectable place — same convention as
 * Bitmomo_Regime_Config in the regime plugin. Nothing in
 * Bitmomo_Watchtower_Materiality_Engine hardcodes a magic number.
 *
 * MATERIALITY_ENGINE_VERSION identifies which ruleset produced a given
 * materiality score. Bump it whenever any weight or band threshold below
 * changes, so a later PR's persisted event records (PR2+) can record
 * exactly which version scored each one — a re-tuned engine must never
 * silently rewrite the meaning of old scores.
 */
class Bitmomo_Watchtower_Config {

	const MATERIALITY_ENGINE_VERSION = 'materiality-v1';

	// --- Materiality dimension weights (must sum to 100) -----------------
	// Magnitude: how large the deviation/move is.
	const WEIGHT_MAGNITUDE = 25;
	// Novelty: how unprecedented this is versus recent baseline.
	const WEIGHT_NOVELTY = 15;
	// BTC Relevance: how directly this bears on BTC specifically (a
	// macro release about an unrelated asset class scores low here even
	// if it is a big move in its own market).
	const WEIGHT_BTC_RELEVANCE = 20;
	// Cross Confirmation: how many independent sources/signals agree.
	const WEIGHT_CROSS_CONFIRMATION = 15;
	// Thesis Impact: how much this could actually change the current
	// market thesis, as opposed to being noteworthy but thesis-neutral.
	const WEIGHT_THESIS_IMPACT = 15;
	// Source Quality: the reliability/tier of the reporting source.
	const WEIGHT_SOURCE_QUALITY = 10;

	/**
	 * Defensive total — Bitmomo_Watchtower_Materiality_Engine refuses to
	 * run if this does not equal 100, so a future edit to one weight
	 * without adjusting the others fails loudly instead of silently
	 * producing a score that isn't actually 0-100.
	 */
	const TOTAL_WEIGHT = self::WEIGHT_MAGNITUDE
		+ self::WEIGHT_NOVELTY
		+ self::WEIGHT_BTC_RELEVANCE
		+ self::WEIGHT_CROSS_CONFIRMATION
		+ self::WEIGHT_THESIS_IMPACT
		+ self::WEIGHT_SOURCE_QUALITY;

	// --- Materiality policy bands (spec-mandated cut points) -------------
	// 0-39 noise, 40-59 interesting, 60-74 material candidate,
	// 75-100 critical candidate (the implicit remainder above BAND_MATERIAL_CANDIDATE_MAX).
	const BAND_NOISE_MAX              = 39;
	const BAND_INTERESTING_MAX        = 59;
	const BAND_MATERIAL_CANDIDATE_MAX = 74;
}

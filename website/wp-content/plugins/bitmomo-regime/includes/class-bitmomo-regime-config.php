<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every threshold and weight the V1 classifier uses, in one inspectable
 * place — nothing in Bitmomo_Regime_Classifier hardcodes a magic number;
 * every constant used there is named here, grouped by the regime it
 * scores. Changing a threshold means editing this file only.
 *
 * CLASSIFIER_VERSION identifies which ruleset produced a given
 * classification. Bump it whenever any scoring rule or threshold below
 * changes, so history persisted in PR2 can record exactly which version
 * produced each stored record — a re-tuned classifier must never silently
 * rewrite the meaning of old history.
 */
class Bitmomo_Regime_Config {

	const CLASSIFIER_VERSION = 'regime-v1';

	// --- Accumulation ---------------------------------------------------
	const ACCUM_LOW_VOL_PCTL          = 35.0;
	const ACCUM_RANGE_RETURN_ABS      = 8.0;
	const ACCUM_LOW_RANGE_POSITION    = 40.0;
	const ACCUM_MODERATE_VOLUME_PCTL  = 50.0;
	const ACCUM_FUNDING_NEUTRAL_LOW   = -0.01;
	const ACCUM_FUNDING_NEUTRAL_HIGH  = 0.02;

	// --- Expansion --------------------------------------------------------
	const EXP_MOMENTUM_STRONG_POS = 40.0;
	const EXP_HIGH_VOLUME_PCTL    = 65.0;
	const EXP_RETURN_7D_MIN       = 8.0;
	const EXP_OI_CHANGE_MIN       = 5.0;
	const EXP_HIGH_RANGE_POSITION = 70.0;

	// --- Distribution -----------------------------------------------------
	const DIST_HIGH_RANGE_POSITION  = 70.0;
	const DIST_MOMENTUM_DECEL_LOW   = -10.0;
	const DIST_MOMENTUM_DECEL_HIGH  = 20.0;
	const DIST_HIGH_VOLUME_PCTL     = 60.0;
	const DIST_STALL_RETURN_7D_MAX  = 3.0;
	const DIST_EXTREME_FUNDING_POS  = 0.05;
	const DIST_HIGH_CROWDING        = 70.0;

	// --- Capitulation -------------------------------------------------------
	const CAP_SHARP_DROP_1D        = -8.0;
	const CAP_SHARP_DROP_7D        = -15.0;
	const CAP_HIGH_VOL_PCTL        = 80.0;
	const CAP_VOL_SHOCK_DELTA      = 15.0;
	const CAP_EXTREME_FUNDING_NEG  = -0.03;
	const CAP_MOMENTUM_STRONG_NEG  = -40.0;

	// --- Classification / transition decision --------------------------
	// A regime must clear this raw score before it can be classified at
	// all; below it, evidence is too thin for any regime and the result
	// is TRANSITION ("insufficient evidence"), not a low-confidence guess.
	const MIN_CLASSIFICATION_SCORE = 35.0;

	// The leading regime's score must beat the runner-up by at least this
	// much, or the result is TRANSITION ("too close to call") rather than
	// picking an arbitrary winner between two similarly-supported regimes.
	const TRANSITION_MARGIN = 15.0;

	// --- Confidence formula bounds ---------------------------------------
	const CONFIDENCE_FLOOR             = 10;
	const CONFIDENCE_CEILING           = 97;
	const TRANSITION_CONFIDENCE_CEILING = 65;
}

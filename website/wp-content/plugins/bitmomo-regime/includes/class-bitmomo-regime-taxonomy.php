<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The V1 Market Regime taxonomy — the single source of truth for regime
 * and directional-bias vocabulary used across Product A (Bitmomo Market
 * Regime).
 *
 * Two deliberately SEPARATE dimensions, never inferred from one another:
 * - REGIME answers "what kind of market is this" (accumulation,
 *   expansion, distribution, capitulation, transition).
 * - BIAS answers "which direction do we expect price to move"
 *   (bullish, neutral, bearish) — this is the existing directional-bias
 *   axis from Bitmomo's existing market stack, passed through unchanged.
 *
 * Accumulation is not assumed bullish. Distribution is not assumed
 * bearish. A caller that wants both must read both fields.
 */
class Bitmomo_Regime_Taxonomy {

	const REGIME_ACCUMULATION = 'accumulation';
	const REGIME_EXPANSION    = 'expansion';
	const REGIME_DISTRIBUTION = 'distribution';
	const REGIME_CAPITULATION = 'capitulation';
	const REGIME_TRANSITION   = 'transition';

	const REGIMES = array(
		self::REGIME_ACCUMULATION,
		self::REGIME_EXPANSION,
		self::REGIME_DISTRIBUTION,
		self::REGIME_CAPITULATION,
		self::REGIME_TRANSITION,
	);

	/**
	 * Regimes the classifier scores evidence for directly. TRANSITION is
	 * deliberately excluded — it is never scored, it is the deterministic
	 * fallback when no other regime has a clear enough lead. Fixed order
	 * here also doubles as the tie-break order when two regimes score
	 * identically (see Bitmomo_Regime_Classifier::evaluate()).
	 */
	const SCORED_REGIMES = array(
		self::REGIME_ACCUMULATION,
		self::REGIME_EXPANSION,
		self::REGIME_DISTRIBUTION,
		self::REGIME_CAPITULATION,
	);

	const BIAS_BULLISH = 'bullish';
	const BIAS_NEUTRAL = 'neutral';
	const BIAS_BEARISH = 'bearish';

	const BIASES = array( self::BIAS_BULLISH, self::BIAS_NEUTRAL, self::BIAS_BEARISH );

	/**
	 * Indonesian frontend label — for Product A's future homepage
	 * component (PR3+). Not used anywhere in the engine layer itself.
	 */
	public static function regime_label_id( $regime ) {
		$labels = array(
			self::REGIME_ACCUMULATION => 'Akumulasi',
			self::REGIME_EXPANSION    => 'Ekspansi',
			self::REGIME_DISTRIBUTION => 'Distribusi',
			self::REGIME_CAPITULATION => 'Kapitulasi',
			self::REGIME_TRANSITION   => 'Transisi',
		);
		return isset( $labels[ $regime ] ) ? $labels[ $regime ] : $regime;
	}

	/**
	 * English label — used in admin diagnostics (PR3) and internal
	 * evidence/conflict strings produced by the classifier.
	 */
	public static function regime_label_en( $regime ) {
		$labels = array(
			self::REGIME_ACCUMULATION => 'Accumulation',
			self::REGIME_EXPANSION    => 'Expansion',
			self::REGIME_DISTRIBUTION => 'Distribution',
			self::REGIME_CAPITULATION => 'Capitulation',
			self::REGIME_TRANSITION   => 'Transition',
		);
		return isset( $labels[ $regime ] ) ? $labels[ $regime ] : $regime;
	}

	public static function is_valid_regime( $value ) {
		return in_array( $value, self::REGIMES, true );
	}

	public static function is_valid_bias( $value ) {
		return in_array( $value, self::BIASES, true );
	}
}

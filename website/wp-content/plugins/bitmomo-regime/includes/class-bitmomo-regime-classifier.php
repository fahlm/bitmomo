<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * V1 deterministic Market Regime classifier (Product A). No LLM, no
 * black-box model — every point awarded is a named rule against
 * Bitmomo_Regime_Config thresholds, and every awarded point produces a
 * human-readable evidence string. Pure function: evaluate() takes a
 * normalized input array (see Bitmomo_Regime_Input::validate()) and
 * returns a classification. No WordPress dependency, no I/O, no
 * randomness, no reliance on "now" — the same input always produces the
 * same output (see tests/test-bitmomo-regime-classifier.php, "repeated
 * evaluation idempotency").
 *
 * Callers MUST validate the input via Bitmomo_Regime_Input::validate()
 * first and only call evaluate() when 'valid' is true — this method
 * assumes a normalized input array and does not re-validate required
 * fields.
 *
 * ARCHITECTURE NOTE: this class, and Product A generally, has no
 * dependency on the bitmomo-pro plugin and must never gain one.
 * DATA/EVENTS -> intelligence engines (this class) -> canonical state ->
 * presentation/Pro consumers. bitmomo-pro (and any future presentation
 * layer) CONSUMES this engine's output; it does not produce it.
 *
 * Directional bias (bullish/neutral/bearish) is never used as an input to
 * regime scoring — it is a separate axis, passed through unchanged in the
 * output. Regime answers "what kind of market is this"; bias answers
 * "which direction do we expect it to move". Accumulation is not assumed
 * bullish; distribution is not assumed bearish.
 */
class Bitmomo_Regime_Classifier {

	/**
	 * @param array $input A normalized array from Bitmomo_Regime_Input::validate()['input'].
	 *
	 * @return array{
	 *   regime:string, confidence:int, evidence:string[], conflicts:string[],
	 *   scores:array<string,float>, directional_bias:string,
	 *   directional_confidence:?float, classifier_version:string
	 * }
	 */
	public function evaluate( $input ) {
		$scores          = array();
		$evidence_by_regime = array();

		foreach ( Bitmomo_Regime_Taxonomy::SCORED_REGIMES as $regime ) {
			$result                       = $this->score_regime( $regime, $input );
			$scores[ $regime ]            = $result['score'];
			$evidence_by_regime[ $regime ] = $result['evidence'];
		}

		$ranked = $this->rank_regimes( $scores );

		$top_regime   = $ranked[0];
		$top_score    = $scores[ $top_regime ];
		$second_regime = isset( $ranked[1] ) ? $ranked[1] : null;
		$second_score = null !== $second_regime ? $scores[ $second_regime ] : 0.0;
		$margin       = $top_score - $second_score;

		if ( $top_score < Bitmomo_Regime_Config::MIN_CLASSIFICATION_SCORE ) {
			$regime     = Bitmomo_Regime_Taxonomy::REGIME_TRANSITION;
			$evidence   = array(
				sprintf(
					'No regime reached the minimum classification score (top: %s, %s < %s).',
					Bitmomo_Regime_Taxonomy::regime_label_en( $top_regime ),
					$this->fmt( $top_score ),
					$this->fmt( Bitmomo_Regime_Config::MIN_CLASSIFICATION_SCORE )
				),
			);
			$confidence = $this->transition_confidence( $top_score, $margin );
		} elseif ( $margin < Bitmomo_Regime_Config::TRANSITION_MARGIN ) {
			$regime     = Bitmomo_Regime_Taxonomy::REGIME_TRANSITION;
			$evidence   = array(
				sprintf(
					'Leading candidates too close to call: %s (%s) vs %s (%s), margin %s < %s.',
					Bitmomo_Regime_Taxonomy::regime_label_en( $top_regime ),
					$this->fmt( $top_score ),
					null !== $second_regime ? Bitmomo_Regime_Taxonomy::regime_label_en( $second_regime ) : 'n/a',
					$this->fmt( $second_score ),
					$this->fmt( $margin ),
					$this->fmt( Bitmomo_Regime_Config::TRANSITION_MARGIN )
				),
			);
			$confidence = $this->transition_confidence( $top_score, $margin );
		} else {
			$regime     = $top_regime;
			$evidence   = $evidence_by_regime[ $top_regime ];
			$confidence = $this->clear_confidence( $top_score, $margin );
		}

		// Computed AFTER $regime is finalized: when $regime is a scored
		// regime, that regime is excluded from its own conflicts list.
		// When $regime is 'transition' (never a key in $scores), nothing
		// is excluded — every regime that cleared the classification
		// threshold is surfaced, which is exactly what "ambiguous" means.
		$conflicts = $this->build_conflicts( $scores, $evidence_by_regime, $regime );

		return array(
			'regime'                 => $regime,
			'confidence'             => $confidence,
			'evidence'               => $evidence,
			'conflicts'              => $conflicts,
			'scores'                 => $scores,
			'directional_bias'       => isset( $input['directional_bias'] ) && '' !== $input['directional_bias'] ? $input['directional_bias'] : Bitmomo_Regime_Taxonomy::BIAS_NEUTRAL,
			'directional_confidence' => isset( $input['directional_confidence'] ) ? $input['directional_confidence'] : null,
			'classifier_version'     => Bitmomo_Regime_Config::CLASSIFIER_VERSION,
		);
	}

	/**
	 * Ranks SCORED_REGIMES by score descending. Ties are broken by fixed
	 * taxonomy order (Bitmomo_Regime_Taxonomy::SCORED_REGIMES), not by
	 * PHP sort-stability — arsort()'s stability guarantee only exists as
	 * of PHP 8.0, and this plugin targets 7.4+, so relying on it would be
	 * a silent version-dependent determinism bug.
	 *
	 * @return string[] regime keys, highest score first.
	 */
	private function rank_regimes( $scores ) {
		$pairs = array();
		foreach ( Bitmomo_Regime_Taxonomy::SCORED_REGIMES as $index => $regime ) {
			$pairs[] = array(
				'regime' => $regime,
				'score'  => $scores[ $regime ],
				'index'  => $index,
			);
		}

		usort(
			$pairs,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return $a['index'] - $b['index'];
				}
				return ( $a['score'] < $b['score'] ) ? 1 : -1;
			}
		);

		$ranked = array();
		foreach ( $pairs as $pair ) {
			$ranked[] = $pair['regime'];
		}
		return $ranked;
	}

	/**
	 * Every regime other than $winner that still cleared
	 * MIN_CLASSIFICATION_SCORE is surfaced here — i.e. "the market also
	 * shows some X-like behavior" — even when the winner's margin was
	 * comfortable. Never hidden.
	 */
	private function build_conflicts( $scores, $evidence_by_regime, $winner ) {
		$conflicts = array();
		foreach ( Bitmomo_Regime_Taxonomy::SCORED_REGIMES as $regime ) {
			if ( $regime === $winner ) {
				continue;
			}
			$score = $scores[ $regime ];
			if ( $score >= Bitmomo_Regime_Config::MIN_CLASSIFICATION_SCORE ) {
				$conflicts[] = sprintf(
					'%s also scored %s: %s',
					Bitmomo_Regime_Taxonomy::regime_label_en( $regime ),
					$this->fmt( $score ),
					implode( ' ', $evidence_by_regime[ $regime ] )
				);
			}
		}
		return $conflicts;
	}

	private function clear_confidence( $top_score, $margin ) {
		$raw = $top_score * 0.7 + min( $margin, 40 ) * 0.75;
		return (int) round( $this->clamp( $raw, Bitmomo_Regime_Config::CONFIDENCE_FLOOR, Bitmomo_Regime_Config::CONFIDENCE_CEILING ) );
	}

	/**
	 * TRANSITION confidence reflects certainty that this IS a
	 * transitional/ambiguous state, not certainty about a specific
	 * direction — deliberately capped below the "clear regime" range
	 * (Bitmomo_Regime_Config::TRANSITION_CONFIDENCE_CEILING) so a founder
	 * scanning the number alone never mistakes it for a confident call.
	 */
	private function transition_confidence( $top_score, $margin ) {
		$raw = 30 + min( $top_score, 40 ) * 0.3 - min( $margin, Bitmomo_Regime_Config::TRANSITION_MARGIN ) * 0.5;
		return (int) round( $this->clamp( $raw, Bitmomo_Regime_Config::CONFIDENCE_FLOOR, Bitmomo_Regime_Config::TRANSITION_CONFIDENCE_CEILING ) );
	}

	private function clamp( $value, $min, $max ) {
		return max( $min, min( $max, $value ) );
	}

	/**
	 * Trims a float to at most one decimal place for evidence strings —
	 * purely cosmetic, never affects scoring.
	 */
	private function fmt( $number ) {
		return rtrim( rtrim( number_format( (float) $number, 1, '.', '' ), '0' ), '.' );
	}

	private function score_regime( $regime, $input ) {
		switch ( $regime ) {
			case Bitmomo_Regime_Taxonomy::REGIME_ACCUMULATION:
				return $this->score_accumulation( $input );
			case Bitmomo_Regime_Taxonomy::REGIME_EXPANSION:
				return $this->score_expansion( $input );
			case Bitmomo_Regime_Taxonomy::REGIME_DISTRIBUTION:
				return $this->score_distribution( $input );
			case Bitmomo_Regime_Taxonomy::REGIME_CAPITULATION:
				return $this->score_capitulation( $input );
		}
		return array(
			'score'    => 0,
			'evidence' => array(),
		);
	}

	private function score_accumulation( $input ) {
		$score    = 0;
		$evidence = array();

		if ( $input['volatility_percentile'] <= Bitmomo_Regime_Config::ACCUM_LOW_VOL_PCTL ) {
			$score     += 25;
			$evidence[] = sprintf( 'Realized volatility percentile low (%s <= %s).', $this->fmt( $input['volatility_percentile'] ), $this->fmt( Bitmomo_Regime_Config::ACCUM_LOW_VOL_PCTL ) );
		}
		if ( abs( $input['return_30d'] ) <= Bitmomo_Regime_Config::ACCUM_RANGE_RETURN_ABS ) {
			$score     += 20;
			$evidence[] = sprintf( '30D return is range-bound (%s%%, within +/-%s%%).', $this->fmt( $input['return_30d'] ), $this->fmt( Bitmomo_Regime_Config::ACCUM_RANGE_RETURN_ABS ) );
		}
		if ( $input['range_position_pct'] <= Bitmomo_Regime_Config::ACCUM_LOW_RANGE_POSITION ) {
			$score     += 20;
			$evidence[] = sprintf( 'Price sits in the lower part of its recent range (%s pctl).', $this->fmt( $input['range_position_pct'] ) );
		}
		if ( 'range' === $input['structure_state'] ) {
			$score     += 15;
			$evidence[] = 'Structure state is range-bound.';
		}
		if ( $input['volume_percentile'] <= Bitmomo_Regime_Config::ACCUM_MODERATE_VOLUME_PCTL ) {
			$score     += 10;
			$evidence[] = sprintf( 'Volume percentile subdued (%s).', $this->fmt( $input['volume_percentile'] ) );
		}
		$funding = isset( $input['funding_rate_pct'] ) ? $input['funding_rate_pct'] : null;
		if ( null !== $funding && $funding >= Bitmomo_Regime_Config::ACCUM_FUNDING_NEUTRAL_LOW && $funding <= Bitmomo_Regime_Config::ACCUM_FUNDING_NEUTRAL_HIGH ) {
			$score     += 10;
			$evidence[] = sprintf( 'Funding rate neutral (%s%%).', $this->fmt( $funding ) );
		}

		return array(
			'score'    => $score,
			'evidence' => $evidence,
		);
	}

	private function score_expansion( $input ) {
		$score    = 0;
		$evidence = array();

		if ( 'breakout_up' === $input['structure_state'] ) {
			$score     += 30;
			$evidence[] = 'Structure state is breakout_up.';
		}
		if ( $input['momentum_score'] >= Bitmomo_Regime_Config::EXP_MOMENTUM_STRONG_POS ) {
			$score     += 25;
			$evidence[] = sprintf( 'Momentum strongly positive (%s >= %s).', $this->fmt( $input['momentum_score'] ), $this->fmt( Bitmomo_Regime_Config::EXP_MOMENTUM_STRONG_POS ) );
		}
		if ( $input['volume_percentile'] >= Bitmomo_Regime_Config::EXP_HIGH_VOLUME_PCTL ) {
			$score     += 15;
			$evidence[] = sprintf( 'Volume percentile elevated (%s >= %s).', $this->fmt( $input['volume_percentile'] ), $this->fmt( Bitmomo_Regime_Config::EXP_HIGH_VOLUME_PCTL ) );
		}
		if ( $input['return_7d'] >= Bitmomo_Regime_Config::EXP_RETURN_7D_MIN ) {
			$score     += 15;
			$evidence[] = sprintf( '7D return strong (%s%% >= %s%%).', $this->fmt( $input['return_7d'] ), $this->fmt( Bitmomo_Regime_Config::EXP_RETURN_7D_MIN ) );
		}
		$oi_change = isset( $input['open_interest_change_pct'] ) ? $input['open_interest_change_pct'] : null;
		if ( null !== $oi_change && $oi_change > Bitmomo_Regime_Config::EXP_OI_CHANGE_MIN ) {
			$score     += 10;
			$evidence[] = sprintf( 'Open interest expanding (%s%%).', $this->fmt( $oi_change ) );
		}
		if ( $input['range_position_pct'] >= Bitmomo_Regime_Config::EXP_HIGH_RANGE_POSITION ) {
			$score     += 10;
			$evidence[] = sprintf( 'Price pushing toward the top of its range (%s pctl).', $this->fmt( $input['range_position_pct'] ) );
		}

		return array(
			'score'    => $score,
			'evidence' => $evidence,
		);
	}

	private function score_distribution( $input ) {
		$score    = 0;
		$evidence = array();

		if ( $input['range_position_pct'] >= Bitmomo_Regime_Config::DIST_HIGH_RANGE_POSITION ) {
			$score     += 20;
			$evidence[] = sprintf( 'Price near the top of its recent range (%s pctl).', $this->fmt( $input['range_position_pct'] ) );
		}
		if ( $input['momentum_score'] >= Bitmomo_Regime_Config::DIST_MOMENTUM_DECEL_LOW && $input['momentum_score'] <= Bitmomo_Regime_Config::DIST_MOMENTUM_DECEL_HIGH ) {
			$score     += 20;
			$evidence[] = sprintf( 'Momentum decelerating despite elevated price (%s).', $this->fmt( $input['momentum_score'] ) );
		}
		if ( $input['volume_percentile'] >= Bitmomo_Regime_Config::DIST_HIGH_VOLUME_PCTL && $input['return_7d'] <= Bitmomo_Regime_Config::DIST_STALL_RETURN_7D_MAX ) {
			$score     += 15;
			$evidence[] = sprintf( 'High volume (%s pctl) with a stalling 7D return (%s%%).', $this->fmt( $input['volume_percentile'] ), $this->fmt( $input['return_7d'] ) );
		}
		$funding = isset( $input['funding_rate_pct'] ) ? $input['funding_rate_pct'] : null;
		if ( null !== $funding && $funding >= Bitmomo_Regime_Config::DIST_EXTREME_FUNDING_POS ) {
			$score     += 20;
			$evidence[] = sprintf( 'Funding rate extremely positive — crowded long (%s%%).', $this->fmt( $funding ) );
		}
		$crowding = isset( $input['crowding_score'] ) ? $input['crowding_score'] : null;
		if ( null !== $crowding && $crowding >= Bitmomo_Regime_Config::DIST_HIGH_CROWDING ) {
			$score     += 15;
			$evidence[] = sprintf( 'Crowding score high (%s).', $this->fmt( $crowding ) );
		}
		if ( 'range' === $input['structure_state'] && $input['range_position_pct'] >= Bitmomo_Regime_Config::DIST_HIGH_RANGE_POSITION ) {
			$score     += 10;
			$evidence[] = 'Structure state is range-bound near the highs.';
		}

		return array(
			'score'    => $score,
			'evidence' => $evidence,
		);
	}

	private function score_capitulation( $input ) {
		$score    = 0;
		$evidence = array();

		if ( $input['return_1d'] <= Bitmomo_Regime_Config::CAP_SHARP_DROP_1D || $input['return_7d'] <= Bitmomo_Regime_Config::CAP_SHARP_DROP_7D ) {
			$score     += 30;
			$evidence[] = sprintf( 'Sharp drawdown (1D %s%%, 7D %s%%).', $this->fmt( $input['return_1d'] ), $this->fmt( $input['return_7d'] ) );
		}
		if ( $input['volatility_percentile'] >= Bitmomo_Regime_Config::CAP_HIGH_VOL_PCTL && $input['volatility_change'] > Bitmomo_Regime_Config::CAP_VOL_SHOCK_DELTA ) {
			$score     += 20;
			$evidence[] = sprintf( 'Volatility shock (percentile %s, change +%s).', $this->fmt( $input['volatility_percentile'] ), $this->fmt( $input['volatility_change'] ) );
		}
		$liquidation = isset( $input['liquidation_pressure'] ) ? $input['liquidation_pressure'] : null;
		if ( 'extreme' === $liquidation ) {
			$score     += 25;
			$evidence[] = 'Liquidation pressure extreme.';
		} elseif ( 'elevated' === $liquidation ) {
			$score     += 12;
			$evidence[] = 'Liquidation pressure elevated.';
		}
		if ( in_array( $input['structure_state'], array( 'breakdown', 'breakout_down' ), true ) ) {
			$score     += 15;
			$evidence[] = sprintf( 'Structure state is %s.', $input['structure_state'] );
		}
		$funding = isset( $input['funding_rate_pct'] ) ? $input['funding_rate_pct'] : null;
		if ( null !== $funding && $funding <= Bitmomo_Regime_Config::CAP_EXTREME_FUNDING_NEG ) {
			$score     += 10;
			$evidence[] = sprintf( 'Funding rate flipped extreme negative (%s%%).', $this->fmt( $funding ) );
		}
		if ( $input['momentum_score'] <= Bitmomo_Regime_Config::CAP_MOMENTUM_STRONG_NEG ) {
			$score     += 10;
			$evidence[] = sprintf( 'Momentum strongly negative (%s).', $this->fmt( $input['momentum_score'] ) );
		}

		return array(
			'score'    => $score,
			'evidence' => $evidence,
		);
	}
}

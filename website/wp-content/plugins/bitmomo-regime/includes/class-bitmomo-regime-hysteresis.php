<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transition hysteresis for Product A (REGIME PR 2).
 *
 * Prevents regime flip-flopping: Accumulation -> Transition -> Expansion
 * is preferable to Accumulation -> Expansion -> Accumulation -> Expansion
 * on tiny daily fluctuations, per the PR spec's example.
 *
 * Pure function: apply() takes today's classifier result and the last
 * PERSISTED official state record (or null if none exists) and decides
 * whether the official regime actually changes this evaluation. No
 * WordPress dependency, no I/O — directly unit testable (see
 * tests/test-bitmomo-regime-hysteresis.php). Persistence itself is
 * Bitmomo_Regime_State_Store's job, not this class's.
 *
 * Never hides a state change: the decision always exposes the raw
 * candidate regime, any pending regime being watched, how many
 * consecutive evaluations have agreed on it, a transition_strength label,
 * and a human-readable reason — even when the official regime does NOT
 * change this evaluation (transition_strength 'pending' or 'held').
 *
 * Deliberate policy (see Bitmomo_Regime_Config for the exact thresholds):
 * - No previous record -> accept the candidate as-is ('initial').
 * - Candidate matches the current official regime -> no change ('held'),
 *   and any in-progress pending streak toward a different regime is
 *   cleared (the market walked back toward where it already was).
 * - Candidate is TRANSITION -> always reflected immediately
 *   ('transitioned') — it is a non-committal, low-certainty state, so
 *   there is no flip-flop risk in surfacing it in real time.
 * - Candidate is a different clear regime with confidence at or above
 *   HYSTERESIS_OVERRIDE_CONFIDENCE -> switches immediately ('overridden')
 *   — an obvious, severe move must not be artificially delayed.
 * - Otherwise -> requires HYSTERESIS_CONFIRMATION_STREAK consecutive
 *   evaluations proposing the SAME new regime before it becomes official
 *   ('confirmed' once the streak is met, 'pending' while still building).
 */
class Bitmomo_Regime_Hysteresis {

	/**
	 * @param array      $classifier_result Output of Bitmomo_Regime_Classifier::evaluate() for THIS evaluation.
	 * @param array|null $previous_record   The last persisted official state record — expects keys
	 *                                      'regime', 'pending_regime', 'pending_streak' — or null if this
	 *                                      is the very first evaluation ever recorded.
	 *
	 * @return array{
	 *   regime:string, candidate_regime:string, pending_regime:?string,
	 *   pending_streak:int, transition_strength:string, transition_reason:string,
	 *   changed:bool
	 * }
	 */
	public function apply( $classifier_result, $previous_record ) {
		$candidate  = $classifier_result['regime'];
		$confidence = $classifier_result['confidence'];

		if ( null === $previous_record ) {
			return $this->decision(
				$candidate,
				$candidate,
				null,
				0,
				'initial',
				'No previous state record exists — accepting the first evaluation as-is.',
				true
			);
		}

		$previous_regime = $previous_record['regime'];

		if ( $candidate === $previous_regime ) {
			return $this->decision(
				$previous_regime,
				$candidate,
				null,
				0,
				'held',
				sprintf( 'Candidate regime matches the current official regime (%s) — no change.', Bitmomo_Regime_Taxonomy::regime_label_en( $previous_regime ) ),
				false
			);
		}

		if ( Bitmomo_Regime_Taxonomy::REGIME_TRANSITION === $candidate ) {
			return $this->decision(
				Bitmomo_Regime_Taxonomy::REGIME_TRANSITION,
				$candidate,
				null,
				0,
				'transitioned',
				sprintf(
					'Classifier reports transition (uncertain) away from official regime %s — reflected immediately since transition is a non-committal state, not a competing regime claim.',
					Bitmomo_Regime_Taxonomy::regime_label_en( $previous_regime )
				),
				true
			);
		}

		if ( $confidence >= Bitmomo_Regime_Config::HYSTERESIS_OVERRIDE_CONFIDENCE ) {
			return $this->decision(
				$candidate,
				$candidate,
				null,
				0,
				'overridden',
				sprintf(
					'Confidence %d%% >= override threshold %d%% — switching immediately from %s to %s without waiting for confirmation.',
					$confidence,
					Bitmomo_Regime_Config::HYSTERESIS_OVERRIDE_CONFIDENCE,
					Bitmomo_Regime_Taxonomy::regime_label_en( $previous_regime ),
					Bitmomo_Regime_Taxonomy::regime_label_en( $candidate )
				),
				true
			);
		}

		$prior_pending = isset( $previous_record['pending_regime'] ) ? $previous_record['pending_regime'] : null;
		$prior_streak  = isset( $previous_record['pending_streak'] ) ? (int) $previous_record['pending_streak'] : 0;

		$streak = ( $prior_pending === $candidate ) ? $prior_streak + 1 : 1;

		if ( $streak >= Bitmomo_Regime_Config::HYSTERESIS_CONFIRMATION_STREAK ) {
			return $this->decision(
				$candidate,
				$candidate,
				null,
				0,
				'confirmed',
				sprintf(
					'Candidate regime %s confirmed across %d consecutive evaluations (>= %d required) — switching from %s.',
					Bitmomo_Regime_Taxonomy::regime_label_en( $candidate ),
					$streak,
					Bitmomo_Regime_Config::HYSTERESIS_CONFIRMATION_STREAK,
					Bitmomo_Regime_Taxonomy::regime_label_en( $previous_regime )
				),
				true
			);
		}

		return $this->decision(
			$previous_regime, // Hold the official regime — do not flip yet.
			$candidate,
			$candidate,
			$streak,
			'pending',
			sprintf(
				'Candidate regime %s seen %d/%d times — holding official regime %s until confirmed.',
				Bitmomo_Regime_Taxonomy::regime_label_en( $candidate ),
				$streak,
				Bitmomo_Regime_Config::HYSTERESIS_CONFIRMATION_STREAK,
				Bitmomo_Regime_Taxonomy::regime_label_en( $previous_regime )
			),
			false
		);
	}

	private function decision( $regime, $candidate_regime, $pending_regime, $pending_streak, $strength, $reason, $changed ) {
		return array(
			'regime'              => $regime,
			'candidate_regime'    => $candidate_regime,
			'pending_regime'      => $pending_regime,
			'pending_streak'      => $pending_streak,
			'transition_strength' => $strength,
			'transition_reason'   => $reason,
			'changed'             => $changed,
		);
	}
}

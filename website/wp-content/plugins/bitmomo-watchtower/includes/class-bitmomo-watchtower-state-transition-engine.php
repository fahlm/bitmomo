<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The state transition engine for Product B (WATCHTOWER PR 3).
 *
 * Pure PHP, no I/O, no LLM — compares a newly computed candidate thesis
 * (see Bitmomo_Watchtower_Thesis) against the current persisted thesis
 * (Bitmomo_Watchtower_Thesis_Store::get_current()) and classifies exactly
 * what kind of change this is, per the spec's six categories. This PR
 * does NOT use an LLM to manufacture the new candidate thesis itself —
 * that stays a caller/upstream responsibility; this class only compares
 * two already-computed theses (or a lack of one).
 *
 * Priority order (first match wins — see evaluate()):
 *   1. DATA_DEGRADED       — upstream data quality flagged bad, overrides everything else.
 *   2. THESIS_CHANGE       — no previous thesis exists (establishing the very first one).
 *   3. THESIS_INVALIDATED  — the previous thesis's invalidation condition was triggered.
 *   4. THESIS_CHANGE       — regime and/or directional_bias differs from the previous thesis.
 *   5. CONFIDENCE_CHANGE   — confidence moved by >= Bitmomo_Watchtower_Config::CONFIDENCE_CHANGE_THRESHOLD points.
 *   6. MATERIAL_CONTEXT_UPDATE — expected range, invalidation text, or major driver shifted materially.
 *   7. NO_CHANGE           — none of the above.
 */
class Bitmomo_Watchtower_State_Transition_Engine {

	const TYPE_NO_CHANGE              = 'NO_CHANGE';
	const TYPE_MATERIAL_CONTEXT_UPDATE = 'MATERIAL_CONTEXT_UPDATE';
	const TYPE_CONFIDENCE_CHANGE      = 'CONFIDENCE_CHANGE';
	const TYPE_THESIS_CHANGE          = 'THESIS_CHANGE';
	const TYPE_THESIS_INVALIDATED     = 'THESIS_INVALIDATED';
	const TYPE_DATA_DEGRADED          = 'DATA_DEGRADED';

	const TYPES = array(
		self::TYPE_NO_CHANGE,
		self::TYPE_MATERIAL_CONTEXT_UPDATE,
		self::TYPE_CONFIDENCE_CHANGE,
		self::TYPE_THESIS_CHANGE,
		self::TYPE_THESIS_INVALIDATED,
		self::TYPE_DATA_DEGRADED,
	);

	const COMPARABLE_FIELDS = array(
		'regime',
		'directional_bias',
		'confidence',
		'expected_range_low',
		'expected_range_high',
		'invalidation',
		'major_driver',
	);

	/**
	 * @param array      $new_thesis      A validated Bitmomo_Watchtower_Thesis input array.
	 * @param array|null $previous_thesis The current persisted thesis (Bitmomo_Watchtower_Thesis_Store::get_current()'s
	 *                                     shape, or a validated Thesis input array), or null if none exists yet.
	 * @param array      $context         Optional flags an upstream caller supplies, since this
	 *                                     engine cannot itself observe live price/data-quality state:
	 *                                     - 'invalidation_triggered' (bool): the PREVIOUS thesis's
	 *                                       invalidation condition was met (e.g. price crossed the
	 *                                       stored invalidation level) — a live-data judgment call
	 *                                       made upstream, not by this pure class.
	 *                                     - 'data_quality_degraded' (bool): upstream data feeds are
	 *                                       currently degraded; overrides every other classification.
	 *
	 * @return array{transition_type:string,reason:string,diff:array}
	 */
	public function evaluate( $new_thesis, $previous_thesis, array $context = array() ) {
		if ( ! empty( $context['data_quality_degraded'] ) ) {
			return $this->decision(
				self::TYPE_DATA_DEGRADED,
				'Upstream data quality has degraded — this evaluation cannot be trusted regardless of what the computed thesis looks like.',
				$this->diff( $previous_thesis, $new_thesis )
			);
		}

		if ( null === $previous_thesis ) {
			return $this->decision(
				self::TYPE_THESIS_CHANGE,
				'No previous thesis exists — establishing the initial thesis.',
				$this->diff( null, $new_thesis )
			);
		}

		if ( ! empty( $context['invalidation_triggered'] ) ) {
			return $this->decision(
				self::TYPE_THESIS_INVALIDATED,
				sprintf( 'The previous thesis\'s invalidation condition was triggered: %s', $previous_thesis['invalidation'] ),
				$this->diff( $previous_thesis, $new_thesis )
			);
		}

		$diff = $this->diff( $previous_thesis, $new_thesis );

		if ( $new_thesis['regime'] !== $previous_thesis['regime'] || $new_thesis['directional_bias'] !== $previous_thesis['directional_bias'] ) {
			return $this->decision(
				self::TYPE_THESIS_CHANGE,
				'Regime and/or directional bias differs from the previous thesis.',
				$diff
			);
		}

		$confidence_delta = abs( (float) $new_thesis['confidence'] - (float) $previous_thesis['confidence'] );
		if ( $confidence_delta >= Bitmomo_Watchtower_Config::CONFIDENCE_CHANGE_THRESHOLD ) {
			return $this->decision(
				self::TYPE_CONFIDENCE_CHANGE,
				sprintf(
					'Confidence moved by %.1f points (threshold %.1f), with regime and bias unchanged.',
					$confidence_delta,
					Bitmomo_Watchtower_Config::CONFIDENCE_CHANGE_THRESHOLD
				),
				$diff
			);
		}

		if ( $this->context_changed_materially( $previous_thesis, $new_thesis ) ) {
			return $this->decision(
				self::TYPE_MATERIAL_CONTEXT_UPDATE,
				'Regime, bias, and confidence are unchanged, but supporting context (expected range, invalidation, or major driver) shifted materially.',
				$diff
			);
		}

		return $this->decision(
			self::TYPE_NO_CHANGE,
			'No material difference from the current thesis.',
			$diff
		);
	}

	private function context_changed_materially( $prev, $new ) {
		if ( $this->range_bound_changed_materially( $prev['expected_range_low'], $new['expected_range_low'] ) ) {
			return true;
		}
		if ( $this->range_bound_changed_materially( $prev['expected_range_high'], $new['expected_range_high'] ) ) {
			return true;
		}
		if ( trim( (string) $prev['invalidation'] ) !== trim( (string) $new['invalidation'] ) ) {
			return true;
		}
		if ( trim( (string) $prev['major_driver'] ) !== trim( (string) $new['major_driver'] ) ) {
			return true;
		}
		return false;
	}

	private function range_bound_changed_materially( $old_value, $new_value ) {
		$old_value = (float) $old_value;
		$new_value = (float) $new_value;

		if ( 0.0 === $old_value ) {
			// Avoid a division by zero; any change away from an exact
			// zero bound is treated as material.
			return $new_value !== $old_value;
		}

		$pct_change = abs( $new_value - $old_value ) / abs( $old_value ) * 100;
		return $pct_change >= Bitmomo_Watchtower_Config::EXPECTED_RANGE_CHANGE_PCT;
	}

	/**
	 * Field-by-field diff between the previous and new thesis, listing
	 * which comparable fields actually changed — used by the alert
	 * outbox payload (WATCHTOWER PR 3) so an eventual consumer sees
	 * exactly what moved, not just the classification label.
	 */
	private function diff( $prev, $new ) {
		if ( null === $prev ) {
			return array(
				'previous'       => null,
				'new'            => $new,
				'changed_fields' => self::COMPARABLE_FIELDS,
			);
		}

		$changed_fields = array();
		foreach ( self::COMPARABLE_FIELDS as $field ) {
			if ( (string) $prev[ $field ] !== (string) $new[ $field ] ) {
				$changed_fields[] = $field;
			}
		}

		return array(
			'previous'       => $prev,
			'new'            => $new,
			'changed_fields' => $changed_fields,
		);
	}

	private function decision( $type, $reason, $diff ) {
		return array(
			'transition_type' => $type,
			'reason'           => $reason,
			'diff'             => $diff,
		);
	}
}

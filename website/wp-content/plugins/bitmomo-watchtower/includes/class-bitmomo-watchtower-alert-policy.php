<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The cooldown / alert policy for Product B (WATCHTOWER PR 3).
 *
 * Pure PHP, no I/O, no LLM — decides whether a given state transition
 * SHOULD produce an alert, and which of the five spec-mandated alert
 * classes it belongs to. This class does NOT send anything anywhere —
 * see Bitmomo_Watchtower_Alert_Outbox for the provider-neutral queue
 * this decision feeds into. No Telegram credentials, no external call,
 * exist anywhere in this plugin.
 *
 * NO_CHANGE never produces an alert — that mapping is absent from
 * TRANSITION_ALERT_CLASS_MAP entirely, not merely defaulted to
 * "don't alert," so a future transition type added without an explicit
 * mapping fails closed (evaluate() treats "unmapped" the same as
 * NO_CHANGE) rather than silently alerting on something unrecognized.
 */
class Bitmomo_Watchtower_Alert_Policy {

	const ALERT_CLASS_MATERIAL_CHANGE     = 'MATERIAL_CHANGE';
	const ALERT_CLASS_CONFIDENCE_SHIFT    = 'CONFIDENCE_SHIFT';
	const ALERT_CLASS_THESIS_CHANGE       = 'THESIS_CHANGE';
	const ALERT_CLASS_THESIS_INVALIDATED  = 'THESIS_INVALIDATED';
	const ALERT_CLASS_DATA_QUALITY        = 'DATA_QUALITY';

	const ALERT_CLASSES = array(
		self::ALERT_CLASS_MATERIAL_CHANGE,
		self::ALERT_CLASS_CONFIDENCE_SHIFT,
		self::ALERT_CLASS_THESIS_CHANGE,
		self::ALERT_CLASS_THESIS_INVALIDATED,
		self::ALERT_CLASS_DATA_QUALITY,
	);

	const TRANSITION_ALERT_CLASS_MAP = array(
		Bitmomo_Watchtower_State_Transition_Engine::TYPE_MATERIAL_CONTEXT_UPDATE => self::ALERT_CLASS_MATERIAL_CHANGE,
		Bitmomo_Watchtower_State_Transition_Engine::TYPE_CONFIDENCE_CHANGE       => self::ALERT_CLASS_CONFIDENCE_SHIFT,
		Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_CHANGE          => self::ALERT_CLASS_THESIS_CHANGE,
		Bitmomo_Watchtower_State_Transition_Engine::TYPE_THESIS_INVALIDATED     => self::ALERT_CLASS_THESIS_INVALIDATED,
		Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED          => self::ALERT_CLASS_DATA_QUALITY,
		// TYPE_NO_CHANGE intentionally absent — never produces an alert.
	);

	/**
	 * Alert classes severe enough that a cooldown must never suppress
	 * them — mirrors Bitmomo_Regime_Hysteresis's confidence-override
	 * principle ("an obvious, severe move must not be artificially
	 * delayed"): the market or the data feed being wrong is exactly the
	 * kind of thing a cooldown timer should never sit on.
	 */
	const COOLDOWN_EXEMPT_ALERT_CLASSES = array(
		self::ALERT_CLASS_THESIS_INVALIDATED,
		self::ALERT_CLASS_DATA_QUALITY,
	);

	/**
	 * @param string      $transition_type          One of Bitmomo_Watchtower_State_Transition_Engine::TYPES.
	 * @param string|null $last_alert_at_for_class   'Y-m-d H:i:s' timestamp of the last alert actually
	 *                                                 SENT for this alert class (from
	 *                                                 Bitmomo_Watchtower_Alert_Outbox), or null if none yet.
	 * @param string|null $now                       Override for "now" (testability); defaults to the
	 *                                                 current time.
	 *
	 * @return array{alert_class:?string,should_alert:bool,reason:string}
	 */
	public function evaluate( $transition_type, $last_alert_at_for_class = null, $now = null ) {
		if ( ! isset( self::TRANSITION_ALERT_CLASS_MAP[ $transition_type ] ) ) {
			return array(
				'alert_class'   => null,
				'should_alert'  => false,
				'reason'        => 'This transition type never produces an alert (NO_CHANGE, or an unrecognized type treated the same way).',
			);
		}

		$alert_class = self::TRANSITION_ALERT_CLASS_MAP[ $transition_type ];

		if ( in_array( $alert_class, self::COOLDOWN_EXEMPT_ALERT_CLASSES, true ) ) {
			return array(
				'alert_class'  => $alert_class,
				'should_alert' => true,
				'reason'       => sprintf( '%s is cooldown-exempt — always alerts immediately.', $alert_class ),
			);
		}

		if ( null !== $last_alert_at_for_class ) {
			$now_ts          = null === $now ? time() : strtotime( (string) $now );
			$last_ts          = strtotime( (string) $last_alert_at_for_class );
			$elapsed_minutes = ( $now_ts - $last_ts ) / 60;

			if ( $elapsed_minutes < Bitmomo_Watchtower_Config::ALERT_COOLDOWN_MINUTES ) {
				return array(
					'alert_class'  => $alert_class,
					'should_alert' => false,
					'reason'       => sprintf(
						'Cooldown active for %s: last alerted %.1f minutes ago (cooldown %d minutes).',
						$alert_class,
						$elapsed_minutes,
						Bitmomo_Watchtower_Config::ALERT_COOLDOWN_MINUTES
					),
				);
			}
		}

		return array(
			'alert_class'  => $alert_class,
			'should_alert' => true,
			'reason'       => sprintf( '%s is outside any cooldown window (or has never alerted before).', $alert_class ),
		);
	}
}

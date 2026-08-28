<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WATCHTOWER PR 3 orchestration entrypoint — ties the state
 * transition engine, analyst router, alert policy, thesis store, and
 * alert outbox together into one callable, mirroring
 * Bitmomo_Regime_State_Store::evaluate_and_record()'s role in Product A.
 *
 * This is the ONLY class in this PR that touches multiple WordPress
 * state seams (Bitmomo_Watchtower_Thesis_Store and
 * Bitmomo_Watchtower_Alert_Outbox); the four decision classes it calls
 * (State Transition Engine, Analyst Router, Alert Policy) all remain
 * pure PHP with no WordPress dependency and are unit tested directly.
 *
 * NO CRON, NO LIVE DATA FETCH, NO LLM, NO TELEGRAM SEND anywhere in this
 * class. process() is a plain callable for a future scheduled job
 * (Codex's runtime work) to invoke with an already-computed candidate
 * thesis.
 *
 * Persistence policy: a NO_CHANGE evaluation does NOT write a new thesis
 * record (nothing changed — the currently persisted thesis stays
 * current, so no redundant duplicate is created). Every OTHER transition
 * type persists a new append-only thesis record via
 * Bitmomo_Watchtower_Thesis_Store::set_current(), consistent with "never
 * hide a state change."
 */
class Bitmomo_Watchtower_Orchestrator {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * @param array $raw_thesis A candidate thesis — see Bitmomo_Watchtower_Thesis::REQUIRED_FIELDS.
	 * @param array $context    Optional flags passed through to Bitmomo_Watchtower_State_Transition_Engine::evaluate()
	 *                          ('invalidation_triggered', 'data_quality_degraded'), plus an optional
	 *                          'domain' used only for analyst routing (not part of the thesis schema itself).
	 *
	 * @return array{
	 *   success:bool, errors:string[], transition:?array, alert_decision:?array,
	 *   routing:?array, thesis_persisted:bool, thesis_record:?array, alert_record:?array
	 * }
	 */
	public function process( $raw_thesis, array $context = array() ) {
		$validation = Bitmomo_Watchtower_Thesis::validate( $raw_thesis );
		if ( ! $validation['valid'] ) {
			return $this->failure( $validation['errors'] );
		}
		$new_thesis = $validation['input'];

		$thesis_store = Bitmomo_Watchtower_Thesis_Store::instance();
		$previous     = $thesis_store->get_current();

		$transition_engine = new Bitmomo_Watchtower_State_Transition_Engine();
		$transition        = $transition_engine->evaluate( $new_thesis, $previous, $context );

		$alert_policy = new Bitmomo_Watchtower_Alert_Policy();
		$outbox       = Bitmomo_Watchtower_Alert_Outbox::instance();

		$alert_class_map = Bitmomo_Watchtower_Alert_Policy::TRANSITION_ALERT_CLASS_MAP;
		$prospective_class = isset( $alert_class_map[ $transition['transition_type'] ] ) ? $alert_class_map[ $transition['transition_type'] ] : null;
		$last_alert       = null !== $prospective_class ? $outbox->get_last_for_class( $prospective_class ) : null;

		$alert_decision = $alert_policy->evaluate(
			$transition['transition_type'],
			null !== $last_alert ? $last_alert['created_at'] : null
		);

		$domain = isset( $context['domain'] ) ? $context['domain'] : null;
		$router = new Bitmomo_Watchtower_Analyst_Router();
		$routing = $router->route( $transition['transition_type'], $domain );

		$thesis_persisted = false;
		$thesis_record    = null;
		if ( Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE !== $transition['transition_type'] ) {
			$store_result = $thesis_store->set_current( $raw_thesis );
			if ( $store_result['success'] ) {
				$thesis_persisted = true;
				$thesis_record    = $store_result['record'];
			}
		}

		$alert_record = null;
		if ( $alert_decision['should_alert'] ) {
			$enqueue_result = $outbox->enqueue(
				array(
					'alert_class'     => $alert_decision['alert_class'],
					'transition_type' => $transition['transition_type'],
					'reason'          => $transition['reason'],
					'track'           => isset( $routing['track'] ) ? $routing['track'] : '',
					'domain'          => (string) $domain,
					'payload'         => $transition['diff'],
				)
			);
			if ( $enqueue_result['success'] ) {
				$alert_record = $enqueue_result['record'];
			}
		}

		return array(
			'success'          => true,
			'errors'           => array(),
			'transition'       => $transition,
			'alert_decision'   => $alert_decision,
			'routing'          => $routing,
			'thesis_persisted' => $thesis_persisted,
			'thesis_record'    => $thesis_record,
			'alert_record'     => $alert_record,
		);
	}

	private function failure( $errors ) {
		return array(
			'success'          => false,
			'errors'           => $errors,
			'transition'       => null,
			'alert_decision'   => null,
			'routing'          => null,
			'thesis_persisted' => false,
			'thesis_record'    => null,
			'alert_record'     => null,
		);
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The selective analyst router INTERFACE for Product B (WATCHTOWER PR 3).
 *
 * This is a ROUTING TABLE ONLY. It answers "which analyst track would
 * this transition be sent to," nothing more — it does NOT implement the
 * full multi-agent analyst system, and it calls NO LLM anywhere. A
 * future PR (well beyond PR3) may eventually wire a real analyst process
 * behind one of these track names; this class exists so that future PR
 * has a stable, already-tested routing decision to build on, without
 * this PR pretending any analyst actually exists yet.
 *
 * Deterministic and pure: track selection depends only on the
 * transition_type and (when relevant) the triggering domain, both
 * already-known values — no data fetching, no model call, no randomness.
 */
class Bitmomo_Watchtower_Analyst_Router {

	const TRACK_MARKET_STRUCTURE = 'market_structure_analyst';
	const TRACK_DERIVATIVES      = 'derivatives_analyst';
	const TRACK_MACRO            = 'macro_analyst';
	const TRACK_GEOPOLITICAL     = 'geopolitical_analyst';
	const TRACK_REGULATORY       = 'regulatory_analyst';
	const TRACK_SENTIMENT        = 'sentiment_analyst';
	const TRACK_DATA_QUALITY     = 'data_quality_reviewer';
	const TRACK_GENERAL          = 'general_thesis_reviewer';

	const TRACKS = array(
		self::TRACK_MARKET_STRUCTURE,
		self::TRACK_DERIVATIVES,
		self::TRACK_MACRO,
		self::TRACK_GEOPOLITICAL,
		self::TRACK_REGULATORY,
		self::TRACK_SENTIMENT,
		self::TRACK_DATA_QUALITY,
		self::TRACK_GENERAL,
	);

	/**
	 * One entry per Bitmomo_Watchtower_Taxonomy domain. NEWS intentionally
	 * routes to the general track rather than a dedicated one — a "news"
	 * event on its own doesn't imply a specific desk the way MARKET or
	 * DERIVATIVES does.
	 */
	const DOMAIN_TRACK_MAP = array(
		Bitmomo_Watchtower_Taxonomy::DOMAIN_MARKET       => self::TRACK_MARKET_STRUCTURE,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_DERIVATIVES  => self::TRACK_DERIVATIVES,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_MACRO        => self::TRACK_MACRO,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_GEOPOLITICS  => self::TRACK_GEOPOLITICAL,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_REGULATION   => self::TRACK_REGULATORY,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_NEWS         => self::TRACK_GENERAL,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_SENTIMENT    => self::TRACK_SENTIMENT,
		Bitmomo_Watchtower_Taxonomy::DOMAIN_DATA_QUALITY => self::TRACK_DATA_QUALITY,
	);

	/**
	 * @param string      $transition_type One of Bitmomo_Watchtower_State_Transition_Engine::TYPES.
	 * @param string|null $domain          One of Bitmomo_Watchtower_Taxonomy::DOMAINS, or null.
	 *
	 * @return array{track:?string,reason:string}
	 */
	public function route( $transition_type, $domain = null ) {
		if ( Bitmomo_Watchtower_State_Transition_Engine::TYPE_DATA_DEGRADED === $transition_type ) {
			return array(
				'track'  => self::TRACK_DATA_QUALITY,
				'reason' => 'DATA_DEGRADED transitions always route to the data quality reviewer track, regardless of domain.',
			);
		}

		if ( Bitmomo_Watchtower_State_Transition_Engine::TYPE_NO_CHANGE === $transition_type ) {
			return array(
				'track'  => null,
				'reason' => 'NO_CHANGE transitions are not routed to any analyst track.',
			);
		}

		if ( null !== $domain && isset( self::DOMAIN_TRACK_MAP[ $domain ] ) ) {
			return array(
				'track'  => self::DOMAIN_TRACK_MAP[ $domain ],
				'reason' => sprintf( 'Routed by domain (%s).', $domain ),
			);
		}

		return array(
			'track'  => self::TRACK_GENERAL,
			'reason' => 'No specific domain matched — routed to the default general thesis reviewer track.',
		);
	}
}

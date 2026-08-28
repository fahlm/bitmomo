<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The canonical vocabulary for Product B (Bitmomo Watchtower) — domains,
 * the controlled event-type registry, and the materiality policy bands.
 * Single source of truth, mirroring how Bitmomo_Regime_Taxonomy anchors
 * Product A.
 *
 * The event-type registry is "controlled but extensible": every type this
 * plugin will ever recognize is named here up front (WATCHTOWER PR 1's
 * spec explicitly lists all 15), each mapped to exactly one owning
 * domain. Extending it later means adding one row to
 * EVENT_TYPE_DOMAIN_MAP, not touching any detector or the materiality
 * engine. This PR does NOT implement a detector for every type listed —
 * the registry exists so later PRs have a fixed vocabulary to detect
 * into, not so every detector exists today.
 */
class Bitmomo_Watchtower_Taxonomy {

	const DOMAIN_MARKET       = 'market';
	const DOMAIN_DERIVATIVES  = 'derivatives';
	const DOMAIN_MACRO        = 'macro';
	const DOMAIN_GEOPOLITICS  = 'geopolitics';
	const DOMAIN_REGULATION   = 'regulation';
	const DOMAIN_NEWS         = 'news';
	const DOMAIN_SENTIMENT    = 'sentiment';
	const DOMAIN_DATA_QUALITY = 'data_quality';

	const DOMAINS = array(
		self::DOMAIN_MARKET,
		self::DOMAIN_DERIVATIVES,
		self::DOMAIN_MACRO,
		self::DOMAIN_GEOPOLITICS,
		self::DOMAIN_REGULATION,
		self::DOMAIN_NEWS,
		self::DOMAIN_SENTIMENT,
		self::DOMAIN_DATA_QUALITY,
	);

	const EVENT_TYPE_PRICE_RANGE_BREAK       = 'PRICE_RANGE_BREAK';
	const EVENT_TYPE_VOLATILITY_SHOCK        = 'VOLATILITY_SHOCK';
	const EVENT_TYPE_VOLUME_SHOCK            = 'VOLUME_SHOCK';
	const EVENT_TYPE_OPEN_INTEREST_SHOCK     = 'OPEN_INTEREST_SHOCK';
	const EVENT_TYPE_FUNDING_EXTREME         = 'FUNDING_EXTREME';
	const EVENT_TYPE_LIQUIDATION_CASCADE     = 'LIQUIDATION_CASCADE';
	const EVENT_TYPE_SHORT_SQUEEZE           = 'SHORT_SQUEEZE';
	const EVENT_TYPE_LEVERAGE_BUILDUP        = 'LEVERAGE_BUILDUP';
	const EVENT_TYPE_MACRO_RELEASE_SURPRISE  = 'MACRO_RELEASE_SURPRISE';
	const EVENT_TYPE_CENTRAL_BANK_EVENT      = 'CENTRAL_BANK_EVENT';
	const EVENT_TYPE_GEOPOLITICAL_ESCALATION = 'GEOPOLITICAL_ESCALATION';
	const EVENT_TYPE_REGULATORY_EVENT        = 'REGULATORY_EVENT';
	const EVENT_TYPE_SYSTEMIC_CRYPTO_EVENT   = 'SYSTEMIC_CRYPTO_EVENT';
	const EVENT_TYPE_SENTIMENT_EXTREME       = 'SENTIMENT_EXTREME';
	const EVENT_TYPE_DATA_DEGRADED           = 'DATA_DEGRADED';

	const EVENT_TYPES = array(
		self::EVENT_TYPE_PRICE_RANGE_BREAK,
		self::EVENT_TYPE_VOLATILITY_SHOCK,
		self::EVENT_TYPE_VOLUME_SHOCK,
		self::EVENT_TYPE_OPEN_INTEREST_SHOCK,
		self::EVENT_TYPE_FUNDING_EXTREME,
		self::EVENT_TYPE_LIQUIDATION_CASCADE,
		self::EVENT_TYPE_SHORT_SQUEEZE,
		self::EVENT_TYPE_LEVERAGE_BUILDUP,
		self::EVENT_TYPE_MACRO_RELEASE_SURPRISE,
		self::EVENT_TYPE_CENTRAL_BANK_EVENT,
		self::EVENT_TYPE_GEOPOLITICAL_ESCALATION,
		self::EVENT_TYPE_REGULATORY_EVENT,
		self::EVENT_TYPE_SYSTEMIC_CRYPTO_EVENT,
		self::EVENT_TYPE_SENTIMENT_EXTREME,
		self::EVENT_TYPE_DATA_DEGRADED,
	);

	/**
	 * Every registered event type belongs to exactly one owning domain.
	 * Bitmomo_Watchtower_Event_Input cross-checks any caller-supplied
	 * `domain` against this map and rejects a mismatch rather than
	 * silently trusting the caller.
	 */
	const EVENT_TYPE_DOMAIN_MAP = array(
		self::EVENT_TYPE_PRICE_RANGE_BREAK       => self::DOMAIN_MARKET,
		self::EVENT_TYPE_VOLATILITY_SHOCK        => self::DOMAIN_MARKET,
		self::EVENT_TYPE_VOLUME_SHOCK            => self::DOMAIN_MARKET,
		self::EVENT_TYPE_SYSTEMIC_CRYPTO_EVENT   => self::DOMAIN_MARKET,
		self::EVENT_TYPE_OPEN_INTEREST_SHOCK     => self::DOMAIN_DERIVATIVES,
		self::EVENT_TYPE_FUNDING_EXTREME         => self::DOMAIN_DERIVATIVES,
		self::EVENT_TYPE_LIQUIDATION_CASCADE     => self::DOMAIN_DERIVATIVES,
		self::EVENT_TYPE_SHORT_SQUEEZE           => self::DOMAIN_DERIVATIVES,
		self::EVENT_TYPE_LEVERAGE_BUILDUP        => self::DOMAIN_DERIVATIVES,
		self::EVENT_TYPE_MACRO_RELEASE_SURPRISE  => self::DOMAIN_MACRO,
		self::EVENT_TYPE_CENTRAL_BANK_EVENT      => self::DOMAIN_MACRO,
		self::EVENT_TYPE_GEOPOLITICAL_ESCALATION => self::DOMAIN_GEOPOLITICS,
		self::EVENT_TYPE_REGULATORY_EVENT        => self::DOMAIN_REGULATION,
		self::EVENT_TYPE_SENTIMENT_EXTREME       => self::DOMAIN_SENTIMENT,
		self::EVENT_TYPE_DATA_DEGRADED           => self::DOMAIN_DATA_QUALITY,
	);

	/**
	 * Deterministic materiality policy bands (spec-mandated cut points —
	 * see Bitmomo_Watchtower_Config for the numeric thresholds):
	 * 0-39 noise, 40-59 interesting, 60-74 material candidate, 75-100
	 * critical candidate.
	 */
	const MATERIALITY_BAND_NOISE              = 'noise';
	const MATERIALITY_BAND_INTERESTING        = 'interesting';
	const MATERIALITY_BAND_MATERIAL_CANDIDATE = 'material_candidate';
	const MATERIALITY_BAND_CRITICAL_CANDIDATE = 'critical_candidate';

	const MATERIALITY_BANDS = array(
		self::MATERIALITY_BAND_NOISE,
		self::MATERIALITY_BAND_INTERESTING,
		self::MATERIALITY_BAND_MATERIAL_CANDIDATE,
		self::MATERIALITY_BAND_CRITICAL_CANDIDATE,
	);

	public static function is_valid_domain( $value ) {
		return in_array( $value, self::DOMAINS, true );
	}

	public static function is_valid_event_type( $value ) {
		return in_array( $value, self::EVENT_TYPES, true );
	}

	public static function is_valid_materiality_band( $value ) {
		return in_array( $value, self::MATERIALITY_BANDS, true );
	}

	/**
	 * @return string|null The owning domain for a registered event type, or null if unregistered.
	 */
	public static function domain_for_event_type( $event_type ) {
		return isset( self::EVENT_TYPE_DOMAIN_MAP[ $event_type ] ) ? self::EVENT_TYPE_DOMAIN_MAP[ $event_type ] : null;
	}

	/**
	 * WATCHTOWER PR 2 — the current-thesis vocabulary
	 * (Bitmomo_Watchtower_Thesis). These deliberately DUPLICATE the same
	 * five regimes and three biases bitmomo-regime uses, rather than
	 * importing Bitmomo_Regime_Taxonomy — the architecture rule is that
	 * bitmomo-watchtower has ZERO dependency on bitmomo-regime (or any
	 * other Bitmomo plugin). If Codex's runtime happens to feed Product
	 * A's regime output into a Watchtower thesis, that is a data-level
	 * choice made outside this codebase, not a code dependency between
	 * the two plugins.
	 */
	const THESIS_REGIME_ACCUMULATION = 'accumulation';
	const THESIS_REGIME_EXPANSION    = 'expansion';
	const THESIS_REGIME_DISTRIBUTION = 'distribution';
	const THESIS_REGIME_CAPITULATION = 'capitulation';
	const THESIS_REGIME_TRANSITION   = 'transition';

	const THESIS_REGIMES = array(
		self::THESIS_REGIME_ACCUMULATION,
		self::THESIS_REGIME_EXPANSION,
		self::THESIS_REGIME_DISTRIBUTION,
		self::THESIS_REGIME_CAPITULATION,
		self::THESIS_REGIME_TRANSITION,
	);

	const THESIS_BIAS_BULLISH = 'bullish';
	const THESIS_BIAS_NEUTRAL = 'neutral';
	const THESIS_BIAS_BEARISH = 'bearish';

	const THESIS_BIASES = array( self::THESIS_BIAS_BULLISH, self::THESIS_BIAS_NEUTRAL, self::THESIS_BIAS_BEARISH );

	public static function is_valid_thesis_regime( $value ) {
		return in_array( $value, self::THESIS_REGIMES, true );
	}

	public static function is_valid_thesis_bias( $value ) {
		return in_array( $value, self::THESIS_BIASES, true );
	}
}

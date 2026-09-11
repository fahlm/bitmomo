# BTC Mode Research — Data Dictionary

Status: DRAFT FOR RESEARCH

This file defines the minimum canonical fields needed for BTC Mode research. Exact source-specific field names may differ; research code must map them explicitly and document the mapping.

## Identity and time

- `observation_id` — immutable research observation identifier.
- `event_time` — time the underlying market observation represents.
- `knowledge_time` — earliest defensible time the observation was available to the model/user.
- `ingested_at` — ingestion timestamp.
- `session_type` — `us_pre_open` or `us_post_close` when session-aligned research is used.
- `session_anchor` — canonical America/New_York anchor represented by the observation.
- `us_market_status` — regular session day, weekend, holiday, early close, closed, or other canonical status.

Research feature construction must use information with `knowledge_time <= decision_time`.

## BTC reference and outcomes

Required BTC reference fields:

- `btc_price`
- source/provider
- price event time
- price knowledge time

Forward outcome fields:

- `return_6h`
- `return_24h`
- `return_72h`
- `return_7d`
- `mae_6h`, `mae_24h`, `mae_72h`, `mae_7d`
- `mfe_6h`, `mfe_24h`, `mfe_72h`, `mfe_7d`
- realized-volatility fields for the evaluated horizon where defensible

Outcome construction must never feed back into feature generation.

## BTC Core

Preserve raw/normalized inputs where available plus canonical engine outputs.

### Direction
- `adx`
- `plus_di`
- `minus_di`
- `bias_1h`
- `bias_4h`
- `bias_1d`
- direction score/status

### Structure
- canonical structure state
- daily structure state
- operational support/resistance if historically point-in-time reproducible
- structure score/status

### Carry
- funding rate
- futures/mark-index basis
- carry data status
- carry score/status

### Crowding / derivatives
- open-interest change
- BTC price change used by crowding logic
- global long/short ratio when historically available
- taker buy/sell ratio when historically available
- crowding score/status

### Volatility
- ATR-based fields used by the production engine
- Bollinger-width field if used
- volatility regime
- volatility percentile

### Canonical BTC Core outputs
- `directional_score`
- `directional_bias`
- `direction_strength`
- `confidence`
- `data_quality_status`
- source completeness/freshness fields
- engine/rule version

## Market Regime

- `regime` — accumulation / expansion / distribution / capitulation / transition
- `regime_confidence` or canonical regime certainty
- per-regime scores
- evidence
- conflicts
- classifier version
- source data quality

Market Regime remains semantically separate from Directional Bias and BTC Mode.

## Bond / Macro

Prefer production-candidate Treasury-derived evidence first.

- US 2Y level and relevant changes
- US 10Y level and relevant changes
- 10Y real yield level and relevant changes
- breakeven inflation level and relevant changes
- 2s10s curve level/change
- policy-repricing state
- real-rate-pressure state
- inflation-repricing state
- curve state
- bond-volatility state when available
- source maturity
- provenance
- freshness
- `knowledge_time_status`
- Bond schema/rule version

Experimental sources such as Yahoo-derived MOVE/DXY/Nasdaq must be labeled experimental and tested separately from production-candidate Treasury evidence.

## Quality and provenance

Every observation used in analysis should retain, where available:

- provider/source
- event time
- knowledge time
- ingestion time
- freshness/age
- source maturity
- fallback status
- normalized error/status
- engine/classifier/schema/rule versions

Missing data must remain missing. Do not silently substitute zeros or reconstruct unavailable intraday observations without an explicit, documented research rule.

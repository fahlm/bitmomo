# BTC Mode Research — Data Dictionary

Status: DRAFT FOR RESEARCH

This file defines the minimum canonical fields needed for BTC Mode research. Exact source-specific field names may differ; research code must map them explicitly and document the mapping.

## Dataset identity

Each generated dataset must record:

- `dataset_id`
- coverage start/end
- observation cadence (`session_aligned` or `4h_robustness`)
- row count
- source versions
- engine/classifier/rule versions
- creation timestamp
- research-code commit SHA where applicable
- checksum/hash of the frozen export

## Identity and time

- `observation_id` — immutable research observation identifier.
- `decision_time` — timestamp at which BTC Mode could have been evaluated.
- `event_time` — time the underlying market observation represents.
- `knowledge_time` — earliest defensible time the observation was available to the model/user.
- `ingested_at` — ingestion timestamp where known.
- `sample_type` — `production_aligned` or `robustness_4h`.
- `session_type` — `us_pre_open` or `us_post_close` for production-aligned observations; null for non-session robustness rows.
- `session_anchor` — canonical America/New_York anchor when applicable.
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
- `return_12h`
- `return_24h`
- `return_72h`
- `mae_6h`, `mae_12h`, `mae_24h`, `mae_72h`
- `mfe_6h`, `mfe_12h`, `mfe_24h`, `mfe_72h`
- `realized_vol_6h`, `realized_vol_12h`, `realized_vol_24h`, `realized_vol_72h` where defensible

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
- canonical 4H structure state
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
- global long/short ratio
- taker buy/sell ratio
- OI z-score where reproducible
- crowding data status
- crowding score/status

### Volatility
- ATR percentage fields used by production engine
- ATR percentile
- Bollinger width
- volatility regime
- volatility score/status

### Canonical BTC Core outputs
- `directional_score`
- `directional_bias`
- `direction_strength`
- `confidence`
- `data_quality_status`
- `completeness_pct`
- freshness/data-age fields
- source diagnostic summary
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

- US 2Y level and relevant change
- US 10Y level and relevant change
- 10Y real yield level and relevant change
- breakeven inflation level and relevant change
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

Daily Treasury data must only enter an intraday row once its historical availability is defensible. Unknown publication time must not be back-projected.

Experimental sources such as Yahoo-derived MOVE/DXY/Nasdaq must be labeled experimental and tested separately from production-candidate Treasury evidence.

## Quality and provenance

Every observation should retain, where available:

- provider/source
- event time
- knowledge time
- ingestion time
- freshness/age
- source maturity
- fallback status
- normalized error/status
- engine/classifier/schema/rule versions
- missing required fields
- missing optional fields

Missing data must remain missing. Do not silently substitute zeros or reconstruct unavailable intraday observations without an explicit, documented research rule.

## Analysis metadata

Every conditional result table must include:

- sample type
- condition definition
- `n`
- horizon
- median/mean return where reported
- positive-return rate
- MAE summary
- MFE summary
- relevant quantiles
- missingness/exclusion count

No conditional conclusion may be presented without its sample count.

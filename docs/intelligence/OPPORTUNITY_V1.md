# Bitmomo Opportunity V1

Status: METHODOLOGY FROZEN FOR IMPLEMENTATION / NOT YET PRODUCTION

Version: `opportunity-v1`

Tracks: Issue #80

## Purpose

Opportunity answers one narrow question:

> Is BTC currently in a short-horizon activity environment where a materially tradable move is more likely than usual?

Opportunity does **not** predict direction. It must never be presented as a bullish/bearish probability or as a substitute for Directional Bias.

## Research basis

The first intraday P0.2B replay contains 2,880 15-minute point-in-time observations covering approximately 30 days.

The strongest simple and stable fast discriminator was the realized BTC price range over the trailing 60 minutes.

Validation target used for V1 selection:

- whether BTC subsequently reached an absolute favorable/adverse excursion of at least 0.30% within +1h / +2h / +4h
- chronological 70/30 calibration/holdout split
- no random split

Direct trailing-60m range discrimination remained strong across the chronological split. In the holdout, AUC was approximately:

- +1h: 0.785
- +2h: 0.867
- +4h: 0.884

Alternative recent-range windows such as 15m and 30m were also informative, but trailing 60m range showed the strongest longer intraday holdout discrimination while remaining simple and auditable.

A subsequent P0.2C replay extended the check across Binance BTCUSDT 5-minute history from 2020-01-01 through 2026-09-10. The absolute event rate changed substantially across years and market environments, but `HIGH > NORMAL > LOW` remained ordered in every calendar year and in all nine sufficiently populated trend-context × volatility-context cells at +15m, +30m, +1h, and +2h. This strengthens the V1 interpretation as a relative activity state rather than a fixed probability forecast.

## Canonical input

Use **Binance BTCUSDT USD-M perpetual** market candles at 5-minute cadence for `opportunity-v1`.

The P0.2C source-sensitivity replay found approximately 96.2% HIGH/NORMAL/LOW state agreement between Spot and USD-M when both sources had a strict valid reference window. USD-M is canonical because the tested 2020–2026 source history is complete while the Spot archive contains historical gaps that materially expand fail-closed periods under strict V1 integrity rules.

Spot may be used for research sensitivity and diagnostics, but it is **not** a silent runtime fallback for the same `opportunity-v1` methodology version. If the canonical USD-M source is unavailable or fails integrity/freshness checks, Opportunity must fail closed.

At each eligible 15-minute evaluation cutoff `T`:

1. use only fully closed 5-minute candles with candle `close_time <= T`;
2. take the trailing 12 completed 5-minute candles;
3. compute:

`range_60m_pct = (max(high) - min(low)) / min(low) * 100`

The current/open 5-minute candle is never used.

## Adaptive baseline

Opportunity is relative to recent BTC activity rather than a fixed absolute volatility threshold.

For every 15-minute cutoff, compare the current `range_60m_pct` with the immediately preceding **14 calendar days** of the same metric sampled at the same 15-minute evaluation cadence.

The current observation is excluded from the reference window.

Compute the empirical percentile rank:

`activity_percentile = percentile_rank(current range_60m_pct within prior 14-day range_60m_pct observations)`

The 14-day baseline was selected as the V1 default because it produced strong separation while remaining notably stable between calibration and chronological holdout. For the +1h 0.30% excursion target, percentile discrimination was approximately 0.789 AUC in both calibration and holdout.

Shorter windows were informative as well; V1 intentionally chooses a conservative adaptive baseline rather than optimizing to the single best holdout statistic.

## Customer state mapping

Exactly three Opportunity states exist in V1:

- `HIGH` when `activity_percentile >= 75`
- `LOW` when `activity_percentile <= 25`
- `NORMAL` otherwise

These are relative activity states, not price-direction labels.

## Observed separation

Using the 14-day adaptive percentile and the +1h >=0.30% excursion validation target:

Calibration sample event rates were approximately:

- HIGH: 73.7%
- NORMAL: 33.4%
- LOW: 13.4%

Chronological holdout event rates were approximately:

- HIGH: 89.6%
- NORMAL: 69.8%
- LOW: 24.6%

The absolute event rate changed materially between periods, which is precisely why Opportunity is defined as a relative activity state rather than a fixed probability forecast.

The ordering remained intact and materially separated.

The multi-year P0.2C replay confirms the same ranking is stable across years and coarse market contexts; it does **not** convert these state labels into fixed event probabilities.

## Evaluation cadence

Canonical V1 evaluation cadence: every 15 minutes.

A recomputation does not automatically create a customer alert or publication event.

The engine should persist every valid evaluation for forward validation. Customer-facing `Changed` events, alert dedupe, persistence confirmation, and cooldown rules are delivery semantics and do not modify the underlying Opportunity state definition.

## Warm-up / availability

Opportunity requires a defensible 14-day reference baseline.

A fresh installation may bootstrap the required closed 5-minute history from the canonical USD-M market-data source. Until the baseline is sufficiently available and source integrity checks pass, Opportunity must return `unavailable` rather than fabricate `NORMAL`.

Engineering must preserve missingness and source diagnostics. It must not silently fill missing historical observations with neutral values or switch market source under the same methodology version.

## Output contract

A canonical Opportunity record should include at minimum:

- `methodology_version = opportunity-v1`
- `evaluated_at`
- `knowledge_time`
- `state`: HIGH / NORMAL / LOW / unavailable
- `range_60m_pct`
- `activity_percentile`
- `reference_window_days = 14`
- `reference_observation_count`
- source/freshness diagnostics
- previous accepted state where available
- whether the raw state changed from the previous valid evaluation

Customer copy must not expose unnecessary raw numerical precision unless useful for explanation.

## Product semantics

Allowed interpretation:

- HIGH: BTC is in a relatively active short-horizon environment; a meaningful move is more likely than during recent lower-activity states.
- NORMAL: activity is around the middle of its recent distribution.
- LOW: BTC is in a relatively quiet short-horizon environment; meaningful movement is less likely than during recent higher-activity states.

Not allowed:

- HIGH = bullish
- HIGH = buy
- LOW = bearish
- Opportunity 80th percentile = 80% probability of a winning trade
- Confidence = probability Opportunity is correct

## Relationship to other Bitmomo layers

Opportunity is separate from:

- Directional Bias
- BTC Core
- Market Regime / Market State
- Bond / macro context
- future Directional Stance

A product surface may display these together, but no V1 formula converts Opportunity into Risk-On/Risk-Off.

## Forward validation

Every valid V1 evaluation must be stored append-only and later labeled with short-horizon outcomes, including at least:

- +15m / +30m / +1h / +2h / +4h returns where available
- +1h / +2h / +4h MAE and MFE where available
- whether 0.20% / 0.30% excursion thresholds were reached
- time to excursion where defensible

Methodology changes require a new version. Historical `opportunity-v1` records are immutable.

## Release rule

This document freezes research semantics for implementation, not production deployment.

Production remains unchanged until deterministic implementation, fixtures, persistence/state-change behavior, staging acceptance, mobile/desktop acceptance, and release authorization pass.

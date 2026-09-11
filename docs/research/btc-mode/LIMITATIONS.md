# BTC Mode Research — Limitations

Status: ACTIVE

These limitations must accompany any interpretation of P0.2B results.

## Data window and dependence

- Initial V1 calibration uses one 30-day market window. It contains many intraday rows but far fewer independent market regimes/days.
- Hourly and 15-minute forward outcomes overlap. Raw row count must never be presented as independent-sample count.
- Chronological evaluation, embargo/purging, day/event-level checks, and append-only forward validation are required.
- The observed window contains Accumulation, Expansion, Transition, and Distribution but no Capitulation candidate. Risk-Off behavior cannot be considered fully calibrated from this window alone.

## Historical replay parity

The corrected replay uses repository Signal Engine and Regime rules, but historical source reconstruction is not perfectly identical to live production:

- historical basis uses hourly mark/index closes rather than an instantaneous historical premium-index observation
- the portable archive supplies roughly April-August daily warm-up, shorter than the production code's maximum 252-day percentile lookback for some volatility/volume percentile calculations
- derivatives knowledge time is reconstructed under a conservative availability assumption; alternate zero-delay sensitivity changes some numeric scores but did not change the main research conclusions
- historical liquidation pressure is unavailable
- Market Regime receives `crowding_score = null`, matching the current production runtime adapter

Results that depend strongly on these fields require additional sensitivity or forward validation.

## Direction versus movement

The corrected study finds materially stronger evidence for forecasting near-term movement magnitude than for forecasting direction.

Do not convert movement probability into an implied directional signal. A high probability of a >=0.3% move can still be compatible with either upside or downside.

## Confidence semantics

Existing Bitmomo Confidence measures agreement/strength of current evidence. It is not calibrated as win probability or expected return probability.

Observed multi-timeframe agreement can occur late in an intraday move. Customer-facing language must not imply that `70% confidence` means a 70% chance a bullish/bearish view will be profitable.

## Tactical layer

The exploratory 15-minute layer demonstrates potential for Opportunity/Activity detection from existing 5-minute public data. It does not yet prove a production recomputation cadence or a standalone Directional Stance engine.

Simple and shallow nonlinear directional models remained near chance on the later chronological period. Additional complexity must not be added merely to improve in-sample fit.

## Regime/state imbalance

Some states are concentrated in different parts of the 30-day window. Accumulation dominates earlier observations; Distribution and much of Transition appear later; Capitulation is absent. Apparent conditional effects can therefore be confounded with time/regime shifts.

No conditional rule should be treated as universally validated solely because it performs in one segment of this window.

## Bond / macro

Bond incremental value has not yet been evaluated under the corrected intraday target. It should be tested by horizon. Lack of usefulness at 15m-1h would not imply lack of usefulness at 4h-12h or for session narrative/context.

Historical Treasury knowledge-time precision remains a constraint: daily observations without defensible intraday availability cannot be back-projected into earlier timestamps.

MOVE, DXY, and Nasdaq inputs remain experimental and must not silently become mandatory V1 dependencies.

## Forward validation

Any accepted V1 rule remains launch-calibrated rather than universally validated. Material state outputs and outcomes must be stored append-only after launch. Forward evidence should supersede repeated retrospective retuning.

Future methodology changes require an explicit new Mode version rather than silently rewriting historical V1 behavior.

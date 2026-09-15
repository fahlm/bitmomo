# Hyperliquid Direction Alpha Audit — Predeclared Plan

Date: 2026-09-16
Status: PREDECLARED / SEEN-DATA AUDIT ONLY
Branch: `research/hyperliquid-referral-volume-v0`

## Objective

Determine whether the current 3/3 microstructure alignment contains standalone short-horizon directional information, independently of passive JOIN fill mechanics.

The audit must distinguish:

1. signal quality;
2. maker-fill selection/adverse selection;
3. execution/fee drag.

No threshold tuning is allowed after results are observed.

## Canonical signal

Use the current standardized direction rule exactly as implemented by `ShadowProbeEngine._aligned_direction()`:

LONG when all are true:
- book imbalance >= +0.05;
- microprice edge > 0;
- aggressor flow >= +0.05.

SHORT when all are true:
- book imbalance <= -0.05;
- microprice edge < 0;
- aggressor flow <= -0.05.

Else direction = NONE.

Do not change these thresholds for the primary audit.

## Primary datasets

Use only already-SEEN research data:

- Development Capture 2: BTC, ETHFI, PONS, PUMP, VVV, ~6h each;
- VVV 8h raw L2/trades replay when available.

Session 4 remains untouched/unseen.

## Causal event construction

At each eligible book event t:

1. Compute the signal using only information timestamped <= t.
2. Record current midprice m(t).
3. Measure future midprice using the first valid book at or after each horizon:
   - +1s
   - +2s
   - +5s
   - +10s
   - +30s
   - +60s
4. Never use future data to decide whether an event is a signal.
5. Suppress duplicate signal observations within the same unchanged directional episode to avoid oversampling one persistent state. Canonical episode rule: one event at episode start, then at most one new event after direction first leaves and later re-enters the same 3/3 state.

## Primary outcome

For direction d in {+1,-1}, define sign-adjusted future return:

`R_h = d * (mid(t+h) - mid(t)) / mid(t) * 10,000`

Positive R_h means price moved in the predicted direction.

For each coin and pooled cross-market sample report at every horizon:

- N signals;
- mean R_h;
- median R_h;
- hit rate P(R_h > 0);
- P10 / P25 / P75 / P90;
- standard deviation;
- standard error / bootstrap confidence interval;
- MFE and MAE over the path to the horizon when computable.

Report LONG and SHORT separately as well as combined sign-adjusted results.

## Baselines

Primary comparisons, with no parameter search:

1. 3/3 alignment versus unconditional matched-time baseline.
2. 3/3 alignment versus 2/3 alignment episodes.
3. 3/3 alignment versus direction-shuffled labels within coin/day blocks.

Matched-time baseline should sample timestamps from the same coin and broad time block to avoid comparing different volatility/session regimes.

## Fill-selection audit

For every 3/3 signal episode, replay the canonical standardized JOIN probe without changing its fill semantics and classify the event as:

- MAKER_FILLED;
- UNFILLED_WITHIN_ENTRY_LIFETIME.

Compare future sign-adjusted returns between these subsets using the same horizons.

Primary adverse-selection diagnostic:

`selection_penalty_h = mean(R_h | maker-filled) - mean(R_h | all 3/3 signals)`

Strong evidence of toxic maker selection requires:

- all-signal directional return is positive or materially better than baseline;
- maker-filled subset is materially worse than all-signal subset;
- effect is visible in multiple markets and horizons, not one isolated coin.

## Decision tree

### A — Signal informative, maker fills toxic

If 3/3 beats baselines but maker-filled subset deteriorates materially, continue execution/lifecycle research.

### B — Signal not informative

If 3/3 does not beat matched baselines robustly, freeze the current 3/3 rule as not validated alpha and redesign the Direction Engine before more JOIN tuning.

### C — Regime/market conditional

If edge is concentrated in identifiable markets/regimes, create a new predeclared conditional-alpha development cycle. Do not retroactively cherry-pick conditions from this audit as production rules.

### D — Signal and fills both informative but net economics poor

Focus on fees, exit mechanics, turnover and maker/taker mix.

## Robustness standard

Do not call 3/3 validated directional alpha merely because pooled mean is positive.

Evidence should include:

- positive median or credible positive mean at a useful horizon;
- materially positive hit-rate lift versus matched baseline;
- effect present in more than one market;
- no single market dominates pooled result;
- effect does not reverse catastrophically on VVV 8h versus Dev2;
- sufficient sample size for stable confidence intervals.

## Prohibited practices

- no threshold search in the primary audit;
- no choosing the best horizon after the fact and ignoring the rest;
- no Session 4 use;
- no live orders;
- no lookahead in signal construction;
- no treating maker-filled observations as the full signal population.

## Deliverable

Freeze a result document with one of four conclusions:

- `SIGNAL_SUPPORTED / EXECUTION_TOXIC`
- `SIGNAL_NOT_SUPPORTED`
- `SIGNAL_CONDITIONAL / NEW PREDECLARED CYCLE REQUIRED`
- `SIGNAL_SUPPORTED / EXECUTION_NOT_PRIMARY_CAUSE`

Only after this result should Order Lifecycle Dev3 be resumed or replaced.

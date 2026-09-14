# Hyperliquid Entry Policy Development Plan

Date: 2026-09-14

Status: PREDECLARED DEVELOPMENT PLAN — SEEN DATA ONLY

Session 3 is frozen as `NO QUALIFIED MARKET / IDLE` and is now SEEN. The next research iteration targets maker-entry adverse selection. This document freezes the development search space before replay results are inspected.

## Objective

Reduce toxic passive fills while retaining enough fill rate and volume throughput to make a $10K genuine-volume target economically plausible with approximately $100 notional.

The primary diagnostic target is **post-fill markout**, not raw fill rate. A lower fill rate is acceptable if maker ratio, markout, and fee-adjusted P10K improve enough to compensate.

## Fixed components during entry-policy development

The following are not to be tuned in this development cycle:

- selector qualification thresholds;
- fee assumptions already used by the shadow probe;
- $100 target notional;
- queue-ahead fill accounting semantics;
- exit policy / exit lifetime used by the standardized probe;
- no cancellation credit;
- no wallet or order submission;
- Session 4 remains untouched/unseen until one candidate is frozen.

## Development datasets

Permitted SEEN datasets include previously evaluated/revealed captures such as:

- VVV 8h holdout books + trades (now SEEN);
- previous multi-market 30m books + trades;
- previous microstructure diagnostic captures;
- frozen Session 3 results for diagnosis only.

Raw replay data should preserve event ordering using exchange timestamps where available, with receive time used only as an operational diagnostic.

## Predeclared entry-policy families

### C0 — Immediate 3/3 JOIN control

Existing behavior. Enter JOIN immediately when book imbalance, microprice edge, and aggressor flow align in one direction under the current standardized probe semantics.

Purpose: exact control for all comparisons.

### P1 — Persistence confirmation

Require the same 3/3 directional alignment to remain valid before placing the JOIN quote.

Predeclared development grid:

- confirmation duration: 0.5s, 1.0s, 2.0s;
- direction must not flip during confirmation;
- quote uses the current BBO only after confirmation;
- no look-ahead and no reuse of pre-confirmation queue state.

Hypothesis: transient 3/3 states contain more toxic fills than persistent alignment.

### P2 — Normalized microprice-strength filter

Require microprice edge magnitude to be materially large relative to the current spread, rather than merely having the correct sign.

Define:

`micro_strength = abs(microprice_edge_bps) / spread_bps`

Predeclared development grid:

- minimum micro_strength: 0.10, 0.20, 0.30.

The original 3/3 sign conditions remain required.

Hypothesis: weak microprice sign alignment is insufficient compensation for adverse-selection risk.

### P3 — Aggressor-flow strength filter

Require stronger directional aggressor flow than the current minimum sign/alignment requirement.

Predeclared development grid for absolute directional flow:

- 0.10, 0.20, 0.30.

Book imbalance and microprice direction must still align.

Hypothesis: entries backed by stronger recent executed flow have better post-fill continuation than marginal 3/3 signals.

### P4 — Same-side queue accessibility guard

Require the displayed queue ahead at the intended JOIN side to remain bounded relative to the $100 hypothetical order.

Predeclared development grid:

- queue-ahead multiple <= 2x, 5x, 10x.

This is an entry-level filter only; it does not change the selector's existing market-level queue gate.

Hypothesis: very large queue-ahead states either do not fill or fill mainly when informed pressure is strong enough to make the fill toxic.

## Combination rule

Do not brute-force arbitrary combinations. Development proceeds in two stages:

1. evaluate C0 and each family independently;
2. only if at least two independent families show consistent improvement in markout/P10K across SEEN datasets, allow one predeclared two-filter combination using the individually best setting from each family.

No three-way or larger combination is permitted in this cycle.

## Evaluation metrics

For every policy/market/dataset record at least:

- attempts;
- filled entries;
- completed round trips;
- fill rate;
- maker ratio;
- mean and median 5s markout;
- P10K;
- volume/hour and T10K;
- max drawdown;
- entry queue multiple distribution;
- result split by direction where sample permits.

The comparison must also report delta versus C0 on the exact same replay interval.

## Candidate selection rule

A development candidate is eligible to be frozen for Session 4 only if it shows a material reduction in adverse selection rather than merely suppressing nearly all fills.

Preferred ordering:

1. P10K improvement versus C0;
2. 5s markout improvement versus C0;
3. maker ratio >= frozen selector gate where sample permits;
4. T10K remains plausibly <= 6h;
5. sufficient sample size and no result driven by one or two fills;
6. improvement is not isolated to one tiny replay slice.

If no policy is convincing, record the development cycle as failed and collect a new SEEN development capture rather than weakening Session 4 gates.

## Session 4 boundary

Session 4 must not begin until:

- replay implementation is deterministic and tested;
- one policy configuration is selected and documented;
- that configuration is frozen before new unseen data collection begins.

Once Session 4 begins, no entry-policy threshold may be tuned until its verdict is frozen.

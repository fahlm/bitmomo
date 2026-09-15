# Hyperliquid Order Lifecycle Development Cycle 3 — Predeclared Plan

Date: 2026-09-15

Status: **PREDECLARED — SEEN DEVELOPMENT ONLY**

Session 4 remains untouched/unseen.

## Motivation

Development Cycles 1 and 2 show that static entry filters alone do not solve adverse selection robustly. Queue accessibility changes fill probability materially but does not generalize economically across BTC, ETHFI, PONS, PUMP, and VVV.

Primary hypothesis:

> A passive JOIN quote becomes toxic when it remains live after the original 3/3 microstructure state weakens or flips. Reducing quote exposure time and canceling stale intent may improve post-fill markout more consistently than adding another static entry filter.

## Fixed components

The following stay fixed in this cycle:

- target notional: $100;
- 3/3 directional alignment definition;
- JOIN entry price semantics;
- queue-ahead fill accounting;
- maker/taker fee assumptions;
- exit policy and exit lifetime;
- cooldown;
- selector qualification gates;
- no wallet/order submission;
- no Session 4 data;
- no tuning after development results are inspected beyond the predeclared grid below.

## Control

### L0 — Current lifecycle

- entry lifetime: 10s
- quote remains live until fill or 10s expiry
- no cancel-on-decay

This is the current standardized shadow-probe behavior.

## Predeclared lifecycle variants

### L1 — Shorter genuine entry lifetime

Same initial signal and JOIN quote as control, but unfilled entry is canceled at a shorter fixed lifetime.

Grid:

- L1_2s
- L1_5s

Rationale: if passive fills occurring late in the 10s window are disproportionately toxic, shorter exposure should improve markout at the cost of fill rate/throughput.

### L2 — Cancel on full signal loss/flip

Quote is canceled before fill if the current 3/3 directional alignment is no longer the same direction as at quote creation.

Two predeclared variants:

- L2_STRICT: cancel whenever current aligned direction is not equal to entry direction, including neutral/partial-decay states;
- L2_FLIP: cancel only when a full 3/3 opposite-direction signal appears; neutral/partial-decay states leave the quote active.

No queue credit is granted after cancellation. A later signal starts a new attempt only after the existing cooldown semantics permit it.

### L3 — Short lifetime + strict decay cancellation

Only one predeclared combination family is allowed because the static-filter combination rule failed to activate in earlier cycles.

Variants:

- L3_2s_STRICT
- L3_5s_STRICT

These combine the corresponding L1 fixed lifetime with L2_STRICT cancellation.

No other combination is permitted in this cycle.

## Causal implementation rule

Cancellation checks may use only market state available at or before the current replay event timestamp. No future book/trade information, no retroactive cancellation, and no cancellation after a fill timestamp.

If a trade event consumes enough queue to fill before the next book snapshot that would have shown signal decay, the fill stands. This preserves event-order causality.

## Evaluation datasets

All are SEEN development data:

1. VVV 8h replay dataset;
2. prior multi-market 30m dataset;
3. Development Capture 2: ~6h each of BTC, ETHFI, PONS, PUMP, VVV.

Development Capture 2 is the primary cross-market dataset. Earlier datasets are robustness/context checks.

## Metrics

For each policy and market report:

- attempts;
- canceled-unfilled attempts;
- expired-unfilled attempts;
- fills;
- fill rate;
- maker ratio;
- mean/median 5s markout;
- P10K;
- volume/hour;
- T10K;
- max drawdown;
- fill latency distribution where available;
- delta versus L0 on identical replay interval.

## Candidate eligibility for Session 4 freeze

A lifecycle candidate can be considered only if, on Development Capture 2:

1. mean markout improves versus L0 in at least 4 of 5 markets;
2. P10K improves versus L0 in at least 4 of 5 markets;
3. both improve in at least 3 of 5 markets;
4. cross-market median dMark > 0;
5. cross-market median dP10K > 0;
6. aggregate filled sample is not trivially small;
7. no improvement is produced merely by suppressing almost all fills;
8. economics are materially closer to the intended gates, not just statistically positive deltas from a very poor baseline.

Preferred tie-break order among eligible candidates:

1. better P10K;
2. better mean markout;
3. lower max drawdown;
4. higher maker ratio;
5. faster T10K.

If no candidate meets these criteria, freeze Development Cycle 3 as failed and do not start Session 4.

## Safety boundary

This work is replay/shadow research only. Any later real cancellation policy must reflect genuine trading intent and risk control. No spoofing, layering, fake orders, wash trading, self-trading, or artificial-volume generation is permitted.

# Bitmomo Hyperliquid Controlled Canary V0

Date: 2026-09-16
Status: LAUNCH RUNBOOK — ORDER SUBMISSION STILL DISABLED IN CANONICAL RUNTIME
Branch: `research/hyperliquid-referral-volume-v0`

## Purpose

Ship a complete end-to-end system this week without pretending that directional alpha or execution optimization is final.

The product goal is broad coverage with strong safety invariants:

- dynamic market discovery;
- market screening;
- conservative direction trigger;
- bounded execution lifecycle;
- mission accounting;
- risk guardian;
- reconciliation;
- exactly one execution authority;
- stop / flatten / switch discipline;
- reconnect / restart safety;
- observability;
- research continuing in parallel.

The first live stage is a controlled canary, not an unrestricted production bot.

## Canonical mission accounting

Implemented in `market_selector/mission.py`.

Full beta mission:

- target cumulative genuine volume: $10,000;
- epoch net-PnL floor: -$5;
- mission net-PnL floor: -$10;
- maximum two risk epochs.

Profit is a real buffer. If cumulative realized PnL is +$2, the distance to the -$10 mission floor is $12.

The mission uses finalized realized net PnL after policy fee accounting. It does not add gross losing trades while ignoring winners.

A trade that overshoots the hard loss floor cannot be labelled successful merely because the same trade also crosses the volume target.

## Canary mission

The first live-readiness canary is intentionally smaller:

- target cumulative volume: $1,000;
- epoch net-PnL floor: -$1;
- mission net-PnL floor: -$2;
- maximum two risk epochs.

This stage exists to validate real fill, fee, cancellation, position, reconciliation, restart and accounting behavior with limited exposure.

## Mandatory execution boundary

Implemented safety primitives:

- `market_selector/execution_boundary.py`;
- host-local `flock` single-authority lock;
- deterministic comparison of expected versus exchange positions;
- deterministic comparison of expected versus exchange open-order IDs;
- unexpected/missing order => reconciliation failure;
- position mismatch => reconciliation failure;
- reconciliation failure blocks new entries and requires flattening if inventory exists;
- `market_selector/operator_control.py` — external operator enable/kill primitive that defaults OFF and requires an exact enable token; kill presence always overrides enablement.

The host-local lock is sufficient only for a single-host deployment. A future multi-host active/active design requires a distributed lease before order submission is permitted.

## Risk gate order

Before every NEW entry, all must be green:

1. mission status RUNNING;
2. operator control explicitly enabled and kill switch absent;
3. exclusive authority lock held;
4. public market feed healthy;
5. account/order reconciliation OK;
6. supervisor allows new entries;
7. active market matches the intended order market;
8. no unresolved flatten/drain state;
9. configured risk limits have remaining buffer.

Any failure blocks risk creation. Reduce-only / flatten actions must remain available when risk is being removed.

## Stage 0 — Shadow operational soak

Use the Autonomous Runtime V0 on the actual always-on host.

Minimum launch-week target: 12–24 continuous hours before canary.

Pass conditions:

- process restarts automatically;
- websocket reconnect recovers;
- no stale generation mutates current state;
- no duplicate event inflation;
- universe refresh works without operator coin selection;
- state JSON remains readable and current;
- no unexplained memory/file growth;
- selector can stay IDLE without being forced to trade;
- no fatal traceback;
- CI remains green.

Direction Alpha Audit and execution research continue in parallel and do not alter frozen runtime rules mid-soak.

## Stage 1 — $1,000 mainnet canary

This stage is NOT yet enabled by canonical code because no wallet/order adapter has been approved.

When the adapter exists, launch only with:

- one account;
- one process holding the execution-authority lock;
- approximately $100 target notional per entry;
- canary mission limits above;
- reconciliation before first order and continuously thereafter;
- operator-control enable file managed outside the trading loop;
- external kill file available outside the trading loop;
- no manual second bot against the same account;
- no gate relaxation during the canary.

Immediate HALT / flatten triggers:

- mission hard floor breached;
- reconciliation mismatch;
- feed hard fault;
- unexpected exchange order;
- unexpected position;
- order-state ambiguity after restart;
- execution authority lock lost;
- operator kill switch.

## Canary promotion gate

Do not promote merely because PnL is positive.

All operational conditions must pass:

- actual cumulative volume accounting matches exchange history;
- actual realized PnL/fees reconcile to exchange within a predeclared tolerance;
- every order belongs to the single execution authority;
- cancel acknowledgement/state transitions are deterministic;
- flatten works from a real non-flat position;
- restart while flat returns fail-closed;
- restart/recovery with known state does not create duplicate entry;
- no orphan orders;
- mission loss limits block new entries as designed;
- supervisor stop/switch state never opens risk on two markets simultaneously.

If any safety invariant fails, return to shadow or fix the implementation. Do not compensate by increasing the loss budget.

## Stage 2 — Full beta mission

Only after canary operational promotion:

- cumulative target $10,000;
- epoch floor -$5;
- mission floor -$10;
- profit remains part of cumulative net-PnL buffer;
- cumulative volume does not reset between epochs;
- market selection remains dynamic;
- if no market qualifies, IDLE;
- epoch stop requires flat inventory before a second epoch begins.

## What is deliberately not final

Launch V0 is not a claim that the current 3/3 directional rule is validated alpha.

Still provisional:

- directional alpha quality;
- best order lifetime/cancel-on-decay policy;
- selector economic thresholds;
- challenger/switch calibration;
- statistical tail probability of CostTo10K.

These improve economics after launch but must not bypass safety invariants.

## Current implementation status

Implemented and CI-covered or compile-gated:

- autonomous dynamic-universe shadow scanner;
- selector / ranker / supervisor;
- resilient transport and dedupe;
- mission ledger with profit buffer;
- $1k canary and $10k beta mission configurations;
- epoch and hard mission loss states;
- execution risk guardian;
- deterministic reconciliation contract;
- single-host exclusive execution authority lock;
- default-off operator enable / kill-switch primitive;
- mission replay evaluator for finalized shadow/paper outcomes.

Still required before Stage 1 live order submission:

- reviewed Hyperliquid account/order adapter;
- exchange-native fee/PnL reconciliation wired to the reconciliation contract;
- real order lifecycle implementation and cancel acknowledgements;
- wire `operator_control.py` into that adapter/controller;
- actual host shadow-soak evidence;
- real flatten/restart behavior validated before promotion.

Until those are present, mainnet order submission remains disabled.

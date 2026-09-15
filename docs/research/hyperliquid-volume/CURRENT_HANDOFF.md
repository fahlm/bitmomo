# Bitmomo Hyperliquid Referral-Volume Research — Current Handoff

Last updated: 2026-09-16

Canonical branch: `research/hyperliquid-referral-volume-v0`
Local research project historically used: `~/bitmomo-hl-volume-bot`

## Canonical mission

Build a 24/7 autonomous Hyperliquid market-selection and execution-supervision engine.

The final system must **not** depend on a human choosing coins or repeatedly running per-coin commands. It must continuously:

1. discover the Hyperliquid perpetual universe;
2. cheaply prefilter liquid markets;
3. subscribe to live L2/trade data for the current watch set;
4. screen every watched market using frozen structural + execution-economics rules;
5. rank only markets that actually qualify;
6. keep at most one active trading market;
7. stop new entries when the active market deteriorates;
8. drain/flatten before a switch once real inventory exists;
9. switch only to a stably qualified replacement;
10. remain IDLE when no market is good enough;
11. repeat discovery/screening/ranking indefinitely.

Research scripts/captures are a laboratory for finding a valid execution policy. They are **not** the intended operating model.

## Autonomous Runtime V0 — IMPLEMENTED IN SHADOW MODE

Canonical architecture doc:

`docs/research/hyperliquid-volume/AUTONOMOUS_RUNTIME_V0.md`

Implementation includes:

- `market_selector/universe.py` — dynamic universe discovery, top-volume scan set, retention buffer, pinned active/pending markets;
- `market_selector/autonomous_runtime.py` — one-process 24/7 dynamic scanner/supervisor using a shared public websocket;
- `market_selector/runtime_state.py` — atomic status persistence and fail-closed restart semantics;
- `market_selector/event_integrity.py` — reconnect replay dedupe;
- `market_selector/raw_capture.py` — optional combined multi-market SEEN capture;
- `run_autonomous_shadow.sh` — one-command launcher;
- `ops/bitmomo-hl-shadow.service.example` — systemd example with automatic restart.

Canonical CI runs the complete selector/research control-plane suite. Latest verified run after launch-week safety additions: **58 tests passed**.

There is **no Exchange client, wallet, signing key, or order-submission path** in Autonomous Runtime V0.

## Launch-week strategy — broad coverage, controlled exposure

The project is no longer blocked on finding perfect alpha before completing the rest of the system.

Target: reach roughly 70–80% maturity across every critical layer while keeping safety invariants strict. Direction/execution research continues in parallel, but operational safety is not allowed to be partial.

Canonical runbook:

`docs/research/hyperliquid-volume/CONTROLLED_CANARY_V0.md`

Launch progression:

`12–24h actual-host shadow soak -> $1,000 controlled canary -> $10,000 full beta mission`

The canary stage is still gated because the repository intentionally has no approved live Hyperliquid order adapter yet.

## Mission accounting / risk guardian — IMPLEMENTED

`market_selector/mission.py`

Full beta mission:

- cumulative genuine-volume target: $10,000;
- epoch net-PnL floor: -$5;
- cumulative mission net-PnL floor: -$10;
- maximum two sequential risk epochs.

Canary mission:

- cumulative target: $1,000;
- epoch floor: -$1;
- cumulative floor: -$2;
- maximum two sequential epochs.

Profit is part of the risk buffer. Example: if realized cumulative PnL is +$2, distance to the -$10 full-mission floor is $12.

Cumulative volume and cumulative PnL do not reset between epochs. Epoch 2 may begin only after an epoch stop and flat inventory.

A completed trade that overshoots the hard loss floor cannot be treated as success merely because the same trade crosses the volume target.

`market_selector/mission_replay.py` can replay finalized shadow/paper execution JSONL through the same mission state machine for canary/full-budget diagnostics.

## Execution boundary — IMPLEMENTED, NO ORDER ADAPTER

`market_selector/execution_boundary.py`

Implemented:

- deterministic expected-vs-exchange position reconciliation contract;
- deterministic expected-vs-exchange open-order reconciliation contract;
- unexpected/missing order => reconciliation failure;
- position mismatch => reconciliation failure;
- host-local exclusive execution-authority lock using `flock`;
- authority lock automatically releases on process death;
- risk guardian blocks new entries on mission stop/halt, feed fault, reconciliation failure, disabled authority, or supervisor denial;
- flatten/reduce exposure remains the required action when risk must be removed.

The lock is single-host only. Multi-host active/active execution is prohibited until a distributed lease exists.

## Existing selector architecture

Deterministic stack:

`Dynamic Universe -> Market Observers -> Eligibility Engine -> Ranker -> MarketSupervisor`

The MarketSupervisor implements:

- no qualified market => IDLE;
- qualification hysteresis before activation;
- active-market deterioration => stop new entry;
- inventory-not-flat => drain before switch;
- persistent superior challenger required for discretionary rotation;
- hard fault => HALT/fail-closed.

In shadow runtime `inventory_flat=True` because the process owns no wallet. A future execution adapter must supply reconciled real inventory state.

## Frozen research gates

Current qualification rules remain research parameters, not production truth:

- 24h volume >= $10M
- spread 1–12 bp
- BBO queue / $100 <= 20x
- trade rate >= 3/min
- execution samples >= 30
- fill >= 5%
- maker >= 75%
- 5s markout >= 0 bp
- T10K <= 6h
- P10K >= -$2

These parameters still require calibration/sensitivity/walk-forward validation before production freeze. Do not silently loosen them inside the existing research selector merely to force trading.

## Session 3 — FROZEN

Canonical verdict: `SESSION3_VERDICT.md`

Result: **NO QUALIFIED MARKET / IDLE**.

Key execution-economics evidence under standardized JOIN + 3/3:

- PONS: maker 65%, markout -9.38 bp, P10K -$10.09;
- VVV: maker 70%, markout -9.54 bp, P10K -$8.19.

This is strong evidence that passive filled observations are toxic, but it does **not** by itself prove that the underlying 3/3 directional signal has standalone alpha.

## Entry Policy Development Cycles 1 and 2 — FROZEN

`ENTRY_POLICY_DEV1_RESULT.md`

Result: **NO SESSION-4 CANDIDATE**. P1 1.0s persistence materially improved VVV 8h but did not generalize consistently.

`ENTRY_POLICY_DEV2_RESULT.md`

Development Capture 2: ~6.02h each across BTC, ETHFI, PONS, PUMP, VVV.

Result: **NO SESSION-4 CANDIDATE**. P4 queue filtering improved PUMP materially and PONS modestly, but worsened BTC/VVV and did not produce a robust positive cross-market effect.

## Direction Alpha Audit — PREDECLARED / PARALLEL RESEARCH

Canonical plan:

`docs/research/hyperliquid-volume/DIRECTION_ALPHA_AUDIT_PLAN.md`

Primary question:

> Does current 3/3 microstructure alignment predict future midprice direction across all signals, or are negative outcomes mainly caused by passive JOIN fills selecting a toxic subset?

The audit keeps current thresholds frozen and measures sign-adjusted future returns at +1s/+2s/+5s/+10s/+30s/+60s for all signals versus maker-filled/unfilled/baselines.

Possible frozen conclusions:

- `SIGNAL_SUPPORTED / EXECUTION_TOXIC`
- `SIGNAL_NOT_SUPPORTED`
- `SIGNAL_CONDITIONAL / NEW PREDECLARED CYCLE REQUIRED`
- `SIGNAL_SUPPORTED / EXECUTION_NOT_PRIMARY_CAUSE`

Order Lifecycle Dev3 remains paused until this causal diagnosis is available, but launch-week control-plane/reliability work proceeds in parallel.

Session 4 remains untouched/unseen.

## Volume-budget objective

Canonical objective:

`docs/research/hyperliquid-volume/VOLUME_BUDGET_OBJECTIVE_V1.md`

Business target is cumulative **$10,000 genuine Hyperliquid volume**, not necessarily positive trading PnL.

Two explicit cost tiers remain useful for research:

- Tier A: reach $10K within $5 net cost;
- Tier B: reach $10K within $10 net cost.

The user's previous "run it 2x" concept is implemented as two **sequential risk epochs under one execution authority**, never two simultaneous bots.

## Launch-week remaining blockers

Before any real order can be submitted, all of the following still need implementation/verification:

1. reviewed Hyperliquid account/order adapter with no secret committed to GitHub;
2. exchange-native position/open-order/fee/PnL reads wired to reconciliation;
3. bounded real order lifecycle with deterministic submit/cancel/fill state transitions;
4. external/operator kill switch defaulting OFF;
5. actual-host 12–24h shadow soak evidence;
6. real flatten path validated under canary exposure;
7. restart recovery must not duplicate an entry or orphan an order;
8. canary promotion evidence from the $1K stage.

These are execution/operations blockers, not reasons to pause Direction Alpha research.

## Current next action

Run two tracks in parallel:

**Launch track:** deploy current shadow runtime to the actual always-on host, complete 12–24h soak, and implement/review the thin Hyperliquid account/order adapter behind the already-tested mission + reconciliation + single-authority boundary. Do not enable it by default.

**Research track:** execute the predeclared Direction Alpha + Fill Selection Audit on existing SEEN raw data, then use the diagnosis to improve direction/execution economics without rebuilding the operational foundation.

After the shadow soak and adapter/reconciliation path are green, the next exposure step is the `$1,000 / -$1 epoch / -$2 mission` controlled canary from `CONTROLLED_CANARY_V0.md`, not an immediate $10K run.

## Mainnet status

**ORDER SUBMISSION NOT APPROVED / STILL DISABLED.**

The repository now has the accounting and safety boundary needed to make a controlled canary possible, but it deliberately does not contain an enabled mainnet order path.

## New-chat bootstrap

Read this file first.

Correct state: Autonomous Runtime V0 exists and CI is green at 58 tests; mission ledger/profit buffer/two-epoch limits, risk guardian, reconciliation contract, single-host execution-authority lock, mission replay evaluator, and controlled canary runbook are implemented. Mainnet order submission remains disabled pending the actual-host shadow soak and a reviewed exchange adapter. Direction Alpha Audit continues in parallel; Session 4 has not started.

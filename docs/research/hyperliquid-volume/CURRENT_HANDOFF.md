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

Canonical CI now runs the complete selector research suite. Latest verified run after Autonomous Runtime V0 additions: **45 tests passed**.

There is **no Exchange client, wallet, signing key, or order-submission path** in Autonomous Runtime V0.

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

In shadow runtime `inventory_flat=True` because the process owns no wallet. A future execution authority must supply real reconciled inventory state.

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

These parameters still require calibration/sensitivity/walk-forward validation before production freeze.

## Session 3 — FROZEN

Canonical verdict: `SESSION3_VERDICT.md`

Result: **NO QUALIFIED MARKET / IDLE**.

Key execution-economics evidence under standardized JOIN + 3/3:

- PONS: maker 65%, markout -9.38 bp, P10K -$10.09;
- VVV: maker 70%, markout -9.54 bp, P10K -$8.19.

This is strong evidence that passive filled observations are toxic, but it does **not** by itself prove that the underlying 3/3 directional signal has standalone alpha.

## Entry Policy Development Cycle 1 — FROZEN

`ENTRY_POLICY_DEV1_RESULT.md`

Result: **NO SESSION-4 CANDIDATE**.

P1 1.0s persistence materially improved VVV 8h but did not generalize consistently in the short multi-market check.

## Development Capture 2 / Entry Policy Cycle 2 — FROZEN

Development Capture 2: ~6.02h each across BTC, ETHFI, PONS, PUMP, VVV.

`ENTRY_POLICY_DEV2_RESULT.md`

Result: **NO SESSION-4 CANDIDATE**.

P4 queue filtering improved PUMP materially and PONS modestly, but worsened BTC/VVV and did not produce a robust positive cross-market effect.

## Research priority change — DIRECTION ALPHA AUDIT FIRST

Before further lifecycle tuning, separate signal quality from fill/execution quality.

Canonical predeclared plan:

`docs/research/hyperliquid-volume/DIRECTION_ALPHA_AUDIT_PLAN.md`

Primary question:

> Does the current 3/3 microstructure alignment predict future midprice direction on all signal events, or do negative outcomes arise mainly because passive JOIN fills select a toxic subset?

The audit freezes the existing 3/3 thresholds and measures sign-adjusted future returns at +1s/+2s/+5s/+10s/+30s/+60s for:

- all 3/3 signal episodes;
- maker-filled subset;
- unfilled subset;
- matched baselines / 2-of-3 / shuffled-direction controls.

Outcomes must lead to one frozen conclusion:

- `SIGNAL_SUPPORTED / EXECUTION_TOXIC`
- `SIGNAL_NOT_SUPPORTED`
- `SIGNAL_CONDITIONAL / NEW PREDECLARED CYCLE REQUIRED`
- `SIGNAL_SUPPORTED / EXECUTION_NOT_PRIMARY_CAUSE`

**Order Lifecycle Dev3 is paused until this audit is complete.** Its existing predeclared plan remains valid but must not be interpreted as the current top priority.

Session 4 remains untouched/unseen.

## Volume-budget objective — $5 primary, $10 fallback

Canonical objective:

`docs/research/hyperliquid-volume/VOLUME_BUDGET_OBJECTIVE_V1.md`

The business target is cumulative **$10,000 genuine Hyperliquid volume**, not necessarily positive trading PnL.

Evaluate two explicit cost tiers:

- Tier A: reach $10K cumulative volume within $5 net loss;
- Tier B fallback: reach $10K cumulative volume within $10 net loss.

The user's 'run it 2x' concept is represented as **two sequential risk epochs under one execution authority**, not two simultaneous bots:

- Epoch 1: stop new entries at -$5 if target incomplete;
- flatten/reassess through normal selector rules;
- Epoch 2: may resume only in a qualified market;
- cumulative volume carries forward;
- cumulative -$10 => hard HALT.

Running two independent order-authority bots is rejected because it can create duplicated exposure, conflicting state, queue cannibalization, and accidental self-interaction.

Tier B is not validated merely because a point-estimate P10K is above -$10. Required research metrics include:

- distribution of CostTo10K;
- P(reach 10K before -$5);
- P(reach 10K before -$10);
- P50/P75/P90/P95 CostTo10K;
- CVaR95;
- maximum drawdown before target;
- TimeTo10K;
- volume accumulated before each risk boundary;
- number of risk epochs required.

Predeclared Tier-B research target before future unseen validation:

- expected CostTo10K <= $7.50;
- P90 CostTo10K <= $10;
- >=90% probability of reaching $10K before cumulative -$10;
- each epoch individually capped at -$5;
- cumulative -$10 hard HALT.

## Strategic separation: research vs runtime

### Research layer

- uses SEEN captures/replays;
- audits direction alpha independently from execution;
- investigates execution only after signal diagnosis;
- evaluates volume-budget distributions and tail risk;
- freezes parameters before unseen validation;
- never tunes runtime rules automatically from recent losses.

### Runtime layer

- operates continuously;
- dynamically decides which market qualifies under frozen rules;
- automatically stops/switches/IDLEs as conditions change;
- never silently changes what the rules are.

## Current next action

1. Run the predeclared Direction Alpha Audit on existing SEEN raw datasets.
2. Run the Fill Selection Audit on the same signal episodes.
3. Freeze the causal diagnosis before resuming/replacing Order Lifecycle Dev3.
4. Evaluate CostTo10K distribution under $5 and $10 budgets; do not rely only on mean P10K.
5. Continue autonomous-runtime reliability soak separately; shadow only.
6. Keep Session 4 untouched until one full policy/configuration and its budget objective are frozen.

## Mainnet status

**NOT APPROVED.**

Before any wallet/order integration:

1. Direction Alpha Audit result frozen;
2. one execution policy frozen;
3. selector parameters calibrated/frozen;
4. volume-budget objective frozen;
5. fresh unseen Session 4 passes economics + tail-risk gates;
6. real account/position reconciliation exists;
7. risk guardian implements per-epoch and cumulative hard loss budgets;
8. exactly one execution authority exists;
9. stop/drain/flatten works on real inventory;
10. kill switch exists;
11. 24/7 shadow/paper soak passes operational reliability gates.

## New-chat bootstrap

Read this file first.

Correct state: Autonomous Runtime V0 exists in shadow mode; Session 3, Dev1 and Dev2 are frozen failures/no-candidate results; Order Lifecycle Dev3 is paused; current top research priority is the predeclared standalone Direction Alpha + Fill Selection Audit, followed by CostTo10K risk-distribution analysis under the $5 primary and $10 two-epoch fallback budgets. Session 4 has not started.

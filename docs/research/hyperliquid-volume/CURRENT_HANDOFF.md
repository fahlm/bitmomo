# Bitmomo Hyperliquid Referral-Volume Research — Current Handoff

Last updated: 2026-09-15

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

New implementation:

- `market_selector/universe.py` — dynamic universe discovery, top-volume scan set, retention buffer, pinned active/pending markets;
- `market_selector/autonomous_runtime.py` — one-process 24/7 dynamic scanner/supervisor using a shared public websocket;
- `market_selector/runtime_state.py` — atomic status persistence and fail-closed restart semantics;
- `run_autonomous_shadow.sh` — one-command launcher;
- `ops/bitmomo-hl-shadow.service.example` — systemd example with automatic restart;
- `tests/test_universe.py`;
- `tests/test_runtime_state.py`.

CI workflow `hyperliquid-research-selector.yml` now runs the complete `research/hyperliquid-volume/tests` suite rather than a partial test list.

### Runtime behavior

Default autonomous shadow runtime:

- dynamic scan set: top 24 markets by 24h notional volume;
- cheap minimum-volume gate: current selector threshold ($10M/day);
- retention buffer: 8 volume ranks to reduce subscription churn;
- universe rediscovery: every 300 seconds;
- one shared Hyperliquid public websocket;
- per-market MarketObserver pool;
- standardized shadow probe on all watched markets to accumulate execution evidence;
- fail-closed eligibility + ranker + MarketSupervisor;
- automatic reconnect on whole-feed stale condition;
- clean websocket generation replacement when the universe changes;
- append-only shadow execution JSONL;
- atomic runtime status JSON.

There is **no Exchange client, wallet, signing key, or order-submission path** in Autonomous Runtime V0.

### Restart safety

A previous process's active market is persisted for diagnostics but **trading authority is never restored**. After restart:

- active/pending authority is cleared;
- qualification/challenger streaks reset;
- market-state windows warm up from fresh public data;
- execution evidence may be rehydrated only when `policy_id` exactly matches the current standardized probe;
- markets must qualify again through normal hysteresis before becoming active.

This is intentional fail-closed behavior.

## Existing selector architecture

Deterministic stack:

`Dynamic Universe -> Market Observers -> Eligibility Engine -> Ranker -> MarketSupervisor`

The MarketSupervisor already implements the strategic behavior required by the mission:

- no qualified market => IDLE;
- qualification hysteresis before activation;
- active-market deterioration => stop new entry;
- inventory-not-flat => drain before switch;
- persistent superior challenger required for discretionary rotation;
- hard fault => HALT/fail-closed.

In shadow runtime `inventory_flat=True` because the process owns no wallet. A future execution authority must supply real reconciled inventory state.

## Frozen research gates

Current research qualification rules remain:

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

These are research thresholds, not mainnet approval.

## Session 3 — FROZEN

Canonical verdict:

`docs/research/hyperliquid-volume/SESSION3_VERDICT.md`

Result: **NO QUALIFIED MARKET / IDLE**.

Final checkpoint showed severe passive-fill adverse selection under standardized JOIN + 3/3:

- PONS: maker 65%, markout -9.38 bp, P10K -$10.09;
- VVV: maker 70%, markout -9.54 bp, P10K -$8.19;
- ETHFI/PUMP/BTC also failed structural and/or economics gates.

Session 3 is SEEN and may not be reused as an unseen holdout for modified policies.

## Dual-window / throughput fixes — COMPLETE

Market-state metrics use 15m. Execution evidence uses 4h/latest 100 with <=30m freshness.

Restart-safe T10K/volume-per-hour accounting was fixed and locally verified with **34 passing tests** before the autonomous-runtime additions. New universe/runtime-state tests were subsequently added to canonical CI.

## Entry Policy Development Cycle 1 — FROZEN

`docs/research/hyperliquid-volume/ENTRY_POLICY_DEV1_RESULT.md`

Result: **NO SESSION-4 CANDIDATE**.

P1 1.0s persistence materially improved VVV 8h but failed to generalize in the 30m multi-market sanity check. P2/P3 were inconsistent. P4 queue accessibility looked directionally interesting but sample size was insufficient.

## Development Capture 2 / Cycle 2 — FROZEN

A 6h SEEN capture across BTC, ETHFI, PONS, PUMP, VVV completed successfully with resilient reconnect/recovery.

Books were approximately 39.6k–39.8k per market over ~6.02h; individual trades ranged from ~7k to ~91k depending on market.

P4 queue guards were replayed on the full 6h multi-market capture. Result: **NO SESSION-4 CANDIDATE**.

Key pattern:

- PUMP improved materially under P4;
- PONS improved only modestly at 5x;
- BTC worsened;
- VVV worsened;
- ETHFI sample remained small;
- cross-market medians were not convincingly positive.

Therefore static queue filtering is not a robust universal solution.

Canonical result:

`docs/research/hyperliquid-volume/ENTRY_POLICY_DEV2_RESULT.md`

## Development Cycle 3 — execution lifecycle research

Canonical predeclared plan:

`docs/research/hyperliquid-volume/ORDER_LIFECYCLE_DEV3_PLAN.md`

Research hypothesis: standardized JOIN quotes may become toxic because they remain live after the 3/3 state decays or flips.

Predeclared variants:

- L0: 10s current control;
- L1_2s;
- L1_5s;
- L2_STRICT: cancel unfilled quote when original 3/3 direction is no longer valid;
- L2_FLIP: cancel only on full opposite 3/3;
- L3_2s_STRICT;
- L3_5s_STRICT.

Exit policy, fees, notional, queue accounting and selector gates remain fixed.

Dev3 is **SEEN research only**. Session 4 remains untouched.

## Strategic separation: research vs runtime

This distinction is mandatory:

### Research layer

- uses SEEN captures/replays;
- investigates execution mechanics;
- proposes at most one candidate;
- freezes parameters before unseen validation;
- never tunes runtime rules automatically from recent losses.

### Runtime layer

- operates continuously;
- dynamically decides **which market** qualifies under frozen rules;
- automatically stops/switches/IDLEs as conditions change;
- never silently changes **what the rules are**.

The autonomous scanner should continue to mature even while execution-policy research is unresolved.

## Mainnet status

**NOT APPROVED.**

Autonomous Runtime V0 is shadow-only by design.

Before any wallet/order integration:

1. one execution policy must be frozen and pass fresh unseen Session 4;
2. economics/risk gates must pass;
3. real account/position reconciliation must exist;
4. risk guardian must exist;
5. exactly one execution authority must exist;
6. stop/drain/flatten must operate on real inventory;
7. kill switch must exist;
8. 24/7 shadow/paper soak must show no silent divergence;
9. deployment/restart/reconnect behavior must be validated on the actual always-on host.

Do not weaken gates merely to force the engine to trade. IDLE is a valid and desirable state when no market is suitable.

## Next engineering priorities

1. Validate Autonomous Runtime V0 test/compile gate in CI.
2. Run a shadow soak of the autonomous runtime on an always-on host; no manual per-coin processes.
3. Add operational alerting/heartbeat around `autonomous_state.json` (runtime down, HALT, repeated reconnect, ACTIVE/SWITCH events).
4. Continue Dev3 lifecycle replay in the research layer in parallel.
5. If Dev3 produces a robust candidate, freeze exactly one configuration and then run fresh unseen Session 4.
6. Only after Session 4 success design the live execution authority/risk guardian boundary.

## New-chat bootstrap

Read this file first.

Correct starting state is **not** "continue manually running five coin scripts". The canonical mission is now the autonomous 24/7 dynamic-universe scanner/supervisor. Session 3, Dev1 and Dev2 are frozen failures/no-candidate results. Autonomous Runtime V0 exists in shadow mode. Dev3 lifecycle research remains parallel work; Session 4 has not started.

# Bitmomo Hyperliquid Autonomous Runtime V0

Status: IMPLEMENTED IN SHADOW MODE

Branch: `research/hyperliquid-referral-volume-v0`

## Mission

Operate continuously without a human choosing coins manually. The engine must:

1. discover the Hyperliquid perpetual universe;
2. cheaply prefilter liquid markets;
3. maintain live microstructure state for the watched set;
4. accumulate empirical execution evidence with standardized shadow probes;
5. evaluate every watched market fail-closed;
6. rank only truly QUALIFIED markets as activation candidates;
7. keep one active market at most;
8. stop new entries when the active market deteriorates;
9. drain/flatten before switching once live execution exists;
10. switch only after challenger hysteresis is satisfied;
11. remain IDLE when no market is good enough;
12. repeat discovery/screening/ranking indefinitely.

V0 remains strictly shadow-only. There is no wallet, `Exchange` client, signing key, or order submission path.

## Runtime flow

```text
Hyperliquid metadata
        ↓ every 5m
Dynamic Universe Manager
        ↓ top liquid set + retention buffer + pinned active market
Shared public websocket
        ↓ L2 top-5 + trades
Per-market MarketObserver pool
        ↓
Standardized shadow execution probes
        ↓
Execution evidence (4h/latest 100)
        ↓
Eligibility Engine
        ↓
Ranker
        ↓
MarketSupervisor
        ├─ IDLE
        ├─ ACTIVATE (shadow decision only)
        ├─ HOLD_ACTIVE
        ├─ STOP_NEW_ENTRY
        ├─ DRAIN_FOR_SWITCH
        ├─ SWITCH
        └─ HALT
```

## Dynamic universe

`market_selector/universe.py`

Default policy:

- scan top 24 markets by 24h notional volume;
- minimum 24h volume remains the selector threshold ($10M in current research config);
- retain buffer: 8 ranks, so small volume-rank swaps do not force websocket churn;
- active/pending markets are pinned in the watched set while still present in eligible metadata;
- delisted markets are excluded;
- metadata refresh every 300 seconds by default.

The dynamic universe is only the cheap first stage. A market does **not** become tradeable merely because it is in this set.

## Market screening

Every watched market receives independent rolling state:

- spread;
- top-of-book queue;
- top-5 depth / imbalance;
- trade rate;
- aggressor flow;
- microprice edge;
- execution sample count;
- fill rate;
- maker ratio;
- 5s markout;
- P10K;
- T10K.

Market-state horizon remains 15 minutes. Execution evidence remains 4 hours/latest 100 attempts with a 30-minute freshness requirement.

## Switching discipline

The existing `MarketSupervisor` remains the single market-selection authority.

Key invariants:

- only QUALIFIED markets can become activation candidates;
- qualification must persist for the configured number of windows;
- an active market that deteriorates stops new entries;
- a replacement market must itself be stably qualified;
- a challenger must beat the active market by the configured margin for multiple windows before rotation;
- no candidate => IDLE;
- hard feed fault => HALT/fail-closed.

The shadow runtime currently passes `inventory_flat=True` because it owns no wallet. A future execution authority must replace that input with real reconciled position/account state before live switching is allowed.

## Evidence continuity

The runtime writes finalized standardized shadow-probe events to an append-only JSONL file. On restart it rehydrates only events whose `policy_id` exactly matches the current standardized probe; evidence from a different policy is not mixed into the same economics estimate.

Observer objects are retained in a process-level pool when a coin rotates out of the active scan set, so temporary universe rotation does not erase its execution evidence if the coin returns.

Market-state windows are intentionally not restored after a process restart. They must warm up from fresh public data.

## Fail-closed restart behavior

`runtime_state.py` atomically writes a JSON control-plane snapshot containing:

- dynamic universe;
- transport generation/health/reconnects;
- supervisor state and histories;
- decision/action;
- ranking;
- compact per-market metrics;
- feedback statistics.

An active market remembered from a previous process is restored only as control-plane identity. Its qualification streak is reset and its degradation state is forced fail-closed, so a restart cannot immediately grant new-entry authority from stale data.

## Transport

One shared Hyperliquid public websocket is used for the watched universe.

- callbacks are generation-fenced;
- all-book stale watchdog triggers automatic reconnect;
- exponential reconnect backoff is retained;
- universe changes trigger a clean generation replacement/resubscription;
- individual stale markets fail their data-quality eligibility instead of receiving optimistic data.

## Operations

One-command launcher:

`research/hyperliquid-volume/run_autonomous_shadow.sh`

Example systemd unit:

`research/hyperliquid-volume/ops/bitmomo-hl-shadow.service.example`

The systemd example runs continuously with `Restart=always` and restrictive filesystem/process settings. Paths are examples and must be adapted to the deployment host.

Default runtime artifacts:

- `data/runtime/autonomous_state.json`
- `data/runtime/autonomous_shadow_feedback.jsonl`

## Mainnet boundary

This implementation does not approve live trading.

Live execution remains blocked until all of the following are true:

1. one execution policy is frozen and passes fresh unseen Session 4;
2. risk/fee/drawdown economics pass;
3. real position/account reconciliation exists;
4. a risk guardian exists;
5. exactly one execution authority exists;
6. stop/drain/flatten semantics are integrated with real inventory;
7. kill switch exists;
8. shadow/paper soak shows no silent state divergence;
9. deployment and restart recovery are tested on the 24/7 host.

## Strategic separation

Research and runtime are separate layers.

Research may use SEEN replay data to propose/freeze a candidate. Runtime is not allowed to tune thresholds online from recent losses. The runtime continuously adapts **which market** is selected using frozen rules; it does not silently adapt **what the rules are**.

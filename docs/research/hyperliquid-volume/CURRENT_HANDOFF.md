# Bitmomo Hyperliquid — Current Handoff

Last updated: 2026-09-16
Canonical branch: `research/hyperliquid-referral-volume-v0`

## Mission

Build a 24/7 autonomous Hyperliquid engine that continuously discovers the perp universe, screens/ranks markets, trades at most one active market, stops when conditions deteriorate, drains/flat before switching, and remains IDLE when no market qualifies.

Business objective for launch beta is cumulative **$10,000 genuine trading volume** with bounded net cost, not necessarily positive trading PnL.

## Launch philosophy

Do not wait for one component to become 100% perfect while other layers are missing. Target roughly 70–80% maturity across direction, execution, screening, switching and observability while keeping safety/accounting/reconciliation fail-closed.

Launch progression remains:

`autonomous shadow -> 12–24h actual-host soak -> $1,000 controlled canary -> $10,000 beta mission`

No mainnet order submission is approved yet.

## Autonomous Runtime V0 — implemented / shadow only

Canonical runtime:

- dynamic Hyperliquid universe discovery;
- top-volume watch set with retention buffer;
- one shared public websocket;
- per-market rolling observers;
- standardized shadow execution evidence;
- deterministic eligibility/ranking;
- `MarketSupervisor` with IDLE / ACTIVE / STOP_NEW_ENTRY / DRAIN / SWITCH / HALT behavior;
- websocket reconnect/generation safety;
- duplicate-event suppression;
- atomic runtime status JSON;
- optional combined raw multi-market capture;
- fail-closed restart (old trading authority is never restored).

`Autonomous Runtime V0` itself remains read-only and owns no wallet/private key.

## Mission accounting / risk guardian — implemented

`market_selector/mission.py`

Full beta:

- target volume $10,000;
- epoch net-PnL floor -$5;
- mission hard floor -$10;
- maximum two sequential risk epochs.

Canary:

- target volume $1,000;
- epoch floor -$1;
- mission floor -$2;
- maximum two sequential epochs.

Profit is a real buffer. Example: realized mission PnL +$2 means $12 distance to the -$10 hard floor. Cumulative volume and PnL never reset between epochs.

Hard-loss precedence is conservative: if one fill both crosses target volume and overshoots the mission hard floor, result is HALT, not success.

## Exchange-native read/accounting — implemented and CI-covered

New read-side normalization:

- `market_selector/hyperliquid_adapter.py` reads real positions, open order IDs and fill economics through an injected official Hyperliquid `Info` object;
- no key is accepted/stored by the read adapter;
- reconciliation uses expected-vs-exchange positions/orders and fails closed on unexpected state;
- actual fill notional is used for volume accounting;
- actual `fee` is deducted from `closedPnl` rather than trusting closed PnL as fee-net.

New mission accounting:

- `market_selector/live_accounting.py` reads actual Hyperliquid fills and user funding history;
- cumulative genuine fill notional is the mission volume;
- mission PnL = gross closed PnL - actual fill fees + funding cashflow;
- entry fees count even when `closedPnl=0`;
- profit expands both epoch and mission buffers;
- the same $1k/$10k epoch state rules are evaluated from exchange-native history.

This closes the previous gap where mission accounting depended only on simulated round-trip outcomes.

## Execution safety boundary — implemented primitives

Implemented:

- deterministic account/order reconciliation contract;
- host-local `flock` exclusive execution-authority lock;
- default-OFF external operator control / kill primitive;
- risk guardian blocks new entries on mission stop/halt, feed fault, reconciliation failure, disabled authority or supervisor denial;
- reduce/flatten is conceptually separated from new-risk creation.

A low-level injected SDK adapter/controller scaffold exists for integration review, but there is still **no approved executable signer/bootstrap and no enabled mainnet service**. Do not treat the presence of adapter classes as mainnet approval.

## CI / validation state

Core selector/control-plane CI is green at **66 tests passed** after adding exchange-native accounting and read-adapter tests.

A separate `Hyperliquid Shadow Network Smoke` workflow has been added. It installs the official `hyperliquid-python-sdk==0.24.0`, connects only to public mainnet data in SHADOW_ONLY mode, discovers a dynamic universe, runs the autonomous scanner briefly, and asserts:

- runtime state was produced;
- mode remains `SHADOW_ONLY`;
- `mainnet_order_submission == false`;
- universe is non-empty;
- transport generation is live/recent.

This workflow uses no wallet or secret.

## Frozen research evidence

Session 3: **NO QUALIFIED MARKET / IDLE**. Standard JOIN+3/3 showed strong negative maker-fill markout (PONS/VVV roughly -9 bp) and poor P10K.

Entry Policy Dev1: **NO SESSION-4 CANDIDATE**. Persistence helped VVV but did not generalize.

Entry Policy Dev2: **NO SESSION-4 CANDIDATE**. Queue filters helped PUMP/PONS selectively but worsened BTC/VVV.

Do not weaken old research gates merely to force a market through.

## Direction Alpha Audit — parallel research priority

Predeclared plan: `DIRECTION_ALPHA_AUDIT_PLAN.md`.

Goal: separate standalone 3/3 signal quality from maker-fill adverse selection by measuring sign-adjusted future mid returns at +1s/+2s/+5s/+10s/+30s/+60s for all signals vs maker-filled/unfilled and matched baselines.

Order Lifecycle Dev3 remains paused until this causal diagnosis is known. Session 4 remains untouched.

## Launch-week remaining blockers

Before any real canary exposure:

1. complete/read the public-network smoke result;
2. deploy shadow runtime to one actual always-on host and collect 12–24h soak evidence;
3. review the low-level account/order integration boundary end-to-end;
4. wire exchange-native position/open-order/fill/fee/funding reads into the future controller loop;
5. demonstrate restart recovery cannot duplicate an entry or leave an orphan order;
6. demonstrate real flatten/reconciliation behavior in a controlled environment before promotion;
7. retain one execution authority and external default-OFF operator control;
8. only then consider the $1,000 canary.

## Mainnet status

**NOT APPROVED / NOT ENABLED.**

Current canonical production-like activity is public-data shadow scanning and read-only accounting/reconciliation. No secret should be committed to GitHub.

## New-chat bootstrap

Read this file first. Correct state is: autonomous dynamic-universe shadow runtime implemented; mission/risk/reconciliation/operator controls implemented; exchange-native read/accounting implemented; 66 tests green; public network smoke workflow added; Direction Alpha Audit still pending; Session 4 untouched; mainnet order submission not approved.

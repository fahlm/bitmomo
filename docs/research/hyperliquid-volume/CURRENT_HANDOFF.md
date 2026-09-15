# Bitmomo Hyperliquid — Current Handoff

Last updated: 2026-09-16
Canonical branch: `research/hyperliquid-referral-volume-v0`

## Mission

Build a 24/7 autonomous Hyperliquid engine that continuously discovers the perp universe, screens/ranks markets, trades at most one active market, stops when conditions deteriorate, drains/flat before switching, and remains IDLE when no market qualifies.

Business objective for launch beta is cumulative **$10,000 genuine trading volume** with bounded net cost, not necessarily positive trading PnL.

Launch philosophy: target roughly 70–80% maturity across direction, execution, screening, switching and observability while keeping safety/accounting/reconciliation fail-closed.

Launch progression:

`autonomous shadow -> >=12h actual-host soak -> $1,000 controlled canary -> $10,000 beta mission`

Mainnet order submission is **not approved or enabled**.

## Autonomous Runtime V0 — implemented / shadow only

Implemented:

- dynamic Hyperliquid universe discovery;
- top-volume watch set with retention buffer;
- one shared public websocket;
- per-market rolling observers;
- standardized shadow execution evidence;
- deterministic eligibility/ranking;
- `MarketSupervisor` with IDLE / ACTIVE / STOP_NEW_ENTRY / DRAIN / SWITCH / HALT;
- websocket reconnect/generation safety;
- duplicate-event suppression;
- atomic latest-state JSON;
- compact bounded operational-history JSONL;
- optional combined raw multi-market capture;
- fail-closed restart: previous trading authority is never restored.

The autonomous runtime is read-only and owns no wallet/private key.

## Public Hyperliquid network smoke — PASS

Canonical read-only smoke workflow: `.github/workflows/hyperliquid-shadow-smoke.yml`.

Verified against actual public Hyperliquid mainnet data:

- official `hyperliquid-python-sdk==0.24.0` installed successfully;
- dynamic universe discovery succeeded;
- observed universe included BTC, ETH, HYPE, LIT, PONS, SOL, XRP, ZEC;
- public websocket connected (`generation=1`);
- final transport healthy with sub-second book age;
- shadow feedback accumulated;
- supervisor correctly stayed IDLE/WARMUP without a stable qualified market;
- `mode=SHADOW_ONLY`;
- `mainnet_order_submission=false`;
- wallet not used.

A second smoke after adding compact state history also passed, confirming the persistence layer did not break real public-data operation.

## Operational soak observability — implemented

Canonical gate: `docs/research/hyperliquid-volume/SHADOW_SOAK_GATE_V0.md`.

`RuntimeStateStore` appends compact bounded history on every state save. `market_selector.soak_assessor` evaluates the history using predeclared defaults:

- duration >= 12h;
- healthy samples >= 99%;
- max state-sample gap <= 180s;
- max continuous unhealthy streak <= 180s;
- final transport healthy;
- all samples `SHADOW_ONLY`;
- mainnet submission false in every sample;
- non-empty universe in every sample.

`market_selector.runtime_healthcheck` provides an external fail-closed health probe for missing/stale/unhealthy state, unexpected mode, accidental mainnet flag, or empty universe.

Systemd watchdog examples were added:

- `ops/bitmomo-hl-shadow-watchdog.service.example`;
- `ops/bitmomo-hl-shadow-watchdog.timer.example`.

The watchdog runs every minute and can restart the shadow service when the process is alive but runtime state is stale/unhealthy. This complements `Restart=always`, which only handles process exit.

Reconnects, universe changes, supervisor transitions, HALT samples and suppressed duplicates remain diagnostic soak outputs rather than automatic failures merely for existing.

## Mission accounting / risk guardian — implemented

`market_selector/mission.py`

Full beta:

- target volume $10,000;
- epoch net-PnL floor -$5;
- mission hard floor -$10;
- max two sequential risk epochs.

Canary:

- target volume $1,000;
- epoch floor -$1;
- mission floor -$2;
- max two sequential epochs.

Profit is a real buffer. Cumulative volume/PnL do not reset between epochs. Hard-loss precedence is conservative: crossing target and overshooting the hard loss floor on the same finalized event results in HALT.

## Exchange-native read/accounting — implemented / CI-covered

`market_selector/hyperliquid_adapter.py` read side:

- positions from Hyperliquid account state;
- open-order IDs;
- real fill notional;
- real fill fees and closed PnL;
- deterministic reconciliation against expected local state;
- no key accepted/stored by read adapter.

`market_selector/live_accounting.py`:

- mission volume = actual cumulative fill notional;
- mission PnL = gross closed PnL - actual fill fees + funding cashflow;
- entry fees count even when `closedPnl=0`;
- profit expands mission/epoch loss buffers;
- canary/full mission states can be evaluated from exchange-native history.

## Execution safety boundary — implemented primitives / live lifecycle not approved

Implemented safety primitives:

- deterministic position/open-order reconciliation;
- host-local exclusive `flock` execution-authority lock;
- default-OFF external operator enable/kill control;
- mission risk guardian;
- supervisor/feed/reconciliation failures block new exposure.

A low-level SDK order/controller scaffold exists only for integration review. It is **not production-ready and must not be treated as an approved order service**. Partial-fill/cancel/restart/flatten behavior still requires complete controlled validation before exposure.

## CI state

Core selector/control-plane suite is green at **75 tests passed** after adding soak-history, soak-assessor and external runtime-healthcheck coverage.

Public-network smoke is also green with the operational-history persistence enabled.

## Frozen research evidence

Session 3: **NO QUALIFIED MARKET / IDLE**; standard JOIN+3/3 showed severe negative maker-fill markout in PONS/VVV.

Entry Policy Dev1: **NO SESSION-4 CANDIDATE**; persistence improved VVV but did not generalize.

Entry Policy Dev2: **NO SESSION-4 CANDIDATE**; queue filtering helped selected markets but worsened others.

Do not loosen frozen research gates merely to force activity.

## Direction Alpha Audit — parallel research priority

Predeclared plan: `DIRECTION_ALPHA_AUDIT_PLAN.md`.

Goal: isolate standalone 3/3 directional information from maker-fill adverse selection using future mid returns at +1/+2/+5/+10/+30/+60s and matched baselines.

Raw local research datasets are still required to execute the numeric audit. Do not fabricate a conclusion without them. Session 4 remains untouched.

## Launch-week remaining blockers

Before a real $1,000 canary:

1. deploy canonical shadow runtime to one actual always-on Linux host;
2. collect >=12h soak history and pass `soak_assessor`;
3. complete/review the controlled order lifecycle end-to-end, especially partial fills, cancel acknowledgement, restart recovery and flatten confirmation;
4. wire exchange-native position/open-order/fill/fee/funding reads into the future controller loop;
5. verify restart cannot duplicate entry or orphan order;
6. keep one execution authority and external default-OFF operator control;
7. only after those operational gates pass consider the $1,000 canary.

The connected Hostinger capability available in this environment is Horizons website creation, not a Linux/VPS runtime, so it is not suitable for this Python daemon soak. No compatible always-on host is currently connected to this chat.

## Mainnet status

**NOT APPROVED / NOT ENABLED.**

Current canonical production-like activity is public-data shadow scanning plus read-only account/accounting/reconciliation infrastructure. No secret should be committed to GitHub.

## New-chat bootstrap

Read this file first. Correct state: autonomous dynamic-universe shadow runtime implemented; public Hyperliquid network smoke PASS; mission/risk/reconciliation/operator controls implemented; exchange-native read/accounting implemented; compact soak history + deterministic assessor + external healthcheck/watchdog implemented; core CI 75 tests green; actual-host >=12h soak still pending; Direction Alpha Audit pending raw datasets; Session 4 untouched; mainnet order submission not approved.

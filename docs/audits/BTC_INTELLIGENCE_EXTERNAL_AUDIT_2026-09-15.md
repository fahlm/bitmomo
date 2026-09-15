# BTC Intelligence External Audit — 2026-09-15

Status: **AUDIT BRANCH ONLY — NOT PRODUCTION APPROVED**

Branch: `audit/btc-intelligence-institutional-hardening-v1`
Base: `8e3f5926173a0c98bd103a98e1204f22e15a305f` (PR #161 head at audit start)

## Executive conclusion

Bitmomo BTC Intelligence already has several unusually strong trust primitives for a small crypto product: deterministic market evaluation, canonical session records, fail-closed freshness behavior, version-separated evaluation, an append-only/no-cherry-picking Decision Ledger, delayed frozen Pro proof, and an explicit separation between directional bias and market regime.

The product is **not yet Glassnode/Bloomberg-class**. The main gap is no longer visual polish. It is the institutional data/operations layer: independent clock health, source lineage, point-in-time model governance, calibration, breadth of data, alerting/SLOs, and decoupling core compute from WordPress scheduling/presentation.

The audit therefore treats the target as:

> trustworthy decision intelligence first; terminal aesthetics second.

## External benchmark

Current public product material from Glassnode describes a unified layer spanning on-chain, spot, futures, options, ETFs, macro and TradFi data, with point-in-time credibility, metric metadata, backtesting, alerts and programmatic delivery through API/MCP/CLI. Bloomberg describes real-time normalized data, integrated news/event context, programmatic APIs, entitlements, activity monitoring, encrypted access, failover/load balancing and integrated analytical workflows.

Reference material:

- https://glassnode.com/products/data
- https://glassnode.com/products/studio
- https://professional.bloomberg.com/products/bloomberg-terminal/
- https://professional.bloomberg.com/products/data/data-connectivity/server-api/
- https://professional.bloomberg.com/products/data/enterprise-catalog/real-time-data-feed/
- https://professional.bloomberg.com/products/bloomberg-terminal/news/

Bitmomo should not imitate their feature count. It should adopt the same institutional principles on a narrower BTC-focused surface.

## Strong foundations worth preserving

### 1. Deterministic engine before narrative

The five-axis signal engine is deterministic and versioned. It does not rely on an LLM to decide the canonical market state. This is the correct trust architecture for a financial intelligence product.

### 2. Bias and regime are separate concepts

The regime classifier does not simply rename directional bias. That separation is important because structural market regime and short-horizon directional view are different questions.

### 3. Public/private trust boundaries are explicit

The public page reads current intelligence through a narrow public adapter. Row-level accountability is exposed through a dedicated accountability boundary. Frozen historical Pro proof is delayed before public exposure.

### 4. Accountability is unusually good

Decision Ledger selection is based on provenance and maturity rather than result. Misses, inconclusive results and missed settlement windows are not silently filtered away. This is a genuine product differentiator.

### 5. Methodology versions are not mixed

The scorecard separates incompatible methodology versions instead of presenting a blended historical accuracy number. Preserve this rule permanently.

## P0 findings and changes made in this audit

### P0-1 — Two-clock model was violated in the renderer

**Finding:** Market Pulse has an intraday clock (5-minute candles, canonical evaluation every 15 minutes, freshness <=30 minutes), while Major Brief has a much slower session clock. The page nevertheless hid Market Activity whenever the Major Brief became delayed.

**Risk:** a fresh intraday signal could disappear because an unrelated slower brief was stale. This contradicts the architecture, destroys repeat-visit value and makes freshness semantics misleading.

**Fix:** Market Pulse is now rendered independently before Major Brief freshness gating. A stale Major Brief withholds stale Bias/Confidence/Drivers but can still show a fresh Market Pulse.

### P0-2 — Invalid sessions could silently become US Post-Close

**Finding:** `normalize_session_type()` intentionally supports legacy aliases but defaults unknown inputs to `us_post_close`. Scheduler and canonical public read paths were using normalization before strict validation.

**Risk:** malformed records or calls can look like valid Post-Close intelligence. This is unacceptable at a financial trust boundary.

**Fix:** scheduler, canonical source validation and public adapter now require `is_supported_session_type()` before normalization. Unsupported session generation is blocked.

### P0-3 — Invalid bias could silently become Neutral

**Finding:** canonical Free projection previously coerced an invalid bias enum to `neutral`.

**Risk:** data corruption becomes a plausible-looking market opinion rather than an observable failure.

**Fix:** canonical validation now rejects invalid bias and returns unavailable instead of fabricating Neutral.

### P0-4 — Canonical numeric fields were insufficiently strict

**Finding:** public projection could cast malformed confidence to zero downstream. Price/confidence validation was not consistently enforced at every public trust boundary.

**Fix:** canonical validation requires positive numeric close, numeric score and numeric confidence. Public adapter independently verifies positive price, numeric confidence, valid timestamps and supported session type.

### P0-5 — Small-sample “accuracy” could look more mature than it is

**Finding:** the public Track Record correctly showed sample status, but still printed an accuracy percentage for an insufficient sample.

**Risk:** an 80%+ number from a tiny sample can create a false impression of validated predictive quality.

**Fix:** when sample status is `INSUFFICIENT SAMPLE`, the public surface withholds the accuracy percentage. `EARLY SAMPLE` is explicitly labelled temporary. `ADEQUATE` can show the percentage.

### P0-6 — Regression contracts updated

Tests now lock the intended behavior:

- Market Pulse survives a stale Major Brief when its own data is fresh.
- stale Major Brief still withholds stale directional view.
- insufficient-sample accuracy is withheld.
- unsupported scheduler sessions are blocked before market data access.
- canonical source code validates session/bias/numeric fields before public projection.
- public snapshot Opportunity is read from the immutable canonical session record, not from a later latest-option read.
- observability exposes independent health rows for Market Pulse, US Pre-Open, US Post-Close, settlement and source freshness.

### P0-7 — Market Pulse / Opportunity canonical wiring hardened

**Finding:** the branch had an append-only Opportunity store and a canonical attach hook, but the public directional snapshot could still read the latest Opportunity independently. That allowed a visitor-facing snapshot to combine one Major Brief record with a newer intraday Opportunity record that was not part of that immutable session snapshot.

**Risk:** canonical history and public presentation could drift. That is especially dangerous for a product claiming Decision Ledger/accountability semantics because a later intraday state can look as if it belonged to an earlier Major Brief.

**Fix:** public directional snapshots now consume Opportunity from `session_intelligence` inside the canonical session record. The latest Opportunity option remains available only for non-directional surface context. The session attach path records Opportunity record id, methodology, source, knowledge time and source last-close time in `valid_snapshot_lineage`.

### P0-8 — Lightweight operations health matrix added

**Finding:** automation health was effectively Post-Close-centric.

**Fix:** diagnostics now expose a five-row matrix for Market Pulse, US Pre-Open, US Post-Close, settlement and source freshness. Each row carries current state, reason, last run, next schedule and freshness budget. This is intentionally lightweight and reuses existing scheduler/runtime/source-diagnostic state.

## P0 remaining before any production approval

### P0-A — Run the complete release gate on the exact candidate

Required:

- PHP lint for every managed file.
- every deterministic PHP suite.
- terminal-grade contract.
- institutional-copy contract.
- authority/source contract.
- responsive/browser gate at required viewports.
- deterministic artifact build twice with matching hash.
- staging artifact/runtime parity.

Do not merge/deploy based only on code review.

### P0-B — Prove the health matrix on staging

The audit branch now includes a lightweight matrix for:

1. Market Pulse scheduler/feed/freshness.
2. US Pre-Open Major Brief.
3. US Post-Close Major Brief.
4. settlement/outcome evaluator.
5. source freshness diagnostics.

Before production approval, verify on staging that these rows reflect real cron state, source failures and recovery behavior. Alert thresholds/SLO routing can remain a follow-up; do not block this PR on a separate monitoring stack.

### P0-C — Decide and document the meaning of “US Post-Close” at 20:10 ET

The anchor is 20:10 America/New_York, approximately four hours after the US regular equity close at 16:00 ET. That may be a valid evening-wrap design choice, but it must be intentional and described accurately.

Options:

- keep 20:10 ET and call it an evening/post-close wrap with the exact anchor visible; or
- move the anchor closer to market close only after replay/backtest and methodology-version migration.

Do **not** change the anchor casually because it changes information availability and historical comparability.

## P1 — model governance

### Confidence must eventually include evidence coverage

Current Confidence is explicitly evidence coherence, not probability. That is the right conceptual framing. However, a snapshot may be publishable at >=80% completeness while some optional derivatives evidence is unavailable. Confidence does not yet explicitly penalize evidence coverage.

Do not hot-fix the current weights. Instead:

1. add `evidence_coverage` as a separately recorded feature;
2. replay historical snapshots;
3. test whether Confidence should be capped or penalized on degraded coverage;
4. evaluate calibration by confidence bucket;
5. version the methodology if behavior changes.

### Compare against simple baselines publicly when sample becomes meaningful

The scorecard already contains baseline machinery. The institutional question is not “is accuracy >50%?” but “does this system add value relative to simple alternatives?”

Minimum comparisons:

- previous-direction persistence;
- simple momentum;
- simple trend;
- always-neutral / abstention policy where relevant.

### Add calibration, not only hit rate

For a mature system, expose:

- sample size;
- conclusive denominator;
- calibration by confidence bucket;
- abstention/inconclusive rate;
- data-quality failure rate;
- performance by regime;
- methodology version.

## P1 — architecture and operations

### Split data/compute plane from WordPress presentation plane

WordPress is acceptable as CMS and renderer. It should not remain the long-term institutional execution substrate for market-data collection and core intelligence scheduling.

Target architecture:

`Sources -> ingestion -> normalized point-in-time store -> deterministic feature/engine jobs -> immutable intelligence records -> public/private API -> WordPress renderer + Telegram/email/alerts`

The first migration does not need Kubernetes or expensive infrastructure. A small supervised worker/cron service plus a durable relational/time-series store is enough if contracts are explicit.

### Introduce SLOs and observable data lineage

For every public number/intelligence record, preserve internally:

- source/provider;
- source timestamp;
- ingestion timestamp;
- transformation/model version;
- publication timestamp;
- freshness budget;
- whether fallback was used;
- whether a source was missing/degraded;
- immutable record ID.

Define SLOs for freshness and publication reliability before adding more AI agents.

## P1 — data breadth

Current engine is primarily price + exchange derivatives/microstructure. That is useful but materially narrower than a modern institutional BTC intelligence stack.

Prioritized expansion should be driven by decision value, not metric count:

1. **ETF flows / institutional spot demand**.
2. **macro & rates/event calendar** (CPI, FOMC, payrolls, Treasury yields, DXY where justified).
3. **options volatility surface** (IV, skew, term structure, key expiries/gamma context).
4. **on-chain capital/holder behavior** using point-in-time-safe metrics.
5. **stablecoin/exchange liquidity flows**.
6. **cross-exchange basis, liquidations and spot CVD/order-book quality**.

Every new source must enter shadow mode, get backfilled, receive freshness/data-quality contracts and prove incremental value before it affects canonical Bias/Confidence.

## P2 — product surface

After trust/data-plane work is stable, evolve the page from a static brief into a BTC workstation:

- drill-down chart from each driver to the underlying metric;
- definition/tooltips with methodology and caveats;
- historical state-change timeline;
- user alerts for state changes, not generic notifications;
- saved views/watch conditions for Pro;
- export/API only when canonical contracts are mature;
- event/news context linked to market movement without allowing narrative/LLM layers to overwrite canonical quantitative facts.

## What not to do

- Do not make “11 AI analysts” the core source of truth.
- Do not add dozens of metrics merely to resemble Glassnode.
- Do not optimize Confidence weights from tiny samples.
- Do not hide stale/failing data with neutral defaults.
- Do not mix historical methodology versions for a prettier performance number.
- Do not move the 20:10 ET anchor without a versioned replay/backtest.
- Do not deploy this audit branch directly to production.

## Audit branch change set

At the time this report was written, the branch contains targeted hardening only. It does not change the directional model weights or historical evaluator.

Changed areas:

- canonical intelligence validation;
- scheduler session validation;
- public adapter validation;
- immutable Market Pulse / Opportunity wiring into canonical session snapshots;
- lightweight operations health matrix;
- BTC Intelligence two-clock presentation semantics;
- small-sample Track Record presentation;
- regression contracts.

Production approval remains blocked until the exact candidate passes the repository's full release/staging/browser gates.

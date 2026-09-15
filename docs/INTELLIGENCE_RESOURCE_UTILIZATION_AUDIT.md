# Bitmomo Intelligence Resource Utilization Audit

Status: CTO audit for post-whitelist product work
Base reviewed: `bdc42f2a01214c70b1dbfbc7c7ab85ec247e0691`
Working PR: #176 (`feature/btc-intelligence-show-first-v1`)

## Product rule

Do not equate "use every resource" with "show every metric" or "put every field into the score".

Every intelligence resource must have one explicit job:

1. **Decisioning** — changes Bias, Confidence, Change, Meaning or Watch.
2. **Risk / context** — changes how the decision should be interpreted, not necessarily its direction.
3. **Proof** — validates whether prior intelligence behaved as expected.
4. **Diagnostics** — protects data quality/freshness/fallbacks and should normally stay out of the visitor UI.
5. **Research candidate** — collected/derived, but must prove incremental forward value before being promoted into the decision model.

The public page should expose the smallest useful projection of these resources. The paid product should expose additional decision depth, not a raw data dump.

## Resource map

| Resource | Current engine role | Current customer translation | CTO assessment / next action |
| --- | --- | --- | --- |
| 1H / 4H / 1D price candles | 4H primary direction; 1H/1D directional confirmation; 4H/1D structure; operational 1H levels; volatility and regime metrics | Bias, Confidence, drivers, session comparison; historical context | **Core / actively used.** Keep raw indicators hidden; surface conclusions and material change. |
| Direction: ADX, +DI/-DI, 1H/4H/1D bias | 35% of directional score; multi-timeframe confirmation affects Confidence | Bias / directional strength; material Direction driver | **Core / actively used.** Strong visitor value when expressed as state/change rather than indicator values. |
| Structure 4H | 30% of directional score | Material Structure driver; contributes to report/scenario language | **Core / actively used.** Keep as evidence rather than a second competing headline state. |
| Structure 1D | Stored in the structure axis and reason/context | Not independently surfaced | **Context candidate.** Do not add weight until forward validation shows incremental value beyond 4H structure + 1D directional confirmation. |
| Operational 1H support/resistance | Risk layer; support/resistance zones; confirmation status | Pro projection/risk; report scenario language | **High-value Pro resource.** Translate into monitoring/decision levels, but do not mislabel as Expected Range. |
| Funding rate | Direct Carry input | Material Carry driver; regime input | **Core / actively used.** Current funding matters to directional/carry state. |
| Basis | Direct Carry input | Material Carry driver; regime input | **Core / actively used.** |
| 7-day funding average | Computed in source data | Not used by current Carry score | **Research candidate.** Test incremental predictive/context value before scoring or surfacing. |
| Open interest 24h change | Direct Crowding input; regime input | Material Crowding driver | **Core / actively used.** |
| OI z-score | Computed in source data | Not used by current Crowding score | **Research candidate.** Validate whether normalization improves state-change or outcome quality versus raw 24h OI change. |
| Global long/short ratio | Direct Crowding input | Material Crowding driver | **Core / actively used.** |
| Taker buy/sell ratio | Direct Crowding input | Material Crowding driver; report scenario context | **Core / actively used.** |
| ATR 1H / 4H | Risk/invalidation distances and volatility context | Pro risk/invalidation; report language | **Risk / context.** Correctly not a direct direction vote. |
| Daily volatility percentile / regime | Independent volatility axis; extreme regime penalizes Confidence; regime-classifier input | Market activity/driver context | **Core risk resource.** Volatility should communicate uncertainty/pace, not direction. |
| Bollinger width 4H | Present in volatility reason | Not part of current volatility score | **Research/diagnostic candidate.** Do not surface or weight until incremental value is demonstrated. |
| 1D / 7D / 30D returns | Regime classifier inputs | Regime subsystem / history, not current public headline | **Actively used by regime engine.** |
| Volume percentile | Regime classifier input | Regime subsystem | **Actively used by regime engine.** |
| 30D range position | Regime classifier input | Regime subsystem | **Actively used by regime engine.** |
| Regime classification | Separate deterministic market-type classifier; explicitly independent from directional Bias | Stored/history; deliberately not shown as a second current public headline | **Useful internal/context state, not a Free headline.** Preserve semantic separation and existing public contract. |
| Opportunity / Market Pulse | Separate 5m source, canonical 15m evaluation, 14-day reference, <=30m freshness | HIGH / NORMAL / LOW activity state | **Strong Free differentiator.** Keep simple; raw percentile/range should remain hidden. |
| Session Intelligence | Compares canonical sessions; derives What Changed, Why It Matters, Watch | Change / Meaning / Watch | **High customer value.** This should dominate the public UX after state. |
| Source diagnostics / completeness / fallbacks | Quality/fail-closed protection | Provenance/freshness only | **Diagnostics.** Do not turn into UI clutter. |
| Outcome scorecard | Evaluates matured recorded-live intelligence, versioned | Aggregate proof / track record | **Proof resource.** Default UI should show only concise aggregate evidence; detailed ledger belongs to audit/drill-down. |
| Delayed frozen Pro brief | Historical Pro decision contract after delay/settlement | Expected Range, Base/Bull/Bear, Invalidation, What Changed, +24h outcome | **Best public proof of Pro value.** Show one strong real example, not a catalogue. |

## Key findings

### 1. The engine is richer than the public experience

The directional engine already combines Direction, Carry, Structure and Crowding and maintains Volatility as a separate risk/context axis. It also creates support/resistance zones and invalidation. The customer-facing Key Drivers layer then converts material five-axis evidence into deterministic language. The public terminal currently exposes only a thin subset of this value.

The correct fix is not a raw five-axis dashboard. It is better prioritization of **material evidence** and **state change**.

### 2. Current public page has too many simultaneous objects

The previous default flow exposed duplicated clock/session information plus State, Market Pulse, Bias, Confidence, Current Setup, What Changed, Why It Matters, Watch, 30D History, a six-column Decision Ledger, Track Record and multiple Pro archive cards.

Show-First V2 reduces the default visitor hierarchy to:

**State -> Change -> Meaning -> Watch -> Context/Evidence -> 30D -> one Pro proof -> aggregate proof -> CTA.**

The raw Decision Ledger remains an accountability data source but is removed from the default visitor flow. It is an audit instrument, not a primary investor/user artifact.

### 3. Do not expand the Free public contract just to prove sophistication

Existing regression tests deliberately keep raw Opportunity internals, Market State classifier taxonomy/certainty, private scorecard internals and current protected Pro fields out of the public presentation.

Quality should therefore be demonstrated through approved public-safe outputs: Bias, Confidence, Market Pulse, up to two strongest drivers, What Changed, Why It Matters, Watch, 30D context, aggregate outcomes and delayed historical Pro proof.

### 4. Pro translation layer is the main utilization gap

`Bitmomo_AI_Intelligence::pro_projection()` already exposes the full axes, support/resistance zones, invalidation and risk object to entitled consumers. The current Pro canonical adapter only prefills canonical identity, reference price, directional state, Confidence, freshness and invalidation. It intentionally refuses to fabricate Expected Range from support/resistance.

That safeguard is correct. The next Pro improvement should instead use existing risk/axis evidence to assist **Confidence explanation, monitoring conditions, scenario evidence and decision levels** while keeping Expected Range on its own versioned methodology.

### 5. Some computed fields need evidence before promotion

`funding_7d`, `oi_zscore`, 1D structure as an independent factor and Bollinger width are available/derived but are not direct inputs to the current directional score. This is not automatically a bug.

Before adding them to scoring, run shadow forward-validation against the existing frozen outcome methodology and require measurable incremental value. Avoid feature-count engineering.

## Product priorities

**P0 — Visitor cognition:** keep current intelligence compact; remove duplicated clock/session cards; hide the row ledger; show one delayed Pro decision example and concise aggregate proof.

**P1 — Pro Evidence Composer:** map existing axes/risk outputs into deterministic editor-assist fields for Confidence explanation, monitoring conditions and scenario evidence. Do not generate Expected Range from support/resistance.

**P1 — Resource utilization telemetry:** record which axis/driver materially influenced each canonical view so later outcome analysis can test contribution by resource.

**P2 — Shadow tests for research candidates:** test `funding_7d`, `oi_zscore`, 1D structure and Bollinger width for incremental forward value before changing production weights.

**P2 — Audit drill-down:** if detailed Decision Ledger remains useful for trust/research, move it behind a secondary accountability/methodology disclosure or dedicated audit route rather than the default conversion journey.

## Non-negotiable safety

- Never expose current protected Pro values on Free.
- Never call support/resistance an Expected Range.
- Never let stale Major Brief data look current because Market Pulse is fresh.
- Never promote a new metric into production scoring solely because it is already collected.
- Preserve recorded-live / frozen outcome lineage and version separation.

# Bitmomo Engineering Strategy

**Status:** Canonical engineering direction  
**Audience:** All Bitmomo engineers, reviewers, and technical agents  
**Purpose:** Define how Bitmomo evolves from a reliable B2C Bitcoin intelligence product into an institutional-grade intelligence platform without premature rewrites.

---

## 1. Mission and quality bar

Bitmomo is a Bitcoin market-intelligence product, not a crypto media site, not an AI chatbot, and not a trading-signal service.

The near-term commercial model remains **B2C**. The target customer is a professional or sophisticated retail user with meaningful buying power who is already comfortable with institutional-grade research and analytics tools.

The longer-term direction includes **family offices and conventional companies evaluating or adopting BTC as a reserve asset**. This future direction sets the engineering quality bar now, but does not justify premature enterprise infrastructure before business evidence exists.

The product should eventually be able to answer, for any important number or conclusion:

- What is this value?
- Where did it come from?
- When did the underlying event occur?
- When did Bitmomo observe it?
- Is it fresh, stale, partial, unavailable, or insufficient?
- Which methodology/version produced it?
- Can the historical result be reproduced without look-ahead bias?
- If AI stated it, what canonical record supports the claim?

The core strategic differentiation is not raw data collection alone. Bitmomo aims to create value through:

**multi-source data → context → intelligence → thesis → monitoring → accountability**

---

## 2. Guiding principles

### 2.1 Foundation before redesign

Do not perform a major visual rewrite while source control, deploy integrity, environment parity, data-state handling, and contract boundaries remain weak.

A visual V2 may happen later. It must be built on a foundation that can preserve it.

### 2.2 WordPress is not the permanent intelligence architecture

WordPress remains appropriate for:

- marketing
- homepage
- research/editorial content
- SEO
- About/legal pages
- pricing/conversion
- the near-term Pro MVP

But new Bitmomo intelligence capabilities must not assume WordPress is their permanent computational home.

The long-term direction is for WordPress to become a **consumer of canonical intelligence**, not the owner of the intelligence engine.

### 2.3 Frontend is never the source of truth

No chart, card, email, Telegram alert, or future terminal view may define its own version of a metric or thesis.

Presentation consumes canonical data and canonical intelligence outputs.

### 2.4 Fail closed, never fabricate

If input is stale, missing, inconsistent, or insufficient, surface that state explicitly.

Never invent substitute values, silently reuse stale data as current data, or let an LLM fill gaps with unsupported claims.

### 2.5 Institutional discipline without building Glassnode from scratch

Bitmomo should adopt institutional-grade data discipline where it matters: provenance, point-in-time awareness, versioning, lineage, reliability, reproducibility, methodology, and grounding.

It should **not** prematurely build a proprietary full-chain indexing warehouse, enterprise IAM stack, public SDK ecosystem, or SOC 2 program before demand justifies them.

---

## 3. Current and target architecture

### 3.1 Near-term

```text
Public data sources
(Binance / ETF / macro / Dune / others)
                ↓
       Bitmomo normalization
                ↓
   Existing intelligence engines
                ↓
     Canonical intelligence
                ↓
 WordPress / email / Telegram
```

The existing PHP-based engines may remain while they work. Separation happens through contracts first, not through a rewrite for its own sake.

### 3.2 Future target

```text
                         BITMOMO PLATFORM

        ┌─────────────────────────────────────┐
        │ MARKETING / CONTENT                 │
        │ WordPress · bitmomo.id              │
        │ Homepage · Riset · SEO · Legal      │
        │ Pricing · Conversion                │
        └────────────────┬────────────────────┘
                         │ consumes
                         ▼
        ┌─────────────────────────────────────┐
        │ INTERNAL / PRODUCT API              │
        │ Canonical outputs · provenance      │
        │ auth/entitlement · versioning       │
        └───────────────┬─────────────────────┘
                        │
             ┌──────────┴──────────┐
             ▼                     ▼
┌──────────────────────┐  ┌────────────────────────┐
│ DATA PLATFORM        │  │ INTELLIGENCE ENGINE    │
│ Binance              │  │ Five-axis engine       │
│ Dune                 │  │ Regime                 │
│ ETF / macro          │  │ Event Engine           │
│ derivatives          │  │ 11 Analysts            │
│ other sources        │  │ Jamba compression      │
└──────────┬───────────┘  │ Watchtower             │
           │              └──────────┬─────────────┘
           └─────────────┬───────────┘
                         ▼
             ┌───────────────────────┐
             │ CANONICAL DATA STORE  │
             │ provenance            │
             │ event/knowledge time  │
             │ methodology version   │
             │ quality/freshness     │
             └───────────────────────┘

Future consumer:
app.bitmomo.id → dedicated intelligence terminal
```

### 3.3 Technology direction, not immediate migration

When justified by product complexity:

- **WordPress/PHP:** marketing, editorial, conversion, existing near-term product surfaces
- **Next.js/React:** future web intelligence terminal at `app.bitmomo.id`
- **Python/FastAPI:** future data/intelligence services where AI/data workloads justify extraction
- **PostgreSQL:** likely canonical/point-in-time data store when current storage becomes insufficient
- **React Native:** only if a native iOS/Android app later has a clear product case

Do **not** introduce these frameworks merely to modernize the stack.

---

## 4. Roadmap overview

| Stage | Name | Purpose | Status |
|---|---|---|---|
| M0 | Reproducible Deploy | Work cannot exist only on a server | **CLOSED** |
| M1 | Production Known, Then Reachable | Inventory and baseline production before any write | **IN PROGRESS** |
| M2 | Founding Whitelist Launch Gate | Reliable launch-critical homepage + `/pro/` slice | QUEUED |
| M3 | One Visual System | Remove visual entropy without redesign | QUEUED |
| M4 | Data Presentation Contract | Numbers, provenance, units, precision and source behave consistently | QUEUED |
| M5 | Data State Contract | Every data component handles non-happy states explicitly | QUEUED |
| M6 | Operable and Fast | Accessibility, keyboard operation, performance | QUEUED |
| M7 | Contracts and Drift Prevention | Preserve the system through tests and documented contracts | QUEUED |
| M8 | Visual V2 / Rebrand | Optional full visual redesign after foundation is stable | DEFERRED |

In parallel, Bitmomo adopts the **Institutional Architecture Principles** in Section 7. They are constraints and incremental foundations, not six new projects that compete with M0–M7.

---

## 5. Detailed milestone gates

## M0 — Reproducible Deploy

**Goal:** A deploy path that cannot lose work.

### Gate

- Two consecutive deploys from a clean checkout; second reports no drift.
- Deliberate hand-edit on server causes deploy abort, not overwrite.
- All classified stray staging artefacts removed.
- `tests/` absent from all four live Bitmomo plugins.
- Clean dry run reports `missing=0`, `changed=0`, `drift=0`, `extras=0`.
- Final staging hashes match canonical git source.

### Current evidence

M0 is closed.

- Staging SSH authenticated successfully.
- Staging document root verified.
- 26 staging files recovered to git.
- 102 canonical files matched staging snapshot hashes at recovery checkpoint.
- 8 classified staging artefacts removed.
- Four live Bitmomo plugin `tests/` directories verified absent.
- Two clean deployments passed.
- Deliberate remote edit produced `changed=1`, `drift=1`, and deploy abort.
- Test edit restored.
- Final dry run: `missing=0`, `changed=0`, `drift=0`, `extras=0`.
- Production was not changed.

---

## M1 — Production Known, Then Reachable

**Goal:** Know exactly what production is running before permitting a production write.

### Required inventory

Read-only classification of managed production files:

- known
- drifted
- extra
- missing

Managed scope:

- Bitmomo child theme
- `bitmomo-ai`
- `bitmomo-btc-intelligence`
- `bitmomo-pro`
- `bitmomo-regime`

Anything production-only must be rescued into git before it can be overwritten.

### Configuration/DB baseline

M1 also inventories critical state that is not represented by ordinary files:

- active theme
- active plugins and versions
- WordPress version
- PHP version where available
- homepage/front-page mapping
- critical slug → page ID mappings
- permalink structure
- relevant menu assignments
- relevant WordPress cron/scheduler state
- Bitmomo plugin schema/version values
- LiteSpeed exclusions affecting Bitmomo
- important environment-specific constants/settings

Environment-specific WordPress numeric IDs are documentation, not preferred application identifiers. Stable slugs/templates/keys should be used wherever possible.

### Gate

- Production inventory complete; every managed file classified.
- No production-only code remains outside git.
- Critical production configuration baseline recorded.
- Production manifest baseline recorded.
- Production dry run reports zero unexplained extras.
- Deploy tooling has explicit `staging` and `production` targets.
- Secrets, paths and manifests are separate per target.
- Production write requires explicit confirmation.
- All M0 guards remain intact.
- No production write occurs during M1 inventory/baseline work.

---

## M2 — Founding Whitelist Launch Gate

**Goal:** Reach the first real B2C audience without waiting for the whole platform to be perfected.

Scope is intentionally limited to the two conversion-critical surfaces:

- homepage
- `/pro/`

### Gate

Verified anonymously:

- zero viewport overflow at 390 / 768 / 1024 / 1440
- tap targets ≥ 44 × 44 px on conversion controls
- numeric surfaces use tabular figures
- every launch-critical data component shows source + as-of + timezone
- loading, empty, error, stale and partial/insufficient states are handled where applicable
- whitelist form works on mobile and desktop
- title/metadata do not expose staging hostname or environment leakage
- Founding pricing and business rules remain canonical

M2 is a **business-event gate**, not a visual perfection gate.

---

## M3 — One Visual System

**Goal:** Remove visual entropy without performing the future V2 redesign.

Baseline measurements from 9–10 Sep 2026 identified excessive font sizes, radii, weights and unused tokens.

### Gate

- Distinct font sizes on `/pro/`: target ≤ 10 meaningful sizes.
- Distinct radii: 3.
- Distinct font weights: 4.
- Unreferenced visual tokens: ≤ 5.
- Zero viewport overflow across four widths and five representative page types.
- Existing contrast baseline does not regress.
- No structural M3 work changes approved rendered colors.

Prefer semantic tokens such as display, h1, h2, h3, body-lg, body, body-sm, label, metric-lg and metric-sm rather than arbitrary one-off values.

---

## M4 — Data Presentation Contract

**Goal:** All Bitmomo data surfaces behave like components of a research instrument.

This contract must be reusable across Binance, Dune, ETF flows, macro data, Event Engine, Jamba, 11 Analysts and Watchtower.

For every metric class define:

- canonical metric identifier
- value type
- unit
- precision
- sign convention
- formatting rule
- missing-state rule
- stale threshold
- source
- as-of timestamp
- timezone
- data-quality state
- methodology/version identifier where applicable
- semantic cue
- accessible non-color cue

### Gate

- Metric formatting registry covers every customer-facing metric class.
- 100% numeric surfaces use tabular figures where appropriate.
- Fixed precision and sign conventions are consistent.
- Use real minus sign `U+2212` for customer-facing figures.
- Every data component exposes source + as-of + timezone.
- Direction is never communicated by hue alone.
- Frontend components do not independently redefine canonical values.

---

## M5 — Data State Contract

**Goal:** A blank, stale or partial data surface never masquerades as a valid current result.

Canonical states:

- `loading`
- `fresh`
- `partial`
- `stale`
- `unavailable/error`
- `empty/insufficient`

A component may use a subset only if some states are structurally impossible and documented.

### Gate

- Component × state matrix documented and implemented.
- No component collapses to zero height during loading where layout stability matters.
- Error, stale, partial, empty and insufficient states are distinguishable.
- No fabricated values in any state.
- Source quality/freshness propagates into downstream AI and narrative layers.

---

## M6 — Operable and Fast

**Goal:** The product is usable without a mouse and fast for a cold anonymous visitor.

### Gate

- Full keyboard walkthrough passes at 390 and 1440.
- `aria-expanded` matches actual state.
- Focus returns correctly after menu/modal interactions.
- Skip link exists where appropriate.
- `aria-current` is correct.
- Visible focus reaches at least 3:1 contrast.
- Reduced-motion preference is honoured.
- Cold anonymous mobile-throttled test: LCP ≤ 2.5 s.
- CLS ≤ 0.1.
- CSS + JS ≤ 250 KB on the measured launch surface unless an explicitly approved exception is recorded.
- Performance measurement records cache state and test method so a warm-cache pass cannot masquerade as the gate.

---

## M7 — Contracts and Drift Prevention

**Goal:** The system stays reliable after the rescue project is over.

### Required contracts

At minimum:

- homepage 30D chart contract test
- whitelist form contract test
- Daily Intelligence freshness/fail-closed contract test
- schema compatibility tests for canonical data/output structures

### Gate

- Minimum test suites reflect actual critical contracts.
- Lowering required suites is an explicit engineering decision, never a convenience fix.
- LiteSpeed exclusions critical to product correctness are represented in repository documentation/config where feasible.
- `DESIGN_SYSTEM.md` evolves in the same commit as relevant visual-system changes from M3 onward.
- A reviewer can predict important rendered behavior from the documented contracts.
- Drift between repository, environment and critical runtime configuration is detectable.

---

## M8 — Visual V2 / Rebrand

A full redesign is deliberately deferred until M0–M7 have created a stable substrate.

Current candidate direction:

- white base
- Navy Blue `#1B2A4A`
- institutional, authoritative, highly responsive visual language
- semantic green / gray / red for market states

M8 is not required for Founding launch and should not interrupt foundation work.

---

## 6. Canonical data contract direction

After M2, Bitmomo should formalize a canonical data envelope before expanding Dune/Jamba/Event Engine work materially.

A representative shape:

```json
{
  "metric": "btc_funding_rate",
  "value": 0.0125,
  "unit": "percent",
  "event_time": "2026-09-10T07:00:00Z",
  "observed_at": "2026-09-10T07:01:12Z",
  "source": "binance",
  "source_record_id": "...",
  "source_version": "...",
  "schema_version": "1",
  "methodology_version": "...",
  "quality": "good",
  "freshness": "fresh"
}
```

Not every source can supply every field. Missing provenance should be explicit rather than invented.

### Point-in-time principle

Bitmomo should distinguish:

- **event time:** when the underlying market/on-chain event occurred
- **knowledge/observed time:** when Bitmomo first knew or ingested it

This is the minimum foundation for honest historical evaluation and future backtesting without silent look-ahead bias.

Full bitemporal storage is a future capability, not a reason to delay M2. Add revision/supersession fields when real source behavior requires them.

### Source reconciliation

A second source is required where business risk and metric criticality justify it, not mechanically for every metric.

Each critical metric should eventually define:

- primary source
- optional independent/reference source
- acceptable tolerance
- mismatch behavior
- degraded-state policy

---

## 7. Institutional Architecture Principles

These principles run alongside M0–M8. They are not a mandate to build six enterprise projects now.

### I1 — Point-in-time and provenance discipline

Historical evaluation must eventually be able to distinguish what is known now from what Bitmomo knew at the evaluated time.

### I2 — Internal API/data contracts first

Before public APIs, establish stable internal contracts between source ingestion, intelligence logic and presentation.

A future direction may include endpoints such as:

```text
/api/v1/btc/current
/api/v1/btc/history
/api/v1/events
/api/v1/thesis
/api/v1/watchtower
```

These are architectural examples, not an instruction to build a public API now.

### I3 — Methodology registry

Every important metric or proprietary output should eventually have a customer-readable methodology surface covering:

- definition
- source
- formula/logic at an appropriate disclosure level
- interpretation
- known limitations
- frequency/freshness
- methodology version

This includes proprietary concepts such as Directional Bias, Confidence, Market State and Market State Certainty.

### I4 — AI grounding and evaluation

AI is allowed to summarize and reason over canonical inputs; it is not allowed to invent market facts.

Target requirements:

- numerical claims trace to canonical records
- input package carries provenance/freshness/quality
- output follows strict schema
- reject/fallback path exists for invalid output
- stale/partial source state propagates into AI output
- evaluation set exists for known-answer tasks
- material changes to prompts/models/schemas are regression-tested

Jamba is best treated initially as a compression/narrative layer over grounded data, not an unrestricted source of facts.

The 11 Analysts should consume normalized/canonical data packages and emit schema-validated outputs before synthesis.

### I5 — WordPress escape hatch

The architecture must allow a future dedicated terminal to consume the same canonical outputs as WordPress.

A future split may be:

```text
bitmomo.id      → WordPress marketing/content
app.bitmomo.id  → Next.js/React intelligence terminal
                 ↓
             internal API
                 ↓
       canonical data/intelligence
```

No current rewrite is required to preserve this option.

### I6 — Enterprise readiness follows evidence

Potential later capabilities:

- SSO / OIDC / SAML
- RBAC
- audit logs
- API keys and lifecycle
- rate limiting / usage metering
- status page and incident history
- written service objectives/SLA
- exports/SDKs
- SOC 2 readiness

These become implementation projects when institutional prospects or operating risk justify them.

---

## 8. Intelligence platform sequence after M2

Once the Founding launch gate is closed, execute in this order unless evidence dictates otherwise.

### Phase D1 — Canonical Data Foundation

- normalized source envelope
- provenance
- event/observed time
- quality/freshness
- schema/version policy
- source health checks

### Phase D2 — Intelligence Engine Boundary

Move toward:

```text
Sources → Canonical Data → Intelligence Engine → Canonical Output → Consumers
```

Do not rewrite the existing PHP engine merely to claim service separation. Extract when complexity and reuse make the boundary valuable.

### Phase D3 — New intelligence capabilities

Build against the same contracts:

- Event Engine
- Dune feeds
- ETF/macro inputs
- 11 Analysts
- Jamba compression
- Watchtower

### Phase D4 — Internal product API

Expose canonical outputs consistently to multiple Bitmomo consumers.

### Phase D5 — Dedicated terminal

Build `app.bitmomo.id` only when a richer interactive intelligence workspace creates clear user value.

### Phase D6 — Institutional product

Potential focus:

- family-office Bitcoin intelligence
- corporate reserve-asset monitoring
- liquidity/regime risk
- volatility/drawdown context
- macro context
- counterparty/exchange risk context
- executive intelligence briefs

Enterprise/security capabilities are added based on real buyer requirements.

---

## 9. Operational standards

The following items were missing or under-specified in the original presentation roadmap and are now part of the engineering direction.

### 9.1 Observability

Every scheduled ingestion and critical intelligence job should eventually expose:

- last success
- last failure
- duration
- source freshness
- record count where meaningful
- schema/version
- retry state

Do not wait for customer reports to discover that a feed is stale.

### 9.2 Source health and degradation

For each external source define:

- expected update cadence
- timeout
- retry/backoff
- stale threshold
- failure/degraded behavior
- whether downstream generation must stop

### 9.3 Schema and methodology versioning

Any stored output that may be evaluated historically must carry enough version information to know which contract/methodology produced it.

Breaking schema changes require explicit migration or version transition; silent reinterpretation of old records is prohibited.

### 9.4 Secret management

- secrets never enter git
- staging and production credentials remain separated
- use environment/host secret mechanisms appropriate to the deployment environment
- rotate leaked or shared credentials
- document ownership without documenting secret values

### 9.5 Backup and restore drills

A backup is not trusted merely because it exists.

Critical stores should have:

- documented backup location
- retention policy
- restore procedure
- periodic restore verification/drill appropriate to business risk

### 9.6 Data retention

Define retention by class:

- raw source payloads
- normalized/canonical records
- intelligence outputs
- event records
- logs
- customer/account data

Do not retain data indefinitely by default if it has no product, audit or legal value.

### 9.7 Incident ownership

Even before formal on-call, production-critical components must have:

- an owner
- detection method
- kill switch/fail-closed behavior where relevant
- recovery runbook
- post-incident note for material failures

### 9.8 Vendor and LLM cost controls

Dune, LLMs and other paid providers require explicit operational controls:

- usage limits
- request budgets
- caching/deduplication
- fallback behavior
- per-feature cost visibility
- model/vendor changes recorded with evaluation evidence

Do not let a narrative-generation feature create unbounded variable cost.

### 9.9 Security baseline

Before enterprise security work, maintain a practical baseline:

- minimal active plugins
- remove unused attack surface after dependency verification
- patch dependencies/plugins
- least privilege for deploy credentials
- no publicly reachable test/debug code
- explicit environment separation
- audit critical write paths where feasible

---

## 10. Current founder decisions

### Numeric WordPress page IDs

Target direction: remove environment-specific numeric-ID coupling where stable slug/template/context can replace it. Establish historical reason before changing existing special cases.

### Inactive SEO plugins

Canonical SEO plugin is expected to remain Rank Math. Yoast and All in One SEO should be removable once M1 verifies no dependency or required data migration.

### Unused 786 KB logo asset

Remove after reference checks across source and relevant WordPress configuration/database confirm it is unused.

These are maintenance decisions, not reasons to expand scope into redesign.

---

## 11. Explicit non-goals

Until supported by business evidence, do not add:

- full visual V2 before foundation milestones
- WordPress/page-builder migration
- React/Next.js merely for modernization
- React Native unless a native mobile product is justified
- full proprietary Bitcoin indexing infrastructure
- public API/SDK ecosystem before internal contracts mature
- enterprise SSO/RBAC/SOC 2 program before institutional demand
- RAG/per-agent memory by default
- unbounded autonomous agents
- fabricated historical backfills
- duplicated market engines
- new multi-asset scope before BTC product evidence

No new feature may weaken deploy safety or data trust to ship faster.

---

## 12. Execution rules for engineers and coding agents

1. **One milestone at a time.** Do not start M(n+1) until the current gate is closed or an explicit exception is approved.
2. **Do not redo completed audits without evidence.** Reuse verified checkpoints and hashes.
3. **Prompts/tasks should be outcome-based and concise.** Avoid spending runtime narrating work that is already known.
4. **Read before write.** Inventory an environment before modifying it.
5. **Recover drift before overwrite.** Unknown server code is evidence, not garbage.
6. **Production is opt-in.** No production write without an explicit target and explicit approval/confirmation path.
7. **No silent fallback.** Data and deploy systems fail closed on ambiguity.
8. **Keep staging and production distinct.** Separate paths, secrets, manifests and configuration.
9. **No source-of-truth in screenshots or server editors.** Canonical code and contracts belong in git.
10. **Documentation changes with the contract.** If an implementation changes a documented contract, update the canonical document in the same change.
11. **Prefer incremental extraction to big rewrites.** Introduce service/framework boundaries when reuse or complexity justifies them.
12. **Customer-visible data must be explainable.** Source, freshness and methodology are product properties, not optional metadata.

---

## 13. Current status

### M0 — CLOSED

Reproducible staging deployment and drift protection have been proven live. Production was not changed.

### M1 — IN PROGRESS

At the latest known checkpoint, production comparison work and a production snapshot were available locally and production-only differences were being packaged as rescue evidence before tooling decisions. Do not assume M1 is closed until its explicit gate report is available.

### M2+ — QUEUED

Do not pull forward later-stage redesign or institutional infrastructure while M1/M2 remain open unless an urgent security issue requires an exception.

---

## 14. Strategic end state

Bitmomo should evolve in this order:

```text
Reliable code/deploy foundation
          ↓
Known production environment
          ↓
Founding customer launch
          ↓
Canonical data + provenance
          ↓
Reusable intelligence contracts
          ↓
Event Engine / Dune / 11 Analysts / Jamba / Watchtower
          ↓
Internal API + multiple consumers
          ↓
Dedicated intelligence terminal when justified
          ↓
Family-office / corporate BTC reserve intelligence
          ↓
Enterprise security and service capabilities based on demand
```

The objective is not to imitate an institutional data vendor feature-for-feature.

The objective is to make Bitmomo's differentiated intelligence system **trustworthy, reproducible, grounded, portable and commercially useful** — first for sophisticated B2C users, then for institutional users when the evidence supports that expansion.

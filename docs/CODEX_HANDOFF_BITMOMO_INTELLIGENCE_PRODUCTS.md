# Codex Handoff — Bitmomo Intelligence Products (Market Regime + Watchtower)

This is the combined summary for both new, fully isolated intelligence products built in
parallel with Codex's runtime/deployment work on the existing Bitmomo stack. It indexes the
two detailed, product-specific handoff documents rather than repeating them — read those for
the actual step-by-step checklists:

- **Product A (Bitmomo Market Regime):** [`website/wp-content/plugins/bitmomo-regime/CODEX_INTEGRATION_CONTRACT.md`](../website/wp-content/plugins/bitmomo-regime/CODEX_INTEGRATION_CONTRACT.md)
- **Product B (Bitmomo Watchtower):** [`website/wp-content/plugins/bitmomo-watchtower/CODEX_INTEGRATION_CONTRACT.md`](../website/wp-content/plugins/bitmomo-watchtower/CODEX_INTEGRATION_CONTRACT.md)

This document has two layers: the original **core build** (each product's own engine, PRs
#40–#46), and a subsequent **Integration Hardening Phase** (this update) that added no new
features and touched no live runtime, but produced the contracts, fixtures, and safety
documentation Codex needs to integrate both products with as little guessing as possible.

## Layer 1 — What was built, and where (core build)

Two new, fully isolated WordPress plugins — each its own PR sequence, each with zero
dependency on `bitmomo-pro`, `bitmomo-ai`, the active theme, or each other:

| Product | Plugin | PRs | Status |
|---|---|---|---|
| A — Market Regime | `website/wp-content/plugins/bitmomo-regime/` | [#40](https://github.com/fahlm/bitmomo/pull/40), [#41](https://github.com/fahlm/bitmomo/pull/41), [#42](https://github.com/fahlm/bitmomo/pull/42) | Core sequence complete |
| B — Watchtower | `website/wp-content/plugins/bitmomo-watchtower/` | [#43](https://github.com/fahlm/bitmomo/pull/43), [#44](https://github.com/fahlm/bitmomo/pull/44), [#45](https://github.com/fahlm/bitmomo/pull/45), [#46](https://github.com/fahlm/bitmomo/pull/46) | Core sequence complete |

Both are deterministic, no-LLM, no-black-box classification/scoring engines. Both persist
their history in private, headless, append-only custom post types — nothing publicly
queryable, nothing in REST. Neither plugin fetches live data, runs on a schedule, or sends
anything to anyone. Both stop exactly at "here is a clean, tested, documented callable — a
runtime layer needs to invoke it."

## Layer 2 — Integration Hardening Phase (this update)

**Goal of this phase, stated plainly:** make integration mechanical, not exploratory. Every
document and fixture below exists so Codex can build the runtime layer by reading contracts and
running tests against them, instead of reverse-engineering intent from source code. **No third
product was built. No feature was added. No live runtime, Hostinger, staging, theme, homepage,
`bitmomo-pro` integration, Telegram send, external feed, or payment code was touched.**

New artifacts, by product:

**Product A (`bitmomo-regime/`):**
- `FIELD_SEMANTICS.md` — every `Bitmomo_Regime_Input` field's definition, unit, time window,
  allowed range, source expectation, required/optional status, and derived-vs-raw nature.
- `tests/fixtures/regime-{accumulation,expansion,distribution,capitulation,transition}.json` —
  five fixtures, each a full normalized input plus the classifier's real, code-verified output
  (regime, confidence, evidence, conflicts, scores, directional bias).
- `tests/test-bitmomo-regime-fixtures.php` (86 assertions) — regenerates and asserts each
  fixture against the live classifier.
- `tests/test-bitmomo-regime-adapter-contract.php` (98 assertions) — a deterministic contract
  test for what a future runtime adapter must and must not be able to get past validation,
  including two documented KNOWN GAP canaries (see "Known gaps" below).

**Product B (`bitmomo-watchtower/`):**
- `DETECTOR_INTERFACE.md` — maps the spec's conceptual "Event Candidate" fields onto
  `Bitmomo_Watchtower_Event_Input`'s real contract, plus an illustrative (non-shipped)
  `Bitmomo_Watchtower_Detector` interface sketch and five documented ambiguities.
- `tests/test-bitmomo-watchtower-detector-contract.php` (98 assertions) — the enforcement-side
  contract test a real future detector's output must pass.
- `CANONICAL_FIELD_MAPPING.md` — a field-by-field mapping between `bitmomo-pro`'s real
  `bm_pro_brief` record and Watchtower's `Bitmomo_Watchtower_Thesis`, grounded in the actual
  `bitmomo-pro` source (not guessed) — including the finding that `bitmomo-pro` has **zero**
  existing concept of "regime" today.
- `tests/fixtures/watchtower-{price-range-break,volatility-shock,open-interest-shock,funding-extreme,liquidation-cascade,data-degraded}.json`
  — six fixtures, one per `Bitmomo_Watchtower_State_Transition_Engine::TYPES` value, each with
  a raw event candidate, normalized event, real materiality score, a 3-candidate dedup batch
  demonstrating clustering, and an alert-transition walkthrough (five are explicitly labeled
  `is_illustrative: true` — see "Known gaps," #1).
- `tests/test-bitmomo-watchtower-fixtures.php` (122 assertions) — regenerates and asserts each
  fixture against the live materiality engine, deduplicator, transition engine, alert policy,
  and router.
- `ALERT_POLICY_SAFETY_REVIEW.md` + `tests/test-bitmomo-watchtower-alert-safety.php` (20
  assertions) — six requested edge-case scenarios against the real cooldown/alert-class logic,
  surfacing two genuine ambiguities (see "Known gaps," #2–#3). **No threshold was retuned.**
- `STATIC_SECURITY_AUDIT.md` (this phase, cross-plugin) +
  `tests/test-bitmomo-watchtower-alert-outbox-contract.php` (10 assertions) — the one concrete
  defect this phase found and fixed: `Bitmomo_Watchtower_Alert_Outbox::enqueue()` now validates
  `alert_class`/`transition_type` against their real registered enums before persisting, closing
  the one inconsistency found relative to how every other WP-writing class in both plugins
  already validates before writing.

**Cross-cutting (`docs/`, `scripts/`):**
- `BITMOMO_RUNTIME_DATAFLOW.md` — a Mermaid diagram plus a 9-point walkthrough distinguishing,
  for every arrow, whether it is implemented/tested code, a logical (no-code) relationship, or
  explicitly not built.
- `FAILURE_MODE_MATRIX.md` — 7 Regime failure modes × 8 Watchtower failure modes, each scored
  across FAIL CLOSED? / STATE PRESERVED? / PUBLIC OUTPUT? / ALERT? / ADMIN VISIBILITY?, plus a
  cross-cutting-gaps section.
- `STATIC_SECURITY_AUDIT.md` — the nine-category static audit described above (public CPT
  exposure, REST exposure, unescaped output, direct external calls, hidden cron, direct
  Telegram/LLM calls, unbounded DB growth, unsafe meta writes, accidental cross-plugin
  dependency).
- `scripts/test-bitmomo-intelligence.sh` — one consolidated runner: `php` (no WordPress, no
  PHPUnit, no database) over every `tests/test-*.php` file in both plugins, aggregating and
  reporting a single pass/fail verdict with a non-zero exit code on any failure. **640/640
  assertions passing** across both plugins as of this phase (up from 154 assertions in the core
  build — the increase is fixtures/contract/safety/security tests, not new product features).

## CLAUDE BUILT / CODEX MUST BUILD / FOUNDER MUST APPROVE

This is the load-bearing section of this update — the explicit three-way split the brief asked
for, so nobody has to infer it from prose elsewhere.

### CLAUDE BUILT (done, tested, merged or in this phase's two PRs — nothing further needed here)

- Both products' full domain logic: `Bitmomo_Regime_Input`/`Classifier`/`Hysteresis`/
  `State_Store`/`History` (Product A); `Event_Input`/`Materiality_Engine`/`Deduplicator`/
  `Thesis`/`Thesis_Store`/`State_Transition_Engine`/`Alert_Policy`/`Alert_Outbox`/
  `Analyst_Router`/`Orchestrator` (Product B). All pure PHP, all deterministic, all covered by
  passing tests.
- All contract documentation: `FIELD_SEMANTICS.md`, `DETECTOR_INTERFACE.md`,
  `CANONICAL_FIELD_MAPPING.md`, `ALERT_POLICY_SAFETY_REVIEW.md`, `STATIC_SECURITY_AUDIT.md`,
  `BITMOMO_RUNTIME_DATAFLOW.md`, `FAILURE_MODE_MATRIX.md`.
- All fixtures (5 Regime regime outcomes, 6 Watchtower event types) and all contract/fixture/
  safety/security regression tests (640 assertions total), plus the one consolidated test
  runner.
- The one concrete security fix found in this phase (`Alert_Outbox::enqueue()` enum
  validation).
- The private, headless, append-only CPT storage layer for all three record types, already
  fully locked down (not public, no REST, no front-end query).

### CODEX MUST BUILD (the actual runtime — nothing below exists yet, by design)

- **Product A:** a real market-data adapter that shapes live data into `Bitmomo_Regime_Input`'s
  exact contract (see `FIELD_SEMANTICS.md` for every field's precise meaning — note the two
  KNOWN GAP canaries below), a scheduled call (cron or otherwise) to
  `Bitmomo_Regime_State_Store::evaluate_and_record()`, and placement of the
  `[bitmomo_market_regime]` / `[bitmomo_market_regime_history]` shortcodes wherever the founder
  decides (homepage or elsewhere — not decided by either plugin).
- **Product B:** the entire detector layer (`DETECTOR_INTERFACE.md` documents the contract, but
  zero detector code exists — no polling, no Binance, no news, no exchange APIs anywhere in
  this plugin); the event-candidate-to-Thesis synthesis step (the single biggest gap in
  `BITMOMO_RUNTIME_DATAFLOW.md` — nothing today turns a scored/clustered event cluster into a
  new candidate `Bitmomo_Watchtower_Thesis`); a scheduled call to
  `Bitmomo_Watchtower_Orchestrator::process()`; and a real Telegram (or other) adapter around
  `Bitmomo_Watchtower_Alert_Outbox::get_queued()`/`mark_status()` — no send transport exists
  anywhere in this plugin today.
- For both products: verifying every WordPress-dependent class (both CPT stores, the outbox) on
  real staging — these are only ever exercised against pure-PHP stubs in this phase's tests, per
  the brief's explicit "no live runtime work" restriction.
- Any retention/archiving mechanism for the three append-only CPTs (`STATIC_SECURITY_AUDIT.md`
  finding 7) — flagged as a real operational gap, deliberately not built here since it requires
  a new scheduled job, which is runtime work.

### FOUNDER MUST APPROVE (product/policy decisions this phase deliberately did not make)

- **Alert Policy Ambiguity 1** (`ALERT_POLICY_SAFETY_REVIEW.md`): should a dramatically larger
  same-class event bypass cooldown? Today it doesn't — `Alert_Policy::evaluate()` has no
  severity/magnitude input at all. Fixing this needs a new parameter and a decision on how much
  bigger "big enough to bypass" means.
- **Alert Policy Ambiguity 2** (`ALERT_POLICY_SAFETY_REVIEW.md`): should cooldown be scoped per
  domain? Today one busy domain's alert can silently suppress an unrelated domain's alert of
  the same class for up to 60 minutes, because `get_last_for_class()` has no domain filter.
- **`funding_rate_pct` display convention** (`FIELD_SEMANTICS.md`, `test-bitmomo-regime-
  adapter-contract.php` Case I — KNOWN GAP canary): the classifier's `fmt()` helper displays
  this field's raw fractional value without multiplying by 100 (so a true 1% funding rate,
  passed correctly as `0.01`, displays as "0.0%"; passed as `1.0` — a plausible integration
  mistake meaning "1%" — it validates unchanged and would score as a 100% funding rate).
  Whether to fix the display helper, or to make the input contract stricter about which
  convention is required, is a decision that touches the classifier's public output text —
  flagged, not changed.
- **`momentum_score`/`volatility_change` range enforcement** (`FIELD_SEMANTICS.md`,
  `test-bitmomo-regime-adapter-contract.php` Case H — KNOWN GAP canary): a comment claims
  `momentum_score` is bounded -100..100, but `Bitmomo_Regime_Input::validate()` never enforces
  either field's range. Whether to add that enforcement (and what happens to a Codex adapter
  that has been silently relying on the current permissiveness) is the founder's call.
- **Watchtower event `occurred_at` format** (`DETECTOR_INTERFACE.md`,
  `test-bitmomo-watchtower-detector-contract.php` Case H — KNOWN GAP canary): no format is
  enforced on this field today, even though `Bitmomo_Watchtower_Deduplicator::cluster()`
  depends on `strtotime()` parsing it correctly for its 30-minute rolling-window clustering.
  A malformed value currently passes validation and silently produces `strtotime()`'s fallback
  behavior. Whether to add a strict format requirement is a decision for whoever builds the
  detector layer, made explicit here so it isn't discovered by surprise later.
- **Unbounded DB growth** (`STATIC_SECURITY_AUDIT.md` finding 7): no retention policy exists
  for any of the three append-only CPTs. How much history to keep, and whether/how to prune it,
  is an operational decision, not a code defect.
- **`bitmomo-pro`'s undefined "canonical Bitmomo AI record"** (`CANONICAL_FIELD_MAPPING.md`):
  the `bitmomo_pro_available_source_payload` filter that's meant to carry a canonical upstream
  record has no defined shape anywhere in inspectable `bitmomo-pro` code — only
  `source_record_id` is ever read from it. If/when Watchtower's future thesis-synthesis step
  wants to consume this, its shape needs to be defined first, by whoever owns `bitmomo-pro`.

## Standing constraints that applied throughout (and still apply to Codex's runtime work)

- No claim that either product is "live" — 24/7 monitoring, market regime tracking, 11 AI
  agents, Telegram alerts — until its runtime is actually connected and verified on staging.
  Today, both plugins render their honest "no data yet" state if activated as-is.
- No dependency was ever added from either new plugin back into `bitmomo-pro`, `bitmomo-ai`, or
  the theme, and none should be added going forward — the architecture is DATA/EVENTS → these
  intelligence engines → canonical state → presentation/Pro consumers, never the reverse. This
  phase's `CANONICAL_FIELD_MAPPING.md` and `STATIC_SECURITY_AUDIT.md` (finding 9) both
  independently re-confirm zero cross-plugin code dependency exists today.
- No LLM call exists anywhere in either plugin as built. Where the original spec anticipated
  one eventually (Watchtower's selective analyst router, its thesis-synthesis step), the build
  deliberately stopped at the routing table / clean interface, not the agent itself.
- None of the explicit non-goals from the original engineering-phase brief, or this hardening
  phase's brief, were touched: payment, membership, email, homepage redesign, affiliate
  content, ETH/altcoin intelligence, mobile app, WhatsApp, public performance leaderboard,
  Hostinger, production, staging, the theme, or live Telegram/external feeds.

## What's NOT in this handoff

Neither product's own contract document, nor this summary, is a project plan or a timeline —
they describe the technical handoff surface only. Sequencing, prioritization, and staging
deployment logistics are Codex's/the founder's call. The "Known gaps"/"FOUNDER MUST APPROVE"
items above are deliberately reported, not resolved — per this phase's explicit brief, fixing
them would mean retuning policy or adding new capability, neither of which was in scope.

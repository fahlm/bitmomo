# Watchtower Thesis — Canonical Bitmomo Field Mapping

**Scope:** the Integration Hardening Phase brief asked for an audit confirming
`Bitmomo_Watchtower_Thesis`'s fields (`includes/class-bitmomo-watchtower-thesis.php`) line up
semantically with "the Bitmomo Pro / canonical intelligence concepts": regime, bias,
confidence, expected range, invalidation, major driver, source record id, updated timestamp.
This document is that audit and mapping. **No cross-plugin dependency was introduced** — this
plugin still imports nothing from `bitmomo-pro`; this is a documentation-only comparison,
produced by reading `bitmomo-pro`'s source directly (not by adding a `require` or a class
reference).

## Important caveat before the table: what "canonical" actually means today

The brief's phrase "Bitmomo Pro / canonical intelligence concepts" turns out to refer to two
different things in the actual `bitmomo-pro` codebase, and they are not the same schema:

1. **`bm_pro_brief`** (`Bitmomo_Pro_Briefs::fields()`) — a manually-authored, editorial
   content type. A human fills these fields in wp-admin today; nothing computes them
   automatically. This is the schema with concrete, named fields in code, and is what this
   mapping table compares against.
2. **The canonical Bitmomo AI record** — referenced only as a WordPress filter,
   `bitmomo_pro_available_source_payload`, that `bitmomo-pro` reads `source_record_id` from
   (see `class-bitmomo-pro-daily.php`'s "Canonical Source" panel) once "Codex's canonical
   adapter" is wired up. **As of this audit, no code defines this payload's full shape** —
   `bitmomo-pro` only ever reads one key off it (`source_record_id`) to decide whether a
   one-click brief-prefill draft can be created. It is not yet a real, implemented schema
   anywhere accessible to this audit.

So this mapping is necessarily anchored to (1), the `bm_pro_brief` field set, as the best
available concrete proxy for "canonical Bitmomo field" — not to (2), which does not exist as
inspectable code yet. If/when Codex's canonical adapter defines the real payload shape behind
`bitmomo_pro_available_source_payload`, this mapping should be revisited against that instead.

## The mapping

| Canonical concept (brief's wording) | `bm_pro_brief` field (`bitmomo-pro`) | `Bitmomo_Watchtower_Thesis` field | Match? |
|---|---|---|---|
| Regime | *(none)* | `regime` (`accumulation`/`expansion`/`distribution`/`capitulation`/`transition`) | **No canonical counterpart** — see note 1 |
| Bias | `market_state` (`bullish`/`neutral`/`bearish`) | `directional_bias` (`bullish`/`neutral`/`bearish`) | **Same three values**, different field name and different conceptual scope — see note 2 |
| Confidence | `confidence` (0-100) | `confidence` (0-100) | **Exact match** — same name, same 0-100 convention |
| Expected range | `expected_range_low` / `expected_range_high` | `expected_range_low` / `expected_range_high` | **Exact match** — identical field names |
| Invalidation | `invalidation` (textarea, "Thesis Invalidation") | `invalidation` (string) | **Exact match** — identical field name and concept |
| Major driver | *(none directly)* — closest are `base_scenario`/`bull_scenario`/`bear_scenario` (three separate narrative fields) | `major_driver` (single string) | **No 1:1 counterpart** — see note 3 |
| Source record ID | `source_record_id` (optional text field) | `source_record_id` (required string) | **Exact match** in name; different requiredness — see note 4 |
| Updated timestamp | `data_timestamp` ("Data / Update Timestamp") | *(not in `Thesis::REQUIRED_FIELDS`)* — `as_of` exists only at the `Bitmomo_Watchtower_Thesis_Store::set_current()` persistence layer | **Exists in both, but at different layers** — see note 5 |
| *(no canonical equivalent)* | `data_freshness_status` (`fresh`/`delayed`/`unavailable`) | *(none on the Thesis record itself)* | **Gap in Watchtower** — see note 6 |
| *(no canonical equivalent)* | `confidence_explanation`, `what_changed` | *(none stored — `what_changed` is computed dynamically by `Bitmomo_Watchtower_State_Transition_Engine`'s `diff`/`reason`, never persisted onto the thesis itself)* | Different architecture, not a gap — see note 7 |

## Notes

1. **`regime` has no canonical counterpart in `bitmomo-pro` today.** `bitmomo-pro`'s only
   top-line market read is the three-state `market_state` (bullish/neutral/bearish) — there
   is no five-state regime taxonomy (accumulation/expansion/distribution/capitulation/
   transition) anywhere in the `bitmomo-pro` source this audit could inspect. This is
   consistent with the sibling Bitmomo Market Regime plugin (`bitmomo-regime`) being a *new*
   product built in this same engineering phase — its regime concept did not previously
   exist canonically. Watchtower's `Bitmomo_Watchtower_Taxonomy::THESIS_REGIMES` deliberately
   duplicates `bitmomo-regime`'s five-value vocabulary (see that class's own docblock) rather
   than importing it, specifically so Watchtower has zero code dependency on
   `bitmomo-regime` — but there is, as of today, no *canonical* (bitmomo-pro-recognized)
   definition of "regime" for either plugin's vocabulary to be checked against. This is not a
   mismatch to fix; it is a documented absence for Codex's awareness when it eventually
   decides how (or whether) `bitmomo-pro`'s presentation layer should surface `regime` at
   all.
2. **`market_state` and `directional_bias` share exact values but not exact scope.**
   `bitmomo-pro` treats `market_state` as its single top-line "what's happening" signal — in
   a world with no `regime` concept, `market_state` is doing double duty as roughly
   "regime-ish + bias" combined into one three-way read. Watchtower's `directional_bias` is
   deliberately narrower — see `Bitmomo_Watchtower_Taxonomy`'s docblock: "REGIME answers what
   kind of market is this... BIAS answers which direction do we expect price to move" — the
   same separation-of-concerns principle `bitmomo-regime` uses. The three string values line
   up exactly (`bullish`/`neutral`/`bearish` in both), so a direct value-level mapping is
   safe; treating them as scope-equivalent concepts would not be.
3. **`major_driver` (Watchtower, single string) has no 1:1 canonical counterpart.**
   `bitmomo-pro`'s closest fields are three separate scenario narratives (`base_scenario`,
   `bull_scenario`, `bear_scenario`) — a fuller structure than Watchtower's one-line
   `major_driver`. They are not interchangeable: `major_driver` answers "what's the ONE thing
   driving this thesis right now" (used by `Bitmomo_Watchtower_State_Transition_Engine` to
   detect a `MATERIAL_CONTEXT_UPDATE` when it changes), while `base_scenario`/`bull_scenario`/
   `bear_scenario` together describe three full forward-looking narratives for a human
   subscriber to read. Collapsing one into the other would lose information in either
   direction.
4. **`source_record_id` matches in name; requiredness differs.** `bm_pro_brief` marks it
   "(optional)" explicitly in its label; `Bitmomo_Watchtower_Thesis::REQUIRED_FIELDS`
   requires it unconditionally. This is a reasonable, deliberate difference given the two
   layers' different jobs — a `bm_pro_brief` is human-authored editorial content that may
   predate any canonical source integration entirely (hence optional), while a Watchtower
   thesis record is machine-synthesized from the start and should always be traceable to
   whatever produced it (hence required). Not a mismatch to fix.
5. **The "updated timestamp" concept exists on both sides, but at different architectural
   layers.** `bm_pro_brief.data_timestamp` is a field the human author fills in directly.
   Watchtower's equivalent, `as_of`, is deliberately NOT part of
   `Bitmomo_Watchtower_Thesis::REQUIRED_FIELDS` (a caller building a candidate thesis does
   not supply it) — it is assigned by `Bitmomo_Watchtower_Thesis_Store::set_current()` at
   write time (`current_time('mysql')` by default, or an explicit override), the same way a
   database "created_at" column is assigned by the writer, not the caller. This is correct
   as designed — a candidate thesis's timestamp should reflect when it was actually
   persisted, not whatever a synthesis step happened to stamp on it — so no fix is proposed.
6. **Gap: `bitmomo-pro` has `data_freshness_status` (fresh/delayed/unavailable) as a field
   on the brief itself; Watchtower's `Thesis` record has no equivalent stored field.**
   Watchtower does have a conceptually related mechanism — `Bitmomo_Watchtower_
   State_Transition_Engine`'s `context['data_quality_degraded']` flag, which produces a
   `DATA_DEGRADED` transition and a cooldown-exempt `DATA_QUALITY` alert (see the Task 4
   `watchtower-data-degraded.json` fixture) — but that is a transition-time signal, not a
   persisted property of the thesis record the way `data_freshness_status` is a persisted
   property of the brief. **This is a real, reportable mismatch. It is NOT fixed in this PR**:
   adding a `data_freshness_status`-equivalent field to `Bitmomo_Watchtower_Thesis` would mean
   changing `REQUIRED_FIELDS`, `validate()`, every existing test fixture and test case built
   around the current field set (154 assertions across 7 files before this PR, plus this
   PR's own new fixtures/tests), and the comparison logic in
   `Bitmomo_Watchtower_State_Transition_Engine::context_changed_materially()` — that is a
   schema-migration-sized change, not a "safe and isolated" one, and the brief explicitly
   scopes this PR to auditing and fixing only concrete, isolated defects. Flagged here and in
   the Task 11 audit / final Codex handoff as a design decision for a future PR, not a bug.
7. **`what_changed` (bitmomo-pro, a stored field) vs. Watchtower's dynamically computed diff
   — different architecture, not a gap.** `bitmomo-pro`'s `what_changed` is a field a human
   author writes describing today's change from yesterday. Watchtower already computes the
   equivalent information automatically and more granularly, every time
   `Bitmomo_Watchtower_State_Transition_Engine::evaluate()` runs — `transition_type`,
   `reason`, and a field-by-field `diff.changed_fields` list — but does not persist that
   comparison onto the thesis record itself (only the new thesis's raw fields are persisted;
   the comparison lives in the transition result returned to the caller, e.g. for the alert
   outbox's `payload`). This is arguably a strictly better mechanism (automatic, exact,
   available at the moment of change) rather than a gap, so no fix is proposed — Codex's
   admin/presentation layer, if it wants a `bitmomo-pro`-style human-readable "what changed"
   line, already has everything needed to construct one from `transition.reason` and
   `transition.diff` without any change to this plugin.

## Fixes applied

**None.** Every difference found above is either (a) not a mismatch at all once the two
layers' different jobs are understood (notes 2, 4, 5, 7), (b) an absence in the canonical
side with no Watchtower-side bug to fix (note 1), or (c) a real gap that would require a
schema-migration-sized change to close safely (note 6) — outside what "fix only if safe and
isolated" authorizes in this PR. This is a reporting document, not a code change.

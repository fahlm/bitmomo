# Bitmomo Watchtower — Detector Interface Contract

**Scope:** what a future Codex-built runtime detector must produce before Product B can score
it. No detector exists in this codebase — no polling, no Binance/exchange calls, no news/RSS
fetching, nothing that reaches outside the process. This document describes the shape a
detector's output must take and points at the one real, already-tested class
(`Bitmomo_Watchtower_Event_Input::validate()`) that enforces it, plus a new pure contract test
(`tests/test-bitmomo-watchtower-detector-contract.php`) added in this PR that exercises that
enforcement from a detector-author's point of view.

## The conceptual "Event Candidate"

The engineering-hardening brief that requested this document described a detector as
conceptually producing an **Event Candidate** with these fields: `event_type`, `domain`,
`timestamp`, `magnitude`, `novelty`, `btc_relevance`, `cross_confirmation`, `thesis_impact`,
`source_quality`, `evidence`, and source identifiers. Every one of those concepts already has
a concrete, exact counterpart in `Bitmomo_Watchtower_Event_Input`'s existing schema — this
table is that mapping, so a detector author never has to guess a field name:

| Conceptual field | Real field(s) in `Bitmomo_Watchtower_Event_Input` | Required? |
|---|---|---|
| `event_type` | `event_type` — must be one of `Bitmomo_Watchtower_Taxonomy::EVENT_TYPES` (15 registered types) | Required |
| `domain` | `domain` — cross-checked against `Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_DOMAIN_MAP` for the supplied `event_type`; a mismatch is rejected outright, not silently corrected. **Recommended: omit it and let it be derived automatically** — see "Ambiguity" below. | Optional |
| `timestamp` | `occurred_at` — a string; no format is enforced (see FIELD_SEMANTICS-style caveat below) | Required |
| `magnitude` | `magnitude_score` — 0-100 | Required |
| `novelty` | `novelty_score` — 0-100 | Required |
| `btc_relevance` | `btc_relevance_score` — 0-100 | Required |
| `cross_confirmation` | `cross_confirmation_score` — 0-100 | Required |
| `thesis_impact` | `thesis_impact_score` — 0-100 | Required |
| `source_quality` | `source_quality_score` — 0-100 | Required |
| `evidence` | `headline` (short, one line) + `description` (longer free text) + `raw_metrics` (an array of the raw numbers the detector computed its scores from, e.g. `{"oi_change_pct": 12.3}`) | All three optional, all recommended |
| source identifiers | `source_id` (a stable ID for this specific record — used by the deduplicator's member list and by traceability) + `source_name` (a human-readable label for the feed/venue) | Both optional, both recommended |

Nothing in this table is a new field — every name on the right is already in
`Bitmomo_Watchtower_Event_Input::REQUIRED_FIELDS` / `OPTIONAL_FIELDS`. This document adds no
code and changes no schema; it only gives Codex a fixed vocabulary bridge between "what a
detector conceptually produces" and "what this plugin's real validate() function checks."

## What "producing an Event Candidate" means in practice

A detector, in Codex's future runtime, is any process that:

1. Observes something (a price feed, an exchange API, a news source, a macro calendar — none
   of which this plugin talks to) and decides an event of interest occurred.
2. Computes the six 0-100 dimension scores itself — **Product B does not compute these
   scores; the detector does.** `Bitmomo_Watchtower_Materiality_Engine` only combines
   already-scored dimensions via the fixed weighted sum in `Bitmomo_Watchtower_Config`; it
   has no opinion on how "how novel is this" gets turned into a number. That methodology is
   entirely the detector's responsibility, same as `momentum_score`/`structure_state` are
   the caller's responsibility in the sibling Bitmomo Market Regime plugin.
3. Assembles a plain associative array with the fields in the table above.
4. Calls `Bitmomo_Watchtower_Event_Input::validate($raw)`.
5. On `valid === true`, hands `$validation['input']` to
   `Bitmomo_Watchtower_Materiality_Engine::evaluate()` for scoring, and eventually to
   `Bitmomo_Watchtower_Deduplicator::cluster()` alongside other candidates.
6. On `valid === false`, **does not score the event** — `errors` names exactly what was
   wrong, and the detector should either fix its own output or drop the candidate; Product B
   will never fabricate a partial record to paper over a bad candidate.

No detector class, base class, or PHP interface is defined by this PR — deliberately, per the
"do not implement a live detector" instruction this document was written under.
`Bitmomo_Watchtower_Event_Input::validate()` (static method, plain array in/out) already IS
the interface; a real detector in a future PR would most naturally be a small class with one
method — conventionally `detect(): array[]` returning zero or more raw candidate arrays — but
that class does not exist yet and is explicitly out of this PR's scope.

## Illustrative shape (not a real class — for documentation only)

```
interface Bitmomo_Watchtower_Detector {
    /**
     * @return array[] Zero or more raw event-candidate arrays, each shaped
     *                  per the table above. Each MUST be passed through
     *                  Bitmomo_Watchtower_Event_Input::validate() by the
     *                  caller before scoring — a detector's own detect()
     *                  return value is not pre-validated.
     */
    public function detect(): array;
}
```

This is pseudocode for illustration, not a file in this codebase. A future PR that actually
builds a detector should feel free to use, ignore, or replace this shape — the only binding
contract is `Bitmomo_Watchtower_Event_Input::validate()`'s field list and behavior, not this
interface sketch.

## Ambiguities a detector author should know about before guessing

1. **`domain` — omit it rather than guess it.** Every registered `event_type` maps to exactly
   one owning `domain` in `Bitmomo_Watchtower_Taxonomy::EVENT_TYPE_DOMAIN_MAP`. Supplying a
   `domain` that doesn't match the registry is a hard validation failure (not a warning, not
   a silent correction) — `Bitmomo_Watchtower_Event_Input::validate()` will reject the entire
   candidate. Omitting `domain` entirely is always safe: it is derived automatically from
   `event_type`.
2. **`timestamp` (`occurred_at`) has no enforced format.** Any non-empty string passes.
   Detectors should use a consistent, sortable format — ISO-8601 UTC (matching every fixture
   in this PR, e.g. `"2026-08-20T14:32:00Z"`) — because `Bitmomo_Watchtower_Deduplicator`
   parses it with PHP's `strtotime()` to build its clustering window; a format `strtotime()`
   can't parse will silently produce a `false`/epoch-adjacent timestamp rather than an error,
   corrupting clustering order. This is a real gap flagged in the Task 11 security/defect
   audit, not fixed here.
3. **The six `*_score` fields are each independently 0-100 — there is no cross-field
   consistency check.** A detector could submit `magnitude_score=100` with
   `cross_confirmation_score=0` (a huge, single-source, uncorroborated move) and Product B
   will score it exactly as the weighted formula dictates — it does not second-guess an
   internally implausible combination. That's intentional (the materiality engine is
   deliberately simple and deterministic, not a plausibility model), but a detector author
   should not expect Product B to catch a detector's own scoring bugs.
4. **`raw_metrics` is never scored, never validated for shape beyond "must be an array."** It
   exists purely for downstream traceability/debugging (e.g. an admin diagnostics screen
   showing "here's the raw OI numbers behind this score"). Sending malformed or nonsensical
   `raw_metrics` will not fail validation.
5. **There is currently no code path from a scored Event Candidate to a new Thesis.** See the
   Task 4 fixtures' `illustrative_transition` blocks and their notes — `Bitmomo_Watchtower_
   State_Transition_Engine` compares two already-built `Bitmomo_Watchtower_Thesis` records;
   nothing in this plugin turns a materiality-scored event (or a cluster of them) into a new
   thesis automatically. That synthesis step is unbuilt and is Codex's runtime work, not a
   gap in this contract.

## The contract validator/test added in this PR

`tests/test-bitmomo-watchtower-detector-contract.php` is a pure contract test (no live
detector) that builds payloads the way a future detector would, in exactly the conceptual
field order from the brief this document was written under (`event_type`, `domain`,
`timestamp`, `magnitude`, `novelty`, `btc_relevance`, `cross_confirmation`, `thesis_impact`,
`source_quality`, `evidence`, source identifiers), and asserts
`Bitmomo_Watchtower_Event_Input::validate()` accepts a valid one and clearly rejects each
named failure mode: missing required field, unknown `event_type`, a `domain`/`event_type`
mismatch, a score field out of the 0-100 range, malformed (non-numeric) score input, and an
unexpected `null` in a required field.

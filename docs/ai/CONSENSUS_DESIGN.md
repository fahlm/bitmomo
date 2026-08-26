# Bitmomo BTC Intelligence — Consensus Layer Design (V1, Reconciled)

Status: design document, prepared locally, not committed/pushed.

## Reconciliation note (2026-08-26)

PR #7's actual source has now been read directly. The live engine (`Bitmomo_AI_Signal_Engine::evaluate()`) already implements a **single-shot weighted consensus of its own** — not across 11 analysts, but across its 5 fixed axes:

```
directional_score = direction*0.35 + carry*0.15 + structure*0.30 + crowding*0.20
```

(volatility is not part of this weighted sum — it acts only as a confidence penalty when the regime is `extreme`). This is real, deterministic, hand-picked weighting that already runs daily in production-merged code. It is **not** the same layer as the one this document designs — this document's consensus layer combines 11 *analyst outputs* (each a direction/confidence/reasoning object, presumably LLM-produced), while the existing engine combines 5 *raw numeric axis scores* directly, with no analyst-level reasoning step in between. They are different layers of abstraction that happen to rhyme.

Two things follow from this:

1. **Nothing below needs to be rebuilt** — the existing engine is not a competing implementation of this design, it's a separate, already-working, coarser-grained system that could keep running in parallel (e.g. as a fast fallback, or as a sanity check the new consensus output gets diffed against) rather than being replaced outright. Whether to keep it running alongside the new 11-analyst system, retire it, or fold it in as a 12th "fast deterministic" input is a founder decision, not resolved here.
2. **The existing 35/15/30/20 axis weights are a hand-picked prior, not a validated one.** They were not derived from `VALIDATION_PLAN.md`-style outcome tracking (no such tracking exists at that granularity — see the reconciliation note in `VALIDATION_PLAN.md`). They are a useful *reference point* if a founder ever wants to hand-tune `specialization_base_weight` away from the flat 1.0 default below, but importing them silently as if they were calibrated would repeat the exact mistake this design has consistently avoided elsewhere (fabricating confidence from data that isn't there). V1 ships flat, as already designed — this note exists so the reference point isn't lost, not to justify using it yet.

## Design goals (unchanged)

Not a majority vote. The method below is a **weighted, auditable** aggregation where every number that goes into the final call can be traced back to a specific analyst output and a specific, documented weight.

## Step 1 — Convert each analyst output to a signed score (unchanged)

```
signed_score = direction_sign * (confidence / 100)
  where direction_sign: BULLISH = +1, NEUTRAL = 0, BEARISH = -1
```

## Step 2 — Compute each analyst's effective weight (unchanged)

```
weight_i = specialization_base_weight_i
           x data_quality_multiplier_i
           x historical_accuracy_multiplier_i
```

- **specialization_base_weight** — starts at **1.0 for all 11 analysts in V1**. (See reconciliation note above: the existing engine's 35/15/30/20 axis weights are a reference point for a future founder-directed re-weighting, not a starting value for V1.)
- **data_quality_multiplier** — `good = 1.0`, `degraded = 0.5`, `insufficient = 0.1`, mechanically derived from `data_quality_flag`.
- **historical_accuracy_multiplier** — defaults to 1.0 until `VALIDATION_PLAN.md`'s tracking has enough real per-analyst outcomes (see that document's reconciliation note — this now needs to be built as an extension of the existing outcome tracker, not from scratch).

## Step 3 — Weighted consensus score (unchanged)

```
consensus_score = sum(weight_i * signed_score_i) / sum(weight_i)
```

- `consensus_score > +0.15` → BULLISH · `< -0.15` → BEARISH · otherwise NEUTRAL
- `confidence = round(abs(consensus_score) * 100)`, capped at 95

## Step 4 — Disagreement handling (unchanged, still experimental)

```
disagreement = weighted_stdev(signed_score_i, weights)
```

If `disagreement` exceeds **0.35** (unchanged starting point): cap confidence at 40, add `risk_flags: ["high_disagreement_expected"]`, log the actual split.

**Explicitly flagged per this reconciliation's instructions: the 0.35 threshold and the 40 confidence cap remain experimental defaults, not calibrated values.** Nothing found in PR #7's source changes that — the live engine has no disagreement concept at all (its 4 weighted axes are always combined into one number, never checked for mutual contradiction), so there is no existing precedent to borrow here, calibrated or otherwise. These two numbers should only move once real snapshot history (Step 6 of `VALIDATION_PLAN.md`) shows they're miscalibrated in a specific, evidenced direction.

## Step 5 — Expected range (unchanged)

Weighted average of each analyst's `expected_range.low`/`.high`, widened by the Step 4 disagreement factor, never narrower than any individual analyst's stated range.

## Step 6 — Regime relevance (unchanged, still deferred to V2)

The Market Structure analyst's `trend_state` output (fed by the live `structure.state`/`state_1d` fields, see `11_ANALYST_ROLES.md`) is surfaced as context alongside the consensus; dynamic regime-adaptive weighting remains deferred to V2 pending real validation history.

## Auditability requirement (unchanged)

Every consensus run must persist each analyst's `signed_score`, `weight_i` and its three multiplier components, the raw `disagreement` value, and which analysts (if any) hit `insufficient` and were effectively excluded.

## Open founder decision surfaced by this reconciliation

Should the existing deterministic 5-axis engine (`Bitmomo_AI_Signal_Engine`) keep running independently once the 11-analyst system exists, be retired, or be folded in as an additional fast/cheap input to the consensus layer? This document does not decide that — it only makes clear the two systems are not duplicates of each other and don't need to be reconciled into one before either can proceed.

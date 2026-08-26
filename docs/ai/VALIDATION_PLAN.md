# Bitmomo BTC Intelligence — Historical Validation Plan (V1, Reconciled)

Status: design document, prepared locally, not committed/pushed. No historical results exist yet for the 11-analyst system — every metric below is a design specification for tracking that begins once the system runs.

## Reconciliation note (2026-08-26)

PR #7's actual source has now been read directly. `class-bitmomo-ai-performance.php` already implements a real, running forward-validation mechanism for the single published signal — this section replaces the earlier "unverified, sounds related" note with exactly what it does, and states precisely what's genuinely new work versus an extension.

**What already exists and runs today:**

- `settle()` runs against every `bm_btc_signal` post still marked `pending`, evaluated in a **22–27 hour window** after `_bm_generated_at` (not exactly 24h — a tolerance band; posts older than 27h get marked `window_missed` rather than force-evaluated late).
- Direction correctness uses a **fixed 0.5% move threshold** (`MEANINGFUL_MOVE_PCT`), not an ATR-based dynamic threshold: bullish/bearish calls need the price to have moved ≥0.5% in the called direction to score `correct`, ≤0.5% against it to score `incorrect`, otherwise `inconclusive`; neutral calls score `correct` if the move stayed under 0.5% either way.
- Captures `outcome_price_24h`, `outcome_high_24h`, `outcome_low_24h`, `outcome_return_pct`, `outcome_direction` (correct/incorrect/inconclusive) — a near-exact match to what this document originally specified.
- Also captures level-test outcomes this document's original draft did not have: `outcome_support_tested`, `outcome_resistance_tested`, `outcome_resistance_closed_above`, and `outcome_risk_triggered` (whether the invalidation/risk level was actually breached). These are real, working fields worth adopting into the 11-analyst output shape rather than re-inventing.
- `summary()` computes `accuracy_pct` (correct / (correct + incorrect), excluding inconclusive) over the **last 30 evaluated signals** and reports `risk_triggered` count alongside it.

**What this means for the plan below:** the 11-analyst validation system is an **extension of an existing, working pattern**, not new infrastructure. The gap is granularity — everything above tracks one signal (effectively the equivalent of "the consensus output") per day; it does not track 11 separate analyst outputs per day, and it does not store the full input package needed for replay.

## Two different "30"s — do not conflate them

`summary()`'s `posts_per_page => 30` is a **rolling display window** (always the most recent 30 evaluated signals, whatever that number represents in wall-clock time). It is not, and was never intended as, a minimum-sample-size gate. This document's own **minimum 30 snapshots before trusting any accuracy number** (see below) is a distinct, stricter concept — a floor before publishing any claim at all, not a display window size. Both concepts are kept, under their original names, so implementation doesn't accidentally merge them.

## What gets stored per snapshot (unchanged design, now explicitly the missing piece)

```
{
  package_id,
  input_package,          # the full Phase 4 input, as-fetched — NOT currently stored anywhere;
                           # the existing tracker only stores post-meta fields on the signal itself
  analyst_outputs[11],    # per ANALYST_OUTPUT_SCHEMA.json — genuinely new, no equivalent exists
  consensus_output,       # per CONSENSUS_DESIGN.md — genuinely new
  outcome: null           # populated at T+24h — the existing settle() logic is the template for this,
                           # extended to run per-analyst rather than per-signal
}
```

Storing the full input alongside outputs is what makes snapshots replayable. This is the one genuinely new piece of infrastructure required — the existing tracker stores outcome fields as WordPress post meta on the signal post itself, which works for one signal but doesn't scale to storing 11 analyst outputs plus a full input package per run; a dedicated snapshot store (custom table or a dedicated post type) is real, scoped work, not a duplication of anything that exists.

## What gets captured at T+24h — reconciled against the existing evaluation window

```
outcome: {
  price_t24, realized_high_t24, realized_low_t24,
  direction_correct: bool,
  stayed_in_range: bool,
  captured_at
}
```

**Open decision this reconciliation surfaces rather than resolves silently:** the existing `settle()` uses a fixed 0.5% move threshold; this document's original draft proposed a dynamic threshold (half of that day's ATR(1D)) specifically so accuracy scoring isn't gamed by tiny moves on low-volatility days. These are genuinely different, and only one should exist per output type:

- Keeping the 11-analyst system's `direction_correct` on the ATR-based threshold (as originally designed) means the new per-analyst tracker and the existing single-signal tracker will use **different definitions of "correct"** unless the existing tracker is also updated — a real inconsistency, not a documentation gap.
- Harmonizing them (moving the existing tracker to ATR-based, or moving the new tracker to the existing fixed 0.5%) is an implementation decision, not something to resolve by picking one silently in a docs-only pass. Recommend the founder decide this explicitly before either system is built further, since it affects how any future "our accuracy is X%" claim gets computed and whether the old and new numbers are even comparable.
- The **22–27 hour evaluation window** (vs. this plan's generic "T+24h") is a smaller, non-conflicting difference — reusing the existing tolerance band for the new per-analyst tracker is recommended rather than inventing a second one.

The existing `outcome_risk_triggered` field (was the invalidation level breached) has no equivalent in the original `ANALYST_OUTPUT_SCHEMA.json` design — recommend adding an `invalidation_price` numeric field to that schema (the current `invalidation` field is free text, not machine-checkable against a price) so each analyst's invalidation can be settled the same mechanical way the existing tracker already settles the single signal's risk level.

## Metrics tracked (once real data exists) — unchanged, now explicitly framed as an extension

- **Direction accuracy** — the existing `summary()` computes this today for one signal stream; extending it to run per-analyst and separately for the consensus output is additive work on a proven pattern, not new design.
- **Range hit rate** (`stayed_in_range`) — the existing support/resistance-test fields are close cousins of this; the exact `expected_range` concept doesn't exist today (the live engine has support/resistance zones, not a single low/high forecast range) so this piece is new, but the settlement *mechanism* (compare stored levels against realized high/low) is directly reused.
- **Calibration** (confidence-bucket reliability diagram) — confirmed genuinely new; nothing in the existing tracker buckets by stated confidence.
- **Analyst-level leaderboard** — confirmed genuinely new; the existing tracker has no per-analyst concept at all (single signal only).
- **Consensus vs. best-individual-analyst** — confirmed genuinely new, and only becomes checkable once per-analyst tracking (above) exists.

## Minimum sample size before trusting any of this (unchanged)

Do not act on or publish accuracy percentages before **at least 30 snapshots** exist (this is the strict floor described above — not `summary()`'s 30-post rolling display window), and treat anything under ~60 as a small-sample estimate. This reconciliation does not lower or relax this floor — if anything, the existing tracker's own 22–27h evaluation tolerance shows real-world outcome evaluation already has natural slack built in, reinforcing rather than undermining the case for a real sample-size floor before trusting the resulting percentage.

## How this connects to `historical_accuracy_multiplier` (unchanged)

Once 30+ snapshots exist for a given analyst: `historical_accuracy_multiplier = clamp(trailing_accuracy / 0.5, 0.5, 1.5)`. Exact bounds remain a starting point for founder review, not a final tuned constant — nothing in PR #7's source provides evidence to tune these further, since no per-analyst tracking of this kind exists yet to tune against.

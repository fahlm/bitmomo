# Bitmomo BTC Intelligence — Implementation Roadmap (V1)

Status: design document, prepared locally, not committed/pushed. No code, no theme/staging/production changes.

## Locked decisions (source of truth for every phase below)

1. **Keep** the existing 5-axis deterministic engine (`Bitmomo_AI_Signal_Engine`) as a baseline/reference model — permanently, not just during transition.
2. Do **not** replace or retire it, in any phase.
3. The 11 analysts run **alongside** it, never instead of it.
4. The existing fixed **±0.5% outcome metric** (`class-bitmomo-ai-performance.php`'s `MEANINGFUL_MOVE_PCT`) is preserved exactly as-is.
5. A **second, ATR/regime-adjusted outcome metric** is added alongside the fixed one — not a replacement.
6. **No historical weighting** of any analyst before **30 settled forecasts** exist for it.
7. At 30 forecasts, weighting is **exploratory only** and must stay **narrow** (tight bounds, not the full range a mature dataset would justify).
8. **No strong public accuracy claim** is made from 30 observations alone.
9. **Liquidation data remains deferred** — no phase below reintroduces it.

These carry forward from `docs/ai/DATA_ARCHITECTURE.md`, `11_ANALYST_ROLES.md`, `CONSENSUS_DESIGN.md`, and `VALIDATION_PLAN.md` (all reconciled 2026-08-26 against the real, merged PR #7 source at `origin/main` commit `adea0a5`). This document sequences their implementation; it does not re-derive their content.

---

## PHASE 1 — Normalize existing output (no new external data)

**Objective:** Build a package-normalization layer that reshapes `Bitmomo_AI_Binance::snapshot()`'s live output into the `ANALYST_INPUT_SCHEMA.json` v1.1.0 shape (the field renames/splits already specified in the reconciled `DATA_ARCHITECTURE.md` — `atr_pct` not `atr14`, separate `direction`/`structure` blocks instead of one collapsed `trend_state`, etc.), plus the handful of pure-computation fields that need zero new fetches (1h/4h/7d returns, the momentum rate-of-change series). Nothing is fetched that isn't already fetched today.

- **Existing components reused:** `Bitmomo_AI_Binance::snapshot()` in full; `Bitmomo_AI_Signal_Engine::evaluate()`'s output, kept as the baseline reference record; the existing transient cache pattern; `quality.*` fields as the source for `freshness`/`missing_data_flags`.
- **New components required:** one package-builder function (`build_analyst_input_package()` or similar) that wraps `snapshot()` and reshapes its return value; a `package_id` generator; the small set of pure-derived fields (returns across timeframes, rate-of-change for Momentum) computed once alongside the reshape.
- **External API calls added:** **zero.**
- **Expected LLM calls:** **zero** — this phase is pure data transformation, no analyst exists yet.
- **Token-cost risks:** none yet. Note for later phases: this is the layer that enforces "core + slice only" per analyst — get the shape right here so nothing downstream over-sends fields.
- **Validation gate:** every normalized package validates against `ANALYST_INPUT_SCHEMA.json`; run the builder against several consecutive real daily snapshots and diff normalized fields back against the raw `snapshot()` output field-by-field to catch mapping bugs before anything depends on this layer.
- **Rollback/failure behavior:** trivial — the builder is not wired into anything that publishes or schedules; if validation fails, it simply isn't used yet. Zero blast radius.
- **Dependencies on production V1:** read-only dependency on `snapshot()`'s existing return shape. If Codex changes that shape later, this layer needs a matching update — a contract risk to track, not a blocker today.

---

## PHASE 2 — First analyst subset on existing data only

**Objective:** Implement and run the 6 analyst roles whose full field slice is already **EXISTING/REUSE** or a zero-new-call derived computation, per `11_ANALYST_ROLES.md`: **Trend & Direction, Volatility, Momentum, Market Structure, Derivatives Positioning, Basis & Carry**. Each produces an `ANALYST_OUTPUT_SCHEMA.json`-shaped record. Still nothing publishes, nothing is wired to the live site.

- **Existing components reused:** Phase 1's package builder; `quality.*` → `data_quality_flag` derivation; `carry.data_status` (Binance vs Bybit fallback) surfaced so Derivatives Positioning / Basis & Carry can self-report `degraded` honestly when running on the fallback.
- **New components required:** 6 analyst prompt templates (core 6 fields + named slice only, per the cost-control principles already established); a per-analyst runner/dispatcher with schema-forced output (StructuredOutput-style, not free text parsing); a bounded retry policy (1 retry on schema-validation failure, then fall back to `data_quality_flag: insufficient` rather than retrying indefinitely); ephemeral run logging for review (not yet the persistent snapshot store — that's Phase 4/5).
- **External API calls added:** **zero** — same Phase 1 package, fetched once, reused across all 6 analysts.
- **Expected LLM calls:** **6 per run**, at the existing daily cadence (19:10 WIB) — no new schedule introduced.
- **Token-cost risks:** compact prompts keep each call small by design; the two real risks are (a) reasoning unbounded by the model despite the 400-char cap — mitigate with schema-forced/tool-call output, not a length instruction alone — and (b) retry storms on repeated schema failures — mitigate with the 1-retry-then-degrade policy above.
- **Validation gate:** 100% of outputs validate against `ANALYST_OUTPUT_SCHEMA.json`; each output's `direction`/`invalidation`/`expected_range` pass the same internal-consistency check style `Bitmomo_AI_Quality_Gate` already applies to the baseline signal (e.g. a BULLISH call with an invalidation above the reference price is a contradiction, reject and log it); a manual founder spot-check of a handful of outputs for plausibility before Phase 3 begins.
- **Rollback/failure behavior:** analysts run completely outside the publish path — not wired to `bm_btc_signal` or the editorial gate in any way. Disabling the runner (a single manual/cron trigger) fully stops the work with zero effect on the live site.
- **Dependencies on production V1:** none beyond Phase 1's read-only dependency. `BITMOMO_AI_AUTO_PUBLISH` and the existing editorial gate remain completely untouched and irrelevant to this work.

**Recommended sequencing within Phase 2** (see the ranked table below): implement **Volatility, Market Structure, Trend & Direction** first (wave 1 — cheapest, most diverse, richest already-computed fields), then **Momentum, Derivatives Positioning, Basis & Carry** (wave 2 — cheap but each has a small additional wrinkle: Momentum needs its derived field built first, Derivatives/Basis & Carry need the fallback-honesty handling).

---

## PHASE 3 — Add the minimum missing data for the remaining 5 roles

**Objective:** Add exactly the confirmed-missing data identified in `DATA_ARCHITECTURE.md` — nothing more — and implement the remaining 5 analysts: **Spot vs Perp Flow, Cross-Market Context, Crowding & Sentiment** (completed with real Fear & Greed data, having run in Phase 2's scope only partially if at all — see note below), **Historical Pattern/Regime** (lite version, using only the already-live `volatility.regime` label — the deeper snapshot-history version is out of scope until Phase 5+ accumulates real history), and **Event & Narrative**. All 11 roles exist after this phase.

*Note: Crowding & Sentiment was deliberately excluded from Phase 2's "existing-data-only" set because its `data_quality_flag` would otherwise have to permanently under-report (missing Fear & Greed) — cleaner to implement it once, fully, here.*

- **Existing components reused:** Phase 1 package builder (schema extended, not rebuilt); Phase 2's analyst runner/dispatcher pattern and prompt-template structure; the existing transient cache pattern, applied identically to the 3 new external calls below.
- **New components required:** a parallel (not failover) Binance spot-klines fetch; a CoinGecko global-market client; an alternative.me Fear & Greed client; a manually-curated `event-calendar.json` plus a date-window lookup function (capped to, e.g., the next 7 days per run — enforced at package-build time, not left to the LLM to self-limit); 5 new analyst prompt templates; package schema additions for `flow.spot_*`, `cross_market.*`, `event_context[]`.
- **External API calls added:** **3** — Binance spot klines (parallel), CoinGecko global market, alternative.me Fear & Greed. All free, no key, same once-per-run cadence as everything else.
- **Expected LLM calls:** **+5 per run**, bringing the total to **11 per run** — still once daily, no cadence change.
- **Token-cost risks:** `event_context` is free text, not numeric — the real risk is unbounded growth as the calendar file accumulates entries over time. Mitigated by the hard date-window cap above, enforced in the package builder so a bloated calendar file can never silently inflate every analyst's prompt.
- **Validation gate:** each new external source must degrade the same way the existing Binance/Bybit calls already do — verified by deliberately simulating a CoinGecko/alternative.me outage and confirming the package still builds, with `missing_data_flags` populated rather than the whole run failing; all 11 analysts (not just the 5 new ones) re-validated against schema and internal-consistency checks now that the package shape has grown.
- **Rollback/failure behavior:** each new external source gets its own independent fail-soft path (mirroring `quality.optional_missing`) so one dead endpoint degrades only its own analyst(s), never blocks the other 10. Still fully decoupled from the publish path.
- **Dependencies on production V1:** none new — still read-only, still unwired from publishing.

---

## PHASE 4 — Shadow mode, all 11 analysts, zero public effect

**Objective:** Run the complete 11-analyst pipeline on the existing daily cron cadence, sequenced **after** (not concurrent with) the baseline engine's normal run, storing every output for review — never publishing, never influencing `bm_btc_signal` or any public page.

- **Existing components reused:** the existing WP-Cron trigger and its 19:10 WIB timing (piggybacked, not duplicated — avoids introducing a second schedule to reason about); the baseline engine's publish path, completely untouched; the `BITMOMO_AI_AUTO_PUBLISH`-style kill-switch pattern, mirrored for shadow mode.
- **New components required:** a shadow-run orchestrator sequencing all 11 analyst calls with a concurrency/rate cap; an internal-only admin view (alongside the existing "Manual staging test" under Tools → Bitmomo AI) to inspect shadow outputs; a `BITMOMO_AI_SHADOW_MODE` config flag, default state chosen for safety (off until explicitly enabled).
- **External API calls added:** **zero** beyond Phase 1–3 — one package fetched once, reused across all 11 analysts, per the existing "fetch once, compute once" principle.
- **Expected LLM calls:** **11 per run**, unchanged from Phase 3 — this phase changes where results go, not how many calls happen.
- **Token-cost risks:** call volume is unchanged from Phase 3, so the main new risk is *ad hoc* manual triggers during review (each manual "run shadow mode now" click costs a full 11-call pass) — mitigate by rate-limiting the manual trigger the same way the existing manual staging test is already scoped/limited.
- **Validation gate:** at least 5–10 consecutive daily shadow runs complete with zero pipeline failures (a reliability gate, distinct from the accuracy sample-size gate in Phase 5/6) before Phase 5 starts treating the collected data as trustworthy.
- **Rollback/failure behavior:** `BITMOMO_AI_SHADOW_MODE` is a one-line kill switch, same shape as the existing auto-publish flag. Because shadow mode never writes to `bm_btc_signal` or any public post type, no failure mode here can reach the live site — this is a structural guarantee, not a policy one.
- **Dependencies on production V1:** the first real dependency in this roadmap — shares (and must respect) the existing cron's timing to avoid resource contention on shared hosting. Still zero dependency on the baseline engine's content/publish path.

---

## PHASE 5 — Collect per-analyst + baseline outcomes (dual metric)

**Objective:** Extend `class-bitmomo-ai-performance.php`'s `settle()`/`summary()` pattern to run per-analyst *and* for the baseline engine's own signal, computing **both** outcome metrics side-by-side per decisions #4–#5: the existing fixed ±0.5% rule, unchanged, and a new ATR/regime-adjusted rule (threshold = half that day's ATR(1D), per the original `VALIDATION_PLAN.md` design) — so baseline and every analyst are judged on identical terms, twice.

- **Existing components reused:** the exact `settle()` mechanism and its 22–27h evaluation window (kept rather than inventing a second window, per the `VALIDATION_PLAN.md` reconciliation); the existing outcome-field shape (`outcome_price`, `high/low`, `return_pct`, support/resistance-tested, risk-triggered) as the template for the new per-analyst outcome record.
- **New components required:** a snapshot store (the one genuinely new piece of infrastructure identified in `VALIDATION_PLAN.md`) holding `package_id` + all 11 analyst outputs + the baseline engine's own output + `outcome: null` until settled; a dual-metric settlement function producing `outcome_direction_fixed` (verbatim existing rule) and `outcome_direction_atr_adjusted` (new rule) for every tracked output, analyst and baseline alike.
- **External API calls added:** **zero** — settlement only needs the reference price and 24h high/low, already captured in the Phase 1 package's `outcome_window`.
- **Expected LLM calls:** **zero** — pure data collection and arithmetic, no generation.
- **Token-cost risks:** none directly. Indirect: the snapshot store grows unbounded over time — mitigate with a stated retention policy (archive raw `input_package` payloads after N months, keep outcome summaries indefinitely) rather than a silent truncation.
- **Validation gate:** the new dual-metric settlement must reproduce the *existing* single-metric `settle()`'s fixed-0.5% result exactly for the baseline engine's own signal — a regression check. If the new pipeline disagrees with the original `settle()` on the same signal, the new pipeline has a bug, full stop, before any per-analyst number is trusted.
- **Rollback/failure behavior:** settlement is a background/read process over stored, replayable snapshots — a bug here affects only internal reporting, never the live site, and can be safely re-run from scratch at any time since raw inputs+outputs are stored (this is exactly why the Phase 5 snapshot store exists).
- **Dependencies on production V1:** read-only, same as the existing `settle()` — depends on 24h-later market data (reachable through the same Phase 1 package builder), no write dependency on production content.

---

## PHASE 6 — Consensus/meta layer, gated by sample size

**Objective:** Implement `CONSENSUS_DESIGN.md`'s weighted aggregation across the 11 shadow analysts — still shadow-only, never published — strictly gated by decisions #6–#8: flat weighting (no historical adjustment at all) until **30 settled forecasts** per analyst exist; at 30, `historical_accuracy_multiplier` may move but only within a deliberately **narrow** band; no accuracy claim, internal or public, is made confidently from 30 observations alone.

- **Existing components reused:** `CONSENSUS_DESIGN.md` Steps 1–5 exactly as designed (signed score → weight → weighted consensus score → disagreement handling → expected range), `specialization_base_weight = 1.0` flat throughout this phase — the existing 5-axis engine's 35/15/30/20 weights remain a **documented reference point only**, never imported as a starting value, per that document's own reconciliation note.
- **New components required:** the consensus computation itself (genuinely new — nothing like it exists yet); a **structural** sample-size gate (`if settled_count < 30: historical_accuracy_multiplier = 1.0`, enforced in code, not left as a policy note); a narrow-bound override for the 30–60 sample range specifically (tighter than the full `clamp(trailing_accuracy/0.5, 0.5, 1.5)` range — e.g. a founder-reviewable tighter clamp such as ±0.15 around 1.0 at exactly-30 samples, widening only well past 60); an internal-only comparison report (consensus vs. baseline engine vs. best-individual-analyst), never public-facing.
- **External API calls added:** **zero.**
- **Expected LLM calls:** **zero** — consensus is arithmetic over already-generated Phase 4 outputs, per the original design.
- **Token-cost risks:** none directly. The real risk here is organizational, not technical: publishing a consensus output or accuracy figure before the sample-size gate clears. Mitigated by enforcing the gate in code (see above) rather than trusting it to remain a followed convention.
- **Validation gate:** minimum 30 settled per-analyst forecasts (both metrics, from Phase 5) before `historical_accuracy_multiplier` moves at all; the narrow-band override active from 30–60 samples; Step 5's reliability/calibration diagram (per `VALIDATION_PLAN.md`) reviewed before any accuracy language — even internal — is described with confidence.
- **Rollback/failure behavior:** additive/read-only over Phase 4/5's stored outputs — disabling the consensus layer has zero effect upstream. If the sample-size gate is ever found to have been bypassed, the fix is a constant/config change, not a data migration, since every raw analyst output remains stored and replayable.
- **Dependencies on production V1:** none new — the only production dependency across all six phases remains Phase 4's shared cron timing.

---

## Analyst-role sequencing rationale (Phase 2, ranked)

| Role | Existing-data coverage | Signal diversity | Implementation simplicity | Token efficiency | Verdict |
|---|---|---|---|---|---|
| **Volatility** | Complete — 5 fields, richest already-computed block | High — orthogonal to direction, needed context for every other role's `expected_range` | High — numeric/enum, near-mechanical | High — small slice | **Wave 1** |
| **Market Structure** | Complete — richer than originally designed (operational S/R + status labels) | High — provides the price-level/invalidation context other roles' risk output should stay consistent with | High, though status-label reasoning needs slightly more prompt care | High, but largest slice of the three (9 fields) | **Wave 1** |
| **Trend & Direction** | Complete — ADX/DI/bias fields ready as-is | Moderate — closely echoes the baseline engine's own 35%-weighted direction axis | High — minimal transformation | High — small slice | **Wave 1** |
| **Momentum** | Zero new API call, but needs one new derived computation built first | High — genuinely distinct from Trend & Direction (rate-of-change vs. bias) | Moderate — small dependency (the derived field) before the analyst itself | High, once built | **Wave 2** |
| **Derivatives Positioning** | Good — funding/OI/z-score/long-short ratio all live | High — a genuinely different lens (leverage/positioning vs. price action) | Moderate — must honestly self-report `degraded` on the Bybit-fallback path | High — small slice | **Wave 2** |
| **Basis & Carry** | Good, but shares `funding_rate` with Derivatives Positioning | Lower marginal diversity if run alongside Derivatives Positioning (overlap is intentional per design, not a flaw) | Highest — only 2 fields, simplest prompt of all 11 | Highest — smallest slice of any role | **Wave 2** |

**Recommendation:** implement **Volatility, Market Structure, Trend & Direction** first (Phase 2, wave 1) — the trio that is simultaneously cheapest, most field-complete, and most mutually diverse in signal. Follow immediately with **Momentum, Derivatives Positioning, Basis & Carry** (Phase 2, wave 2) to complete the existing-data-only set before Phase 3 adds any new external call.

# Codex Handoff — Bitmomo Intelligence Products (Market Regime + Watchtower)

This is the combined summary requested at the end of the engineering
phase that built two new, fully isolated intelligence products in
parallel with Codex's runtime/deployment work on the existing Bitmomo
stack. It indexes the two detailed, product-specific handoff documents
rather than repeating them — read those for the actual step-by-step
checklists:

- **Product A (Bitmomo Market Regime):** [`website/wp-content/plugins/bitmomo-regime/CODEX_INTEGRATION_CONTRACT.md`](../website/wp-content/plugins/bitmomo-regime/CODEX_INTEGRATION_CONTRACT.md)
- **Product B (Bitmomo Watchtower):** [`website/wp-content/plugins/bitmomo-watchtower/CODEX_INTEGRATION_CONTRACT.md`](../website/wp-content/plugins/bitmomo-watchtower/CODEX_INTEGRATION_CONTRACT.md)

## What was built, and where

Two new, fully isolated WordPress plugins — each its own PR sequence,
each with zero dependency on `bitmomo-pro`, `bitmomo-ai`, the active
theme, or each other:

| Product | Plugin | PRs | Status |
|---|---|---|---|
| A — Market Regime | `website/wp-content/plugins/bitmomo-regime/` | [#40](https://github.com/fahlm/bitmomo/pull/40), [#41](https://github.com/fahlm/bitmomo/pull/41), [#42](https://github.com/fahlm/bitmomo/pull/42) | Core sequence complete |
| B — Watchtower | `website/wp-content/plugins/bitmomo-watchtower/` | [#43](https://github.com/fahlm/bitmomo/pull/43), [#44](https://github.com/fahlm/bitmomo/pull/44), [#45](https://github.com/fahlm/bitmomo/pull/45), [#46](https://github.com/fahlm/bitmomo/pull/46) | Core sequence complete |

Both are deterministic, no-LLM, no-black-box classification/scoring
engines with full test suites (Product A: 87 assertions across 3 test
files, all passing; Product B: 154 assertions across 7 test files, all
passing). Both persist their history in private, headless, append-only
custom post types — nothing publicly queryable, nothing in REST. Neither
plugin fetches live data, runs on a schedule, or sends anything to
anyone. Both stop exactly at "here is a clean, tested, documented
callable — a runtime layer needs to invoke it."

## The one-sentence version of what Codex must do for each

- **Product A:** connect a real market-data feed matching
  `Bitmomo_Regime_Input`'s contract, schedule a daily call to
  `Bitmomo_Regime_State_Store::evaluate_and_record()`, place the
  `[bitmomo_market_regime]` / `[bitmomo_market_regime_history]`
  shortcodes somewhere (Homepage V2 or elsewhere — not decided by this
  PR sequence), and verify the WordPress-dependent classes on staging.
- **Product B:** build live event detectors and the scoring/clustering/
  thesis-synthesis pipeline (no LLM), schedule a call to
  `Bitmomo_Watchtower_Orchestrator::process()`, build a Telegram adapter
  around `Bitmomo_Watchtower_Alert_Outbox::get_queued()`/`mark_status()`,
  and verify the WordPress-dependent classes on staging.

Full detail, exact method signatures, and explicit no-overclaim
guardrails are in each product's own contract document linked above.

## Standing constraints that applied throughout (and still apply to Codex's runtime work)

- No claim that either product is "live" — 24/7 monitoring, market
  regime tracking, 11 AI agents, Telegram alerts — until its runtime is
  actually connected and verified on staging. Today, both plugins render
  their honest "no data yet" state if activated as-is.
- No dependency was ever added from either new plugin back into
  `bitmomo-pro`, `bitmomo-ai`, or the theme, and none should be added
  going forward — the architecture is DATA/EVENTS → these intelligence
  engines → canonical state → presentation/Pro consumers, never the
  reverse.
- No LLM call exists anywhere in either plugin as built. Where the
  original spec anticipated one eventually (Watchtower's selective
  analyst router, its thesis-synthesis step), this PR sequence
  deliberately stopped at the routing table / clean interface, not the
  agent itself.
- None of the explicit non-goals from the original engineering-phase
  brief were touched: payment, membership, email, homepage redesign,
  affiliate content, ETH/altcoin intelligence, mobile app, WhatsApp,
  public performance leaderboard.

## What's NOT in this handoff

Neither product's own contract document, nor this summary, is a project
plan or a timeline — they describe the technical handoff surface only.
Sequencing, prioritization, and staging deployment logistics are
Codex's/the founder's call.

# Bitmomo Market Regime (Product A) — Codex Integration Contract

This document is the handoff for Product A only (Bitmomo Watchtower/Product
B has its own contract once it exists). It describes exactly what Codex
must connect for this plugin's runtime to go live — nothing here is done
yet, and nothing in this repo claims otherwise.

## What exists in the repo right now (PR 1–3)

- A deterministic classifier (`Bitmomo_Regime_Classifier`) that turns a
  normalized input array into a regime + confidence + evidence.
- A hysteresis layer (`Bitmomo_Regime_Hysteresis`) that turns a raw
  classification into an official regime change decision.
- An append-only persistence layer (`Bitmomo_Regime_State_Store`) with the
  single orchestration entrypoint:
  `Bitmomo_Regime_State_Store::instance()->evaluate_and_record( $raw_input, $as_of = null )`.
- A frontend-safe 30-day read projection (`Bitmomo_Regime_History`) and two
  shortcodes: `[bitmomo_market_regime]`, `[bitmomo_market_regime_history days="7|14|30"]`.
- A `manage_options`-gated admin diagnostics screen ("Bitmomo Regime" in
  the WP admin menu).

## What Codex must connect

1. **A real data feed producing the normalized input contract.**
   See `Bitmomo_Regime_Input::REQUIRED_FIELDS` / `OPTIONAL_FIELDS` in
   `includes/class-bitmomo-regime-input.php` for the exact field list
   (returns across windows, realized volatility + change, volume,
   momentum, price-vs-range position, structure/breakout state, OI
   change, funding, basis, liquidations, crowding, and the existing
   directional bias signal). Product A intentionally does NOT fetch
   ETF flows, macro data, or news — that is Product B's domain.

2. **A scheduler that calls `evaluate_and_record()`.** No cron is
   registered anywhere in this plugin. Codex needs to wire either
   WP-Cron or a system cron (recommended: once daily, matching the
   "30-day daily state" design) that builds the raw input array from
   step 1 and calls:
   ```php
   Bitmomo_Regime_State_Store::instance()->evaluate_and_record( $raw_input );
   ```
   Check the returned `{success, errors, record}` — `success=false` means
   validation failed closed (see `errors`); no partial record is ever
   written in that case.

3. **Staging verification of the WordPress-dependent seam.** Only
   `Bitmomo_Regime_State_Store`, `Bitmomo_Regime_Shortcodes`, and
   `Bitmomo_Regime_Admin_Diagnostics` touch WordPress; everything else in
   this plugin is pure PHP and already covered by the deterministic test
   suites in `tests/`. On staging, confirm:
   - `bm_regime_state` posts are created correctly by `evaluate_and_record()`.
   - `get_latest()` / `get_recent()` return hydrated records with all 16
     meta fields intact (including that JSON-encoded fields — evidence,
     conflicts, input_summary — decode back to arrays, not strings).
   - The two shortcodes render correctly once real data exists, including
     the "N of 30 days collected" note while history is still building.
   - The admin diagnostics screen (`wp-admin` → "Bitmomo Regime") shows
     real evidence/conflicts and the previous-state comparison correctly
     once at least two records exist.

4. **Placement of the shortcodes.** This PR deliberately does not touch
   any theme file or the homepage. Where `[bitmomo_market_regime]` and
   `[bitmomo_market_regime_history]` actually get placed (Homepage V2, a
   dedicated page, a widget area) is a founder/Codex decision, not made
   here.

## What Codex must NOT do based on this PR

- Do not claim "market regime tracking is live" anywhere in marketing or
  homepage copy until step 2 (the scheduler) is actually connected and
  verified on staging — an installed-but-unscheduled plugin produces no
  data and both shortcodes render their honest "no data yet" state.
- Do not infer directional bias from regime, or vice versa, anywhere
  downstream — they are stored and displayed as two separate fields by
  design.
- Do not modify `includes/class-bitmomo-regime-config.php` thresholds
  without bumping `Bitmomo_Regime_Config::CLASSIFIER_VERSION` — old
  history must remain attributable to the ruleset that actually produced
  it.

## Explicitly out of scope for Product A (do not add here)

ETF flows, macro/news data, payment/membership, email, homepage redesign,
affiliate content, ETH/altcoin intelligence, mobile app, WhatsApp, a
public performance leaderboard, and any dependency on `bitmomo-pro`,
`bitmomo-ai`, or the active theme. Product A's engine remains fully
isolated in `website/wp-content/plugins/bitmomo-regime/`.

# Bitmomo Watchtower (Product B) — Codex Integration Contract

This document is the handoff for Product B only (Product A/Bitmomo Market
Regime has its own contract, written at the end of REGIME PR 3). It
describes exactly what Codex must connect for this plugin's runtime to
go live — nothing here is done yet, and nothing in this repo claims
otherwise. As with Product A: 24/7 monitoring, material-change alerts,
and any "N agents are live" claim must not appear anywhere in marketing
or homepage copy until every item below is actually connected and
verified on staging.

## What exists in the repo right now (WATCHTOWER PR 1–4)

- A canonical event candidate schema and a controlled 15-type event
  registry across 8 domains (`Bitmomo_Watchtower_Event_Input`,
  `Bitmomo_Watchtower_Taxonomy`) — registered, not detected. No detector
  exists for any of the 15 types.
- A deterministic, no-LLM materiality engine
  (`Bitmomo_Watchtower_Materiality_Engine`) scoring a validated candidate
  0–100 across six weighted dimensions into one of four policy bands.
- Deterministic deduplication/clustering
  (`Bitmomo_Watchtower_Deduplicator`) so repeated reports of one
  underlying event become one cluster, not N alerts.
- A current thesis/state model and its append-only persistence
  (`Bitmomo_Watchtower_Thesis`, `Bitmomo_Watchtower_Thesis_Store`) —
  deliberately independent of `bitmomo-regime`'s vocabulary.
- A deterministic state transition engine
  (`Bitmomo_Watchtower_State_Transition_Engine`) classifying NO_CHANGE /
  MATERIAL_CONTEXT_UPDATE / CONFIDENCE_CHANGE / THESIS_CHANGE /
  THESIS_INVALIDATED / DATA_DEGRADED.
- A routing-table-only selective analyst router
  (`Bitmomo_Watchtower_Analyst_Router`) — no agent exists behind any
  track, no LLM is called.
- A cooldown/alert policy (`Bitmomo_Watchtower_Alert_Policy`) mapping
  transitions to 5 alert classes, and a provider-neutral, append-only
  alert outbox (`Bitmomo_Watchtower_Alert_Outbox`) — every alert stays
  `queued`; nothing here sends anything anywhere.
- An orchestration entrypoint tying all of the above together:
  `Bitmomo_Watchtower_Orchestrator::instance()->process( $raw_thesis, $context )`.
- A `manage_options`-gated admin diagnostics screen ("Bitmomo Watchtower"
  in the WP admin menu) — debug/trust view only.

## What Codex must connect

1. **Live detectors producing validated event candidates.** For each of
   the 15 registered event types Codex chooses to implement (the spec
   does not require all 15 on day one), build a detector that outputs
   data matching `Bitmomo_Watchtower_Event_Input::REQUIRED_FIELDS` and
   run it through `Bitmomo_Watchtower_Event_Input::validate()`. No
   detector exists in this repo for any type.

2. **A scoring + clustering pipeline.** Validated candidates go through
   `Bitmomo_Watchtower_Materiality_Engine::evaluate()`, then batches of
   scored candidates go through `Bitmomo_Watchtower_Deduplicator::cluster()`.
   **Neither scored candidates nor clusters are persisted anywhere in
   this plugin** — there is no event/cluster store. This is a deliberate
   PR1–4 scope boundary, not an oversight: Codex's runtime layer is
   expected to hold this intermediate state (or a future PR can add a
   store) before deciding what candidate thesis to propose.

3. **A process for turning clusters into a candidate thesis.** Nothing
   in this plugin computes a thesis from clusters — that synthesis step
   (how do these event clusters translate into a regime/bias/confidence/
   expected-range view) is explicitly Codex's runtime responsibility
   (or a future PR's), and per the original spec, must NOT be an LLM call
   in this first integration ("no LLM to manufacture the new thesis").

4. **The two context flags for `Orchestrator::process()`.** This plugin
   cannot itself observe live price or data-quality state:
   - `invalidation_triggered` (bool): whether the CURRENT thesis's
     stored `invalidation` condition was actually met — requires
     comparing live price data against that stored text/level, which is
     entirely a Codex runtime judgment.
   - `data_quality_degraded` (bool): whether upstream feeds are
     currently unreliable.
   Both default to `false` if omitted; get these wrong and transitions
   will be misclassified, so build them carefully.

5. **A scheduler calling `Bitmomo_Watchtower_Orchestrator::process()`.**
   No cron is registered anywhere in this plugin. Wire WP-Cron or a
   system cron to build a candidate thesis (steps 1–3) and call
   `process()` with it plus the context flags.

6. **A Telegram adapter draining the alert outbox.** Poll
   `Bitmomo_Watchtower_Alert_Outbox::instance()->get_queued()`, send each
   entry via Telegram, then call `mark_status( $id, 'sent' | 'failed', $note )`.
   No Telegram credentials or send logic exist anywhere in this plugin —
   this is the exact "clean handoff point" the original spec asked for.

7. **Staging verification of every WordPress-touching class.**
   `Bitmomo_Watchtower_Thesis_Store`, `Bitmomo_Watchtower_Alert_Outbox`,
   `Bitmomo_Watchtower_Orchestrator`, and `Bitmomo_Watchtower_Admin_Diagnostics`
   are not covered by the deterministic PHP test suites (they require a
   real WordPress database) — confirm on staging that theses persist
   correctly, alerts enqueue and can be marked sent/failed, and the
   admin screen renders real data once the above is connected.

## What Codex must NOT do based on this PR sequence

- Do not claim "24/7 monitoring is live," "material-change alerts are
  live," or "11 AI agents are live" anywhere until steps 1–7 above are
  connected and verified — an installed-but-unscheduled plugin produces
  no theses and no alerts; the admin screen honestly shows "no thesis
  recorded yet" in that state.
- Do not implement the full multi-agent analyst system behind
  `Bitmomo_Watchtower_Analyst_Router`'s tracks as part of "just wiring
  this up" — that is explicitly out of scope for this PR sequence and
  was never requested as part of it.
- Do not call any LLM to manufacture a candidate thesis in this first
  integration.
- Do not modify `includes/class-bitmomo-watchtower-config.php`'s weights
  or thresholds without bumping `Bitmomo_Watchtower_Config::MATERIALITY_ENGINE_VERSION` —
  old scored records (once a store exists) must remain attributable to
  the ruleset that actually produced them.

## Explicitly out of scope for Product B (do not add here)

Payment/membership, email, homepage redesign, affiliate content,
ETH/altcoin intelligence, mobile app, WhatsApp, a public performance
leaderboard, and any dependency on `bitmomo-regime`, `bitmomo-pro`, or
`bitmomo-ai`. Product B's engine remains fully isolated in
`website/wp-content/plugins/bitmomo-watchtower/`.

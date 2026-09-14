# Bitmomo Research Feed Provider V1

Status: P0 READ-ONLY PROVIDER / NOT RUNTIME-WIRED  
Version: `research-feed-provider-v1`  
Tracks: #143

## Purpose

Provider V1 is the call-on-demand bridge from Bitmomo's current canonical BTC runtime state into `research-feed-v1`.

It does not register hooks, schedules, REST routes, database writes, network calls, or publishers. Nothing executes unless a future consumer explicitly calls the provider.

## Current BTC read path

The provider reads two existing canonical surfaces:

1. `Bitmomo_AI_Intelligence::free_projection()` — the existing public-safe validated intelligence projection.
2. `Bitmomo_AI_Runtime_State::latest_valid_snapshot()` — the canonical valid runtime snapshot used only to recover quality and source lineage that the public projection intentionally does not expose.

The provider does not independently classify BTC and does not create a second intelligence validator.

## Lineage gate

The two reads must resolve to the same canonical snapshot before a Research Feed item can exist.

Provider V1 fails closed unless:

- both reads are available;
- projection status is `fresh` or `delayed`;
- timestamps match within one second;
- positive reference prices match within floating-point tolerance;
- Directional Bias matches exactly;
- snapshot evaluation quality is `complete` or `degraded`;
- the public-safe provenance/source label is non-empty.

If explicit `source_record_id` is missing, identity falls back deterministically to `bitmomo-ai:<snapshot_timestamp>`.

## Optional context

Opportunity is carried only when the canonical session snapshot already contains an `available` Opportunity record. It is never recomputed by the provider and remains a non-directional activity state.

Market State/Regime is intentionally not fabricated or inferred from Directional Bias. Provider V1 leaves it absent unless a later version adds a time-aligned canonical regime join.

## Aggregation

`Bitmomo_Research_Feed_Provider_V1::build($manual_sources, $now)` produces one read-only feed snapshot containing:

- the current BTC research item when canonical lineage passes;
- zero or more explicitly supplied manual/original research records;
- deterministic duplicate diagnostics;
- rejected-source diagnostics;
- total and distribution-eligible item counts.

A failure to read current BTC does not delete valid manual research. BTC is instead listed under `rejected` with explicit reasons.

## Safety boundary

Provider V1 does not:

- publish content;
- enqueue content;
- decide social channels;
- generate marketing copy;
- call an LLM;
- call X, YouTube, Reddit, TradingView, email, or Telegram;
- create whitelist traffic;
- change the homepage or Pro surfaces;
- modify current BTC intelligence semantics.

The next distribution layer may consume only `distribution.eligible = true` items for automatic content generation unless a human explicitly overrides a later policy layer.

## Next integration step

After P0 is accepted, P1 may add an Intent Radar and Content Compiler as separate consumers. They must depend on this provider/feed boundary rather than reading WordPress options, theme JSON, or market APIs directly.

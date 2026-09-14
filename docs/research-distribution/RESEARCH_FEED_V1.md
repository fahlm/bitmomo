# Bitmomo Research Feed V1

Status: P0 CONTRACT / NOT RUNTIME-WIRED  
Version: `research-feed-v1`  
Tracks: #143

## Purpose

Research Feed V1 is the read-only integration boundary between Bitmomo's canonical intelligence/research systems and future distribution consumers such as the Content Compiler, Intent Radar, newsletter tooling, and social publishing workflows.

It does **not** generate market intelligence, alter research conclusions, classify BTC, publish content, call social APIs, or decide whether a user should trade.

## Source-of-truth boundaries

Current BTC intelligence remains governed by `docs/INTELLIGENCE_CONTRACT_V1.md` and its runtime producers. Research Feed V1 is a projection only.

P0 accepts two source families:

1. `btc_intelligence` — a validated snapshot produced from the existing canonical BTC intelligence boundary.
2. `manual_research` — an explicitly authored Bitmomo research record with methodology, provenance, limitations, and source references.

Historical Watchtower/Event Engine branches are research evidence only in P0. They are not runtime dependencies because Watchtower is not part of current `main`. An `event` source family may be added later only after an event producer becomes canonical in `main`.

## Non-equivalence rules

Research Feed V1 must preserve existing semantics.

- Directional Bias is directional.
- Confidence is evidence strength/completeness, **not probability of being correct**.
- Opportunity is a relative short-horizon activity state and **must not be interpreted as direction**.
- Market State/Regime is structural context and is not Directional Bias.
- Distribution eligibility is a publishing-safety property and is not research quality or investment conviction.

No adapter may infer one of these concepts from another.

## Canonical research item

Every valid item has the following top-level fields:

```json
{
  "contract_version": "research-feed-v1",
  "research_id": "bmr-...",
  "fingerprint": "sha256:...",
  "source_type": "btc_intelligence|manual_research",
  "title": "...",
  "summary": "...",
  "findings": [],
  "metrics": {},
  "confidence": {
    "value": null,
    "label": null,
    "semantics": "evidence_strength_not_probability"
  },
  "limitations": [],
  "market_context": {},
  "event_links": [],
  "topics": [],
  "tags": [],
  "provenance": {
    "source_record_id": "...",
    "as_of": "ISO-8601",
    "producer": "...",
    "methodology_version": "...",
    "source_refs": []
  },
  "data_quality": {
    "status": "complete|degraded|unavailable",
    "freshness": "fresh|delayed|unavailable",
    "reasons": []
  },
  "distribution": {
    "eligible": true,
    "reasons": []
  }
}
```

## Identity and deduplication

`fingerprint` is deterministic. It is calculated from a stable canonical subset of the normalized item and never includes ingestion time, array insertion order, or distribution state.

`research_id` is deterministic from source lineage:

- BTC intelligence: derived from the canonical source record identifier / `as_of` lineage.
- Manual research: derived from the declared research slug/version and provenance.

The same normalized source must produce the same `fingerprint` and `research_id` on repeated ingestion. A feed aggregator keeps the first instance of an identical fingerprint and records duplicates as diagnostics rather than creating a second research item.

## BTC intelligence adapter

The adapter may only project fields already present in the canonical snapshot. It may not recompute market classification.

Required BTC inputs:

- usable status: `fresh` or `delayed`;
- quality status: `complete` or `degraded`;
- positive BTC reference price;
- valid `as_of` timestamp;
- directional bias in `bullish|neutral|bearish`;
- confidence 0–100;
- non-empty provenance source;
- source record lineage, or enough canonical timestamp lineage to deterministically derive `bitmomo-ai:<unix_timestamp>`.

Optional projected context:

- direction strength;
- Market State;
- Opportunity state and methodology version;
- key drivers;
- session metadata;
- engine/classifier versions.

A delayed record can remain a valid research item but is distribution-ineligible by default in P0. An unavailable, invalid, future-dated, or provenance-less BTC record fails closed.

## Manual/original research adapter

Required manual research inputs:

- stable `research_slug`;
- explicit `version`;
- title and summary;
- at least one finding;
- `as_of` timestamp;
- methodology version or methodology description;
- at least one source reference;
- at least one limitation;
- quality status `complete` or `degraded`.

Manual research with missing provenance, sources, methodology, or limitations fails closed. Degraded research may be retained but is distribution-ineligible unless a later policy version explicitly allows it.

## Distribution eligibility policy — P0

P0 is intentionally conservative.

Eligible only when all are true:

- normalized item is valid;
- quality is `complete`;
- freshness is `fresh` for time-sensitive BTC intelligence;
- provenance is complete;
- at least one non-empty finding exists;
- no adapter-level safety reason is present.

`distribution.eligible = false` never deletes the research item. It prevents downstream publishers from silently turning stale/degraded evidence into public claims.

## Fail-closed behavior

The validator returns a structured failure and no partial research item when a required invariant is broken.

Examples:

- malformed/future `as_of`;
- invalid confidence;
- missing provenance;
- unsupported source type;
- empty findings;
- impossible BTC reference price;
- unknown directional bias;
- missing methodology/sources/limitations for manual research.

Missing optional values stay `null`/empty. They are never replaced with fabricated neutral values.

## P0 runtime boundary

P0 adds no:

- WordPress hook;
- cron schedule;
- REST route;
- database write;
- auto-publisher;
- social API call;
- whitelist/public UI change;
- production deployment.

The implementation is a pure deterministic class that future consumers must call explicitly.

## Consumer rule

Future consumers must treat Research Feed V1 as immutable evidence input. They may summarize or format eligible items, but they may not alter source semantics, invent causality, convert Opportunity into direction, or reinterpret confidence as forecast probability.

Any semantic change to this contract requires a new contract version.

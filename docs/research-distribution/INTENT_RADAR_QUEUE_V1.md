# Bitmomo Intent Radar Queue V1

Status: P1 BATCH CONTRACT / FIXTURE-ONLY / NO LIVE PLATFORM ACCESS  
Version: `intent-radar-queue-v1`  
Tracks: #145  
Depends on: `intent-radar-v1` and PR #144 Research Feed contracts.

## Purpose

Queue V1 is the deterministic batch boundary between a future approved ingestion layer and downstream review/content workflows.

It accepts already-supplied observations, runs them through Intent Radar V1, deduplicates them, applies cooldown state, and returns explicit queues. It performs no network requests, persistence, publishing, or engagement.

## Input shape

Each batch entry is:

```json
{
  "source": "x|youtube",
  "payload": {}
}
```

`payload` must satisfy the relevant fixture adapter contract documented in `INTENT_RADAR_V1.md`.

Unsupported sources are rejected in V1. Reddit, TradingView, Telegram, email, and other adapters require separate future contracts rather than silently reusing X/YouTube semantics.

## Output shape

```json
{
  "queue_version": "intent-radar-queue-v1",
  "radar_version": "intent-radar-v1",
  "generated_at": "ISO-8601",
  "counts": {
    "input": 0,
    "accepted_before_dedupe": 0,
    "unique": 0,
    "review": 0,
    "monitor": 0,
    "ignored": 0,
    "cooldown": 0,
    "rejected": 0,
    "duplicates": 0
  },
  "review": [],
  "monitor": [],
  "ignored": [],
  "cooldown": [],
  "rejected": [],
  "duplicates": []
}
```

## Queue semantics

- `review` — Intent Radar returned `review_for_reply`; every item still has `approval_required = true` and `auto_outbound_permitted = false`.
- `monitor` — relevant opportunity that is not eligible for outbound review, including high-scoring statements without explicit request evidence and non-interactive surfaces such as YouTube search results.
- `ignored` — generic chatter, low-score observations, or hard-block spam that passed structural validation but should not consume operator attention.
- `cooldown` — otherwise valid opportunity whose deterministic cooldown key is currently active. It is retained for traceability but removed from the review/monitor queues.
- `rejected` — structurally invalid, stale, future-dated, unsupported-source, or otherwise fail-closed observations.
- `duplicates` — repeated stable source objects or repeated fingerprints removed before queue routing.

## Ordering

Each queue is sorted deterministically:

1. score descending;
2. `opportunity_id` ascending as the stable tie-breaker.

The highest-priority review item therefore appears first without requiring an LLM or nondeterministic ranking service.

## Cooldown

Queue V1 accepts externally supplied cooldown state:

```json
{
  "cooldown:<key>": "last-action-ISO-8601"
}
```

Intent Radar V1 evaluates it against the frozen six-hour default. Queue V1 does not write or persist cooldown state.

## Research enrichment

The same distribution-eligible Research Feed items supplied to the batch are passed to each Intent Radar evaluation. Queue V1 does not perform a second research join and cannot override upstream research eligibility.

## Safety boundary

Queue membership never means permission to send, post, reply, comment, DM, like, follow, or otherwise contact a user.

`review` means only that the observation is worth human review.

A future live ingestion/engagement system must be implemented separately and may not bypass the `approval_required` / `auto_outbound_permitted` invariants.

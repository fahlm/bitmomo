# Bitmomo Dry-Run Acquisition Orchestrator V1

Status: P5 CONTRACT / END-TO-END DRY RUN / NO LIVE PLATFORM ACCESS  
Run contract: `acquisition-run-v1`  
Tracks: #154  
Depends on: PR #144 Research Feed, #146 Intent Radar, #149 Content Compiler, #151 Engagement Copilot, #153 Growth Attribution.

## Purpose

P5 proves the research-distribution stack can operate as one deterministic marketing engine before any live ingestion or delivery is connected.

The orchestrator performs no new analysis. It composes canonical modules in this order:

1. canonical Research Feed items;
2. fixture-only X/YouTube observations;
3. Intent Radar Queue V1;
4. Content Compiler campaign;
5. Engagement Copilot approval queue;
6. Growth Attribution internal dry-run events;
7. operator worklist.

## Inputs

- canonical research items compatible with `research-feed-v1`;
- supplied X/YouTube fixture observations;
- optional cooldown state;
- optional explicit interaction context;
- optional owned-content formats;
- deterministic clock for testing.

P5 does not fetch any platform itself.

## Output

Each run contains:

- stable `run_id`;
- stable end-to-end fingerprint;
- explicit safety state;
- aggregate counts;
- `engagement_review` worklist;
- `owned_content` worklist;
- `monitor` worklist;
- per-layer diagnostics;
- full Intent Radar queue;
- full Content Compiler campaign;
- full Engagement Copilot queue;
- attribution-ready P4 ledger.

## Safety state

Every V1 run is frozen to:

- `mode = dry_run_only`
- network access OFF;
- browser automation OFF;
- auto-publish OFF;
- auto-send OFF;
- production mutation OFF;
- human approval required for engagement.

No runtime switch exists in P5 to override those constraints.

## Orchestration authority

P5 introduces no independent:

- intent classifier;
- scoring system;
- research evidence;
- content claim;
- response policy;
- attribution semantics.

Those remain owned by their upstream canonical modules. P5 only coordinates their outputs.

## Worklist

### Engagement review

Contains only Engagement Copilot drafts produced from Intent Radar `review_for_reply` opportunities with valid linked evidence.

Every entry remains human-review-only and auto-send disabled.

### Owned content

Contains Content Compiler briefs for eligible research. They remain draft-only and auto-publish disabled.

### Monitor

Contains high-value opportunities that are useful for owned-content/search demand understanding but are not eligible for outbound engagement review.

YouTube search results normally belong here rather than the engagement queue.

## Attribution-ready dry-run events

P5 emits only internal planning events:

- `intent_detected` for review/monitor opportunities;
- `draft_created` for owned-content briefs and engagement drafts.

It does **not** fabricate:

- `operator_approved`;
- `content_published`;
- profile/site visits;
- whitelist events;
- paid activations.

Those require real downstream observations in a later instrumentation phase.

Dry-run events contain no actor/session refs.

## Deterministic identity

`run_id` is derived from canonical research ids/fingerprints, observation source/id/text hashes, and requested formats. Equivalent inputs produce the same run id independent of observation ordering.

The run fingerprint is based on stable worklist identities, counts, safety state, and attribution event ids, excluding wall-clock presentation fields.

## Repository / production boundary

P5 implementation/tests live under:

- `research/acquisition-orchestrator/includes/`
- `research/acquisition-orchestrator/tests/`

P5 must not touch:

- `website/wp-content/**`;
- `config/production-runtime.json`;
- databases;
- cookies/pixels;
- platform APIs;
- publishing/delivery surfaces.

## Next gate

After P5 acceptance, the stack is ready for a separately reviewed **live ingestion phase**. Live ingestion should begin read-only with platform-supported APIs/search surfaces and preserve the existing human-approval and attribution contracts. Production website instrumentation remains a separate release decision.

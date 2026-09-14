# Bitmomo Content Compiler V1

Status: P2 CONTRACT / OWNED-CONTENT BRIEFS ONLY / NO PUBLISHING  
Version: `content-compiler-v1`  
Brief contract: `content-brief-v1`  
Tracks: #148  
Depends on: PR #144 Research Feed V1 and PR #146 Intent Radar V1.

## Purpose

Content Compiler V1 converts distribution-eligible Bitmomo research into deterministic, evidence-locked briefs for owned Bitmomo content surfaces.

It is the boundary between **what Bitmomo knows** and **how that knowledge should be framed for a channel**.

P2 does not write final free-form posts, call an LLM, publish content, or contact users.

## Supported owned formats

- `x_post` — one concise original Bitmomo post.
- `x_thread` — evidence-sequenced original Bitmomo thread.
- `youtube_search` — search-first long-form video brief.
- `youtube_short` — one-finding short-form video brief.
- `research_article` — Bitmomo Research article brief.

Replies, comments, DMs, and other user-targeted outbound formats are intentionally excluded.

## Evidence eligibility

A research record fails closed unless all are present:

- `distribution.eligible = true`;
- stable `research_id` and fingerprint;
- non-empty title;
- at least one finding;
- at least one material limitation;
- provenance source record id;
- provenance `as_of`;
- methodology version.

The compiler cannot override Research Feed eligibility.

## Evidence lock

Every brief contains an immutable evidence section projected from the Research Feed input:

```json
{
  "findings": [],
  "metrics": {},
  "limitations": [],
  "provenance": {
    "source_record_id": "...",
    "as_of": "...",
    "producer": "...",
    "methodology_version": "...",
    "source_refs": []
  }
}
```

Intent context may change title framing, objective, search keyword, and distribution format. It may **not** change findings, metrics, methodology, provenance, or limitations.

## Claim policy

Downstream content generation may claim only what can be grounded in:

- `evidence.findings`;
- `evidence.metrics`;
- `evidence.provenance`;
- `evidence.limitations`.

V1 explicitly forbids:

- invented causality;
- invented statistics;
- guaranteed returns;
- unsupported win rates;
- interpreting confidence as probability of correctness;
- interpreting Opportunity as direction;
- omission of material limitations.

## Intent privacy boundary

Intent Radar opportunities are public-demand observations, not content sources to quote automatically.

The compiler retains only safe aggregate intent context:

- normalized primary intent;
- matched intent terms;
- intent score;
- detected language;
- normalized search keyword.

The following are **not propagated into briefs**:

- author references;
- source/post/comment ids;
- source URLs;
- verbatim third-party observation text;
- private/sensitive profile attributes.

This prevents the Content Compiler from turning demand discovery into unsolicited targeting or accidental quoting.

## Objectives

V1 selects one deterministic objective:

- `intent_capture` — evidence exists and relevant demand intent is present.
- `authority` — original/manual research or AI-research demand.
- `event_intelligence` — event-reaction intent or event/macro research context.
- `accountability` — forecast-ledger/accountability research.

Objectives alter framing only. They do not alter evidence.

## Native format structures

### X post

`hook → one evidence point → implication → limitation → CTA`

### X thread

`hook → research question → evidence 1 → evidence 2 → implication → limitations → CTA`

### YouTube Search

`query-match hook → current context → evidence → what matters next → limitations → CTA`

### YouTube Short

`first 2s hook → single finding → single caveat → CTA`

### Research article

`question → data/method → findings → interpretation → limitations → methodology/sources`

## Signature authority framing

Original/manual research articles may use the signature framing:

> `Bitmomo Tested It: <research title>`

This is a framing system, not permission to exaggerate research results.

AI-research intent may use:

> `AI Bitcoin Research: What the Evidence Actually Shows`

No AI win-rate, superiority, prediction-accuracy, or performance claim may be introduced unless that exact evidence exists upstream.

## Search-intent framing

For YouTube formats, normalized intent maps to a stable query phrase such as:

- explicit signal → `bitcoin signal today`;
- long/short → `btc long or short`;
- entry/exit → `btc entry`;
- analysis/prediction → `bitcoin analysis today`;
- support/resistance → `btc support resistance`;
- why-move → `why is btc moving`;
- event reaction → `btc event reaction`;
- AI research → `ai bitcoin research`.

The phrase is metadata/framing guidance only; it cannot alter research conclusions.

## CTA and attribution

V1 supports only owned Bitmomo acquisition CTA metadata:

- type: `founding_member_whitelist`;
- destination key: `bitmomo_founding_whitelist`.

It does not hardcode an external URL.

Each brief has deterministic UTM metadata based on research id, channel format, and normalized intent. Attribution is planning metadata only; P2 does not create a click, visit, or signup.

## Identity and dedupe

`brief_id` is deterministic from:

`research_id + normalized intent + format`

Fingerprint is deterministic over the source research reference, safe intent context, format, framing, and evidence lock.

Repeated identical research/intent/format combinations deduplicate without creating additional briefs.

## Campaign planner

`compile_campaign()` accepts:

- eligible Research Feed items;
- an existing Intent Radar Queue V1 snapshot;
- requested owned formats.

For each research id, it selects the highest-scoring linked intent from `review` or `monitor` buckets only.

The `ignored`, `cooldown`, `rejected`, and duplicate buckets cannot influence content framing.

The campaign planner then compiles, deduplicates, and deterministically sorts briefs. Invalid research is reported under `rejected` without blocking valid research.

## Human-review boundary

Every V1 brief is:

- `status = draft_only`;
- `human_review_required = true`;
- `auto_publish_permitted = false`.

A downstream system may not reinterpret a brief as publication approval.

## Repository and production boundary

Implementation/tests live under:

- `research/content-compiler/includes/`
- `research/content-compiler/tests/`

P2 must not add files under `website/wp-content/**` or modify `config/production-runtime.json`.

## Explicitly out of scope

P2 contains no:

- LLM calls;
- final free-form copy generation;
- platform API calls;
- auto-publishing;
- automated replies/comments/DMs;
- browser automation;
- WordPress hooks or database writes;
- whitelist writes;
- production deployment.

# Bitmomo Engagement Copilot V1

Status: P3 CONTRACT / HUMAN-REVIEW-ONLY / NO LIVE DELIVERY  
Version: `engagement-copilot-v1`  
Draft contract: `engagement-draft-v1`  
Approval queue: `engagement-approval-queue-v1`  
Tracks: #150  
Depends on: PR #144 Research Feed, PR #146 Intent Radar, PR #149 Content Compiler.

## Purpose

Engagement Copilot V1 turns an Intent Radar `review_for_reply` opportunity and linked Content Compiler evidence into a concise operator-ready response draft.

It automates research selection, response angle, evidence insertion, caveat insertion, policy diagnostics, dedupe, and queue ranking. It does **not** send anything.

## Platform policy boundary

V1 encodes a conservative delivery policy:

- X opportunities discovered by keyword search are never eligible for automated unsolicited replies.
- X AI-powered automated replies require explicit platform approval; V1 does not model approval as permission to send.
- YouTube response drafts remain manual because repetitive/high-volume promotional commenting creates spam risk.
- Every V1 draft is `human_review_only`.

These are delivery constraints, not ranking penalties. A high-value opportunity may still receive an excellent draft while remaining impossible to auto-send.

## Required upstream opportunity

A draft fails closed unless the Intent Radar opportunity has:

- `suggested_action = review_for_reply`;
- `approval_required = true`;
- `auto_outbound_permitted = false`;
- supported source `x` or `youtube`;
- stable source reference;
- non-empty primary intent;
- at least one linked research id.

`monitor`, `ignored`, cooldown, rejected, or upstream auto-outbound-permitted opportunities cannot become V1 drafts.

## Required Content Compiler brief

The linked brief must have:

- `contract_version = content-brief-v1`;
- `status = draft_only`;
- `human_review_required = true`;
- `auto_publish_permitted = false`;
- research id matching one of the opportunity's Research Feed links;
- at least one evidence finding;
- at least one material limitation.

The Engagement Copilot does not query market data or construct new research evidence.

## Draft contract

Each valid draft contains:

```json
{
  "contract_version": "engagement-draft-v1",
  "copilot_version": "engagement-copilot-v1",
  "draft_id": "bed-...",
  "fingerprint": "sha256:...",
  "status": "human_review_only",
  "source_context": {
    "source": "x|youtube",
    "source_ref": "...",
    "source_url": null,
    "opportunity_id": "...",
    "score": 0
  },
  "intent": "...",
  "language": "id|en",
  "strategy": {},
  "draft_text": "...",
  "evidence_trace": {},
  "cta": {
    "mode": "none_by_default",
    "direct_link_permitted": false
  },
  "delivery_policy": {},
  "approval_required": true,
  "auto_send_permitted": false,
  "created_at": "ISO-8601"
}
```

Source reference/URL exist only as operator metadata for traceability. They must never be interpolated into `draft_text`.

## Evidence rule

V1 selects the first valid upstream finding and first material limitation from the evidence-locked Content Compiler brief.

The finding and limitation are inserted without translation or factual rewriting. This keeps the response attributable to canonical Bitmomo evidence even when the surrounding response framing is Indonesian or English.

The response template may add brand/decision-support framing, but it may not add:

- new metrics;
- new causal claims;
- guaranteed outcomes;
- unsupported performance/win-rate claims;
- a new directional classification;
- removal of the selected caveat.

## Intent strategies

V1 has deterministic strategies for:

- explicit signal → signal plus invalidation framing;
- long/short → direction plus invalidation framing;
- entry/exit → entry requires context framing;
- analysis/prediction → evidence over prediction;
- support/resistance → levels require context;
- why-move → driver explanation;
- event reaction → whether the setup changed;
- AI research → AI as a testable model, not an oracle.

The strategy affects explanatory framing only. The evidence statement remains upstream-controlled.

## Language

`id` produces concise Bahasa Indonesia framing. `en` produces English framing. Unknown language defaults to English.

The compiler does not machine-translate evidence claims in V1 because translation would create a second semantic transformation layer.

## Promotional CTA policy

Unsolicited response drafts contain no direct whitelist link by default.

CTA metadata is frozen to:

- `mode = none_by_default`;
- `direct_link_permitted = false`.

Bitmomo account/profile identity is the default discovery path. Any later explicit promotional CTA rule requires a new policy version.

## Delivery policy diagnostics

### X keyword discovery

Policy reasons include:

- `unsolicited_auto_reply_prohibited`;
- `x_ai_auto_reply_requires_platform_approval`;
- `human_review_required`.

### X explicit opt-in/mention

The unsolicited reason may be absent when explicit opt-in context is supplied, but AI delivery still remains blocked in V1 and `auto_send_permitted=false`.

### YouTube

Policy reasons include:

- `youtube_comment_spam_manual_review_required`;
- `human_review_required`.

## Approval queue

`Bitmomo_Engagement_Copilot_Queue_V1::build()` accepts:

- review opportunities;
- Content Compiler briefs;
- optional interaction-context metadata.

For each opportunity it chooses a linked brief deterministically, preferring:

1. matching intent;
2. source-native format (`x_post` for X, `youtube_search` for YouTube);
3. research article fallback.

It then drafts, deduplicates, rejects invalid inputs, and sorts review drafts by opportunity score descending with stable draft-id tie-break.

Queue output contains:

- `review_drafts`;
- `duplicates`;
- `rejected`;
- explicit counts.

## Identity and dedupe

`draft_id` derives from source, source reference, research id, and primary intent.

Fingerprint additionally includes response text, evidence trace, language, and delivery policy. Repeated identical opportunities/evidence deduplicate deterministically.

## Repository and production boundary

Implementation/tests live under:

- `research/engagement-copilot/includes/`
- `research/engagement-copilot/tests/`

P3 must not add files under `website/wp-content/**` or modify `config/production-runtime.json`.

## Explicitly out of scope

V1 contains no:

- X/YouTube API calls;
- browser automation;
- sending/posting/commenting/DM actions;
- auto-like/follow actions;
- account creation or rotation;
- fake engagement;
- live LLM calls;
- WordPress hooks/UI/database writes;
- production deployment.

# Bitmomo Intent Radar V1

Status: P1 CONTRACT / FIXTURE-ONLY / NO LIVE PLATFORM ACCESS  
Version: `intent-radar-v1`  
Opportunity contract: `intent-opportunity-v1`  
Tracks: #145  
Depends on: PR #144 (`research-feed-v1` / `research-feed-provider-v1`)

## Purpose

Intent Radar V1 converts supplied public BTC-demand observations into deterministic acquisition opportunities for later Bitmomo workflows.

It answers four narrow questions:

1. Is this observation actually about BTC?
2. What user intent does it express?
3. Is it fresh, specific, and useful enough to prioritize?
4. Is there distribution-eligible Bitmomo research that can support a future response?

P1 does **not** contact platforms, publish content, generate replies, or perform engagement.

## Supported intent taxonomy

- `explicit_signal` — explicit request for a BTC/Bitcoin signal.
- `long_short` — directional long/short or bullish/bearish decision request.
- `entry_exit` — entry, exit, buy/sell timing, take-profit, or stop-loss request.
- `analysis_prediction` — BTC analysis, prediction, next-move, or direction request.
- `breakout_support_resistance` — breakout, support, resistance, or level request.
- `why_move` — question about why BTC is pumping, dumping, rising, or falling.
- `event_reaction` — BTC reaction to catalysts such as Fed/FOMC/CPI/ETF/NFP/SEC.
- `ai_research` — explicit demand around AI models, AI analysis, or AI research for BTC/crypto.

A single observation may match multiple intents. `intent.primary` is chosen deterministically using frozen intent weights; all matched intents and terms remain visible. AI-specific demand outranks generic analysis when both match, while explicit signal/long-short/entry demand remain the highest acquisition priorities.

## Opportunity contract

Every valid opportunity contains:

```json
{
  "contract_version": "intent-opportunity-v1",
  "radar_version": "intent-radar-v1",
  "opportunity_id": "bio-...",
  "fingerprint": "sha256:...",
  "source": "x|youtube",
  "source_ref": "...",
  "source_url": null,
  "surface": "post|comment|reply|search_result|video|unknown",
  "text": "...",
  "author": {"ref": "..."},
  "intent": {"primary": "...|null", "matches": {}},
  "score": 0,
  "score_components": {},
  "confidence": {
    "value": 0,
    "semantics": "classification_confidence_not_conversion_probability"
  },
  "matched_terms": [],
  "detected_language": "id|en|unknown",
  "freshness": {"age_seconds": 0, "band": "live|recent|current|aging|old"},
  "spam": {"penalty": 0, "reasons": [], "hard_block": false},
  "research_links": [],
  "suggested_action": "ignore|monitor|review_for_reply",
  "priority": "ignore|p2|p1|p0",
  "approval_required": false,
  "auto_outbound_permitted": false,
  "cooldown_key": "cooldown:...",
  "observed_at": "ISO-8601",
  "created_at": "ISO-8601"
}
```

`confidence` is classifier certainty only. It is **not** estimated conversion probability, trading confidence, forecast accuracy, or probability the user will buy.

## Required observation inputs

An observation fails closed unless it contains:

- supported source: `x` or `youtube`;
- stable non-empty `source_ref`;
- non-empty public text or, for YouTube fixtures, title/description that can form text;
- valid observation timestamp not more than five minutes in the future;
- age no greater than 48 hours.

`source_url` and public author reference are optional. P1 stores no private user profile or sensitive attributes.

## Deterministic scoring

Score range is `0–100` and is composed from explicit fields, not an LLM:

- intent-specific base weight;
- BTC relevance;
- question/request framing;
- time sensitivity;
- observation freshness;
- Indonesian/English language fit;
- engagement potential;
- optional research-fit boost;
- spam/noise penalties.

The scoring model is an acquisition-priority heuristic. It does not estimate investment value or expected returns.

### Demand gate and action policy

**High score alone is never permission to reply.**

For `review_for_reply`, V1 requires all of the following:

- a recognized intent;
- score >= 55;
- no spam hard block;
- explicit request/question evidence (`?`, `anyone have`, `looking for`, `lagi cari`, `butuh`, etc.);
- an interaction-capable surface: X post/comment/reply, or YouTube comment.

Then:

- score >= 75 → `p0 / review_for_reply`;
- score 55–74 → `p1 / review_for_reply`.

Recognized high-scoring observations without explicit demand—such as a post stating `BTC ETF update` or a YouTube search result—remain `monitor`, not reply candidates.

Recognized intent with score 35–54 remains `p2 / monitor`. Below 35, missing intent, or hard-block spam becomes `ignore`.

Any `review_for_reply` opportunity has `approval_required = true`.

`auto_outbound_permitted` is frozen to `false` in V1.

## Platform safety boundary

P1 does not implement:

- live X or YouTube API calls;
- browser automation;
- automated replies, DMs, or comments;
- automated likes or follows;
- account creation or account rotation;
- fake engagement or mass unsolicited outreach;
- LLM-generated outbound copy.

The X and YouTube methods in V1 are **fixture adapters only**. A later live ingestion layer must have its own policy/platform review and may not weaken the outbound approval boundary by changing an adapter.

## Spam/noise handling

V1 applies deterministic penalties to promotional/noise markers such as guaranteed-profit claims, unrealistic win-rate language, VIP promotion, referral/promo language, giveaways/airdrops, repeated characters, and multiple URLs.

At sufficiently high penalty, `hard_block = true` and action is forced to `ignore` even if BTC-signal keywords are present.

## Identity, dedupe, and cooldown

`opportunity_id` is deterministic from `source + source_ref`.

`fingerprint` is deterministic from contract version, source identity, and normalized public text.

Deduplication treats either repeated `opportunity_id` or repeated fingerprint as duplicate. An edited post/comment with the same stable source id therefore does not become a new opportunity.

`cooldown_key` is derived from source + public author reference + primary intent when author reference exists, otherwise source + source reference + intent.

V1 provides a deterministic cooldown check with a six-hour default. It does not persist cooldown state or perform an action.

## Research Feed enrichment

Intent Radar may receive Research Feed items from P0. Only items with `distribution.eligible = true` may be attached to `research_links`.

Matching uses deterministic lexical/topic overlap between observation/intent terms and research title, summary, topics, and tags. The radar returns references, match scores, and matched terms only; it does not rewrite or reinterpret the research conclusion.

Research may strengthen an already-recognized demand opportunity by a small fixed score boost. **Research relevance may never create an intent or promote generic BTC chatter into demand.**

Ineligible/stale/degraded Research Feed items are ignored. No research match remains a valid state.

## Repository and production boundary

Implementation and tests live under:

- `research/intent-radar/includes/`
- `research/intent-radar/tests/`

P1 must not add files under `website/wp-content/**` or modify `config/production-runtime.json`. Therefore P1 cannot silently enter the WordPress production artifact.

## Consumer rule

Future Content Compiler or Engagement Copilot consumers may read this contract, but they must not interpret `review_for_reply` as permission to send anything.

`review_for_reply` means only:

> a potentially valuable public demand observation deserves human review.

Any future live outbound system requires a separate explicit policy gate and implementation phase.

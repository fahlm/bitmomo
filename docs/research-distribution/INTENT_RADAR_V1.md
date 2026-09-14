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

The V1 taxonomy is intentionally narrow and acquisition-oriented:

- `explicit_signal` — explicit request for a BTC/Bitcoin signal.
- `long_short` — directional long/short or bullish/bearish decision request.
- `entry_exit` — entry, exit, buy/sell timing, take-profit, or stop-loss request.
- `analysis_prediction` — BTC analysis, prediction, next-move, or direction request.
- `breakout_support_resistance` — breakout, support, resistance, or level request.
- `why_move` — question about why BTC is pumping, dumping, rising, or falling.
- `event_reaction` — BTC reaction to catalysts such as Fed/FOMC/CPI/ETF/NFP/SEC.
- `ai_research` — explicit demand around AI models, AI analysis, or AI research for BTC/crypto.

A single observation may match multiple intents. `intent.primary` is chosen deterministically using frozen intent weights; all matched intents and terms remain visible.

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
  "surface": "post|comment|search_result|video|unknown",
  "text": "...",
  "author": {"ref": "..."},
  "intent": {
    "primary": "...|null",
    "matches": {}
  },
  "score": 0,
  "score_components": {},
  "confidence": {
    "value": 0,
    "semantics": "classification_confidence_not_conversion_probability"
  },
  "matched_terms": [],
  "detected_language": "id|en|unknown",
  "freshness": {
    "age_seconds": 0,
    "band": "live|recent|current|aging|old"
  },
  "spam": {
    "penalty": 0,
    "reasons": [],
    "hard_block": false
  },
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

A source observation fails closed unless it contains:

- supported source: `x` or `youtube`;
- stable non-empty `source_ref`;
- non-empty public text or, for YouTube fixtures, title/description that can form text;
- valid `observed_at` / `published_at` timestamp not more than five minutes in the future;
- age no greater than 48 hours.

`source_url` and public author reference are optional. P1 stores no private user profile or sensitive attributes.

## Deterministic scoring

Score range is `0–100` and is composed from explicit fields, not an LLM:

- intent-specific base weight;
- BTC relevance;
- question/request framing;
- time sensitivity (`today`, `now`, `hari ini`, `sekarang`, etc.);
- observation freshness;
- Indonesian/English language fit;
- engagement potential;
- optional research-fit boost;
- spam/noise penalties.

The scoring model is an acquisition-priority heuristic. It does not estimate investment value or expected returns.

### Priority policy

- `p0`: score >= 75 and not policy-blocked → `review_for_reply`.
- `p1`: score >= 55 and not policy-blocked → `review_for_reply`.
- `p2`: score >= 35 → `monitor`.
- below 35, no recognized intent, or hard-block spam → `ignore`.

Any `review_for_reply` opportunity has `approval_required = true`.

`auto_outbound_permitted` is frozen to `false` in V1.

## Platform safety boundary

P1 does not implement:

- live X or YouTube API calls;
- browser automation;
- automated replies;
- automated DMs;
- automated comments;
- automated likes or follows;
- account creation or account rotation;
- fake engagement;
- mass unsolicited outreach;
- LLM-generated outbound copy.

The X and YouTube methods in V1 are **fixture adapters only**. They normalize records already supplied by tests or a future approved ingestion layer.

A later live ingestion layer must have its own policy and platform review. It may not weaken the outbound approval boundary by changing an adapter.

## Spam/noise handling

V1 applies deterministic penalties to obvious promotional/noise markers such as guaranteed-profit claims, unrealistic win-rate language, VIP promotion, referral/promo language, giveaways/airdrops, repeated characters, and multiple URLs.

At sufficiently high penalty, the observation is `hard_block = true` and the action is forced to `ignore` even if BTC-signal keywords are present.

## Identity, dedupe, and cooldown

`opportunity_id` is deterministic from `source + source_ref`.

`fingerprint` is deterministic from the contract version, source identity, and normalized public text.

Deduplication treats either repeated `opportunity_id` or repeated fingerprint as duplicate. This means an edited post/comment with the same stable platform source id does not become a new acquisition opportunity.

`cooldown_key` is derived from source + public author reference + primary intent when author reference exists, otherwise from source + source reference + intent.

V1 provides a deterministic cooldown check with a six-hour default. It does not persist cooldown state or perform an action.

## Research Feed enrichment

Intent Radar may receive Research Feed items from P0.

Only items with:

```text
distribution.eligible = true
```

may be attached to `research_links`.

Matching is deterministic lexical/topic overlap using:

- observation terms;
- intent-specific research terms;
- research title/summary;
- research topics/tags.

The radar returns only references, match scores, and matched terms. It does not rewrite, summarize, or reinterpret the research conclusion.

Ineligible/stale/degraded Research Feed items are ignored for enrichment.

No research match is a valid state and does not invalidate the intent opportunity.

## Repository and production boundary

Implementation and tests live under:

- `research/intent-radar/includes/`
- `research/intent-radar/tests/`

P1 must not add files under `website/wp-content/**` or modify `config/production-runtime.json`.

Therefore P1 cannot silently enter the WordPress production artifact.

## Consumer rule

Future Content Compiler or Engagement Copilot consumers may read this contract, but they must not interpret `review_for_reply` as permission to send anything.

`review_for_reply` means only:

> a potentially valuable public demand observation deserves human review.

Any future live outbound system requires a separate explicit policy gate and implementation phase.

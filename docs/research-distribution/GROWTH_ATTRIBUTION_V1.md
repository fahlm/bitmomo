# Bitmomo Growth Attribution Ledger V1

Status: P4 CONTRACT / RESEARCH-TOOLING ONLY / NO PRODUCTION TRACKING  
Event contract: `growth-event-v1`  
Ledger: `growth-attribution-ledger-v1`  
Report: `growth-attribution-report-v1`  
Tracks: #152  
Depends on: PR #144 Research Feed, PR #146 Intent Radar, PR #149 Content Compiler, PR #151 Engagement Copilot.

## Purpose

P4 closes the measurement loop between Bitmomo research/distribution activity and qualified whitelist outcomes while preserving strict privacy and non-causal semantics.

It answers:

1. Which research/intent/keyword/channel/format/campaign is associated with downstream funnel activity?
2. Which observations have enough sample to be worth learning from?
3. What was the first and last measurable touch before a qualified whitelist completion?
4. Can the engine measure this without storing direct personal identifiers?

P4 does **not** instrument production. It only defines and tests the event/ledger/reporting contracts.

## Supported funnel events

- `intent_detected`
- `draft_created`
- `operator_approved`
- `content_published`
- `profile_visit`
- `site_click`
- `btc_page_view`
- `whitelist_start`
- `whitelist_complete`
- `paid_activation`

`whitelist_complete` is the primary qualified conversion event.

## Attribution semantics

Every event and report declares:

`observational_not_causal`

First-touch, last-touch, conversion rates, and segment performance are descriptive associations only. P4 does not estimate incremental lift or causal contribution.

## Growth event contract

A valid event contains:

- stable `event_ref` supplied by the producer;
- supported `event_type`;
- `occurred_at` timestamp;
- optional opaque `actor_ref` / `session_ref`;
- lineage fields for research, intent, keyword cluster, channel, format, and campaign;
- a small allow-listed metadata surface.

`event_id` is deterministically derived from event type + event ref. `fingerprint` additionally covers timestamp, anonymous refs, lineage, and metadata. Replayed identical events deduplicate.

## Privacy boundary

Actor/session refs are optional. When present they must be opaque refs matching:

- `anon_<hex>`
- `sess_<hex>`

P4 rejects direct PII-shaped data including:

- email fields/values;
- usernames/handles;
- IP addresses;
- URLs in anonymous identifiers/metadata;
- phone-number shaped data;
- common wallet-address shapes;
- explicit `email`, `username`, `handle`, `ip`, `wallet`, `phone`, `name`, or `source_user` fields.

Reports never expose actor refs. Actor refs are used only internally to count unique anonymous journeys and aggregate first/last touch.

## Campaign lineage

For downstream measurable events (`content_published` onward), V1 requires deterministic UTM lineage:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`

Additional optional lineage:

- `research_id`
- `opportunity_id`
- `brief_id`
- `draft_id`
- `intent`
- `keyword_cluster`
- `channel`
- `format`

`intent_detected` requires intent + keyword cluster but does not require downstream UTM fields.

## Ledger behavior

`Bitmomo_Growth_Attribution_V1::build_ledger()`:

1. validates each raw event independently;
2. rejects malformed/privacy-unsafe events without blocking valid ones;
3. deduplicates deterministic replayed events;
4. sorts accepted events by `occurred_at` and stable event id;
5. returns counts, events, duplicates, and rejected diagnostics.

Events may arrive out of order.

## Reporting

`Bitmomo_Growth_Attribution_Report_V1::build()` produces:

- global funnel counts;
- segment reports by channel, intent, research id, keyword cluster, campaign, and format;
- unique anonymous actors per segment when actor refs are available;
- qualified whitelist conversions;
- descriptive whitelist conversion rate;
- aggregate first-touch and last-touch counts;
- minimum-sample learning status.

No report emits raw actor/session refs.

## First / last touch

V1 touch events are:

- `profile_visit`
- `site_click`
- `btc_page_view`

For an anonymously linked journey ending in `whitelist_complete`, the earliest and latest pre-conversion touch are counted by channel, campaign, and intent.

This is descriptive attribution only.

## Learning policy

Default minimum sample:

- 20 unique anonymous actors; and
- 3 unique converted actors.

Below that threshold:

`learning_signal.status = insufficient_sample`

At/above threshold:

`learning_signal.status = eligible_observation`

Even an eligible observation has:

- `winner_recommendation = null`
- `causal_claim_permitted = false`

P4 never automatically allocates budget or declares a winning channel/keyword.

## Repository / production boundary

Implementation/tests live under:

- `research/growth-attribution/includes/`
- `research/growth-attribution/tests/`

P4 must not add:

- WordPress runtime files;
- database writes;
- cookies/pixels;
- browser tracking scripts;
- ad-platform integrations;
- production deployment changes.

## Next integration step

After P4 acceptance, a later instrumentation phase may connect approved owned Bitmomo surfaces to this contract. Production instrumentation must be a separate release-reviewed change with explicit privacy, consent, data-retention, and fail-safe rules.

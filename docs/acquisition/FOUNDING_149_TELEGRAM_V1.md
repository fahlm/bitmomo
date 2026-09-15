# Founding 149 — Telegram Acquisition V1

Status: PREPARED / POST-WHITELIST-V1
Owner: Bitmomo
Primary goal: acquire the first 149 paid Founding Members with one acquisition motion.

## 1. One-jurus decision

Bitmomo will not scale a multi-channel marketing stack for Founding 149.

V1 uses one acquisition motion only:

**targeted Telegram crypto-trader attention -> Bitmomo BTC Decision Brief -> Founding Whitelist -> paid Founding Member**

The first paid placement is intentionally narrow: one Indonesian crypto trading/intelligence channel at a time. The first planned placement is The Liquidity Waves. No second placement is added until the first placement has enough data to judge.

This document is an acquisition contract, not a promise that any external channel will accept or deliver an ad.

## 2. Buyer definition

The target is not a generic crypto-curious audience.

The V1 buyer is an Indonesian BTC/crypto market participant who:

- already consumes trading, macro, derivatives, on-chain, or market-analysis content in Telegram;
- already has money at risk in crypto and takes market decisions at least several times per month;
- has enough disposable income that Rp149.000/month is a rational information-tool purchase rather than a major discretionary expense;
- wants to reduce time spent combining multiple market sources;
- does not need Bitmomo to teach basic crypto concepts.

Founding 149 is explicitly not optimized for first-time crypto users, guaranteed-return seekers, pump groups, or copy-trading buyers.

## 3. Funnel

The canonical V1 funnel is:

1. Telegram Sponsored Message is shown inside one selected crypto trading/intelligence channel.
2. The sponsored destination is the public Bitmomo BTC Intelligence Telegram channel, in accordance with Telegram Ads destination rules.
3. The visitor sees useful BTC Decision Briefs before being asked to buy anything.
4. Each brief is generated from Bitmomo's existing public-safe intelligence boundary. Telegram never calculates a second opinion.
5. The Founding CTA points directly to the canonical Bitmomo Pro whitelist with campaign attribution.
6. The existing whitelist remains the canonical prospect record. No second CRM/subscriber database is introduced.
7. When checkout later becomes canonical, the existing Bitmomo Pro entitlement/payment lifecycle remains the authority for paid seats.

## 4. Source-of-truth boundaries

### Intelligence

Telegram consumes `Bitmomo_Public_Intelligence_Adapter::snapshot()` only.

The public Telegram brief follows the same Free contract as `/btc-intelligence/`:

**Now + Change + Meaning + One Watch**

Allowed public concepts include:

- BTC reference price;
- directional bias;
- public confidence;
- public-safe driver(s);
- allowlisted public change statements;
- allowlisted public meaning statements;
- exactly one allowlisted public watch context;
- canonical freshness/provenance time.

Telegram must never expose or infer protected Pro fields such as:

- expected range;
- bull/base/bear scenario contract;
- monitoring conditions;
- invalidation;
- private/internal reason codes;
- raw derivatives kitchen metrics that the public BTC surface intentionally hides.

If the canonical snapshot is delayed, unavailable, malformed, or missing a required public-safe field, Telegram fails closed. It does not invent a brief.

### Acquisition record

The existing `Bitmomo_Pro_Whitelist` record is canonical. It already owns:

- email;
- source;
- landing page;
- UTM source/medium/campaign;
- referrer;
- waiting/invited/converted status;
- validation class.

No new marketing database is required for V1.

## 5. Campaign naming

First-placement campaign id:

`founding149_tlw_v1`

Canonical CTA URL from the Telegram brief:

`https://bitmomo.id/pro/?utm_source=telegram&utm_medium=channel&utm_campaign=founding149_tlw_v1#bm-pro-whitelist`

The sponsored-message destination itself is the Bitmomo public Telegram channel. The website URL above is used inside the Bitmomo channel brief so the existing whitelist captures the campaign attribution.

Do not add a second campaign id until V1 has enough evidence to make a keep/change/stop decision.

## 6. Canonical Telegram brief

The brief must be short enough to be read inside Telegram without opening another page.

Required structure:

```text
BTC DECISION BRIEF
<timestamp WIB>

Bias: <Bullish/Netral/Bearish> · Confidence: <n>%
BTC: $<reference price>
Driver utama: <public-safe driver>

APA YANG BERUBAH
• <allowlisted public change>

MENGAPA PENTING
• <allowlisted public meaning>

PANTAU BERIKUTNYA
• <one allowlisted public watch>

Bukan sinyal beli/jual.
Founding 149: <attributed URL>
```

A formatter implementation lives in the BTC Intelligence plugin and is intentionally transport-agnostic. A Telegram bot sender is a separate transport concern and must not be allowed to alter the intelligence content.

## 7. Measurement

Primary business metric:

**paid Founding Members attributable to `founding149_tlw_v1`**

Until checkout is enabled, the leading conversion metric is:

**new canonical whitelist records with `utm_campaign=founding149_tlw_v1`**

Telegram Ads owns ad-delivery metrics such as impressions/clicks. Bitmomo owns downstream whitelist and paid-state truth. These numbers must not be blended into one fabricated metric when one side is unavailable.

The browser already emits provider-neutral whitelist events (`whitelist_view`, `whitelist_submit`, `whitelist_created`, `whitelist_duplicate`, `whitelist_error`) with acquisition-only context. V1 does not add an analytics vendor merely for this campaign.

### P0 test decision rule

The first test is not judged by followers or reactions. It is judged by downstream demand.

Use a meaningful initial sponsored sample (planned operating sample: 20,000 impressions) before declaring the placement ineffective, unless a hard failure is obvious earlier.

Hypothesis gate for the first sample:

- campaign-attributed new whitelist records: target >= 40;
- duplicate signups are not counted as new demand;
- obvious internal/test records are excluded;
- if the sample materially misses the gate, change the message/offer before adding another marketing channel;
- if it clears the gate, scale the same motion before introducing a second acquisition motion.

The >=40 threshold is an internal operating hypothesis for V1, not an industry benchmark.

## 8. Manual prerequisites outside the repository

The repository must never contain Telegram credentials.

Before live transport is enabled, an operator must create/confirm:

1. a public Bitmomo BTC Intelligence Telegram channel;
2. a Telegram bot with permission to post to that channel, if automated posting is used;
3. bot token and destination chat/channel id stored only in the approved secret/runtime environment;
4. a Telegram Ads account/campaign targeted to the first selected placement;
5. the canonical Founding CTA URL above.

No bot token, phone number, personal Telegram account session, or payment credential may be committed to Git.

## 9. Release boundary

This work is deliberately isolated from the Whitelist V1 release freeze.

Before any merge/deploy:

- rebase or recreate the feature from the post-release canonical head;
- run existing BTC Intelligence and launch-surface tests;
- run the Telegram brief contract test;
- verify no public Pro-only fields appear in generated Telegram text;
- keep checkout state unchanged unless a separate release explicitly enables it.

## 10. Acceptance criteria

P0 infrastructure is ready when:

- [ ] canonical Telegram brief can be generated from a fresh public snapshot;
- [ ] delayed/unavailable public snapshot fails closed;
- [ ] Pro-only fields cannot leak into Telegram output;
- [ ] one attributed Founding CTA is embedded in the brief;
- [ ] no new subscriber/CRM database exists;
- [ ] no Telegram secret exists in the repository;
- [ ] campaign-attributed whitelist records can be identified using existing UTM metadata;
- [ ] transport can be added later without changing intelligence logic.

Only after these pass should automated Telegram delivery be wired.
# Founding 149 — Telegram Acquisition V1

Status: RECONCILED SOURCE CANDIDATE / STAGING NOT YET DEPLOYED
Owner: Bitmomo
Primary goal: acquire the first 149 paid Founding Members with one acquisition motion.

## 1. One-jurus decision

V1 uses one acquisition motion only:

**targeted Telegram crypto-trader attention → @bitmomodaily → Bitmomo BTC Decision Brief → Founding Whitelist → paid Founding Member**

Canonical identities:

- public channel: `@bitmomodaily` (`https://t.me/bitmomodaily`);
- posting bot: `@Bitmomo_id_bot` (`https://t.me/Bitmomo_id_bot`).

The first planned paid placement is The Liquidity Waves. No second placement is added until the first placement has enough downstream conversion evidence to judge.

## 2. Buyer definition

The V1 buyer is an Indonesian BTC/crypto market participant who already consumes trading, macro, derivatives, on-chain, or market-analysis content; has money at risk; values time saved combining multiple sources; and can rationally pay Rp149.000/month for an information tool.

This motion is not optimized for first-time crypto users, guaranteed-return seekers, pump groups, or copy-trading buyers.

## 3. Funnel

1. Telegram sponsored attention reaches one selected crypto trading/intelligence audience.
2. Destination is the public Bitmomo channel `@bitmomodaily`.
3. The visitor sees useful BTC Decision Briefs before being asked to buy.
4. Every brief is derived from Bitmomo's existing public-safe intelligence boundary; Telegram never calculates a second opinion.
5. The Founding CTA points to the canonical Bitmomo Pro whitelist with campaign attribution.
6. The existing whitelist remains the canonical prospect record. No second CRM/subscriber database is introduced.
7. Paid membership remains governed by the existing Bitmomo Pro entitlement/payment lifecycle.

## 4. Intelligence boundary

Telegram consumes `Bitmomo_Public_Intelligence_Adapter::snapshot()` only through `Bitmomo_Btc_Telegram_Brief`.

Public contract:

**Now + Change + Meaning + One Watch**

Allowed concepts include BTC reference price, directional bias, public confidence, public-safe drivers, allowlisted change/meaning/watch context, and canonical freshness/provenance time.

Telegram must never expose or infer protected Pro fields such as expected range, bull/base/bear scenario contract, monitoring conditions, invalidation, private reason codes, or raw derivatives kitchen metrics hidden by the public BTC surface.

If the canonical snapshot is delayed, unavailable, malformed, or incomplete, publication fails closed.

## 5. Transport boundary

`@Bitmomo_id_bot` is transport only. It must never calculate, rewrite, summarize, enrich, or infer intelligence.

A live send must verify the runtime token through Telegram `getMe`; a username other than `Bitmomo_id_bot` fails closed. It must then verify channel membership/posting rights through `getChatMember` before any `sendMessage` request.

The token is runtime-only. It must never be committed to Git or saved as a WordPress option.

Delivery and autopost are separate kill switches. Autopost uses the existing `bitmomo_ai_daily_generation` hook and creates no second scheduler.

## 6. Canonical campaign attribution

Campaign id:

`founding149_tlw_v1`

CTA:

`https://bitmomo.id/pro/?utm_source=telegram&utm_medium=channel&utm_campaign=founding149_tlw_v1#bm-pro-whitelist`

The existing `Bitmomo_Pro_Whitelist` owns email, source, landing page, UTM metadata, referrer, status, and validation class. The founder scoreboard reads those existing records only; it does not create another acquisition database.

## 7. Canonical Telegram brief

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

`Bitmomo_Btc_Telegram_Brief::current()` is the one publishable-text entry point. The transport must not rebuild the message itself.

## 8. Measurement

Primary business metric: paid Founding Members attributable to `founding149_tlw_v1`.

Until checkout is enabled, the leading conversion metric is new canonical whitelist records with `utm_campaign=founding149_tlw_v1`, excluding duplicate and internal/test records.

The first sponsored sample is planned around 20,000 impressions. Internal hypothesis gate: >=40 new external whitelist records before scaling the same motion. This is an internal experiment rule, not an industry benchmark.

## 9. Runtime and staging boundary

The Telegram slice is now being reconciled onto the current canonical Whitelist/Home/Research release line. Release engineering must not deploy the historical feature branch directly and must not cherry-pick code on the server.

Once the integration PR is merged and a new Full Release artifact passes, staging must use that one immutable release artifact. The runtime artifact must include:

- `Bitmomo_Btc_Telegram_Brief`;
- `Bitmomo_Btc_Telegram_Transport`;
- `Bitmomo_Btc_Telegram_Publisher`;
- `Bitmomo_Pro_Founding149_Acquisition`.

Baseline staging safety keeps Telegram delivery, autopost and Telegram staging-canary mode OFF.

A controlled Telegram canary may be enabled only after baseline safety, parity and regression checks pass. The canary exception is deliberately bounded to the canonical `api.telegram.org` host and only `getMe` GET, `getChatMember` GET, and `sendMessage` POST while explicit staging + delivery + canary flags are all active. The global staging outbound guard remains active for every other destination.

## 10. Acceptance criteria

P0 infrastructure is accepted only when:

- canonical Telegram brief is generated from a fresh public snapshot;
- delayed/unavailable public snapshot fails closed;
- Pro-only/private fields cannot leak;
- attributed Founding CTA is present;
- canonical channel and bot identities are machine-verified;
- no new subscriber/CRM database exists;
- no Telegram secret exists in the repository/artifact;
- runtime artifact contains all four Telegram/acquisition classes;
- all Telegram deterministic tests and existing BTC/Pro/release regressions pass;
- baseline staging side-effect guard remains intact;
- one controlled staging post succeeds only through the narrow canary gate;
- repeating the identical brief returns `duplicate_brief` and creates no second post;
- no second scheduler exists;
- autopost remains OFF until separate Stage B approval;
- production remains unchanged until a separate production go/no-go.

Operational configuration lives in `BITMOMODAILY_RUNTIME_CONFIG.md`; staging execution lives in `BITMOMODAILY_STAGING_DEPLOY_HANDOFF.md`; channel content lives in `BITMOMODAILY_LAUNCH_PACK.md`.

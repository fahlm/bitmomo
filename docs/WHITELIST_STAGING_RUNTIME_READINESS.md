# Whitelist V1 — Staging Runtime Readiness

**Release authority:** `release/whitelist-v1` / PR #135  
**Purpose:** prevent a technically correct artifact from reaching staging with incomplete WordPress content, stale market data, broken communication surfaces, or misleading product states.

A Whitelist V1 staging release is not complete when files are merely deployed. The release envelope has four inseparable layers:

1. **Runtime code** — exact deterministic artifact from the frozen canonical release head.
2. **WordPress content/database state** — canonical legal copy, qualified Research content, required pages and product data exist in the staging database.
3. **Environment/configuration state** — market-data pipeline, whitelist persistence and safe communication boundaries are operational while checkout/production side effects remain disabled.
4. **Acceptance evidence** — source/artifact/runtime identity, cache/CDN bytes, browser/accessibility behavior and product-readiness assertions are recorded for the exact candidate.

## 1. Runtime code

Stage only the exact artifact produced from the frozen current head of PR #135 after authoritative source checks execute and pass.

The candidate must include the institutional site system, BTC Decision Ledger, BTC Market Context + integrity fixes, institutional Pro conversion surface, whitelist/account/help improvements, and release provenance/cache-coherence tooling already converged into #135.

Do not deploy artifacts from #119/#121/#122/#123 or the historical `release/staging-home-chrome-integration` line.

## 2. WordPress content/database readiness

Before whitelist launch acceptance:

- `kebijakan-privasi` must be published **and semantically current** with `docs/content/kebijakan-privasi.md`;
- `disclaimer` must be published **and semantically current** with `docs/content/disclaimer.md`;
- at least **2 qualified Market Research publications** must satisfy the canonical Research classification boundary;
- generic legacy `Riset` or `ai-lab` content must not be promoted as Market Research merely to populate the page;
- the Research Hub must lead to at least one qualified article that passes article-reading acceptance;
- About, Help, Pro, BTC Intelligence and Pro Account canonical routes must resolve normally.

For whitelist-only launch, Terms may remain absent while checkout remains disabled. Paid launch uses the stricter paid-readiness profile and issue #127.

## 3. BTC operational readiness

The public BTC proof surface is the primary product credibility boundary.

Whitelist launch requires:

- canonical public snapshot `available=true`;
- state is `fresh` or `delayed`;
- the snapshot has a valid `as_of` timestamp;
- snapshot age is no greater than **30 hours** by default;
- anything beyond the age budget is launch-blocking rather than presented as arbitrarily old `delayed` intelligence.

The current engine is already stricter than an unlimited delayed state: its canonical free projection becomes unavailable after its delayed-age budget. Staging readiness independently records the observed age so the release report contains evidence rather than only a label.

## 4. Whitelist and communication readiness

Whitelist V1 is deliberately **email-only by default**. Optional WhatsApp code may remain dormant in the codebase, but it is not a launch capability until a later release explicitly enables and accepts it.

Whitelist-only launch requires:

- public whitelist AJAX action registered;
- checkout remains disabled;
- WhatsApp opt-in remains disabled/fail-closed;
- no WhatsApp field, button, record token, or WhatsApp notification promise is exposed to a normal Whitelist V1 visitor;
- the consent surface links directly to the canonical Privacy page;
- staging database persistence works through the real whitelist write path;
- missing consent is rejected without creating a record;
- a valid signup creates exactly one canonical private record;
- a duplicate normalized email resolves to that same record and does not resend confirmation;
- confirmation-email generation reaches the canonical `wp_mail` boundary with the expected recipient and email-only trust copy;
- the staging readiness probe short-circuits `wp_mail` before transport, so the probe cannot send an external message;
- the temporary probe record is deleted and verified absent afterwards;
- production mail transport receives its own bounded internal-recipient preflight before real audience traffic.

MailPoet is **not** the whitelist confirmation transport today. The current confirmation path is `Bitmomo_Pro_Email_Service` → `wp_mail()`.

The dormant WhatsApp implementation has its own deterministic test suite with the explicit capability flag enabled. Enabling it in a future release requires its own runtime/browser/transport acceptance and an updated Privacy/communication release decision; it must never become live merely because dormant code exists.

## 5. Product-story consistency

Cold visitors must encounter one coherent product story across homepage, Pro, Help and email:

- public product name: **BTC Intelligence**;
- Pro must not statically claim `Decision View aktif hari ini` or `aktif setiap hari` independently of runtime data quality;
- current intelligence is shown only when the relevant quality/freshness gates pass;
- future capability such as Watchtower, Telegram Alerts and 11 AI Analysts remains explicitly roadmap/not-live;
- Help must not route the product journey back into legacy `/category/tren-ai/` publisher IA;
- support links use the canonical configured support email rather than a hard-coded public address;
- Founding Price language must not promise lifetime pricing after membership lapses or guarantee a future new-member price change.

## 6. Newsletter trust boundary

A visibly broken newsletter is worse than no newsletter during whitelist launch.

- if MailPoet's public form shortcode is available, the compact footer Email Brief surface may render;
- if it is unavailable, the newsletter surface remains deterministic in the DOM for testing but is visually fail-closed;
- never render `Subscribe email sementara tidak tersedia.` or another public broken-capability message;
- the legacy MailPoet popup/modal remains structurally disabled;
- activating MailPoet must not silently reintroduce generic publisher/newsletter acquisition ahead of the BTC → proof → Pro → whitelist funnel.

## 7. Optional/fail-closed launch surfaces

The following do **not** block whitelist-only launch when honestly absent:

- Telegram/YouTube/X links — render only verified HTTPS destinations;
- Terms — required before paid checkout, not before whitelist-only launch;
- newsletter — hide when unavailable;
- Gold Market Context provider — may remain explicitly unavailable if the release report accepts that state and BTC/ETH/SOL continue to work.

WhatsApp differs from the optional items above: for **Whitelist V1 the expected state is explicitly OFF**, not merely "optional". A future release may change that expected state only after the channel is intentionally promoted through its own release gates.

## 8. Acceptance evidence

A staging handoff is incomplete without all of the following tied to the exact candidate:

- source commit SHA + tree SHA;
- artifact ID/name + SHA-256;
- staging filesystem/source/tree/hash parity;
- guest-facing first-party asset/CDN byte coherence;
- fresh-cache and warm-cache behavior;
- 360 / 390 / 390x568 / 768 / 1024 / 1440 responsive acceptance;
- keyboard navigation, Escape/focus-return, 200% zoom;
- Axe serious/critical = 0 at required viewports;
- console/page errors = 0;
- Market Context range/race/provider-failure states;
- BTC snapshot age and operational state;
- qualified Research count and Research → article journey;
- legal semantic-current checks;
- whitelist form + Privacy-link + email-only channel boundary;
- whitelist persistence/dedupe/confirmation generation/cleanup contract;
- no legacy Tren AI trust-path link;
- no visible broken-newsletter message;
- checkout disabled for whitelist profile.

Only after this envelope passes is Whitelist V1 product-ready for an explicit production authorization decision.

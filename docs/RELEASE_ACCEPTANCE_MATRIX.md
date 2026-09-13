# Bitmomo Integrated Release Acceptance Matrix

This is the canonical release gate for the public launch candidate that reconciles the institutional chrome/homepage/design system with BTC Market Context. Do not promote an older stacked artifact merely because its individual PR is green.

## 0. Source of truth

- Candidate must contain the current `release/whitelist-v1` product/site/BTC convergence state plus every reviewed launch-readiness patch deliberately reconciled into it.
- One final artifact only. Record branch head SHA, PR merge-ref SHA, artifact ID/name, artifact SHA-256 and runtime file count in the release report.
- Current integrated runtime contract: **117 managed files** before any additional runtime file is intentionally added; theme 46, bitmomo-ai 24, BTC Intelligence 8, Bitmomo Pro 27, regime 12.
- `custom.css` remains frozen. Do not add emergency visual overrides there.
- Production is untouched until every P0 gate below passes on canonical staging.

## 1. P0 — source / CI / artifact

Required:

- Full Release Safety = PASS.
- Authority Surface Safety = PASS.
- Theme Safety / UI architecture = PASS.
- Homepage Research Boundary = PASS.
- All deterministic PHP suites = PASS, including the default email-only whitelist contract and the separately enabled dormant WhatsApp capability contract.
- All first-party JavaScript syntax checks = PASS.
- Artifact builds twice byte-identically and file count matches the manifest.
- BTC Intelligence asset version is newer than the previous public CSS/JS release; Bitmomo Pro version is newer than the previous Pro CSS release. Production must not depend on a manual browser cache purge.
- Market Context is read-only and never queries current protected Pro records.
- Market Context range failure clears stale payload/compare state; a failed new range must never be able to render the previous range under the new UI selection.
- A first history observation without a known prior observation must not be labeled as a thesis/state transition.
- Only closed Binance daily candles may be labeled daily close.
- Gold stays fail-closed when its provider/key is unavailable; never substitute PAXG or another proxy and call it Gold.
- Current Pro Expected Range / Scenario Map / Invalidation remain unavailable in the public Market Context contract.
- Pro/Help/whitelist source contracts do not promise a capability merely because dormant code exists.

## 2. P0 — staging environment identity

Before visual QA, record:

- exact staging URL;
- deployed artifact ID + SHA-256;
- theme/plugin versions returned by runtime health/admin;
- UTC and Asia/Jakarta timestamps;
- database/environment identity;
- confirmation that outbound production mail/webhooks/checkout actions remain disabled or staging-safe.

If artifact/runtime identity cannot be proven, stop. Do not visually approve an unknown environment.

## 3. P0 — responsive public-surface matrix

Run at **360 / 390 / 768 / 1024 / 1440**, including short-height **390x568**, for:

- `/`
- `/btc-intelligence/`
- `/pro/`
- `/help/`
- `/category/riset/`
- one qualified Research article
- `/tentang-kami/`
- `/kebijakan-privasi/`
- `/disclaimer/`
- `/pro/account/`
- search results
- 404

For every surface:

- HTTP status is correct.
- exactly one visible H1;
- no header/H1 collision;
- no horizontal overflow;
- canonical header/footer present;
- active navigation semantics correct where applicable;
- internal fragment links resolve;
- no empty links;
- no legacy newsletter modal;
- footer newsletter appears exactly once in the deterministic DOM and is visually fail-closed when unavailable;
- no public `Subscribe email sementara tidak tersedia.` broken-capability message;
- no legacy `/category/tren-ai/` trust-path link;
- keyboard focus order is logical;
- mobile targets are at least 44px where touch interaction is expected;
- 200% text zoom remains usable with no clipped critical content;
- browser console/page errors = 0;
- Axe serious/critical = 0 at minimum 390 and 1440.

Also test short-height mobile navigation: menu must scroll internally, hidden links must not receive focus while closed, and Escape must close the menu and return focus to the hamburger control.

## 4. P0 — BTC Intelligence / Market Context

### Canonical Decision View

- Direction, Market State, Confidence, reference price and freshness agree with the public-safe canonical adapter.
- stale/delayed/unavailable states are visually explicit and never fabricated.
- public snapshot has a valid `as_of` timestamp and is within the whitelist launch age budget (default **30 hours**); older intelligence is launch-blocking rather than accepted as arbitrarily old `delayed` data.
- Pro/Help copy does not statically claim `Decision View aktif hari ini` or `aktif setiap hari` independently of runtime state.
- What Changed is visually prior to supporting Why in enhanced experience.
- server-rendered Decision View remains complete if Market Context JS or provider calls fail.
- BTC shell width does not visibly jump when JavaScript initializes.

### Explorer ranges

Exercise **7D / 30D / 90D / YTD / 1Y**.

For each range:

- selection state (`aria-pressed`) matches the visible range;
- x-axis dates match the requested range;
- BTC starts at indexed 100;
- actual BTC source values in tooltip match source points;
- changing ranges never shows data from the previous range after an error;
- rapidly changing ranges cannot let a late older response overwrite the latest selection.

### Asset comparison

Exercise BTC plus Gold / ETH / SOL combinations:

- maximum 3 active series;
- comparison baseline is common and meaningful;
- each line remains distinguishable without relying only on hue (line pattern/weight plus legend label);
- ETH/SOL/BTC use closed Binance daily candles;
- Gold uses the configured Gold provider only;
- if Gold is intentionally unconfigured, the state is honest and does not break BTC/ETH/SOL. For production, either configure Gold or explicitly accept the unavailable state in the release report.

### Markers

- first known record is not falsely shown as a transition;
- state/bias transition markers correspond to actual prior→current change;
- Decision Ledger markers correspond to public ledger records;
- for 90D/YTD/1Y, reviewer must understand marker-history coverage. Absence of a marker must not be presented as proof that no historical transition occurred outside available recorded history.

### Failure modes

Test deliberately:

- Market Context REST failure;
- Binance failure/timeout;
- Gold key absent;
- Gold provider failure/rate limit;
- JavaScript disabled;
- slow network / aborted range switch.

In all cases core BTC Intelligence remains usable or fails closed honestly.

## 5. P0 — conversion and trust

### Whitelist V1

Whitelist V1 is **email-only by default**. Dormant WhatsApp code does not make WhatsApp a launch capability.

Test the real staging flow, not only stubs:

- valid email;
- invalid email;
- consent absent;
- consent links directly to canonical Privacy;
- duplicate normalized email;
- success state and focus movement;
- exactly one canonical private record for a new email;
- duplicate resolves to the same record and does not resend confirmation;
- checkout is disabled;
- WhatsApp opt-in is disabled/fail-closed: no phone field/button, no record token exposure, no WhatsApp notification promise;
- confirmation email is generated exactly once for a new signup through the canonical `wp_mail` boundary;
- staging readiness short-circuits the mail transport so the probe cannot send externally;
- temporary readiness record is removed after the probe;
- narrow viewport overflow = 0;
- no fake seat reservation/urgency;
- no payment implication while checkout is unavailable.

Before real audience traffic, run a bounded production mail-transport preflight to an internal recipient. A successful staging generation probe is not proof of inbox delivery.

A future release that enables WhatsApp must explicitly update the release expectation and pass separate runtime/browser/transport/privacy acceptance; it must not become live accidentally through dormant code.

### Pro / Account / Help

- commercial action uses canonical orange hierarchy;
- login/account state resolves correctly;
- lost-password path works;
- protected content remains protected;
- Help deep links/FAQ controls work by keyboard;
- Help consistently calls the public product **BTC Intelligence**, not the retired `BTC Daily Intelligence` label;
- Help does not route users back into legacy `/category/tren-ai/` publisher IA;
- support links use the canonical configured support email;
- founding pricing copy does not promise lifetime pricing after lapse or guarantee a future new-member price change;
- checkout URL remains fail-closed until explicitly configured.

### Footer / social / legal

- social destinations are fail-closed: only configured validated HTTPS URLs may render;
- if release expects Telegram/YouTube/X, record expected URLs and verify exact destinations;
- Terms link appears only when the canonical published Terms page exists;
- Privacy is published and semantically current with the Whitelist V1 email-only default plus conditional future WhatsApp language;
- Disclaimer is published and semantically current with current product/data/accountability boundaries;
- a merely published stale legal page is a release failure.

## 6. P0 — Research / Google entry path

- at least **2 qualified Market Research publications** exist in the staging database before whitelist launch;
- Research Hub shows only qualified institutional research under the canonical classification boundary;
- no AI Lab / generic legacy Riset leakage into the market Research promise;
- homepage Research can render genuine qualified BTC/market work instead of disappearing or being padded with unrelated content;
- Research Hub navigation/search/empty-state copy is coherent for the Indonesian audience while retaining intentional technical vocabulary;
- article title/deck/meta/body typography is clean and deterministic across devices;
- reading column remains approximately 720px desktop; wide research figures remain bounded;
- article tables scroll horizontally rather than breaking the viewport;
- no injected duplicate disclaimer/newsletter/body H1;
- related research classification is correct;
- search/legacy utility/archive side doors remain noindex where contracted.

## 7. P1 — quality improvements after P0 is green

These should be reviewed before paid scale, but do not independently justify delaying a safe whitelist launch unless staging reveals material UX damage:

- provide a non-pointer/keyboard equivalent for historical chart tooltip values;
- expose explicit marker-history coverage metadata for long ranges;
- minimize the Market Context REST `current` object to fields actually consumed by the public explorer;
- replace vendor-owned newsletter presentation with a first-party Bitmomo renderer while retaining MailPoet only as backend if useful;
- remove frozen legacy header/footer declarations from `custom.css` only after integrated staging parity is proven;
- author/review/publish canonical Terms before paid checkout goes live;
- measure LCP/CLS/INP on the integrated staging artifact and investigate regressions rather than adding arbitrary animation/performance hacks.

## 8. Production promotion

Only after staging PASS:

1. record fresh backup and rollback point;
2. deploy the exact accepted artifact — do not rebuild from another ref;
3. purge server/CDN cache once as deployment hygiene, while asset versions still guarantee browser invalidation;
4. verify health/version and artifact identity;
5. smoke home, BTC Intelligence, Pro, Research article, whitelist and account;
6. verify canonical/robots/title/description on key indexable pages;
7. verify HTTP security/cache headers appropriate to the actual Hostinger/Cloudflare runtime;
8. verify no staging banner/config leaks and no production outbound integration is unexpectedly disabled;
9. run bounded internal-recipient mail transport preflight before opening whitelist traffic;
10. monitor console/runtime errors and conversion events after release.

If production differs from accepted staging in source, database contract or runtime configuration, the staging approval does not transfer: stop and reconcile.
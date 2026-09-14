# Bitmomo Integrated Release Acceptance Matrix

This is the canonical release gate for the public Whitelist V1 launch candidate. It is a **product acceptance contract**, not merely a code checklist. A green source build cannot override an incorrect or contradictory product requirement.

## 0. Source of truth

- One and only active whitelist release authority: `release/whitelist-v1` / PR #135.
- Runtime identity and file counts are owned by `config/production-runtime.json`; do not duplicate a hard-coded current file count here.
- `docs/CURRENT_RELEASE.md` owns current mutable release status and exact candidate identity.
- This document owns stable acceptance requirements.
- `custom.css` remains frozen legacy debt. Do not add emergency public-surface overrides there.
- Production is untouched until every P0 gate below passes on canonical staging.
- A candidate under **AUDIT HOLD** must not be promoted even if its previous CI run was green.

## 1. P0 — source / CI / artifact

Required:

- Full Release Safety = PASS.
- Authority Surface Safety = PASS.
- Theme Safety / UI architecture = PASS.
- Regime Safety = PASS.
- Homepage Research Boundary = PASS.
- Institutional public-copy contract = PASS.
- Navigation/footer contract = PASS and agrees with the public-surface/browser contracts.
- All deterministic PHP suites = PASS, including email-only whitelist behavior and dormant WhatsApp enabled-mode tests.
- All first-party JavaScript syntax/contracts = PASS.
- Artifact builds twice byte-identically and file count matches `config/production-runtime.json`.
- Artifact provenance binds the exact accepted commit/tree.
- No CI assertion may intentionally contradict another canonical product contract. If a contradiction is found, the release is blocked until the intended product behavior is reconciled and tests are updated.

BTC / Market Context integrity remains mandatory:

- Market Context is read-only and never queries current protected Pro records.
- Range failure clears stale payload/compare state; a failed new range cannot render the previous range under the new selection.
- First history observation without a known prior observation is never labeled as a transition.
- Only closed Binance daily candles may be labeled daily close.
- Gold stays fail-closed when its provider/key is unavailable; never substitute PAXG or another proxy and call it Gold.
- Current Pro Expected Range / Scenario Map / invalidation remain unavailable in the public Market Context contract unless the release explicitly changes that boundary.
- Pro/Help/whitelist source contracts do not promise a capability merely because dormant code exists.

## 2. P0 — staging environment identity

Before visual QA, record:

- exact staging URL;
- deployed artifact ID + SHA-256;
- manifest source commit/tree + runtime file count;
- theme/plugin versions returned by runtime health/admin;
- UTC and Asia/Jakarta timestamps;
- database/environment identity;
- confirmation that outbound production payment/webhook actions remain disabled or staging-safe.

If artifact/runtime identity cannot be proven, stop. Do not visually approve an unknown environment.

## 3. P0 — responsive public-surface matrix

Run at **360x800 / 390x568 / 390x844 / 768x1024 / 1024x900 / 1440x1000**, plus **200% text zoom**, for:

- `/`
- `/btc-intelligence/`
- `/pro/`
- `/help/`
- `/category/riset/`
- at least two qualified Research articles where available
- `/tentang-kami/`
- `/kebijakan-privasi/`
- `/disclaimer/`
- `/pro/account/` logged out
- search results
- canonical 404

For every surface:

- HTTP status is correct;
- exactly one visible H1;
- no header/H1 collision;
- no horizontal overflow or hidden clipping;
- canonical header/footer present;
- active navigation semantics correct where applicable;
- internal fragments resolve and no empty links exist;
- no legacy newsletter modal;
- exactly one global footer newsletter subscribe surface when newsletter backend is operational;
- newsletter may fail closed when its backend is unavailable, but must not display a broken-capability message;
- no legacy `/category/tren-ai/` trust-path link;
- keyboard focus order is logical;
- expected touch targets remain comfortable on mobile;
- 200% text zoom remains usable without loss of critical content/function;
- browser console/page errors = 0;
- Axe serious/critical = 0 at minimum 390 and 1440.

Short-height mobile navigation must scroll internally; closed menu links must not receive focus; Escape closes and returns focus to the hamburger.

### Overflow integrity

Global overflow suppression is not evidence that a layout fits. Browser acceptance must detect content whose geometry extends beyond its intended container even if `overflow-x` masking prevents a scrollbar. Do not approve clipping that is merely hidden by a global overflow rule.

## 4. P0 — homepage product hierarchy

The homepage must remain one coherent Bitcoin intelligence front door:

1. product promise;
2. current BTC market view;
3. accountability/proof path;
4. concise product mechanism;
5. Founding/Pro conversion;
6. qualified Research;
7. retention/trust footer.

The current market view exposes the canonical visitor terminology:

- **Bias** — Bullish / Netral / Bearish;
- **Confidence** — bounded confidence with explicit non-probability explanation;
- **Referensi BTC**;
- **Faktor Utama** — one concise public-safe driver;
- **Diperbarui** — canonical observation/update time;
- **Sumber Data** — concise allowlisted source label;
- one link to complete BTC Intelligence;
- direct accountability path to Decision Ledger.

Homepage must not grow back into a mini-dashboard, generic crypto media site, AI Lab catalogue, future-capability wall, direct referral card, or duplicated newsletter/conversion wall.

Commercial color semantics are intentional: commercial/paid actions use the commercial action token; free product/navigation actions should not be styled as if they are paid conversion actions.

## 5. P0 — BTC Intelligence / Market Context

### Canonical Decision View

- Direction/Bias, Market State where intentionally exposed, Confidence, reference price and freshness agree with the public-safe canonical adapter.
- stale/delayed/unavailable states are explicit and never fabricated.
- public snapshot has a valid `as_of` timestamp and is within the whitelist launch age budget (default **30 hours**).
- Pro/Help copy does not statically claim a live current Decision View independently of runtime state.
- server-rendered Decision View remains usable if Market Context JS/provider calls fail.
- BTC shell width does not visibly jump when JavaScript initializes.

### Explorer ranges

Exercise **7D / 30D / 90D / YTD / 1Y**:

- selected state matches visible range;
- dates match requested range;
- normalized comparison starts at indexed 100;
- actual tooltip/source values agree with the payload;
- changing ranges never shows stale data from the previous range after error;
- late older responses cannot overwrite the latest selection.

### Asset comparison

- maximum 3 active series;
- common baseline is meaningful;
- lines remain distinguishable without hue alone;
- ETH/SOL/BTC use closed Binance daily candles;
- Gold uses only configured Gold provider;
- unavailable Gold remains honest and does not break crypto series.

### Interaction clarity

Controls or glyphs that look interactive must actually be interactive or be visually recast as labels/status. Do not use link-like arrows on non-links.

### Failure modes

Deliberately test REST/provider failure, Gold key absent, JavaScript disabled, slow/aborted range switches. Core BTC Intelligence remains usable or fails closed honestly.

## 6. P0 — conversion, retention and trust

### Founding Whitelist

Whitelist V1 is email-only by default. Dormant WhatsApp code does not make WhatsApp a launch capability.

Test real staging flow:

- valid email;
- invalid email;
- consent absent;
- consent links to canonical Privacy;
- duplicate normalized email;
- success state and focus movement;
- exactly one canonical private record;
- duplicate resolves to same record and does not resend confirmation;
- checkout disabled;
- WhatsApp disabled/fail-closed;
- confirmation generated exactly once;
- temporary test records cleaned up;
- no fake seat reservation/urgency;
- no payment implication while checkout unavailable.

Run bounded real mail transport preflight to internal recipient before opening audience traffic.

### Newsletter retention

Newsletter and Founding Whitelist are separate user intents and separate consent paths.

Required:

- exactly one compact global-footer newsletter subscribe surface;
- no newsletter popup/modal or second newsletter wall;
- newsletter is visually secondary to commercial Founding/Pro actions;
- do not use the commercial orange treatment for newsletter submit;
- email-only input should minimize friction;
- accessible label, loading, validation, duplicate/subscribed, success and failure states;
- explicit Privacy link/disclosure appropriate to newsletter processing;
- whitelist signup must not silently subscribe a user to newsletter and newsletter signup must not silently create a whitelist record;
- legacy `/subscribe`, `#subscribe`, and `#newsletter` destinations resolve to the footer newsletter anchor, not to Founding Whitelist;
- MailPoet may remain the backend, but visitor presentation must be owned by Bitmomo and not visually look like an embedded vendor widget;
- backend/form identity must be configurable and staging readiness must prove the configured form actually exists;
- launch acceptance must verify a real newsletter subscription lifecycle sufficiently to prove the form is not decorative.

### Pro / Account / Help

- commercial action hierarchy remains clear;
- login/account state resolves correctly;
- lost-password path works;
- protected content remains protected;
- Help deep links/controls work by keyboard;
- product naming is consistent;
- support uses canonical configured identity;
- account/help tone uses one consistent formality level;
- checkout URL remains fail-closed until explicitly configured.

### Footer / social / legal

- footer IA is compact and visually subordinate to page content;
- Product / Research / Bitmomo groups remain distinct from legal utility links;
- newsletter retention row and social links do not become another card/pill wall;
- social destinations are fail-closed and validated HTTPS;
- Terms appears only when a canonical published Terms page exists;
- Privacy and Disclaimer are current, not merely published.

## 7. P0 — Research / Google entry path

- at least **2 qualified Market Research publications** exist before whitelist launch;
- Research Hub shows only qualified institutional Research under the canonical classification boundary;
- no AI Lab/generic legacy Riset leakage into Market Research promise;
- homepage Research renders genuine qualified market work or hides cleanly;
- Research Hub search/filter/empty states remain coherent;
- article title/deck/meta/body typography is deterministic across devices;
- reading column remains approximately 720px desktop; wide figures are bounded;
- article tables scroll locally, not the page;
- related research classification is correct;
- search/legacy utility/archive side doors remain noindex where contracted;
- English research labels/principles are intentional product/program names, not accidental mixed-language filler.

## 8. P0 — editorial / language integrity

`docs/editorial/BITMOMO_INSTITUTIONAL_COPY_SYSTEM_V1.md` is the language authority.

Before release:

- visitor-facing copy must be natural Bahasa Indonesia with intentional retained market/product terms;
- no internal engineering terms leak into explanatory prose;
- no casual phrases, urgency theater, defensive copy or literal translation artifacts;
- labels, tooltips, empty/error/failure states, dynamic factors, meta descriptions, account states and email copy are included;
- final public language should live in the owning renderer when feasible rather than depending on broad runtime string replacement. Any presentation compatibility layer must be narrowly scoped and justified.

## 9. P0 — visual/design-system integrity

- graphite/off-white/slate form the base visual system;
- steel blue is restrained product/data/navigation emphasis;
- amber/orange is reserved for commercial actions;
- positive/negative/warning colors remain semantic;
- direct hard-coded legacy teal/cyan values must not undermine the canonical palette;
- typography hierarchy should feel analytical, not like a developer terminal; monospace is reserved for compact metadata/status contexts;
- surfaces should not become card walls simply to create hierarchy;
- footer/header must remain deterministic across every page type.

## 10. P0 — analytics / PMF observability

Before public whitelist promotion, prove that acquisition and product events have an actual sink; emitting browser events with no persisted analytics receiver is not observability.

At minimum verify privacy-safe capture for:

- landing/source/UTM;
- homepage BTC Intelligence click;
- Decision Ledger/proof interest;
- Pro interest;
- whitelist start/submit/success/duplicate/error;
- newsletter subscribe attempt/success/error;
- BTC Intelligence meaningful view and return visit;
- Research-to-product navigation.

Never include submitted email/phone/PII in analytics events. Document the analytics destination and validate events end-to-end on staging.

## 11. P0 — SEO / social distribution

Verify rendered, not assumed:

- canonical URL;
- index/noindex policy;
- title + meta description;
- sitemap/robots behavior;
- Open Graph/Twitter/X title, description and image on homepage, Pro, Research Hub and representative Research article;
- social preview assets are current, branded and not dependent on a stale generic image;
- no staging hostname leaks into metadata.

Social preview verification is launch-critical because whitelist traffic will be driven from social platforms.

## 12. P0 — release governance

- PR #135 remains the sole release authority.
- `docs/CURRENT_RELEASE.md` is the only mutable current-state release document.
- Static contract documents must not duplicate mutable candidate SHA/artifact/file-count state unless generated/validated automatically.
- `release/whitelist-v1` should be protected or otherwise technically guarded against accidental direct mutation while it is the active release line; policy alone is weaker than enforcement.
- No artifact is staging-eligible while `CURRENT_RELEASE.md` says AUDIT HOLD.

## 13. P1 — quality improvements after P0 is green

Review before paid scale, but do not independently delay a safe whitelist launch unless staging reveals material UX harm:

- remove frozen legacy `custom.css` ownership only after integrated parity is proven;
- provide keyboard/non-pointer access to historical chart values where needed;
- expose marker-history coverage metadata for long ranges;
- minimize Market Context payload to fields actually consumed publicly;
- consider a first-party newsletter renderer over MailPoet backend to reduce vendor coupling;
- author/review/publish canonical Terms before paid checkout goes live;
- simplify Pro page sections that do not answer a unique buyer question or objection;
- strengthen verifiable editorial/analyst authority as the Research corpus grows;
- measure LCP/CLS/INP and investigate actual regressions rather than adding speculative optimizations.

## 14. Production promotion

Only after final staging PASS:

1. record fresh backup and rollback point;
2. deploy the exact accepted artifact — never rebuild from another ref;
3. purge server/CDN cache once as deployment hygiene while asset versions guarantee browser invalidation;
4. verify health/version/artifact identity;
5. smoke homepage, BTC Intelligence, Pro, Research article, whitelist, newsletter and account;
6. verify canonical/robots/title/description/social metadata on key pages;
7. verify runtime security/cache headers appropriate to Hostinger/Cloudflare;
8. verify no staging banner/config leaks;
9. run bounded internal-recipient mail tests;
10. monitor runtime/console errors and acquisition/retention events after release.

If production differs from accepted staging in source, database contract or runtime configuration, staging approval does not transfer: stop and reconcile.

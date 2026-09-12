# Bitmomo Public Surface Contract

## Purpose

No public Bitmomo route may silently inherit an unreviewed Hello Elementor layout. Every public route must have an explicit Bitmomo owner, responsive acceptance, a known content hierarchy, and a deliberate **public information boundary**.

Public pages are product surfaces, not engine observability dashboards. A fact belongs on a public page only when it is useful to a visitor, understandable without knowing Bitmomo's internal architecture, and appropriate to expose outside the engine/research layer.

## Route ownership

| Route class | Owner |
|---|---|
| `/` | `front-page.php` |
| ordinary pages (`/tentang-kami/`, `/kebijakan-privasi/`, `/disclaimer/`) | `page.php` + `.bm-public-page` |
| product shortcode pages (`/btc-intelligence/`, `/pro/`, `/help/`, Pro account/dashboard) | `page.php` pass-through + product renderer |
| posts index | `home.php` -> `template-parts/archive-index.php` |
| category | `category.php` -> shared archive |
| tag | `tag.php` -> shared archive |
| generic archive | `archive.php` -> shared archive |
| search | `search.php` -> shared archive |
| single article | `single.php` |
| unknown/fallback | `index.php` / `404.php` |

## Public information boundary

### Homepage

The homepage answers one question quickly: **what is Bitmomo's current BTC reading?**

Its public intelligence card is deliberately limited to:

- **Arah** — Bullish / Netral / Bearish;
- **Keyakinan** — the exact confidence score plus a plain-language label, explicitly not a price probability;
- **Alasan utama** — one concise public-safe driver;
- **Diperbarui** — the canonical observation/update time;
- one link to deeper BTC Intelligence context.

The homepage must **not** grow back into a mini-dashboard. Do not expose Opportunity internals, activity percentiles, raw 60-minute ranges, Market State taxonomy/certainty, raw derivative fields, engine/classifier identifiers, QA diagnostics, or a duplicate 30-day chart on the homepage.

The homepage launch hierarchy is intentionally narrow:

1. current BTC reading;
2. Bitmomo Pro / Founding conversion;
3. short explanation of how Bitmomo turns market data into a reading;
4. concise market research;
5. footer.

AI Lab belongs on a dedicated research surface, not in the homepage launch funnel. Direct referral cards also do not belong in the launch hierarchy. Referral/platform content may return only as clearly editorial articles with transparent disclosure rather than a promotional card that links directly to an affiliate destination.

### BTC Intelligence

`/btc-intelligence/` owns the deeper public context and accountability layer. It should answer:

1. What is the current BTC reading?
2. Why does Bitmomo read the market that way?
3. What materially changed from the previous canonical reading?
4. How has directional context changed over the recent 30-day record?
5. How has the **current evaluation methodology** performed against future outcomes?

The main public reading may expose:

- reference BTC price;
- directional reading in human language;
- confidence score with a clear explanation;
- market activity translated to `Tinggi / Normal / Rendah` without exposing percentile/range internals;
- up to two public-safe reasons;
- up to two human-readable changes;
- concise data source and exact WIB timestamp.

The 30-day public context is **direction-only**. It may summarize Bullish / Netral / Bearish official daily records and must never fabricate missing days.

The public track record displays only the current compatible directional-evaluation methodology as the primary proof. It may show overall, rolling recent, Bullish and Bearish accuracy with honest conclusive/total denominators and sample status. Older incompatible methodologies stay preserved for audit but must not be mixed into, or presented as peers of, the current headline proof.

The current directional outcome contract is anchored exactly at **+24 hours** from the observation time, using a compatible price provider and exactly 24 closed hourly candles. For Bullish/Bearish outcomes, moves between -0.5% and +0.5% are inconclusive and excluded from the accuracy denominator; conclusive and total counts remain visible.

### What stays in the engine/research layer

The following may exist in adapters, stores, admin diagnostics, tests, or research tooling but are not public UI primitives:

- Opportunity methodology name, activity percentile and raw 60-minute range;
- Market State classifier taxonomy and classifier certainty;
- raw OI, funding, basis, long/short and taker metrics as a customer-facing grid;
- individual five-axis/debug scores;
- engine/classifier/version identifiers;
- confidence calibration buckets;
- Expected Range QA until a genuinely frozen/versioned range methodology exists;
- Regime forward-return QA;
- stale/blocked/missing-data rates and settlement-completeness diagnostics;
- provider implementation/fallback wording;
- source diagnostics, internal evidence, record IDs and private notes;
- Pro-only scenarios, monitoring conditions, watched range and invalidation details on the free surface.

Methodology explanation on `/btc-intelligence/` remains behind progressive disclosure. It explains concepts in visitor language; it is not a dump of engine internals.

## Data-correctness contract behind the public reading

A clean public UI is not permission to weaken the engine. Inputs that affect the displayed reading must be more rigorously validated than the information exposed on screen.

- Directional settlement is exact +24h, provider-compatible and versioned. Late/misaligned windows fail closed rather than borrowing a convenient later endpoint.
- Open-interest `24H` change uses exactly 24 hourly intervals (25 observations), not the first and last values of an arbitrary larger fetch.
- Successful derivative inputs may affect a new reading only while their observation timestamps remain within cadence-aware freshness limits. A successful-but-stale or untimestamped derivative input fails the quality gate rather than silently influencing Bias/Confidence.
- Expected Range public-comparable proof requires a frozen original plus an explicit methodology version. Support/resistance zones must never be relabeled as a forecast Expected Range.
- US pre-open/post-close editions are grouped by canonical US market day rather than raw UTC calendar date.

## Visual contract

- One site header and one site footer.
- One intentional H1 per route.
- No parent-theme page header on owned routes.
- Generic reading width: approximately 780px; archive/product surfaces may be wider.
- Major vertical gaps must be intentional. Do not stack two generic section paddings simply to create separation.
- Conversion surfaces must not repeat the same offer as adjacent standalone sections.
- Redundant conversion/content blocks must be removed from the renderer/template, not merely hidden with CSS.
- Upcoming capabilities are clearly marked as not live.
- Public product/data claims must describe capabilities and inputs that actually exist in the current live system. Do not imply unsupported continuous order-book/on-chain ingestion, real-time monitoring, 24/7 delivery, or other future capability as live.
- Navigation, CTA and disclosure semantics remain usable without color alone.

## Navigation and retention contract

The public header is intentionally small. Its primary information architecture is:

- BTC Intelligence
- Riset
- Tentang
- Masuk
- one commercial `BITMOMO PRO` CTA

`Bitmomo Pro` must not appear a second time as an equal-weight content-navigation link. The active route is expressed with `aria-current="page"` and a non-color visual treatment.

On mobile, a visually collapsed navigation is also removed from keyboard/accessibility traversal. A closed menu uses `inert` + `aria-hidden`; opening restores access. The menu must close after a nav selection, outside click, Escape, and when returning to desktop width.

Newsletter is a retention utility, not a launch conversion hero. There is exactly one permanent public subscribe surface: a compact MailPoet form in the global footer. The former newsletter modal is structurally disabled on every route, and `/subscribe`, `#subscribe`, or legacy newsletter links resolve to the footer anchor rather than opening an overlay.

The footer groups product, research and trust/legal links, then exposes the compact email subscribe form beside canonical social destinations:

- Telegram: `https://t.me/bitmomodaily`
- YouTube: `https://www.youtube.com/@bitmomoid`
- X: `https://x.com/bitmomoid`

These destinations may be changed through the narrow public-link filters, but footer markup must not hard-code divergent copies of the URLs.

## WordPress content ownership boundary

A canonical child-theme wrapper does **not** prove the database content is clean. Legacy Elementor/database markup can survive inside the new public template even after route ownership moves to the child theme.

For ordinary pages, `page.php` owns the route-level H1. Rendered WordPress body content passes through `bitmomo_normalize_public_page_body_headings()`, which structurally demotes body-level H1 tags to H2 so legacy database headings cannot create a second route-level H1. Product shortcode surfaces bypass this normalizer because their renderer owns its heading hierarchy.

This heading normalization is not a substitute for content migration. For `/tentang-kami/`, `/kebijakan-privasi/`, `/disclaimer/`, and any migrated ordinary page, staging acceptance must still inspect the rendered DOM/body as well as the outer template. Legacy Elementor full-page containers, stale layout wrappers, unexplained spacing, obsolete copy, or other database-content defects must be reported and migrated explicitly. Do not conceal database-content defects with arbitrary CSS.

## Mandatory viewport QA before production

Check at **360 / 390 / 768 / 1024 / 1440** on at least:

1. `/`
2. `/btc-intelligence/`
3. `/pro/`
4. `/help/`
5. `/category/riset/`
6. `/tentang-kami/`
7. `/kebijakan-privasi/`
8. `/disclaimer/`
9. one representative article
10. search or 404 fallback

Acceptance:

- no horizontal overflow;
- no duplicate H1, including H1 embedded in WordPress page content;
- no clipped navigation or CTA;
- no accidental empty blocks / unexplained large whitespace;
- consistent header/footer;
- no legacy Hello Elementor or Elementor full-page layout inside ordinary-page body content;
- no PHP fatal or material browser-console error;
- zero serious/critical accessibility violations at acceptance viewports;
- touch targets remain usable on mobile;
- homepage reading remains limited to Arah / Keyakinan / Alasan Utama / update time;
- BTC Intelligence does not expose the engine/QA kitchen-detail list above;
- track-record headline proof uses only the current compatible methodology;
- product fail-closed/data semantics remain unchanged;
- `/pro/` has one canonical conversion path and contains no unsupported live-data claim;
- `/help/` interactive FAQ/accordion behavior remains functional;
- header contains one and only one public Pro destination/CTA;
- desktop nav exposes the intended five destinations and an active-route state;
- mobile menu opens/closes correctly and hidden links are not keyboard-focusable while closed;
- footer email form is compact, usable and does not create a second modal/overlay;
- Telegram, YouTube and X footer destinations resolve to the canonical URLs above.

## CI prevention

Theme/UI architecture checks protect the **information hierarchy**, not a frozen implementation primitive. CI must fail if:

- an owned public template disappears;
- the old media-style posts-index copy returns;
- archive routes diverge from the shared archive surface;
- homepage restores a duplicate BTC card, 30-day engine dashboard, AI Lab block, direct referral block, separate Pro teaser + whitelist, or newsletter hero;
- homepage loses Arah / Keyakinan / Alasan Utama / update time;
- BTC Intelligence re-exposes engine/QA kitchen details;
- methodology stops being progressively disclosed;
- the canonical public-surface styles are no longer loaded;
- ordinary-page body content stops passing through the H1 normalizer;
- the unified homepage whitelist loses its grid shrink/width containment and can overflow narrow viewports.

`check-navigation-footer.mjs` additionally protects one canonical Pro header CTA, `aria-current`, accessible mobile navigation, footer-only newsletter ownership, and canonical social destinations.

The M2 launch-surface contract additionally protects the visitor-first information boundary, provider-neutral conversion, supported-data claims, fail-closed intelligence/provenance behavior, and privacy-safe acquisition/repeat-use telemetry.

## Continuous production checks

The production monitor checks launch-critical routes for HTTP/content availability, runtime/PHP leakage, environment leakage and accidental `noindex`.

Beginning with theme **v4.4 / public snapshot schema 2**, the machine-readable consistency contract is intentionally narrow and mirrors visible public facts only:

- schema;
- availability/status;
- canonical as-of time;
- directional bias;
- confidence label.

It must not serialize Market State, direction-strength detail, Opportunity state or other engine internals simply for monitoring convenience. BTC Intelligence separately exposes concise human trust metadata (`Sumber data` + exact WIB timestamp); provider diagnostics remain internal.

This contract is deliberately structural. CI and synthetic monitoring cannot prove rendered visual quality, WordPress database-body cleanliness, or browser interaction quality; staging browser QA remains mandatory before a whole-site UI candidate is merged or promoted.

# Bitmomo Public Surface Contract

## Purpose

No public Bitmomo route may silently inherit an unreviewed Hello Elementor layout. Every public route must have an explicit Bitmomo owner, responsive acceptance, a known content hierarchy, and a deliberate **public information boundary**.

Public pages are product surfaces, not engine observability dashboards. A fact belongs on a public page only when it is useful to a visitor, understandable without knowing Bitmomo's internal architecture, and appropriate to expose outside the engine/research layer.

The homepage has an additional institutional rule: **clarity before breadth**. It must behave like the front door of one coherent intelligence product, not a catalogue of every Bitmomo project, future capability, content vertical, or monetization experiment.

## Route ownership

| Route class | Owner |
|---|---|
| `/` | `front-page.php` + `assets/css/home.css` |
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

The homepage must answer five questions in order:

1. **What is Bitmomo?** — a BTC market-intelligence product, not a general crypto-news portal.
2. **What is the current reading?** — one concise, timestamped public reading.
3. **Why should I trust it?** — the reading is recorded before outcomes and remains reviewable through the public accountability layer.
4. **How does the product work?** — compression -> decision support -> accountability, in visitor language.
5. **What should I do next?** — use BTC Intelligence, inspect proof, then consider Founding/Pro access.

Its current-reading surface is deliberately compact but finance-grade. It may expose only:

- **Arah** — Bullish / Netral / Bearish;
- **Keyakinan** — the exact confidence score plus a plain-language label, explicitly not a price probability;
- **Referensi BTC** — the public-safe BTC reference price tied to that observation;
- **Alasan utama** — exactly one concise public-safe driver;
- **Diperbarui** — the canonical observation/update time in WIB;
- **Sumber** — one concise public-safe source label from the allowlisted public adapter;
- one link to the complete BTC Intelligence reading;
- one direct link to the Decision Ledger.

The homepage must **not** grow back into a mini-dashboard. Do not expose Opportunity internals, activity percentiles, raw 60-minute ranges, Market State taxonomy/certainty, raw derivative fields, engine/classifier identifiers, QA diagnostics, private provenance/diagnostics, source record IDs, Pro scenarios/ranges/invalidation details, or a duplicate 30-day chart on the homepage.

The reading must fail closed. A delayed state is visibly delayed. An unavailable state must never inherit a visual treatment that implies fresh/current data. No placeholder price, direction, driver, source, or timestamp may be fabricated.

The canonical launch hierarchy is:

1. product positioning + current BTC reading;
2. accountability principles / public proof path;
3. short explanation of how Bitmomo works;
4. one Bitmomo Pro / Founding conversion surface;
5. concise qualified market research;
6. footer utilities.

This order is intentional: **value -> proof -> mechanism -> commitment -> authority**. A commitment CTA must not outrank or precede the direct accountability proof path.

AI Lab belongs on a dedicated research surface, not in the homepage launch funnel. Direct referral cards also do not belong in the launch hierarchy. Future capability walls (11 AI analysts, Watchtower, Telegram automation, etc.) may not return as homepage sections while they are not the current product value. Referral/platform content may return only as clearly editorial articles with transparent disclosure rather than a promotional card that links directly to an affiliate destination.

Homepage market research is a **research desk**, not a generic blog grid. It surfaces at most three currently qualified market-research items with topic, publication date, reading time, title and a concise excerpt. Generic `Riset` membership alone is insufficient; the same institutional research-classification boundary used by the Research Hub applies.

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
- Homepage/public shell uses the shared institutional width; its grid must align with the header/footer shell rather than introducing a private page width.
- Major vertical gaps must be intentional. Do not stack two generic section paddings simply to create separation.
- Conversion surfaces must not repeat the same offer as adjacent standalone sections.
- Redundant conversion/content blocks must be removed from the renderer/template, not merely hidden with CSS.
- Homepage section hierarchy must be readable from spacing/borders/typography without relying on decorative cards for every concept.
- Upcoming capabilities are clearly marked as not live and should not occupy homepage space unless they are necessary to understand the current product.
- Public product/data claims must describe capabilities and inputs that actually exist in the current live system. Do not imply unsupported continuous order-book/on-chain ingestion, real-time monitoring, 24/7 delivery, or other future capability as live.
- Navigation, CTA, freshness and disclosure semantics remain usable without color alone.
- Small text and metadata meet WCAG AA contrast against their actual background; decorative low-contrast styling must never be used for required information.
- Homepage responsive contract is 360 / 390 / 768 / 1024 / 1440 with no horizontal overflow or card clipping.

### CSS ownership

`custom.css` is frozen legacy debt. New homepage presentation rules belong to `assets/css/home.css`. The embedded Founding/whitelist form remains owned by `assets/css/home-conversion.css` and the Pro plugin styles it intentionally consumes.

Do not solve homepage polish by appending another override generation to `custom.css`. A new first-party homepage primitive must either belong in `home.css` or in an already-established shared design-system layer.

## Navigation and retention contract

The public header is intentionally small. Its primary information architecture is:

- BTC Intelligence
- Riset
- Tentang
- Masuk/Akun
- one commercial `BITMOMO PRO` CTA

`Bitmomo Pro` must not appear a second time as an equal-weight content-navigation link. The active route is expressed with `aria-current="page"` and a non-color visual treatment.

On mobile, a visually collapsed navigation is also removed from keyboard/accessibility traversal. A closed menu uses `inert` + `aria-hidden`; opening restores access. The menu must close after a nav selection, outside click, Escape, and when returning to desktop width.

Newsletter is a retention utility, not a launch conversion hero. There is exactly one permanent public subscribe surface: a compact MailPoet form in the global footer. The former newsletter modal is structurally disabled on every route, and `/subscribe`, `#subscribe`, or legacy newsletter links resolve to the footer anchor rather than opening an overlay.

The footer groups product, research and trust/legal links, then exposes the compact email subscribe form beside canonical social destinations. Public social destinations must come through the canonical public-link helper/filter and must not diverge between templates.

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
- homepage reading remains limited to Arah / Keyakinan / Referensi BTC / Alasan Utama / update time / concise public source;
- homepage delayed/unavailable state cannot visually imply current/fresh data;
- homepage direct Decision Ledger proof appears before Founding commitment;
- homepage does not render Opportunity, Market State, AI Lab, referral cards, future-capability walls, or a duplicate newsletter surface;
- homepage Research renders qualified market research only and hides cleanly when no qualifying items exist;
- BTC Intelligence does not expose the engine/QA kitchen-detail list above;
- track-record headline proof uses only the current compatible methodology;
- product fail-closed/data semantics remain unchanged;
- `/pro/` has one canonical conversion path and contains no unsupported live-data claim;
- `/help/` interactive FAQ/accordion behavior remains functional;
- header contains one and only one public Pro destination/CTA;
- desktop nav exposes the intended destinations and an active-route state;
- mobile menu opens/closes correctly and hidden links are not keyboard-focusable while closed;
- footer email form is compact, usable and does not create a second modal/overlay;
- public social links resolve only to their configured canonical destinations.

## CI prevention

Theme/UI architecture checks protect the **information hierarchy and public boundary**, not a frozen implementation primitive. CI must fail if:

- an owned public template disappears;
- the old media-style posts-index copy returns;
- archive routes diverge from the shared archive surface;
- homepage restores a duplicate BTC card, 30-day engine dashboard, AI Lab block, direct referral block, separate Pro teaser + whitelist, newsletter hero, or future-capability wall;
- homepage loses Arah / Keyakinan / Referensi BTC / Alasan Utama / update time / concise source;
- homepage commitment precedes its direct accountability proof path;
- homepage starts reading raw/private stores instead of the public-safe adapter;
- `assets/css/home.css` stops being the canonical homepage presentation owner;
- `custom.css` grows because new homepage rules were appended there;
- BTC Intelligence re-exposes engine/QA kitchen details;
- methodology stops being progressively disclosed;
- the canonical public-surface styles are no longer loaded;
- ordinary-page body content stops passing through the H1 normalizer;
- the unified homepage whitelist loses its grid shrink/width containment and can overflow narrow viewports.

`check-navigation-footer.mjs` additionally protects one canonical Pro header CTA, `aria-current`, accessible mobile navigation, footer-only newsletter ownership, and canonical social-destination ownership.

The M2 launch-surface contract additionally protects the visitor-first information boundary, provider-neutral conversion, supported-data claims, fail-closed intelligence/provenance behavior, and privacy-safe acquisition/repeat-use telemetry.

## Continuous production checks

The production monitor checks launch-critical routes for HTTP/content availability, runtime/PHP leakage, environment leakage and accidental `noindex`.

Beginning with public snapshot schema 2, the machine-readable consistency contract is intentionally narrow and contains only public-safe monitoring facts needed for freshness/cross-surface consistency. It must not serialize Market State, direction-strength detail, Opportunity state or other engine internals simply for monitoring convenience. Human-facing public surfaces may separately render concise source/timestamp context from the allowlisted adapter; provider diagnostics remain internal.

This contract is deliberately structural. CI and synthetic monitoring cannot prove rendered visual quality, WordPress database-body cleanliness, or browser interaction quality; staging browser QA remains mandatory before a whole-site UI candidate is merged or promoted.

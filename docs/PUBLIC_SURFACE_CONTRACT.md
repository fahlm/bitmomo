# Bitmomo Public Surface Contract

## Purpose

No public Bitmomo route may silently inherit an unreviewed Hello Elementor layout. Every public route must have an explicit Bitmomo owner, responsive acceptance, a known content hierarchy, and a deliberate public-information boundary.

Public pages are product surfaces, not engine observability dashboards. A fact belongs on a public page only when it is useful to a visitor, understandable without knowing Bitmomo's internal architecture, and appropriate to expose outside the engine/research layer.

The homepage has an additional institutional rule: **clarity before breadth**. It must behave like the front door of one coherent Bitcoin intelligence product, not a catalogue of every Bitmomo project, future capability, content vertical, or monetization experiment.

## Route ownership

| Route class | Owner |
|---|---|
| `/` | `front-page.php` + `assets/css/home.css` |
| ordinary pages (`/tentang-kami/`, `/kebijakan-privasi/`, `/disclaimer/`) | `page.php` + `.bm-public-page` |
| product shortcode pages (`/btc-intelligence/`, `/pro/`, `/help/`, Pro account/dashboard) | `page.php` pass-through + product renderer |
| posts index | `home.php` -> `template-parts/archive-index.php` |
| category | `category.php` -> shared archive / Research Hub where applicable |
| tag | `tag.php` -> shared archive |
| generic archive | `archive.php` -> shared archive |
| search | `search.php` -> shared archive |
| single article | `single.php` |
| unknown/fallback | `index.php` / `404.php` |

## Public information boundary

### Homepage

The homepage answers these questions in order:

1. **What is Bitmomo?** — a Bitcoin market-intelligence product, not a general crypto-news portal.
2. **What is happening now?** — one concise, timestamped BTC Market View.
3. **Why should I trust it?** — observations are recorded before outcomes and remain reviewable through the public accountability layer.
4. **How does the product work?** — compression -> decision support -> accountability, in visitor language.
5. **What should I do next?** — use BTC Intelligence, inspect proof, then consider Founding/Pro access.

The current-reading surface is deliberately compact but finance-grade. It may expose only:

- **Bias** — Bullish / Netral / Bearish;
- **Confidence** — bounded confidence plus a plain-language explanation that it is not a price probability;
- **Referensi BTC** — public-safe BTC reference price tied to the observation;
- **Faktor Utama** — exactly one concise public-safe market factor;
- **Diperbarui** — canonical observation/update time in WIB;
- **Sumber Data** — one concise public-safe source label from the allowlisted adapter;
- one link to the complete BTC Intelligence reading;
- one direct link to the Decision Ledger.

The homepage must not grow back into a mini-dashboard. Do not expose Opportunity internals, activity percentiles, raw 60-minute ranges, classifier certainty, raw derivative grids, engine/classifier identifiers, QA diagnostics, private provenance/diagnostics, source record IDs, current Pro scenarios/ranges/invalidation details, or a duplicate 30-day chart on the homepage.

The reading fails closed. Delayed state is visibly delayed. Unavailable state never inherits a visual treatment that implies fresh/current data. No placeholder price, direction, factor, source or timestamp is fabricated.

Canonical launch hierarchy:

1. product positioning + current BTC Market View;
2. accountability principles / proof path;
3. short explanation of how Bitmomo works;
4. one Bitmomo Pro / Founding conversion surface;
5. concise qualified Market Research;
6. retention/trust footer.

The order is intentional: **value -> proof -> mechanism -> commitment -> authority -> retention**. Commitment must not outrank direct accountability proof.

AI Lab belongs on a dedicated Research surface, not in the homepage launch funnel. Direct referral cards do not belong in the launch hierarchy. Future capability walls (11 AI Analysts, Watchtower, Telegram automation, etc.) may not return as homepage sections while they are not current product value. Referral/platform content may return only as clearly editorial articles with transparent disclosure rather than a promotional card linking directly to an affiliate destination.

Homepage Market Research is a research desk, not a generic blog grid. It surfaces at most three currently qualified market-research items with topic, publication date, reading time, title and concise excerpt. Generic `Riset` membership alone is insufficient; the Research Hub classification boundary applies.

### BTC Intelligence

`/btc-intelligence/` owns deeper public context and accountability. It should answer:

1. What is the current BTC reading?
2. What happened in the latest canonical session context?
3. What materially changed from the previous canonical reading?
4. Why does that change matter?
5. What is one public-safe thing worth observing next?
6. How has directional context changed over the recent record?
7. How has the current evaluation methodology performed against future outcomes?

The main public reading may expose:

- reference BTC price;
- canonical session identity and anchor (`US PRE-OPEN`, `US POST-CLOSE`, or the closed-market presentation label);
- directional reading in human language;
- Confidence with a clear explanation;
- market activity translated to `Tinggi / Normal / Rendah` without exposing percentile/range internals;
- up to two public-safe factors/reasons;
- up to two human-readable changes;
- up to two translated public-safe `why_it_matters` explanations;
- for Post-Close, one concise trailing-24h observed summary using public-safe fields only;
- exactly one allowlisted public watch context;
- concise source and exact WIB timestamp.

The Free product contract is **Now + Change + Meaning + One Watch**. The public watch context is observational rather than actionable: it tells a visitor what evidence to observe next. It must never be a target, price forecast, expected range, scenario, invalidation level, alert trigger, or arbitrary current Pro monitoring condition.

Only these deterministic watch codes may be translated to a current Free public watch item:

- `directional_consistency`;
- `structure_continuity`.

Unknown codes fail closed. Even when the canonical payload contains two candidates, the public renderer publishes at most one.

The fast Market Pulse and the twice-daily Major Brief are separate clocks. Opportunity consumes 5-minute candles and evaluates canonically every 15 minutes. Session Intelligence produces DST-aware Pre-Open and Post-Close editions. Public copy may describe the fast layer as `evaluasi 15 menit dari candle 5 menit`; it must not claim a five-minute canonical evaluation cadence.

The 30-day public context is direction-only. It may summarize Bullish / Netral / Bearish official daily records and must never fabricate missing days.

The public track record displays only the current compatible directional-evaluation methodology as primary proof. Older incompatible methodologies stay preserved for audit but must not be mixed into the headline metric.

The directional outcome contract is anchored exactly at **+24 hours** from observation time, using a compatible provider and exactly 24 closed hourly candles. For Bullish/Bearish outcomes, moves between -0.5% and +0.5% are inconclusive and excluded from the accuracy denominator; conclusive and total counts remain visible.

### Engine/research-only detail

The following may exist internally but are not public UI primitives:

- Opportunity methodology name, activity percentile and raw 60-minute range;
- classifier certainty/debug taxonomy not intentionally translated into visitor context;
- raw OI, funding, basis, long/short and taker metrics as a customer-facing kitchen-sink grid;
- individual five-axis/debug scores;
- engine/classifier/version identifiers;
- confidence calibration buckets;
- Expected Range QA until a genuinely frozen/versioned range methodology exists;
- Regime forward-return QA;
- stale/blocked/missing-data rates and settlement-completeness diagnostics;
- provider implementation/fallback wording;
- source diagnostics, internal evidence, record IDs and private notes;
- current Pro-only scenarios, full monitoring conditions, watched range and invalidation details on the free surface.

A single allowlisted Free public watch context is not a Pro `monitoring_condition`; those are separate contracts. Free must never expose or translate arbitrary Pro monitoring content.

Methodology explanation remains behind progressive disclosure. It explains concepts in visitor language rather than dumping engine internals.

## Data-correctness contract

A clean UI never permits a weaker engine.

- Directional settlement is exact +24h, provider-compatible and versioned. Late/misaligned windows fail closed rather than borrowing a convenient endpoint.
- Open-interest `24H` change uses exactly 24 hourly intervals (25 observations), not the first and last values of an arbitrary larger fetch.
- Successful derivative inputs may affect a new reading only while observation timestamps remain within cadence-aware freshness limits. A successful-but-stale or untimestamped derivative input fails the quality boundary.
- Expected Range public-comparable proof requires a frozen original plus explicit methodology version. Support/resistance zones must never be relabeled as a forecast Expected Range.
- US pre-open/post-close editions are grouped by canonical US market day rather than raw UTC calendar date.

## Visual contract

- One site header and one site footer.
- One intentional H1 per route.
- No parent-theme page header on owned routes.
- Generic reading width approximately 780px; archive/product surfaces may be wider.
- Homepage/public shell aligns with shared header/footer geometry.
- Major vertical gaps must be intentional.
- Conversion surfaces must not repeat the same offer as adjacent standalone sections.
- Redundant blocks are removed from renderer/template, not merely hidden with CSS.
- Hierarchy comes from spacing, typography, borders and information order rather than turning every concept into a card.
- Future capability is clearly marked as not live.
- Public claims describe capabilities and data inputs that actually exist.
- Navigation, CTA, freshness and disclosure semantics work without color alone.
- Required metadata meets WCAG AA contrast.
- Responsive acceptance includes 360 / 390 / 768 / 1024 / 1440 and short-height mobile.

### Visual semantics

- graphite/off-white/slate = foundation;
- restrained steel blue = product/data/navigation emphasis;
- amber/orange = commercial action;
- green/red/warning = semantic market states only;
- free-product navigation or informational actions must not consume the commercial-action treatment merely because they are primary in a local section;
- legacy teal/cyan hard-codes must not bypass design tokens.

### Overflow discipline

Global `overflow-x` suppression is not proof of responsive correctness. No release may use clipping/masking as a substitute for fixing an overflowing element. Acceptance tooling must detect geometry that extends beyond intended containers even when a root overflow rule would otherwise hide a scrollbar.

### CSS ownership

`custom.css` is frozen legacy debt. New homepage rules belong to `assets/css/home.css`; shared chrome to `assets/css/navigation-footer.css`; cross-surface tokens/foundation to the design-system layer; article/Research/product styles remain in their named owners. Do not append another override generation to `custom.css`.

## Navigation, conversion and retention

The public header is intentionally small:

- BTC Intelligence
- Riset
- Tentang
- Masuk/Akun
- one commercial `BITMOMO PRO` CTA

`Bitmomo Pro` must not appear a second time as equal-weight content navigation. Active route uses `aria-current="page"` and a non-color treatment.

On mobile, collapsed navigation is removed from keyboard/accessibility traversal. Closed menu uses `inert` + `aria-hidden`; opening restores access. Menu closes after nav selection, outside click, Escape and return to desktop width.

### Founding Whitelist

Founding Whitelist is the current commercial acquisition path for Pro while checkout is OFF. It does not guarantee a seat and must not fabricate urgency. WhatsApp remains OFF unless a later release explicitly enables it.

### Newsletter

Newsletter is a **retention/distribution utility**, not a commercial launch hero and not a synonym for the Founding Whitelist.

- exactly one permanent public newsletter subscribe surface lives in the global footer;
- the former newsletter modal remains structurally disabled on every route;
- `/subscribe`, `#subscribe`, and `#newsletter` resolve to the footer newsletter anchor;
- newsletter signup and whitelist signup have separate consent and storage semantics; neither silently enrolls the other;
- visitor presentation is Bitmomo-owned and visually secondary; MailPoet may remain the backend;
- newsletter submit does not use the commercial orange hierarchy;
- when the backend is unavailable, the surface fails closed without a public broken-capability message.

The footer groups Product, Research and company/trust links, exposes the compact newsletter utility and canonical social destinations, then separates legal/copyright utility from primary navigation. Social destinations come only through canonical validated configuration and fail closed when absent.

## WordPress content ownership boundary

A canonical child-theme wrapper does not prove database content is clean. Legacy Elementor/database markup can survive inside a new template after route ownership moves to the child theme.

For ordinary pages, `page.php` owns the route-level H1. Rendered body content passes through `bitmomo_normalize_public_page_body_headings()` so legacy body-level H1 tags cannot create a second route-level H1. Product shortcodes bypass this normalizer because their renderer owns heading hierarchy.

This is not a substitute for content migration. About, Privacy, Disclaimer and other ordinary pages still require rendered DOM/body inspection. Legacy full-page wrappers, stale copy, unexplained spacing or obsolete database markup are explicit content defects, not CSS problems to conceal.

## Editorial ownership

`docs/editorial/BITMOMO_INSTITUTIONAL_COPY_SYSTEM_V1.md` owns visitor-facing language policy.

Final copy should live in the renderer that owns the surface wherever feasible. Runtime string replacement is acceptable only as a narrow compatibility bridge; it must not become the long-term canonical editorial architecture because source review should show the same language the visitor sees.

## Analytics / PMF boundary

Cold-audience acquisition and retention must be measurable without collecting submitted identity in analytics. Browser events are useful only when an actual analytics sink receives them. Before launch, staging acceptance proves end-to-end event capture for product interest, whitelist conversion, newsletter retention and meaningful BTC Intelligence use.

## Mandatory viewport QA before production

Check at **360x800 / 390x568 / 390x844 / 768x1024 / 1024x900 / 1440x1000** plus 200% zoom on at least:

1. `/`
2. `/btc-intelligence/`
3. `/pro/`
4. `/help/`
5. `/category/riset/`
6. `/tentang-kami/`
7. `/kebijakan-privasi/`
8. `/disclaimer/`
9. representative qualified articles
10. `/pro/account/` logged out
11. search and canonical 404

Acceptance includes:

- no horizontal overflow or hidden clipping;
- no duplicate H1;
- no clipped navigation or CTA;
- no accidental empty blocks / unexplained large whitespace;
- consistent header/footer;
- no legacy full-page layout inside migrated ordinary-page content;
- zero material browser-console errors;
- zero serious/critical accessibility violations;
- usable touch targets and focus behavior;
- homepage remains concise and fail-closed;
- accountability proof precedes commitment;
- homepage excludes AI Lab/referral/future-capability walls and duplicate newsletter;
- qualified Research only;
- BTC Intelligence excludes engine kitchen details while preserving the contracted Free session brief;
- BTC Intelligence Free renders no more than one allowlisted public watch context and never current Pro monitoring/scenario/range/invalidation content;
- Pro has one commercial conversion path and no unsupported live-data claim;
- Help remains functional by keyboard;
- footer newsletter appears exactly once and is visually secondary;
- social links resolve only to configured canonical destinations.

## CI prevention

CI protects intended product boundaries, not historical implementation accidents. A test that contradicts this contract is a defect in the test and blocks release until reconciled.

CI must fail if:

- an owned public template disappears;
- homepage becomes a generic publisher/dashboard surface;
- private engine details leak publicly;
- the free/Pro boundary blurs;
- the Free session brief loses its contracted Now + Change + Meaning + One Watch value;
- an unknown/arbitrary watch code is rendered publicly;
- commercial and informational CTA semantics collapse into one visual treatment;
- homepage/Research/accountability classification boundaries weaken;
- newsletter disappears, duplicates, becomes a popup, or is silently conflated with Founding Whitelist;
- `custom.css` grows with new public ownership;
- responsive/accessibility contracts regress;
- final visitor copy reintroduces banned literal/internal language;
- public social destinations bypass canonical fail-closed configuration.

`check-navigation-footer.mjs`, `check-institutional-copy.mjs`, M2 contracts and browser acceptance must agree with this document.

## Continuous production checks

Production monitoring checks launch-critical routes for availability, runtime/PHP leakage, environment leakage, indexing and narrow public snapshot consistency. After launch, monitoring should additionally prove that critical retention/conversion entry points remain present without submitting synthetic user identity on every run.

CI and synthetic monitoring cannot prove rendered visual quality, WordPress database-body cleanliness, browser interaction quality, social share appearance or real inbox delivery; staging/browser acceptance remains mandatory.

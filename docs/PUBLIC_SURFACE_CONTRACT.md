# Bitmomo Public Surface Contract

## Purpose

No public Bitmomo route may silently inherit an unreviewed Hello Elementor layout or an accidental legacy CSS cascade. Every public route must have an explicit owner, responsive acceptance, known content hierarchy, and one stylesheet ownership path.

## Route ownership

| Route class | Owner |
|---|---|
| `/` | `front-page.php` + homepage template parts |
| `/category/riset/` | `category.php` -> `template-parts/research-hub.php` |
| ordinary pages (`/tentang-kami/`, `/kebijakan-privasi/`, `/disclaimer/`) | `page.php` + `.bm-public-page` |
| `/tentang-kami/` authority context | `page.php` + `template-parts/about-authority.php` |
| product shortcode pages (`/btc-intelligence/`, `/pro/`, `/help/`, Pro account/dashboard) | `page.php` pass-through + product renderer |
| posts index | `home.php` -> `template-parts/archive-index.php` |
| non-Riset category | `category.php` -> shared archive |
| tag / generic archive / search | shared archive surface |
| single article | `single.php` research publication surface |
| unknown/fallback | `index.php` / `404.php` |

## CSS ownership

Public styling is a dependency graph, not a patch stack:

1. `assets/css/foundation.css` — tokens, reset, typography, layout primitives, focus states, reduced-motion and minimum-width contract.
2. `assets/css/navigation-footer.css` — header, responsive navigation, footer, newsletter and social chrome.
3. `assets/css/public-surfaces.css` — ordinary pages, generic archives, search and 404.
4. `assets/css/home.css` — homepage composition only.
5. `assets/css/research.css` — Research Hub and article publication surfaces.
6. `assets/css/about.css` — About institutional authority surface.
7. Product plugins keep namespaced component CSS and may consume shared tokens.

The historical `custom.css`, `home-opportunity.css`, and `public-readability.css` are legacy source-history only. They are not enqueued and are excluded from the production runtime artifact. New visual fixes must land in the owning layer above.

Templates must not emit visual `<style>` blocks or manually inject stylesheet `<link>` elements. WordPress enqueue dependencies own stylesheet order and cache invalidation.

## Product and brand hierarchy

Homepage responsibility is deliberately ordered:

1. current BTC utility;
2. institutional authority — Crypto Market Intelligence + AI Systems Research;
3. method / decision-support explanation;
4. current research proof;
5. substantive AI Lab positioning;
6. one Founding conversion surface;
7. disclosure/platform content.

The homepage must answer both **what Bitmomo does** and **why a cold visitor should trust the process** before asking for conversion.

The Research Hub is not a generic WordPress archive. It must expose:

- Bitmomo research thesis;
- Crypto Market Research and AI Systems Research as explicit disciplines;
- current featured research from published WordPress content;
- Research Standard / methodology principles;
- Market Research and AI Lab streams derived from taxonomy (`Riset` + `ai-lab` tag), never fabricated hard-coded posts;
- complete chronological Riset archive.

Single articles must read as publications, not generic blog posts: research classification, deck, date/read-time/update state, article body, research standard, topics and related research.

## Visual and interaction contract

- One site header and one site footer.
- One intentional visible H1 per route.
- No parent-theme page header on owned routes.
- Generic reading width approximately 760px; analytical/featured surfaces may be wider.
- Minimum supported layout width is 320px.
- Major grids use shrinkable tracks (`minmax(0, …)`) or collapse before overflow.
- Major vertical gaps are intentional; no stacked generic paddings as spacing hacks.
- No duplicate current-intelligence, newsletter, Pro teaser or conversion surface.
- Upcoming capabilities are clearly marked not live.
- Public product/data claims describe capabilities and inputs that actually exist.
- Navigation, CTA and disclosure semantics work without color alone.
- Reduced-motion preference is respected.

## Navigation and retention contract

Primary public IA:

- BTC Intelligence
- Research
- Tentang
- Masuk
- one commercial `BITMOMO PRO` CTA

The active route uses `aria-current="page"` plus a non-color visual treatment. On mobile, a closed menu uses `inert` + `aria-hidden`; opening restores traversal. It closes after selection, outside click, Escape, and desktop resize.

Newsletter is retention utility, not the launch hero. There is exactly one permanent public subscribe surface: the compact MailPoet form in the global footer. The former newsletter modal and standalone newsletter template are structurally removed/disabled. Legacy `/subscribe`, `#subscribe`, and subscribe-menu behavior resolve to the footer anchor.

Canonical social destinations remain centrally configured:

- Telegram: `https://t.me/bitmomodaily`
- YouTube: `https://www.youtube.com/@bitmomoid`
- X: `https://x.com/bitmomoid`

## WordPress content ownership boundary

A child-theme wrapper does **not** prove database content is clean. Legacy Elementor/database markup can survive inside a canonical template.

For ordinary pages, `page.php` owns the route-level H1. Rendered body content passes through `bitmomo_normalize_public_page_body_headings()`, demoting legacy body H1 tags to H2. Product shortcode surfaces bypass this normalizer because their renderer owns heading hierarchy.

This is not a substitute for content migration. Staging acceptance must still inspect rendered ordinary-page bodies for legacy Elementor full-page containers, stale wrappers, obsolete copy, unexplained whitespace, or duplicate content. Do not conceal database-content defects with CSS.

## Mandatory browser QA before production

Check **360 / 390 / 768 / 1024 / 1440** on at least:

1. `/`
2. `/btc-intelligence/`
3. `/pro/`
4. `/help/`
5. `/category/riset/`
6. `/tentang-kami/`
7. `/kebijakan-privasi/`
8. `/disclaimer/`
9. one representative article
10. search and/or 404 fallback

Acceptance:

- HTTP 200 where expected;
- zero horizontal overflow;
- exactly one visible route-level H1;
- no H1 hidden beneath sticky header;
- no material browser-console/page errors;
- Axe serious = 0 and critical = 0 on audited viewports;
- no clipped navigation, CTA, chart or form controls;
- mobile menu accessibility state is correct while open and closed;
- one canonical `/pro/` nav destination;
- exactly one footer newsletter and zero legacy modal;
- canonical social destinations resolve correctly;
- homepage renders current BTC instrument, institutional authority, research preview and substantive AI Lab;
- Research renders hero, two disciplines, standards, streams and archive;
- About renders institutional authority context;
- no unexplained large whitespace or legacy full-page Elementor composition;
- product fail-closed/data semantics remain unchanged.

## CI prevention

`theme-safety.yml` and `check-ui-architecture.mjs` fail if canonical route ownership or stylesheet ownership regresses, legacy CSS returns to the runtime/cascade, PHP emits visual CSS, header manually injects stylesheets, Research becomes a generic archive, AI Lab loses substance, About loses authority context, mobile navigation loses accessibility semantics, or known low-contrast colors return.

`check-navigation-footer.mjs` protects one canonical Pro destination, Research-oriented navigation, mobile-menu accessibility, footer-only newsletter ownership, legacy subscribe-route migration, compact responsive footer behavior, canonical social links, and dependency-managed stylesheet order.

`check-m2-launch-surfaces.mjs` additionally protects intelligence/provenance semantics, fail-closed behavior, provider-neutral Pro conversion, roadmap truthfulness, founding pricing/seat facts, privacy-safe telemetry, homepage authority hierarchy and taxonomy-driven Research separation.

`check-public-ui.mjs` is the rendered-browser gate across the viewport matrix. It checks route markers, canonical surface selectors, overflow, one H1, sticky-header overlap, nav state, footer retention/social surfaces, mobile menu behavior, browser errors, screenshots and Axe serious/critical violations.

## Continuous production checks

The production synthetic monitor remains separate from staging visual acceptance and checks launch-critical routes for HTTP/content availability, runtime/PHP leakage, staging-host leakage and accidental `noindex`. BTC Intelligence provenance and external diagnostic checks remain explicit.

CI proves structural contracts; it does not replace staging visual acceptance. Production remains untouched until an exact artifact passes staging safety, runtime parity, browser/Axe and manual visual review.

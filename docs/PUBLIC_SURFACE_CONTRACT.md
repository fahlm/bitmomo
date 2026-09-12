# Bitmomo Public Surface Contract

## Purpose

No public Bitmomo route may silently inherit an unreviewed Hello Elementor layout. Every public route must have an explicit Bitmomo owner, responsive acceptance, and a known content hierarchy.

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
- touch targets remain usable on mobile;
- product fail-closed/data semantics remain unchanged;
- `/pro/` has one canonical conversion path and contains no unsupported live-data claim;
- `/help/` interactive FAQ/accordion behavior remains functional;
- header contains one and only one public Pro destination/CTA;
- desktop nav exposes the intended five destinations and an active-route state;
- mobile menu opens/closes correctly and hidden links are not keyboard-focusable while closed;
- footer email form is compact, usable and does not create a second modal/overlay;
- Telegram, YouTube and X footer destinations resolve to the canonical URLs above.

## CI prevention

`theme-safety.yml` / the UI architecture contract fails if:

- an owned public template disappears;
- the old media-style posts-index copy returns;
- archive routes diverge from the shared archive surface;
- the old `big-stories` tag-only layout returns;
- homepage restores separate Pro teaser + whitelist blocks;
- homepage restores the duplicated full future-capability panel;
- the canonical public-surface stylesheet is no longer loaded;
- ordinary-page body content stops passing through the H1 normalizer;
- the unified homepage whitelist loses its grid shrink/width containment and can overflow narrow viewports.

`check-navigation-footer.mjs`, run by both Theme Safety and Full Release Safety, additionally protects:

- one canonical Pro header CTA with no duplicate Pro content-nav entry;
- `aria-current` active-route semantics;
- inaccessible closed-mobile-menu prevention via `inert` / `aria-hidden`;
- footer-only newsletter ownership and structural removal of modal markup;
- legacy subscribe links resolving to the footer;
- compact responsive footer MailPoet presentation;
- canonical Telegram, YouTube and X destinations;
- deterministic stylesheet ordering for the navigation/footer layer.

`release-safety.yml` / the M2 launch-surface contract additionally protects product semantics, including:

- one provider-neutral Pro conversion path;
- removal of the redundant final Pro conversion block at source level rather than through CSS;
- current Pro DATA copy restricted to supported market inputs;
- no unsupported continuous order-book/on-chain ingestion claim;
- fail-closed BTC Intelligence/provenance behavior;
- privacy-safe whitelist and repeat-use telemetry contracts.

## Continuous production checks

The production synthetic monitor runs every 15 minutes and checks the launch-critical product routes plus Help, Research, About and legal surfaces for HTTP/content availability, runtime/PHP leakage, staging-host leakage and accidental `noindex`. BTC Intelligence provenance (`SOURCE / AS OF / WIB`) and the external Binance USD-M diagnostic remain separate explicit checks.

This contract is deliberately structural. CI and synthetic monitoring cannot prove rendered visual quality, WordPress database-body cleanliness, or browser interaction quality; staging browser QA remains mandatory before a whole-site UI candidate is merged or promoted.

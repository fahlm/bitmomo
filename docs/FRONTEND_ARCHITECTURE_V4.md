# Bitmomo Frontend Architecture V4

## Objective

Build public Bitmomo surfaces as one coherent product/research system: responsive, stable, precise, fast, and difficult to regress.

This architecture replaces patch-stacking with explicit ownership.

## Ownership

- `assets/css/foundation.css` — global tokens, reset, typography, layout primitives, accessibility states, responsive contract.
- `assets/css/navigation-footer.css` — global site chrome only.
- `assets/css/public-surfaces.css` — generic WordPress page/archive/search/404 surfaces.
- `assets/css/home.css` — homepage only.
- `assets/css/research.css` — Research Hub and article publication surfaces.
- `assets/css/about.css` — About institutional authority surface.
- Product plugin CSS remains namespaced inside its plugin and may consume shared theme tokens.

## Legacy boundary

The historical `custom.css`, `home-opportunity.css`, and `public-readability.css` are source-history artifacts only. They are not enqueued and are excluded from the production runtime artifact. New fixes must never be added to them.

## Product hierarchy

Homepage:

1. current BTC utility
2. Bitmomo research/intelligence authority
3. method
4. research proof
5. AI Lab
6. Founding conversion
7. disclosures/platform content

Research Hub:

1. research thesis
2. two disciplines: Crypto Market Research and AI Systems Research
3. featured current research
4. research standard
5. Market Research and AI Lab streams derived from WordPress taxonomy
6. complete Riset archive

Article:

1. research classification
2. title/deck
3. publication and update metadata
4. article body
5. research standard
6. topics
7. related research

## Invariants

- one route-level H1
- no full-page Elementor ownership on canonical public routes
- no CSS `<style>` output from PHP
- no manual stylesheet `<link>` injection in templates
- no legacy CSS in production artifact
- no fabricated intelligence when data is unavailable/stale
- no duplicated homepage intelligence, newsletter, or Pro conversion blocks
- 320px minimum supported layout width
- all major grids use shrinkable `minmax(0, …)` tracks or collapse before overflow
- keyboard-accessible navigation with `inert`, `aria-hidden`, `aria-expanded`, Escape close, outside-click close
- WCAG AA contrast for normal public text
- production remains untouched until exact artifact passes staging parity, browser/axe and manual visual acceptance

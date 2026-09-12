# Bitmomo Research Hub Contract

## Purpose

The Research Hub is a research workspace, not a marketing landing page, generic WordPress archive, or crypto-media homepage.

A professional visitor should understand within seconds that Bitmomo operates a research desk across two connected disciplines:

1. **Markets** — Bitcoin and digital-asset market research.
2. **Intelligence Systems** — the systems, evaluation methods, provenance, and reliability work used to turn evidence into trustworthy intelligence.

The interface must make sophisticated research easier to find and inspect. Visual complexity must never compete with analytical complexity.

## Information hierarchy

The canonical order is:

1. compact institutional Research header;
2. research discovery navigation + search;
3. Lead Research;
4. dense Research Library;
5. Research Domains;
6. Research Programs;
7. Research Standard / methodology;
8. classification-boundary note.

Research inventory must appear before long methodology or brand-philosophy sections. Do not move large manifesto cards above the publications.

## Discovery contract

Research discovery is server-side and allowlisted. Canonical filters are owned by `bitmomo_research_focus_filters()` and currently include:

- All Research
- Bitcoin
- Macro
- Market Structure
- Derivatives
- ETF & Flows
- Liquidity
- Intelligence Systems

Filtered/search views are utility views, not separate SEO documents. They are `noindex,follow` and canonicalize to the base Research Hub.

A broad `Riset` category membership is never enough to qualify a publication as institutional research.

## Classification contract

Public research classification has one owner in `inc/template-functions.php`:

- Market Research = `Riset` + explicit market taxonomy and no `ai-lab` tag;
- Intelligence Systems Research = `Riset` + explicit `ai-lab` tag;
- everything else = unclassified.

Homepage Research, Research Hub, and single-article related content must consume this boundary instead of inferring classification independently.

Unclassified legacy/general posts may remain reachable at their original URLs, but they must not be promoted as institutional research.

## Lead Research contract

Lead Research prioritizes analytical information over decorative editorial imagery.

It should expose:

- research domain;
- topic;
- publication date;
- estimated reading time;
- title;
- concise research summary or explicit excerpt-derived key finding;
- optional featured image treated as a **Research Figure**, not as a magazine hero.

If no qualified research exists for the active view, the interface fails closed with an explicit empty state.

## Library contract

The Research Library is typography-led and information-dense. Each row exposes enough metadata to support scanning without opening the article.

The library must not become a grid of oversized SaaS cards or decorative thumbnails.

Preferred visual primitives:

- thin rules;
- compact metadata;
- structured columns;
- restrained use of accent color;
- minimal radius;
- clear typography hierarchy.

Avoid excessive pills, gradients, nested cards, and promotional CTA repetition.

## Research domains

**Markets** answers what changed and why it matters in the market.

**Intelligence Systems** answers how evidence is processed, evaluated, traced, and made reliable enough to support intelligence.

The two domains belong to one institution and one evidence standard; they must not look like unrelated blogs.

## Research programs

Research Programs organize recurring analytical questions rather than trend-driven content. The initial public programs are:

- Market Structure Notes
- Derivatives Monitor
- ETF & Capital Flows
- Intelligence Systems

Programs currently resolve through the canonical research filters; they must not imply a separate dataset or methodology that does not exist.

## Research standard

Methodology remains visible but follows the research inventory.

Canonical principles:

- Traceable evidence
- Context over noise
- Thesis + invalidation
- Evaluation

The page may use the statement **Evidence before narrative.** as the compact institutional standard.

## Commercial boundary

The Research Hub is an authority surface. Do not place the Founding Whitelist or another high-pressure commercial CTA in the Research hero.

A contextual link to BTC Intelligence is allowed because it connects research to the public intelligence product. Normal site-wide conversion remains available through header/footer/product surfaces.

## Responsive and accessibility contract

At 360 / 390 / 768 / 1024 / 1440:

- exactly one route-level H1;
- no horizontal page overflow;
- filter navigation may scroll within its own bounded container on narrow screens;
- search remains operable without JavaScript;
- active filters use `aria-current` and non-color treatment;
- link purpose remains understandable;
- Research Figure never forces horizontal overflow;
- library metadata collapses without losing domain/topic/date/read-time information;
- serious/critical accessibility violations remain zero.

## Regression prevention

CI must fail if the Research Hub loses its server-side discovery layer, Research Library, Research Programs, canonical classification helpers, metadata helpers, or if a Founding Whitelist CTA returns to the Research hero.

CI must also protect the ordering invariant that Research Library appears before the long-form Research Standard section.

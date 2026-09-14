# Bitmomo Research Hub Contract

## Purpose

The Research Hub is a research workspace, not a marketing landing page, generic WordPress archive, or crypto-media homepage.

A professional visitor should understand within seconds that Bitmomo publishes research across two connected classifications:

1. **Markets** — Bitcoin and digital-asset market research.
2. **Intelligence Systems** — the systems, evaluation methods, provenance, and reliability work used to turn evidence into trustworthy intelligence.

The interface must make qualified publications easier to find and inspect. Visual or taxonomy complexity must never outrank the research corpus itself.

## Information hierarchy

The canonical order is:

1. compact institutional Research header;
2. corpus-backed discovery navigation + search;
3. Lead Research when qualified content exists;
4. dense Research Library;
5. Research Standard / methodology;
6. classification-boundary note.

Research inventory must appear before methodology or brand philosophy. Do not add empty program, domain, taxonomy, or feature sections simply to make the page look larger.

## Publication-first contract

The corpus is the proof. Navigation is derived from publications that actually exist.

A filter earns public navigation only when at least one currently qualified publication matches it. Empty research programs or categories must fail closed instead of being advertised as future capability.

The Research Hub must not require separate "Research Programs" or "Research Domains" presentation layers. Classification and topic metadata may communicate those distinctions directly in Lead Research and Research Library rows.

## Discovery contract

Research discovery is server-side and allowlisted. Canonical filters are owned by `bitmomo_research_focus_filters()`.

Only filters represented by the current qualified corpus may be rendered. The supported vocabulary can include:

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

- research classification;
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

## Research standard

Methodology remains visible but follows the research inventory.

Canonical principles:

- Traceable evidence
- Context over noise
- Thesis + invalidation
- Evaluation

The page may use the statement **Evidence before narrative.** as the compact institutional standard.

## Commercial boundary

The Research Hub is an authority surface. Do not place the Founding Whitelist or another high-pressure commercial CTA in the Research hero or publication inventory.

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
- library metadata collapses without losing classification/topic/date/read-time information;
- serious/critical accessibility violations remain zero.

## Regression prevention

CI must fail if the Research Hub loses its server-side discovery layer, Lead Research, Research Library, canonical classification helpers, metadata helpers, explicit empty state, or if a Founding Whitelist CTA returns to the Research authority surface.

CI must protect the publication-first ordering invariant: qualified publication inventory appears before the long-form Research Standard section.

CI must also fail if source contracts begin requiring an empty presentation layer such as Research Programs or Research Domains when the renderer no longer owns that layer. Source documentation, renderer ownership, CSS ownership, and executable assertions must describe the same architecture.
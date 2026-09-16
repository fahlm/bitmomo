# Bitmomo Research Hub Contract

## Purpose

The Research Hub is a research workspace, not a marketing landing page, generic WordPress archive, or crypto-media homepage.

A professional visitor should understand within seconds that Bitmomo publishes research across two connected institutional desks:

1. **Market Research** — Bitcoin and digital-asset market research.
2. **Intelligence Systems Research** — AI systems, agents, evaluation methods, provenance, reliability, and infrastructure used to turn evidence into trustworthy intelligence.

The interface must make qualified publications easier to find and inspect. Visual or taxonomy complexity must never outrank the research corpus itself.

## Information hierarchy

The canonical order is:

1. compact institutional Research header;
2. corpus-backed discovery navigation + search;
3. Lead Research when qualified content exists;
4. paginated Research Library;
5. Research Standard / methodology;
6. classification-boundary note.

Research inventory must appear before methodology or brand philosophy. Do not add empty program, domain, taxonomy, or feature sections simply to make the page look larger.

## Publication-first contract

The corpus is the proof. Navigation is derived from publications that actually exist.

A filter earns public navigation only when at least one currently qualified publication matches it. Empty research programs or categories must fail closed instead of being advertised as future capability.

The Research Hub must not require separate "Research Programs" or "Research Domains" presentation layers. Desk and topic metadata communicate those distinctions directly in Lead Research and Research Library rows.

## Canonical classification contract — Research Taxonomy V3

Public institutional-research identity is not inferred from article titles, body keywords, the first WordPress category, or a generic `Riset` category membership.

V3 owns two non-public editorial taxonomies:

- `bm_research_desk` — exactly one allow-listed desk is required for a qualified research publication.
- `bm_research_topic` — zero or more allow-listed reusable topic terms support discovery and context.

Canonical desk vocabulary:

- `market-research` → Market Research
- `intelligence-systems` → Intelligence Systems Research

The desk taxonomy is intentionally small. Granular concepts such as Bitcoin, Macro, Market Structure, Derivatives, ETF & Flows, Liquidity, AI Agents, Evaluation, Data Provenance, Decentralized AI, AI Infrastructure, Models, and AI Industry & Society belong in Research Topics rather than becoming public top-level desks.

Once V3 is active:

- zero desks = unclassified;
- more than one desk = unclassified;
- an unknown desk = unclassified;
- exactly one allow-listed desk = qualified research.

This is fail-closed by design. Homepage Research, Research Hub, single-article identity, related reading, and SEO must consume the same canonical helper boundary.

## Migration-safe activation contract

A code deploy must never silently reclassify or remove the existing Research corpus.

Until the audited database migration explicitly activates `bitmomo_research_taxonomy_version = v3`, the current production-safe legacy boundary remains authoritative:

- Market Research = `Riset` + explicit allow-listed market taxonomy and no `ai-lab`;
- Intelligence Systems Research = `Riset` + explicit `ai-lab`;
- ambiguous AI + market legacy signals fail closed;
- everything else remains unclassified.

The compatibility path is transitional only. It exists so code can be deployed and tested before database activation without changing the visible corpus.

The canonical WP-CLI migration is `scripts/migrations/research-taxonomy-v3.php`.

Migration requirements:

1. dry-run is the default;
2. an optional explicit slug map may resolve editorial exceptions;
3. ambiguous legacy signals abort the plan unless explicitly mapped;
4. desk/topic writes are idempotent;
5. partial metadata writes do not activate V3;
6. V3 activation happens only after post-write validation passes;
7. generic or editorial posts are not promoted to institutional research merely because they exist in historical categories.

## Editorial contract

The WordPress post editor exposes one Bitmomo-owned Research Classification control.

Editors may select:

- not institutional research;
- Market Research;
- Intelligence Systems Research;

and may attach only allow-listed Research Topics.

The UI must not expose an uncontrolled free-form desk vocabulary. A desk is an institutional claim, not an arbitrary tag.

## Discovery contract

Research discovery is server-side and allow-listed. Canonical filters are owned by `bitmomo_research_focus_filters()`.

Only filters represented by the current qualified corpus may be rendered. Supported visitor-facing vocabulary can include:

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

## Query and pagination contract

After V3 activation, the Research Hub must query canonical desk/topic taxonomies through `WP_Query`; it must not load an arbitrary fixed number of posts and silently truncate the corpus.

The current contract is:

- Lead Research is resolved separately;
- the Lead publication is excluded from the paginated library;
- the library renders 10 publications per page;
- pagination preserves active filter/search arguments;
- returned publications are still validated through the canonical classification helper before rendering;
- migration-era legacy querying is allowed only while V3 is inactive.

The Hub must remain usable when the corpus grows beyond the current article count.

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
- pagination remains keyboard-operable and preserves a visible current state;
- link purpose remains understandable;
- Research Figure never forces horizontal overflow;
- library metadata collapses without losing classification/topic/date/read-time information;
- serious/critical accessibility violations remain zero.

## Regression prevention

CI must fail if the Research Hub loses its server-side discovery layer, Lead Research, Research Library, pagination contract, canonical classification helpers, explicit empty state, or if a Founding Whitelist CTA returns to the Research authority surface.

CI must protect the publication-first ordering invariant: qualified publication inventory appears before the long-form Research Standard section.

CI must also protect the V3 safety boundary:

- both canonical taxonomy names remain present;
- classification remains fail-closed;
- the legacy bridge remains gated by the activation option;
- the migration remains dry-run by default and activates only after validation;
- the migration PHP file is syntax-checked.

Source documentation, renderer ownership, CSS ownership, migration behavior, and executable assertions must describe the same architecture.

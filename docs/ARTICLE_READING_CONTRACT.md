# Bitmomo Article Reading Contract

## Purpose

A Bitmomo article is often the first page a cold visitor sees. The article surface therefore has to work as a complete trust and reading experience even when the visitor has never seen the homepage, Research Hub, BTC Intelligence, or Bitmomo Pro.

The canonical journey is:

**Google/search result → article orientation → comfortable long-form reading → trust context → relevant next research.**

An article must not depend on prior brand familiarity to explain what kind of publication it is.

## Search-result promise

Search titles and descriptions must match the classification of the destination page.

- Qualified Market Research or Intelligence Systems Research uses the `Bitmomo Research` title suffix.
- Legacy/editorial/unclassified posts use only the `Bitmomo` suffix.
- Generic membership in the historical `Riset` category is never enough to earn an institutional-research label.
- Meta descriptions prefer the manual excerpt and otherwise derive a concise factual snippet from article content.
- Search-result copy must not imply a capability, methodology, author, dataset, or research classification that the article cannot support on-page.

Google may retain older indexed titles/snippets until recrawl. Source correctness and deployment come first; reindexing is an operational follow-up, not a reason to weaken the canonical source.

## First-screen orientation

A qualified research article must make the following clear before the body begins:

1. research context / path back to Bitmomo Research;
2. institutional classification (`MARKET RESEARCH` or `INTELLIGENCE SYSTEMS RESEARCH`);
3. article title;
4. optional editorial deck only when a manual excerpt exists;
5. publisher context (`Bitmomo Research`);
6. publication date;
7. meaningful update date when the article changed materially after publication;
8. estimated reading time.

A legacy/editorial article remains readable but receives neutral publication treatment. A historical `Riset` category must never appear as proof that an unclassified post is institutional research.

## Reading geometry

Long-form research uses a deliberately narrower measure than product/archive surfaces.

- canonical body max width: approximately **720px**;
- desktop body font: **17–18px**;
- mobile body font: **17px**;
- body line-height: at least **1.65**, target around **1.75–1.8**;
- article H1 remains below oversized marketing-hero scale;
- paragraphs, lists, headings, captions, tables and code use stable spacing that does not depend on WordPress/Elementor legacy defaults.

The goal is sustained reading comfort, not maximum information density.

## Content hierarchy

- Exactly one route-level H1.
- H2 starts major analytical sections.
- H3/H4 support subsections without visually competing with the article title.
- Heading spacing must make the beginning of a new argument obvious.
- Body copy must not be pure white against dark backgrounds; headings may use brighter ink.
- Strong/emphasis styles must remain readable without becoming a second accent system.

## Figures, captions, tables and technical content

- Featured images are article figures, not decorative magazine heroes.
- Existing media captions are rendered visibly when supplied.
- In-body figures and captions use a consistent research style.
- Wide figures may break out modestly beyond the reading column without creating page-level horizontal overflow.
- Tables remain horizontally scrollable on narrow screens rather than shrinking text below readable sizes.
- Code/pre blocks scroll internally and never widen the page.
- Blockquotes are restrained and typography-led rather than card-like.

## Research trust treatment

Qualified research ends with a compact Research Standard reminder:

**Evidence before narrative.**

It links back to the canonical Research Standard on the Research Hub. This is context, not a marketing CTA.

The article must not insert a Founding Whitelist or other primary sales block into the reading flow.

## Related reading

Generic WordPress previous/next navigation is forbidden on the canonical article surface because category order can cross research boundaries.

- Market Research recommends qualified Market Research only.
- Intelligence Systems Research recommends qualified Intelligence Systems Research only.
- Unclassified/editorial content may use a neutral related-publication path, but generic `Riset` must not be used as its fallback category.
- Related content uses restrained editorial rows rather than another image-heavy card wall.

## Editorial deck policy

The article template shows a deck only from a manually authored WordPress excerpt. It does not generate a visible deck from the first paragraph because that would duplicate the opening copy.

For every new qualified research publication, editors should supply a concise one- or two-sentence manual excerpt that works both as:

- an on-page orientation/deck; and
- the preferred search/meta description source.

## Browser acceptance

The staging browser contract must dynamically discover at least one qualified article from the current Research Hub selectors and audit it at 390px and 1440px.

Acceptance requires:

- HTTP 200;
- exactly one visible H1;
- no page-level horizontal overflow;
- body reading width no more than 740px;
- computed body font size at least 17px;
- computed line-height/font-size ratio at least 1.65;
- H1 no larger than 54px;
- publisher context contains `Bitmomo Research`;
- publication-date context exists;
- reading-time context exists;
- institutional label is either Market Research or Intelligence Systems Research;
- one research-context breadcrumb;
- one Research Standard trust block;
- zero generic `.bm-post-nav` previous/next blocks;
- zero console errors and uncaught page errors;
- zero serious/critical axe violations on acceptance viewports.

## Source ownership

- classification and article metadata helpers: `inc/template-functions.php`;
- canonical article markup: `single.php`;
- article reading system: `assets/css/article-reading.css`;
- SEO title/description ownership: `functions.php`;
- rendered browser acceptance: `scripts/check-public-ui.mjs`;
- deployment requirement: `config/production-runtime.json`.

These pieces form one contract. A change to one layer must not silently weaken the others.

# Architecture Ownership

Bitmomo stays maintainable by having one canonical owner for each concern. A renderer may project canonical data; it must not quietly create a second business/data engine.

## Runtime layers

| Layer | Canonical owner | Responsibility | Must not own |
|---|---|---|---|
| Market ingestion + intelligence computation | `bitmomo-ai` | source acquisition, quality/freshness, scores, drivers, public-safe canonical projection inputs | sales/entitlement UI |
| Regime classification | `bitmomo-regime` | regime state, hysteresis, regime history/runtime adapter | Pro access, page layout |
| Public projection boundary | `class-bitmomo-public-intelligence-adapter.php` | fail-closed public contract consumed by public surfaces | recomputing market logic in templates |
| BTC Intelligence product surface | `bitmomo-btc-intelligence` | BTC page, market context, accountability/proof presentation | independent scoring methodology |
| Pro lifecycle/product | `bitmomo-pro` | whitelist, entitlement, briefs, paid/protected projection, email lifecycle | canonical market-data computation |
| Site shell/content system | `bitmomo-child-v3` | navigation, homepage, research/article shell, SEO/public metadata | duplicating plugin business logic |

Cross-layer data should move through explicit adapters/contracts. If two components calculate the same concept independently, that is an architecture defect until one becomes canonical.

## Frontend ownership

New CSS must have a named owner. The intended theme ownership is:

- `assets/css/design-system.css` — tokens/primitives only;
- `navigation-footer.css` — global shell/navigation/footer;
- `home*.css` — homepage-only composition;
- `research.css` — Research Hub/archive;
- `article-reading.css` — long-form reading experience;
- `public-surfaces.css` / `public-readability.css` — shared public-surface contracts;
- plugin-local CSS — only the plugin's own product surface.

`custom.css` is a legacy compatibility layer. It is frozen at its current byte ceiling; no new feature should add rules there. Stable legacy rules should be migrated into the canonical owner when that surface is next materially changed.

Avoid `!important` unless required to cross a third-party boundary and the reason is documented adjacent to the rule. A later override should not be used as the normal way to resolve ownership conflicts.

## Change rule

Every PR touching a shared contract names the canonical owner/files in the PR body. If another open PR is changing the same owner, coordinate or sequence the work rather than creating two competing final implementations.

# M1 semantic conflict decisions — 2026-09-10

Scope: decision artifact only. No production write, no deploy tooling finalization.

| path | classification | key semantic difference | rationale | recommended action | risk |
|---|---|---|---|---|---|
| wp-content/plugins/bitmomo-btc-intelligence/assets/css/bitmomo-btc-intelligence.css | STAGING_CANONICAL | Staging adds sample-size framing styles and refines metrics presentation. | Supports explicit data-quality disclosure; production is missing the newer presentation contract. | Keep staging as canonical source. | Medium: visual QA before deploy. |
| wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php | STAGING_CANONICAL | Staging adds SEO/meta handling, public-safe version labels, sample-status messaging, and methodology wording. | Intentional product/data transparency logic; preserves version separation and avoids raw classifier labels. | Keep staging as canonical source. | High: public BTC Intelligence page behavior changes. |
| wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js | STAGING_CANONICAL | Staging adds WhatsApp deep-link continuation from whitelist email. | Completes the server-side WhatsApp record-token flow already present in staging email/whitelist code. | Keep staging as canonical source. | Medium: user conversion flow requires browser QA. |
| wp-content/plugins/bitmomo-pro/bitmomo-pro.php | STAGING_CANONICAL | Staging bumps Bitmomo Pro from 0.12.3 to 0.12.4. | Version bump matches the newer whitelist/email/help/sales behavior in staging. | Keep staging as canonical source. | Low: metadata/runtime version only. |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-email-service.php | STAGING_CANONICAL | Staging changes Founding Beta copy to Founding Membership and adds WhatsApp follow-up link. | Aligns membership language and supports the newer WhatsApp capture flow. | Keep staging as canonical source. | Medium: outbound email copy/link behavior. |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php | STAGING_CANONICAL | Staging updates Founding Membership wording, title semantics, and BTC Intelligence URL. | Fixes public help copy and routes to the dedicated BTC Intelligence page. | Keep staging as canonical source. | Low: content/routing QA. |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php | STAGING_CANONICAL | Staging enables methodology page, adds Pro meta description, and anchors founding membership CTA. | Explicit product-page intent; not incidental drift. | Keep staging as canonical source. | High: commercial page visibility/copy must be reviewed before deploy. |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php | STAGING_CANONICAL | Staging exposes WhatsApp record action for email nonce generation and adds form method. | Required by the newer email and JS continuation flow; production lacks the full loop. | Keep staging as canonical source. | Medium: whitelist submission path QA. |
| wp-content/plugins/bitmomo-regime/bitmomo-regime.php | STAGING_CANONICAL | Staging bumps Regime from 0.5.2 to 0.5.3. | Version bump matches newer regime shortcode behavior in staging. | Keep staging as canonical source. | Low: metadata/runtime version only. |
| wp-content/plugins/bitmomo-regime/includes/class-bitmomo-regime-shortcodes.php | STAGING_CANONICAL | Staging adds compact chart, JSON history payload, modal script, and full target-day history handling. | Intentional frontend enhancement over existing public regime history; production is older list-only behavior. | Keep staging as canonical source. | Medium: accessibility/browser QA for chart/modal. |
| wp-content/themes/bitmomo-child-v3/assets/js/bitmomo-frontend.js | STAGING_CANONICAL | Staging fixes newsletter hash/modal timing and adds regime history progressive enhancement. | Supports newer staging regime history UI without fabricating data. | Keep staging as canonical source. | Medium: frontend regression QA. |
| wp-content/themes/bitmomo-child-v3/category.php | STAGING_CANONICAL | Staging changes research/category IA to compact latest index and revised category labels/copy. | File comments describe an intentional correction away from magazine-style layout. | Keep staging as canonical source. | Medium: visible research layout change. |
| wp-content/themes/bitmomo-child-v3/custom.css | STAGING_CANONICAL | Staging contains broader visual/data-card styles for BTC, Pro, whitelist, footer, and regime surfaces. | Matches newer staging templates/plugins and includes intentional support styles rather than isolated drift. | Keep staging as canonical source. | High: large visual regression surface. |
| wp-content/themes/bitmomo-child-v3/footer.php | STAGING_CANONICAL | Staging expands footer IA with product/company/social/newsletter sections. | Coherent with newer public navigation and product pages; production is minimal legacy footer. | Keep staging as canonical source. | Medium: visible footer/navigation change. |
| wp-content/themes/bitmomo-child-v3/functions.php | STAGING_CANONICAL | Staging suppresses Hello Elementor titles on Pro and Help pages. | Supports custom page headers rendered by Bitmomo templates/classes. | Keep staging as canonical source. | Low: page-title QA. |
| wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-assets.php | STAGING_CANONICAL | Staging loads Archivo fonts and additive typography stylesheet. | Intentional additive layer; required for the staging-only typography file. | Keep staging as canonical source. | Medium: external font request and visual QA. |
| wp-content/themes/bitmomo-child-v3/template-parts/btc-intelligence-card.php | STAGING_CANONICAL | Staging adds methodology/track-record bridge to dedicated BTC Intelligence page. | Reinforces provenance and public transparency; production omits the route. | Keep staging as canonical source. | Low: link/copy QA. |
| wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php | MERGE_REQUIRED | Staging has newer hero copy; production still renders the primary Driver metric that staging computes but no longer displays. | Production-only Driver display is a relevant Signal Engine semantic, while staging copy is newer intentional messaging. | Merge staging copy with production Driver display before any canonical deploy. | High: homepage Signal Engine semantics. |
| wp-content/themes/bitmomo-child-v3/assets/css/bitmomo-typography.css | STAGING_CANONICAL | File exists in staging/M0 and is missing in production. | Intentional additive typography/control-surface layer referenced by staging asset loader. | Keep staging file as canonical source. | Medium: typography/browser QA. |

Counts:

- STAGING_CANONICAL: 18
- PRODUCTION_CANONICAL: 0
- MERGE_REQUIRED: 1
- FOUNDER_DECISION_REQUIRED: 0

Founder decisions required: none.

PRODUCTION CHANGED: NO

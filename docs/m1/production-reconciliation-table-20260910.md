# M1 production/staging reconciliation table — 2026-09-10

| path | staging/M0 version | production version | semantic difference | recommended canonical version | risk |
|---|---|---|---|---|---|
| wp-content/plugins/bitmomo-btc-intelligence/assets/css/bitmomo-btc-intelligence.css | M0/staging SHA ad6f12c7904b | production SHA 8217325ef3e9 | BTC Intelligence public page/style contract differs: labels, copy, meta/sample handling, layout. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-intelligence-page.php | M0/staging SHA 5bc0a40139ec | production SHA bf4ea74d9139 | BTC Intelligence public page/style contract differs: labels, copy, meta/sample handling, layout. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-pro/assets/js/bitmomo-pro-whitelist.js | M0/staging SHA f4dee4e95fb9 | production SHA 2e79df710434 | Whitelist flow differs; staging adds WhatsApp deep-link/public record-action behavior absent in production. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-pro/bitmomo-pro.php | M0/staging SHA 6857172ce3ab | production SHA efde2f5014a2 | Plugin version differs: staging/M0 0.12.4, production 0.12.3; bootstrap/runtime may differ. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-email-service.php | M0/staging SHA 9a1ade374518 | production SHA 841a3d7eab2f | Email/notification behavior differs, tied to staging whitelist flow. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-help-center.php | M0/staging SHA 72e63f9a5206 | production SHA 07a95c0ff990 | Help-center content/routing differs between environments. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-sales.php | M0/staging SHA b5a31746f2bf | production SHA b9ecd0f70cef | Sales/methodology behavior differs; production has methodology page not live while staging enables it. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-whitelist.php | M0/staging SHA 5cfb75940ef3 | production SHA 6b98837120f3 | Whitelist flow differs; staging adds WhatsApp deep-link/public record-action behavior absent in production. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-regime/bitmomo-regime.php | M0/staging SHA 7801ed57da15 | production SHA eabbe690001d | Plugin version differs: staging/M0 0.5.3, production 0.5.2; regime runtime semantics may differ. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/plugins/bitmomo-regime/includes/class-bitmomo-regime-shortcodes.php | M0/staging SHA 36edab304389 | production SHA 2ce67917ce80 | Divergent implementation/copy/layout; requires owner decision. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/assets/js/bitmomo-frontend.js | M0/staging SHA 6c1542488c49 | production SHA 945e11df3c20 | Frontend asset loading/runtime behavior differs. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/category.php | M0/staging SHA 906575a9dde5 | production SHA ef586acdf3c6 | Research/category layout differs visibly between production and staging/M0. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/custom.css | M0/staging SHA 73c071c2f37f | production SHA 50c4cd306a46 | Large visual/layout stylesheet divergence. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/footer.php | M0/staging SHA 578e306c77dc | production SHA 94e4cd7ecb16 | Footer information architecture/copy differs. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/functions.php | M0/staging SHA 2a6aebdc27fd | production SHA d992940067d7 | Frontend asset loading/runtime behavior differs. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/inc/trait-bitmomo-assets.php | M0/staging SHA b6b3c29d2af2 | production SHA 193b5c07445f | Frontend asset loading/runtime behavior differs. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/template-parts/btc-intelligence-card.php | M0/staging SHA afdce5ff357a | production SHA 248c99dcd90c | BTC Intelligence public page/style contract differs: labels, copy, meta/sample handling, layout. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/template-parts/home-hero.php | M0/staging SHA 49a8122b361a | production SHA 44860b9ecee5 | Homepage Signal Engine presentation differs, including confidence/driver/copy treatment. | Ambiguous — hold both; do not overwrite. | High |
| wp-content/themes/bitmomo-child-v3/assets/css/bitmomo-typography.css | Present in staging/M0 | Missing in production | Missing-file evidence: production lacks typography stylesheet present in staging baseline. | Ambiguous — do not add to production until visual intent is approved. | Medium |

Ambiguous conflict count: 19. No canonical side selected in M1.

PRODUCTION CHANGED: NO

# M1 production configuration baseline — 2026-09-10

Production inventory was read-only against `bitmomo.id`. No production file, database, cache, plugin, theme, deployment state, or WordPress configuration was changed.

- SSH user/account: `u689746960`
- SSH endpoint: `46.202.138.170:65002` via local SSH alias `bitmomo-staging`
- Server host label: `id-dci-web1152.main-hosting.eu`
- Production document root: `/home/u689746960/domains/bitmomo.id/public_html`
- Staging document root: `/home/u689746960/domains/seagreen-snail-158456.hostingersite.com/public_html`
- PHP binary: `/opt/alt/php81/usr/bin/php`
- PHP version: `8.1.34`
- WP-CLI version: `2.12.0`
- MySQL client: `11.8.8-MariaDB`
- WordPress version reported by WP-CLI: `7.1`
- Active theme: `bitmomo-child-v3` `1.0`; parent theme: `hello-elementor` `3.5.1`
- Active Bitmomo plugins: `bitmomo-ai` `1.3.0`, `bitmomo-btc-intelligence` `0.1.0`, `bitmomo-regime` `0.5.2`, `bitmomo-pro` `0.12.3`
- Selected settings: `show_on_front=posts`, `permalink_structure=/%postname%/`, `template=hello-elementor`, `stylesheet=bitmomo-child-v3`, `WP_DEBUG=false`, `WP_CACHE=true`, `WP_ENVIRONMENT_TYPE=<undefined>`, `BITMOMO_AI_AUTO_PUBLISH=<undefined>`
- Known production page IDs: `/btc-intelligence/` `2494`, `/help/` `2458`, `/pro/` `2445`, `/account/` `2446`, `/pro-dashboard/` `2443`, `/disclaimer/` `2435`, `/kebijakan-privasi/` `2433`, `/tentang-kami/` `2431`
- Bitmomo cron hooks observed: `bitmomo_ai_daily_generation`, `bitmomo_regime_daily_evaluation`, `bitmomo_ai_outcome_settlement`, `bitmomo_ai_morning_generation`

M1 classification: production and M0/staging differ semantically. M1 must stop before production deploy tooling can be considered safe.

PRODUCTION CHANGED: NO

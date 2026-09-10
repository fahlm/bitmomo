# M1 production inventory — 2026-09-10

Production target verified read-only: `bitmomo.id` resolves to `/home/u689746960/domains/bitmomo.id/public_html` on the SSH account.

Managed Bitmomo production directories present:

- `wp-content/plugins/bitmomo-ai`
- `wp-content/plugins/bitmomo-btc-intelligence`
- `wp-content/plugins/bitmomo-pro`
- `wp-content/plugins/bitmomo-regime`
- `wp-content/themes/bitmomo-child-v3`

Additional Bitmomo themes present but not in deploy-managed scope:

- `wp-content/themes/bitmomo-child`
- `wp-content/themes/bitmomo-speed`

Comparison against M0 staging baseline commit `e195619b8030fbe43a8ded48410040a07c2899af` found:

- Known repo files compared: 102
- Production managed files in baseline scope: 101
- Drifted managed files: 18
- Missing in production: 1
- Extra in production: 0

The 18 drifted files were copied into `docs/m1/production-rescue/website/` as rescue evidence.

M1 stop condition: these differences are semantic, including plugin version differences, environment-specific page IDs, whitelist/deep-link behavior, methodology page live flag, and visible theme/template/copy differences. No production deploy was run.

PRODUCTION CHANGED: NO

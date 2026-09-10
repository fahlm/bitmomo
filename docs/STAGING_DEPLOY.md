# Staging deploy runbook

Scope: `https://seagreen-snail-158456.hostingersite.com` only. Production `bitmomo.id` is out of scope.

## What deploys

The staging deploy workflow writes only these repo roots:

- `website/wp-content/themes/bitmomo-child-v3`
- `website/wp-content/plugins/bitmomo-ai`
- `website/wp-content/plugins/bitmomo-pro`
- `website/wp-content/plugins/bitmomo-regime`
- `website/wp-content/plugins/bitmomo-btc-intelligence`

It does not deploy the three known stray server files:

- `themes/bitmomo-child-v3/bitmomo-typography/`
- `themes/bitmomo-child-v3/bitmomo-typography-deploy.zip`
- `themes/bitmomo-typography.css/`

Those are cleanup items for the founder, not expected deploy content.

## First run

1. Confirm staging still matches the branch.
2. Configure the GitHub secrets listed in `docs/DESIGN_SYSTEM.md`.
3. Run **Deploy staging** manually with `dry_run=true` and `allow_initialize=true`.
4. If that passes, run it with `dry_run=false` and `allow_initialize=true`. This creates the first real `deploy/staging-manifest.json` commit.
5. Run it again with `dry_run=false` and `allow_initialize=false`. It should report no drift and no file changes.

## Normal run

Run **Deploy staging** manually with `dry_run=false` and `allow_initialize=false`.

The script downloads each manifest-tracked remote file and hashes it before upload. If a file differs from the last committed manifest, the job aborts before writing anything.

## Smoke check

After a non-dry run, the script purges cache when a purge endpoint is configured and fetches these pages anonymously:

- `/`
- `/pro/`
- `/btc-intelligence/`

It also fetches the first stylesheet from `/pro/` and checks that the combined anonymous CSS still contains `--bm-font-sans`.

## LiteSpeed settings

The load-bearing LiteSpeed exclusions still need to be exported from WordPress into the repo. Until they are exported, cache/plugin restores can silently break the whitelist form or anonymous CSS. Record the exact UCSS and deferred-JS exclusions here when exported from staging.

# Staging deploy runbook

Scope: `https://seagreen-snail-158456.hostingersite.com` only. Production `bitmomo.id` is out of scope.

## What deploys

The staging deploy workflow writes only these repo roots:

- `website/wp-content/themes/bitmomo-child-v3`
- `website/wp-content/plugins/bitmomo-ai`
- `website/wp-content/plugins/bitmomo-pro`
- `website/wp-content/plugins/bitmomo-regime`
- `website/wp-content/plugins/bitmomo-btc-intelligence`

The staging deploy workflow scans these wider remote roots before writing:

- `wp-content/themes`
- `wp-content/plugins`

That wider scan is intentional. Archives, test folders, symlinks, misplaced folders such as `themes/bitmomo-typography.css/`, and unrelated plugin/theme folders are reported as server-only extras unless they are tracked deploy output. The upload filter stays strict; the scan filter stays suspicious.

## First run

1. Confirm staging still matches the branch.
2. Configure the GitHub secrets listed in `docs/DESIGN_SYSTEM.md`.
3. Run **Deploy staging** manually with `dry_run=true`, `allow_initialize=true`, and `allow_extras=false`. The first run should report any server-only extras and exit non-zero.
4. Inspect the reported extras. The report should include any stray archives such as `*.zip`, test paths found on staging, and symlinks with their targets.
5. Remove reviewed stray files by hand, or rerun with `allow_extras=true` only when the extras have been inspected and deliberately tolerated.
6. If the dry run passes, run it with `dry_run=false` and `allow_initialize=true`. This creates the first real `deploy/staging-manifest.json` commit.
7. Run it again with `dry_run=false` and `allow_initialize=false`. It should report no drift and no file changes.

## Normal run

Run **Deploy staging** manually with `dry_run=false` and `allow_initialize=false`.

The script scans the configured scan roots, hashes remote files once, and classifies them before upload:

- known: in the manifest and unchanged
- drifted: in the manifest but hash changed
- stale: in the manifest and still present on the server, but removed from Git
- reconciled: in the manifest and removed from Git, but already absent on the server
- extra: on the server but in neither the manifest nor Git, including symlinks and files excluded from upload such as archives or tests

Drift, stale files, and extras abort by default before writing anything. Use `allow_extras=true` only after inspecting server-only files. Use `allow_delete=true` only when stale files should be removed; the script verifies their hashes against the manifest before deleting.

Old `*.bmdeploy-tmp` files created by interrupted uploads are swept at the start of each run and logged. Nested temp directories are removed from the bottom up.

## Smoke check

After a non-dry run, the script purges cache when a purge endpoint is configured and fetches these pages anonymously:

- `/`
- `/pro/`
- `/btc-intelligence/`

It also asserts `/pro/` serves exactly one stylesheet, fetches it, and checks that the combined anonymous CSS still contains `--bm-font-sans`.

## Half-deploy recovery

If an SFTP connection drops after some files upload but before the manifest commit, the next run should refuse with drift. Treat that as a safety stop: follow `docs/staging-rollback-runbook.md`, compare the server against the last manifest, and either restore the old files or intentionally rerun after recording the correct manifest state.

## LiteSpeed settings

The load-bearing LiteSpeed exclusions still need to be exported from WordPress into the repo. Until they are exported, cache/plugin restores can silently break the whitelist form or anonymous CSS. Record the exact UCSS and deferred-JS exclusions here when exported from staging.

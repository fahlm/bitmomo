# Bitmomo Design System

This document is the durable record for the frontend foundation work on staging. It covers the current branch `rescue/staging-recovery-2026-09-09` and the staging site `https://seagreen-snail-158456.hostingersite.com`.

## Type scale

The token layer lives in `website/wp-content/themes/bitmomo-child-v3/assets/css/bitmomo-typography.css`. WP-1 declared the current scale without changing rendered colours or business copy.

| Token | Value | Role |
|---|---:|---|
| `--bm-fs-micro` | 10px | fine print / compact metadata |
| `--bm-fs-eyebrow` | 12px | uppercase labels |
| `--bm-fs-caption` | 13px | captions and secondary metadata |
| `--bm-fs-body-sm` | 15px | compact body copy |
| `--bm-fs-body` | 16px | default prose |
| `--bm-fs-body-lg` | 18px | lead copy |
| `--bm-fs-h3` | 20px | small section titles |
| `--bm-fs-h2` | 28px | section titles |
| `--bm-fs-h1` | `clamp(2rem, 3.6vw, 2.75rem)` | page hero title |
| `--bm-fs-display` | `clamp(2.5rem, 5.2vw, 4.875rem)` | display hero title |

## Weight and radius sets

Approved weights are 400, 500, 600, and 700. Any legacy `650` should be mapped to 600 or 700 during WP-2, based on the component role.

Approved radii are control, card, and pill. Legacy `999px` and `9999px` are one intent and should map to the pill token during WP-2.

## Spacing and breakpoints

The spacing step declared in WP-1 is 4px based: 0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 56, and 64px.

The current breakpoint inventory still needs WP-3 consolidation. The intended documented set for future repair is 480, 768, 1024, and 1280px, but existing media queries must be inventoried before replacement.

## Palette

The palette is locked. Token migration may reference these values but must not redefine them.

| Role | Value |
|---|---|
| Ground | `#0c1c2a` |
| Panel | `#0b1220` / `#101a2c` |
| Line | `#263b58` |
| Text | `#e6edf5` |
| Muted text | `#93a4bd` |
| Bullish / teal | `#2dd4bf` |
| CTA / orange | `#f4ad32` |
| Bearish | `#ff7b6d` |

## Deploy procedure and drift manifest

Staging deploys are managed by `.github/workflows/staging-deploy.yml` and `scripts/deploy_staging.py`.

The manifest is `deploy/staging-manifest.json`. It records each deployed file by path, SHA-256, size, deploy time, source commit, remote root, deploy roots, and scan roots. The deploy script checks remote file hashes against this manifest before writing. It scans `wp-content/themes` and `wp-content/plugins`, so server-only archives, test folders, symlinks, misplaced theme folders, and unrelated plugin/theme folders are visible as extras even when they are not deployable content. Uploads still write only the five approved deploy roots.

Remote classification uses these states: known, drifted, stale, reconciled, and extra. If a remote file has been hand-edited, an untracked file appears on the server, or a deleted file would be orphaned, the deploy aborts before upload unless the operator passes the explicit review flags. A manifest entry removed from Git but already absent from the server is marked reconciled instead of blocking the run.

Required GitHub secrets:

| Secret | Purpose |
|---|---|
| `STAGING_SFTP_HOST` | Hostinger SFTP host |
| `STAGING_SFTP_PORT` | SFTP port, usually 22 |
| `STAGING_SFTP_USER` | SFTP username |
| `STAGING_SFTP_PASSWORD` or `STAGING_SFTP_PRIVATE_KEY` | SFTP authentication; private keys may be Ed25519, ECDSA, or RSA |
| `STAGING_SFTP_PRIVATE_KEY_PASSPHRASE` | Optional passphrase for encrypted private keys |
| `STAGING_SFTP_KNOWN_HOSTS` | Required known_hosts line from `ssh-keyscan -p <PORT> <HOST>` |
| `STAGING_REMOTE_ROOT` | Absolute remote path to staging `public_html` |
| `STAGING_CACHE_PURGE_URL` | Optional cache purge endpoint |
| `STAGING_CACHE_PURGE_METHOD` | Optional purge method, defaults to POST |
| `STAGING_CACHE_PURGE_TOKEN` | Optional bearer token for purge endpoint |

First run must use `allow_initialize=true` only after confirming staging still matches Git. Later runs must leave initialization off. A second deploy from the same checkout should report no drift and no file changes.

## Metric formatting table

WP-7 owns this table. It must list every numeric surface on `/`, `/pro/`, and `/btc-intelligence/` with metric class, precision, unit, sign rule, tabular status, and provenance stamp.

## State matrix

WP-8 owns this table. It must cover loading, empty, error, stale, and partial states for regime history, homepage 30D chart, BTC intelligence panels, and the whitelist form.

## Performance baseline

WP-9 owns this section. It must record anonymous cold mobile measurements for `/`, `/pro/`, and `/btc-intelligence/`: LCP, CLS, INP, CSS bytes, JS bytes, and font bytes.

## Invisible contracts

WP-5 owns this section. Current known contracts:

| Surface | Contract | Pinning file |
|---|---|---|
| Regime history | PHP emits `.bmreg-history-data` JSON and JS builds the modal/chart UI | `website/wp-content/plugins/bitmomo-regime/tests/test-bitmomo-regime-history-widget.php` |
| Homepage 30D chart | `.bm-direction-bar` markup consumed by theme JS | Not yet pinned |
| Whitelist form | `.bm-wl-*` markup consumed by `bitmomo-pro-whitelist.js` and LiteSpeed exclusions | Not yet pinned |

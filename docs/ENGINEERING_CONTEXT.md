# Bitmomo Engineering Context

Living memory for engineering work on Bitmomo. Read this before re-analyzing the
repo from scratch — it should answer "what exists, where, and why" without
needing to open every file again.

## Repo layout

- `website/wp-content/themes/bitmomo-child-v3/` — the only thing that actually
  deploys to Hostinger. Everything the live site reads must live here.
- `docs/` — audits, runbooks, this file.
- `prompts/` — planned home for BTC-analyst prompts/schemas (not yet populated).
- `data/` (top-level, if ever used) — NOT deployed. Any data WordPress needs at
  runtime must live inside the theme folder (see `.../data/` below), not here.

## Git workflow in use

- `main` = production baseline. Never edited directly.
- One short-lived `feature/*` branch per change, PR into `main`, merge via
  GitHub UI (founder reviews and clicks merge).
- CI: `.github/workflows/theme-safety.yml` runs PHP syntax + baseline-file
  checks on PRs and pushes to `main`/`feature/**`/`fix/**`/`chore/**`. It is
  **not** a deploy pipeline — merging to `main` does not touch the live site.
- **Deployment is manual.** Staging is provisioned at
  `seagreen-snail-158456.hostingersite.com`; there is still no CI/CD. Theme
  releases are packaged from a reviewed commit, backed up, uploaded to
  staging, smoke-tested, and only then considered for production.
- Local git note: the connected device's sandboxed shell blocks file deletion,
  which can leave stray `*.lock` files under `.git/`. If git commands start
  failing with "Unable to create .../index.lock: File exists" or similar,
  `mv` (not `rm`, which is blocked) the stale lock out of any `refs/` subtree
  first (a lock file left inside `refs/heads/` or `refs/remotes/` breaks ref
  resolution for *all* git commands, not just the one that made it) into e.g.
  `.git/_stale_locks/`, then retry. Prefer `git reset <target>` (no `--hard`)
  over `git checkout`/`git reset --hard` when switching branches or syncing to
  a remote ref — `reset` without `--hard` only rewrites the index, not the
  working tree, so it avoids the unlink-on-checkout failure entirely when the
  working tree content already matches the target.
- GitHub PAT (in the local `origin` remote URL) needs both **Contents:
  Read/write** and **Pull requests: Read/write** scopes to let an assistant
  session push branches and open PRs end-to-end; Contents-only will 403 on PR
  creation.

## What's built so far (P0–P3 of the cash-flow roadmap)

### P1 — BTC Daily Intelligence (MVP, manual data)
- Data source: `.../bitmomo-child-v3/data/btc-daily-sample.json` — one manual
  record, hand-edited for now. Fields: `timestamp`, `btc_reference_price`,
  `direction` (BULLISH/NEUTRAL/BEARISH), `confidence` (0–100), `expected_low`,
  `expected_high`, `expected_move_pct`, `consensus` {bullish/neutral/bearish},
  `key_drivers[]`, `invalidation`, `risk_level`, `analysts[]` (unused so far).
- Render: `template-parts/btc-intelligence-card.php` — reads + decodes the
  JSON itself (self-contained, no dependency on `functions.php`'s trait
  bootstrap); silently no-ops if the file is missing/invalid so a bad data
  file can never fatal the homepage.
- Wired into `front-page.php` only (the actual WP front page template — NOT
  `home.php`, which is the separate posts-index template and wasn't touched).
- Styling: new `.bm-btc-*` classes appended to `custom.css`, reusing existing
  design tokens (`--teal`, `--card-bg`, `--muted`, etc.) — no new colors
  invented except `#26d096`/`#ef4444` for bullish/bearish, and no `!important`
  added.
- **To update the data**: hand-edit the JSON file and redeploy. Nothing else
  needs to change.
- **Not yet built**: the 10-LLM analyst pipeline that would auto-generate this
  JSON. Intentionally deferred per the roadmap — validate UX/monetization on
  manual data first.

### P2 — Affiliate CTA (monetization layer)
- Central config: `inc/bitmomo-cta-config.php` — `bitmomo_get_cta( $key )` and
  `bitmomo_get_cta_url( $key )` (adds UTM params). One entry so far:
  `btc_intelligence`, pointing at the founder's real RedotPay referral link
  (`https://wap.redotpay.com/en/invite/?referralId=88bh7` — resolved from a
  `url.hk` short link the founder supplied, resolved to the direct URL so UTM
  params survive and to drop an unnecessary redirect hop).
- **To change/add a partner link**: edit the array in
  `inc/bitmomo-cta-config.php` only. No template changes needed.
- Click tracking: native WP AJAX (`wp_ajax_bitmomo_cta_click` /
  `wp_ajax_nopriv_...`), no external analytics service. Increments a per-day
  counter in the `bitmomo_cta_clicks` option. JS listener appended to the
  existing `assets/js/bitmomo-frontend.js` (delegated click on
  `[data-bm-cta]`, `navigator.sendBeacon` with `fetch` fallback). Ajax URL +
  nonce reach the page via `wp_localize_script('bitmomo-frontend',
  'bitmomoConfig', ...)` added in `inc/trait-bitmomo-assets.php`.
- **To read click counts**: `get_option('bitmomo_cta_clicks')` — array keyed
  by CTA key, then by `Y-m-d`, value = count. No admin UI for this yet.

### P3 — Distribution output (reusable content assets)
- `inc/bitmomo-content-assets.php` — `bitmomo_build_btc_content_assets( $record )`
  turns one BTC Intelligence record into three text blocks: X/Twitter post,
  YouTube Shorts script outline, newsletter blurb.
- Surfaced at **wp-admin → Tools → BTC Content Assets** (`manage_options`
  only). Click-to-select `<textarea>`s, manual copy/paste. **No auto-posting**
  to X/YouTube/email — intentionally out of scope until the manual workflow
  is validated.

## Module loading pattern

`functions.php` requires a fixed list of files from `$bitmomo_modules` (traits
+ standalone files) before booting `Bitmomo_Performance_Optimizer`. New
theme-level PHP files that need to run on every request get added there. The
BTC template-part files intentionally do NOT go through this list — they're
self-contained and only loaded when a template calls
`get_template_part(...)`, so a bug in them can't break every page.

## Known open items (not yet done, not currently blocking)

1. **Production promotion is pending.** Commit `85492ad` (through PR #14) was
   deployed to staging on 2026-08-26 after a server-side theme backup. It has
   not been promoted to production. The staging smoke test passed for the
   homepage, single post, real category, tag, pagination, 404, AI preview,
   noindex, desktop width, and a 390px mobile viewport.
2. **Complete staging acceptance before production.** The three footer Pages
   (`tentang-kami`, `kebijakan-privasi`, and `disclaimer`) were created and
   verified on staging on 2026-08-26. All return 200 and remain protected by
   staging noindex. The privacy Page intentionally notes that a verified
   privacy contact channel must be added before production. Broader
   accessibility, SEO/social-image, performance, and restore-drill evidence
   remains to be completed per `docs/staging-rollback-runbook.md`.
3. **"5 production gates"** mentioned in the founder's brief were never found
   documented anywhere in this repo — do not invent them if asked again;
   confirm with the founder what they mean, if it matters.
4. **No admin UI for CTA click counts** — currently `wp_options` only,
   readable via WP-CLI/db or a future small admin page.
5. **10-LLM analyst automation** — not started. When it happens, it should
   only need to change how `data/btc-daily-sample.json` gets written (e.g. a
   cron job or webhook writing a fresh file) — the rendering, CTA, and
   content-assets code should not need to change.

## Suggested next steps (engineering, not business decisions)

- After the founder confirms P1–P3 are production-ready: create and review
  the three required trust/legal Pages on staging, then revisit the audit
  findings in
  `docs/technical-audit-v1.md` (Phase 4 — SEO/mobile/performance) — many are
  already resolved by the phase-0–4 refactor merged before this session
  started (shared header/footer, deduped hamburger menu, trait-based
  `functions.php`), so re-check which findings still apply before doing new
  work there.
- Small non-invasive polish worth doing opportunistically: verify the BTC
  Intelligence card's mobile layout once it's actually live (it was built
  against the existing `--breakpoint` patterns in `custom.css` but has not
  been visually tested in a real browser).

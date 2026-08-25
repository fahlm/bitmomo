# Bitmomo Staging and Rollback Runbook

**Status:** Staging active; acceptance in progress
**Production changes:** prohibited until every preflight gate below is complete  
**Current staging candidate:** `85492add602f89361371d45310656e2887ef8199`

This runbook defines the safe path from the current production baseline to a staging environment. It intentionally contains no hosting credentials, secret values, or automatic production deployment.

## Environment boundaries

| Environment | Purpose | Write policy |
|---|---|---|
| Local/repository | Review, static checks, documentation | Changes only on non-default branches |
| Staging | Runtime verification and acceptance testing | Deployment allowed after backup and checklist |
| Production | Public site | No direct edits; promotion only after approval and rollback readiness |

Staging must use its own hostname, database, uploads copy, salts/keys, API credentials, cache namespace, analytics setting, and email behavior. It must not send real newsletters, transactions, webhooks, or indexing signals.

## Required information before staging can be provisioned

Record these values in the hosting password manager or deployment system, **not in GitHub**:

- staging hostname and document root;
- hosting project/account owner;
- WordPress, PHP, parent theme, and plugin versions;
- database and uploads backup locations with retention;
- DNS/CDN/cache ownership and purge procedure;
- staging-only database credentials and WordPress salts;
- MailPoet/email suppression method;
- API/provider sandbox credentials;
- deployment user and least-privilege access;
- technical owner and approval owner;
- rollback decision maker and contact channel.

## Production guardrails

- Never edit theme files from the WordPress editor or hosting file manager.
- Never point staging at the production database.
- Disable search indexing on staging at WordPress level and via HTTP headers/authentication.
- Disable outbound MailPoet campaigns and other external side effects.
- Sanitize personal data when copying production data.
- Keep secrets outside the repository and logs.
- Require an explicit approval before any production deployment.
- Preserve the exact production baseline tag/commit for rollback.
- Do not enable automatic production deployment during Phase 0–1.

## One-time staging setup

1. Create an isolated staging site and database.
2. Capture production inventory: WordPress/PHP version, active theme, parent theme, plugins, mu-plugins, cron, cache/CDN, and relevant server rules.
3. Take full production backups of database, uploads, themes/plugins, and server configuration.
4. Verify that backups can be read and that restore access exists.
5. Copy production to staging using the hosting platform's supported mechanism.
6. Replace production URL references safely using WordPress-aware serialization handling.
7. Rotate staging salts and credentials.
8. Disable indexing, real email, analytics contamination, payment/webhook calls, and scheduled publication side effects.
9. Deploy the repository baseline to staging only.
10. Purge staging cache and verify the deployed file hashes/commit.
11. Run the acceptance checklist below.
12. Record evidence and sign-off.

## Acceptance checklist

### Site health

- Homepage, latest posts, category, tag, single article, pagination, and 404 load without PHP errors.
- WordPress Site Health has no new critical issue.
- Error logs contain no new fatal, warning, or repeated notice caused by the theme.
- Admin login, editor, preview, and scheduled post behavior remain functional.
- MailPoet form opens but cannot send a real campaign.

### Visual and mobile

Test at minimum 320, 375, 768, 1024, and 1440 CSS pixels:

- logo/header/nav do not overlap;
- hamburger opens/closes and scrolling recovers;
- focus indicator remains visible;
- cards, titles, images, pagination, modal, tables, embeds, and long links do not overflow;
- custom logo and fallback logo both render;
- admin bar does not cover the sticky header;
- landscape and 200% zoom remain usable.

Capture reference screenshots for homepage, archive, and single article on mobile and desktop.

### Accessibility

- One logical H1 per page.
- Menu button state is announced correctly.
- Menu and modal can be operated using keyboard only.
- Escape closes overlays and focus returns to the trigger.
- Dialog has an accessible name and focus remains within it while open.
- Links/buttons have meaningful names and visible focus.
- Automated scan has no new serious/critical violation; manually verify its key findings.

### SEO

- Canonical, title, robots, sitemap membership, and social metadata are correct.
- Staging responds with noindex protection.
- Article date/author/category data is consistent.
- Structured data has no new error and is not duplicated between theme and SEO plugin.
- No production URL unintentionally remains in staging navigation/forms.

### Performance

Record mobile and desktop results for homepage, category, tag, and single article:

- Core Web Vitals/Lighthouse or equivalent;
- LCP element and requested image URL;
- number/size of CSS, JS, images, fonts, and third-party requests;
- cache status, TTFB, console errors, and duplicate preloads;
- database query count/time where available.

Phase 1 changes must not regress the agreed baseline without a documented trade-off.

## Per-change staging deployment

1. Confirm CI passes and review the exact diff.
2. Confirm the change does not contain credentials, production data, or unrelated files.
3. Create a staging database/files backup.
4. Deploy the reviewed commit to staging.
5. Purge staging caches.
6. Run targeted smoke tests plus homepage/archive/single checks.
7. Check PHP/server/browser logs.
8. Compare screenshots and performance budget.
9. Record tester, commit, timestamp, results, and known limitations.
10. Obtain approval before scheduling any production promotion.

## Rollback procedure

Rollback is triggered by a fatal error, broken navigation/content, data corruption, unexpected external action, serious accessibility regression, SEO/indexing fault, or material performance regression.

1. Stop further deployment and enable maintenance protection if necessary.
2. Record the failed commit and visible symptoms.
3. Restore the last known-good theme release atomically.
4. If database/content changed, restore only after confirming scope and backup timestamp.
5. Purge application, object, CDN, and browser-facing caches as applicable.
6. Run homepage, archive, single, admin, and newsletter smoke tests.
7. Confirm logs stabilize and monitoring recovers.
8. Document the incident and keep the failed release out of production until a new review.

For the current baseline, the source rollback target is:

`d7a7149a4e0b459296f88452fecede2938d008b0`

A source rollback does not replace database/uploads backups.

## Evidence log template

| Field | Value |
|---|---|
| Environment | staging / production |
| Commit | |
| Deployed by | |
| Started/finished | |
| Backup identifier | |
| Tests performed | |
| Screenshots/report links | |
| Logs reviewed | |
| Known limitations | |
| Approval | |
| Rollback target | |

### Deployment record — 2026-08-26

| Field | Value |
|---|---|
| Environment | staging (`seagreen-snail-158456.hostingersite.com`) |
| Commit | `85492add602f89361371d45310656e2887ef8199` |
| Deployment package | `bitmomo-child-v3-85492ad.zip` |
| Package SHA-256 | `2e7ab20279edcd9ea305e7df2cce3aa7f8118853aedadbf830523476aa477689` |
| Backup identifier | `bitmomo-child-v3-backup-before-85492ad-20260826.zip` |
| Tests passed | Homepage, single, category, tag, pagination, 404, AI preview, staging noindex, desktop 1280px, mobile 390px, console warnings/errors, horizontal overflow |
| Known limitation | Footer trust/legal Pages are not created yet and return 404; full acceptance checklist and restore drill remain open |
| Production status | Not deployed |

## Phase 0 exit criteria

- Staging is isolated and protected from indexing/outbound side effects.
- Production inventory and version matrix are recorded.
- Full backup and a restore drill are verified.
- Repository CI passes on the candidate commit.
- Baseline screenshots, logs, and performance measurements exist.
- Deployment and rollback ownership are assigned.
- No production automation is enabled.
- Phase 1 stabilization scope is approved.

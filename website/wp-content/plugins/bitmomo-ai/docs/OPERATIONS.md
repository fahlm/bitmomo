# Bitmomo AI Operations Runbook

## Current rollout status

- Version: `1.0.26`
- Environments validated: staging and production shadow
- Publishing mode: production auto-publish is disabled by the safe default; manual, TradingView, and scheduled production-shadow inputs remain draft-first
- Production status: version `1.0.26` active in shadow mode
- Required before enabling production auto-publish: observe a successful scheduled production run, confirm mobile rendering, and obtain explicit approval

## Production shadow evidence

- Production backup verified before installation: complete UpdraftPlus backup from 25 August 2026, including database, plugins, themes, uploads, must-use plugins, and other files.
- Reviewed source: merged commit `0fb0e3fc0a119ce1183f62f720347f207b3368db` from PR #18.
- Installed artifact SHA-256: `6b92d5af81a175b2d39d2eb032a2a15c44ce6c69d364aac8707b8fc5d0f7e8a4`.
- Runtime validation: WordPress activated version `1.0.26` without a fatal error and diagnostics reported auto-publish disabled by the safe default.
- Shadow cycle: 27 August 2026 at 00:06 WIB created or refreshed post `2425` as a draft; quality passed and the audit log recorded publishing as disabled.
- Public verification: the production homepage returned HTTP 200 and the unauthenticated URL for draft `2425` returned HTTP 404.
- Hosting cron: separate staging and production jobs are configured every five minutes; the production job targets the `bitmomo.id` WordPress cron entry point without replacing the staging job.
- Freshness gate: the two files reviewed in PR #16 were deployed to the active production child theme and verified byte-for-byte against GitHub after WordPress accepted both edits.
- Freshness verification: the production homepage returned HTTP 200, retained its normal editorial content, omitted the expired BTC Daily Intelligence card, and produced no browser console errors.
- Auto-publish remains disabled until the remaining production observations pass and explicit approval is recorded.

## Daily schedule

- The WordPress event targets `19:10 WIB`.
- The analysis uses the latest fully closed candles: 1H for operational levels, 4H for primary context, and 1D for the broader regime.
- Hosting should call `wp-cron.php` every five minutes so the event is not dependent on website traffic.
- The hosting command is environment-specific. Do not commit usernames, home-directory paths, credentials, or tokens to the repository.

## Daily editorial workflow

1. Confirm the automatic run completed.
2. Open **Tools → Bitmomo AI** when the run reports `blocked` or `error`.
3. Review the audit log and quality result during routine evaluation, not as a daily pre-publication requirement.
4. Improve scoring and copy from forward-validation evidence rather than manually publishing each edition.

## Release gates

Scheduled auto-publishing is allowed only when all of the following are true:

- At least six of the seven quality checks pass.
- No critical check fails: freshness, completeness, reference price, zone ordering, bias/score consistency, and invalidation consistency.
- Required Binance public-data inputs are complete and fresh.
- The generated analysis is at most 180 minutes old.
- Support, resistance, and invalidation are logically consistent. The distance guard is the single non-critical check that may produce a safe 6/7 result.
- `BITMOMO_AI_AUTO_PUBLISH` is explicitly set to `true`.

The scheduler publishes automatically after these conditions pass. A 6/7 result is marked `degraded` and is auditable. TradingView and manual content remain draft-only.

## Data sources and graceful degradation

- Primary market data comes from Binance public, read-only endpoints; no Binance account or API key is required.
- Optional crowding inputs may degrade to `partial` without stopping the full analysis.
- Missing or stale required inputs block draft refresh through the quality gate.
- TradingView webhook ingestion is optional. Its secret must be defined only in `wp-config.php` and must never be committed.

## Monitoring and incident checks

Check **Tools → Bitmomo AI** when:

- no draft appears after the scheduled window;
- automation health reports a missing or overdue event;
- data freshness or completeness is blocked;
- the release-gate notice reports that an analysis has expired; or
- support/resistance no longer matches the current market context.

For a missed run, first verify the hosting cron is active and that WordPress reports a next scheduled event. A manual staging run may be used for diagnosis, but it must not be treated as proof that the automatic schedule works.

## Staging acceptance checklist

- Automatic 19:10 WIB run completes without manual intervention.
- A 7/7 or safe 6/7 result publishes automatically.
- A critical failure or score below 6/7 remains blocked and unpublished.
- Desktop and mobile preview remain readable.
- The first forward-validation record is evaluated inside its 22–27 hour observation window.
- No PHP errors, repeated drafts, or administrator-notice loops are observed.

## Production shadow rollout

1. Take a production backup and record the active theme/plugin versions.
2. Install the reviewed plugin artifact. Auto-publish remains disabled by default; an explicit `false` value may still be added as a visible kill switch.
3. Restrict preview output to administrators and run one production shadow cycle.
4. Confirm cron health, data freshness, quality gates, and mobile rendering.
5. Set `BITMOMO_AI_AUTO_PUBLISH` to `true` only after explicit approval.

## Rollback

1. Deactivate Bitmomo AI.
2. Remove its shortcodes or preview placement if they were enabled publicly.
3. Reinstall the previously approved plugin package if a version rollback is required.
4. Clear relevant WordPress/page caches.
5. Verify the public site and administrator screens.

Deactivation does not intentionally delete generated posts or validation metadata. Do not delete database content during routine rollback.

Auto-publish is fail-closed: if `BITMOMO_AI_AUTO_PUBLISH` is absent, scheduled analysis remains a draft. To enable publishing, set `define('BITMOMO_AI_AUTO_PUBLISH', true);` in `wp-config.php` only after approval. For a non-destructive emergency stop, set it to `false`; scheduled analysis continues as drafts while public auto-publishing stops.

## Security rules

- Never commit credentials, webhook URLs containing real tokens, database exports, or `wp-config.php`.
- Use a random webhook token of at least 32 characters and rotate it if exposed.
- Keep WordPress, the active theme, and plugins updated through the normal maintenance process.
- Production changes require explicit approval and a current backup.

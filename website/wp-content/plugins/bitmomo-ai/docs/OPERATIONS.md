# Bitmomo AI Operations Runbook

## Current rollout status

- Version: `1.0.25`
- Environment validated: staging only
- Publishing mode: conditional auto-publish for scheduled Binance runs; manual and TradingView inputs remain draft-first
- Production status: not deployed
- Required before production rollout: one successful 1.0.25 staging simulation and a current production backup

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
- `BITMOMO_AI_AUTO_PUBLISH` is not set to `false`.

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
2. Install the reviewed plugin artifact with `BITMOMO_AI_AUTO_PUBLISH` set to `false`.
3. Restrict preview output to administrators and run one production shadow cycle.
4. Confirm cron health, data freshness, quality gates, and mobile rendering.
5. Remove or set the kill switch to `true` only after explicit approval.

## Rollback

1. Deactivate Bitmomo AI.
2. Remove its shortcodes or preview placement if they were enabled publicly.
3. Reinstall the previously approved plugin package if a version rollback is required.
4. Clear relevant WordPress/page caches.
5. Verify the public site and administrator screens.

Deactivation does not intentionally delete generated posts or validation metadata. Do not delete database content during routine rollback.

For a non-destructive emergency stop, set `define('BITMOMO_AI_AUTO_PUBLISH', false);` in `wp-config.php`. Scheduled analysis continues as drafts while public auto-publishing stops.

## Security rules

- Never commit credentials, webhook URLs containing real tokens, database exports, or `wp-config.php`.
- Use a random webhook token of at least 32 characters and rotate it if exposed.
- Keep WordPress, the active theme, and plugins updated through the normal maintenance process.
- Production changes require explicit approval and a current backup.

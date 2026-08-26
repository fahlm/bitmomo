# Bitmomo AI Operations Runbook

## Current rollout status

- Version: `1.0.24`
- Environment validated: staging only
- Publishing mode: draft-first; the plugin never auto-publishes
- Production status: not deployed
- Required before production rollout: one successful automatic 19:10 WIB run and one completed 24-hour forward-validation observation

## Daily schedule

- The WordPress event targets `19:10 WIB`.
- The analysis uses the latest fully closed candles: 1H for operational levels, 4H for primary context, and 1D for the broader regime.
- Hosting should call `wp-cron.php` every five minutes so the event is not dependent on website traffic.
- The hosting command is environment-specific. Do not commit usernames, home-directory paths, credentials, or tokens to the repository.

## Daily editorial workflow

1. Confirm the automatic run completed and generated or refreshed a draft.
2. Open **Tools → Bitmomo AI** and inspect automation health, data freshness, and the quality-gate result.
3. Review the conclusion, support/resistance zones, invalidation level, scenarios, and five-axis evidence.
4. Confirm the analysis is no more than 180 minutes old.
5. Complete the editor confirmation checkbox only after the review is finished.
6. Publish manually. If any gate fails, keep the post as a draft and investigate the diagnostic message.

## Release gates

Production publishing is allowed only when all of the following are true:

- The seven-check quality gate passes.
- Required Binance public-data inputs are complete and fresh.
- The generated analysis is at most 180 minutes old.
- Support, resistance, and invalidation are logically consistent and within the configured distance guard.
- An editor explicitly confirms the review.
- The post is published manually.

The plugin intentionally does not provide automated publishing.

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
- A new draft is created or refreshed, never auto-published.
- All seven quality checks pass on current market data.
- Desktop and mobile preview remain readable.
- The first forward-validation record is evaluated inside its 22–27 hour observation window.
- No PHP errors, repeated drafts, or administrator-notice loops are observed.

## Production shadow rollout

1. Take a production backup and record the active theme/plugin versions.
2. Install the reviewed plugin artifact without activating public output.
3. Keep all generated posts as drafts and restrict preview output to administrators.
4. Run at least one scheduled cycle in shadow mode and compare its draft with staging.
5. Confirm cron health, data freshness, quality gates, and mobile rendering.
6. Enable public presentation only after explicit approval.

## Rollback

1. Deactivate Bitmomo AI.
2. Remove its shortcodes or preview placement if they were enabled publicly.
3. Reinstall the previously approved plugin package if a version rollback is required.
4. Clear relevant WordPress/page caches.
5. Verify the public site and administrator screens.

Deactivation does not intentionally delete generated posts or validation metadata. Do not delete database content during routine rollback.

## Security rules

- Never commit credentials, webhook URLs containing real tokens, database exports, or `wp-config.php`.
- Use a random webhook token of at least 32 characters and rotate it if exposed.
- Keep WordPress, the active theme, and plugins updated through the normal maintenance process.
- Production changes require explicit approval and a current backup.

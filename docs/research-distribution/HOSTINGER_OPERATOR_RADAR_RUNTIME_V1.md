# Bitmomo Opportunity Radar — Hostinger Runtime V1

## Purpose
Run the P7 Indonesia-first Opportunity Radar without Replit. Hostinger executes one PHP worker on a cron schedule. The worker reads public discovery APIs, consumes canonical Bitmomo Research Feed data from the existing WordPress runtime, creates operator-ready engagement briefs, and sends them to a private Telegram destination. It never posts, replies, likes, follows, DMs, or publishes on a social platform.

## Operating boundary
The human operator only:
1. opens the exact `source_url` in the alert;
2. pastes `ready_to_post`;
3. clicks Reply/Post.

All discovery, freshness filtering, scoring, evidence linking, drafting, dedupe, checkpointing, cost accounting, and notification delivery are automatic.

## Runtime location
Do not place secrets or mutable state under `public_html`.

Recommended layout:
- repository/runtime code: `/home/<account>/bitmomo-ops/`
- private config: `/home/<account>/.bitmomo/opportunity-radar.php`
- state: `/home/<account>/.bitmomo/opportunity-radar-state.json`
- WordPress remains in its existing domain `public_html` directory.

The Hostinger worker explicitly refuses config/state paths containing `/public_html/`.

## Deployment source
Deploy `feature/operator-alert-engine-v1` only for the first controlled validation. The PR remains stacked/draft and must not be treated as a production merge authorization.

Prefer a private checkout/copy outside the public web root. Hostinger's Git integration can deploy PHP repositories, but do not point this operations checkout at the existing Bitmomo `public_html` because Git deployment can overwrite the destination. A dedicated private directory is required.

## Private configuration
Copy `research/operator-alert/config/hostinger-config.example.php` to the private config path and populate it on the server. Never commit the populated file.

Required for X live trial:
- `repo_root`
- `wordpress_root`
- `state_path`
- `x_bearer_token`
- `telegram_bot_token`
- `telegram_chat_id`

Optional initially:
- `youtube_api_key` — blank disables YouTube discovery
- `manual_research_path` — blank uses canonical BTC Intelligence only

Set `dry_run=true` for the first validation run. In dry-run mode alerts are printed to cron output, Telegram is not used, and alert IDs are not marked delivered.

## Canonical research binding
The Hostinger worker bootstraps the existing WordPress installation read-only and calls `Bitmomo_Research_Feed_Provider_V1::build()`.

This preserves the P0 lineage gate:
- public projection and canonical runtime timestamp must match;
- reference price must match;
- directional bias must match;
- quality/provenance must be valid;
- only `distribution.eligible=true` research can support an operator alert.

If no distribution-eligible canonical research is available, the worker fails closed before producing opportunity alerts.

## X discovery lane
P7 uses one recent-search request per poll with:
- Indonesian language operator (`lang:id`);
- Indonesian BTC decision vocabulary;
- retweet/spam exclusions;
- `since_id` checkpoint;
- one-hour API lookback;
- public metrics for ranking.

Freshness policy remains stricter than the API lookback:
- <=10 minutes: ideal reply opportunity;
- <=30 minutes: reply-alert eligible;
- 30–60 minutes: monitor only;
- >60 minutes: suppressed.

## Cost guard
Default `daily_x_read_budget_usd` is USD 1.00.

The worker tracks estimated X Post-read spend in the private state file by UTC date. Before each poll it reserves against the P7 theoretical maximum of USD 0.05 for one X query. If the next poll could exceed the configured daily budget, X discovery is skipped automatically for the rest of that UTC day.

This is a safety ceiling, not a forecast of actual spend. Actual usage is based on returned billable Post reads.

## Cron
Hostinger supports PHP cron jobs. Schedule the worker every 5 minutes for the initial live trial.

Primary command target:
`research/operator-alert/bin/hostinger-run-once.php`

If the default private config path is used (`$HOME/.bitmomo/opportunity-radar.php`), the PHP cron only needs the worker path. If the hosting environment does not expose `HOME`, use a Custom cron invoking PHP and pass the private config path as the first argument.

Hostinger cron schedules use UTC. A five-minute cadence is timezone-independent.

## Concurrency
The worker obtains a non-blocking file lock next to the private state file. If a previous run is still active, the next cron invocation exits successfully with `previous_run_still_active` rather than performing duplicate API calls.

## State and dedupe
Private state retains:
- X `since_id`;
- optional YouTube checkpoint;
- delivered alert IDs (bounded history);
- UTC daily X estimated read spend;
- run count;
- last run timestamp and compact summary.

Only successfully delivered Telegram alerts are marked delivered in live mode. Failed notifications remain retryable. Dry-run never marks an alert delivered.

## First activation sequence
1. Deploy the P7 source to a dedicated non-public directory.
2. Create the private `.bitmomo` directory with owner-only permissions.
3. Create/populate the private config from the example.
4. Keep `dry_run=true`.
5. Run the worker once manually and inspect cron/stdout output.
6. Confirm canonical Research Feed items are available and X discovery succeeds.
7. Confirm any synthetic/real eligible alert contains exact X URL, age, evidence, caveat, and ready-to-post copy.
8. Configure the five-minute Hostinger cron.
9. Add Telegram credentials and validate one notification.
10. Change `dry_run=false` only after the notification payload is accepted.

## Kill switch
Any of these stop outbound operator alerts without touching WordPress:
- disable/delete the Hostinger cron;
- set `dry_run=true`;
- blank `telegram_bot_token` or `telegram_chat_id` (delivery fails and remains retryable);
- blank `x_bearer_token` (X discovery is skipped);
- set `daily_x_read_budget_usd` below the already-consumed daily amount.

No production WordPress file needs to be edited to stop this worker.

## Verification
A healthy live cron output is a one-line JSON summary containing:
- `ok=true`
- `mode=live_operator_alert`
- research item count
- discovered count
- alert count
- sent/failed count
- per-run X estimated cost
- cumulative daily X estimated cost
- configured daily budget

Use Hostinger's Cron Jobs `View Output` feature to inspect execution results during the trial.

## Rollout rule
Do not automate social posting after this runtime goes live. P7's boundary remains operator-assisted manual posting. A later outbound automation phase would require a separate policy/security review and explicit authorization.

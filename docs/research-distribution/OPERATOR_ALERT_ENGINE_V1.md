# Bitmomo Operator Alert Engine V1

Status: P7 CONTRACT / OPERATOR-ASSISTED / MANUAL SOCIAL POSTING  
Version: `operator-alert-engine-v1`  
Alert contract: `operator-alert-v1`  
Tracks: #159  
Depends on: PRs #144, #146, #149, #151, #153, #155, #157.

## Objective

The marketing engine must perform discovery, filtering, scoring, research matching, drafting, dedupe, cooldown, and operator notification automatically.

The human operator has one required social action only:

> open the exact source link, paste the prepared reply, and click Reply/Post manually.

P7 corrects the gap between the earlier source-complete pipeline and the desired operating system.

## Fast X reply lane

The default acquisition profile is Indonesia-first.

P7 issues one X Recent Search request per poll with:

- BTC/Bitcoin demand terms in Bahasa Indonesia;
- `lang:id`;
- `-is:retweet`;
- spam/seller exclusions;
- 10 results maximum;
- one-hour `start_time` window;
- `since_id` checkpoint on subsequent polls;
- `public_metrics` for post-filter priority ranking.

One request/poll is deliberate. It reduces X read cost and avoids brute-force keyword scanning while retaining a broad Indonesian decision-demand query.

### Freshness

Unsolicited reply opportunities use a hard operational gate:

- `<=10m`: ideal;
- `>10m && <=30m`: eligible;
- `>30m && <=60m`: monitor only;
- `>60m`: suppressed;
- a 12-hour-old tweet can never become an operator alert.

The older P1 48-hour research/classification allowance is not used as the operator reply policy. P7 owns the much stricter live-engagement freshness gate.

## Indonesia targeting semantics

P7 uses targeting evidence, not invented audience demographics.

For X, a high V1 targeting confidence means:

- discovery query required `lang:id`;
- P1 independently detected Indonesian text;
- observation came through the Indonesia-first query profile.

For YouTube, discovery uses:

- `regionCode=ID`;
- `relevanceLanguage=id`;
- Indonesian search terms.

These signals improve relevance. They **do not** reveal what percentage of an author's followers are Indonesian. `audience_percentage` is always null in V1 and percentage claims are forbidden without measured downstream data.

## Operator alert

Each eligible alert must contain:

- exact platform and source URL;
- source timestamp;
- age in minutes;
- expiry timestamp;
- source text for operator context;
- intent and score;
- score components;
- Indonesia target confidence + reasons;
- explanation of why the opportunity is timely;
- linked Bitmomo research id/title/match score;
- evidence finding and material limitation;
- `ready_to_post` reply copy;
- cooldown key;
- `manual_post_required=true`;
- `auto_send_permitted=false`.

An opportunity without a source URL, Indonesian language, fresh timestamp, research evidence, or ready reply fails closed.

## Priority

P7 starts with the canonical P1 score, then adds only operational ranking signals:

- freshness bonus (maximum for <=10m);
- bounded public-engagement bonus.

This priority does not change the underlying intent classification or research evidence.

## Notification

`Bitmomo_Operator_Alert_Engine_V1::dispatch()` accepts an injected notifier callable.

`research/operator-alert/bin/run-once.php` provides the first runtime adapter:

- X/YouTube credentials come from environment variables;
- research items come from a supplied canonical Research Feed JSON file;
- state/checkpoints are stored in a local JSON file with restrictive permissions;
- Telegram Bot `sendMessage` is supported for private operator notifications;
- if Telegram is not configured, the runner prints the safe operator brief to stdout for local validation.

No social write endpoint is present.

## Runtime environment

Required:

- `BITMOMO_RESEARCH_FEED_PATH`

Optional source credentials:

- `BITMOMO_X_BEARER_TOKEN`
- `BITMOMO_YOUTUBE_API_KEY`

Notification:

- `BITMOMO_TELEGRAM_BOT_TOKEN`
- `BITMOMO_TELEGRAM_CHAT_ID`

State:

- `BITMOMO_ALERT_STATE_PATH`

Secrets must never be committed to Git or placed in research/feed/state output.

## Scheduler

Recommended initial production cadence:

```cron
*/5 * * * * /usr/bin/php /path/to/bitmomo/research/operator-alert/bin/run-once.php
```

The five-minute cadence is chosen to make the <=10 minute ideal freshness window operationally reachable. The worker uses `since_id` and dedupe state so repeated polls do not intentionally re-alert the same Post.

Deployment/scheduler activation is a separate environment step. Source code alone does not imply that a cron job is already running.

## Safety

P7 performs no:

- automatic X replies;
- automatic YouTube comments;
- DMs;
- likes/follows;
- owned-content publication;
- fake engagement;
- credential persistence in repository files;
- WordPress/runtime modification.

The final social action remains manual by design.

# @bitmomodaily — Telegram Runtime Configuration

Status: PREPARED / DELIVERY OFF / AUTOPOST OFF

This document contains no credentials. It defines the approved runtime identity and the minimum configuration required to enable Bitmomo Telegram delivery later.

## Canonical identities

Public channel:

- handle: `@bitmomodaily`
- URL: `https://t.me/bitmomodaily`

Posting bot:

- handle: `@Bitmomo_id_bot`
- expected Telegram API username: `Bitmomo_id_bot`
- URL: `https://t.me/Bitmomo_id_bot`

The transport rejects a runtime token if Telegram `getMe` returns any other username.

## Telegram-side state

Operator confirmation on 2026-09-15:

- `@Bitmomo_id_bot` has been added as an administrator of `@bitmomodaily`.

This is not yet treated as machine-verified runtime evidence. Staging preflight must still confirm Telegram `getChatMember` returns `creator` or `administrator` with posting permission for the canonical bot.

## Runtime-only constants

Do not commit secret values to Git.

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', false );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_BOT_TOKEN', getenv( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ?: '' );
```

Two independent kill switches are intentional:

- `BITMOMO_TELEGRAM_DELIVERY_ENABLED` permits controlled transport/preflight and manual sending;
- `BITMOMO_TELEGRAM_AUTOPOST_ENABLED` permits automatic publishing after the existing canonical daily generation hook.

Both stay `false` in the repository and default runtime configuration.

The bot token belongs only in the approved Hostinger/runtime secret environment. It must not be stored in plugin files, theme files, GitHub issues, screenshots, chat messages, or WordPress options.

## Canonical preflight

`Bitmomo_Btc_Telegram_Transport::preflight()` is read-only: it sends no Telegram message.

A PASS requires all of the following:

1. delivery runtime flag is enabled;
2. bot token exists in runtime secret configuration;
3. Telegram `getMe` resolves exactly to `Bitmomo_id_bot`;
4. Telegram `getChatMember` confirms the canonical bot is `creator` or `administrator` in `@bitmomodaily`;
5. an administrator has `can_post_messages=true`;
6. the canonical public-safe BTC brief is currently available.

Any failure blocks send.

## Enablement sequence

Do not jump directly to autonomous posting.

### Stage A — preflight/manual transport

Runtime only:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
```

Then:

- run preflight;
- confirm identity + channel permission;
- generate the canonical brief;
- execute one controlled manual send;
- confirm the post lands in `@bitmomodaily` from `@Bitmomo_id_bot`;
- repeat the same send and confirm duplicate suppression returns `duplicate_brief` rather than posting again.

### Stage B — canonical daily autopost

Only after Stage A and regression gates pass:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', true );
```

Autopost does not create a second cron. `Bitmomo_Btc_Telegram_Publisher` subscribes at priority 20 to the existing `bitmomo_ai_daily_generation` hook, after the canonical AI scheduler callback.

Turning `BITMOMO_TELEGRAM_AUTOPOST_ENABLED` back to `false` immediately disables autonomous Telegram publication while leaving controlled preflight/manual transport available.

## Staging acceptance before autopost

All must pass:

- `Bitmomo_Btc_Telegram_Brief` contract test;
- Telegram transport contract test;
- Telegram publisher contract test;
- runtime `getMe` returns `Bitmomo_id_bot`;
- runtime `getChatMember` confirms channel posting access;
- canonical fresh public snapshot produces one brief;
- delayed/unavailable snapshot produces no publishable brief;
- no expected range, scenarios, invalidation, monitoring conditions, private reason codes, or raw kitchen metrics appear;
- one controlled test post lands in `@bitmomodaily` from `@Bitmomo_id_bot`;
- duplicate canonical message is suppressed;
- existing Whitelist V1 and BTC Intelligence gates remain green;
- no additional Telegram cron/scheduler exists.

## Important boundary

The bot is transport only.

It must never calculate, rewrite, summarize, enrich, or infer BTC intelligence. Message content is owned by `Bitmomo_Btc_Telegram_Brief`, which in turn consumes only the existing public-safe Bitmomo intelligence boundary.

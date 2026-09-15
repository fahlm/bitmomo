# @bitmomodaily — Telegram Runtime Configuration

Status: PREPARED / DELIVERY OFF

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

## Runtime-only constants

Do not commit values to Git.

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', false );
define( 'BITMOMO_TELEGRAM_BOT_TOKEN', getenv( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ?: '' );
```

`BITMOMO_TELEGRAM_DELIVERY_ENABLED` stays `false` until staging acceptance is complete.

The bot token belongs only in the approved Hostinger/runtime secret environment. It must not be stored in plugin files, theme files, GitHub issues, screenshots, chat messages, or WordPress options.

## Telegram-side setup

1. Confirm `@Bitmomo_id_bot` is controlled by Bitmomo.
2. Add `@Bitmomo_id_bot` as an administrator of `@bitmomodaily`.
3. Grant only the permission required to post messages; avoid unrelated admin permissions.
4. Keep the channel public so it can be used as the Telegram Ads destination.

## Staging acceptance before enablement

All must pass:

- `Bitmomo_Btc_Telegram_Brief` contract test;
- Telegram transport contract test;
- runtime `getMe` returns `Bitmomo_id_bot`;
- canonical fresh public snapshot produces one brief;
- delayed/unavailable snapshot produces no publishable brief;
- no expected range, scenarios, invalidation, monitoring conditions, private reason codes, or raw kitchen metrics appear;
- a controlled staging/manual test post lands in `@bitmomodaily` from `@Bitmomo_id_bot`;
- no duplicate send occurs;
- existing Whitelist V1 and BTC Intelligence gates remain green.

Only after these checks may `BITMOMO_TELEGRAM_DELIVERY_ENABLED` be changed to `true` in runtime configuration.

## Important boundary

The bot is transport only.

It must never calculate, rewrite, summarize, enrich, or infer BTC intelligence. Message content is owned by `Bitmomo_Btc_Telegram_Brief`, which in turn consumes only the existing public-safe Bitmomo intelligence boundary.

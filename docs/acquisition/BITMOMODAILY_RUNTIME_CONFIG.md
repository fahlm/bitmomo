# @bitmomodaily — Telegram Runtime Configuration

Status: RECONCILED / DELIVERY OFF / AUTOPOST OFF

This document contains no credentials. It defines the approved runtime identity, staging-canary boundary, and minimum configuration required for Bitmomo Telegram delivery.

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

Do not commit secret values to Git.

Default/off state:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', false );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', false );
define( 'BITMOMO_TELEGRAM_BOT_TOKEN', getenv( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ?: '' );
```

Kill switches are independent:

- `BITMOMO_TELEGRAM_DELIVERY_ENABLED` permits preflight/manual transport;
- `BITMOMO_TELEGRAM_AUTOPOST_ENABLED` permits automatic publishing after canonical daily generation;
- `BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED` exists only for canonical staging and permits a narrow exception through the staging outbound-write guard.

The token belongs only in the approved Hostinger/runtime secret environment. It must not be stored in plugin/theme files, GitHub issues, screenshots, chat, WordPress options, or release notes.

## Baseline staging state

Before Telegram-specific testing, canonical staging must pass `scripts/check-staging-safety.sh` with:

- delivery OFF;
- autopost OFF;
- staging canary OFF;
- global outbound HTTP write guard still blocking ordinary external POSTs.

Do not enable Telegram flags before exact artifact parity, staging safety, asset/cache checks, and ordinary release readiness pass.

## Stage A — controlled staging transport

Canonical staging only:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', true );
```

`BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED` is intentionally not a global side-effect bypass. The runtime transport may clear only Bitmomo's known staging outbound-write guard when all of these are true:

1. `BITMOMO_STAGING_SIDE_EFFECTS_DISABLED === true`;
2. delivery is explicitly enabled;
3. the Telegram staging-canary flag is explicitly enabled;
4. destination scheme is HTTPS;
5. destination host is exactly `api.telegram.org`;
6. request is exactly one of:
   - `GET getMe`;
   - `GET getChatMember`;
   - `POST sendMessage`;
7. the request path contains the exact configured runtime token.

Every other destination/method remains blocked by the staging guard.

## Canonical preflight

`Bitmomo_Btc_Telegram_Transport::preflight()` is read-only. PASS requires:

1. delivery enabled;
2. valid-format runtime bot token present;
3. Telegram `getMe` username exactly `Bitmomo_id_bot`;
4. `getChatMember` confirms `creator` or `administrator` in `@bitmomodaily`;
5. administrator has `can_post_messages=true` when applicable;
6. `Bitmomo_Btc_Telegram_Brief::current()` returns a fresh canonical public-safe brief.

Any failure blocks send.

## Controlled send

Only after preflight PASS:

```bash
wp eval 'var_export(Bitmomo_Btc_Telegram_Transport::send_current_brief());'
```

Acceptance:

- exactly one new post lands in `@bitmomodaily`;
- message comes from the canonical bot/channel configuration;
- content matches the canonical public-safe brief;
- no Expected Range, scenarios, invalidation, monitoring conditions, private reason codes, or raw derivative fields appear.

Repeat the same command immediately. The second call must return `duplicate_brief` and must not create another Telegram post.

After the controlled Stage A proof, return staging to:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', false );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', false );
```

Then rerun baseline staging safety. This ensures Telegram testing leaves no persistent side-effect exception active.

## Stage B — staging autopost

Stage B requires a separate approval after Stage A evidence and regressions are reviewed.

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', true );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', true );
```

Autopost creates no second cron. `Bitmomo_Btc_Telegram_Publisher` subscribes at priority 20 to the existing `bitmomo_ai_daily_generation` hook.

Prove exactly one canonical daily-generation cycle triggers at most one Telegram post; then turn the flags back OFF unless a separate staging-operational decision explicitly keeps Stage B active.

## Production boundary

Production must never use `BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED=true`.

A production release requires its own go/no-go. Staging acceptance does not authorize production delivery or autopost. Production token handling must be separately approved and must not be copied casually from staging.

## Required tests

Before any live Telegram request:

- `test-bitmomo-btc-telegram-brief.php`;
- `test-bitmomo-btc-telegram-transport-contract.php`;
- `test-bitmomo-btc-telegram-staging-canary.php`;
- `test-bitmomo-btc-telegram-publisher-contract.php`;
- `test-bitmomo-pro-founding149-acquisition-contract.php`;
- all existing BTC Intelligence, Pro, M2, authority/theme and release-safety regressions.

The bot is transport only. Intelligence ownership remains entirely with the canonical public-safe Bitmomo intelligence boundary.

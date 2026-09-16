# @bitmomodaily — Staging Deployment Handoff

Status: SOURCE RECONCILED / STAGING NOT YET DEPLOYED
Target: STAGING ONLY
Production: DO NOT DEPLOY / DO NOT ENABLE

This handoff starts only after the Telegram slice has been merged into the canonical release line and a new Full Release artifact has passed. Release engineering must not rebase, cherry-pick, rebuild, or resolve source conflicts.

## 1. Deployment input

Use only the immutable artifact named by the current release authority for `release/whitelist-v1-remediation`.

Before mutation record:

- exact source commit;
- exact source tree;
- Full Release run ID;
- artifact ID/name;
- runtime TAR SHA-256;
- uploaded ZIP digest;
- runtime file count.

The runtime manifest must include the Telegram/Founding classes:

- `wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-brief.php`
- `wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-transport.php`
- `wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-publisher.php`
- `wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-founding149-acquisition.php`

Do not deploy the historical `feature/founding149-telegram-acquisition-v1` branch directly.

## 2. Baseline staging deployment

Target only:

`https://seagreen-snail-158456.hostingersite.com`

1. Confirm target identity and take a verifiable staging rollback point.
2. Verify artifact/hash/provenance before extraction.
3. Deploy the exact artifact using the canonical Bitmomo staging procedure.
4. Run runtime parity: missing=0, changed=0, unexpected=0.
5. Purge staging cache only.
6. Run asset coherence.
7. Run `scripts/check-staging-safety.sh` with Telegram delivery/autopost/canary all OFF.
8. Run the ordinary staging readiness profile.
9. Confirm production is unchanged.

Stop on any failure. Do not hotfix the server.

## 3. Runtime class proof

After exact artifact parity passes:

```bash
wp eval 'echo class_exists("Bitmomo_Btc_Telegram_Brief") ? "BRIEF_OK\n" : "BRIEF_MISSING\n"; echo class_exists("Bitmomo_Btc_Telegram_Transport") ? "TRANSPORT_OK\n" : "TRANSPORT_MISSING\n"; echo class_exists("Bitmomo_Btc_Telegram_Publisher") ? "PUBLISHER_OK\n" : "PUBLISHER_MISSING\n"; echo class_exists("Bitmomo_Pro_Founding149_Acquisition") ? "ACQUISITION_OK\n" : "ACQUISITION_MISSING\n";'
```

Required:

```text
BRIEF_OK
TRANSPORT_OK
PUBLISHER_OK
ACQUISITION_OK
```

## 4. Regression tests

Actually execute and record:

- `test-bitmomo-btc-telegram-brief.php`
- `test-bitmomo-btc-telegram-transport-contract.php`
- `test-bitmomo-btc-telegram-staging-canary.php`
- `test-bitmomo-btc-telegram-publisher-contract.php`
- `test-bitmomo-pro-founding149-acquisition-contract.php`

Also retain the exact Full Release/authority/theme/BTC/Pro regression evidence for the deployed source SHA.

Do not mark PASS merely because test files exist.

## 5. Runtime secret

Only after baseline deployment, parity, safety and tests pass, configure the bot token through the approved Hostinger/runtime secret mechanism.

Never place the token in Git, WP options, screenshots, logs, release notes, issue comments, or chat.

Canonical identity:

- channel: `@bitmomodaily`
- bot: `@Bitmomo_id_bot`

## 6. Stage A — controlled canary only

Temporarily set on canonical staging:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_BOT_TOKEN', getenv( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ?: '' );
```

The staging-canary flag is deliberately narrow. It may clear only Bitmomo's known staging outbound-write guard for HTTPS `api.telegram.org` requests to:

- GET `getMe`;
- GET `getChatMember`;
- POST `sendMessage`.

It must not make any other external destination/method writable.

## 7. Read-only preflight

Run:

```bash
wp eval 'var_export(Bitmomo_Btc_Telegram_Transport::preflight());'
```

PASS requires:

- token format accepted;
- `getMe` username exactly `Bitmomo_id_bot`;
- `getChatMember` reports creator/administrator for `@bitmomodaily`;
- posting permission available;
- fresh canonical public-safe brief available.

Preflight itself sends no Telegram message.

## 8. Controlled send + duplicate proof

Only after preflight PASS:

```bash
wp eval 'var_export(Bitmomo_Btc_Telegram_Transport::send_current_brief());'
```

Verify exactly one post appears in `@bitmomodaily` and contains no protected Pro fields.

Immediately execute the same command again. Required result:

`duplicate_brief`

No second message may appear.

## 9. Return staging to safe state

After the canary evidence is captured, turn all three flags OFF:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', false );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_STAGING_CANARY_ENABLED', false );
```

Then rerun `scripts/check-staging-safety.sh`. It must PASS again with the normal global outbound-write block intact.

Stage A success does not authorize Stage B autopost.

## 10. Stage B boundary

Autopost remains OFF until separate approval. When later approved on staging, it must use the existing `bitmomo_ai_daily_generation` hook and prove one generation cycle produces at most one message. No second cron/scheduler is allowed.

## 11. Production boundary

This handoff never authorizes production Telegram delivery, token configuration, staging-canary mode, or autopost.

Production remains unchanged until a separate production go/no-go is explicitly approved.

## 12. Evidence required

Release report must contain, with token redacted:

- source/tree/run/artifact/hash identity;
- staging target proof and rollback point;
- runtime parity result;
- baseline staging-safety result;
- class proof;
- all Telegram/acquisition contract-test results;
- runtime flag state;
- preflight non-secret identity/permission result;
- first controlled-send result;
- duplicate-suppression result;
- visual confirmation in `@bitmomodaily`;
- final staging-safety result after flags are returned OFF;
- autopost = OFF;
- production = UNCHANGED;
- rollback used = YES/NO.

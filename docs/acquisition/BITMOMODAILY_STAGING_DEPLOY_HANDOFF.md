# @bitmomodaily — Staging Deployment Handoff

Status: READY FOR RELEASE ENGINEER / NOT YET DEPLOYED
Owner: Bitmomo
Target: STAGING ONLY
Production: DO NOT DEPLOY / DO NOT ENABLE

This document is the canonical release-engineer handoff for the Founding 149 Telegram acquisition slice. It exists so Telegram delivery cannot be omitted or partially configured during the next staging deployment.

## 1. Source of truth

Working branch:

`feature/founding149-telegram-acquisition-v1`

The branch was originally cut from Whitelist V1 SHA:

`dfafd654216c8459820362ec832652576a361f7f`

Before deployment, the release engineer must fetch the branch and record the exact resolved `SOURCE_SHA`. Do not deploy from a remembered/local stale SHA.

The acquisition branch must not be merged blindly into a newer release line. If the canonical release head has moved, rebase/recreate/cherry-pick the acquisition slice onto the current canonical integration base, resolve conflicts deliberately, rerun all gates, and record the resulting integration SHA.

Related source-of-truth documents:

- `docs/acquisition/FOUNDING_149_TELEGRAM_V1.md`
- `docs/acquisition/BITMOMODAILY_RUNTIME_CONFIG.md`
- `docs/acquisition/BITMOMODAILY_LAUNCH_PACK.md`
- GitHub Issue `#175` — `[P8] Founding 149 — Telegram Acquisition V1`

## 2. Expected code scope

The staging artifact must include the Telegram/acquisition slice and no unrelated changes.

Expected files from this slice include:

- `website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-brief.php`
- `website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-transport.php`
- `website/wp-content/plugins/bitmomo-btc-intelligence/includes/class-bitmomo-btc-telegram-publisher.php`
- `website/wp-content/plugins/bitmomo-btc-intelligence/tests/test-bitmomo-btc-telegram-brief.php`
- `website/wp-content/plugins/bitmomo-btc-intelligence/tests/test-bitmomo-btc-telegram-transport-contract.php`
- `website/wp-content/plugins/bitmomo-btc-intelligence/tests/test-bitmomo-btc-telegram-publisher-contract.php`
- BTC Intelligence plugin bootstrap changes required to load/register the Telegram classes
- `website/wp-content/plugins/bitmomo-pro/includes/class-bitmomo-pro-founding149-acquisition.php`
- Bitmomo Pro bootstrap changes required to load the Founding 149 acquisition scoreboard
- the four acquisition docs under `docs/acquisition/`

No Telegram-specific change may introduce a second cron/scheduler. The publisher must remain attached to the existing `bitmomo_ai_daily_generation` hook at priority 20.

## 3. Pre-deploy gates

Before changing staging:

1. Confirm target environment is STAGING, not `bitmomo.id` production.
2. Take/confirm a fresh staging backup according to the normal Bitmomo release process.
3. Record:
   - canonical base/integration SHA;
   - Telegram acquisition source SHA;
   - final staging artifact/integration SHA;
   - changed-file list.
4. Compare the integration artifact against its base and confirm `UNRELATED CHANGES: NO`.
5. Confirm checkout/payment state is unchanged unless covered by a separate approved release.
6. Confirm WhatsApp remains unchanged/off unless covered by a separate approved release.
7. Confirm Telegram runtime secret is not present in Git, artifact files, issues, logs, screenshots, or release notes.

If any of these cannot be established, stop before deploy.

## 4. Deploy to staging

Deploy the reviewed integration artifact to STAGING using the existing Bitmomo release process.

Immediately after deploy, verify the exact artifact/runtime contains:

- `Bitmomo_Btc_Telegram_Brief`
- `Bitmomo_Btc_Telegram_Transport`
- `Bitmomo_Btc_Telegram_Publisher`

Recommended runtime proof:

```bash
wp eval 'echo class_exists("Bitmomo_Btc_Telegram_Brief") ? "BRIEF_OK\n" : "BRIEF_MISSING\n"; echo class_exists("Bitmomo_Btc_Telegram_Transport") ? "TRANSPORT_OK\n" : "TRANSPORT_MISSING\n"; echo class_exists("Bitmomo_Btc_Telegram_Publisher") ? "PUBLISHER_OK\n" : "PUBLISHER_MISSING\n";'
```

Expected:

```text
BRIEF_OK
TRANSPORT_OK
PUBLISHER_OK
```

Do not configure or test the token until this artifact verification passes.

## 5. Contract/regression tests

Run and record actual results. Do not mark tests PASS merely because test files exist.

Required Telegram tests:

- `test-bitmomo-btc-telegram-brief.php`
- `test-bitmomo-btc-telegram-transport-contract.php`
- `test-bitmomo-btc-telegram-publisher-contract.php`

Also rerun the existing BTC Intelligence, M2 launch-surface, whitelist and release safety gates applicable to the integration artifact.

Acceptance:

- all Telegram contracts PASS;
- no Pro-only fields leak into the public Telegram brief;
- delayed/unavailable/malformed public data fails closed;
- existing BTC Intelligence/whitelist behavior does not regress;
- no second Telegram cron/scheduler exists.

## 6. Runtime secret/configuration — Stage A only

Canonical channel:

`@bitmomodaily`

Canonical bot:

`@Bitmomo_id_bot`

Operator has confirmed the bot was added as channel admin, but staging must still machine-verify this through Telegram API preflight.

The bot token must be stored only in the approved Hostinger/runtime secret environment. Never put the token in GitHub, this document, Issue #175, screenshots, chat, WordPress options, plugin files, or theme files.

Stage A runtime state:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', false );
define( 'BITMOMO_TELEGRAM_BOT_TOKEN', getenv( 'BITMOMO_TELEGRAM_BOT_TOKEN' ) ?: '' );
```

Important: `AUTOPOST` must remain `false` during staging acceptance.

## 7. Read-only preflight

Run:

```bash
wp eval 'var_export(Bitmomo_Btc_Telegram_Transport::preflight());'
```

PASS requires:

- runtime token present;
- Telegram `getMe` username exactly `Bitmomo_id_bot`;
- Telegram `getChatMember` reports the canonical bot as `creator` or `administrator` in `@bitmomodaily`;
- administrator has posting permission;
- canonical public-safe fresh BTC brief is available.

Preflight must send no message.

If any check fails, delivery remains blocked and `AUTOPOST` remains OFF.

## 8. Controlled manual send

Only after preflight PASS:

```bash
wp eval 'var_export(Bitmomo_Btc_Telegram_Transport::send_current_brief());'
```

Verify manually in `@bitmomodaily`:

- exactly one new message appears;
- content is the canonical BTC Decision Brief;
- no protected Pro range/scenario/invalidation/monitoring/private reason/raw derivatives fields appear;
- attributed Founding CTA is correct;
- message is associated with the approved bot/channel configuration.

Repeat the exact command once. The second attempt must return `duplicate_brief` and must not create a second Telegram post.

## 9. Stage B — autopost acceptance

Do not enable Stage B merely because the manual send worked.

Only after all Telegram and existing regression gates are green may staging use:

```php
define( 'BITMOMO_TELEGRAM_DELIVERY_ENABLED', true );
define( 'BITMOMO_TELEGRAM_AUTOPOST_ENABLED', true );
```

Then prove one canonical daily-generation cycle triggers the publisher after the existing engine callback and does not create duplicate posts.

The Telegram publisher must not own a cron. Scheduling remains owned by the existing Bitmomo AI scheduler.

## 10. Production boundary

This staging task does NOT authorize production Telegram delivery.

Production remains:

- Telegram deployment: NOT AUTHORIZED by this handoff;
- `BITMOMO_TELEGRAM_DELIVERY_ENABLED`: OFF unless a later production release explicitly approves it;
- `BITMOMO_TELEGRAM_AUTOPOST_ENABLED`: OFF unless a later production release explicitly approves it;
- bot token: never copied casually from staging; production secret handling requires its own approved runtime configuration.

A separate production go/no-go is required after staging evidence is reviewed.

## 11. Rollback / kill switch

If Telegram behavior is unexpected:

1. immediately set `BITMOMO_TELEGRAM_AUTOPOST_ENABLED=false`;
2. if transport itself must be stopped, set `BITMOMO_TELEGRAM_DELIVERY_ENABLED=false`;
3. do not delete intelligence data to stop Telegram;
4. record the failure status/evidence;
5. if the deployed artifact caused regression outside Telegram, use the normal staging artifact rollback process.

Turning both Telegram flags OFF must leave the core BTC engine and public BTC Intelligence product operational.

## 12. Required release-engineer evidence

The staging release report must include at minimum:

- `SOURCE_SHA` / integration SHA / deployed artifact SHA;
- target environment proof = STAGING;
- backup status;
- changed-file/parity result;
- Telegram classes runtime proof;
- Telegram contract test results;
- existing regression gate results;
- runtime flags with secret value REDACTED;
- preflight status and returned non-secret identity/permission fields;
- controlled-send result;
- duplicate-suppression result;
- `@bitmomodaily` visual confirmation;
- autopost status (expected OFF during Stage A);
- production status = UNCHANGED;
- rollback used = YES/NO.

Never include the bot token in the report.

## 13. Definition of done for this staging slice

Staging Telegram integration is accepted only when:

- exact reviewed artifact deployed;
- code parity established;
- all required tests actually run and pass;
- preflight machine-verifies canonical bot + channel posting rights;
- one canonical public-safe brief is delivered successfully;
- second identical send is suppressed;
- no existing product/release gate regresses;
- no second scheduler exists;
- Stage A evidence is recorded;
- production remains unchanged.

Only after this evidence is reviewed should Bitmomo decide whether to enable staging autopost and later prepare a separate production release.
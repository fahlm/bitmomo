# BitmomoDaily Telegram V1 — Reconciliation Notes

Canonical integration branch: `integration/telegram-founders-v1-final`
Base release SHA: `bda453cefd56d69ee48476b7bedb435b6ab68eea`
Historical source branch: `feature/founding149-telegram-acquisition-v1` (superseded for deployment)

This reconciliation deliberately ports the reviewed Telegram/Founding 149 behavior onto the current Home + Research Authority V3 release line instead of merging the historical branch wholesale.

Key reconciliation changes:

- preserves the current BTC accountability asset registration and all post-`dfafd654` release work;
- adds `Bitmomo_Btc_Telegram_Brief::current()` because the historical transport referenced it but the historical formatter did not implement it;
- keeps Telegram intelligence strictly on `Bitmomo_Public_Intelligence_Adapter::snapshot()` and the public allowlist;
- adds runtime-token format validation and duplicate message fingerprint suppression;
- adds a narrowly-scoped staging canary that can clear only Bitmomo's known outbound-write guard for canonical Telegram API calls while explicit staging/delivery/canary flags are active;
- keeps the normal staging global side-effect guard authoritative before and after the controlled Telegram canary;
- reuses the existing AI daily-generation hook and creates no second scheduler;
- reuses the existing Pro whitelist records for campaign attribution and creates no CRM/subscriber store;
- updates the production runtime contract from 119 to 123 managed files (BTC Intelligence 9→12, Pro 28→29);
- adds deterministic Telegram formatter, transport, canary, publisher and Founding149 acquisition contracts;
- folds Telegram validation into existing Authority Surface and Full Release gates rather than adding a new hosted CI workflow.

No Telegram secret is stored in the repository. Staging is not deployed by this reconciliation. Production remains unchanged and unauthorized for Telegram delivery.

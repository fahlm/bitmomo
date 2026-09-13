# Resilient Selector Feed

Status: research only. No wallet and no order-submission path.

## Why this exists

The first live shadow-selector session produced valid shadow feedback, then the Hyperliquid websocket stopped delivering fresh L2 data. The selector correctly failed closed (`IDLE`, `new_entries=NO`), but the live adapter did not automatically recreate the websocket session.

## V0.2 transport rules

- L2/book updates are the primary transport heartbeat.
- Trade inactivity alone is **not** treated as a websocket failure; it remains a market-quality input through rolling trade rate.
- If all watched book feeds remain stale for 60 seconds, the adapter reconnects the shared websocket and re-subscribes all watched markets.
- Every websocket session receives a monotonically increasing generation id.
- Callbacks from obsolete generations are ignored.
- Disconnect cleanup is bounded so an SDK cleanup hang cannot freeze recovery.
- Reconnect backoff starts at 2 seconds, doubles up to 30 seconds, and resets after the replacement generation receives a fresh book update.
- The supervisor remains fail-closed while a replacement generation has not yet received fresh L2 data.
- Existing rolling observer/execution samples are preserved across short reconnects, but stale book observations cannot qualify a market.

## Session-1 handling

The first shadow-probe JSONL is preserved locally as `data/hl_shadow_probe_session1.jsonl`. It is now **SEEN diagnostic data**, not an unseen holdout. Do not tune a policy on it and then claim validation from the same file.

## Next research run

Start a fresh session-2 JSONL after syncing the resilient selector code. Continue to treat the built-in shadow probe as a standardized execution comparator rather than validated trading alpha.

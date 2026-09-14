# Shadow Session 3 Runbook

Purpose: validate the dual-window selector after Session 2 exposed the single-window evidence flaw.

1. Sync branch `research/hyperliquid-referral-volume-v0` locally.
2. Run all selector tests.
3. Start a fresh output file `data/hl_shadow_probe_session3.jsonl`.
4. Run resilient dry-run on `VVV,PONS,ETHFI,PUMP,BTC` with 30-second reporting and 60-second transport watchdog.
5. Do not tune selector thresholds during Session 3.
6. Treat Session 1 and Session 2 as SEEN diagnostics only.

Expected evidence behavior:

- Spread/queue/trade-flow reflect the latest 15 minutes.
- `ExecN` persists up to 4 hours / latest 100 attempts instead of falling back toward zero every 15 minutes.
- A market with 30+ execution samples but no execution attempt for >30 minutes returns WATCH because evidence is stale.
- No qualified market means supervisor stays IDLE.

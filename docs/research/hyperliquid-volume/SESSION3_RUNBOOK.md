# Shadow Session 3 Runbook

Purpose: validate the dual-window selector after Session 2 exposed the single-window evidence flaw.

## Frozen Session 3 rules

1. Sync branch `research/hyperliquid-referral-volume-v0` locally.
2. Run all selector tests.
3. Start with fresh Session 3 execution evidence under `data/hl_shadow_probe_session3.jsonl`.
4. Run resilient dry-run on `VVV,PONS,ETHFI,PUMP,BTC` with 30-second reporting and 60-second transport watchdog.
5. Do not tune selector thresholds, shadow-probe policy, fee assumptions, market list, or qualification rules during Session 3.
6. Treat Session 1 and Session 2 as SEEN diagnostics only.
7. No qualified market is a valid result; supervisor must remain IDLE rather than weakening gates.

Expected evidence behavior:

- Spread/queue/trade-flow reflect the latest 15 minutes.
- `ExecN` persists up to 4 hours / latest 100 attempts instead of falling back toward zero every 15 minutes.
- A market with 30+ execution samples but no execution attempt for >30 minutes returns WATCH because evidence is stale.
- No qualified market means supervisor stays IDLE.

## Restart-safe Session 3 continuation

Do **not** simply restart `live_dry_run` with only the original `--shadow-output-jsonl`. A fresh process starts with empty in-memory observers, so `ExecN` would appear to reset even though the append-only JSONL still contains the previous attempts.

To continue the same Session 3 after a process stop, preload the previous finalized attempts through `--feedback-jsonl` and write new built-in probe events to a different continuation file. The selector already forbids using the same file for both arguments.

From `research/hyperliquid-volume`:

```bash
PYTHONPATH=. pytest -q \
  tests/test_market_selector.py \
  tests/test_execution_feedback.py \
  tests/test_shadow_probe.py \
  tests/test_transport.py \
  tests/test_dual_window.py

PYTHONPATH=. python -m market_selector.live_dry_run \
  --coins VVV,PONS,ETHFI,PUMP,BTC \
  --interval 30 \
  --transport-stale-seconds 60 \
  --feedback-jsonl data/hl_shadow_probe_session3.jsonl \
  --shadow-probe \
  --shadow-output-jsonl data/hl_shadow_probe_session3_cont1.jsonl \
  2>&1 | tee -a data/hl_shadow_probe_session3_runtime_cont1.log
```

This is a continuation of the frozen Session 3 policy, not a new tuning iteration. Historical Session 3 attempts are reloaded as execution evidence, subject to the existing 4-hour / latest-100 cap and 30-minute freshness rule. New probe attempts are routed directly into the same in-memory observers and are also persisted to the continuation JSONL.

If another restart is needed, first build a chronological resume seed from the completed Session 3 segments, for example:

```bash
cat \
  data/hl_shadow_probe_session3.jsonl \
  data/hl_shadow_probe_session3_cont1.jsonl \
  > data/hl_shadow_probe_session3_resume_seed.jsonl
```

Then use that combined file as `--feedback-jsonl` and write the next segment to a new output file such as `hl_shadow_probe_session3_cont2.jsonl`. Do not overwrite or truncate any prior segment.

## Freeze readiness

Session 3 should continue until each watched market can be classified without relaxing the frozen rules:

- structurally ineligible markets may remain REJECT without forcing additional execution samples;
- structurally eligible markets need at least `ExecN >= 30` before execution economics can be judged;
- execution evidence must be fresh (`<= 30m` old);
- fill, maker ratio, 5s markout, P10K, and T10K must remain fail-closed when unavailable;
- any candidate that reaches QUALIFIED must survive the supervisor's existing qualification hysteresis (`3` consecutive report windows) before activation is considered stable;
- reconnect count / feed-health behavior must be recorded with the final verdict.

At freeze time, record per market at least:

- structural verdict and failing gate(s),
- ExecN,
- fill rate,
- maker ratio,
- 5s markout,
- P10K,
- T10K,
- execution freshness,
- reconnect / feed-health context,
- final supervisor state.

If nothing passes all frozen gates, freeze the session as `NO QUALIFIED MARKET / IDLE` and only then begin a new research iteration.

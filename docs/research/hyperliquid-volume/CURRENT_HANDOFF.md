# Bitmomo Hyperliquid Referral-Volume Research — Current Handoff

Last updated: 2026-09-14

Canonical branch: `research/hyperliquid-referral-volume-v0`
Local research project: `~/bitmomo-hl-volume-bot`

## Objective

With approximately $100 USDC of capital, research a legitimate Hyperliquid trading/execution strategy that can accumulate the $10,000 trading-volume requirement for referral eligibility at the lowest practical expected cost, ideally with positive expectancy. No wash trading, self-trading, spoofing, or other artificial-volume behavior.

## Research conclusions so far

### Rejected directional hypotheses

1. HH/HL, LL/LH, BOS/retest structure strategies failed out-of-sample validation after fees.
2. A fade/opposite-direction variant also failed on a second untouched holdout.
3. Candle-level aggressive-flow/order-flow proxy strategies did not produce a meaningful fee-adjusted edge.

These ideas are not production trade triggers. Market structure may still be useful as context/regime information later.

### Hyperliquid microstructure finding

The strongest information signal found so far remains 3/3 microstructure alignment:

- order-book imbalance,
- microprice edge,
- aggressor trade flow.

This showed monotonic short-horizon directional information in prior BTC research, but execution/fill economics remain the dominant problem.

## Frozen VVV validation result

Frozen primary policy before holdout:

- Market: VVV
- Notional: $100
- Signal: score 3/3
- Maker entry: IMP1
- Maker exit: IMP1
- Exit lifetime: 30s

Unseen 8-hour holdout result:

- RT: 94
- Fill: 16.2%
- Maker ratio: 76.1%
- Volume/hour: ~$2,350
- T10K: 4.3h
- P10K: -$6.42
- Max DD: $12.07
- Verdict: FAIL

Control (`IMP1 -> JOIN`, 30s) also failed:

- RT: 90
- Maker: 68.3%
- T10K: 4.4h
- P10K: -$5.99
- DD: $10.79

## State-aware exit diagnostic

The failed VVV holdout was subsequently marked SEEN and used only for diagnostic research.

Best maker-compatible candidate:

- `state_hold2_weak1_flip3_180s`
- Sample: 577
- RT: 93
- Maker: 79.6%
- T10K: 4.3h
- P10K: -$5.40
- DD: $10.13

Best raw P10K/DD candidate:

- `state_hold1_weak0_flip3_180s`
- Sample: 548
- RT: 83
- Maker: 74.7%
- T10K: 4.8h
- P10K: -$5.03
- DD: $8.38

Conclusion: exit-policy tuning helped only slightly. The deeper problem is entry/fill adverse selection.

## Market selector architecture

Deterministic fail-closed stack:

`Market Observer -> Eligibility Engine -> Market Ranker -> Supervisor`

Design principles:

- market-agnostic, not VVV-hard-coded;
- no LLM in the trading loop;
- one eventual execution authority only;
- no qualified market => IDLE;
- active-market deterioration => stop new entries, drain/flatten, switch only when flat;
- hysteresis prevents rapid switching.

Frozen Session 3 qualification rules were:

- 24h volume >= $10M
- spread 1–12 bp
- BBO queue / $100 <= 20x
- trade rate >= 3/min
- empirical execution samples >= 30
- fill >= 5%
- maker ratio >= 75%
- 5s markout >= 0 bp
- T10K <= 6h
- P10K >= -$2

These remain research thresholds, not production approval rules.

## Dual-window selector

Session 2 exposed a structural evaluator flaw: market-state metrics and execution evidence both used the same 15-minute rolling window, causing `ExecN` to collapse even when the append-only JSONL had accumulated many attempts.

The fix separates the horizons:

### Market-state window

Rolling 15 minutes for:

- spread,
- queue/depth,
- trade rate,
- imbalance,
- microprice,
- aggressor flow.

### Execution-evidence window

Rolling 4 hours, capped to latest 100 attempts, for:

- execution sample count,
- fill rate,
- maker ratio,
- 5s markout,
- realized/proxy volume per hour,
- T10K,
- P10K.

Latest execution evidence must also be <=30 minutes old.

Session 3 validated that this architecture works: `ExecN` persisted beyond the 15-minute market window and reached useful sample sizes.

## Session 3 — FROZEN

Canonical verdict document:

`docs/research/hyperliquid-volume/SESSION3_VERDICT.md`

Status: **FROZEN — NO QUALIFIED MARKET / IDLE**.

Session 3 is now SEEN data and must not be reused as an unseen holdout for a modified policy.

Final observed checkpoint:

| Market | Verdict | Spread | Queue/$100 | Trades/min | ExecN | Fill | Maker | Markout5 | P10K | T10K |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| PONS | DEGRADED | 2.67 bp | 7.80x | 61.67 | 88 | 22.73% | 65.00% | -9.38 bp | -$10.09 | 4.26h |
| VVV | DEGRADED | 1.75 bp | 4.00x | 39.87 | 100 | 5.00% | 70.00% | -9.54 bp | -$8.19 | 10.86h |
| ETHFI | REJECT | 3.38 bp | 2.75x | 10.33 | 90 | 4.44% | 62.50% | -5.31 bp | -$5.05 | 21.30h |
| PUMP | REJECT | 2.75 bp | 33.36x | 56.27 | 100 | 11.00% | 54.55% | -2.64 bp | -$6.63 | 7.74h |
| BTC | REJECT | 0.13 bp | 5,288.56x | 210.93 | 100 | 10.00% | 55.00% | -0.74 bp | -$3.63 | 8.52h |

Final transport/supervisor checkpoint:

- feed healthy: YES
- websocket generation: 1
- reconnects: 0
- supervisor: IDLE
- active market: none
- new entries: NO

### Session 3 interpretation

PONS and VVV demonstrated that structural market access is not enough. Both failed the economics gates decisively:

- PONS maker 65%, markout -9.38 bp, P10K -$10.09;
- VVV maker 70%, markout -9.54 bp, P10K -$8.19.

This is strong evidence of a toxic-fill / adverse-selection problem under the standardized `JOIN + 3/3` shadow probe.

PUMP and BTC remained structurally unsuitable for the $100 target notional at the final checkpoint. ETHFI remained REJECT under the frozen evaluator and also had weak execution evidence.

The correct result is **NO QUALIFIED MARKET / IDLE**. Do not weaken thresholds to manufacture a candidate.

## Evidence-integrity / restart notes

Local Session 3 evidence was preserved across a restart:

- original JSONL: 625 rows;
- continuation segment 1: 19 rows;
- merged resume seed: 644 unique rows;
- duplicates removed: 0;
- selector tests before continuation: 32 passed;
- resume seed loaded successfully (`external_loaded=644`);
- additional fresh continuation events were accepted afterward.

Raw JSONL datasets remain local and should generally not be committed.

### T10K restart caveat

The current observer uses process `started_ms` when computing the elapsed-time denominator for `volume_per_hour` / T10K. Rehydrating historical execution events into a fresh process can therefore temporarily bias T10K.

Do not use resumed-session T10K as the sole basis for Session 3 conclusions. The frozen result is robust without it because PONS and VVV independently fail maker ratio, markout, and P10K.

This accounting issue must be fixed before a future validation depends on rehydrated throughput metrics.

## WebSocket/reliability status

The selector includes:

- L2/book heartbeat as primary transport-health signal;
- 60s all-book stale watchdog;
- automatic reconnect;
- generation fencing for obsolete callbacks;
- bounded disconnect cleanup;
- exponential backoff capped at 30s;
- fail-closed supervisor state during reconnect.

Recovery works, but earlier long sessions sometimes showed high reconnect frequency. Session 3's final continuation checkpoint itself was healthy with zero reconnects.

## Safety / mainnet status

Mainnet trading is NOT approved.

Current blockers:

1. No execution policy has passed fresh unseen economics/risk validation.
2. Adverse selection remains severe under the standardized passive-entry probe.
3. Restart-safe T10K/volume-per-hour accounting must be corrected.
4. A new candidate must pass a fresh unseen Session 4 after being frozen in advance.
5. Risk guardian, reconciliation, position/account state, and single execution authority are not yet integrated for live execution.
6. Shadow/paper soak testing must show no silent state divergence.
7. Reconnect behavior still needs broader reliability characterization.

## Next action — start a new policy research iteration, NOT Session 3 tuning

Do not collect more Session 3 data for policy selection. Session 3 is frozen and SEEN.

The next research question is now:

> Why does a directionally informative 3/3 microstructure signal produce strongly negative markout when entered passively, and can a legitimate maker-entry policy reduce this negative fill selection enough to meet the economics gates?

Recommended order:

1. Fix restart-safe throughput accounting first, with tests, without changing Session 3 results.
2. Use SEEN diagnostic/replay evidence to investigate entry adverse selection rather than further optimizing exit logic.
3. Pre-declare a small number of entry-policy variants. Candidate dimensions may include queue position / price improvement, shorter genuine order lifetime, signal persistence before quote placement, and cancel-on-signal-decay rules. Any real order design must represent genuine trading intent; no spoofing or artificial-volume behavior.
4. Select at most one candidate using SEEN research evidence.
5. Freeze that candidate and all gates before starting a fresh unseen Session 4.
6. If the candidate still cannot produce acceptable P10K/markout/drawdown, remain IDLE and reject the approach rather than loosening gates.

## Current files/modules

Selector code:

`research/hyperliquid-volume/market_selector/`

Important modules:

- `observer.py`
- `eligibility.py`
- `ranker.py`
- `supervisor.py`
- `feedback.py`
- `shadow_probe.py`
- `transport.py`
- `live_dry_run.py`

Important docs:

- `CURRENT_HANDOFF.md` — source of truth for the next chat/session
- `SESSION3_VERDICT.md` — frozen Session 3 result
- `SESSION3_RUNBOOK.md` — Session 3 operational/restart procedure
- `DUAL_WINDOW_SELECTOR_V1.md`
- `EXECUTION_FEEDBACK_CONTRACT.md`
- `MARKET_SELECTOR_ARCHITECTURE_V0.md`
- `RESILIENT_SELECTOR_FEED.md`
- `VVV_EXIT_POLICY_SEEN_DIAGNOSTIC.md`

## New-chat bootstrap

A new ChatGPT conversation must read this file first. The correct starting state is now **post-Session-3**: Session 3 is frozen `NO QUALIFIED MARKET / IDLE`; the next work is restart-safe throughput accounting and a new adverse-selection/entry-policy research iteration before any fresh Session 4 validation.

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

The strongest information signal found so far is 3/3 microstructure alignment:

- order-book imbalance,
- microprice edge,
- aggressor trade flow.

This showed monotonic short-horizon markout improvement in BTC research data, but execution/fill economics remained the dominant problem.

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

The strategy solved throughput, but not economics/risk.

Control policy (`IMP1 -> JOIN`, 30s) also failed:

- RT: 90
- Maker: 68.3%
- T10K: 4.4h
- P10K: -$5.99
- DD: $10.79

## State-aware exit diagnostic

The failed VVV holdout was subsequently marked SEEN and used only for diagnostic research.

Best maker-compatible state-aware candidate:

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

Conclusion: state-aware exit helped slightly but still failed the economics and drawdown gates. These are research candidates only, not validated policies.

## Market selector architecture

The research branch now contains a deterministic, fail-closed market-selection stack:

`Market Observer -> Eligibility Engine -> Market Ranker -> Supervisor`

Production design principle:

- market-agnostic, not VVV-hard-coded;
- no LLM in the trading loop;
- one eventual execution authority only;
- no qualified market => IDLE;
- active market deterioration => stop new entries, drain/flatten, then switch only when flat;
- hysteresis prevents rapid market switching.

Current provisional eligibility gates include:

- 24h volume >= $10M
- spread roughly 1–12 bp
- BBO queue / $100 <= 20x
- trade rate >= 3/min
- empirical execution samples >= 30
- fill >= 5%
- maker ratio >= 75%
- 5s markout >= 0 bp
- T10K <= 6h
- P10K >= -$2

These thresholds are research hypotheses, not production-frozen rules.

## WebSocket/reliability status

The live selector has resilient feed recovery with:

- L2/book heartbeat as primary transport-health signal,
- 60s all-book stale watchdog,
- automatic reconnect,
- generation fencing so callbacks from old websocket generations are ignored,
- bounded disconnect cleanup,
- exponential backoff capped at 30s,
- backoff reset after healthy feed recovery,
- fail-closed supervisor state during reconnect.

This recovery path has been observed working repeatedly in live dry-run sessions.

Important: reconnect frequency has sometimes been high (e.g. >20 reconnects in a long session). Recovery works, but SDK/feed transport noise may still need later hardening.

## Shadow sessions

### Session 1

File: `data/hl_shadow_probe_session1.jsonl` on the local research machine.

Status: SEEN diagnostic.

The first live selector run showed the original selector could accumulate shadow events but lost current leaderboard evidence after the websocket stalled. This led to the resilient reconnect work.

### Session 2

File: `data/hl_shadow_probe_session2.jsonl` if preserved locally.

Status: SEEN diagnostic.

Key architecture flaw discovered:

- market-state data and execution evidence both used the same 15-minute rolling window;
- cumulative accepted events could exceed 1,000 while per-market `ExecN` repeatedly fell back to single digits;
- therefore the `ExecN >= 30` qualification gate was structurally difficult to satisfy.

This was an evaluator/windowing flaw, not evidence that all historical execution samples disappeared from the append-only JSONL.

## Dual-window fix

The selector was changed so market state and execution economics use different horizons.

### Market-state window

Rolling 15 minutes for:

- spread,
- queue/depth,
- trade rate,
- imbalance,
- microprice,
- aggressor flow.

### Execution-evidence window

Rolling 4 hours, capped to the latest 100 attempts, for:

- execution sample count,
- fill rate,
- maker ratio,
- 5s markout,
- realized/proxy volume per hour,
- T10K,
- P10K.

Execution evidence must also remain fresh: latest execution attempt <= 30 minutes old.

The purpose is to retain enough execution evidence for statistical usefulness while ensuring current market-state metrics remain responsive to regime changes.

## Session 3

Status: fresh validation session for the dual-window selector. Thresholds/rules should NOT be tuned using Session 3 before its verdict is frozen.

Session 3 has already demonstrated that the dual-window fix works: `ExecN` can exceed 30 without resetting simply because the 15-minute market-state window rolls forward.

Latest observed snapshot from the conversation:

| Market | Verdict | Spread | Queue/$100 | Trades/min | ExecN | Fill | Maker | Markout5 | P10K | T10K |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| PONS | WATCH | ~2.33 bp | ~11.25x | ~65.4 | 11 | 45.45% | 80% | ~-10.51 bp | ~-$9.50 | ~4.98h |
| VVV | WATCH | ~2.59 bp | ~5.90x | ~14.9 | 12 | 8.33% | 50% | ~-10.36 bp | ~-$0.63 | ~24.9h |
| ETHFI | REJECT | ~2.29 bp | ~4.30x | ~6.5 | 54 | 1.85% | 50% | ~+0.08 bp | ~-$12.93 | ~24.6h |
| BTC | REJECT | ~0.13 bp | ~7,430x | ~128.7 | 40 | 10.0% | 62.5% | ~-1.13 bp | ~-$3.33 | ~5.9h |
| PUMP | REJECT | ~2.76 bp | ~34.5x | ~10.0 | 1 | 0% | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN |

Interpretation at this checkpoint:

- No market is qualified yet.
- PONS had good fill/maker/throughput characteristics in this snapshot but strongly negative markout and P10K.
- VVV had acceptable structural access but weak maker ratio and very slow T10K.
- ETHFI accumulated enough execution samples but failed fill/maker/economics gates.
- BTC remains structurally unsuitable for $100 capital because spread is tiny and BBO queue is extremely deep.
- The correct supervisor action remains IDLE until all gates are satisfied.

Do not over-interpret individual Session 3 snapshots; continue to use the frozen dual-window logic and evaluate the session as a whole.

## Current files/modules

Research selector modules live under:

`research/hyperliquid-volume/market_selector/`

Important modules include:

- `observer.py`
- `eligibility.py`
- `ranker.py`
- `supervisor.py`
- `feedback.py`
- `shadow_probe.py`
- `transport.py`
- `live_dry_run.py`

Relevant documentation lives under:

`docs/research/hyperliquid-volume/`

Raw large datasets / local JSONL research captures should generally not be committed to GitHub; the repository should contain methodology, rules, results, manifests, and canonical code.

## Safety / mainnet status

Mainnet trading is NOT approved.

Current blockers before any live-wallet execution:

1. Freeze a selector/execution policy that passes fresh unseen validation.
2. Demonstrate acceptable P10K and drawdown under independent holdout conditions.
3. Confirm market-selector switching/IDLE behavior across changing market regimes.
4. Reduce or fully understand abnormal reconnect frequency.
5. Integrate risk guardian, reconciliation, position/account state, and single execution authority.
6. Run shadow/paper soak testing with no silent state divergence.

## Next action

Continue/inspect Session 3 without changing qualification thresholds mid-session.

When enough fresh evidence exists, freeze the Session 3 verdict per market using at least:

- structural eligibility,
- ExecN,
- fill rate,
- maker ratio,
- markout,
- P10K,
- T10K,
- reconnect/data-health context.

If no market passes all gates, record `NO QUALIFIED MARKET / IDLE` as a valid result rather than weakening rules to force a candidate.

Only after the Session 3 verdict is frozen should a new research iteration modify execution policy or eligibility logic, followed by a fresh unseen session.

## New-chat bootstrap

A new ChatGPT conversation should start by reading this file and the canonical research branch, then continue from Session 3 instead of restarting research from scratch.

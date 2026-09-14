# Bitmomo Hyperliquid Referral-Volume Research — Current Handoff

Last updated: 2026-09-14

Canonical branch: `research/hyperliquid-referral-volume-v0`
Local research project: `~/bitmomo-hl-volume-bot`

## Objective

With approximately $100 USDC of capital, research a legitimate Hyperliquid trading/execution strategy that can accumulate the $10,000 trading-volume requirement for referral eligibility at the lowest practical expected cost, ideally with positive expectancy. No wash trading, self-trading, spoofing, or artificial-volume behavior.

## High-level conclusions

Rejected directional hypotheses:

1. HH/HL, LL/LH, BOS/retest failed out-of-sample after fees.
2. A fade/opposite-direction variant failed on a second untouched holdout.
3. Candle-level aggressive-flow/order-flow proxy did not produce meaningful fee-adjusted edge.

Strongest informational feature remains 3/3 microstructure alignment:

- order-book imbalance;
- microprice edge;
- aggressor trade flow.

The unresolved problem is execution economics: passive JOIN fills are negatively selected.

## Frozen VVV unseen holdout

Previously frozen policy:

- VVV
- $100 notional
- score 3/3
- IMP1 maker entry
- IMP1 maker exit
- 30s exit lifetime

Unseen 8h result:

- RT 94
- fill 16.2%
- maker 76.1%
- T10K 4.3h
- P10K -$6.42
- max DD $12.07
- FAIL

Control `IMP1 -> JOIN` also failed: maker 68.3%, T10K 4.4h, P10K -$5.99, DD $10.79.

State-aware exit research on the now-SEEN VVV holdout improved economics only slightly and did not solve the problem. Conclusion: the deeper issue is adverse selection at entry/fill, not exit logic alone.

## Selector architecture

Deterministic fail-closed stack:

`Market Observer -> Eligibility Engine -> Market Ranker -> Supervisor`

Principles:

- market-agnostic;
- no LLM in trading loop;
- one eventual execution authority only;
- no qualified market => IDLE;
- stop new entries on degradation, drain/flatten, switch only when flat;
- hysteresis prevents rapid switching.

Frozen Session 3 research gates:

- 24h volume >= $10M
- spread 1–12 bp
- BBO queue / $100 <= 20x
- trade rate >= 3/min
- execution samples >= 30
- fill >= 5%
- maker >= 75%
- 5s markout >= 0 bp
- T10K <= 6h
- P10K >= -$2

## Dual-window selector

Session 2 exposed that market-state and execution evidence incorrectly shared one 15m window.

Current architecture:

- market state: rolling 15m;
- execution evidence: rolling 4h, latest 100 attempts;
- execution freshness: <=30m.

Session 3 validated that the architecture retains execution evidence correctly while market state remains responsive.

## Session 3 — FROZEN

Canonical result:

`docs/research/hyperliquid-volume/SESSION3_VERDICT.md`

Status: **FROZEN — NO QUALIFIED MARKET / IDLE**.

Final checkpoint:

| Market | Verdict | Spread | Queue/$100 | Trades/min | ExecN | Fill | Maker | Markout5 | P10K | T10K |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| PONS | DEGRADED | 2.67 bp | 7.80x | 61.67 | 88 | 22.73% | 65.00% | -9.38 bp | -$10.09 | 4.26h |
| VVV | DEGRADED | 1.75 bp | 4.00x | 39.87 | 100 | 5.00% | 70.00% | -9.54 bp | -$8.19 | 10.86h |
| ETHFI | REJECT | 3.38 bp | 2.75x | 10.33 | 90 | 4.44% | 62.50% | -5.31 bp | -$5.05 | 21.30h |
| PUMP | REJECT | 2.75 bp | 33.36x | 56.27 | 100 | 11.00% | 54.55% | -2.64 bp | -$6.63 | 7.74h |
| BTC | REJECT | 0.13 bp | 5,288.56x | 210.93 | 100 | 10.00% | 55.00% | -0.74 bp | -$3.63 | 8.52h |

Final supervisor state: IDLE, no active market, new entries disabled. Session 3 is now SEEN data.

## Evidence integrity / restart accounting

Session 3 local evidence was preserved across restart:

- original JSONL 625 rows;
- continuation 19 rows;
- merged resume seed 644 unique rows;
- duplicates removed 0;
- seed loaded successfully and fresh continuation events accepted.

A restart-accounting bug was then fixed: historical execution evidence rehydrated after restart no longer clips throughput elapsed-time to the fresh process start.

Local verification after the fix: **34 tests passed**, including two dedicated regression tests for rehydrated and uninterrupted T10K/volume-per-hour behavior.

Therefore restart-safe throughput accounting is now considered fixed for this research branch.

## Entry-policy development plan

Canonical predeclared search space:

`docs/research/hyperliquid-volume/ENTRY_POLICY_DEV_PLAN.md`

Only SEEN data may be used for development. Session 4 must remain unseen until one candidate is selected and frozen.

Fixed components during this development cycle:

- selector thresholds;
- fee assumptions;
- $100 target notional;
- queue-ahead fill semantics;
- exit policy / exit lifetime;
- no cancellation credit;
- no wallet/order submission.

Predeclared entry families:

- C0: immediate 3/3 JOIN control;
- P1: 3/3 persistence confirmation (0.5s / 1.0s / 2.0s);
- P2: normalized microprice-strength filter (0.10 / 0.20 / 0.30);
- P3: aggressor-flow strength filter (0.10 / 0.20 / 0.30);
- P4: same-side queue accessibility guard (2x / 5x / 10x).

Arbitrary brute-force combinations are prohibited. A two-filter combination is allowed only if at least two independent families first show consistent improvement across SEEN datasets.

## Development Cycle 1 — FROZEN

Canonical result:

`docs/research/hyperliquid-volume/ENTRY_POLICY_DEV1_RESULT.md`

Status: **NO SESSION-4 CANDIDATE YET**.

### VVV 8h replay

C0 control:

- attempts 400
- fill 7.2%
- maker 62.1%
- mean markout -7.01 bp
- P10K -$7.68
- T10K 13.82h
- max DD $4.46

Best persistence result was P1_1.0s:

- attempts 389
- fill 6.9%
- maker 64.8%
- mean markout -2.80 bp
- P10K -$4.93
- T10K 14.84h
- max DD $2.84
- dMark +4.21 bp
- dP10K +$2.75

Interpretation: persistence confirmation materially reduced adverse selection on VVV without simply suppressing nearly all trading, but economics still failed.

### Multi-market 30m sanity check

P1 did not generalize consistently:

- P1_0.5s: valid 2, +Mark 0, +P10K 2, Both+ 0, median dMark -1.13 bp, median dP10K +$1.52.
- P1_1.0s: valid 3, +Mark 0, +P10K 1, Both+ 0, median dMark -0.65 bp, median dP10K $0.00.
- P1_2.0s: valid 3, +Mark 1, +P10K 0, Both+ 0, median dMark 0.00 bp, median dP10K -$0.99.

Other families:

- P2 showed some positive short-sample cross-market markout but harmed the longer VVV replay, so it is inconsistent.
- P3 is mixed.
- P4_5x / P4_10x produced modest positive direction on both VVV 8h and the short cross-market sanity check. Queue accessibility is therefore the most directionally consistent family so far, but the multi-market fill counts are too small to freeze a candidate.

Frozen conclusion: no policy is ready for Session 4; the predeclared combination rule is not activated.

## Raw development data available locally

Useful SEEN replay datasets include:

- `data/vvv_holdout_8h_books.csv`
- `data/vvv_holdout_8h_trades.csv`
- `data/hl_multi_30m_books.csv`
- `data/hl_multi_30m_trades.csv`
- `data/hl_microstructure_120m.csv`
- shorter execution/reconnect/watchdog captures.

VVV 8h is ~480 minutes of one-market raw L2/trades. Multi-market 30m contains BTC, ETHFI, PONS, PUMP, VVV. Raw books include top-5 levels and raw trades include exchange timestamps, side, price, size, trade ID/hash.

## Current next action

**Do not start Session 4 yet.**

Collect a new **SEEN multi-market development capture** using the same raw event schema, then replay the already-predeclared C0/P1/P2/P3/P4 grid unchanged.

Recommended capture:

- markets: BTC, ETHFI, PONS, PUMP, VVV;
- duration: at least 4 hours; 6–8 hours preferred if transport remains healthy;
- raw L2 top-5 books + individual trades;
- resilient reconnect and health log;
- label the capture DEVELOPMENT / SEEN before it starts;
- no policy tuning during capture.

Why: the existing 30m cross-market sample has too few fills to distinguish a robust policy from noise. A longer SEEN multi-market capture is needed before choosing/freeze one entry policy.

After that capture:

1. replay the unchanged predeclared grid;
2. evaluate P10K, mean/median markout, maker, fill, T10K, max DD, sample size and consistency by market;
3. select at most one candidate only if the improvement is robust;
4. freeze that candidate and all gates;
5. only then begin fresh unseen Session 4.

If no candidate becomes convincing, record the development cycle as failed and remain IDLE rather than loosening gates.

## Safety / mainnet status

Mainnet trading is NOT approved.

Still required before any live wallet execution:

1. one frozen execution policy must pass fresh unseen Session 4 economics/risk validation;
2. acceptable P10K and drawdown;
3. reliable market-selection/IDLE behavior across regimes;
4. transport/reconnect reliability characterized;
5. risk guardian, reconciliation, account/position state and one execution authority;
6. shadow/paper soak with no silent divergence.

## Important docs

- `CURRENT_HANDOFF.md` — source of truth
- `SESSION3_VERDICT.md` — frozen Session 3
- `ENTRY_POLICY_DEV_PLAN.md` — predeclared entry search space
- `ENTRY_POLICY_DEV1_RESULT.md` — frozen Development Cycle 1 result
- `SESSION3_RUNBOOK.md`
- `DUAL_WINDOW_SELECTOR_V1.md`
- `EXECUTION_FEEDBACK_CONTRACT.md`
- `MARKET_SELECTOR_ARCHITECTURE_V0.md`
- `RESILIENT_SELECTOR_FEED.md`
- `VVV_EXIT_POLICY_SEEN_DIAGNOSTIC.md`

## New-chat bootstrap

A new chat must read this file first. Correct starting state: Session 3 is frozen `NO QUALIFIED MARKET / IDLE`; restart-safe throughput accounting is fixed and locally verified with 34 passing tests; Entry Policy Development Cycle 1 is frozen with **NO SESSION-4 CANDIDATE YET**; next work is a longer SEEN multi-market development capture, not Session 4.

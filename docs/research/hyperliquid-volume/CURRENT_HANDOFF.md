# Bitmomo Hyperliquid Referral-Volume Research — Current Handoff

Last updated: 2026-09-15

Canonical branch: `research/hyperliquid-referral-volume-v0`
Local research project: `~/bitmomo-hl-volume-bot`

## Objective

With approximately $100 USDC, research a legitimate Hyperliquid execution/trading approach that can reach the $10,000 genuine-volume requirement at the lowest practical expected cost, ideally with positive expectancy. No wash trading, self-trading, spoofing, layering, fake liquidity, or artificial-volume behavior.

## Stable conclusions

- HH/HL/BOS/retest directional strategies failed OOS after fees.
- Fade/opposite-direction variant failed a second untouched holdout.
- Candle-level order-flow proxy did not produce meaningful fee-adjusted edge.
- Strongest information feature remains 3/3 microstructure alignment: book imbalance + microprice edge + aggressor trade flow.
- The dominant unresolved problem is execution economics / adverse selection of passive JOIN fills.

## Frozen VVV holdout

Earlier frozen unseen 8h VVV policy (IMP1 entry/exit, $100, 3/3 signal) failed:

- RT 94
- fill 16.2%
- maker 76.1%
- T10K 4.3h
- P10K -$6.42
- max DD $12.07

State-aware exit diagnostics on the now-SEEN data improved only slightly. Exit tuning alone did not solve the problem.

## Selector architecture

Deterministic fail-closed stack:

`Market Observer -> Eligibility Engine -> Market Ranker -> Supervisor`

Frozen Session 3 research gates included:

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

No qualified market => IDLE.

## Dual-window + reliability status

Current selector uses:

- market state: rolling 15m;
- execution evidence: rolling 4h / latest 100 attempts;
- execution freshness <=30m;
- resilient websocket reconnect with stale-book watchdog and generation fencing.

Restart-safe throughput/T10K accounting was fixed and verified locally: **34 tests passed**, including dedicated rehydration regression tests.

## Session 3 — FROZEN

Canonical result: `SESSION3_VERDICT.md`

Status: **NO QUALIFIED MARKET / IDLE**. Session 3 is now SEEN.

Final key economics:

- PONS: maker 65%, markout -9.38 bp, P10K -$10.09
- VVV: maker 70%, markout -9.54 bp, P10K -$8.19

Conclusion: structurally accessible markets still produced toxic passive fills under the standardized JOIN+3/3 probe.

## Entry Policy Development Cycle 1 — FROZEN

Canonical files:

- `ENTRY_POLICY_DEV_PLAN.md`
- `ENTRY_POLICY_DEV1_RESULT.md`

Predeclared static entry families:

- C0 immediate 3/3 JOIN
- P1 persistence 0.5s/1s/2s
- P2 normalized microprice-strength filter
- P3 aggressor-flow strength filter
- P4 queue-accessibility guard 2x/5x/10x

VVV 8h made P1_1.0s look promising, but it did not generalize in the 30m cross-market sanity check. Result: **NO SESSION-4 CANDIDATE**.

## Development Capture 2 — ACCEPTED SEEN DATA

Capture was labeled DEVELOPMENT/SEEN before collection.

Markets: BTC, ETHFI, PONS, PUMP, VVV.

Duration: ~6.02h books per market.

Raw counts:

- BTC: 39,749 books / 90,796 trades
- ETHFI: 39,749 / 7,284
- PONS: 39,750 / 43,536
- PUMP: 39,749 / 22,465
- VVV: 39,622 / 14,387

Recovery behavior:

- BTC/ETHFI/PONS/PUMP: 2 reconnects each
- VVV: 3 reconnects
- stale-book watchdog fired around 60–62s and recovery succeeded
- all recorders reached FINISH
- no reviewed fatal traceback

Capture is valid for SEEN development replay, not unseen validation.

## Entry Policy Development Cycle 2 — FROZEN

Canonical result: `ENTRY_POLICY_DEV2_RESULT.md`

Status: **NO SESSION-4 CANDIDATE**.

P4 was tested on the 6h x 5-market development capture.

Cross-market aggregate:

| Policy | Valid | +Mark | +P10K | Both+ | Median dMark | Median dP10K | Fills |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| P4_2x | 5 | 2 | 3 | 2 | -0.29 bp | +$0.04 | 318 |
| P4_5x | 5 | 2 | 2 | 2 | 0.00 bp | $0.00 | 343 |
| P4_10x | 5 | 1 | 1 | 1 | -0.19 bp | -$0.21 | 344 |

Market pattern:

- BTC: all P4 variants worsened markout/P10K.
- ETHFI: P4_2x looked better but had only 3 fills.
- PONS: P4_5x modestly improved markout/P10K but economics remained poor.
- PUMP: P4 materially improved markout/P10K, but still failed economics and did not generalize.
- VVV: all P4 variants worsened markout/P10K.

Conclusion: queue accessibility strongly changes fill probability but is not a universal adverse-selection solution. Do not freeze P4.

## Current research cycle — ORDER LIFECYCLE DEV3

Canonical predeclared plan: `ORDER_LIFECYCLE_DEV3_PLAN.md`

Session 4 remains untouched/unseen.

Primary hypothesis:

> A passive JOIN quote becomes toxic when it remains live after the original 3/3 state weakens or flips. Shorter genuine quote exposure and cancel-on-signal-decay may improve post-fill markout more robustly than static entry filters.

Predeclared variants:

- L0: current 10s entry lifetime, no decay cancellation
- L1_2s
- L1_5s
- L2_STRICT: cancel unfilled quote when current 3/3 direction is no longer the original direction, including neutral/partial decay
- L2_FLIP: cancel only on full opposite 3/3 alignment
- L3_2s_STRICT
- L3_5s_STRICT

Everything else remains fixed: $100 notional, JOIN entry price, queue-ahead accounting, fees, exit policy/lifetime, cooldown, selector gates.

Causality rule: cancellation can use only information available at or before the current replay event. No retroactive cancellation and no cancellation after fill.

Candidate threshold for consideration on Development Capture 2:

- markout improves in >=4/5 markets
- P10K improves in >=4/5 markets
- both improve in >=3/5 markets
- median dMark >0
- median dP10K >0
- non-trivial aggregate fill sample
- improvement cannot come merely from suppressing almost all fills

If no lifecycle candidate meets this standard, freeze Dev3 as failed and do not start Session 4.

## Current next action

Implement a deterministic lifecycle replay harness reusing the canonical `ShadowProbeEngine` fill/exit semantics, then replay L0/L1/L2/L3 on the already-SEEN datasets, primarily Development Capture 2.

Do **not** collect Session 4 yet and do not modify the predeclared lifecycle grid after seeing results.

## Mainnet status

Mainnet trading is NOT approved.

Still required before live wallet execution:

1. one policy frozen in advance;
2. fresh unseen Session 4 pass on economics/risk;
3. acceptable P10K/markout/drawdown;
4. reliable selector/IDLE behavior;
5. transport reliability characterized;
6. risk guardian + reconciliation + account/position state + one execution authority;
7. shadow/paper soak with no silent divergence.

## Important docs

- `CURRENT_HANDOFF.md` — source of truth
- `SESSION3_VERDICT.md`
- `ENTRY_POLICY_DEV_PLAN.md`
- `ENTRY_POLICY_DEV1_RESULT.md`
- `ENTRY_POLICY_DEV2_RESULT.md`
- `ORDER_LIFECYCLE_DEV3_PLAN.md`
- `DEV2_CAPTURE_PLAN.md`
- `SESSION3_RUNBOOK.md`
- `DUAL_WINDOW_SELECTOR_V1.md`
- `EXECUTION_FEEDBACK_CONTRACT.md`
- `MARKET_SELECTOR_ARCHITECTURE_V0.md`
- `RESILIENT_SELECTOR_FEED.md`

## New-chat bootstrap

A new chat must read this file first. Correct starting state: Session 3 frozen NO QUALIFIED MARKET / IDLE; restart-safe T10K fixed/34 tests; static entry Development Cycles 1 and 2 both failed to produce a robust Session-4 candidate; Development Capture 2 is accepted SEEN data; current work is predeclared Order Lifecycle Development Cycle 3, not Session 4.

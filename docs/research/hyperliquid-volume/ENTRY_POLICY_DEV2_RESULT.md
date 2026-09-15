# Hyperliquid Entry Policy Development Cycle 2 — Result

Date: 2026-09-15

Status: **FROZEN DEVELOPMENT RESULT — NO SESSION-4 CANDIDATE**

Session 4 remains untouched/unseen.

## Development capture

Development Capture 2 was explicitly labeled SEEN before collection.

Markets:

- BTC
- ETHFI
- PONS
- PUMP
- VVV

Duration: approximately 6.02 hours of book data per market.

Observed raw capture sizes:

- BTC: 39,749 books / 90,796 trades
- ETHFI: 39,749 books / 7,284 trades
- PONS: 39,750 books / 43,536 trades
- PUMP: 39,749 books / 22,465 trades
- VVV: 39,622 books / 14,387 trades

Recorder recovery was exercised during the capture. BTC/ETHFI/PONS/PUMP each recorded 2 reconnects; VVV recorded 3 reconnects. Reconnects followed ~60s stale-book detection and recovered through the resilient recorder. Every recorder reached FINISH. No fatal traceback was observed in the reviewed logs.

## Replay methodology

The predeclared C0/P1/P2/P3/P4 grid from `ENTRY_POLICY_DEV_PLAN.md` was replayed unchanged. Session 4 thresholds were not tuned.

This cycle was intended to decide whether the previously interesting queue-accessibility family P4 generalized on a substantially longer multi-market dataset.

## P4 cross-market aggregate

| Policy | Valid markets | +Markout | +P10K | Both improved | Median dMark | Median dP10K | Fills | Attempts |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| P4_2x | 5 | 2 | 3 | 2 | -0.29 bp | +$0.04 | 318 | 811 |
| P4_5x | 5 | 2 | 2 | 2 | 0.00 bp | $0.00 | 343 | 891 |
| P4_10x | 5 | 1 | 1 | 1 | -0.19 bp | -$0.21 | 344 | 920 |

The family does not show a robust positive cross-market median effect.

## Market details

### BTC

Control C0:

- attempts 315
- fills 58
- fill 18.41%
- maker 68.10%
- mean markout -0.84 bp
- P10K -$3.13
- T10K 5.19h

P4 filters increased fill probability dramatically but worsened economics:

- P4_2x: markout -1.13 bp, P10K -$3.54
- P4_5x: markout -1.15 bp, P10K -$3.45
- P4_10x: markout -1.04 bp, P10K -$3.51

Conclusion: queue accessibility is not a useful universal toxicity filter for BTC in this capture.

### ETHFI

Control C0:

- attempts 37
- fills 4
- maker 75.00%
- mean markout -4.05 bp
- P10K -$5.52
- T10K 75.81h

P4_2x looked strongly better (markout +3.13 bp, P10K -$1.86), but only 3 fills were observed. P4_5x and P4_10x were identical to control. This is too small a sample to drive policy selection.

### PONS

Control C0:

- attempts 305
- fills 112
- maker 83.04%
- mean markout -5.56 bp
- P10K -$7.19
- T10K 2.69h

P4_5x produced a modest improvement:

- attempts 297
- fills 122
- maker 84.43%
- mean markout -4.78 bp
- P10K -$6.93
- T10K 2.47h
- dMark +0.78 bp
- dP10K +$0.27

However economics remain far outside the intended gate.

### PUMP

Control C0:

- attempts 130
- fills 26
- maker 75.00%
- mean markout -5.62 bp
- P10K -$8.14
- T10K 11.65h

P4 improved PUMP materially, especially P4_10x:

- attempts 98
- fills 39
- maker 73.08%
- mean markout -0.01 bp
- P10K -$4.23
- T10K 7.76h
- dMark +5.61 bp
- dP10K +$3.91

This is a real development signal, but still fails maker/P10K/T10K gates and does not generalize across markets.

### VVV

Control C0:

- attempts 381
- fills 43
- maker 69.77%
- mean markout -4.60 bp
- P10K -$7.06
- T10K 7.07h

All P4 variants worsened markout and P10K. Example P4_5x:

- fills 60
- maker 67.50%
- markout -4.99 bp
- P10K -$7.70
- dMark -0.39 bp
- dP10K -$0.64

## Frozen conclusion

**Development Cycle 2: NO SESSION-4 CANDIDATE.**

Queue accessibility affects fill probability strongly but is not a universal adverse-selection solution. PUMP benefits materially, PONS benefits slightly at 5x, while BTC and VVV worsen. ETHFI's apparent 2x benefit is too small-sample to generalize.

Do not freeze P4 and do not start Session 4.

The evidence now suggests the next research cycle should change **order lifecycle mechanics**, not add more static signal filters. The primary hypothesis is that a JOIN quote becomes toxic when it remains live after the original 3/3 state weakens or flips.

Next development work should therefore test predeclared shorter entry lifetimes and cancel-on-signal-decay behavior using the already-SEEN raw replay datasets. Any simulated cancellation must represent genuine risk control and must not involve spoofing, layering, fake liquidity, or artificial-volume behavior.

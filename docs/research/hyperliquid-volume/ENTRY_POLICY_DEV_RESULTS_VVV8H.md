# Hyperliquid Entry Policy Development — VVV 8h SEEN Replay

Date: 2026-09-14

Status: SEEN DEVELOPMENT RESULT — NOT SESSION 4

Dataset: previously evaluated VVV 8h holdout books + trades, now explicitly treated as SEEN development data.

Control and policy search space were predeclared in `ENTRY_POLICY_DEV_PLAN.md` before replay results were inspected. Exit semantics, fees, notional, queue-ahead accounting, and selector gates were kept fixed.

## Results

| Policy | Attempts | Fill | Maker | Mean markout 5s | Median markout 5s | P10K | T10K | Max DD | Delta P10K vs C0 | Delta mean markout vs C0 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| C0 | 400 | 7.2% | 62.1% | -7.01 bp | -4.76 bp | -$7.68 | 13.82h | $4.46 | $0.00 | 0.00 bp |
| P1 0.5s | 399 | 6.5% | 63.5% | -3.55 bp | -3.06 bp | -$5.30 | 15.41h | $2.84 | +$2.39 | +3.45 bp |
| P1 1.0s | 389 | 6.9% | 64.8% | -2.80 bp | -2.94 bp | -$4.93 | 14.84h | $2.84 | +$2.75 | +4.21 bp |
| P1 2.0s | 371 | 7.5% | 62.5% | -4.55 bp | -3.75 bp | -$5.23 | 14.31h | $2.95 | +$2.45 | +2.46 bp |
| P2 0.10 | 119 | 12.6% | 63.3% | -9.03 bp | -5.20 bp | -$8.65 | 26.73h | $2.59 | -$0.97 | -2.02 bp |
| P2 0.20 | 63 | 9.5% | 58.3% | -19.05 bp | -18.51 bp | -$16.16 | 66.87h | $1.94 | -$8.48 | -12.05 bp |
| P2 0.30 | 4 | 0.0% | N/A | N/A | N/A | N/A | N/A | $0.00 | N/A | N/A |
| P3 0.10 | 381 | 7.1% | 63.0% | -7.91 bp | -4.68 bp | -$8.41 | 14.85h | $4.54 | -$0.72 | -0.90 bp |
| P3 0.20 | 340 | 6.5% | 63.6% | -4.12 bp | -3.25 bp | -$5.91 | 18.21h | $2.60 | +$1.78 | +2.89 bp |
| P3 0.30 | 304 | 5.9% | 55.6% | -7.45 bp | -3.84 bp | -$7.81 | 22.27h | $2.81 | -$0.12 | -0.44 bp |
| P4 2x | 320 | 8.1% | 57.7% | -4.83 bp | -3.54 bp | -$6.28 | 15.42h | $3.30 | +$1.41 | +2.18 bp |
| P4 5x | 386 | 6.5% | 60.0% | -4.94 bp | -4.68 bp | -$6.97 | 16.03h | $3.52 | +$0.71 | +2.06 bp |
| P4 10x | 388 | 6.4% | 62.0% | -4.09 bp | -4.30 bp | -$6.64 | 16.03h | $3.35 | +$1.04 | +2.92 bp |

## Interpretation

The strongest family-level evidence is P1 persistence confirmation.

`P1 1.0s` is the best single VVV setting in this replay because it improves all three adverse-selection diagnostics simultaneously while preserving activity:

- mean 5s markout improves by +4.21 bp;
- P10K improves by +$2.75;
- max drawdown falls from $4.46 to $2.84;
- attempts remain 389 vs 400 control;
- fill rate remains 6.9% vs 7.2% control.

This is evidence that transient 3/3 states are materially more toxic than states that persist for approximately one second.

However, P1 1.0s is **not yet acceptable for Session 4 freeze**:

- mean markout remains negative (-2.80 bp);
- P10K remains materially below the -$2 selector gate (-$4.93);
- maker ratio remains below 75% (64.8%);
- T10K remains far above 6h (14.84h).

P2 microprice-strength filtering clearly fails on this dataset and becomes worse as the threshold tightens. P3 only shows a moderate improvement at 0.20 and is not monotonic. P4 queue guards improve markout/P10K modestly but reduce maker quality and do not solve economics.

## Next gate

Do not freeze P1 1.0s yet. Run the exact same predeclared policy set on the previously captured multi-market 30m dataset as a SEEN cross-market sanity check. The purpose is not to optimize thresholds again, but to test whether P1's adverse-selection improvement generalizes directionally across markets.

No Session 4 unseen capture may begin from this result alone.
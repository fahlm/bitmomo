# Hyperliquid Entry Policy Development Cycle 1 — Result

Date: 2026-09-14

Status: **FROZEN DEVELOPMENT RESULT — NO SESSION-4 CANDIDATE YET**

This cycle used only SEEN development data. Session 4 remains untouched/unseen.

## Development datasets

1. VVV 8h raw L2 + trades replay (previously evaluated holdout, now SEEN).
2. Multi-market 30m raw L2 + trades replay across BTC, ETHFI, PONS, PUMP, VVV.

The replay engine reused the current standardized shadow-probe fill/exit semantics and changed only the predeclared entry filters from `ENTRY_POLICY_DEV_PLAN.md`.

## VVV 8h result

Control C0:

- attempts: 400
- fill: 7.2%
- maker: 62.1%
- mean 5s markout: -7.01 bp
- median 5s markout: -4.76 bp
- P10K: -$7.68
- T10K: 13.82h
- max DD: $4.46

Best persistence result on VVV was `P1_1.0s`:

- attempts: 389
- fill: 6.9%
- maker: 64.8%
- mean 5s markout: -2.80 bp
- median 5s markout: -2.94 bp
- P10K: -$4.93
- T10K: 14.84h
- max DD: $2.84
- delta P10K vs C0: +$2.75
- delta mean markout vs C0: +4.21 bp

Interpretation: persistence confirmation materially reduced adverse selection on the long VVV replay without simply eliminating most activity. However, economics still failed the intended gates.

Other notable VVV observations:

- `P2` micro-strength filters were generally harmful; tighter thresholds strongly worsened markout/P10K and eventually removed nearly all fills.
- `P3_0.20` improved VVV moderately but remained economically negative.
- `P4` queue guards improved VVV markout/P10K modestly and preserved more activity than the strongest micro filter.

## Multi-market 30m sanity check

The 30m sample was intentionally treated as a sanity check, not a statistically sufficient validation set.

Persistence family did **not** reproduce the VVV result consistently:

- `P1_0.5s`: valid comparisons 2; markout improved in 0; P10K improved in 2; both improved in 0; median dMark -1.13 bp; median dP10K +$1.52.
- `P1_1.0s`: valid comparisons 3; markout improved in 0; P10K improved in 1; both improved in 0; median dMark -0.65 bp; median dP10K $0.00.
- `P1_2.0s`: valid comparisons 3; markout improved in 1; P10K improved in 0; both improved in 0; median dMark 0.00 bp; median dP10K -$0.99.

Therefore `P1_1.0s` is **not robust enough to freeze** for Session 4 despite its attractive VVV result.

Cross-market family summary also showed:

- `P2_0.10`: some markout improvement in the short multi-market sample, but this conflicts with its harmful VVV 8h result.
- `P3`: mixed/inconsistent.
- `P4_5x` and `P4_10x`: modest positive median dMark and dP10K in the short cross-market sample and also modest improvement on VVV 8h. This makes queue accessibility the most directionally consistent family across the two development datasets, but sample size is still too small to select/freeze a policy.

## Frozen decision

**No entry policy is frozen for Session 4 yet.**

Reasons:

1. P1 persistence improved VVV materially but did not generalize in the 30m multi-market sanity check.
2. P2 cross-market positives conflict with the longer VVV replay.
3. P3 is mixed.
4. P4 is the most consistent family, but the multi-market sample is too short and fill counts are too small for a reliable freeze decision.
5. No tested variant achieved acceptable economics; all apparent improvements remain development diagnostics.

The predeclared combination rule is not activated yet because two independent families have not shown sufficiently consistent improvement across SEEN datasets.

## Next action

Collect a **new SEEN multi-market development capture**, not Session 4, using the same raw event schema and no live trading.

Recommended capture target:

- markets: BTC, ETHFI, PONS, PUMP, VVV;
- duration: at least 4 hours, preferably 6–8 hours if transport remains healthy;
- raw L2 top-5 books + individual trades;
- resilient reconnect + health log;
- no policy tuning during capture;
- label explicitly as development/SEEN before collection starts.

Then replay the already-predeclared C0/P1/P2/P3/P4 grid unchanged. If P4 (or another family) becomes convincingly consistent on the longer multi-market development capture, select and freeze one configuration before starting fresh unseen Session 4.

Do not weaken selector qualification gates and do not start Session 4 yet.

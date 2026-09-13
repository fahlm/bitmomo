# VVV Exit-Policy Seen Diagnostic

Date: 2026-09-13

## Status

The original VVV 8-hour holdout result is frozen and remains FAIL. The dataset was unseen when the fixed VVV policy was evaluated, so that historical result must not be overwritten or reclassified.

Frozen holdout summary:

- Round trips: 94
- Maker ratio: 76.1%
- T10K: 4.3 hours
- P10K: -$6.42
- Max drawdown: $12.07
- Win rate: 7.4%
- Verdict: FAIL

The same 8-hour dataset is now classified as SEEN_DIAGNOSTIC. It may be used for diagnostic research only. It cannot validate a new policy.

## New Methodology

The next research direction is exit policy, not volume generation. The volume engine showed sufficient throughput, but the fixed 30-second exit produced unacceptable economics and drawdown.

Diagnostic policy family:

- Keep score 3/3 IMP1 maker entry unchanged.
- Hold while the original microstructure thesis remains valid.
- Attempt passive maker exit when the thesis weakens.
- Use immediate taker risk exit when the thesis flips.
- Use recorded book and trade CSV data only.
- Do not live trade.
- Do not touch wallet or exchange account state.

## Seen Diagnostic Results

Baseline and candidate results on the now-seen 8-hour VVV dataset:

| Policy | Sample | RT | Maker | T10K | P10K | DD | Notes |
|---|---:|---:|---:|---:|---:|---:|---|
| fixed_30s_imp1 | 582 | 94 | 76.1% | 4.3h | -$6.42 | $12.07 | Frozen failed holdout baseline |
| state_hold2_weak1_flip3_180s | 577 | 93 | 79.6% | 4.3h | -$5.40 | $10.13 | Best maker-compatible seen diagnostic candidate |
| state_hold1_weak0_flip3_180s | 548 | 83 | 74.7% | 4.8h | -$5.03 | $8.38 | Better P10K/DD, but below 75% maker |
| state_hold2_weak1_flip3_90s | 584 | 95 | 76.8% | 4.2h | -$5.82 | $11.06 | Faster but worse economics |
| state_hold3_weak2_flip2_60s | 633 | 110 | 62.3% | 3.6h | -$5.62 | $12.36 | Higher throughput, maker ratio damaged |

Best maker-compatible candidate: state_hold2_weak1_flip3_180s.

This is not a validation pass. It improves P10K and drawdown versus the frozen fixed 30-second exit, but it remains far from the economics gate of P10K >= -$2 and max DD <= $2.50.

## Decision

Do not claim the new policy is valid. Treat it as a diagnostic candidate only. A fresh holdout collected after the policy is fixed is required before any validation claim or production/live-trading consideration.

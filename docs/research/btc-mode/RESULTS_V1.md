# BTC Mode Research — Results V1

Status: NOT YET RUN

This document is the canonical summary of empirical findings used to accept or reject the first production BTC Mode methodology.

## Required result sections

When experiments are complete, summarize:

1. Dataset coverage, exact 30-day window, source completeness, and exclusions.
2. Sample sizes for production-aligned and 4-hour robustness observations.
3. Point-in-time/data-quality audit outcome.
4. Direction-only baseline.
5. BTC Core incremental result.
6. Market Regime incremental result.
7. Bond incremental result.
8. Conditional findings that materially affect interpretation.
9. +6h / +12h / +24h return, MAE, MFE, and realized-volatility results.
10. +72h persistence check.
11. Production-aligned versus 4-hour robustness consistency.
12. Chronological holdout and threshold-sensitivity results.
13. Risk-On / Wait & See / Risk-Off candidate-state distribution.
14. Failure modes, unstable relationships, and low-`n` cells.
15. Recommended deterministic production rule.
16. Rejected alternatives and why they were rejected.
17. Decision-power map: which pillars can change Mode versus explanation-only inputs.

## Acceptance standard

No `btc-mode-v1` formula is accepted because it sounds economically plausible. It must be supported by point-in-time evidence and remain operationally explainable.

With the intentionally short calibration window, a rule should favor robustness and simplicity over in-sample optimization. Precise fitted weights require exceptional justification and are not the default approach.

A more complex rule should not be chosen unless its value is meaningful relative to the simpler baseline, especially for downside-risk discrimination.

## Forward-validation requirement

Acceptance of V1 for launch does not imply universal validation. Once launched, official twice-daily session outputs must be stored append-only with +6h/+12h/+24h/+72h outcomes. This forward record becomes the long-run evidence base.

## Current conclusion

No empirical BTC Mode conclusion is frozen yet. Any weighting, veto, downgrade, confirmation, or mapping discussed before this file is populated remains a research hypothesis, not production methodology.

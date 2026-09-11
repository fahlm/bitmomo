# BTC Mode Research — Results V1

Status: INTRADAY RE-EXECUTION REQUIRED

This document is the canonical summary of empirical findings used to accept or reject the first production BTC Mode methodology.

## Important correction

The first 30-day P0.2B exploratory run is **not accepted as canonical BTC Mode calibration evidence**.

Two issues require re-execution before any production formula is frozen:

1. The research target emphasized +6h/+12h/+24h and +72h persistence, which is too slow for Bitmomo's intended active intraday decision use case.
2. The exploratory replay did not reproduce every production semantic exactly. Any result based on substitute Direction/Regime semantics must remain exploratory rather than production-methodology evidence.

The useful parts of that run remain company research assets: source-coverage work, PIT/leakage procedures, raw-data acquisition, missingness audit, and experiment infrastructure.

## Corrected V1 research target

Primary feature replay cadence: hourly PIT-safe snapshots unless a source genuinely supports a higher canonical update cadence.

Primary outcome horizons:

- +15m
- +30m
- +1h
- +2h
- +4h
- +6h

Secondary context horizons:

- +12h
- +24h

Required path metrics:

- return
- MAE
- MFE
- time to MAE
- time to MFE
- realized volatility
- path/first-excursion ordering where defensible

The 08:10/20:10 `America/New_York` session anchors remain important product subsets and deep-context reports, but are not the sole or primary statistical sample for intraday Mode calibration.

## Required result sections

When corrected experiments are complete, summarize:

1. Dataset coverage, exact 30-day window, source completeness, and exclusions.
2. Canonical replay parity against repository formulas.
3. Hourly intraday sample size and dependence warnings.
4. Production-anchor subset results.
5. State-change/event subset results.
6. Point-in-time/data-quality audit outcome.
7. Canonical Direction baseline.
8. Canonical BTC Core incremental result.
9. Market Regime incremental result by horizon.
10. Derivatives incremental/context findings.
11. Bond incremental result by horizon.
12. 15m/30m/1h/2h/4h/6h return and MAE/MFE results.
13. Time-to-opportunity / time-to-invalidation findings.
14. Tradability/friction sensitivity.
15. Chronological holdout with purging/embargo and dependence-aware uncertainty.
16. Risk-On / Wait & See / Risk-Off candidate-state distribution.
17. Failure modes, unstable relationships, and low-independent-sample conditions.
18. Recommended deterministic production rule and justified recomputation cadence.
19. Rejected alternatives and why they were rejected.
20. Decision-power map: which pillars can change Mode at which horizons versus explanation-only inputs.

## Acceptance standard

No `btc-mode-v1` formula is accepted because it sounds economically plausible or because a static label correlates with a one-day return in one 30-day market regime.

The candidate rule must show useful intraday **path asymmetry**: a defensible distinction between favorable and adverse excursion over minutes-to-hours, not merely buy-and-hold close-to-close performance.

A more complex rule should not be chosen unless its incremental value is meaningful relative to the simpler baseline.

## Forward-validation requirement

Once launched, BTC Mode evaluations and material state changes must be stored append-only with short-horizon outcomes. The twice-daily 08:10/20:10 session editions remain canonical deep-context checkpoints, but forward validation of the Mode itself should reflect its justified intraday recomputation cadence.

## Current conclusion

No empirical BTC Mode formula is frozen yet.

The previous A/B/C run is classified as **exploratory / superseded for calibration purposes**, not deleted. Its reproducible artifacts remain useful for audit and data engineering, but its longer-horizon conclusions must not be used to set `btc-mode-v1` weights, vetoes, or mappings.

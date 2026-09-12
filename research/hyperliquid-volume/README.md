# Hyperliquid Referral-Volume Research Code

This directory is reserved for reproducible code and small machine-readable artifacts supporting `docs/research/hyperliquid-volume/`.

## Intended responsibilities

Research code placed here should support:

1. public Hyperliquid market-data acquisition
2. resilient websocket capture and data-health logging
3. trade/L2 normalization and deduplication
4. microstructure feature generation
5. queue-aware hypothetical maker-fill simulation
6. maker/taker fee and fallback modeling
7. cross-market screening
8. frozen-policy unseen holdout evaluation
9. report/table generation

## Rules

- Do not commit large raw CSV recordings directly to Git.
- Record dataset filenames, duration, row counts and status in `docs/research/hyperliquid-volume/DATA_MANIFEST.md`.
- Failed experiments remain reproducible and documented.
- No code in this folder may submit live orders by default.
- Live/testnet execution code, if added later, must require explicit configuration and preserve ALO/post-only intent, reconciliation and kill-switch behavior.
- Research price formatting must not silently become production price formatting; use official Hyperliquid metadata before sending any real order.
- Every accepted result should identify the code commit and dataset used.

## Current status

The working scripts currently live in the user's local `~/bitmomo-hl-volume-bot` directory and have not yet been promoted here as canonical repository code. The documentation branch intentionally captures the experiment history first so the research record is not lost while the resilient VVV holdout recorder is still being validated.

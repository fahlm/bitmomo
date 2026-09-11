# BTC Mode Research Code

This directory is reserved for reproducible BTC Mode research code and small machine-readable research artifacts.

## Expected responsibilities

Research code should eventually support:

1. point-in-time dataset construction
2. production-rule-compatible feature replay
3. forward BTC outcome labeling (+6h/+24h/+72h/+7d, MAE, MFE, realized volatility)
4. conditional analysis
5. baseline comparison (A/B/C/D)
6. walk-forward validation
7. report/table generation for `docs/research/btc-mode/RESULTS_V1.md`

## Rules

- Research code must not write to production or staging WordPress environments.
- Research code must not silently reimplement a different Signal Engine or Regime methodology. If production logic cannot be called directly, the replay implementation must be explicitly versioned and tested against production fixtures/outputs.
- Large raw datasets should not be committed directly to Git. Store a dataset manifest/checksum and durable artifact reference instead.
- Every generated result must identify the source dataset/version and code commit used.
- No production BTC Mode weights/rules are to be encoded here before empirical acceptance and methodology freeze.

Canonical methodology and results live under `docs/research/btc-mode/`.

# Hyperliquid Referral-Volume Research

Status: ACTIVE — EXECUTION VALIDATION

This folder is the canonical research record for Bitmomo's experiment to determine whether a small account can reach Hyperliquid referral eligibility volume with controlled capital loss and, ideally, positive expected PnL.

## Product/business question

Can Bitmomo generate at least **$10,000 of genuine Hyperliquid trading volume** from approximately **100 USDC starting capital** quickly enough to unlock referral eligibility, while avoiding self-trading/manipulative volume and keeping expected capital loss acceptably small?

The research objective evolved from "find a profitable directional strategy" into the more precise question:

> Can Bitmomo combine a real microstructure edge with passive-maker execution so that genuine external fills create the required volume at low or positive expected cost?

## Current conclusion

Directional hypotheses tested so far have **not** generalized out of sample. HH/HL/BOS continuation, its reversal/fade variant, and 1-minute candle-level order-flow proxies are rejected as standalone alpha.

The strongest reproducible finding so far is **Hyperliquid microstructure alignment**:

- 5-level order-book imbalance,
- microprice displacement, and
- recent aggressor-flow imbalance

show a monotonic relationship with post-fill markout. In the same-session validation split, score 3/3 produced materially better 5s and 10s markout than score 0/3.

However, BTC is operationally unattractive for a 100 USDC account because the spread is tiny relative to maker fees and top-of-book queues are deep. Cross-market scanning identified VVV, PONS and ETHFI as more plausible execution venues; VVV is the current primary candidate.

The current frozen candidate awaiting a clean unseen holdout is:

- market: **VVV**
- order notional: **$100**
- signal: **3/3 microstructure alignment**
- entry: **one-tick improvement inside spread (IMP1), maker-only intent**
- exit: **IMP1, up to 30s before fallback**
- validation gates: **RT >= 50, maker ratio >= 75%, max drawdown <= $2.50, projected T10K <= 6h**
- profit gate: **P10K >= $0**
- low-cost referral gate: **P10K >= -$2.00**

No live trading is authorized from this research record.

## Canonical files

- `EXPERIMENT_LOG.md` — append-only history of every accepted/rejected experiment
- `RESULTS_V0.md` — consolidated empirical results and current candidate
- `METHODOLOGY.md` — research and validation rules
- `DATA_MANIFEST.md` — local datasets, row counts and provenance notes
- `LIMITATIONS.md` — known modeling and execution caveats

Research code should live under `research/hyperliquid-volume/`; large raw market datasets should not be committed to Git.

## Non-negotiable rules

- genuine fills against external market flow only; no self-trading or synthetic wash volume
- no look-ahead in signal construction or outcome measurement
- failed hypotheses remain documented
- once a holdout rule is frozen, do not tune it on that holdout
- quote/fill simulation must distinguish queue-ahead, post-only behavior, maker/taker fees and fallback exits
- small-sample leaderboard winners are not accepted as production evidence
- raw local datasets are referenced by manifest rather than committed directly
- no mainnet trading until an unseen holdout passes predefined gates

## Branching decision

This research is intentionally isolated on `research/hyperliquid-referral-volume-v0` rather than being mixed into the existing BTC Mode research branches. The repository already has a canonical research convention under `docs/research/` and topic-specific `research/*` branches; this branch follows that pattern while keeping referral-volume execution research separate from BTC Mode product research.

# Hyperliquid Referral-Volume Research

Status: ACTIVE — DYNAMIC MARKET / EXECUTION VALIDATION

> **Start here for the latest operational state:** [`CURRENT_HANDOFF.md`](./CURRENT_HANDOFF.md)
>
> That file is the canonical cross-chat handoff and should be read before continuing this research.

This folder is the canonical research record for Bitmomo's experiment to determine whether a small account can reach Hyperliquid referral eligibility volume with controlled capital loss and, ideally, positive expected PnL.

## Product/business question

Can Bitmomo generate at least **$10,000 of genuine Hyperliquid trading volume** from approximately **100 USDC starting capital** quickly enough to unlock referral eligibility, while avoiding self-trading/manipulative volume and keeping expected capital loss acceptably small?

The research objective evolved from "find a profitable directional strategy" into the more precise question:

> Can Bitmomo combine a real microstructure edge, passive execution, dynamic market selection, and strict risk controls so that genuine external fills create the required volume at low or positive expected cost?

## Current conclusion

Directional hypotheses tested so far have **not** generalized out of sample. HH/HL/BOS continuation, its reversal/fade variant, and 1-minute candle-level order-flow proxies are rejected as standalone alpha.

The strongest information finding remains **Hyperliquid microstructure alignment** across:

- order-book imbalance,
- microprice displacement, and
- recent aggressor-flow imbalance.

However, execution economics remain the dominant problem.

The previously frozen VVV 8-hour unseen holdout has already completed and **FAILED**:

- RT: 94
- maker ratio: 76.1%
- T10K: 4.3h
- P10K: -$6.42
- max DD: $12.07

A subsequent state-aware-exit diagnostic on that now-SEEN dataset improved results only modestly and still failed economics/drawdown gates.

Therefore VVV is **not** hard-coded as the production market and is not considered validated.

## Current architecture

The active research direction is a market-agnostic selector/supervisor:

`Market Observer -> Eligibility Engine -> Market Ranker -> Supervisor`

Key principles:

- no LLM in the execution loop;
- no wallet/order submission in the research selector;
- fail closed when metrics are missing/stale;
- no qualified market => IDLE;
- active-market degradation => stop new entries, drain/flatten, then switch only when flat;
- deterministic hysteresis prevents market-selection thrashing;
- one eventual execution authority only.

The live dry-run selector includes resilient Hyperliquid feed recovery with L2 heartbeat monitoring, automatic reconnect, generation fencing, bounded cleanup, and exponential backoff.

## Dual-window selector

An important Session 2 research bug was found and fixed: the original selector used one 15-minute window for both current market state and execution evidence, causing useful execution samples to age out before the minimum sample gate could be satisfied.

The selector now separates:

- **market-state window:** rolling 15 minutes;
- **execution-evidence window:** rolling 4 hours, capped to latest 100 attempts;
- **execution freshness:** latest attempt must be no older than 30 minutes.

Execution qualification still requires a minimum sample count and all empirical economics gates to pass.

## Current validation session

Session 3 is the fresh validation session for the dual-window selector. Its rules should not be tuned mid-session.

At the latest documented checkpoint, the dual-window behavior is functioning: execution sample counts can exceed the former 15-minute limit without resetting simply because the market-state window advances. No market has yet been accepted as qualified.

See [`CURRENT_HANDOFF.md`](./CURRENT_HANDOFF.md) for the latest per-market metrics and next action.

## Canonical files

- `CURRENT_HANDOFF.md` — latest operational status and next action; cross-chat source of truth
- `EXPERIMENT_LOG.md` — history of accepted/rejected experiments
- `RESULTS_V0.md` — consolidated empirical results
- `METHODOLOGY.md` — research and validation rules
- `DATA_MANIFEST.md` — local datasets, row counts and provenance notes
- `LIMITATIONS.md` — known modeling and execution caveats
- `VVV_EXIT_POLICY_SEEN_DIAGNOSTIC.md` — state-aware exit diagnostic on the now-SEEN VVV holdout

Research code lives under `research/hyperliquid-volume/`; large raw market datasets and local JSONL capture sessions should generally not be committed to Git.

## Non-negotiable rules

- genuine fills against external market flow only; no self-trading or synthetic wash volume
- no look-ahead in signal construction or outcome measurement
- failed hypotheses remain documented
- once a holdout/session rule is frozen, do not tune it on that same validation data
- quote/fill simulation must distinguish queue-ahead, post-only behavior, maker/taker fees and fallback exits
- small-sample leaderboard winners are not accepted as production evidence
- unknown metrics remain UNKNOWN rather than being imputed optimistically
- no qualified market means IDLE, not "pick the least bad market"
- raw local datasets are referenced by manifest rather than committed directly
- no mainnet trading until fresh unseen validation passes predefined economics, risk, reliability, and reconciliation gates

## Branching decision

This research is intentionally isolated on `research/hyperliquid-referral-volume-v0` rather than being mixed into BTC Mode/product branches. The repository follows the existing `docs/research/` convention while keeping referral-volume execution research separate from Bitmomo's product-facing BTC intelligence work.

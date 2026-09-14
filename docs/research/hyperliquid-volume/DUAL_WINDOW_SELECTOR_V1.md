# Dual-window selector v1

Status: research-only. Not validated for mainnet.

## Why this change

The first resilient live shadow sessions showed that using one 15-minute rolling window for both market state and execution evidence continuously deleted fill/maker/markout/P10K/T10K samples before the `min_execution_samples=30` gate could be evaluated reliably.

## Windows

- Market-state window: 15 minutes (`900s`)
  - spread
  - BBO queue
  - trade rate
  - book imbalance
  - microprice edge
  - aggressor flow
- Execution-evidence window: 4 hours (`14,400s`) and at most the latest 100 attempts
  - fill rate
  - maker ratio
  - 5s markout
  - volume/hour
  - T10K
  - P10K
- Execution freshness: latest execution attempt must be no older than 30 minutes (`1,800s`) before a market can become `QUALIFIED`.

The selector remains fail-closed: stale execution evidence yields `WATCH`, not `QUALIFIED`; stale L2/book data remains a hard reject.

## Research discipline

Existing shadow sessions are SEEN diagnostics. They can be used to verify that the revised aggregation behaves sensibly, but they cannot serve as independent validation for rules changed after seeing those sessions. A fresh session is required after the dual-window logic is frozen.

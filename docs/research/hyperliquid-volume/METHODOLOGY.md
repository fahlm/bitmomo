# Hyperliquid Referral-Volume Research — Methodology

## Objective

Estimate whether Bitmomo can reach Hyperliquid referral eligibility volume using approximately 100 USDC starting capital while minimizing expected capital loss and avoiding non-genuine volume generation.

## Research sequence

The research follows a funnel:

1. test simple directional hypotheses cheaply
2. reject weak ideas on unseen data
3. move closer to actual venue microstructure only when simpler proxies fail
4. model maker fill probability and adverse selection separately
5. screen markets by spread, queue accessibility and activity
6. freeze one execution policy
7. validate once on a clean unseen event-level holdout

## Directional research rules

- signals must be defined before holdout evaluation
- next-candle or later entries only; no same-candle look-ahead
- development diagnostics become seen immediately after inspection
- any rule derived from a seen diagnostic must be tested on a different untouched window
- fees are applied when evaluating economic viability

## Microstructure signal

Current 3-feature alignment score, signed to the intended quote side:

1. 5-level book imbalance
2. microprice edge relative to mid
3. recent aggressor-flow imbalance

For BUY, all three positive => score 3/3.
For SELL, all three negative => score 3/3.

Score 2/3 was explored for throughput but is not the frozen primary rule.

## Fill modeling principles

The fill simulator must distinguish:

- quote side and price
- visible queue ahead when joining an existing level
- zero initial queue ahead only when a hypothetical quote creates a genuinely new best price inside the spread
- individual aggressive trades and whether they trade at/through the resting quote
- maker versus taker exit
- quote lifetime
- post-fill markout

A quote that merely sees opposite-side volume is not automatically considered filled.

## Fee assumptions used in current research

Base-tier assumptions used throughout the current experiments:

- maker fee: 0.015% per filled leg
- taker fee: 0.045% per filled leg

These must be rechecked against the account's actual fee tier immediately before any live deployment.

## Market screening

Markets are compared on:

- 24h notional volume
- BBO spread in basis points
- visible bid/ask BBO queue notional
- order count at BBO when available
- 5-level depth
- trade activity in event-level recordings
- projected maker ratio and volume/hour under the same simulation assumptions

Wide spread alone is not sufficient. Very thin or one-sided books can produce misleading economics and elevated inventory risk.

## Candidate policy terminology

- `JOIN`: rest at current best bid/ask and inherit visible queue ahead
- `IMP1`: improve the current BBO by one valid tick while staying passive; if this creates a new best price, initial queue ahead is modeled as zero
- `S3`: require all three microstructure features aligned
- `S2`: require at least two of three aligned
- `E30s`: maker exit is allowed up to 30 seconds before safety fallback

## Holdout discipline

The definitive VVV holdout is frozen before acquisition:

Primary:
- VVV
- $100 notional
- S3
- IMP1 entry
- IMP1 exit
- 30s exit lifetime

Control:
- same market/order/signal/entry
- JOIN exit
- 30s lifetime

Acceptance gates:

- RT >= 50
- maker ratio >= 75%
- max drawdown <= $2.50
- projected T10K <= 6h
- profit gate: P10K >= $0
- low-cost referral gate: P10K >= -$2

The holdout must not be evaluated partially and then resumed. If acquisition fails, preserve the partial file for audit, do not inspect strategy results, and restart a clean unseen holdout.

## Data acquisition requirements

Event-level recorder should retain at least:

- exchange and receive timestamps
- top five bid/ask prices and sizes
- order count `n` where available
- every individual trade's aggressor side, price, size, time and trade ID

Recorder reliability requirements:

- stale-feed watchdog
- automatic reconnect
- append-safe output
- trade-ID deduplication
- duplicate reconnect-snapshot protection
- explicit health log
- laptop sleep prevention during long Mac captures

## Reproducibility

Large raw CSV data should remain outside Git. For each accepted/rejected result, record:

- local dataset filename
- market and time window/duration
- row counts
- code/script name
- rule version
- whether dataset was development, diagnostic, or unseen holdout
- exact acceptance decision

## Live-trading gate

No mainnet trading should be authorized by a development or same-session split alone. Minimum progression:

1. resilient recorder verified
2. clean unseen 8h holdout passes frozen gates
3. price/size formatting changed from inferred research ticks to official Hyperliquid metadata rules
4. ALO/post-only enforcement verified
5. paper/testnet canary verifies order lifecycle, reconciliation and kill switches
6. only then consider tightly capped mainnet canary

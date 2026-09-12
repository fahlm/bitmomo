# Hyperliquid Referral-Volume Research — Limitations

## 1. Most execution results are still small-sample

The strongest candidate screens were generated from ~30 minutes of multi-market event data. Several attractive rows had only 5-6 completed round trips. Those rows are useful for hypothesis selection, not for estimating stable expected PnL.

## 2. Same-session validation is weaker than a true unseen holdout

The BTC markout study used first-half research and second-half validation within the same ~120-minute recording. The monotonic alignment result is encouraging, but regime independence is not established.

## 3. Queue models remain approximations

Even event-level public L2 data does not reveal our actual exchange queue position because no real order was resting. The simulator estimates queue-ahead from displayed book state and aggressive trades. Cancellation position, hidden/implicit effects and exchange matching details can differ from the model.

## 4. IMP1 fill assumption needs live/testnet verification

When a hypothetical one-tick improvement creates a new BBO, the model assumes zero queue ahead. That is directionally reasonable but does not account for latency between signal observation and order acknowledgement, competitors improving at the same time, or the quote becoming non-passive before submission.

## 5. Research ticks are inferred

Some exploratory scripts inferred ticks from observed price differences and displayed floating-point artifacts such as `9.999999999995449e-06`. No live order should use those inferred values. Production/testnet orders must use official Hyperliquid asset metadata, significant-figure and `szDecimals` rules.

## 6. Fee tier can change economics materially

Current simulations use base-tier assumptions (1.5 bp maker, 4.5 bp taker per leg). Actual account fees, discounts, tier changes or future fee schedule changes must be queried before live use.

## 7. Funding is not yet fully modeled

Short quote/position lifetimes make funding less central than execution fees, but it is not yet explicitly included in every simulator. Longer inventory holds would require funding treatment.

## 8. Slippage on taker fallback is simplified

Fallback exits generally use contemporaneous best bid/ask plus taker fee. Real fill price can be worse if the safety exit size exceeds available top-level liquidity or the book moves during submission.

## 9. Recorder continuity has already failed once

The first 8-hour VVV capture stalled silently. A resilient recorder is being validated, but long-session reliability is not yet proven. Gaps in market data can bias fill simulations and throughput estimates.

## 10. Thin-market venue risk

PONS, VVV and ETHFI have more attractive spreads/queues than BTC, but smaller markets can experience sudden spread widening, disappearing liquidity, jumps and one-sided order books. The very properties that improve maker economics can increase inventory/tail risk.

## 11. 24h volume snapshots are time-specific

The market scanner is a snapshot, not a permanent venue ranking. A market's spread, activity and visible queue can change materially across sessions.

## 12. Projected T10K assumes regime repetition

`T10K` is an extrapolation from observed volume/hour. A 30-minute window projected to 4.2 hours does not mean the same opportunities will persist for 4.2 hours.

## 13. Projected P10K assumes stable conditional execution quality

`P10K` scales observed PnL per executed volume. It should not be interpreted as a guaranteed cost/profit, especially when based on few trades.

## 14. No live latency measurement yet

Research callbacks include exchange/receive timestamps, but the strategy has not yet measured end-to-end signal computation, signing, submission, acknowledgement and cancellation latency from the user's actual Mac/network environment.

## 15. Strategy and business objective must remain distinct

The objective is to unlock referral eligibility efficiently. A low expected cost could be economically rational for Bitmomo even if the bot is not a standalone profit center. That business conclusion must not be used to relabel a negative-EV trading strategy as profitable.

## 16. No self-trading or artificial volume

Research assumes genuine interaction with external market flow. Any implementation must continue to avoid same-account/self-matched volume or other artificial activity designed to fabricate volume without real market interaction.

## 17. Capital floor is tight

With ~100 USDC starting equity, a few USDC of unexpected loss materially changes survival probability. Live canary sizing and kill-switches must be substantially more conservative than the largest research notionals.

## 18. Holdout integrity is fragile

Once the clean VVV holdout is evaluated, it becomes seen. If it fails, tuning rules on that same data and re-testing would invalidate the evidence. Any new rule must move to another untouched period/session.

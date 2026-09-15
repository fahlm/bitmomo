# Hyperliquid Volume-Budget Objective V1

Date: 2026-09-16
Status: PREDECLARED RESEARCH OBJECTIVE
Branch: `research/hyperliquid-referral-volume-v0`

## Business objective

The goal is not necessarily to maximize trading PnL. The operational goal is to accumulate at least $10,000 of legitimate Hyperliquid trading volume at a bounded expected and tail cost.

The engine must remain compliant with normal market conduct. No wash trading, self-trading, spoofing, layering, fake liquidity, or artificial-volume behavior.

## Canonical target

Primary cumulative target:

- genuine cumulative trading volume >= $10,000.

Primary loss budgets to evaluate:

- Tier A: total net trading cost <= $5;
- Tier B: total net trading cost <= $10.

Net trading cost includes realized trading PnL and simulated/actual fees under the policy accounting. Positive trading PnL reduces net cost.

## Important accounting principle

Volume is cumulative across risk epochs. The target does **not** require one uninterrupted run.

If one risk epoch stops after losing $5 before reaching $10,000 volume, a second sequential epoch may continue accumulating toward the same cumulative volume target, subject to the total $10 risk budget.

Do not run two independent order-authority bots against the same account/market. One supervisor and one execution authority must own orders to prevent conflicting positions, queue cannibalization, duplicated exposure, or accidental self-interaction.

Therefore the user's '2x' concept is represented as:

`one engine -> epoch 1 max loss $5 -> if target incomplete, epoch 2 max additional loss $5 -> cumulative hard loss budget $10`

not two simultaneous bots.

## Core metric

`CostTo10K = - NetPnL at the instant cumulative round-trip volume first reaches $10,000`

Report both signed PnL and positive cost convention.

Related efficiency metric:

`VolumePerDollarCost = cumulative_volume / max(cost, epsilon)`

Reference efficiencies:

- $5 CostTo10K = 2,000 volume per $1 cost = 5 bp net cost per unit volume;
- $10 CostTo10K = 1,000 volume per $1 cost = 10 bp net cost per unit volume.

## Why P10K alone is insufficient

Mean or point-estimate P10K does not answer whether the bot usually reaches the target before the loss budget is exhausted.

For every candidate policy/market-selection system estimate the distribution of:

- CostTo10K;
- TimeTo10K;
- maximum drawdown before 10K;
- volume achieved before first -$5 loss boundary;
- volume achieved before cumulative -$10 boundary;
- probability of reaching $10K before -$5;
- probability of reaching $10K before -$10;
- P50/P75/P90/P95 CostTo10K;
- CVaR95 CostTo10K;
- number of risk epochs required;
- fraction of paths that never reach target within the evaluation window.

## Resampling / uncertainty

Do not assume individual round trips are IID.

Preferred evaluation:

- preserve chronological order for primary historical replay;
- use block bootstrap by contiguous time blocks for uncertainty estimates;
- keep market/regime clustering intact where practical;
- do not randomly reshuffle individual trades as the primary risk estimate.

Evaluate both:

1. single-market policy paths;
2. autonomous-selector paths where market switches obey frozen supervisor rules.

## Tier-A acceptance rule ($5 budget)

A candidate can be described as '$5-efficient' only if all are true on fresh unseen validation:

- expected CostTo10K <= $5;
- P75 CostTo10K <= $5, or an explicitly approved alternative probability target before collection;
- catastrophic tail is bounded by risk guardian;
- target volume is reached in a practical time window;
- result is not dependent on one isolated market episode.

## Tier-B acceptance rule ($10 budget)

If Tier A is not achievable, Tier B may be considered separately. Do not relabel a failed $5 policy as successful without freezing the new objective first.

For Tier B, default research target before fresh validation:

- expected CostTo10K <= $7.50;
- P90 CostTo10K <= $10;
- probability of reaching $10K before cumulative -$10 >= 90%;
- no single epoch may exceed -$5 without stopping new entries;
- cumulative hard budget -$10 forces HALT;
- practical TimeTo10K;
- robust across multiple markets/regimes or governed by a validated selector.

These probability thresholds are research targets and must be frozen before unseen validation.

## Sequential two-epoch state machine

```text
START
  |
  v
cumulative_volume = 0
cumulative_pnl = 0
  |
  v
EPOCH 1
  |-- volume >= 10K ----------------------> SUCCESS
  |-- epoch pnl <= -$5 -------------------> STOP / FLAT / REASSESS
                                              |
                                              v
                                           EPOCH 2
                                              |-- cumulative volume >= 10K -> SUCCESS
                                              |-- cumulative pnl <= -$10 ---> HALT FAIL
```

A new epoch is not permission to ignore a degraded market. The normal dynamic selector must still require a qualified market before entries resume.

## Interpretation of existing SEEN P10K estimates

Existing development point estimates near or better than -$10 per $10K are useful diagnostics but are not sufficient for Tier B approval because variance/tail probability has not yet been measured under the cumulative-loss state machine.

Changing the budget from $5 to $10 may make more policies economically relevant, but it does not create alpha or reduce adverse selection. It only changes the acceptable cost envelope.

## Mainnet gate

No mainnet order authority is approved by this document. Before live use:

1. Direction Alpha Audit conclusion frozen;
2. execution policy frozen;
3. autonomous selector configuration frozen;
4. fresh unseen validation using this volume-budget objective;
5. risk guardian implements per-epoch and cumulative hard loss limits;
6. account/position reconciliation and one execution authority verified;
7. shadow soak passes operational reliability gates.

# Hyperliquid Dynamic Market Selector V0

Status: **IMPLEMENTED AS READ-ONLY RESEARCH SKELETON / NOT MAINNET AUTHORIZED**

## Why this exists

The VVV 8-hour unseen holdout demonstrated that market choice cannot be hard-coded. VVV met throughput and maker-ratio gates but failed economics and drawdown gates. The system therefore needs continuous market requalification rather than a permanent `VVV-only` rule.

Current evidence:

- VVV frozen primary policy holdout: 94 RT, 76.1% maker, T10K ~4.3h, P10K -$6.42, DD $12.07 — **FAIL**.
- Seen state-aware exit diagnostic improved economics only modestly; best maker-compatible candidate remained around P10K -$5.40 and DD $10.13 — **FAIL**.
- Therefore **VVV is not presently QUALIFIED for production**.

## Architecture

One process, multiple deterministic modules, one future execution authority:

1. **Market Observer** — rolling L2/trade/execution metrics per market.
2. **Eligibility Engine** — fail-closed verdict: WARMUP / WATCH / QUALIFIED / DEGRADED / REJECT / UNKNOWN.
3. **Market Ranker** — ranks markets but only QUALIFIED markets may become switch candidates.
4. **Supervisor** — hysteretic ACTIVE/IDLE/STOP_NEW_ENTRY/DRAIN/SWITCH/HALT state machine.
5. **Execution Engine** — intentionally absent from this research package. No order submission path is implemented here.
6. **Risk Guardian** — represented by hard-fault inputs; production integration must have authority to block all new orders.

No LLM participates in the execution decision loop.

## Fail-closed principles

- Missing metrics are `UNKNOWN`; they are never silently imputed.
- Insufficient execution samples => WATCH, never QUALIFIED.
- Stale L2 feed => hard reject / no new entry.
- If no market qualifies => IDLE.
- A challenger cannot replace the active market from one ranking fluctuation.
- No switch occurs while inventory is non-flat.
- A degraded active market triggers STOP_NEW_ENTRY and DRAIN before switch.

## Provisional research gates

These are **not validated production thresholds** and live in `market_selector/config.py` so changes are auditable.

Current defaults include:

- warm-up: 300 seconds
- minimum 24h volume: $10M
- median spread: 1–12 bp
- BBO queue / target order: <=20x
- trade activity: >=3 trades/min
- execution samples: >=30
- fill rate: >=5%
- maker ratio: >=75%
- 5s markout: >=0 bp
- T10K: <=6h
- P10K: >=-$2
- qualification streak: 3 windows
- degradation streak: 2 windows
- switch confirmation: 3 windows
- challenger score margin: 8 points

These values are explicit hypotheses, not empirical truths.

## Current code

Canonical research implementation:

- `research/hyperliquid-volume/market_selector/config.py`
- `research/hyperliquid-volume/market_selector/models.py`
- `research/hyperliquid-volume/market_selector/observer.py`
- `research/hyperliquid-volume/market_selector/eligibility.py`
- `research/hyperliquid-volume/market_selector/ranker.py`
- `research/hyperliquid-volume/market_selector/supervisor.py`
- `research/hyperliquid-volume/market_selector/live_dry_run.py`
- `research/hyperliquid-volume/tests/test_market_selector.py`

The live adapter is public-market-data only. It instantiates `Info`, never `Exchange`, and contains no wallet or order-submission path.

## Execution economics caveat

Structural L2/trade metrics alone are insufficient to qualify a market. `fill_rate`, `maker_ratio`, `markout_5s`, `T10K`, and `P10K` require empirical dry-run execution observations. Until those are supplied, the live selector must show WATCH/UNKNOWN rather than promoting a market.

This is intentional: earlier experiments showed that attractive spread/depth snapshots can still produce unacceptable realized economics.

## Required work before mainnet

1. Integrate the selector core into the local `bitmomo-hl-volume-bot` process.
2. Wire the existing dry-run maker/fill simulator into `ExecutionObservation` so execution metrics update online.
3. Run multi-session selector shadow mode across multiple Hyperliquid markets.
4. Validate selector stability, market rotations, and no-qualified IDLE periods.
5. Validate the next exit policy on a fresh unseen dataset.
6. Add production Risk Guardian/reconciler and exact Hyperliquid price/size precision handling.
7. Only after explicit acceptance may an execution client be connected.

## Non-goal

This selector is not designed to maximize trading frequency. Its first responsibility is to refuse trading when execution economics are not supported by current evidence.

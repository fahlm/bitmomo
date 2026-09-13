# Execution Feedback Contract — Hyperliquid Market Selector

Status: research / dry-run only. No wallet or order-submission dependency.

## Purpose

The market selector must not infer execution quality from spread, depth or volume alone. Prior VVV research showed that apparently attractive structural conditions can still produce unacceptable P10K and drawdown. Therefore `fill_rate`, `maker_ratio`, `markout_5s_bps`, `volume_per_hour`, `T10K` and `P10K` are populated only from explicit shadow/paper execution outcomes.

## Event granularity

Emit exactly one finalized event per hypothetical entry attempt.

Every attempt must have a unique `(source, attempt_id)` pair. Unfilled attempts are required because they form the denominator of fill rate.

Required fields:

```json
{
  "attempt_id": "vvv-000001",
  "coin": "VVV",
  "timestamp_ms": 1700000000000,
  "filled": true
}
```

Optional/default fields:

```json
{
  "maker_entry": true,
  "maker_exit": false,
  "markout_5s_bps": 0.12,
  "round_trip_volume_usd": 200.0,
  "pnl_usd": -0.03,
  "policy_id": "shadow-probe-v0",
  "source": "shadow"
}
```

## Semantics

- `filled=false`: the hypothetical entry did not fill. `maker_entry`, `maker_exit` must be false and round-trip economics must be null/omitted.
- `filled=true`: the entry filled under the shadow/paper execution model.
- `maker_entry`: whether the filled entry leg was maker.
- `maker_exit`: whether the completed exit leg was maker.
- `markout_5s_bps`: signed 5-second post-fill markout from the strategy's side. Positive is favorable.
- `round_trip_volume_usd`: total counted notional across entry and exit for the completed round trip.
- `pnl_usd`: net PnL for the completed round trip under the research fee assumptions used by the producer.
- `policy_id`: exact shadow execution/exit policy version; never silently mix incompatible policy versions in one validation claim.
- `source`: producer identity, e.g. `shadow`, `paper`, or a versioned simulator identifier.

## Selector derivations

Within the observer rolling window:

- `execution_samples` = all finalized attempts, filled or not.
- `fill_rate` = filled attempts / all attempts.
- `maker_ratio` = maker legs / (2 × completed round trips).
- `markout_5s_bps` = mean available 5s markout across completed filled attempts.
- `volume_per_hour_usd` = completed round-trip volume / observed hours.
- `T10K` = 10,000 / volume per hour.
- `P10K` = total net PnL / total completed volume × 10,000.

If there are not enough empirical samples, or a required metric is unavailable, the eligibility engine returns WATCH/UNKNOWN rather than substituting a proxy.

## File transport

V0 transport is append-only JSONL. This is intentionally simple for a single-machine research bot.

Producer:

```python
from market_selector.feedback import ExecutionFeedbackEvent, append_feedback_event

append_feedback_event(path, event)
```

Consumer:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run \
  --coins VVV,PONS,ETHFI,PUMP,BTC \
  --feedback-jsonl data/hl_selector_feedback.jsonl
```

The tailer:

1. reads only newly appended complete lines;
2. does not consume a partial final line;
3. validates records;
4. skips malformed rows without crashing the market-data loop;
5. deduplicates `(source, attempt_id)`;
6. resets safely if the file is truncated/rotated.

## Fail-closed rules

Execution feedback does not override market-data health gates. A market is not eligible when the live feed is stale or hard-faulted even if historical rolling execution metrics looked good.

No feedback => execution metrics UNKNOWN => no meaningful QUALIFIED verdict.

No qualified market => supervisor IDLE.

## Policy-version discipline

The feedback bridge is infrastructure, not evidence that a policy works. The current VVV fixed-30s policy failed the unseen holdout, and state-aware exits on the now-SEEN dataset improved results only modestly while still failing the P10K and drawdown targets.

Before any new policy can be treated as validated:

1. develop/diagnose on SEEN data;
2. freeze exact `policy_id` and parameters;
3. collect a new untouched holdout;
4. evaluate against predeclared gates;
5. only then consider shadow-to-mainnet progression.

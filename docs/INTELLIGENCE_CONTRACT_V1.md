# Bitmomo Canonical Intelligence Contract V1

Status: approved integration boundary for Bitmomo AI, Market Regime, Bitmomo Pro, and later Watchtower consumers.

## Source of truth

The only current intelligence source is the validated Bitmomo AI preview exposed by `Bitmomo_AI_Intelligence`. A record is usable only when:

- the quality gate is `passed` or `degraded`;
- evaluation quality is `complete` or `degraded`;
- the source timestamp parses, is not more than five minutes in the future, and the reference price is positive;
- the consumer applies its own freshness limit and fails closed when the record is too old.

Theme JSON, samples, editor copy, and archived posts are never current intelligence sources.

## Stable shared fields

| Canonical field | Runtime source | Rule |
|---|---|---|
| `source_record_id` | validated preview timestamp | `bitmomo-ai:<unix_timestamp>` |
| `as_of` | validated preview timestamp | ISO-8601 UTC |
| `reference_price` | `data.close` | positive float |
| `directional_bias` | `evaluation.bias` | `bullish`, `neutral`, or `bearish` |
| `directional_confidence` | `evaluation.confidence` | integer 0–100 |
| `quality_status` | quality gate + evaluation quality | `complete`, `degraded`, or unavailable |
| `freshness_status` | consumer freshness policy | `fresh`, `delayed`, or `unavailable` |
| `axes` | `evaluation.axes` | read-only evidence; never editorially fabricated |
| `risk` | `evaluation.risk` | operational support/resistance and invalidation only |

Regime and directional bias are independent. Neither may be inferred from the other.

## Market Regime V1 normalization

The Regime adapter must produce every required `Bitmomo_Regime_Input` field from the same closed-candle snapshot used by Bitmomo AI.

| Regime input | Canonical derivation |
|---|---|
| `return_1d` | close-to-close return across 24 closed 1H candles |
| `return_7d` | close-to-close return across seven closed daily candles |
| `return_30d` | close-to-close return across 30 closed daily candles |
| `volatility_percentile` | current `volatility.atr_percentile_1d` |
| `volatility_change` | current percentile minus the comparable prior-day percentile |
| `volume_percentile` | latest closed daily volume percentile against the same trailing daily lookback |
| `momentum_score` | `evaluation.axes.direction.score`, clamped to -100..100 |
| `range_position_pct` | current close within the trailing 30-day high/low range, clamped to 0..100 |
| `structure_state` | current structure state normalized to the Regime vocabulary |
| `directional_bias` | canonical directional bias, passthrough only |
| `open_interest_change_pct` | `evaluation.axes.crowding.oi_change_24h_pct`, otherwise `null` |
| `funding_rate_pct` | raw funding rate multiplied by 100, otherwise `null` |
| `basis_pct` | `evaluation.axes.carry.basis_pct`, otherwise `null` |
| `liquidation_pressure` | `null` until a real liquidation feed exists |
| `crowding_score` | deterministic normalization of available positioning fields; `null` until that formula is versioned and tested |
| `directional_confidence` | canonical directional confidence |

Missing required values invalidate the whole Regime evaluation. They must never be replaced by zero, copied from a different time window, or estimated from support/resistance.

## Consumer boundaries

- Bitmomo AI produces validated market data and directional intelligence.
- Market Regime consumes the canonical record and produces append-only regime state.
- Watchtower may later consume ETF, macro, news, and event data; those fields do not enter Regime V1.
- Bitmomo Pro and homepage components consume intelligence outputs; they do not classify markets or alter source data.

## Versioning and traceability

Every persisted derived record must retain `source_record_id`, `as_of`, and the producing classifier/contract version. Any change to a derivation, threshold, or scoring rule requires a version bump. Historical records remain attributable to the version that created them and are never silently reinterpreted.

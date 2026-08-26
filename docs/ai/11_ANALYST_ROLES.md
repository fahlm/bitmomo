# Bitmomo BTC Intelligence — 11 Analyst Roles (V1 Design, Reconciled)

Status: design document, prepared locally, not committed/pushed.

## Reconciliation note (2026-08-26)

Repo access has been restored and PR #7's actual merged source has been read directly (`class-bitmomo-ai-binance.php`, `class-bitmomo-ai-signal-engine.php`). The live engine computes **one** weighted directional call from 5 fixed axes (direction 35%, carry 15%, structure 30%, crowding 20%, volatility as a confidence modifier rather than a directional vote) — it does not run 11 analysts, and there is no LLM in the loop anywhere in the plugin; it is pure deterministic PHP math. The 11 roles below remain a deliberate expansion, same as before, but each role's field list is now the **real, confirmed field names** from the live snapshot rather than placeholders — so this document can be handed directly to an implementer without a second source-reading pass.

## Common core (unchanged: 6 fields, every analyst gets this and nothing more)

```
generated_at, reference_price, primary_trend_state (1D),
data_quality_summary, 24h_high, 24h_low
```

All six of these map directly to existing snapshot fields: `timestamp` → `generated_at`; `close` → `reference_price`; `structure.state_1d` → `primary_trend_state`; `quality.status` + `quality.completeness_pct` → `data_quality_summary`; `outcome_window.high_24h` / `outcome_window.low_24h` → `24h_high` / `24h_low`. No new computation needed for the common core — it already exists.

## The 11 roles

### 1. Trend & Direction Analyst
*(maps to PR #7 axis: direction)*
- Gets (EXISTING/REUSE, live today): `direction.bias_1h`, `direction.bias_4h`, `direction.bias_1d`, `direction.adx`, `direction.plus_di`, `direction.minus_di`.
- Gets (EXISTING/NEEDS NORMALIZATION): 1h/4h/24h/7d % returns — only 24h (`crowding.price_change_24h_pct`) is computed today; 1h/4h/7d are trivial derivations from already-fetched OHLCV closes, no new API call.

### 2. Volatility Analyst
*(maps to PR #7 axis: volatility)*
- Gets (EXISTING/REUSE, live today): `volatility.atr_pct_1h`, `volatility.atr_pct_4h`, `volatility.atr_percentile_1d`, `volatility.bb_width_pct_4h`, `volatility.regime`.
- Nothing missing for this role — the live engine's volatility block is a direct, complete match to what this role needs.

### 3. Momentum Analyst
- Gets (EXISTING/NEEDS NORMALIZATION): no distinct "momentum" field exists today, but everything it needs is a pure computation over already-fetched OHLCV (rate-of-change across the same closes `direction`/`structure` already use) — **MISSING/ADD, near-zero cost**: no new endpoint, no new fetch, just an additional derived field computed alongside `direction`/`structure` in the same snapshot pass.
- This is the cleanest "genuinely missing but cheap" case among the 11 roles.

### 4. Market Structure Analyst
*(maps to PR #7 axis: structure)*
- Gets (EXISTING/REUSE, live today — richer than originally planned): `structure.last_swing_high/low`, `structure.state`, `structure.state_1d`, `structure.near_resistance/near_support`, plus the **operational support/resistance system** not in the original design: `operational_support_1h`, `operational_resistance_1h`, `resistance_status_1h`/`support_status_1h` (`confirmed_breakout`/`rejected_breakout`/`awaiting_confirmation`/`testing`/`untested`), `recent_high_1h`/`recent_low_1h`.
- This role is better-served by the live engine than the original design anticipated.

### 5. Derivatives Positioning Analyst
- Gets (EXISTING/REUSE, live today): `carry.funding_rate`, `carry.funding_7d` (see field-naming note in `DATA_ARCHITECTURE.md`), `crowding.oi_change_24h_pct`, `crowding.oi_zscore` (not in the original design — adopt it), `crowding.global_long_short_ratio` (not in the original design — adopt it). Includes Bybit fallback automatically.

### 6. Basis & Carry Analyst
*(maps to PR #7 axis: carry)*
- Gets (EXISTING/REUSE, live today): `carry.basis_pct`, `carry.funding_rate` (shared with #5 — small overlap remains intentional). `carry.data_status` reports which exchange actually served the data (`binance_public` vs `bybit_public` vs `unavailable`), which should be surfaced to this analyst so it can flag `data_quality_flag: degraded` honestly when running on the fallback source.

### 7. Crowding & Sentiment Analyst
*(maps to PR #7 axis: crowding)*
- Gets (EXISTING/REUSE, live today): `crowding.oi_change_24h_pct`, `crowding.global_long_short_ratio`, `crowding.taker_buy_sell_ratio`.
- Gets (**MISSING/ADD, unchanged from original design**): Fear & Greed Index — still not present anywhere in the live engine; original CoinGecko/alternative.me recommendation stands.

### 8. Spot vs Perp Flow Analyst
- **Real, confirmed gap — MISSING/ADD.** The live engine's `crowding.taker_buy_sell_ratio` is futures-only (`/futures/data/takerlongshortRatio`); spot klines are fetched only as a failover when the futures call errors, never in parallel. There is currently no way to actually compare spot vs perp flow because spot data isn't routinely present. Fix, per `DATA_ARCHITECTURE.md`: fetch spot klines in parallel every run (one additional free, unauthenticated Binance endpoint call) and compute the spot taker buy/sell ratio from the same kline field already used for perp. This is the one role whose core data genuinely does not exist yet, though the fix is cheap.

### 9. Cross-Market Context Analyst
- **Confirmed, unchanged gap — MISSING/ADD.** Nothing in the live engine touches BTC dominance, total market cap, or any broader-market context. Original CoinGecko-based recommendation stands as-is.
- **Data-quality note (unchanged)**: this role runs on a deliberately thin dataset for V1 (DXY/equities still deferred) — its `data_quality_flag` should reflect that honestly.

### 10. Historical Pattern / Regime Analyst
- Gets (EXISTING/REUSE, partial — better than originally assumed): `volatility.regime` (`extreme`/`high`/`normal`/`low`, from a 252-day ATR percentile) is a live, already-computed coarse regime label this role can use immediately, even before any historical snapshot store exists.
- Gets (**MISSING/ADD, unchanged**): the deeper "has BTC been in a similar regime before, and how did it typically resolve" analysis still depends on Phase 7 snapshot history accumulating over time (see `VALIDATION_PLAN.md`) — `class-bitmomo-ai-performance.php` only tracks the outcome of the single published signal, not a replayable per-run input/output snapshot, so this remains a real (not duplicated) gap. For the first weeks of real operation this role should lean on `volatility.regime` alone and say plainly that deeper pattern history isn't available yet.

### 11. Event & Narrative Context Analyst
- **Confirmed, unchanged gap — MISSING/ADD.** No calendar or event data anywhere in the live engine. Original manually-curated `event-calendar.json` recommendation stands, unrelated to anything PR #7 built.

## Explicit non-goals (unchanged)

- No analyst duplicates another's primary signal as its *main* focus (funding-rate overlap between #5/#6/#7 remains intentional and small).
- No analyst is given the full input package — core + named slice only.
- No analyst performs its own data fetch or web research — everything is pre-fetched into the one shared package (now: mostly the existing `snapshot()` output, extended per `DATA_ARCHITECTURE.md`) before any analyst runs.

## Summary: real gaps for the 5 flagged roles

| Role | Verdict |
|---|---|
| Momentum | Cheap add — pure computation over data already fetched, no new endpoint |
| Spot vs Perp Flow | Real gap — needs one parallel spot-klines fetch (cheap, but a genuine addition) |
| Cross-Market Context | Real gap, unchanged — needs CoinGecko + alternative.me as originally planned |
| Historical Pattern / Regime | Partially served today (`volatility.regime`); the deeper pattern-history part still needs Phase 7 accumulation |
| Event & Narrative | Real gap, unchanged — needs the manually-curated calendar file as originally planned |

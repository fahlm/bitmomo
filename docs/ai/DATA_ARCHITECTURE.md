# Bitmomo BTC Intelligence — Data Architecture (V1 Design, Reconciled)

Status: design document, prepared locally, not committed/pushed. No code, no theme files, no PR #7 changes.

## Reconciliation note (2026-08-26)

The previous version of this document was written while GitHub API access was lost, so PR #7's actual source could not be read — everything about it was marked "design assumption, not source-verified." Repo access has since been restored. This version replaces every assumption below with a direct read of the real, merged source: `website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-binance.php`, `class-bitmomo-ai-signal-engine.php`, `class-bitmomo-ai-quality-gate.php`, `class-bitmomo-ai-content-types.php`, `class-bitmomo-ai-performance.php`, and the plugin `README.md` (Phases AI-1 through AI-2i), all read directly from `origin/main` at commit `adea0a5`.

**PR #7 is no longer a draft.** It merged into `main` on 2026-08-26 (`0d406ca`), and a further PR #17 ("conditional Bitmomo AI auto-publish") merged on top of it the same day. The plugin is live in the git history. Whether it has been deployed to the production Hostinger site is a separate question this document does not answer — deployment status is Codex's territory, not checked here.

## Phase 1 — Data inventory (verified against real source)

### What the existing Bitmomo market engine actually does

It is a **deterministic, rule-based market-data + scoring engine** — not an LLM, not 11 analysts, not a consensus layer. One PHP class (`Bitmomo_AI_Binance::snapshot()`) fetches everything in a single call and returns one structured array; a second class (`Bitmomo_AI_Signal_Engine::evaluate()`) turns that array into one weighted directional call (bias/confidence/score) using fixed, hand-picked axis weights. This runs once daily (WordPress cron target 19:10 WIB) and, if a 7-check quality gate passes 6/7 with no hard-blocker failures, can auto-publish one `bm_btc_signal` post.

This is the system the 11-analyst architecture is meant to sit **on top of**, not replace or duplicate.

### Confirmed live data sources

- **Binance USD-M futures** (`fapi.binance.com`, public, no key): `/fapi/v1/klines` (1H/4H/1D OHLCV), `/fapi/v1/fundingRate` (last 21 funding prints), `/fapi/v1/premiumIndex` (mark/index price), `/futures/data/openInterestHist` (30-point OI history), `/futures/data/globalLongShortAccountRatio`, `/futures/data/takerlongshortRatio`.
- **Binance spot** (`data-api.binance.vision`, public, no key): used only as a fallback for OHLCV if the futures klines call fails — not fetched in parallel, not a standing second source.
- **Bybit linear perpetuals** (`api.bybit.com`, public, no key): **this fallback exists and is real** — confirmed in `bybit_derivatives()`, covering funding history, mark/index price (via `/v5/market/tickers`), open interest, and account long/short ratio. It only activates when the corresponding Binance derivatives call fails (`is_wp_error`); `carry.data_status` and the snapshot's `quality.source` string record which source actually served the data (`Binance USD-M` vs `Bybit linear perpetual fallback`). It was missed in the prior version of this document because it lives inside `class-bitmomo-ai-binance.php` as a private method, not as a separate file — a file-list scan alone would not have found it.
- **Freshness/staleness handling**: every external call is cached via WordPress transients (5-minute primary cache, 6-hour stale-fallback cache); on a hard failure after 2 retries, the 6-hour stale value is served rather than failing the whole snapshot. `quality.data_age_minutes`, `quality.completeness_pct`, and `quality.optional_missing[]` are computed and returned every run.

### Confirmed NOT present (real gaps, not assumptions)

- No cross-market data: no BTC dominance, no total market cap, no Fear & Greed Index, no DXY.
- No event/macro calendar.
- No liquidation data (websocket-only on both Binance and Bybit; never fetched).
- No true parallel spot-vs-perp comparison — spot is a failover path, not a standing second stream.
- No 11-way or multi-analyst decomposition anywhere — one deterministic score, one signal post.
- No historical snapshot store of the analyst-input-package shape — `class-bitmomo-ai-performance.php` tracks outcomes for the single published signal only (see Phase 7 reconciliation in `VALIDATION_PLAN.md`), not a replayable input+output snapshot per run.

## Phase 2 — V1 dataset design, reconciled field-by-field

Classification key: **EXISTING/REUSE** (real field, already computed live, take it as-is) · **EXISTING/NEEDS NORMALIZATION** (the data exists but under a different shape/name/window than originally proposed — a mapping, not new engineering) · **MISSING/ADD** (confirmed absent, real work to add, cost is stated) · **DEFER** (unchanged from the original design — still correctly out of scope for V1).

### PRICE / STRUCTURE

| Proposed field | Status | Source of truth |
|---|---|---|
| OHLCV 1H/4H/1D | EXISTING/REUSE | `Bitmomo_AI_Binance::snapshot()` fetches all three via futures klines (spot fallback on failure) |
| ATR(14) per timeframe | EXISTING/NEEDS NORMALIZATION | Computed today as `atr_pct_1h` / `atr_pct_4h` (a **percentage of price**, not an absolute value). The schema's `atr14` field should store the percentage form directly rather than re-deriving an absolute figure — simpler, and matches what's already computed. |
| Realized volatility | EXISTING/REUSE (partial) | `bb_width_pct_4h` (Bollinger Band width %) and `atr_percentile_1d` (252-day range percentile) already cover this; a classic stdev-of-log-returns is not separately computed but is redundant with what exists — recommend dropping the originally-planned separate realized-vol calc and using the existing pair instead. |
| Volatility regime | EXISTING/REUSE — **not in the original design at all** | `volatility.regime` (`extreme`/`high`/`normal`/`low`, from the 252-day ATR percentile) is a free addition; feeds the Volatility Analyst directly and gives the Historical Pattern/Regime Analyst a coarse, already-computed regime label (see Phase 3 note below). |
| Trend state (EMA20/50 alignment) | EXISTING/NEEDS NORMALIZATION | Two different existing concepts cover this, and they should **not** be merged into one generic `trend_state` enum as originally drafted: (1) `direction.bias_1h/4h/1d` — ADX/DI-based, `bullish`/`neutral`/`bearish`; (2) `structure.state` / `state_1d` — EMA20-vs-50 + breakout based, `breakout_up`/`breakout_down`/`hh_hl`/`lh_ll`/`range`. Collapsing these into one field (as `ANALYST_INPUT_SCHEMA.json` currently does) would be a duplication the original design didn't know it was creating — see "Duplication removed" below. |
| Structural levels (swing high/low) | EXISTING/REUSE — **richer than proposed** | `structure.last_swing_high/low` (20-candle lookback) plus a full **operational support/resistance system** not in the original design: `operational_support_1h`/`operational_resistance_1h` (confirmed-pivot + ATR-guarded), `resistance_status_1h`/`support_status_1h` (`confirmed_breakout`/`rejected_breakout`/`awaiting_confirmation`/`testing`/`untested`), `recent_high_1h`/`recent_low_1h`. This is materially more useful than the originally-planned bare swing-high/low and should be adopted as-is. |
| Returns (1h/4h/24h/7d %) | EXISTING/NEEDS NORMALIZATION | `crowding.price_change_24h_pct` exists for 24h only; 1h/4h/7d are not separately computed but are trivially derivable from the already-fetched OHLCV closes (no new API call — pure computation, near-zero cost). |

### DERIVATIVES

| Proposed field | Status | Source of truth |
|---|---|---|
| Funding rate (current + history) | EXISTING/REUSE | `carry.funding_rate` (latest) computed from 21-print history; Bybit fallback included |
| Funding rate rolling average | EXISTING/NEEDS NORMALIZATION | Existing `funding_7d` is actually the mean of the **last 21 8-hour prints** (≈7 days), not a distinct "8h avg" as `ANALYST_INPUT_SCHEMA.json` currently names it — rename the schema field to match reality rather than implying two separate windows exist. |
| Open interest + 24h change | EXISTING/REUSE | `crowding.oi_change_24h_pct`, computed from `open-interest-hist`, Bybit fallback included |
| Open interest z-score | EXISTING/REUSE — **not in the original design** | `crowding.oi_zscore` — a genuinely useful statistical field the original schema didn't ask for; adopt it. |
| Basis (perp − index) | EXISTING/REUSE | `carry.basis_pct`, exact match to the original design |
| Global long/short account ratio | EXISTING/REUSE — **not in the original design** | `crowding.global_long_short_ratio`, Bybit fallback included; strengthens the Derivatives Positioning and Crowding & Sentiment analysts beyond what was originally planned |
| Liquidation data | DEFER — unchanged | Still websocket-only on Binance; confirmed Bybit also has no reliable free historical REST liquidation endpoint. No change to the original recommendation. |

### FLOW

| Proposed field | Status | Source of truth |
|---|---|---|
| Perp volume, perp taker buy/sell ratio | EXISTING/REUSE | `crowding.taker_buy_sell_ratio`, from `/futures/data/takerlongshortRatio` |
| Spot volume (standing, not fallback-only) | **MISSING/ADD** | Spot klines are only fetched when the futures call errors — under normal operation spot data never reaches the snapshot at all. A true spot-vs-perp comparison needs spot klines fetched **in parallel**, every run, not as a failover. Cost: one additional public, unauthenticated Binance spot klines call per run (`/api/v3/klines`) — same free tier, same pattern already in use, low engineering cost. |
| Spot taker buy/sell ratio | **MISSING/ADD** | Depends on the spot-klines-in-parallel addition above (`taker buy base asset volume` is already a field in the standard kline response, so once spot klines are fetched in parallel this is pure computation, no extra endpoint). |

### CROSS MARKET

| Proposed field | Status | Source of truth |
|---|---|---|
| BTC dominance, total market cap | **MISSING/ADD — unchanged** | Confirmed: no CoinGecko or any market-cap source anywhere in the plugin. Original recommendation (CoinGecko free global-market endpoint) still stands. |
| Fear & Greed Index | **MISSING/ADD — unchanged** | Confirmed absent. Original recommendation (alternative.me) still stands. |
| DXY / equities futures | DEFER — unchanged | Still no reliable free source. No change. |

### EVENT CONTEXT

| Proposed field | Status | Source of truth |
|---|---|---|
| Macro/crypto event calendar | **MISSING/ADD — unchanged** | Confirmed nothing exists. Original recommendation (manually-curated `event-calendar.json`) still stands as the lowest-cost option. |

## Duplication removed from the prior design

1. **Single `trend_state` enum, collapsed from two real signals.** The original `ANALYST_INPUT_SCHEMA.json` `timeframe_block.trend_state` (`uptrend`/`downtrend`/`range`) tried to represent what the live engine actually splits into two independent, differently-computed signals (ADX-based `direction.bias` and EMA/breakout-based `structure.state`). Keeping them separate is more accurate and avoids inventing a translation layer that would throw away information the engine already computes for free.
2. **A separate "realized volatility" calculation that duplicates existing fields.** The original design proposed computing stdev-of-log-returns independently; the live engine's `atr_percentile_1d` + `bb_width_pct_4h` pair already characterizes realized volatility for the Volatility Analyst's purposes. Adding a third, differently-computed volatility figure would be redundant, not additive.
3. **A brand-new spot-data fetch where a fallback path already exists.** The spot klines call already exists in code (`market_klines()`); the fix for Flow's missing spot data is to call it in parallel rather than write a second spot adapter from scratch.

## Phase 8 — Cost control principles (unchanged, now grounded in a real example)

The live engine already demonstrates every principle this section originally argued for in the abstract: one fetch per run (`snapshot()` called once), transient caching (5 min primary / 6h stale-fallback) so repeated reads within a cycle cost nothing extra, and all derived metrics (ATR%, ADX, z-score, structure state) computed once and reused. The 11-analyst layer should follow the same pattern: **the existing `snapshot()` output becomes (most of) Phase 4's analyst input package**, extended only with the confirmed-missing pieces above (parallel spot fetch, cross-market, event calendar) — not rebuilt from zero.

See `11_ANALYST_ROLES.md` for the reconciled per-role field slices, `CONSENSUS_DESIGN.md` for how the existing deterministic engine relates to the new consensus layer, and `VALIDATION_PLAN.md` for how the existing `class-bitmomo-ai-performance.php` outcome tracker relates to per-analyst validation.

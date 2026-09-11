# BTC Mode Research — Source Coverage Audit

Status: INITIAL AUDIT
Date: 2026-09-11

Purpose: identify which production inputs can be replayed historically before building the BTC Mode research dataset.

## Existing Bitmomo BTC Core live inputs

`Bitmomo_AI_Binance::snapshot()` currently uses:

- Binance USD-M 1H / 4H / 1D klines
- funding-rate history (`/fapi/v1/fundingRate`)
- current premium index (`/fapi/v1/premiumIndex`)
- open-interest statistics (`/futures/data/openInterestHist`)
- global long/short account ratio (`/futures/data/globalLongShortAccountRatio`)
- taker buy/sell ratio (`/futures/data/takerlongshortRatio`)
- Bybit linear-perpetual fallback for selected derivatives inputs when the primary Binance calls fail

The current live snapshot is intentionally shallow: 120 x 1H candles, 320 x 4H candles, 320 x 1D candles, 21 funding rows, and 30 x 1H rows for OI / global long-short / taker data. These live request limits are not a historical research pipeline.

## Critical historical-coverage finding

Official Binance USD-M documentation currently states:

- Open Interest Statistics (`/futures/data/openInterestHist`): only the latest 1 month is available.
- Global Long/Short Account Ratio (`/futures/data/globalLongShortAccountRatio`): only the latest 30 days is available.
- Taker Buy/Sell Volume (`/futures/data/takerlongshortRatio`): only the latest 30 days is available.
- Historical futures basis endpoint (`/futures/data/basis`): only the latest 30 days is available.
- Funding Rate History (`/fapi/v1/fundingRate`) supports `startTime`, `endTime`, and pagination up to 1000 rows per request; the documentation does not state the same 30-day-retention restriction on that endpoint.

Official reference:
https://developers.binance.com/en/docs/catalog/core-trading-derivatives-trading-usd-s-m-futures/api/rest-api/market-data

## Consequence for P0.2B

A 2021–2026 research replay cannot assume that the current public Binance REST endpoints can backfill every live BTC Core derivatives feature.

Therefore the research pipeline must separate features into coverage tiers rather than filling missing history with neutral/zero values.

### Tier A — likely long-history / first research baseline

- BTC OHLCV-derived Direction
- BTC OHLCV-derived Structure
- BTC OHLCV-derived Volatility
- regime metrics reproducible from historical candles
- funding rate where historical pagination is verified successfully
- Bond/Treasury features already backfilled by the Bond service, subject to knowledge-time rules

### Tier B — historical source required before long-window use

- open interest history beyond ~1 month
- global long/short ratio beyond ~30 days
- taker buy/sell ratio beyond ~30 days
- historical basis beyond ~30 days

Tier B requires one of:

1. an already-owned historical archive,
2. a reliable external historical dataset/vendor,
3. an official downloadable archive if verified,
4. or exclusion from the long-history model while retaining the fields for forward/live validation.

## Research rule

Do **not** shorten the whole research sample to 30 days merely to preserve every live feature. First establish a long-history baseline using features that can be reproduced honestly, then test shorter-history derivatives variables as incremental research where adequate sample size exists.

This prevents two bad outcomes:

- fabricating multi-year derivatives history that the source cannot provide;
- throwing away years of BTC/regime/Bond evidence just to keep one short-history variable.

## Next verification tasks

- Verify the practical earliest retrievable BTCUSDT funding timestamp through paginated requests.
- Confirm historical kline acquisition path and consistent futures-vs-spot choice for replay.
- Inventory any already-stored Bitmomo canonical observations that can supplement raw-source history.
- Inventory Bond Intelligence historical rows, knowledge-time status, and exact session alignment capability.
- Determine whether a trustworthy historical source for OI / positioning / taker / basis is already available before evaluating paid data acquisition.

No new data vendor should be purchased before these inventories are complete.

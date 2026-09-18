# Binance archive golden fixtures

Tiny verbatim excerpts of official Binance public archive files
(`https://data.binance.vision/data/...`), kept to pin parser behavior on real bytes.

| Fixture | Source file | Excerpt | Why |
|---|---|---|---|
| `spot-BTCUSDT-5m-2024-12-tail.csv` | `spot/monthly/klines/BTCUSDT/5m/BTCUSDT-5m-2024-12.zip` (sha256 `0a2f7074…1301`) | last 2 rows | millisecond timestamps, no header |
| `spot-BTCUSDT-5m-2025-01-head.csv` | `spot/monthly/klines/BTCUSDT/5m/BTCUSDT-5m-2025-01.zip` (sha256 `4ed2edba…f983`) | first 2 rows | **microsecond** timestamps, no header |
| `um-BTCUSDT-5m-2026-08-head.csv` | `futures/um/monthly/klines/BTCUSDT/5m/BTCUSDT-5m-2026-08.zip` (sha256 `14fd408e…67d1`) | header + 2 rows | USD-M files carry a header |
| `um-BTCUSDT-metrics-2020-09-01-head.csv` | `futures/um/daily/metrics/BTCUSDT/BTCUSDT-metrics-2020-09-01.zip` | header + 3 rows | string `create_time`; exact duplicate rows |
| `um-BTCUSDT-fundingRate-2026-08-head.csv` | `futures/um/monthly/fundingRate/BTCUSDT/BTCUSDT-fundingRate-2026-08.zip` (sha256 `7c108b3a…a964`) | header + 2 rows | `calc_time` with +1 ms jitter |

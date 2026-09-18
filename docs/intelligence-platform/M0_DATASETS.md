# M0 — Rebuilt Datasets (register)

Status: M0 — reproducible, research only
Built at code commit `7ccbd21` (clean tree), Python 3.12.11, pyarrow 21.0.0.
Manifests: `research/lab/manifests/*.json` (committed). Data: `research/lab/.data/` (gitignored).

All raw inputs come from `https://data.binance.vision/data/…`, and each file was accepted only
after its SHA-256 matched Binance's published `.CHECKSUM`. P0.2C numbers were used as
verification targets only. Nothing in the code references them.

| Dataset | Window (UTC, inclusive) | Raw files | Rows | Missing grid intervals | Content SHA-256 |
|---|---|---:|---:|---:|---|
| `binance_um_klines_5m` | 2020-01-01 → 2026-09-10 | 90 | **704,160** | **0** | `025cfa10c1378fcb5fb1e9ac58898c8b13c842ffedb362058f15aab723253407` |
| `binance_spot_klines_5m` | 2020-01-01 → 2026-09-10 | 90 | **703,698** (8 `boundary_mismatch`) | 470 (= 462 absent + 8 truncated) | `5c0867dbd83e723a92d0ec3d25ecee8c6b81447bd323b0e80ba9fdcbb237b25b` |
| `binance_um_mark_price_klines_5m` | 2020-01-01 → 2026-09-10 | 90 | 701,554 | 2,606 | `25dd8d3426b82418ea81ffcf0b798ea1c858f84132e376577cb93c258259ad88` |
| `binance_um_index_price_klines_5m` | 2020-01-01 → 2026-09-10 | 90 | 700,691 | 3,469 | `60501fa2fc8d9513e7c91b815a33d25a0fbc50ecd88a5622c38619cfcb2c600c` |
| `binance_um_premium_index_klines_5m` | 2020-01-01 → 2026-09-10 | 90 | 701,822 | 2,338 | `c4b28096f3ad642f14be4ffe78695baf78b5caa262eddf873f623f71fa4fc13c` |
| `binance_um_funding_rate` | 2020-01-01 → 2026-09-10 | 80 (2026-09 not yet archived) | 7,305 events | n/a (8h spacing throughout; data ends 2026-08-31 16:00) | `378be873401ddd4423847de1a3b73aa375497da33e282b8530401a24aa769ee9` |
| `binance_um_metrics_5m` | 2020-09-01 → 2026-09-10 (archive starts 2020-09-01) | 2,201 | 633,254 (75,255 exact duplicates collapsed) | 634 | `ff79af00b85619bf9fbaf93aa1318829e7a5a9f6b7990238348d36f592485063` |

Gap lists, per-year coverage, quality counts and every raw file's URL + SHA-256 are in the
manifests. Interpretation and caveats are in [M0_SOURCE_COVERAGE.md](M0_SOURCE_COVERAGE.md).

## Reproduce from nothing

```bash
cd research/lab
uv sync --frozen --extra dev
rm -rf .data
for ds in binance_um_klines_5m binance_spot_klines_5m binance_um_funding_rate \
          binance_um_mark_price_klines_5m binance_um_index_price_klines_5m \
          binance_um_premium_index_klines_5m; do
  .venv/bin/bitmomo-lab build --dataset $ds --start 2020-01-01 --end 2026-09-10
done
.venv/bin/bitmomo-lab build --dataset binance_um_metrics_5m --start 2020-09-01 --end 2026-09-10
git diff --stat manifests/      # expected: no changes
for m in manifests/*.json; do .venv/bin/bitmomo-lab verify --manifest $m; done
```

Measured on the authoring laptop (Apple Silicon): about 22 s per 5m kline dataset including
download, 3 min for metrics (2,201 daily files), and 88 s for a full rebuild of all seven from
the verified raw cache.

## Determinism evidence

* All seven datasets were built twice (first build at `5f612d4`, rebuilt from the raw cache at
  `b3eb9f3`). Content hashes were **identical** for all seven.
* After the metrics normalization change at `7ccbd21` (sentinel nulling, new column), the
  six non-metrics datasets rebuilt to **identical** hashes again. Only metrics changed, as
  intended.
* `tests/test_build.py` asserts byte-identical manifests for two independent builds of the same
  raw bytes.

## August 2026 (P0.2B window) reconstruction path

The inputs P0.2B used can be rebuilt from declared sources for 2026-08-02 → 2026-09-01:

| P0.2B input | Rebuildable from | Coverage in Aug 2026 |
|---|---|---|
| 5m USD-M / Spot candles, taker volume | klines datasets above | complete |
| Mark / index (basis approximation) | mark/index klines | complete (only 2026-06-29 missing in 2026) |
| Funding | funding archive | complete |
| OI, L/S, taker "5-minute futures metrics" | `metrics` | present. August 2026 is in the "stamp = period start" regime (2026-04 onward), so `create_time` must not be treated as knowledge time (M0_SOURCE_COVERAGE §4.1) |
| aggTrades (Spot + USD-M) | `data.binance.vision` aggTrades (not yet ingested) | to be added only if Wave 1.5 needs it |
| 1H/4H/1D candles for Signal Engine | derivable from 5m klines (resampling to be defined and tested in Wave 1.5) | complete |

If the old `canonical_hourly_intraday_replay.csv` / `tactical_15m_exploratory.csv` are
supplied, they will be registered as `historical_reference / parity_oracle`, never as
`canonical_source`.

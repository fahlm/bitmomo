# Forward Derivatives Recorder — Runbook

Status: **RESEARCH-READY / NOT PRODUCTION-VALIDATED**. Research branch only; **not deployed anywhere.**
A live capture + replay from a legitimately connected host is a required acceptance step before any persistent deployment.
Code: `research/lab/bitmomo_lab/recorder/`
Scheduler example (not installed): `research/lab/ops/recorder.cron.example`

## Purpose

Several Binance USD-M derivatives series that production consumes have only ~30 days
of REST history (see [M0_SOURCE_COVERAGE.md](M0_SOURCE_COVERAGE.md)). Every day they are
not recorded becomes a permanent gap. The recorder builds an append-only point-in-time
archive of those series. It is an archive-building capability, not an intelligence engine.

## Boundaries (enforced in code where possible)

| Boundary | Enforcement |
|---|---|
| Public, unauthenticated GET only | `build_url` rejects `signature`, `apiKey`, `timestamp`, `recvWindow` params; no auth headers exist in the code |
| No trading/execution endpoints | exact path allowlist (`ALLOWED_PATHS`); e.g. `/fapi/v1/order` is refused (tested) |
| No secrets | none are read; nothing in `.env` is used |
| No scheduling/daemon | the recorder runs once per invocation and exits |
| No WordPress / staging / production writes | writes only under `research/lab/.data/` |
| No deployment | the cron file is an example; installing it anywhere needs Fahmi's explicit approval |

## What is recorded

`bitmomo-lab record` performs one pass over these endpoints (symbol `BTCUSDT`):

| Recorder dataset (`binance_um_rest_*`) | Endpoint | Period(s) | Why |
|---|---|---|---|
| `open_interest_hist_{5m,1h}` | `/futures/data/openInterestHist` | 5m, 1h | production: `sumOpenInterestValue`, period 1h, limit 30 |
| `global_long_short_account_ratio_{5m,1h}` | `/futures/data/globalLongShortAccountRatio` | 5m, 1h | production: last `longShortRatio`, period 1h |
| `taker_long_short_ratio_{5m,1h}` | `/futures/data/takerlongshortRatio` | 5m, 1h | production: last `buySellRatio`, period 1h; `buyVol`/`sellVol` allow exact re-aggregation |
| `top_long_short_account_ratio_{5m,1h}` | `/futures/data/topLongShortAccountRatio` | 5m, 1h | tests metrics-archive field equivalence |
| `top_long_short_position_ratio_{5m,1h}` | `/futures/data/topLongShortPositionRatio` | 5m, 1h | tests metrics-archive field equivalence |
| `basis_{5m,1h}` | `/futures/data/basis` (PERPETUAL) | 5m, 1h | REST basis history is ~30d |
| `premium_index` | `/fapi/v1/premiumIndex` | snapshot | production basis = (mark − index) / index |
| `open_interest` | `/fapi/v1/openInterest` | snapshot | instantaneous OI |
| `funding_rate` | `/fapi/v1/fundingRate` (limit 21) | events | production carry input |

Both 5m and 1h are recorded on purpose: whether a 1h value equals the 5m value at the
hour boundary is an open semantic question (see M0_SOURCE_COVERAGE.md §4).

## Point-in-time rules

* `available_at = received_at`: a value is never available before our process saw it.
* `event_time` = the exchange's `timestamp` / `time` / `fundingTime`, normalized to µs.
* Under the conservative assumption that a history `timestamp` marks the **start** of its
  period, a row whose period had not finished at receipt is stored with
  `quality = period_incomplete`. The PIT loader does not serve it by default. The next
  pass that sees the finished period stores a new version (same key, later `available_at`).
* A changed value for an already-recorded key is appended as a **revision**. It is never
  overwritten. `PITFrame.as_of(T)` serves the latest version known at `T`.
* An identical re-observation is skipped (idempotent).

## Storage layout (`research/lab/.data/`, gitignored)

```
raw/recorder/<dataset>/<symbol>/<YYYY-MM-DD>/<received_at_us>-<sha12>.json.gz
    envelope: request_url, received_at, http_status, body_sha256, verdict, verbatim body
normalized/recorder/<dataset>/<symbol>/date=<YYYY-MM-DD>/part-<received_at_us>-<sha12>.parquet
    canonical observation fields + received_at, request_url, raw_response_sha256 + values
```

Raw envelopes are kept for accepted, rejected and HTTP-error responses. A malformed
payload (missing key, non-numeric/non-finite value, wrong container, duplicate timestamps
in one response, empty list) is **rejected**: its raw envelope is kept and no
normalized rows are written.

## Operating it

Setup (once, user-local, no system changes):

```bash
cd research/lab
uv sync --frozen --extra dev        # Python 3.12 venv from uv.lock
```

One manual pass:

```bash
.venv/bin/bitmomo-lab record
```

The exit code is non-zero if any endpoint returned an HTTP error or was rejected. Each
endpoint prints one JSON outcome line: `stored`, `unchanged`, `rejected` or `http_error`,
plus row counts (new / revised / duplicate).

Check for missing periods:

```bash
.venv/bin/bitmomo-lab recorder-gaps --endpoint taker_long_short_ratio_5m \
  --start 2026-09-18T00:00:00Z --end 2026-09-19T00:00:00Z
```

Capture real fixtures, which replace the synthetic ones used by the tests:

```bash
.venv/bin/bitmomo-lab --data-dir /tmp/lab-capture record --capture-fixtures fixtures/recorder
```

## Known limitation on the authoring machine

On the machine where M0 was written, `fapi.binance.com` resolves to `103.123.248.32`, which
looks like a DNS/ISP block page, and connections fail. The recorder has therefore **not yet
made a live call**. All recorder tests run on fixtures in `research/lab/fixtures/recorder/`
that are labeled **synthetic** (built from the documented response shapes). Before relying on
the recorder:

1. run `record --capture-fixtures` once from a host with legitimate access;
2. commit the captured fixtures and re-run `pytest`;
3. compare the captured field sets with the parsers in `recorder/endpoints.py`.

The block must not be circumvented from this machine.

## Retention and failure handling

* Missed runs: the 5m endpoints return 2.5h of history per call and the 1h endpoints ~29h,
  so short outages self-heal. Longer outages are permanent gaps. Detect them with
  `recorder-gaps` and record them in the handoff. Never back-fill them with a different
  source under the same dataset name.
* Disk: each pass stores ~15 small gzip envelopes plus small Parquet parts. Compaction is
  deferred until volumes justify it (M1).

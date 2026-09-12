# Hyperliquid Referral-Volume Research — Data Manifest

Raw market datasets are intentionally not committed to Git. This file records the local artifacts referenced by the research.

## Historical/candle datasets

### `data/btc_1m_60d.csv` / `data/btc_5m_60d.csv`

- Purpose: initial structure research/development
- Venue/source: Hyperliquid mainnet read-only candles for earlier prototypes, later Binance archive for longer history where needed
- Status: SEEN / development

### `data/holdout_btc_1m_60d.csv`
### `data/holdout_btc_5m_60d.csv`

- Purpose: first unseen V3 structure holdout
- Approx coverage: prior 60-day window ending roughly mid-July 2026
- Result: V3 continuation FAIL
- Status: SEEN after one evaluation

### `data/holdout2_btc_1m_60d.csv`
### `data/holdout2_btc_5m_60d.csv`

- Purpose: second unseen holdout for frozen 15m fade hypothesis
- Approx coverage: roughly mid-March to mid-May 2026
- Result: fade V1 FAIL
- Status: SEEN after one evaluation

### `data/orderflow_btc_2026_q1.csv`

- Purpose: candle-level taker-flow/relative-volume research
- Coverage: 2026-01-01 through 2026-03-31
- Research split: Jan-Feb 2026
- Validation split: Mar 2026
- Result: no robust fee-covering directional edge
- Status: SEEN

## Hyperliquid BTC microstructure datasets

### `data/hl_microstructure.csv`

- Purpose: initial 5-minute recorder sanity check
- Rows observed: 299 including data rows/output sanity
- Status: SEEN / plumbing only

### `data/hl_microstructure_120m.csv`

- Purpose: first real Hyperliquid 1-second microstructure/markout research
- Duration target: 120 minutes
- Usable rows recorded: 7,155
- Features: BBO, spread, L1/L3/L5 depth, imbalance, microprice, 1s aggressor flow, trade count
- Result: strong monotonic alignment-score relationship with 5s/10s markout
- Status: SEEN

### `data/hl_exec_10m_books.csv`

- Purpose: event-level BTC L2 execution/queue study
- Book rows: 1,103 including header / 1,102 updates
- Status: SEEN

### `data/hl_exec_10m_trades.csv`

- Purpose: event-level BTC trade stream
- Trade rows: 421 including header / 420 trades
- Status: SEEN

## Multi-market Hyperliquid event dataset

### `data/hl_multi_30m_books.csv`

- Purpose: PONS/VVV/ETHFI/PUMP/BTC execution comparison
- Duration: ~30 minutes
- Total rows: 16,486 including header
- Book updates per market: 3,297
- Status: SEEN / screening

### `data/hl_multi_30m_trades.csv`

- Purpose: same multi-market screen
- Total rows: 5,200 including header
- Individual trades:
  - PONS: 1,372
  - VVV: 517
  - ETHFI: 632
  - PUMP: 732
  - BTC: 1,946
- Status: SEEN / screening

## VVV holdout acquisition artifacts

### `data/vvv_partial_stale_books.csv`
### `data/vvv_partial_stale_trades.csv`

- Origin: first attempted 8-hour VVV unseen holdout capture
- Failure mode: websocket feed stopped updating while outer Python loop remained alive
- Last frozen counters before interruption:
  - books: 11,962 updates (11,963 lines including header)
  - trades: 2,641 trades (2,642 lines including header)
- Strategy evaluator deliberately NOT run on this partial dataset
- Status: QUARANTINED / acquisition-failure evidence, not validation evidence

### `data/vvv_watchdog_test_books.csv`
### `data/vvv_watchdog_test_trades.csv`
### `data/vvv_watchdog_test_health.log`

- Purpose: 5-minute operational test of resilient recorder
- Observed during test: book/trade counters increased normally, then watchdog emitted `STALE book_age=30.1s` near test end
- Status: OPERATIONAL TEST ONLY
- Next requirement: confirm reconnect -> resumed writes -> FINISH, and likely relax final health policy to a less aggressive dual-feed/60s stale rule before definitive holdout

### `data/vvv_holdout_8h_books.csv`
### `data/vvv_holdout_8h_trades.csv`

- Reserved for: clean definitive unseen VVV holdout after recorder health behavior is verified
- Status: NOT YET VALID
- Rule: do not run `hl_vvv_holdout_eval` on partial/incomplete capture

## Current local script inventory referenced by the research

Scripts were developed in the local `~/bitmomo-hl-volume-bot` working directory and are not yet canonical repository code. Names referenced in experiment history include:

- `bot/structure_research.py`
- `bot/entry_research.py`
- `bot/exit_tournament.py`
- `bot/download_history.py`
- `bot/walkforward_research.py`
- `bot/v2_diagnostic.py`
- `bot/v3_holdout.py`
- `bot/v3_horizon_diagnostic.py`
- `bot/download_holdout.py`
- `bot/download_holdout2.py`
- `bot/fade_holdout2.py`
- `bot/orderflow_data.py`
- `bot/orderflow_research.py`
- `bot/hl_microstructure_recorder.py`
- `bot/hl_markout_research.py`
- `bot/hl_volume_challenge_sim.py`
- `bot/hl_execution_recorder.py`
- `bot/hl_queue_research_v2.py`
- `bot/hl_market_scanner.py`
- `bot/hl_multi_execution_recorder.py`
- `bot/hl_multi_roundtrip_screen.py`
- `bot/hl_inside_spread_screen.py`
- `bot/hl_candidate_screen_v2.py`
- `bot/hl_vvv_holdout_eval.py`
- `bot/hl_resilient_execution_recorder.py`

Before any code is promoted into the repository as canonical research code, copy it deliberately into `research/hyperliquid-volume/`, add tests, and record the exact commit used for each new result.

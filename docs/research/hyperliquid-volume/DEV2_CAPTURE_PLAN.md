# Hyperliquid Development Capture 2 Plan

Date: 2026-09-14

Status: PREDECLARED SEEN DEVELOPMENT CAPTURE — NOT SESSION 4

Purpose: collect a longer multi-market raw event dataset to evaluate the already-frozen C0/P1/P2/P3/P4 entry-policy grid without using future Session 4 unseen data.

## Capture scope

- markets: BTC, ETHFI, PONS, PUMP, VVV
- duration: 360 minutes per market
- recorder: current resilient single-coin execution recorder
- raw data: L2 top-5 books + individual trades
- health log: enabled by recorder prefix
- no live orders / no wallet execution
- no policy tuning during capture
- no early stop based on observed policy performance

Because the current resilient recorder accepts one `--coin` at a time, five independent recorder processes will run in parallel with separate prefixes and output files.

## Output layout

Use one timestamped development directory. Each market gets:

- `<COIN>_books.csv`
- `<COIN>_trades.csv`
- `<COIN>_health.log`
- `<COIN>_stdout.log`

The directory itself must be clearly labeled development/SEEN.

## Restart semantics

The resilient recorder already loads existing trade IDs and recent book signatures and appends to the same prefix. If an individual process dies unexpectedly, restart that market with the same `--prefix`; do not truncate or replace prior capture files.

A restarted process must record its interruption/restart in the operational notes before replay.

## Completion gate

Do not begin policy selection until all five market captures finish or any incomplete market is explicitly documented. After capture, run integrity checks for row counts, time spans, duplicate behavior, and health/reconnect context before replaying the unchanged predeclared policy grid.

Session 4 remains untouched until one candidate is selected and frozen after this development cycle.

# Hyperliquid Market Selector (Research)

Read-only deterministic selector for ranking Hyperliquid markets and deciding whether any market is eligible for trading research.

## Safety

- No wallet.
- No `Exchange` client.
- No order submission.
- Missing metrics remain UNKNOWN.
- No qualified market => IDLE.

## Modules

- `observer.py` — rolling L2/trade/execution observations
- `eligibility.py` — fail-closed qualification gates
- `ranker.py` — stable leaderboard ordering
- `supervisor.py` — hysteresis, degrade/drain/switch logic
- `live_dry_run.py` — public-data-only live monitor

## Running the dry-run

From this directory's parent (`research/hyperliquid-volume`) with the Hyperliquid SDK installed:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run --top 12 --interval 30
```

Or watch explicit markets:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run --coins VVV,PONS,ETHFI,PUMP,BTC --interval 30
```

The first version intentionally leaves empirical execution metrics UNKNOWN because structural market data alone was proven insufficient by prior research. Wire the existing dry-run execution simulator to `ExecutionObservation` before using QUALIFIED as a meaningful verdict.

## Tests

```bash
PYTHONPATH=. pytest -q tests/test_market_selector.py
```

Thresholds in `config.py` are provisional research hypotheses, not production-frozen values.

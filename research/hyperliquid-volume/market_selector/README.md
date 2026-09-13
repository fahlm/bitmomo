# Hyperliquid Market Selector (Research)

Read-only deterministic selector for ranking Hyperliquid markets and deciding whether any market is eligible for trading research.

## Safety

- No wallet.
- No `Exchange` client.
- No order submission.
- Missing metrics remain UNKNOWN.
- No qualified market => IDLE.
- Execution economics come only from explicit shadow/paper feedback; they are never inferred from spread/depth alone.

## Modules

- `observer.py` — rolling L2/trade/execution observations
- `feedback.py` — JSONL execution-feedback contract, tailer, dedupe, router
- `eligibility.py` — fail-closed qualification gates
- `ranker.py` — stable leaderboard ordering
- `supervisor.py` — hysteresis, degrade/drain/switch logic
- `live_dry_run.py` — public-data live monitor with optional shadow feedback

## Running the dry-run

From this directory's parent (`research/hyperliquid-volume`) with the Hyperliquid SDK installed:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run --top 12 --interval 30
```

Or watch explicit markets:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run --coins VVV,PONS,ETHFI,PUMP,BTC --interval 30
```

Without execution feedback, markets can reach WATCH but cannot meaningfully become QUALIFIED because fill rate, maker ratio, markout, P10K and T10K remain UNKNOWN.

### With shadow/paper execution feedback

A dry-run simulator may append one finalized JSON object per hypothetical entry attempt to a JSONL file. Then run:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run \
  --coins VVV,PONS,ETHFI,PUMP,BTC \
  --interval 30 \
  --feedback-jsonl data/hl_selector_feedback.jsonl
```

The selector tails only new complete lines, validates them, deduplicates `(source, attempt_id)`, routes them to the correct market observer, and then derives rolling execution economics from those samples.

Example producer code:

```python
from market_selector.feedback import ExecutionFeedbackEvent, append_feedback_event

append_feedback_event(
    "data/hl_selector_feedback.jsonl",
    ExecutionFeedbackEvent(
        attempt_id="vvv-000001",
        coin="VVV",
        timestamp_ms=1_700_000_000_000,
        filled=True,
        maker_entry=True,
        maker_exit=False,
        markout_5s_bps=0.12,
        round_trip_volume_usd=200.0,
        pnl_usd=-0.03,
        policy_id="shadow-probe-v0",
        source="shadow",
    ),
)
```

Unfilled attempts must also be emitted with `filled=False`; they are required for an honest fill-rate denominator. See `docs/research/hyperliquid-volume/EXECUTION_FEEDBACK_CONTRACT.md` for the complete contract.

## Tests

```bash
PYTHONPATH=. pytest -q tests/test_market_selector.py tests/test_execution_feedback.py
```

Thresholds in `config.py` are provisional research hypotheses, not production-frozen values. VVV is not hard-coded as qualified: the previous unseen VVV holdout failed the economics and drawdown gates, and the SEEN state-aware exit diagnostic still failed the economics gate.

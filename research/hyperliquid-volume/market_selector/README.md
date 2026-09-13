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
- `shadow_probe.py` — conservative queue-ahead shadow execution probe
- `eligibility.py` — fail-closed qualification gates
- `ranker.py` — stable leaderboard ordering
- `supervisor.py` — hysteresis, degrade/drain/switch logic
- `transport.py` — generation-fenced websocket health/reconnect state
- `live_dry_run.py` — resilient public-data live monitor with optional shadow feedback/probe

## Running the resilient dry-run

From this directory's parent (`research/hyperliquid-volume`) with the Hyperliquid SDK installed:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run --top 12 --interval 30
```

Or watch explicit markets:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run --coins VVV,PONS,ETHFI,PUMP,BTC --interval 30
```

The live adapter treats L2/book updates as the primary transport heartbeat. If all watched book feeds are stale for 60 seconds, it safely recreates the websocket session, re-subscribes all markets, fences callbacks from obsolete generations, and keeps the supervisor fail-closed until fresh book data returns. Trade inactivity by itself is not treated as transport failure; it still affects market quality through rolling trade rate.

Without execution feedback, markets can reach WATCH but cannot meaningfully become QUALIFIED because fill rate, maker ratio, markout, P10K and T10K remain UNKNOWN.

## Built-in standardized shadow probe

To let the selector collect its own comparable dry-run execution samples across markets without a wallet:

```bash
PYTHONPATH=. python -m market_selector.live_dry_run \
  --coins VVV,PONS,ETHFI,PUMP,BTC \
  --interval 30 \
  --shadow-probe \
  --shadow-output-jsonl data/hl_shadow_probe.jsonl
```

The probe is deliberately conservative:

- entry only when book imbalance, microprice edge and aggressor flow align 3/3;
- JOIN maker entry at current BBO;
- full displayed queue ahead + own $100 notional must be consumed by observed opposite-aggressor flow;
- no cancellation credit;
- waits for the first post-fill L2 update before placing the hypothetical exit;
- JOIN maker exit, 30-second lifetime;
- if maker exit is not observed, taker fallback crosses the then-current BBO;
- 1.5 bp maker and 4.5 bp taker fee assumptions in V0;
- every unfilled attempt is retained for the fill-rate denominator.

Policy id: `shadow-probe-join-3of3-e10-x30-v0`.

This is a **standardized execution probe**, not validated alpha. Its purpose is to compare whether a market currently supports our execution requirements under one fixed policy. A market still needs independent holdout validation before mainnet consideration.

### With external shadow/paper execution feedback

A separate dry-run simulator may append one finalized JSON object per hypothetical entry attempt to a JSONL file. Then run:

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
PYTHONPATH=. pytest -q \
  tests/test_market_selector.py \
  tests/test_execution_feedback.py \
  tests/test_shadow_probe.py \
  tests/test_transport.py
```

Thresholds in `config.py` are provisional research hypotheses, not production-frozen values. VVV is not hard-coded as qualified: the previous unseen VVV holdout failed the economics and drawdown gates, and the SEEN state-aware exit diagnostic still failed the economics gate.

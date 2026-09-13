import json
from pathlib import Path

import pytest

from market_selector.config import SelectorConfig
from market_selector.feedback import (
    ExecutionFeedbackEvent,
    ExecutionFeedbackRouter,
    JsonlExecutionFeedbackTailer,
    append_feedback_event,
)
from market_selector.observer import MarketObserver


def make_event(**overrides):
    payload = dict(
        attempt_id="a1",
        coin="VVV",
        timestamp_ms=1_000_000,
        filled=True,
        maker_entry=True,
        maker_exit=True,
        markout_5s_bps=0.25,
        round_trip_volume_usd=200.0,
        pnl_usd=-0.02,
        policy_id="probe-v0",
        source="shadow",
    )
    payload.update(overrides)
    return ExecutionFeedbackEvent(**payload)


def test_unfilled_attempt_is_valid_fill_rate_denominator():
    cfg = SelectorConfig(warmup_seconds=0)
    observer = MarketObserver("VVV", cfg)
    observer.started_ms = 1_000_000
    router = ExecutionFeedbackRouter({"VVV": observer})

    router.route(
        make_event(
            attempt_id="miss",
            filled=False,
            maker_entry=False,
            maker_exit=False,
            markout_5s_bps=None,
            round_trip_volume_usd=None,
            pnl_usd=None,
        )
    )
    router.route(make_event(attempt_id="fill"))

    metrics = observer.snapshot(now_ms=1_001_000)
    assert metrics.execution_samples == 2
    assert metrics.fill_rate == pytest.approx(0.5)


def test_completed_round_trip_populates_economics():
    cfg = SelectorConfig(warmup_seconds=0)
    observer = MarketObserver("VVV", cfg)
    observer.started_ms = 1_000_000
    router = ExecutionFeedbackRouter({"VVV": observer})

    router.route(make_event(attempt_id="a", pnl_usd=0.04, round_trip_volume_usd=200.0))
    router.route(make_event(attempt_id="b", pnl_usd=-0.02, round_trip_volume_usd=200.0))

    metrics = observer.snapshot(now_ms=1_001_000)
    assert metrics.maker_ratio == pytest.approx(1.0)
    assert metrics.markout_5s_bps == pytest.approx(0.25)
    assert metrics.p10k_usd == pytest.approx(0.5)
    assert metrics.volume_per_hour_usd > 0
    assert metrics.t10k_hours > 0


def test_router_deduplicates_attempt_id_per_source():
    observer = MarketObserver("VVV", SelectorConfig())
    router = ExecutionFeedbackRouter({"VVV": observer})
    event = make_event()

    assert router.route(event) is True
    assert router.route(event) is False
    assert router.stats.accepted == 1
    assert router.stats.duplicate == 1
    assert len(observer.executions) == 1


def test_same_attempt_id_from_different_source_is_not_duplicate():
    observer = MarketObserver("VVV", SelectorConfig())
    router = ExecutionFeedbackRouter({"VVV": observer})

    assert router.route(make_event(source="shadow")) is True
    assert router.route(make_event(source="paper")) is True
    assert len(observer.executions) == 2


def test_unknown_coin_is_ignored():
    observer = MarketObserver("VVV", SelectorConfig())
    router = ExecutionFeedbackRouter({"VVV": observer})

    accepted = router.route(make_event(coin="PONS"))
    assert accepted is False
    assert router.stats.unknown_coin == 1
    assert len(observer.executions) == 0


def test_invalid_unfilled_event_rejected():
    event = make_event(
        filled=False,
        maker_entry=True,
        maker_exit=False,
        round_trip_volume_usd=None,
        pnl_usd=None,
    )
    with pytest.raises(ValueError):
        event.validate()


def test_jsonl_tailer_waits_for_partial_line(tmp_path: Path):
    path = tmp_path / "feedback.jsonl"
    complete = make_event(attempt_id="complete").to_json() + "\n"
    partial = make_event(attempt_id="partial").to_json()
    path.write_text(complete + partial)

    tailer = JsonlExecutionFeedbackTailer(path)
    first = tailer.poll()
    assert [x.attempt_id for x in first] == ["complete"]

    with path.open("a") as handle:
        handle.write("\n")

    second = tailer.poll()
    assert [x.attempt_id for x in second] == ["partial"]


def test_jsonl_tailer_skips_malformed_row(tmp_path: Path):
    path = tmp_path / "feedback.jsonl"
    path.write_text("not-json\n" + make_event().to_json() + "\n")

    tailer = JsonlExecutionFeedbackTailer(path)
    events = tailer.poll()

    assert len(events) == 1
    assert tailer.stats.invalid == 1
    assert tailer.stats.parsed == 1


def test_append_feedback_event_round_trip(tmp_path: Path):
    path = tmp_path / "nested" / "feedback.jsonl"
    event = make_event(attempt_id="persisted")
    append_feedback_event(path, event)

    raw = json.loads(path.read_text().strip())
    restored = ExecutionFeedbackEvent.from_dict(raw)
    assert restored == event

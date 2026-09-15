import json

from market_selector.feedback import ExecutionFeedbackEvent
from market_selector.mission_replay import replay


def write_events(path, rows):
    with path.open("w", encoding="utf-8") as handle:
        for row in rows:
            handle.write(row.to_json() + "\n")


def filled(attempt_id, ts, volume, pnl, policy="p"):
    return ExecutionFeedbackEvent(
        attempt_id=attempt_id,
        coin="TEST",
        timestamp_ms=ts,
        filled=True,
        maker_entry=True,
        maker_exit=True,
        markout_5s_bps=0.0,
        round_trip_volume_usd=volume,
        pnl_usd=pnl,
        policy_id=policy,
        source="shadow_probe",
    )


def test_canary_replay_carries_volume_across_epochs(tmp_path):
    path = tmp_path / "feedback.jsonl"
    write_events(
        path,
        [
            filled("a1", 1_000, 400.0, -1.0),
            filled("a2", 2_000, 600.0, -0.5),
        ],
    )

    result = replay(path, canary=True, policy_id="p")
    assert result["status"] == "SUCCESS"
    assert result["epoch"] == 2
    assert result["cumulative_volume_usd"] == 1_000.0
    assert result["cumulative_pnl_usd"] == -1.5


def test_replay_policy_filter_excludes_other_policy(tmp_path):
    path = tmp_path / "feedback.jsonl"
    write_events(
        path,
        [
            filled("a1", 1_000, 1_000.0, -0.2, policy="other"),
            filled("a2", 2_000, 1_000.0, -0.2, policy="target"),
        ],
    )

    result = replay(path, canary=True, policy_id="target")
    assert result["status"] == "SUCCESS"
    assert result["cumulative_volume_usd"] == 1_000.0
    assert result["cumulative_pnl_usd"] == -0.2

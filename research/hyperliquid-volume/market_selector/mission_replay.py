from __future__ import annotations

import argparse
import json
from pathlib import Path

from .feedback import ExecutionFeedbackEvent
from .mission import CANARY_MISSION, FULL_BETA_MISSION, MissionLedger, MissionStatus


def load_events(path: Path) -> list[ExecutionFeedbackEvent]:
    events: list[ExecutionFeedbackEvent] = []
    with path.open("r", encoding="utf-8") as handle:
        for line_no, line in enumerate(handle, 1):
            if not line.strip():
                continue
            try:
                event = ExecutionFeedbackEvent.from_dict(json.loads(line))
            except Exception as exc:
                raise RuntimeError(f"invalid event at line {line_no}: {exc}") from exc
            events.append(event)
    events.sort(key=lambda e: (e.timestamp_ms, e.coin, e.attempt_id))
    return events


def replay(path: Path, *, canary: bool, policy_id: str | None) -> dict:
    cfg = CANARY_MISSION if canary else FULL_BETA_MISSION
    ledger = MissionLedger(cfg)
    consumed = 0

    for event in load_events(path):
        if policy_id and event.policy_id != policy_id:
            continue

        # Feedback events are finalized round trips. For historical analysis it is
        # therefore safe to begin epoch 2 before the next completed event.
        if ledger.status == MissionStatus.EPOCH_STOP:
            ledger.begin_next_epoch(inventory_flat=True)

        before = ledger.snapshot()
        after = ledger.record(event)
        if after != before:
            consumed += 1

        if after.status in {MissionStatus.SUCCESS, MissionStatus.HALT}:
            break

    payload = ledger.to_dict()
    payload["source"] = str(path)
    payload["mode"] = "CANARY" if canary else "FULL_BETA"
    payload["policy_filter"] = policy_id
    payload["events_consumed"] = consumed
    return payload


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Replay finalized shadow/paper outcomes through the mission budget state machine."
    )
    parser.add_argument("feedback_jsonl")
    parser.add_argument("--canary", action="store_true")
    parser.add_argument("--policy-id", default="")
    args = parser.parse_args()

    result = replay(
        Path(args.feedback_jsonl),
        canary=args.canary,
        policy_id=args.policy_id or None,
    )
    print(json.dumps(result, indent=2, sort_keys=True))


if __name__ == "__main__":
    main()

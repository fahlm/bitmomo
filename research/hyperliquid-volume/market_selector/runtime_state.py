from __future__ import annotations

import json
import os
import tempfile
from dataclasses import asdict
from pathlib import Path

from .models import MarketHistory, SupervisorState


class RuntimeStateStore:
    """Atomic JSON persistence for the shadow runtime control-plane state.

    Market-state windows are deliberately not restored: after restart they must
    warm up again from fresh public data. Supervisor identity/history and a compact
    status snapshot are persisted so operators can diagnose what happened before
    the restart without silently resuming stale trading authority.
    """

    def __init__(self, path: str | Path):
        self.path = Path(path)

    def load(self) -> dict:
        if not self.path.exists():
            return {}
        try:
            payload = json.loads(self.path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return {}
        return payload if isinstance(payload, dict) else {}

    def save(self, payload: dict) -> None:
        self.path.parent.mkdir(parents=True, exist_ok=True)
        serialized = json.dumps(payload, sort_keys=True, indent=2)
        fd, temp_name = tempfile.mkstemp(
            prefix=self.path.name + ".",
            suffix=".tmp",
            dir=str(self.path.parent),
            text=True,
        )
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as handle:
                handle.write(serialized)
                handle.write("\n")
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temp_name, self.path)
        finally:
            try:
                os.unlink(temp_name)
            except FileNotFoundError:
                pass


def export_supervisor_state(supervisor) -> dict:
    return {
        "state": supervisor.state.value,
        "active_market": supervisor.active_market,
        "pending_market": supervisor.pending_market,
        "histories": {
            coin: asdict(history)
            for coin, history in supervisor.histories.items()
        },
    }


def restore_supervisor_state(supervisor, payload: dict) -> None:
    """Restore identity/history but force fresh requalification after restart.

    An old ACTIVE state must never immediately grant new-entry authority after a
    process restart. We remember which market had been active, reset qualification
    and challenger streaks, and move the supervisor to STOP_NEW_ENTRY until fresh
    market-state windows requalify it through the normal decision loop.
    """

    if not isinstance(payload, dict):
        return

    active = payload.get("active_market")
    pending = payload.get("pending_market")
    supervisor.active_market = str(active) if active else None
    supervisor.pending_market = str(pending) if pending else None
    supervisor.histories = {}

    histories = payload.get("histories") or {}
    if isinstance(histories, dict):
        for coin, raw in histories.items():
            if not isinstance(raw, dict):
                continue
            supervisor.histories[str(coin)] = MarketHistory(
                qualify_streak=0,
                degrade_streak=max(0, int(raw.get("degrade_streak", 0) or 0)),
                challenger_streaks={},
            )

    if supervisor.active_market:
        supervisor.state = SupervisorState.STOP_NEW_ENTRY
    else:
        supervisor.state = SupervisorState.IDLE

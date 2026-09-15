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
    warm up again from fresh public data. Supervisor history and the prior active
    identity are persisted for diagnostics, but stale trading authority is never
    resumed automatically.
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
    """Restore diagnostic history while revoking stale authority.

    A process restart is a hard authority boundary. The prior active/pending market
    is remembered on diagnostic attributes only; `active_market` and
    `pending_market` are cleared. Qualification and challenger streaks are also
    reset, so fresh public market state must pass the normal hysteresis from zero
    before the supervisor can activate any market again.
    """

    if not isinstance(payload, dict):
        return

    old_active = payload.get("active_market")
    old_pending = payload.get("pending_market")
    supervisor.last_restored_active_market = str(old_active) if old_active else None
    supervisor.last_restored_pending_market = str(old_pending) if old_pending else None

    supervisor.active_market = None
    supervisor.pending_market = None
    supervisor.histories = {}

    histories = payload.get("histories") or {}
    if isinstance(histories, dict):
        for coin, raw in histories.items():
            if not isinstance(raw, dict):
                continue
            supervisor.histories[str(coin)] = MarketHistory(
                qualify_streak=0,
                degrade_streak=0,
                challenger_streaks={},
            )

    supervisor.state = SupervisorState.IDLE

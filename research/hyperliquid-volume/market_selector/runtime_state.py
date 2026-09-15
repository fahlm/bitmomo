from __future__ import annotations

import json
import os
import tempfile
from dataclasses import asdict
from pathlib import Path

from .models import MarketHistory, SupervisorState


class RuntimeStateStore:
    """Atomic current-state persistence plus bounded compact soak history.

    Market-state windows are deliberately not restored: after restart they must
    warm up again from fresh public data. Supervisor history and the prior active
    identity are persisted for diagnostics, but stale trading authority is never
    resumed automatically.

    Every save also appends a compact operational record to a JSONL history file.
    The history excludes per-market metric payloads so a 24/7 soak stays small and
    bounded. Rotation keeps at most the current history plus one previous segment.
    """

    def __init__(
        self,
        path: str | Path,
        *,
        history_path: str | Path | None = None,
        history_max_bytes: int = 64 * 1024 * 1024,
    ):
        self.path = Path(path)
        self.history_path = (
            Path(history_path)
            if history_path is not None
            else self.path.with_suffix(self.path.suffix + ".history.jsonl")
        )
        if history_max_bytes <= 0:
            raise ValueError("history_max_bytes must be positive")
        self.history_max_bytes = history_max_bytes

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

        self._append_history(payload)

    @staticmethod
    def _compact_history_payload(payload: dict) -> dict:
        transport = payload.get("transport") or {}
        integrity = payload.get("event_integrity") or {}
        decision = payload.get("decision") or {}
        feedback = payload.get("feedback") or {}
        return {
            "schema_version": 1,
            "updated_at_ms": payload.get("updated_at_ms"),
            "mode": payload.get("mode"),
            "mainnet_order_submission": payload.get("mainnet_order_submission"),
            "universe": list(payload.get("universe") or []),
            "transport": {
                "generation": transport.get("generation"),
                "healthy": transport.get("healthy"),
                "reconnects": transport.get("reconnects"),
                "max_book_age_seconds": transport.get("max_book_age_seconds"),
            },
            "event_integrity": {
                "book_events_accepted": integrity.get("book_events_accepted"),
                "book_duplicates": integrity.get("book_duplicates"),
                "trade_events_accepted": integrity.get("trade_events_accepted"),
                "trade_duplicates": integrity.get("trade_duplicates"),
            },
            "decision": {
                "state": decision.get("state"),
                "active_market": decision.get("active_market"),
                "candidate_market": decision.get("candidate_market"),
                "allow_new_entries": decision.get("allow_new_entries"),
                "action": decision.get("action"),
                "reason": decision.get("reason"),
            },
            "feedback": {
                "policy_id": feedback.get("policy_id"),
                "accepted": feedback.get("accepted"),
                "duplicate": feedback.get("duplicate"),
                "unknown_coin": feedback.get("unknown_coin"),
                "invalid_history": feedback.get("invalid_history"),
            },
        }

    def _rotate_history_if_needed(self) -> None:
        try:
            size = self.history_path.stat().st_size
        except FileNotFoundError:
            return
        if size < self.history_max_bytes:
            return

        previous = self.history_path.with_suffix(self.history_path.suffix + ".1")
        try:
            previous.unlink()
        except FileNotFoundError:
            pass
        os.replace(self.history_path, previous)

    def _append_history(self, payload: dict) -> None:
        record = self._compact_history_payload(payload)
        if not isinstance(record.get("updated_at_ms"), int):
            return
        self.history_path.parent.mkdir(parents=True, exist_ok=True)
        self._rotate_history_if_needed()
        with self.history_path.open("a", encoding="utf-8") as handle:
            handle.write(json.dumps(record, separators=(",", ":"), sort_keys=True))
            handle.write("\n")
            handle.flush()
            os.fsync(handle.fileno())


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

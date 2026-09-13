from __future__ import annotations

import json
import os
from collections import deque
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Deque, Dict, Iterable, Optional

from .observer import ExecutionObservation, MarketObserver


@dataclass(frozen=True)
class ExecutionFeedbackEvent:
    """Finalized dry-run execution outcome for one hypothetical entry attempt.

    One event represents one attempt. Unfilled attempts are important because they
    form the denominator for fill-rate. Filled attempts may carry completed
    round-trip economics. This schema is deliberately independent of wallet/order
    APIs so the market selector can learn only from shadow/paper execution data.
    """

    attempt_id: str
    coin: str
    timestamp_ms: int
    filled: bool
    maker_entry: bool = False
    maker_exit: bool = False
    markout_5s_bps: Optional[float] = None
    round_trip_volume_usd: Optional[float] = None
    pnl_usd: Optional[float] = None
    policy_id: str = "unknown"
    source: str = "shadow"

    def validate(self) -> None:
        if not self.attempt_id.strip():
            raise ValueError("attempt_id must be non-empty")
        if not self.coin.strip():
            raise ValueError("coin must be non-empty")
        if self.timestamp_ms <= 0:
            raise ValueError("timestamp_ms must be positive")
        if not self.source.strip():
            raise ValueError("source must be non-empty")

        if not self.filled:
            if self.maker_entry or self.maker_exit:
                raise ValueError("unfilled attempt cannot have maker legs")
            if self.round_trip_volume_usd is not None or self.pnl_usd is not None:
                raise ValueError("unfilled attempt cannot have round-trip economics")

        if self.maker_exit and not self.filled:
            raise ValueError("maker_exit requires filled=True")

        if self.round_trip_volume_usd is not None and self.round_trip_volume_usd <= 0:
            raise ValueError("round_trip_volume_usd must be positive")
        if self.pnl_usd is not None and self.round_trip_volume_usd is None:
            raise ValueError("pnl_usd requires round_trip_volume_usd")

    def to_observation(self) -> ExecutionObservation:
        self.validate()
        return ExecutionObservation(
            timestamp_ms=self.timestamp_ms,
            filled=self.filled,
            maker_entry=self.maker_entry,
            maker_exit=self.maker_exit,
            markout_5s_bps=self.markout_5s_bps,
            round_trip_volume_usd=self.round_trip_volume_usd,
            pnl_usd=self.pnl_usd,
        )

    def to_json(self) -> str:
        self.validate()
        return json.dumps(asdict(self), separators=(",", ":"), sort_keys=True)

    @classmethod
    def from_dict(cls, payload: dict) -> "ExecutionFeedbackEvent":
        event = cls(
            attempt_id=str(payload["attempt_id"]),
            coin=str(payload["coin"]),
            timestamp_ms=int(payload["timestamp_ms"]),
            filled=bool(payload["filled"]),
            maker_entry=bool(payload.get("maker_entry", False)),
            maker_exit=bool(payload.get("maker_exit", False)),
            markout_5s_bps=(
                None
                if payload.get("markout_5s_bps") is None
                else float(payload["markout_5s_bps"])
            ),
            round_trip_volume_usd=(
                None
                if payload.get("round_trip_volume_usd") is None
                else float(payload["round_trip_volume_usd"])
            ),
            pnl_usd=(
                None if payload.get("pnl_usd") is None else float(payload["pnl_usd"])
            ),
            policy_id=str(payload.get("policy_id", "unknown")),
            source=str(payload.get("source", "shadow")),
        )
        event.validate()
        return event


@dataclass
class FeedbackStats:
    parsed: int = 0
    invalid: int = 0
    accepted: int = 0
    duplicate: int = 0
    unknown_coin: int = 0


class ExecutionFeedbackRouter:
    """Routes finalized feedback into per-market observers with bounded dedupe."""

    def __init__(
        self,
        observers: Dict[str, MarketObserver],
        *,
        max_seen_ids: int = 100_000,
    ):
        self.observers = observers
        self.max_seen_ids = max_seen_ids
        self._seen_order: Deque[tuple[str, str]] = deque()
        self._seen: set[tuple[str, str]] = set()
        self.stats = FeedbackStats()

    def route(self, event: ExecutionFeedbackEvent) -> bool:
        event.validate()
        key = (event.source, event.attempt_id)
        if key in self._seen:
            self.stats.duplicate += 1
            return False

        observer = self.observers.get(event.coin)
        if observer is None:
            self.stats.unknown_coin += 1
            return False

        observer.on_execution(event.to_observation())
        self._remember(key)
        self.stats.accepted += 1
        return True

    def route_many(self, events: Iterable[ExecutionFeedbackEvent]) -> int:
        accepted = 0
        for event in events:
            if self.route(event):
                accepted += 1
        return accepted

    def _remember(self, key: tuple[str, str]) -> None:
        self._seen.add(key)
        self._seen_order.append(key)
        while len(self._seen_order) > self.max_seen_ids:
            expired = self._seen_order.popleft()
            self._seen.discard(expired)


class JsonlExecutionFeedbackTailer:
    """Incrementally tails finalized execution feedback from a JSONL file.

    Binary offsets make truncation/rotation handling deterministic. A final partial
    line is not consumed until its newline arrives, which prevents reading a writer
    mid-append.
    """

    def __init__(self, path: str | Path):
        self.path = Path(path)
        self.offset = 0
        self.stats = FeedbackStats()
        self.last_errors: Deque[str] = deque(maxlen=20)

    def poll(self) -> list[ExecutionFeedbackEvent]:
        if not self.path.exists():
            return []

        size = self.path.stat().st_size
        if size < self.offset:
            # File was truncated or rotated in place.
            self.offset = 0

        events: list[ExecutionFeedbackEvent] = []
        with self.path.open("rb") as handle:
            handle.seek(self.offset)
            while True:
                line_start = handle.tell()
                raw = handle.readline()
                if not raw:
                    break
                if not raw.endswith(b"\n"):
                    # Writer may still be appending this record. Retry next poll.
                    self.offset = line_start
                    break

                self.offset = handle.tell()
                if not raw.strip():
                    continue

                try:
                    payload = json.loads(raw.decode("utf-8"))
                    event = ExecutionFeedbackEvent.from_dict(payload)
                except Exception as exc:  # malformed research data must not crash feed
                    self.stats.invalid += 1
                    self.last_errors.append(f"offset={line_start}: {exc}")
                    continue

                self.stats.parsed += 1
                events.append(event)

        return events


def append_feedback_event(path: str | Path, event: ExecutionFeedbackEvent) -> None:
    """Append one finalized shadow/paper event for the selector to consume."""

    event.validate()
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    with target.open("a", encoding="utf-8") as handle:
        handle.write(event.to_json() + "\n")
        handle.flush()
        os.fsync(handle.fileno())

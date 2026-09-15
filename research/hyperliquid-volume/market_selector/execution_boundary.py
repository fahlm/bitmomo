from __future__ import annotations

import fcntl
import json
import os
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import IO


@dataclass(frozen=True)
class ReconciliationSnapshot:
    """Minimal account state needed before any new risk may be created."""

    expected_positions: dict[str, float] = field(default_factory=dict)
    actual_positions: dict[str, float] = field(default_factory=dict)
    expected_open_order_ids: frozenset[str] = frozenset()
    actual_open_order_ids: frozenset[str] = frozenset()


@dataclass(frozen=True)
class ReconciliationResult:
    ok: bool
    inventory_flat: bool
    reasons: tuple[str, ...]


def reconcile_account(
    snapshot: ReconciliationSnapshot,
    *,
    position_tolerance: float = 1e-9,
) -> ReconciliationResult:
    """Fail closed on any meaningful local/exchange state disagreement."""

    if position_tolerance < 0:
        raise ValueError("position_tolerance must be non-negative")

    reasons: list[str] = []
    coins = set(snapshot.expected_positions) | set(snapshot.actual_positions)

    for coin in sorted(coins):
        expected = float(snapshot.expected_positions.get(coin, 0.0))
        actual = float(snapshot.actual_positions.get(coin, 0.0))
        if abs(expected - actual) > position_tolerance:
            reasons.append(
                f"position_mismatch:{coin}:expected={expected}:actual={actual}"
            )

    missing_orders = snapshot.expected_open_order_ids - snapshot.actual_open_order_ids
    unexpected_orders = snapshot.actual_open_order_ids - snapshot.expected_open_order_ids
    if missing_orders:
        reasons.append("missing_orders:" + ",".join(sorted(missing_orders)))
    if unexpected_orders:
        reasons.append("unexpected_orders:" + ",".join(sorted(unexpected_orders)))

    inventory_flat = all(
        abs(float(size)) <= position_tolerance
        for size in snapshot.actual_positions.values()
    )

    return ReconciliationResult(
        ok=not reasons,
        inventory_flat=inventory_flat,
        reasons=tuple(reasons),
    )


class SingleExecutionAuthority:
    """Host-local exclusive authority lock for the future order adapter.

    `flock` is released automatically by the OS when the owning process exits.
    This is intentionally a host-local safety primitive; a future multi-host
    deployment must replace it with a distributed lease before enabling orders.
    """

    def __init__(self, path: str | Path, *, owner_id: str):
        if not owner_id.strip():
            raise ValueError("owner_id must be non-empty")
        self.path = Path(path)
        self.owner_id = owner_id
        self._handle: IO[str] | None = None

    @property
    def held(self) -> bool:
        return self._handle is not None

    def acquire(self) -> bool:
        if self._handle is not None:
            return True

        self.path.parent.mkdir(parents=True, exist_ok=True)
        handle = self.path.open("a+", encoding="utf-8")
        try:
            fcntl.flock(handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            handle.close()
            return False

        payload = {
            "owner_id": self.owner_id,
            "pid": os.getpid(),
            "acquired_at_ms": int(time.time() * 1000),
        }
        handle.seek(0)
        handle.truncate(0)
        handle.write(json.dumps(payload, sort_keys=True) + "\n")
        handle.flush()
        os.fsync(handle.fileno())
        self._handle = handle
        return True

    def release(self) -> None:
        handle = self._handle
        if handle is None:
            return
        try:
            fcntl.flock(handle.fileno(), fcntl.LOCK_UN)
        finally:
            handle.close()
            self._handle = None

    def __enter__(self) -> "SingleExecutionAuthority":
        if not self.acquire():
            raise RuntimeError("execution authority already held")
        return self

    def __exit__(self, exc_type, exc, tb) -> None:
        self.release()

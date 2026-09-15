from __future__ import annotations

from collections import defaultdict, deque
from typing import Hashable


class BoundedEventDeduper:
    """Bounded per-market dedupe for reconnect/replay protection."""

    def __init__(self, max_keys_per_coin: int = 50_000):
        if max_keys_per_coin <= 0:
            raise ValueError("max_keys_per_coin must be positive")
        self.max_keys_per_coin = max_keys_per_coin
        self._sets: dict[str, set[Hashable]] = defaultdict(set)
        self._queues: dict[str, deque[Hashable]] = defaultdict(deque)
        self.duplicates = 0
        self.accepted = 0

    def accept(self, coin: str, key: Hashable) -> bool:
        seen = self._sets[coin]
        if key in seen:
            self.duplicates += 1
            return False

        seen.add(key)
        queue = self._queues[coin]
        queue.append(key)
        while len(queue) > self.max_keys_per_coin:
            expired = queue.popleft()
            seen.discard(expired)
        self.accepted += 1
        return True


class LastBookDeduper:
    """Reject exact consecutive L2 snapshots for the same market."""

    def __init__(self):
        self.last: dict[str, tuple] = {}
        self.duplicates = 0
        self.accepted = 0

    def accept(self, coin: str, signature: tuple) -> bool:
        if self.last.get(coin) == signature:
            self.duplicates += 1
            return False
        self.last[coin] = signature
        self.accepted += 1
        return True

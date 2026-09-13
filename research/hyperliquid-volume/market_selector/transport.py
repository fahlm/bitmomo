from __future__ import annotations

import time
from dataclasses import dataclass, field
from typing import Iterable


@dataclass
class FeedHealth:
    """Generation-fenced transport health for one shared Hyperliquid websocket.

    Book updates are the primary transport heartbeat. Trade inactivity is deliberately
    not treated as a websocket failure because an otherwise healthy market can simply
    have no trades for a while.
    """

    coins: tuple[str, ...]
    stale_seconds: float = 60.0
    initial_backoff_seconds: float = 2.0
    max_backoff_seconds: float = 30.0
    generation: int = 0
    reconnects: int = 0
    generation_started_wall: float = field(default_factory=time.monotonic)
    last_book_wall: dict[str, float] = field(default_factory=dict)
    healthy: bool = False
    _backoff_seconds: float = 2.0

    @classmethod
    def for_coins(
        cls,
        coins: Iterable[str],
        *,
        stale_seconds: float = 60.0,
        initial_backoff_seconds: float = 2.0,
        max_backoff_seconds: float = 30.0,
    ) -> "FeedHealth":
        return cls(
            coins=tuple(coins),
            stale_seconds=stale_seconds,
            initial_backoff_seconds=initial_backoff_seconds,
            max_backoff_seconds=max_backoff_seconds,
            _backoff_seconds=initial_backoff_seconds,
        )

    def start_generation(self, now: float | None = None) -> int:
        self.generation += 1
        self.generation_started_wall = time.monotonic() if now is None else now
        self.last_book_wall = {}
        self.healthy = False
        return self.generation

    def accepts(self, generation: int) -> bool:
        return generation == self.generation

    def note_book(
        self,
        generation: int,
        coin: str,
        now: float | None = None,
    ) -> bool:
        if not self.accepts(generation):
            return False
        now = time.monotonic() if now is None else now
        self.last_book_wall[coin] = now
        if not self.healthy:
            self.healthy = True
            self._backoff_seconds = self.initial_backoff_seconds
        return True

    def all_books_stale(self, now: float | None = None) -> bool:
        now = time.monotonic() if now is None else now
        # Give each freshly created generation one full stale window to produce data.
        if now - self.generation_started_wall <= self.stale_seconds:
            return False
        if not self.coins:
            return True
        return all(
            now - self.last_book_wall.get(coin, self.generation_started_wall)
            > self.stale_seconds
            for coin in self.coins
        )

    def register_reconnect(self) -> float:
        self.reconnects += 1
        delay = self._backoff_seconds
        self._backoff_seconds = min(
            self._backoff_seconds * 2,
            self.max_backoff_seconds,
        )
        return delay

    @property
    def backoff_seconds(self) -> float:
        return self._backoff_seconds

    def max_book_age(self, now: float | None = None) -> float:
        now = time.monotonic() if now is None else now
        if not self.coins:
            return float("inf")
        return max(
            now - self.last_book_wall.get(coin, self.generation_started_wall)
            for coin in self.coins
        )

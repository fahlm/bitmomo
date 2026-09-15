from __future__ import annotations

import time
from dataclasses import dataclass
from typing import Iterable


@dataclass(frozen=True)
class UniverseMarket:
    coin: str
    day_volume_usd: float
    rank_by_volume: int


@dataclass(frozen=True)
class UniverseSnapshot:
    markets: tuple[UniverseMarket, ...]
    discovered_at_monotonic: float

    @property
    def coins(self) -> tuple[str, ...]:
        return tuple(m.coin for m in self.markets)

    def volume_by_coin(self) -> dict[str, float]:
        return {m.coin: m.day_volume_usd for m in self.markets}


class UniverseManager:
    """Cheap, dynamic Hyperliquid universe discovery with churn control.

    The scanner intentionally uses only inexpensive exchange metadata here. Rich
    microstructure and execution-economics filtering belongs downstream in the
    MarketObserver / eligibility engine. Existing watched markets are retained
    while they remain within a configurable volume buffer, which avoids tearing
    down the shared websocket whenever two adjacent volume ranks swap.
    """

    def __init__(
        self,
        *,
        scan_top: int = 24,
        min_day_volume_usd: float = 10_000_000.0,
        retain_buffer: int = 8,
        refresh_seconds: float = 300.0,
    ):
        if scan_top <= 0:
            raise ValueError("scan_top must be positive")
        if retain_buffer < 0:
            raise ValueError("retain_buffer cannot be negative")
        if refresh_seconds <= 0:
            raise ValueError("refresh_seconds must be positive")

        self.scan_top = scan_top
        self.min_day_volume_usd = min_day_volume_usd
        self.retain_buffer = retain_buffer
        self.refresh_seconds = refresh_seconds
        self.current = UniverseSnapshot((), 0.0)

    def due(self, now: float | None = None) -> bool:
        now = time.monotonic() if now is None else now
        if not self.current.markets:
            return True
        return now - self.current.discovered_at_monotonic >= self.refresh_seconds

    def build_snapshot(
        self,
        meta: dict,
        contexts: list[dict],
        *,
        pinned: Iterable[str] = (),
        now: float | None = None,
    ) -> UniverseSnapshot:
        now = time.monotonic() if now is None else now
        ranked: list[tuple[str, float]] = []

        for asset, ctx in zip(meta.get("universe", []), contexts):
            if asset.get("isDelisted") is True:
                continue
            coin = str(asset.get("name", "")).strip()
            if not coin:
                continue
            try:
                volume = float(ctx.get("dayNtlVlm", 0) or 0)
            except (TypeError, ValueError):
                continue
            if volume < self.min_day_volume_usd:
                continue
            ranked.append((coin, volume))

        ranked.sort(key=lambda item: (-item[1], item[0]))
        rank_by_coin = {coin: i + 1 for i, (coin, _) in enumerate(ranked)}
        volume_by_coin = dict(ranked)

        core = [coin for coin, _ in ranked[: self.scan_top]]
        retain_limit = self.scan_top + self.retain_buffer
        retainable = {coin for coin, _ in ranked[:retain_limit]}

        chosen: list[str] = []
        for coin in self.current.coins:
            if coin in retainable and coin not in chosen:
                chosen.append(coin)

        for coin in core:
            if len(chosen) >= self.scan_top:
                break
            if coin not in chosen:
                chosen.append(coin)

        # Active/pending markets are pinned even if they temporarily fall outside
        # the scan-top bucket. This lets the supervisor observe deterioration and
        # drain safely rather than dropping the market from view first.
        for coin in pinned:
            if coin in volume_by_coin and coin not in chosen:
                chosen.append(coin)

        markets = tuple(
            UniverseMarket(
                coin=coin,
                day_volume_usd=volume_by_coin[coin],
                rank_by_volume=rank_by_coin[coin],
            )
            for coin in chosen
        )
        return UniverseSnapshot(markets=markets, discovered_at_monotonic=now)

    def refresh(
        self,
        info,
        *,
        pinned: Iterable[str] = (),
        now: float | None = None,
    ) -> tuple[UniverseSnapshot, bool]:
        meta, contexts = info.meta_and_asset_ctxs()
        snapshot = self.build_snapshot(meta, contexts, pinned=pinned, now=now)
        changed = set(snapshot.coins) != set(self.current.coins)
        self.current = snapshot
        return snapshot, changed

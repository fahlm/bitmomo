from __future__ import annotations

from dataclasses import dataclass
from enum import Enum
from typing import Any, Protocol

from .mission import MissionConfig


class LiveMissionStatus(str, Enum):
    RUNNING = "RUNNING"
    EPOCH_STOP = "EPOCH_STOP"
    SUCCESS = "SUCCESS"
    HALT = "HALT"


class HyperliquidAccountingInfo(Protocol):
    def user_fills_by_time(
        self,
        address: str,
        start_time: int,
        end_time: int | None = None,
        aggregate_by_time: bool | None = False,
    ) -> Any: ...

    def user_funding_history(
        self,
        user: str,
        startTime: int,
        endTime: int | None = None,
    ) -> Any: ...


@dataclass(frozen=True)
class AccountEconomics:
    fill_count: int
    trading_volume_usd: float
    closed_pnl_gross_usd: float
    trading_fees_usd: float
    funding_pnl_usd: float
    net_pnl_usd: float


@dataclass(frozen=True)
class LiveMissionSnapshot:
    status: LiveMissionStatus
    epoch: int
    cumulative_volume_usd: float
    cumulative_net_pnl_usd: float
    epoch_net_pnl_usd: float
    remaining_volume_usd: float
    remaining_epoch_loss_buffer_usd: float
    remaining_mission_loss_buffer_usd: float
    fills: int
    trading_fees_usd: float
    funding_pnl_usd: float

    @property
    def net_cost_usd(self) -> float:
        return max(0.0, -self.cumulative_net_pnl_usd)


class HyperliquidMissionAccounting:
    """Exchange-native read-only mission accounting.

    Volume comes from actual fills, not modeled round trips. Net PnL is calculated
    as gross closed PnL minus actual fill fees plus funding cashflows observed in
    the same time range. This makes profitable trades a real buffer while keeping
    fees/funding inside the mission loss budget.
    """

    def __init__(self, info: HyperliquidAccountingInfo, *, account_address: str):
        if not account_address.strip():
            raise ValueError("account_address must be non-empty")
        self.info = info
        self.account_address = account_address

    @staticmethod
    def _funding_usd(rows: Any) -> float:
        total = 0.0
        for row in rows or []:
            delta = row.get("delta") or {}
            value = delta.get("usdc")
            if value is None:
                continue
            total += float(value)
        return total

    def economics(self, *, start_ms: int, end_ms: int | None = None) -> AccountEconomics:
        if start_ms <= 0:
            raise ValueError("start_ms must be positive")

        fills = self.info.user_fills_by_time(
            self.account_address,
            start_ms,
            end_ms,
            False,
        )
        volume = 0.0
        gross = 0.0
        fees = 0.0
        count = 0
        for row in fills or []:
            px = float(row.get("px", 0.0) or 0.0)
            size = abs(float(row.get("sz", 0.0) or 0.0))
            if px <= 0.0 or size <= 0.0:
                continue
            volume += px * size
            gross += float(row.get("closedPnl", 0.0) or 0.0)
            fees += float(row.get("fee", 0.0) or 0.0)
            count += 1

        funding_rows = self.info.user_funding_history(
            self.account_address,
            start_ms,
            end_ms,
        )
        funding = self._funding_usd(funding_rows)
        return AccountEconomics(
            fill_count=count,
            trading_volume_usd=volume,
            closed_pnl_gross_usd=gross,
            trading_fees_usd=fees,
            funding_pnl_usd=funding,
            net_pnl_usd=gross - fees + funding,
        )

    def mission_snapshot(
        self,
        *,
        cfg: MissionConfig,
        mission_start_ms: int,
        epoch_start_ms: int,
        epoch: int,
        end_ms: int | None = None,
    ) -> LiveMissionSnapshot:
        cfg.validate()
        if epoch < 1 or epoch > cfg.max_epochs:
            raise ValueError("epoch outside configured range")
        if epoch_start_ms < mission_start_ms:
            raise ValueError("epoch_start_ms cannot precede mission_start_ms")

        total = self.economics(start_ms=mission_start_ms, end_ms=end_ms)
        current_epoch = self.economics(start_ms=epoch_start_ms, end_ms=end_ms)

        mission_floor = -cfg.mission_loss_limit_usd
        epoch_floor = -cfg.epoch_loss_limit_usd
        if total.net_pnl_usd < mission_floor:
            status = LiveMissionStatus.HALT
        elif total.trading_volume_usd >= cfg.target_volume_usd:
            status = LiveMissionStatus.SUCCESS
        elif total.net_pnl_usd <= mission_floor:
            status = LiveMissionStatus.HALT
        elif current_epoch.net_pnl_usd <= epoch_floor:
            status = (
                LiveMissionStatus.HALT
                if epoch >= cfg.max_epochs
                else LiveMissionStatus.EPOCH_STOP
            )
        else:
            status = LiveMissionStatus.RUNNING

        return LiveMissionSnapshot(
            status=status,
            epoch=epoch,
            cumulative_volume_usd=total.trading_volume_usd,
            cumulative_net_pnl_usd=total.net_pnl_usd,
            epoch_net_pnl_usd=current_epoch.net_pnl_usd,
            remaining_volume_usd=max(0.0, cfg.target_volume_usd - total.trading_volume_usd),
            remaining_epoch_loss_buffer_usd=max(
                0.0,
                cfg.epoch_loss_limit_usd + current_epoch.net_pnl_usd,
            ),
            remaining_mission_loss_buffer_usd=max(
                0.0,
                cfg.mission_loss_limit_usd + total.net_pnl_usd,
            ),
            fills=total.fill_count,
            trading_fees_usd=total.trading_fees_usd,
            funding_pnl_usd=total.funding_pnl_usd,
        )

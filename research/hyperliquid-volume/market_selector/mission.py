from __future__ import annotations

from collections import deque
from dataclasses import asdict, dataclass
from enum import Enum
from typing import Deque

from .feedback import ExecutionFeedbackEvent
from .models import SupervisorDecision


class MissionStatus(str, Enum):
    RUNNING = "RUNNING"
    EPOCH_STOP = "EPOCH_STOP"
    SUCCESS = "SUCCESS"
    HALT = "HALT"


@dataclass(frozen=True)
class MissionConfig:
    """Risk-bounded cumulative-volume mission configuration.

    Profit and loss are accounted on a net realized basis. A profitable trade
    therefore increases the remaining loss buffer automatically.
    """

    target_volume_usd: float = 10_000.0
    epoch_loss_limit_usd: float = 5.0
    mission_loss_limit_usd: float = 10.0
    max_epochs: int = 2
    max_seen_ids: int = 100_000

    def validate(self) -> None:
        if self.target_volume_usd <= 0:
            raise ValueError("target_volume_usd must be positive")
        if self.epoch_loss_limit_usd <= 0:
            raise ValueError("epoch_loss_limit_usd must be positive")
        if self.mission_loss_limit_usd <= 0:
            raise ValueError("mission_loss_limit_usd must be positive")
        if self.epoch_loss_limit_usd > self.mission_loss_limit_usd:
            raise ValueError("epoch loss limit cannot exceed mission loss limit")
        if self.max_epochs < 1:
            raise ValueError("max_epochs must be >= 1")


FULL_BETA_MISSION = MissionConfig(
    target_volume_usd=10_000.0,
    epoch_loss_limit_usd=5.0,
    mission_loss_limit_usd=10.0,
    max_epochs=2,
)

# Small live-readiness canary. These limits are intentionally stricter than the
# full mission and can be changed only through an explicit configuration update.
CANARY_MISSION = MissionConfig(
    target_volume_usd=1_000.0,
    epoch_loss_limit_usd=1.0,
    mission_loss_limit_usd=2.0,
    max_epochs=2,
)


@dataclass(frozen=True)
class MissionSnapshot:
    status: MissionStatus
    epoch: int
    cumulative_volume_usd: float
    cumulative_pnl_usd: float
    epoch_pnl_usd: float
    remaining_volume_usd: float
    remaining_epoch_loss_buffer_usd: float
    remaining_mission_loss_buffer_usd: float
    completed_round_trips: int
    winning_round_trips: int
    losing_round_trips: int
    duplicate_events: int

    @property
    def net_cost_usd(self) -> float:
        return max(0.0, -self.cumulative_pnl_usd)


class MissionLedger:
    """Deterministic realized-PnL / cumulative-volume accounting.

    Only finalized filled round trips with both volume and PnL change the mission.
    Unfilled probes do not consume volume or risk budget. Event IDs are deduped so
    reconnect/replay cannot double-count mission progress.
    """

    def __init__(self, cfg: MissionConfig = FULL_BETA_MISSION):
        cfg.validate()
        self.cfg = cfg
        self.status = MissionStatus.RUNNING
        self.epoch = 1
        self.cumulative_volume_usd = 0.0
        self.cumulative_pnl_usd = 0.0
        self.epoch_start_pnl_usd = 0.0
        self.completed_round_trips = 0
        self.winning_round_trips = 0
        self.losing_round_trips = 0
        self.duplicate_events = 0
        self._seen: set[tuple[str, str]] = set()
        self._seen_order: Deque[tuple[str, str]] = deque()

    @property
    def epoch_pnl_usd(self) -> float:
        return self.cumulative_pnl_usd - self.epoch_start_pnl_usd

    def record(self, event: ExecutionFeedbackEvent) -> MissionSnapshot:
        event.validate()
        key = (event.source, event.attempt_id)
        if key in self._seen:
            self.duplicate_events += 1
            return self.snapshot()
        self._remember(key)

        if self.status in {MissionStatus.SUCCESS, MissionStatus.HALT}:
            return self.snapshot()

        if not event.filled:
            return self.snapshot()
        if event.round_trip_volume_usd is None or event.pnl_usd is None:
            raise ValueError("filled mission event requires finalized volume and pnl")

        self.cumulative_volume_usd += event.round_trip_volume_usd
        self.cumulative_pnl_usd += event.pnl_usd
        self.completed_round_trips += 1
        if event.pnl_usd > 0:
            self.winning_round_trips += 1
        elif event.pnl_usd < 0:
            self.losing_round_trips += 1

        self._recompute_status()
        return self.snapshot()

    def begin_next_epoch(self, *, inventory_flat: bool) -> MissionSnapshot:
        if self.status != MissionStatus.EPOCH_STOP:
            raise RuntimeError("next epoch allowed only after EPOCH_STOP")
        if not inventory_flat:
            raise RuntimeError("inventory must be flat before next epoch")
        if self.epoch >= self.cfg.max_epochs:
            self.status = MissionStatus.HALT
            return self.snapshot()
        if self.cumulative_pnl_usd <= -self.cfg.mission_loss_limit_usd:
            self.status = MissionStatus.HALT
            return self.snapshot()

        self.epoch += 1
        self.epoch_start_pnl_usd = self.cumulative_pnl_usd
        self.status = MissionStatus.RUNNING
        return self.snapshot()

    def snapshot(self) -> MissionSnapshot:
        epoch_loss = max(0.0, -self.epoch_pnl_usd)
        mission_loss = max(0.0, -self.cumulative_pnl_usd)
        return MissionSnapshot(
            status=self.status,
            epoch=self.epoch,
            cumulative_volume_usd=self.cumulative_volume_usd,
            cumulative_pnl_usd=self.cumulative_pnl_usd,
            epoch_pnl_usd=self.epoch_pnl_usd,
            remaining_volume_usd=max(
                0.0, self.cfg.target_volume_usd - self.cumulative_volume_usd
            ),
            remaining_epoch_loss_buffer_usd=max(
                0.0, self.cfg.epoch_loss_limit_usd - epoch_loss
            ),
            remaining_mission_loss_buffer_usd=max(
                0.0, self.cfg.mission_loss_limit_usd - mission_loss
            ),
            completed_round_trips=self.completed_round_trips,
            winning_round_trips=self.winning_round_trips,
            losing_round_trips=self.losing_round_trips,
            duplicate_events=self.duplicate_events,
        )

    def to_dict(self) -> dict:
        payload = asdict(self.snapshot())
        payload["status"] = self.status.value
        payload["net_cost_usd"] = self.snapshot().net_cost_usd
        payload["config"] = asdict(self.cfg)
        return payload

    def _recompute_status(self) -> None:
        # Reaching target volume wins immediately at the finalized event boundary.
        if self.cumulative_volume_usd >= self.cfg.target_volume_usd:
            self.status = MissionStatus.SUCCESS
            return

        if self.cumulative_pnl_usd <= -self.cfg.mission_loss_limit_usd:
            self.status = MissionStatus.HALT
            return

        if self.epoch_pnl_usd <= -self.cfg.epoch_loss_limit_usd:
            if self.epoch >= self.cfg.max_epochs:
                self.status = MissionStatus.HALT
            else:
                self.status = MissionStatus.EPOCH_STOP
            return

        self.status = MissionStatus.RUNNING

    def _remember(self, key: tuple[str, str]) -> None:
        self._seen.add(key)
        self._seen_order.append(key)
        while len(self._seen_order) > self.cfg.max_seen_ids:
            expired = self._seen_order.popleft()
            self._seen.discard(expired)


@dataclass(frozen=True)
class ExecutionGateDecision:
    allow_new_entries: bool
    must_flatten: bool
    hard_halt: bool
    reason: str


class MissionRiskGuardian:
    """Combines mission risk with operational prerequisites for one authority.

    This object does not place orders. It answers whether the sole future execution
    authority is permitted to create a NEW position. Closing/reducing exposure must
    remain possible when `must_flatten` is true.
    """

    def evaluate(
        self,
        *,
        mission: MissionSnapshot,
        supervisor: SupervisorDecision,
        feed_healthy: bool,
        reconciliation_ok: bool,
        execution_authority_enabled: bool,
        inventory_flat: bool,
    ) -> ExecutionGateDecision:
        if mission.status == MissionStatus.SUCCESS:
            return ExecutionGateDecision(False, not inventory_flat, False, "mission_complete")
        if mission.status == MissionStatus.HALT:
            return ExecutionGateDecision(False, not inventory_flat, True, "mission_hard_limit")
        if mission.status == MissionStatus.EPOCH_STOP:
            return ExecutionGateDecision(False, not inventory_flat, False, "epoch_loss_limit")
        if not execution_authority_enabled:
            return ExecutionGateDecision(False, False, False, "execution_authority_disabled")
        if not feed_healthy:
            return ExecutionGateDecision(False, not inventory_flat, True, "feed_unhealthy")
        if not reconciliation_ok:
            return ExecutionGateDecision(False, not inventory_flat, True, "reconciliation_failed")
        if not supervisor.allow_new_entries:
            must_flatten = (
                not inventory_flat
                and supervisor.action in {
                    "STOP_NEW_ENTRY_AND_DRAIN",
                    "DRAIN",
                    "DRAIN_FOR_SWITCH",
                    "HALT",
                }
            )
            return ExecutionGateDecision(
                False,
                must_flatten,
                supervisor.action == "HALT",
                f"supervisor:{supervisor.action}",
            )

        return ExecutionGateDecision(True, False, False, "all_gates_pass")

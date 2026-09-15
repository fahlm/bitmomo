from market_selector.live_accounting import (
    HyperliquidMissionAccounting,
    LiveMissionStatus,
)
from market_selector.mission import CANARY_MISSION, FULL_BETA_MISSION


class FakeInfo:
    def __init__(self, fills, funding=None):
        self.fills = list(fills)
        self.funding = list(funding or [])

    def user_fills_by_time(self, address, start_time, end_time=None, aggregate_by_time=False):
        return [
            row for row in self.fills
            if row["time"] >= start_time and (end_time is None or row["time"] <= end_time)
        ]

    def user_funding_history(self, user, startTime, endTime=None):
        return [
            row for row in self.funding
            if row["time"] >= startTime and (endTime is None or row["time"] <= endTime)
        ]


def fill(time, px, sz, closed_pnl, fee):
    return {
        "time": time,
        "px": str(px),
        "sz": str(sz),
        "closedPnl": str(closed_pnl),
        "fee": str(fee),
    }


def funding(time, usdc):
    return {"time": time, "delta": {"usdc": str(usdc)}}


def test_actual_fill_volume_and_fee_net_pnl_include_entry_fees_and_funding():
    info = FakeInfo(
        [
            fill(1_000, 100, 1, 0, 0.05),
            fill(2_000, 101, 1, 1.0, 0.05),
        ],
        [funding(1_500, -0.02)],
    )
    acct = HyperliquidMissionAccounting(info, account_address="0xabc")
    econ = acct.economics(start_ms=900, end_ms=2_100)

    assert econ.fill_count == 2
    assert econ.trading_volume_usd == 201.0
    assert econ.closed_pnl_gross_usd == 1.0
    assert econ.trading_fees_usd == 0.10
    assert econ.funding_pnl_usd == -0.02
    assert round(econ.net_pnl_usd, 8) == 0.88


def test_profit_expands_canary_loss_buffer():
    info = FakeInfo(
        [
            fill(1_000, 100, 1, 0, 0.02),
            fill(2_000, 101, 1, 2.0, 0.02),
        ]
    )
    acct = HyperliquidMissionAccounting(info, account_address="0xabc")
    snap = acct.mission_snapshot(
        cfg=CANARY_MISSION,
        mission_start_ms=900,
        epoch_start_ms=900,
        epoch=1,
        end_ms=2_100,
    )

    assert snap.status == LiveMissionStatus.RUNNING
    assert round(snap.cumulative_net_pnl_usd, 8) == 1.96
    assert round(snap.remaining_mission_loss_buffer_usd, 8) == 3.96
    assert round(snap.remaining_epoch_loss_buffer_usd, 8) == 2.96


def test_epoch_stop_preserves_cumulative_volume_for_next_epoch():
    info = FakeInfo(
        [
            fill(1_000, 100, 2, 0, 0.10),
            fill(2_000, 100, 2, -1.0, 0.10),
        ]
    )
    acct = HyperliquidMissionAccounting(info, account_address="0xabc")
    snap = acct.mission_snapshot(
        cfg=CANARY_MISSION,
        mission_start_ms=900,
        epoch_start_ms=900,
        epoch=1,
        end_ms=2_100,
    )

    assert snap.cumulative_volume_usd == 400.0
    assert round(snap.cumulative_net_pnl_usd, 8) == -1.20
    assert snap.status == LiveMissionStatus.EPOCH_STOP


def test_second_epoch_hitting_epoch_floor_halts():
    info = FakeInfo(
        [
            fill(1_000, 100, 2, 0, 0.10),
            fill(2_000, 100, 2, -1.0, 0.10),
            fill(3_000, 100, 2, 0, 0.10),
            fill(4_000, 100, 2, -1.0, 0.10),
        ]
    )
    acct = HyperliquidMissionAccounting(info, account_address="0xabc")
    snap = acct.mission_snapshot(
        cfg=CANARY_MISSION,
        mission_start_ms=900,
        epoch_start_ms=2_500,
        epoch=2,
        end_ms=4_100,
    )

    assert snap.cumulative_volume_usd == 800.0
    assert snap.status == LiveMissionStatus.HALT


def test_volume_target_at_allowed_floor_is_success_but_overshoot_is_halt():
    cfg = FULL_BETA_MISSION
    success = FakeInfo(
        [fill(1_000, 5_000, 2, -9.0, 1.0)]
    )
    snap = HyperliquidMissionAccounting(success, account_address="0xabc").mission_snapshot(
        cfg=cfg,
        mission_start_ms=900,
        epoch_start_ms=900,
        epoch=1,
        end_ms=1_100,
    )
    assert snap.cumulative_volume_usd == 10_000.0
    assert snap.cumulative_net_pnl_usd == -10.0
    assert snap.status == LiveMissionStatus.SUCCESS

    fail = FakeInfo(
        [fill(1_000, 5_000, 2, -9.1, 1.0)]
    )
    snap2 = HyperliquidMissionAccounting(fail, account_address="0xabc").mission_snapshot(
        cfg=cfg,
        mission_start_ms=900,
        epoch_start_ms=900,
        epoch=1,
        end_ms=1_100,
    )
    assert snap2.cumulative_volume_usd == 10_000.0
    assert snap2.cumulative_net_pnl_usd == -10.1
    assert snap2.status == LiveMissionStatus.HALT

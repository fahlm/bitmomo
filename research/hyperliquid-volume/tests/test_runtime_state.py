from market_selector.config import SelectorConfig
from market_selector.models import MarketHistory, SupervisorState
from market_selector.runtime_state import (
    RuntimeStateStore,
    export_supervisor_state,
    restore_supervisor_state,
)
from market_selector.supervisor import MarketSupervisor


def test_runtime_state_store_round_trip(tmp_path):
    store = RuntimeStateStore(tmp_path / "state.json")
    payload = {"schema_version": 1, "decision": {"action": "IDLE"}}

    store.save(payload)
    assert store.load() == payload


def test_restore_active_market_revokes_stale_authority():
    cfg = SelectorConfig(degradation_windows=2)
    source = MarketSupervisor(cfg)
    source.active_market = "PONS"
    source.pending_market = "VVV"
    source.state = SupervisorState.ACTIVE
    source.histories["PONS"] = MarketHistory(
        qualify_streak=9,
        degrade_streak=0,
        challenger_streaks={"VVV": 4},
    )

    payload = export_supervisor_state(source)

    restored = MarketSupervisor(cfg)
    restore_supervisor_state(restored, payload)

    assert restored.active_market is None
    assert restored.pending_market is None
    assert restored.state == SupervisorState.IDLE
    assert restored.last_restored_active_market == "PONS"
    assert restored.last_restored_pending_market == "VVV"
    assert restored.histories["PONS"].qualify_streak == 0
    assert restored.histories["PONS"].challenger_streaks == {}
    assert restored.histories["PONS"].degrade_streak == 0


def test_restore_without_active_market_starts_idle():
    supervisor = MarketSupervisor(SelectorConfig())
    restore_supervisor_state(
        supervisor,
        {
            "state": "ACTIVE",
            "active_market": None,
            "pending_market": None,
            "histories": {},
        },
    )

    assert supervisor.active_market is None
    assert supervisor.state == SupervisorState.IDLE

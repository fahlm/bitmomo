import json

from market_selector.runtime_healthcheck import check_runtime_state


def write_state(path, *, updated_at_ms=10_000, healthy=True, mode="SHADOW_ONLY", mainnet=False, universe=None):
    payload = {
        "updated_at_ms": updated_at_ms,
        "mode": mode,
        "mainnet_order_submission": mainnet,
        "universe": ["BTC"] if universe is None else universe,
        "transport": {"healthy": healthy},
    }
    path.write_text(json.dumps(payload), encoding="utf-8")


def test_healthy_recent_shadow_state_passes(tmp_path):
    path = tmp_path / "state.json"
    write_state(path, updated_at_ms=10_000)
    result = check_runtime_state(path, now_ms=11_000, max_state_age_seconds=10)
    assert result.healthy is True
    assert result.reasons == ()
    assert result.universe_count == 1


def test_missing_state_fails(tmp_path):
    result = check_runtime_state(tmp_path / "missing.json", now_ms=11_000)
    assert result.healthy is False
    assert result.reasons == ("state_missing",)


def test_stale_or_unhealthy_state_fails(tmp_path):
    path = tmp_path / "state.json"
    write_state(path, updated_at_ms=1_000, healthy=False)
    result = check_runtime_state(path, now_ms=20_000, max_state_age_seconds=5)
    assert result.healthy is False
    assert "transport_unhealthy" in result.reasons
    assert any(reason.startswith("state_stale") for reason in result.reasons)


def test_non_shadow_or_mainnet_enabled_or_empty_universe_fail(tmp_path):
    path = tmp_path / "state.json"
    write_state(
        path,
        updated_at_ms=10_000,
        mode="LIVE",
        mainnet=True,
        universe=[],
    )
    result = check_runtime_state(path, now_ms=11_000)
    assert result.healthy is False
    assert "unexpected_mode:LIVE" in result.reasons
    assert "mainnet_submission_not_false" in result.reasons
    assert "empty_universe" in result.reasons

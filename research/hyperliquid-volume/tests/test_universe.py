from market_selector.universe import UniverseManager


def fixture():
    meta = {
        "universe": [
            {"name": "A"},
            {"name": "B"},
            {"name": "C"},
            {"name": "D"},
            {"name": "X", "isDelisted": True},
        ]
    }
    contexts = [
        {"dayNtlVlm": "500"},
        {"dayNtlVlm": "400"},
        {"dayNtlVlm": "300"},
        {"dayNtlVlm": "200"},
        {"dayNtlVlm": "999"},
    ]
    return meta, contexts


def test_universe_uses_volume_rank_and_excludes_delisted():
    meta, contexts = fixture()
    manager = UniverseManager(
        scan_top=2,
        min_day_volume_usd=100,
        retain_buffer=1,
        refresh_seconds=300,
    )

    snap = manager.build_snapshot(meta, contexts, now=10.0)
    assert snap.coins == ("A", "B")
    assert snap.volume_by_coin() == {"A": 500.0, "B": 400.0}


def test_universe_retention_buffer_prevents_rank_churn():
    meta, contexts = fixture()
    manager = UniverseManager(
        scan_top=2,
        min_day_volume_usd=100,
        retain_buffer=1,
        refresh_seconds=300,
    )
    manager.current = manager.build_snapshot(meta, contexts, now=10.0)

    # B drops from #2 to #3, but remains within scan_top + retain_buffer.
    contexts2 = [
        {"dayNtlVlm": "500"},
        {"dayNtlVlm": "300"},
        {"dayNtlVlm": "400"},
        {"dayNtlVlm": "200"},
        {"dayNtlVlm": "999"},
    ]
    snap = manager.build_snapshot(meta, contexts2, now=20.0)
    assert set(snap.coins) == {"A", "B"}


def test_pinned_market_is_kept_outside_scan_top():
    meta, contexts = fixture()
    manager = UniverseManager(
        scan_top=2,
        min_day_volume_usd=100,
        retain_buffer=0,
        refresh_seconds=300,
    )

    snap = manager.build_snapshot(meta, contexts, pinned=["D"], now=10.0)
    assert snap.coins[:2] == ("A", "B")
    assert "D" in snap.coins


def test_universe_due_uses_refresh_interval():
    meta, contexts = fixture()
    manager = UniverseManager(
        scan_top=2,
        min_day_volume_usd=100,
        retain_buffer=0,
        refresh_seconds=300,
    )
    manager.current = manager.build_snapshot(meta, contexts, now=100.0)

    assert manager.due(now=399.0) is False
    assert manager.due(now=400.0) is True

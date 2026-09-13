from market_selector.transport import FeedHealth


def test_generation_fencing_ignores_old_callbacks():
    health = FeedHealth.for_coins(["VVV", "PONS"], stale_seconds=60)
    g1 = health.start_generation(now=100.0)
    assert health.note_book(g1, "VVV", now=101.0) is True

    g2 = health.start_generation(now=200.0)
    assert g2 == g1 + 1
    assert health.note_book(g1, "VVV", now=201.0) is False
    assert health.note_book(g2, "VVV", now=201.0) is True


def test_all_book_stale_requires_full_watchdog_window():
    health = FeedHealth.for_coins(["VVV", "PONS"], stale_seconds=60)
    generation = health.start_generation(now=100.0)
    health.note_book(generation, "VVV", now=110.0)
    health.note_book(generation, "PONS", now=111.0)

    assert health.all_books_stale(now=160.0) is False
    assert health.all_books_stale(now=172.0) is True


def test_one_live_book_feed_prevents_whole_transport_reconnect():
    health = FeedHealth.for_coins(["VVV", "PONS"], stale_seconds=60)
    generation = health.start_generation(now=100.0)
    health.note_book(generation, "VVV", now=110.0)
    health.note_book(generation, "PONS", now=111.0)
    health.note_book(generation, "VVV", now=170.0)

    assert health.all_books_stale(now=172.0) is False


def test_backoff_grows_and_resets_after_fresh_book():
    health = FeedHealth.for_coins(
        ["VVV"],
        stale_seconds=60,
        initial_backoff_seconds=2,
        max_backoff_seconds=30,
    )
    health.start_generation(now=100.0)

    assert health.register_reconnect() == 2
    assert health.register_reconnect() == 4
    assert health.register_reconnect() == 8

    generation = health.start_generation(now=200.0)
    health.note_book(generation, "VVV", now=201.0)

    assert health.backoff_seconds == 2
    assert health.register_reconnect() == 2


def test_recovery_marks_new_generation_healthy():
    health = FeedHealth.for_coins(["VVV"], stale_seconds=60)
    generation = health.start_generation(now=100.0)
    assert health.healthy is False

    health.note_book(generation, "VVV", now=101.0)
    assert health.healthy is True
    assert health.max_book_age(now=102.0) == 1.0

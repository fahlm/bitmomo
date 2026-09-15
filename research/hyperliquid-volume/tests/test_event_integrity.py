from market_selector.event_integrity import BoundedEventDeduper, LastBookDeduper


def test_bounded_event_deduper_rejects_duplicate_and_evicts_old_keys():
    dedupe = BoundedEventDeduper(max_keys_per_coin=2)

    assert dedupe.accept("PONS", ("id", "1")) is True
    assert dedupe.accept("PONS", ("id", "1")) is False
    assert dedupe.accept("PONS", ("id", "2")) is True
    assert dedupe.accept("PONS", ("id", "3")) is True

    # Key 1 was evicted from the bounded window and may be accepted again.
    assert dedupe.accept("PONS", ("id", "1")) is True
    assert dedupe.duplicates == 1


def test_dedupe_is_scoped_per_coin():
    dedupe = BoundedEventDeduper(max_keys_per_coin=10)

    assert dedupe.accept("PONS", ("id", "42")) is True
    assert dedupe.accept("VVV", ("id", "42")) is True


def test_last_book_deduper_rejects_only_exact_consecutive_signature():
    dedupe = LastBookDeduper()
    sig1 = (1000, (("1", "2", "3"),), (("4", "5", "6"),))
    sig2 = (1001, (("1", "2", "3"),), (("4", "5", "6"),))

    assert dedupe.accept("BTC", sig1) is True
    assert dedupe.accept("BTC", sig1) is False
    assert dedupe.accept("BTC", sig2) is True
    assert dedupe.accept("ETHFI", sig1) is True
    assert dedupe.duplicates == 1

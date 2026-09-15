import csv

from market_selector.raw_capture import RawMultiMarketCapture


def test_raw_capture_writes_replay_compatible_combined_schema(tmp_path):
    capture = RawMultiMarketCapture(tmp_path)
    capture.write_book(
        "PONS",
        {
            "time": 1234,
            "levels": [
                [
                    {"px": "1.00", "sz": "2", "n": 3},
                    {"px": "0.99", "sz": "4", "n": 5},
                ],
                [
                    {"px": "1.01", "sz": "6", "n": 7},
                    {"px": "1.02", "sz": "8", "n": 9},
                ],
            ],
        },
    )
    capture.write_trade(
        "PONS",
        {
            "time": 1250,
            "side": "B",
            "px": "1.01",
            "sz": "10",
            "tid": 42,
            "hash": "0xabc",
        },
    )
    capture.close()

    with (tmp_path / "ALL_books.csv").open(newline="") as handle:
        books = list(csv.DictReader(handle))
    with (tmp_path / "ALL_trades.csv").open(newline="") as handle:
        trades = list(csv.DictReader(handle))

    assert len(books) == 1
    assert books[0]["coin"] == "PONS"
    assert books[0]["exchange_time_ms"] == "1234"
    assert books[0]["bid_px_1"] == "1.00"
    assert books[0]["ask_sz_2"] == "8"
    assert books[0]["bid_px_5"] == ""

    assert len(trades) == 1
    assert trades[0]["coin"] == "PONS"
    assert trades[0]["tid"] == "42"
    assert trades[0]["hash"] == "0xabc"

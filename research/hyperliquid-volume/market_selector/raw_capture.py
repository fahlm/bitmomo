from __future__ import annotations

import csv
import time
from pathlib import Path


class RawMultiMarketCapture:
    """Append-only combined L2/trade capture for SEEN development research.

    Disabled unless explicitly configured by the runtime. Combined files include a
    `coin` column so a single autonomous scanner can replace manual per-coin
    recorder processes while preserving the existing replay schema.
    """

    def __init__(self, directory: str | Path):
        self.directory = Path(directory)
        self.directory.mkdir(parents=True, exist_ok=True)
        self.book_path = self.directory / "ALL_books.csv"
        self.trade_path = self.directory / "ALL_trades.csv"

        self.book_fields = ["coin", "recv_time_ms", "exchange_time_ms"]
        for i in range(1, 6):
            self.book_fields.extend(
                [
                    f"bid_px_{i}",
                    f"bid_sz_{i}",
                    f"bid_n_{i}",
                    f"ask_px_{i}",
                    f"ask_sz_{i}",
                    f"ask_n_{i}",
                ]
            )
        self.trade_fields = [
            "coin",
            "recv_time_ms",
            "exchange_time_ms",
            "side",
            "px",
            "sz",
            "tid",
            "hash",
        ]

        book_has_data = self.book_path.exists() and self.book_path.stat().st_size > 0
        trade_has_data = self.trade_path.exists() and self.trade_path.stat().st_size > 0
        self.book_file = self.book_path.open("a", newline="", encoding="utf-8")
        self.trade_file = self.trade_path.open("a", newline="", encoding="utf-8")
        self.book_writer = csv.DictWriter(self.book_file, fieldnames=self.book_fields)
        self.trade_writer = csv.DictWriter(self.trade_file, fieldnames=self.trade_fields)
        if not book_has_data:
            self.book_writer.writeheader()
            self.book_file.flush()
        if not trade_has_data:
            self.trade_writer.writeheader()
            self.trade_file.flush()
        self.books_written = 0
        self.trades_written = 0

    @staticmethod
    def _level(levels, side_index: int, level_index: int) -> dict:
        side = levels[side_index]
        if level_index >= len(side):
            return {}
        return side[level_index] or {}

    def write_book(self, coin: str, data: dict) -> None:
        levels = data.get("levels") or []
        if len(levels) != 2:
            return
        row = {
            "coin": coin,
            "recv_time_ms": int(time.time() * 1000),
            "exchange_time_ms": int(data.get("time", time.time() * 1000)),
        }
        for i in range(1, 6):
            bid = self._level(levels, 0, i - 1)
            ask = self._level(levels, 1, i - 1)
            row[f"bid_px_{i}"] = bid.get("px", "")
            row[f"bid_sz_{i}"] = bid.get("sz", "")
            row[f"bid_n_{i}"] = bid.get("n", "")
            row[f"ask_px_{i}"] = ask.get("px", "")
            row[f"ask_sz_{i}"] = ask.get("sz", "")
            row[f"ask_n_{i}"] = ask.get("n", "")
        self.book_writer.writerow(row)
        self.book_file.flush()
        self.books_written += 1

    def write_trade(self, coin: str, trade: dict) -> None:
        self.trade_writer.writerow(
            {
                "coin": coin,
                "recv_time_ms": int(time.time() * 1000),
                "exchange_time_ms": int(trade["time"]),
                "side": trade["side"],
                "px": trade["px"],
                "sz": trade["sz"],
                "tid": trade.get("tid", ""),
                "hash": trade.get("hash", ""),
            }
        )
        self.trade_file.flush()
        self.trades_written += 1

    def close(self) -> None:
        self.book_file.close()
        self.trade_file.close()

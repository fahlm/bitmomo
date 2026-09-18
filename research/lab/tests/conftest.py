from __future__ import annotations

import datetime as dt
import hashlib
import io
import pathlib
import zipfile

import pytest

from bitmomo_lab.sources.binance_archive import ArchiveFile, NotFound, RawFile

FIXTURES = pathlib.Path(__file__).resolve().parents[1] / "fixtures"
KLINE_HEADER = ("open_time,open,high,low,close,volume,close_time,quote_volume,count,"
                "taker_buy_volume,taker_buy_quote_volume,ignore")


def utc(*args: int) -> dt.datetime:
    return dt.datetime(*args, tzinfo=dt.timezone.utc)


def us(*args: int) -> int:
    return int(utc(*args).timestamp()) * 1_000_000


def make_zip(csv_name: str, text: str) -> bytes:
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w") as archive:
        info = zipfile.ZipInfo(csv_name, date_time=(2020, 1, 1, 0, 0, 0))
        archive.writestr(info, text)
    return buffer.getvalue()


def kline_rows(start: dt.datetime, count: int, unit: str = "ms", price: float = 100.0,
               skip: set[int] = frozenset()) -> list[str]:
    scale = 1000 if unit == "ms" else 1_000_000
    tail = 1 if unit == "ms" else 1
    rows = []
    for i in range(count):
        if i in skip:
            continue
        open_s = int(start.timestamp()) + i * 300
        o = open_s * scale
        c = (open_s + 300) * scale - tail
        p = price + i
        rows.append(f"{o},{p},{p + 1},{p - 1},{p + 0.5},10.0,{c},1000.0,7,4.0,400.0,0")
    return rows


def raw_file(tmp_path: pathlib.Path, archive_path: str, data: bytes, first: dt.date, last: dt.date,
             period: str = "monthly") -> RawFile:
    local = tmp_path / archive_path
    local.parent.mkdir(parents=True, exist_ok=True)
    local.write_bytes(data)
    stamp = first.isoformat()[:7] if period == "monthly" else first.isoformat()
    return RawFile(f"https://example.invalid/{archive_path}", archive_path, local,
                   hashlib.sha256(data).hexdigest(), len(data), ArchiveFile(period, stamp, first, last))


class FakeArchive:
    """In-memory data.binance.vision: path -> bytes, with published CHECKSUM files."""

    def __init__(self) -> None:
        self.files: dict[str, bytes] = {}
        self.requests: list[str] = []
        self.tamper: set[str] = set()

    def add(self, url: str, data: bytes) -> None:
        self.files[url] = data
        name = url.rsplit("/", 1)[1]
        self.files[url + ".CHECKSUM"] = f"{hashlib.sha256(data).hexdigest()}  {name}\n".encode()

    def __call__(self, url: str) -> bytes:
        self.requests.append(url)
        if url not in self.files:
            raise NotFound(url)
        data = self.files[url]
        return data + b"x" if url in self.tamper else data


@pytest.fixture
def fake_archive() -> FakeArchive:
    return FakeArchive()

"""Public Binance USD-M endpoints recorded forward (no keys, GET only).

These are the short-retention series (REST history ~30 days) that production
consumes or that the historical ``metrics`` archive claims to mirror. Each
parser is strict: any missing key, wrong type or non-finite number raises
``MalformedResponse`` and nothing from that response is stored as normalized.
"""

from __future__ import annotations

import dataclasses
import math
from typing import Any, Callable

from bitmomo_lab.timeutil import INTERVAL_US, epoch_unit, to_us


class MalformedResponse(ValueError):
    pass


@dataclasses.dataclass(frozen=True)
class Endpoint:
    name: str  # recorder dataset suffix
    path: str
    params: tuple[tuple[str, str], ...]
    period: str | None  # None for point-in-time snapshots / event series
    time_key: str
    numeric_keys: tuple[str, ...]
    list_payload: bool
    note: str

    @property
    def dataset(self) -> str:
        return f"binance_um_rest_{self.name}"

    @property
    def period_us(self) -> int:
        return INTERVAL_US[self.period] if self.period else 0


def _num(record: dict, key: str) -> float:
    if key not in record:
        raise MalformedResponse(f"missing key {key!r}")
    raw = record[key]
    if isinstance(raw, bool) or not isinstance(raw, (str, int, float)):
        raise MalformedResponse(f"{key!r} has non-numeric type {type(raw).__name__}")
    try:
        value = float(raw)
    except ValueError:
        raise MalformedResponse(f"{key!r} is not numeric: {raw!r}") from None
    if not math.isfinite(value):
        raise MalformedResponse(f"{key!r} is not finite: {raw!r}")
    return value


def _time(record: dict, key: str) -> int:
    raw = record.get(key)
    if isinstance(raw, bool) or not isinstance(raw, int):
        raise MalformedResponse(f"{key!r} must be an integer epoch, got {raw!r}")
    try:
        return to_us(raw, epoch_unit(raw))
    except ValueError as exc:
        raise MalformedResponse(str(exc)) from None


def parse(endpoint: Endpoint, payload: Any) -> list[dict]:
    """Return one dict per observation: event_time_us + numeric fields."""
    records = payload if endpoint.list_payload else [payload]
    if endpoint.list_payload and not isinstance(payload, list):
        raise MalformedResponse(f"{endpoint.name}: expected a JSON list, got {type(payload).__name__}")
    if not records:
        raise MalformedResponse(f"{endpoint.name}: empty response")
    rows = []
    for record in records:
        if not isinstance(record, dict):
            raise MalformedResponse(f"{endpoint.name}: element is {type(record).__name__}, not object")
        row = {"event_time_us": _time(record, endpoint.time_key)}
        for key in endpoint.numeric_keys:
            row[key] = _num(record, key)
        rows.append(row)
    keys = [r["event_time_us"] for r in rows]
    if len(set(keys)) != len(keys):
        raise MalformedResponse(f"{endpoint.name}: duplicate timestamps within one response")
    return rows


def _hist(name: str, path: str, period: str, numeric: tuple[str, ...], note: str, symbol_param: str = "symbol",
          extra: tuple[tuple[str, str], ...] = ()) -> Endpoint:
    return Endpoint(
        name=f"{name}_{period}",
        path=path,
        params=((symbol_param, "{symbol}"), *extra, ("period", period), ("limit", "30")),
        period=period,
        time_key="timestamp",
        numeric_keys=numeric,
        list_payload=True,
        note=note,
    )


def default_endpoints() -> list[Endpoint]:
    endpoints: list[Endpoint] = []
    for period in ("5m", "1h"):
        endpoints += [
            _hist("open_interest_hist", "/futures/data/openInterestHist", period,
                  ("sumOpenInterest", "sumOpenInterestValue"),
                  "Production uses period=1h, limit=30, field sumOpenInterestValue."),
            _hist("global_long_short_account_ratio", "/futures/data/globalLongShortAccountRatio", period,
                  ("longShortRatio", "longAccount", "shortAccount"),
                  "Production uses period=1h, limit=30, last longShortRatio."),
            _hist("taker_long_short_ratio", "/futures/data/takerlongshortRatio", period,
                  ("buySellRatio", "buyVol", "sellVol"),
                  "Production uses period=1h, limit=30, last buySellRatio. buyVol/sellVol allow exact re-aggregation."),
            _hist("top_long_short_account_ratio", "/futures/data/topLongShortAccountRatio", period,
                  ("longShortRatio", "longAccount", "shortAccount"),
                  "Not consumed by production; recorded to test metrics-archive field equivalence."),
            _hist("top_long_short_position_ratio", "/futures/data/topLongShortPositionRatio", period,
                  ("longShortRatio", "longAccount", "shortAccount"),
                  "Not consumed by production; recorded to test metrics-archive field equivalence."),
            _hist("basis", "/futures/data/basis", period,
                  ("basis", "basisRate", "futuresPrice", "indexPrice"),
                  "REST basis history (~30d retention). annualizedBasisRate is '' for PERPETUAL, so it is "
                  "kept only in the raw envelope.", symbol_param="pair",
                  extra=(("contractType", "PERPETUAL"),)),
        ]
    endpoints += [
        Endpoint("premium_index", "/fapi/v1/premiumIndex", (("symbol", "{symbol}"),), None, "time",
                 ("markPrice", "indexPrice", "lastFundingRate", "interestRate"), False,
                 "Production basis_pct = (markPrice - indexPrice) / indexPrice * 100 from this snapshot."),
        Endpoint("open_interest", "/fapi/v1/openInterest", (("symbol", "{symbol}"),), None, "time",
                 ("openInterest",), False, "Instantaneous OI snapshot."),
        Endpoint("funding_rate", "/fapi/v1/fundingRate", (("symbol", "{symbol}"), ("limit", "21")), None,
                 "fundingTime", ("fundingRate",), True,
                 "Production uses limit=21 and the last settled fundingRate."),
    ]
    return endpoints


EndpointFilter = Callable[[Endpoint], bool]

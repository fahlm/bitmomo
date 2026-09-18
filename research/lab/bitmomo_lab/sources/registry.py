"""Declared source datasets and their point-in-time availability rules.

Each dataset declares *how* ``available_at`` is derived. Rules are
source-specific and documented in
``docs/intelligence-platform/M0_SOURCE_COVERAGE.md``; there is no global lag.
"""

from __future__ import annotations

import dataclasses

from bitmomo_lab.contract import AvailabilityBasis
from bitmomo_lab.timeutil import INTERVAL_US

ARCHIVE_BASE = "https://data.binance.vision/data"
SOURCE = "binance_public_archive"

KLINE_KINDS = ("klines", "markPriceKlines", "indexPriceKlines", "premiumIndexKlines")


@dataclasses.dataclass(frozen=True)
class DatasetSpec:
    dataset: str
    market: str  # "spot" or "futures/um"
    kind: str  # archive folder name: klines, metrics, fundingRate, ...
    interval: str  # nominal cadence ("5m", "8h" for funding)
    parser: str  # "kline" | "metrics" | "funding"
    availability_basis: AvailabilityBasis
    # Extra conservative delay added after the period end / event, in us.
    declared_lag_us: int
    # Length of the period a row describes (0 for point events).
    period_us: int
    monthly: bool
    daily: bool
    availability_note: str

    def archive_path(self, symbol: str, period: str, stamp: str) -> str:
        """Relative archive key for one file (``period`` is 'monthly' or 'daily')."""
        if self.kind in KLINE_KINDS:
            return f"{self.market}/{period}/{self.kind}/{symbol}/{self.interval}/{symbol}-{self.interval}-{stamp}.zip"
        return f"{self.market}/{period}/{self.kind}/{symbol}/{symbol}-{self.kind}-{stamp}.zip"

    def url(self, symbol: str, period: str, stamp: str) -> str:
        return f"{ARCHIVE_BASE}/{self.archive_path(symbol, period, stamp)}"


def _kline(dataset: str, market: str, kind: str, interval: str = "5m") -> DatasetSpec:
    return DatasetSpec(
        dataset=dataset,
        market=market,
        kind=kind,
        interval=interval,
        parser="kline",
        availability_basis=AvailabilityBasis.CLOSE_BOUNDARY,
        declared_lag_us=0,
        period_us=INTERVAL_US[interval],
        monthly=True,
        daily=True,
        availability_note=(
            "Row describes [open_time, open_time+interval). Knowable at the closing boundary; "
            "matches production, which consumes candles with close_time <= cutoff."
        ),
    )


DATASETS: dict[str, DatasetSpec] = {
    spec.dataset: spec
    for spec in (
        _kline("binance_spot_klines_5m", "spot", "klines"),
        _kline("binance_um_klines_5m", "futures/um", "klines"),
        _kline("binance_um_mark_price_klines_5m", "futures/um", "markPriceKlines"),
        _kline("binance_um_index_price_klines_5m", "futures/um", "indexPriceKlines"),
        _kline("binance_um_premium_index_klines_5m", "futures/um", "premiumIndexKlines"),
        DatasetSpec(
            dataset="binance_um_metrics_5m",
            market="futures/um",
            kind="metrics",
            interval="5m",
            parser="metrics",
            availability_basis=AvailabilityBasis.PERIOD_END_PLUS_DECLARED_LAG,
            declared_lag_us=INTERVAL_US["5m"],
            period_us=INTERVAL_US["5m"],
            monthly=False,
            daily=True,
            availability_note=(
                "create_time semantics change over time (taker ratio vs USD-M klines, full history): "
                "2020-09..2024-02 it stamps the END of the 5m period; 2024-03..2025-07 and 2026-04.. it "
                "stamps the START; 2025-08..2026-03 unexplained. event_time = create_time as stamped. "
                "available_at = create_time + 5m + 5m declared lag, which is at or after the period end "
                "under both explained conventions. Publication latency is unobserved; replace the "
                "declared lag with recorder-measured latency. Non-positive OI/ratio values are "
                "sentinels and are nulled (see invalid_value_fields)."
            ),
        ),
        DatasetSpec(
            dataset="binance_um_funding_rate",
            market="futures/um",
            kind="fundingRate",
            interval="8h",
            parser="funding",
            availability_basis=AvailabilityBasis.EVENT_PLUS_DECLARED_LAG,
            declared_lag_us=INTERVAL_US["5m"],
            period_us=0,
            monthly=True,
            daily=False,
            availability_note=(
                "calc_time is the funding settlement time (may carry +1ms jitter). The settled rate "
                "cannot be known before settlement; available_at = calc_time + 5m declared lag."
            ),
        ),
    )
}


def get(dataset: str) -> DatasetSpec:
    try:
        return DATASETS[dataset]
    except KeyError:
        raise KeyError(f"unknown dataset {dataset!r}; known: {sorted(DATASETS)}") from None

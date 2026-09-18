"""opportunity-v1-py — parity port of ``Bitmomo_AI_Opportunity`` (PHP).

Source: website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-opportunity.php
(git blob d6e53044b5d989e04dcf7506e59c4480ca4c89a7). This is a parity port, not a redesign:
input requirements, tie semantics (``<=``), thresholds (>=75 HIGH, <=25 LOW), rounding
(PHP ``round`` half away from zero, 8 / 4 decimals), fail-closed error codes and boundary
behavior are reproduced as implemented. Known questionable behaviors are documented in
docs/intelligence-platform/M1_OPPORTUNITY_V1_PARITY.md and deliberately NOT changed.

Two execution paths, which must agree exactly (tests/test_opportunity_parity.py):

* ``evaluate_rows`` — line-by-line reference port of the PHP pure calculator;
* ``BatchEvaluator`` — an equivalent incremental algorithm for multi-year replay. It
  refuses to run unless the input satisfies the monotone close-boundary availability
  invariant, so it cannot read anything a per-cutoff PIT snapshot could not.
"""

from __future__ import annotations

import bisect
import collections
import dataclasses
import datetime as dt
import decimal
from typing import Any, Iterable, Mapping, Sequence

import pyarrow as pa
import pyarrow.compute as pc

from bitmomo_lab.contract import AvailabilityBasis, Quality
from bitmomo_lab.engines.contract import Engine, InputResolver, Snapshot
from bitmomo_lab.engines.registry import EngineSpec, register

METHODOLOGY_VERSION = "opportunity-v1"
SOURCE = "binance_usdm_btcusdt_5m"
INTERVAL_SECONDS = 300
EVALUATION_SECONDS = 900
REFERENCE_WINDOW_DAYS = 14
REFERENCE_OBSERVATIONS = 1344
TRAILING_CANDLES = 12
REQUIRED_CANDLES = 4044
INPUT_DATASET = "binance_um_klines_5m"

ERR_INSUFFICIENT = "bitmomo_opportunity_insufficient_history"
ERR_GAP = "bitmomo_opportunity_source_gap"
ERR_CUTOFF = "bitmomo_opportunity_cutoff_mismatch"
ERR_REFERENCE = "bitmomo_opportunity_reference_failed"


@dataclasses.dataclass(frozen=True)
class Unavailable:
    """Equivalent of the PHP ``WP_Error`` returned by ``evaluate_rows``."""

    code: str


def php_round(value: float, places: int) -> float:
    """PHP round(): half away from zero on the shortest decimal representation."""
    quantum = decimal.Decimal(1).scaleb(-places)
    return float(decimal.Decimal(repr(float(value))).quantize(quantum, rounding=decimal.ROUND_HALF_UP))


def gmdate_c(timestamp: int) -> str:
    """PHP gmdate('c', ts)."""
    return dt.datetime.fromtimestamp(int(timestamp), tz=dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%S+00:00")


def state_for(percentile: float) -> str:
    return "HIGH" if percentile >= 75 else ("LOW" if percentile <= 25 else "NORMAL")


def cutoff_for(now: int) -> int:
    """PHP evaluate_latest(): quarter-hour cutoff, previous one within the first 30 s."""
    cutoff = (int(now) // EVALUATION_SECONDS) * EVALUATION_SECONDS
    if int(now) - cutoff < 30:
        cutoff -= EVALUATION_SECONDS
    return cutoff


def next_run_timestamp(now: int) -> int:
    """PHP next_run_timestamp(): one minute after the quarter-hour boundary."""
    current_cutoff = (int(now) // EVALUATION_SECONDS) * EVALUATION_SECONDS
    candidate = current_cutoff + 60
    if candidate <= int(now):
        candidate += EVALUATION_SECONDS
    return candidate


def _range_60m_pct(rows: Sequence[Sequence[Any]], end_index: int) -> float:
    start = end_index - TRAILING_CANDLES + 1
    if start < 0:
        return 0
    high = 0.0
    low = None
    for i in range(start, end_index + 1):
        high = max(high, float(rows[i][2]))
        row_low = float(rows[i][3])
        low = row_low if low is None else min(low, row_low)
    return ((high - low) / low) * 100 if low > 0 else 0


def _record(current: float, percentile: float, last_close_ms: int, cutoff: int, evaluated_at: int) -> dict:
    return {
        "methodology_version": METHODOLOGY_VERSION,
        "evaluated_at": gmdate_c(evaluated_at),
        "knowledge_time": gmdate_c(cutoff),
        "state": state_for(percentile),
        "range_60m_pct": php_round(current, 8),
        "activity_percentile": php_round(percentile, 4),
        "reference_window_days": REFERENCE_WINDOW_DAYS,
        "reference_observation_count": REFERENCE_OBSERVATIONS,
        "source": SOURCE,
        "source_last_close_time": gmdate_c(last_close_ms // 1000),
        "source_age_seconds": max(0, evaluated_at - cutoff),
        "source_integrity": "complete",
    }


def evaluate_rows(rows: Iterable[Sequence[Any]], cutoff_timestamp: int, evaluated_at: int | None = None):
    """Line-by-line port of ``Bitmomo_AI_Opportunity::evaluate_rows``.

    ``rows`` are Binance kline arrays ``[open_ms, open, high, low, close, volume, close_ms, ...]``.
    Returns the PHP record dict, or ``Unavailable(code)``.
    """
    cutoff = int(cutoff_timestamp)
    evaluated = cutoff if evaluated_at is None else int(evaluated_at)
    normalized: dict[int, Sequence[Any]] = {}
    for row in rows:
        if not isinstance(row, (list, tuple)) or len(row) < 7:
            continue
        open_ms = int(row[0])
        close_ms = int(row[6])
        if open_ms <= 0 or close_ms <= 0 or close_ms >= (cutoff + 1) * 1000:
            continue
        if float(row[2]) <= 0 or float(row[3]) <= 0:
            continue
        normalized[open_ms] = row  # later duplicates overwrite earlier ones, as in PHP
    ordered = [normalized[k] for k in sorted(normalized)]
    if len(ordered) < REQUIRED_CANDLES:
        return Unavailable(ERR_INSUFFICIENT)
    window = ordered[-REQUIRED_CANDLES:]
    expected_first_open = (cutoff - REQUIRED_CANDLES * INTERVAL_SECONDS) * 1000
    for i in range(REQUIRED_CANDLES):
        if int(window[i][0]) != expected_first_open + i * INTERVAL_SECONDS * 1000:
            return Unavailable(ERR_GAP)
    last = window[-1]
    last_close_ms = int(last[6])
    if int(last[0]) != (cutoff - INTERVAL_SECONDS) * 1000 or last_close_ms > cutoff * 1000:
        return Unavailable(ERR_CUTOFF)

    last_index = len(window) - 1
    current = _range_60m_pct(window, last_index)
    reference = [_range_60m_pct(window, last_index - offset * 3) for offset in range(1, REFERENCE_OBSERVATIONS + 1)]
    if len(reference) != REFERENCE_OBSERVATIONS:
        return Unavailable(ERR_REFERENCE)
    below_or_equal = sum(1 for value in reference if value <= current)
    percentile = 100 * below_or_equal / len(reference)
    return _record(current, percentile, last_close_ms, cutoff, evaluated)


def table_to_rows(table: pa.Table) -> list[list[Any]]:
    """Canonical kline observations -> Binance-style arrays (ms), in event order."""
    table = table.sort_by([("event_time", "ascending")])
    open_ms = [int(v) // 1000 for v in pc.cast(table["event_time"], pa.int64()).to_pylist()]
    close_ms = [int(v) // 1000 for v in pc.cast(table["source_close_time"], pa.int64()).to_pylist()]
    columns = [table[c].to_pylist() for c in ("open", "high", "low", "close", "volume")]
    return [[o, *values, c] for o, *values, c in zip(open_ms, *columns, close_ms)]


SPEC = EngineSpec(
    engine_id="opportunity-v1-py",
    engine_version="1.0.0",
    methodology_version=METHODOLOGY_VERSION,
    required_inputs=(INPUT_DATASET,),
    required_features={"range_60m_pct": "opportunity-v1", "activity_percentile_14d": "opportunity-v1"},
    min_history=f"{REQUIRED_CANDLES} contiguous closed 5m USD-M candles (14 days + 60 minutes)",
    output_schema={
        "status": "available|unavailable",
        "state": "HIGH|NORMAL|LOW|null",
        "range_60m_pct": "float, PHP round 8|null",
        "activity_percentile": "float, PHP round 4|null",
        "reference_observation_count": "int|null",
        "error_code": "PHP WP_Error code|null",
    },
    parameters={
        "interval_seconds": INTERVAL_SECONDS,
        "evaluation_seconds": EVALUATION_SECONDS,
        "reference_observations": REFERENCE_OBSERVATIONS,
        "trailing_candles": TRAILING_CANDLES,
        "required_candles": REQUIRED_CANDLES,
        "high_threshold": 75,
        "low_threshold": 25,
        "tie_rule": "reference <= current",
        "source": SOURCE,
    },
    source_ref="website/wp-content/plugins/bitmomo-ai/includes/class-bitmomo-ai-opportunity.php"
               "@d6e53044b5d989e04dcf7506e59c4480ca4c89a7",
)


def normalize_output(result, cutoff: int) -> dict:
    """Uniform engine output for both available and unavailable evaluations."""
    if isinstance(result, Unavailable):
        return {"status": "unavailable", "state": None, "range_60m_pct": None, "activity_percentile": None,
                "reference_observation_count": None, "error_code": result.code,
                "knowledge_time": gmdate_c(cutoff), "source_last_close_time": None}
    return {"status": "available", "state": result["state"], "range_60m_pct": result["range_60m_pct"],
            "activity_percentile": result["activity_percentile"],
            "reference_observation_count": result["reference_observation_count"], "error_code": None,
            "knowledge_time": result["knowledge_time"], "source_last_close_time": result["source_last_close_time"]}


@register
class OpportunityV1(Engine):
    """Per-cutoff engine: reads the USD-M klines visible at T, mimicking the live fetch.

    Live PHP fetches the latest REQUIRED_CANDLES closed candles by count (paged klines
    ending at T - 1 ms). The PIT equivalent is: rows visible at T, last REQUIRED_CANDLES
    by event time. Opportunity has no neutral fill in production, so strict and
    production-parity modes are identical and emit no imputation flags.
    """

    spec = SPEC

    def compute(self, snapshot: Snapshot, resolver: InputResolver) -> Mapping[str, Any]:
        cutoff = int(snapshot.cutoff.timestamp())
        visible = snapshot.inputs[INPUT_DATASET]
        tail = visible.sort_by([("event_time", "ascending")]).slice(max(0, visible.num_rows - REQUIRED_CANDLES))
        return normalize_output(evaluate_rows(table_to_rows(tail), cutoff), cutoff)


class BatchNotApplicable(ValueError):
    """Input does not satisfy the invariant under which the batch path equals PIT replay."""


class BatchEvaluator:
    """Incremental multi-cutoff evaluation, provably equal to per-cutoff PIT evaluation.

    Precondition (checked): every row is an ``ok`` close-boundary observation with
    ``available_at == event_time + 5m`` and there is exactly one version per event time.
    Then "rows visible at T" == "rows with event_time <= T - 5m", so indexing by position
    reads exactly what ``PITFrame.as_of(T)`` would serve.
    """

    def __init__(self, table: pa.Table) -> None:
        if table.num_rows == 0:
            raise BatchNotApplicable("no rows")
        interval_us = INTERVAL_SECONDS * 1_000_000
        event = pc.cast(table["event_time"], pa.int64())
        available = pc.cast(table["available_at"], pa.int64())
        if not pc.all(pc.equal(table["quality"], Quality.OK.value)).as_py():
            raise BatchNotApplicable("non-ok rows present")
        if not pc.all(pc.equal(table["availability_basis"], AvailabilityBasis.CLOSE_BOUNDARY.value)).as_py():
            raise BatchNotApplicable("availability basis is not close_boundary")
        if not pc.all(pc.equal(available, pc.add(event, interval_us))).as_py():
            raise BatchNotApplicable("available_at != event_time + 5m for some rows")
        rows = table_to_rows(table)
        # Same row filter as the PHP calculator (positive high/low); duplicates -> last wins.
        by_open: dict[int, list] = {}
        for row in rows:
            if row[0] > 0 and row[6] > 0 and float(row[2]) > 0 and float(row[3]) > 0:
                by_open[row[0]] = row
        if len(event) != len(pc.unique(event)):
            raise BatchNotApplicable("multiple versions for one event_time")
        self.open_ms = sorted(by_open)
        self.rows = [by_open[k] for k in self.open_ms]
        step = INTERVAL_SECONDS * 1000
        self.misaligned_prefix = [0]
        for o in self.open_ms:
            self.misaligned_prefix.append(self.misaligned_prefix[-1] + (o % step != 0))
        self.ranges = self._all_ranges()
        self._classes: dict[int, dict] = {}

    def _all_ranges(self) -> list[float | None]:
        """range_60m_pct at every index (same float operations as the reference)."""
        highs = [float(r[2]) for r in self.rows]
        lows = [float(r[3]) for r in self.rows]
        out: list[float | None] = [None] * len(self.rows)
        max_q: collections.deque = collections.deque()
        min_q: collections.deque = collections.deque()
        for i in range(len(self.rows)):
            while max_q and highs[max_q[-1]] <= highs[i]:
                max_q.pop()
            max_q.append(i)
            while min_q and lows[min_q[-1]] >= lows[i]:
                min_q.pop()
            min_q.append(i)
            start = i - TRAILING_CANDLES + 1
            while max_q[0] < start:
                max_q.popleft()
            while min_q[0] < start:
                min_q.popleft()
            if start >= 0:
                high = max(0.0, highs[max_q[0]])
                low = lows[min_q[0]]
                out[i] = ((high - low) / low) * 100 if low > 0 else 0
        return out

    def _count_le(self, last: int, current: float) -> int:
        """#{ranges[last - 3k] <= current : k = 1..1344}, maintained incrementally per residue class."""
        low_index = last - 3 * REFERENCE_OBSERVATIONS
        high_index = last - 3
        cls = self._classes.setdefault(last % 3, {"next": low_index, "window": collections.deque(), "sorted": []})
        if cls["next"] < low_index:  # jumped past a gap: nothing in the old window is reusable
            cls.update(next=low_index, window=collections.deque(), sorted=[])
        while cls["next"] <= high_index:
            value = self.ranges[cls["next"]]
            cls["window"].append((cls["next"], value))
            bisect.insort(cls["sorted"], value)
            cls["next"] += 3
        while cls["window"] and cls["window"][0][0] < low_index:
            _, value = cls["window"].popleft()
            del cls["sorted"][bisect.bisect_left(cls["sorted"], value)]
        return bisect.bisect_right(cls["sorted"], current)

    def evaluate(self, cutoffs: Iterable[int]) -> list[dict]:
        """Evaluate strictly increasing cutoffs (unix seconds); returns normalized outputs."""
        outputs = []
        previous = None
        step = INTERVAL_SECONDS * 1000
        for cutoff in cutoffs:
            if previous is not None and cutoff <= previous:
                raise ValueError("cutoffs must be strictly increasing")
            previous = cutoff
            # Rows the PHP filter keeps at this cutoff: close_ms < (cutoff + 1) * 1000.
            last = bisect.bisect_left(self.open_ms, (cutoff - INTERVAL_SECONDS) * 1000 + 1) - 1
            while last >= 0 and int(self.rows[last][6]) >= (cutoff + 1) * 1000:
                last -= 1
            count = last + 1
            if count < REQUIRED_CANDLES:
                outputs.append(normalize_output(Unavailable(ERR_INSUFFICIENT), cutoff))
                continue
            first = last - REQUIRED_CANDLES + 1
            expected_first = (cutoff - REQUIRED_CANDLES * INTERVAL_SECONDS) * 1000
            contiguous = (
                self.open_ms[first] == expected_first
                and self.open_ms[last] - self.open_ms[first] == (REQUIRED_CANDLES - 1) * step
                and self.misaligned_prefix[last + 1] - self.misaligned_prefix[first] == 0
            )
            if not contiguous:
                outputs.append(normalize_output(Unavailable(ERR_GAP), cutoff))
                continue
            last_close_ms = int(self.rows[last][6])
            if self.open_ms[last] != (cutoff - INTERVAL_SECONDS) * 1000 or last_close_ms > cutoff * 1000:
                outputs.append(normalize_output(Unavailable(ERR_CUTOFF), cutoff))
                continue
            current = self.ranges[last]
            percentile = 100 * self._count_le(last, current) / REFERENCE_OBSERVATIONS
            outputs.append(normalize_output(_record(current, percentile, last_close_ms, cutoff, cutoff), cutoff))
        return outputs

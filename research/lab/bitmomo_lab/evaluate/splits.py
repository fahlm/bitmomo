"""Chronological evaluation plans: segments, purge, walk-forward, and a fitting guard.

No random splits. Segment membership is by decision cutoff; a row is purged from a
segment when its outcome window (cutoff + purge) would cross the segment's end.

Fitting code (baselines now, calibrated models later) may only receive a
``DevelopmentSlice``. Constructing one verifies that no row's outcome window
reaches past the development end, so holdout labels cannot leak into fitting.
"""

from __future__ import annotations

import dataclasses
import datetime as dt
import json
import pathlib
from typing import Sequence


def _ts(text: str) -> int:
    return int(dt.datetime.fromisoformat(text.replace("Z", "+00:00")).timestamp())


class HoldoutAccessError(PermissionError):
    """Fitting code tried to see data outside the development window."""


@dataclasses.dataclass(frozen=True)
class Segment:
    name: str
    start: int  # inclusive, unix seconds
    end: int  # exclusive


@dataclasses.dataclass(frozen=True)
class EvaluationPlan:
    segments: tuple[Segment, ...]
    purge_seconds: int
    first_walk_forward_year: int
    raw: dict

    @classmethod
    def load(cls, path: pathlib.Path) -> "EvaluationPlan":
        raw = json.loads(path.read_text())
        segments = tuple(Segment(name, _ts(a), _ts(b)) for name, (a, b) in raw["segments"].items())
        ordered = sorted(segments, key=lambda s: s.start)
        for a, b in zip(ordered, ordered[1:]):
            if a.end > b.start:
                raise ValueError(f"segments overlap: {a.name} / {b.name}")
        return cls(tuple(ordered), int(raw["purge_seconds"]), int(raw["walk_forward"]["first_test_year"]), raw)

    def segment(self, name: str) -> Segment:
        return next(s for s in self.segments if s.name == name)

    def member_mask(self, cutoffs: Sequence[int], name: str) -> list[bool]:
        seg = self.segment(name)
        return [seg.start <= c and c + self.purge_seconds <= seg.end for c in cutoffs]

    def development_slice(self, cutoffs: Sequence[int], values: Sequence) -> "DevelopmentSlice":
        mask = self.member_mask(cutoffs, "development")
        rows = [(c, v) for c, v, m in zip(cutoffs, values, mask) if m]
        return DevelopmentSlice(rows, self.segment("development").end, self.purge_seconds)

    def walk_forward_folds(self, cutoffs: Sequence[int]) -> list[dict]:
        """Expanding folds by calendar year: train = all earlier years (purged), test = year Y."""
        years = sorted({dt.datetime.fromtimestamp(c, tz=dt.timezone.utc).year for c in cutoffs})
        folds = []
        for year in years:
            if year < self.first_walk_forward_year:
                continue
            start = int(dt.datetime(year, 1, 1, tzinfo=dt.timezone.utc).timestamp())
            end = int(dt.datetime(year + 1, 1, 1, tzinfo=dt.timezone.utc).timestamp())
            folds.append({
                "test_year": year,
                "train_mask": [c + self.purge_seconds <= start for c in cutoffs],
                "test_mask": [start <= c < end for c in cutoffs],
                "train_end": start,
            })
        return folds


class DevelopmentSlice:
    """The only data type fitting functions accept."""

    def __init__(self, rows: list[tuple[int, object]], limit: int, purge_seconds: int) -> None:
        late = [c for c, _ in rows if c + purge_seconds > limit]
        if late:
            raise HoldoutAccessError(f"{len(late)} rows have outcome windows beyond {limit}")
        self.cutoffs = [c for c, _ in rows]
        self.values = [v for _, v in rows]
        self.limit = limit


def fit_quartile_thresholds(data: DevelopmentSlice) -> tuple[float, float]:
    """Baseline fit: 25th / 75th percentiles of a score on development data only."""
    if not isinstance(data, DevelopmentSlice):
        raise HoldoutAccessError("fitting requires a DevelopmentSlice")
    values = sorted(v for v in data.values if v is not None)
    if not values:
        raise ValueError("no development values")

    def q(p: float) -> float:
        pos = (len(values) - 1) * p
        lo = int(pos)
        hi = min(lo + 1, len(values) - 1)
        return values[lo] + (values[hi] - values[lo]) * (pos - lo)

    return q(0.25), q(0.75)


def fixed_state(value: float | None, thresholds: tuple[float, float]) -> str | None:
    if value is None:
        return None
    low, high = thresholds
    return "HIGH" if value >= high else "LOW" if value <= low else "NORMAL"

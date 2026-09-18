"""Evaluation metrics with hand-checkable definitions (see tests/test_evaluate.py)."""

from __future__ import annotations

import random
from typing import Sequence


def average_ranks(values: Sequence[float]) -> list[float]:
    """1-based ranks; ties get the mean of the ranks they span."""
    order = sorted(range(len(values)), key=lambda i: values[i])
    ranks = [0.0] * len(values)
    i = 0
    while i < len(order):
        j = i
        while j + 1 < len(order) and values[order[j + 1]] == values[order[i]]:
            j += 1
        mean_rank = (i + j) / 2 + 1
        for k in range(i, j + 1):
            ranks[order[k]] = mean_rank
        i = j + 1
    return ranks


def auc_from_ranks(ranks: Sequence[float], labels: Sequence[bool]) -> float | None:
    """Mann-Whitney AUC = P(score_pos > score_neg) + 0.5 P(tie). None if one class is empty."""
    positives = sum(1 for y in labels if y)
    negatives = len(labels) - positives
    if positives == 0 or negatives == 0:
        return None
    rank_sum = sum(r for r, y in zip(ranks, labels) if y)
    return (rank_sum - positives * (positives + 1) / 2) / (positives * negatives)


def auc(scores: Sequence[float], labels: Sequence[bool]) -> float | None:
    return auc_from_ranks(average_ranks(scores), labels)


def rate(events: Sequence[bool]) -> float | None:
    return sum(events) / len(events) if events else None


def strictly_ordered(values: Sequence[float | None]) -> bool:
    """True if values[0] > values[1] > ... (all present)."""
    return all(v is not None for v in values) and all(a > b for a, b in zip(values, values[1:]))


def day_bootstrap_spread(day_counts: dict[str, tuple[int, int, int, int]], resamples: int, seed: int) -> dict:
    """CI for event_rate(HIGH) - event_rate(LOW), resampling whole UTC days.

    day_counts[day] = (high_events, high_n, low_events, low_n). Overlapping intraday rows are
    dependent; resampling days keeps within-day dependence intact.
    """
    days = sorted(day_counts)
    if not days:
        return {"estimate": None, "ci95": None, "days": 0}

    def spread(sample: Sequence[str]) -> float | None:
        he = sum(day_counts[d][0] for d in sample)
        hn = sum(day_counts[d][1] for d in sample)
        le = sum(day_counts[d][2] for d in sample)
        ln = sum(day_counts[d][3] for d in sample)
        return he / hn - le / ln if hn and ln else None

    rng = random.Random(seed)
    draws = sorted(s for s in (spread([rng.choice(days) for _ in days]) for _ in range(resamples)) if s is not None)
    lo, hi = draws[int(0.025 * (len(draws) - 1))], draws[int(0.975 * (len(draws) - 1))]
    return {"estimate": spread(days), "ci95": [lo, hi], "days": len(days), "resamples": resamples}

import json

import pytest

from bitmomo_lab.evaluate.metrics import auc, average_ranks, day_bootstrap_spread, strictly_ordered
from bitmomo_lab.evaluate.splits import (
    DevelopmentSlice,
    EvaluationPlan,
    HoldoutAccessError,
    fit_quartile_thresholds,
    fixed_state,
)


def test_average_ranks_with_ties():
    assert average_ranks([10, 20, 20, 30]) == [1, 2.5, 2.5, 4]


def test_auc_hand_computed():
    # positives {3, 2}, negatives {1, 2}: pairs (3>1)=1, (3>2)=1, (2>1)=1, (2=2)=0.5 -> 3.5/4
    assert auc([3, 2, 1, 2], [True, True, False, False]) == pytest.approx(0.875)
    assert auc([1, 2, 3, 4], [False, False, True, True]) == 1.0
    assert auc([1, 1, 1, 1], [True, False, True, False]) == 0.5
    assert auc([1, 2], [True, True]) is None


def test_strictly_ordered():
    assert strictly_ordered([0.9, 0.7, 0.5])
    assert not strictly_ordered([0.9, 0.9, 0.5])
    assert not strictly_ordered([0.9, None, 0.5])


def test_day_bootstrap_is_deterministic_and_brackets_estimate():
    counts = {f"d{i}": (8 + i % 3, 10, 3 + i % 2, 10) for i in range(30)}
    a = day_bootstrap_spread(counts, 200, 1)
    b = day_bootstrap_spread(counts, 200, 1)
    assert a == b
    assert a["ci95"][0] <= a["estimate"] <= a["ci95"][1]


@pytest.fixture
def plan(tmp_path):
    path = tmp_path / "plan.json"
    path.write_text(json.dumps({
        "segments": {"development": ["2020-01-01T00:00:00Z", "2020-01-02T00:00:00Z"],
                     "holdout": ["2020-01-02T00:00:00Z", "2020-01-03T00:00:00Z"]},
        "purge_seconds": 3600, "walk_forward": {"first_test_year": 2020},
    }))
    return EvaluationPlan.load(path)


def test_segments_purge_rows_whose_outcome_crosses_the_boundary(plan):
    dev_end = 1577923200  # 2020-01-02
    cutoffs = [dev_end - 7200, dev_end - 3600, dev_end - 1800, dev_end]
    assert plan.member_mask(cutoffs, "development") == [True, True, False, False]
    assert plan.member_mask(cutoffs, "holdout") == [False, False, False, True]


def test_fitting_only_accepts_development_slices(plan):
    dev_end = 1577923200
    cutoffs = [dev_end - 7200 - 900 * i for i in range(8)]
    thresholds = fit_quartile_thresholds(plan.development_slice(cutoffs, list(range(8))))
    assert thresholds == (1.75, 5.25)
    with pytest.raises(HoldoutAccessError):
        fit_quartile_thresholds([1, 2, 3])
    with pytest.raises(HoldoutAccessError):
        DevelopmentSlice([(dev_end - 60, 1.0)], dev_end, 3600)  # outcome window leaks past dev end


def test_fixed_state():
    assert [fixed_state(v, (1.0, 3.0)) for v in (0.5, 1.0, 2.0, 3.0, None)] == ["LOW", "LOW", "NORMAL", "HIGH", None]


def test_walk_forward_is_expanding_and_purged(tmp_path):
    path = tmp_path / "p.json"
    path.write_text(json.dumps({"segments": {"development": ["2020-01-01T00:00:00Z", "2021-01-01T00:00:00Z"]},
                                "purge_seconds": 3600, "walk_forward": {"first_test_year": 2021}}))
    wf = EvaluationPlan.load(path)
    y2021 = 1609459200
    cutoffs = [y2021 - 7200, y2021 - 1800, y2021, y2021 + 86400 * 400]
    (f2021, f2022) = wf.walk_forward_folds(cutoffs)
    assert f2021["train_mask"] == [True, False, False, False]
    assert f2021["test_mask"] == [False, False, True, False]
    assert f2022["train_mask"] == [True, True, True, False]

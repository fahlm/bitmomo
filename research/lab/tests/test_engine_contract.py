import dataclasses
import json

import pyarrow as pa
import pytest

from bitmomo_lab.contract import TS
from bitmomo_lab.engines.contract import (
    Engine,
    EngineResult,
    ImputationFlag,
    Mode,
    Snapshot,
    StrictModeViolation,
)
from bitmomo_lab.engines.registry import EngineSpec
from bitmomo_lab.store.parquet import ParityContaminationError, write_dataset
from bitmomo_lab.store.pit import PITFrame

from conftest import us, utc


class FundingEcho(Engine):
    """Toy engine mirroring the PHP pattern `(float) ($axis['funding_rate'] ?? 0)`."""

    spec = EngineSpec("funding-echo", "test", "test", ("funding",), {}, "1 row", {"funding_rate": "float|null"},
                      {}, "php: (float) ($axis['funding_rate'] ?? 0)")

    def compute(self, snapshot, resolver):
        rows = snapshot.inputs["funding"]
        raw = rows["value"].to_pylist()[-1] if rows.num_rows else None
        if resolver.mode is Mode.STRICT:
            return {"funding_rate": resolver.value("funding_rate", raw)}
        return {"funding_rate": resolver.parity_fill("funding_rate", raw, 0.0, "php: ($axis['funding_rate'] ?? 0)")}


def funding_frame(available_hour):
    return PITFrame(pa.table({
        "dataset": ["funding"], "symbol": ["BTCUSDT"],
        "event_time": pa.array([us(2026, 9, 1, 8)], pa.int64()).cast(TS),
        "available_at": pa.array([us(2026, 9, 1, available_hour)], pa.int64()).cast(TS),
        "value": [0.0001], "quality": ["ok"],
    }))


def test_strict_mode_keeps_missing_input_missing():
    snapshot = Snapshot.build(utc(2026, 9, 1, 8, 30), {"funding": funding_frame(9)})
    result = FundingEcho().run(snapshot, Mode.STRICT)
    assert result.output == {"funding_rate": None}
    assert [m.field for m in result.missing_inputs] == ["funding_rate"]
    assert result.imputation_flags == ()


def test_parity_mode_fills_with_provenance():
    snapshot = Snapshot.build(utc(2026, 9, 1, 8, 30), {"funding": funding_frame(9)})
    result = FundingEcho().run(snapshot, Mode.PRODUCTION_PARITY)
    assert result.output == {"funding_rate": 0.0}
    (flag,) = result.imputation_flags
    assert (flag.field, flag.original_value, flag.effective_value, flag.production_compatibility_fill) == (
        "funding_rate", None, 0.0, True)


def test_parity_mode_does_not_fill_present_values():
    snapshot = Snapshot.build(utc(2026, 9, 1, 10), {"funding": funding_frame(9)})
    result = FundingEcho().run(snapshot, Mode.PRODUCTION_PARITY)
    assert result.output == {"funding_rate": 0.0001} and result.imputation_flags == ()


def test_parity_fill_in_strict_mode_is_a_hard_error():
    class Cheater(FundingEcho):
        def compute(self, snapshot, resolver):
            return {"x": resolver.parity_fill("x", None, 0, "legacy")}

    with pytest.raises(StrictModeViolation):
        Cheater().run(Snapshot.build(utc(2026, 9, 1), {"funding": funding_frame(9)}), Mode.STRICT)


def test_strict_result_cannot_carry_flags():
    with pytest.raises(StrictModeViolation):
        EngineResult("e", "v", Mode.STRICT, utc(2026, 9, 1), {}, (), (ImputationFlag("f", None, 0, "r"),))


def test_parity_output_cannot_enter_canonical_store(tmp_path):
    snapshot = Snapshot.build(utc(2026, 9, 1, 8, 30), {"funding": funding_frame(9)})
    result = FundingEcho().run(snapshot, Mode.PRODUCTION_PARITY)
    n = 1
    table = pa.table({
        "source": ["lab"] * n, "dataset": ["engine_out"] * n, "symbol": ["BTCUSDT"] * n, "interval": ["15m"] * n,
        "event_time": pa.array([us(2026, 9, 1, 8, 30)], pa.int64()).cast(TS),
        "available_at": pa.array([us(2026, 9, 1, 8, 30)], pa.int64()).cast(TS),
        "availability_basis": ["computed"] * n, "schema_version": ["x"] * n, "quality": ["ok"] * n, "raw_ref": [""] * n,
        "funding_rate": [result.output["funding_rate"]],
        "imputation_flags": [json.dumps([dataclasses.asdict(f) for f in result.imputation_flags])],
    })
    with pytest.raises(ParityContaminationError):
        write_dataset(table, tmp_path / "engine.parquet")


def test_snapshot_only_contains_data_available_at_cutoff():
    snapshot = Snapshot.build(utc(2026, 9, 1, 8, 59), {"funding": funding_frame(9)})
    assert snapshot.inputs["funding"].num_rows == 0


def test_engine_requires_declared_inputs():
    with pytest.raises(KeyError):
        FundingEcho().run(Snapshot(utc(2026, 9, 1), {}), Mode.STRICT)
